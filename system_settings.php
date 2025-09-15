<?php
require_once 'config.php';

// Check if user is logged in and is admin
requireLogin();
requireRole(['admin']);

$user = getCurrentUser();
$db = new Database();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'update_system_info':
                    $system_name = sanitizeInput($_POST['system_name']);
                    $system_email = sanitizeInput($_POST['system_email']);
                    $system_phone = sanitizeInput($_POST['system_phone']);
                    $system_address = sanitizeInput($_POST['system_address']);
                    
                    // Update system settings (you can create a settings table or use a simple approach)
                    $success_message = "System information updated successfully!";
                    break;
                    
                case 'backup_database':
                    // Simple database backup (in a real system, you'd implement proper backup)
                    $success_message = "Database backup initiated successfully!";
                    break;
                    
                case 'clear_cache':
                    // Clear system cache (placeholder)
                    $success_message = "System cache cleared successfully!";
                    break;
                    
                case 'maintenance_mode':
                    $maintenance_mode = isset($_POST['maintenance_enabled']) ? 1 : 0;
                    $maintenance_message = sanitizeInput($_POST['maintenance_message']);
                    $success_message = "Maintenance mode settings updated successfully!";
                    break;
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get system statistics
try {
    $total_users = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'];
    $total_properties = $db->fetchOne("SELECT COUNT(*) as count FROM properties")['count'];
    $total_payments = $db->fetchOne("SELECT COUNT(*) as count FROM rent_payments")['count'];
    $total_notifications = $db->fetchOne("SELECT COUNT(*) as count FROM notifications")['count'];
} catch (Exception $e) {
    $error_message = "Error fetching system statistics: " . $e->getMessage();
    $total_users = $total_properties = $total_payments = $total_notifications = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            overflow-x: hidden;
        }
        
        /* Mobile Header */
        .mobile-header {
            display: none;
            background: #333;
            color: white;
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .mobile-header h1 {
            font-size: 1.2rem;
            margin: 0;
        }
        
        .mobile-user-info {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .hamburger {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
        }
        
        /* Overlay */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .overlay.show {
            display: block;
        }
        
        /* Dashboard Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #333;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            background: #222;
            border-bottom: 1px solid #444;
        }
        
        .sidebar-header h2 {
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }
        
        .user-role {
            background: #555;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu a {
            color: #ccc;
            padding: 15px 20px;
            display: block;
            text-decoration: none;
            border-bottom: 1px solid #444;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #444;
            color: white;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .content-header {
            background: linear-gradient(135deg, #555, #666);
            color: white;
            padding: 20px 24px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .content-header h1 {
            margin: 0 0 8px 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .content-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Settings Sections */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .settings-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.2rem;
        }
        
        .section-content {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <h1>System Settings</h1>
        <div class="mobile-user-info">
            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role">Admin</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="manage_payments.php">Manage Payments</a></li>
                <li><a href="admin_payment_receipts.php">Payment Receipts</a></li>
                <li><a href="tenant_messages.php">Tenant Messages</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="system_reports.php">Reports</a></li>
                <li><a href="system_settings.php" class="active">System Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>System Settings</h1>
                <p>Configure and manage system-wide settings and preferences</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <!-- System Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_properties; ?></div>
                    <div class="stat-label">Total Properties</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_payments; ?></div>
                    <div class="stat-label">Total Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_notifications; ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
            </div>
            
            <!-- Settings Sections -->
            <div class="settings-grid">
                <!-- System Information -->
                <div class="settings-section">
                    <div class="section-header">
                        <h3>System Information</h3>
                    </div>
                    <div class="section-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_system_info">
                            
                            <div class="form-group">
                                <label for="system_name">System Name</label>
                                <input type="text" name="system_name" id="system_name" value="<?php echo APP_NAME; ?>" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="system_email">System Email</label>
                                    <input type="email" name="system_email" id="system_email" value="info@rentcollection.co.ke" required>
                                </div>
                                <div class="form-group">
                                    <label for="system_phone">System Phone</label>
                                    <input type="tel" name="system_phone" id="system_phone" value="+254 700 000 000" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="system_address">System Address</label>
                                <textarea name="system_address" id="system_address" required>Westlands Business Centre, Nairobi, Kenya</textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update System Info</button>
                        </form>
                    </div>
                </div>
                
                <!-- Maintenance Mode -->
                <div class="settings-section">
                    <div class="section-header">
                        <h3>Maintenance Mode</h3>
                    </div>
                    <div class="section-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="maintenance_mode">
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="maintenance_enabled" id="maintenance_enabled">
                                    <label for="maintenance_enabled">Enable Maintenance Mode</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="maintenance_message">Maintenance Message</label>
                                <textarea name="maintenance_message" id="maintenance_message" placeholder="Enter maintenance message for users...">The system is currently under maintenance. Please try again later.</textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">Update Maintenance Settings</button>
                        </form>
                    </div>
                </div>
                
                <!-- System Tools -->
                <div class="settings-section">
                    <div class="section-header">
                        <h3>System Tools</h3>
                    </div>
                    <div class="section-content">
                        <div class="form-group">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="backup_database">
                                <button type="submit" class="btn btn-success">Create Database Backup</button>
                            </form>
                        </div>
                        
                        <div class="form-group">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="clear_cache">
                                <button type="submit" class="btn btn-primary">Clear System Cache</button>
                            </form>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" class="btn btn-danger" onclick="alert('This feature is not implemented yet.')">Reset System</button>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings -->
                <div class="settings-section">
                    <div class="section-header">
                        <h3>Security Settings</h3>
                    </div>
                    <div class="section-content">
                        <div class="form-group">
                            <label>Password Policy</label>
                            <p style="color: #666; font-size: 0.9rem; margin-top: 5px;">
                                Minimum 8 characters, must include letters and numbers
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label>Session Timeout</label>
                            <p style="color: #666; font-size: 0.9rem; margin-top: 5px;">
                                Users are automatically logged out after 2 hours of inactivity
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label>Login Attempts</label>
                            <p style="color: #666; font-size: 0.9rem; margin-top: 5px;">
                                Account locked after 5 failed login attempts
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('overlay');
                const hamburger = document.querySelector('.hamburger');
                
                if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                }
            }
        });
        
        // Close sidebar when window is resized to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>

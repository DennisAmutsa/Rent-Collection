<?php
require_once 'config.php';
requireRole('tenant');

$user = getCurrentUser();
$db = new Database();

// Handle profile update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($full_name && $phone) {
        try {
            // Check if password is being changed
            if ($new_password) {
                if (!$current_password) {
                    $error = "Current password is required to change password";
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = "Current password is incorrect";
                } elseif ($new_password !== $confirm_password) {
                    $error = "New passwords do not match";
                } elseif (strlen($new_password) < 6) {
                    $error = "New password must be at least 6 characters";
                } else {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $db->query(
                        "UPDATE users SET full_name = ?, phone = ?, password = ? WHERE id = ?",
                        [$full_name, $phone, $hashed_password, $user['id']]
                    );
                    $message = "Profile updated successfully!";
                }
            } else {
                // Update without changing password
                $db->query(
                    "UPDATE users SET full_name = ?, phone = ? WHERE id = ?",
                    [$full_name, $phone, $user['id']]
                );
                $message = "Profile updated successfully!";
            }
            
            // Refresh user data
            if ($message) {
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);
            }
        } catch (Exception $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields";
    }
}

// Get tenant's property information
$property = $db->fetchOne(
    "SELECT p.*, tp.move_in_date, tp.is_active
     FROM properties p
     JOIN tenant_properties tp ON p.id = tp.property_id
     WHERE tp.tenant_id = ? AND tp.is_active = 1",
    [$user['id']]
);

// Get payment history
$payments = $db->fetchAll(
    "SELECT * FROM rent_payments 
     WHERE tenant_id = ? 
     ORDER BY payment_date DESC 
     LIMIT 5",
    [$user['id']]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }
        
        .mobile-header {
            display: none;
            background: #333;
            color: white;
            padding: 12px 15px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            align-items: center;
            min-height: 50px;
            box-sizing: border-box;
        }
        
        .hamburger {
            background: none;
            border: none;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            padding: 3px;
            flex-shrink: 0;
            margin-right: 10px;
        }
        
        .mobile-title {
            font-size: 1rem;
            font-weight: 500;
            flex: 1;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
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
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .content-header {
            background: #555;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .content-header h1 {
            margin: 0 0 5px 0;
            font-size: 1.5rem;
        }
        
        .content-header p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f8f8;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h3 {
            margin: 0;
            color: #333;
            font-size: 1rem;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 1.2rem;
            padding: 5px;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #333;
        }
        
        .password-container input {
            padding-right: 40px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #666;
        }
        
        .form-group input:disabled {
            background: #f5f5f5;
            color: #666;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.8rem;
        }
        
        .btn {
            background: #666;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn:hover {
            background: #555;
        }
        
        .btn-primary {
            background: #27ae60;
        }
        
        .btn-primary:hover {
            background: #229954;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #666;
        }
        
        .info-item strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .payment-details strong {
            color: #333;
            font-size: 1.1rem;
        }
        
        .payment-details small {
            color: #666;
            font-size: 0.9rem;
        }
        
        .payment-meta {
            text-align: right;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.open {
            display: block;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 70px;
                padding-left: 15px;
                padding-right: 15px;
                padding-bottom: 15px;
            }
            
            .content-header {
                margin-top: 0;
                padding: 15px;
                position: relative;
                z-index: 1;
            }
            
            .content-header h1 {
                font-size: 1.3rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .card {
                margin-bottom: 15px;
                position: relative;
                z-index: 1;
            }
            
            .card-header {
                padding: 12px 15px;
            }
            
            .card-header h3 {
                font-size: 0.9rem;
            }
            
            .card-body {
                padding: 12px 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .info-item {
                padding: 12px;
            }
            
            .payment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .payment-meta {
                text-align: left;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding-top: 70px;
                padding-left: 10px;
                padding-right: 10px;
                padding-bottom: 10px;
            }
            
            .content-header {
                padding: 12px;
            }
            
            .content-header h1 {
                font-size: 1.2rem;
            }
            
            .card {
                margin-bottom: 10px;
            }
            
            .card-header {
                padding: 10px 12px;
            }
            
            .card-header h3 {
                font-size: 0.85rem;
            }
            
            .card-body {
                padding: 10px 12px;
            }
            
            .form-group input {
                padding: 8px;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
            
            .info-item {
                padding: 10px;
            }
            
            .payment-item {
                padding: 12px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <span class="mobile-title">Profile</span>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role">Tenant</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="tenant_dashboard.php">Dashboard</a></li>
                <li><a href="tenant_receipts.php">Receipts</a></li>
                <li><a href="my_notifications.php">Notifications</a></li>
                <li><a href="my_profile.php" class="active">Profile</a></li>
                <li><a href="contact_management.php">Contact Management</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>My Profile</h1>
                <p>Manage your account information and settings</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Profile Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Update Profile</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small>Username cannot be changed</small>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small>Email cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($user['phone']); ?>"
                                       placeholder="e.g., +254 700 000 000">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <div class="password-container">
                                    <input type="password" id="current_password" name="current_password"
                                           placeholder="Enter current password to change">
                                    <button type="button" class="password-toggle" onclick="togglePassword('current_password')">👁️</button>
                                </div>
                                <small>Required only if changing password</small>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div class="password-container">
                                    <input type="password" id="new_password" name="new_password"
                                           placeholder="Enter new password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">👁️</button>
                                </div>
                                <small>Leave blank to keep current password</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-container">
                                <input type="password" id="confirm_password" name="confirm_password"
                                       placeholder="Confirm new password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">👁️</button>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <!-- Property Information -->
            <?php if ($property): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Your Property</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Property Name</strong>
                            <?php echo htmlspecialchars($property['property_name']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Address</strong>
                            <?php echo htmlspecialchars($property['address']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Unit Number</strong>
                            <?php echo htmlspecialchars($property['unit_number'] ?: 'N/A'); ?>
                        </div>
                        <div class="info-item">
                            <strong>Property Type</strong>
                            <?php echo htmlspecialchars($property['property_type'] ?: 'N/A'); ?>
                        </div>
                        <div class="info-item">
                            <strong>Move-in Date</strong>
                            <?php echo formatDate($property['move_in_date']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Status</strong>
                            <span class="status-badge status-paid">Active</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Payments -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Payments</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <div style="text-align: center; color: #666; padding: 20px;">
                            <p>No payments found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-item">
                                <div class="payment-details">
                                    <strong><?php echo formatCurrency($payment['amount']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($payment['payment_method']); ?></small>
                                </div>
                                <div class="payment-meta">
                                    <span class="status-badge status-paid">Paid</span><br>
                                    <small><?php echo formatDate($payment['payment_date']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="tenant_receipts.php" class="btn btn-primary">View All Receipts</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Account Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Account Created</strong>
                            <?php echo formatDate($user['created_at']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Last Updated</strong>
                            <?php echo formatDate($user['updated_at']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Account Status</strong>
                            <span class="status-badge status-paid">Active</span>
                        </div>
                        <div class="info-item">
                            <strong>User Role</strong>
                            <span class="status-badge status-paid">Tenant</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        }
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '🙈';
            } else {
                field.type = 'password';
                button.textContent = '👁️';
            }
        }
        
        // Close sidebar when clicking on menu items
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', closeSidebar);
        });
        
        // Close sidebar on window resize if it's open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>


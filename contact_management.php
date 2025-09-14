<?php
require_once 'config.php';
requireRole('tenant');

$user = getCurrentUser();
$db = new Database();

// Handle message submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = sanitizeInput($_POST['subject']);
    $message_text = sanitizeInput($_POST['message']);
    $priority = sanitizeInput($_POST['priority']);
    $category = sanitizeInput($_POST['category']);
    
    if ($subject && $message_text) {
        try {
            // Always send to admin - get any admin user
            $admin = $db->fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            
            if ($admin) {
                // Insert message into database
                $db->query(
                    "INSERT INTO tenant_messages (tenant_id, admin_id, subject, message, priority, category, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                    [$user['id'], $admin['id'], $subject, $message_text, $priority, $category]
                );
                
                $message = "Your message has been sent successfully! The administrator will review and respond soon.";
            } else {
                $error = "No administrator found. Please contact support.";
            }
        } catch (Exception $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields";
    }
}

// Get tenant's sent messages
$sent_messages = $db->fetchAll(
    "SELECT * FROM tenant_messages 
     WHERE tenant_id = ? 
     ORDER BY created_at DESC",
    [$user['id']]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Management - <?php echo APP_NAME; ?></title>
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
            background: linear-gradient(135deg, #555, #666);
            color: white;
            padding: 20px 24px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .content-header h1 {
            margin: 0 0 8px 0;
            font-size: 1.6rem;
            font-weight: 700;
        }
        
        .content-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
            line-height: 1.5;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            overflow: hidden;
            border: 1px solid #f0f0f0;
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 16px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 16px 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-group small {
            display: block;
            margin-top: 6px;
            color: #666;
            font-size: 0.85rem;
            font-style: italic;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafafa;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #555;
            background: white;
            box-shadow: 0 0 0 3px rgba(85, 85, 85, 0.1);
        }
        
        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #ccc;
            background: white;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            line-height: 1.4;
        }
        
        .form-group select {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .btn {
            background: #666;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn:hover {
            background: #555;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #229954, #27ae60);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .alert-success {
            background: #f0f9f4;
            color: #166534;
            border-left-color: #22c55e;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
            border: 1px solid #fecaca;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-replied {
            background: #d4edda;
            color: #155724;
        }
        
        .status-resolved {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .priority-low {
            background: #d4edda;
            color: #155724;
        }
        
        .message-item {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .message-subject {
            font-weight: 600;
            color: #333;
        }
        
        .message-meta {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .message-content {
            color: #666;
            margin-bottom: 10px;
        }
        
        .message-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #666;
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
            
            .message-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .message-meta {
                flex-wrap: wrap;
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
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 8px;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
            
            .message-item {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <span class="mobile-title">Contact</span>
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
                <li><a href="my_profile.php">Profile</a></li>
                <li><a href="contact_management.php" class="active">Contact Management</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Contact Management</h1>
                <p>Send messages to the administrator for rent delays, repairs, or general inquiries</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Send Message Form -->
            <div class="card">
                <div class="card-header">
                    <h3>Send Message to Administrator</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required>
                                    <option value="">Select category</option>
                                    <option value="rent_payment">Rent Payment</option>
                                    <option value="repair_request">Repair Request</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="general_inquiry">General Inquiry</option>
                                    <option value="complaint">Complaint</option>
                                    <option value="suggestion">Suggestion</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="priority">Priority *</label>
                                <select id="priority" name="priority" required>
                                    <option value="">Select priority</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <input type="text" id="subject" name="subject" required
                                   placeholder="Brief description of your message">
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" required
                                      placeholder="Please provide detailed information about your request or issue..."></textarea>
                        </div>
                        
                        <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
            
            <!-- Message History -->
            <div class="card">
                <div class="card-header">
                    <h3>Your Messages</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sent_messages)): ?>
                        <div style="text-align: center; color: #666; padding: 40px 20px;">
                            <div style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;">📧</div>
                            <h4 style="margin: 0 0 8px 0; color: #333; font-weight: 600;">No messages sent yet</h4>
                            <p style="margin: 0; font-size: 0.9rem;">Send your first message to the administrator using the form above.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sent_messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                    <div class="message-meta">
                                        <span class="status-badge status-<?php echo $msg['status']; ?>">
                                            <?php echo ucfirst($msg['status']); ?>
                                        </span>
                                        <span class="priority-badge priority-<?php echo $msg['priority']; ?>">
                                            <?php echo ucfirst($msg['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>
                                <div class="message-footer">
                                    <span><strong>Category:</strong> <?php echo ucfirst(str_replace('_', ' ', $msg['category'])); ?></span>
                                    <span><strong>Sent to:</strong> Administrator</span>
                                    <span><?php echo formatDate($msg['created_at']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

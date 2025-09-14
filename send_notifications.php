<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin') && !hasRole('landlord')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Handle notification sending
$message = '';
$error = '';

// Send notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $recipient_type = sanitizeInput($_POST['recipient_type']);
    $subject = sanitizeInput($_POST['subject']);
    $message_text = sanitizeInput($_POST['message']);
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    
    if ($subject && $message_text) {
        try {
            // Determine recipients
            $recipients = [];
            if ($recipient_type === 'all') {
                $recipients = $db->fetchAll("SELECT * FROM users WHERE role = 'tenant' AND is_active = 1");
            } elseif ($recipient_type === 'my_tenants') {
                $recipients = $db->fetchAll(
                    "SELECT DISTINCT u.* FROM users u
                     JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
                     JOIN properties p ON tp.property_id = p.id
                     WHERE p.landlord_id = ? AND u.role = 'tenant'",
                    [$user['id']]
                );
            } elseif ($recipient_type === 'specific') {
                $specific_tenant = sanitizeInput($_POST['specific_tenant']);
                if ($specific_tenant) {
                    $recipients = $db->fetchAll("SELECT * FROM users WHERE id = ? AND role = 'tenant'", [$specific_tenant]);
                }
            }
            
            $sent_count = 0;
            foreach ($recipients as $recipient) {
                // Store in database
                $db->query(
                    "INSERT INTO notifications (user_id, title, message, created_by) VALUES (?, ?, ?, ?)",
                    [$recipient['id'], $subject, $message_text, $user['id']]
                );
                
                // Send email if requested
                if ($send_email && $recipient['email']) {
                    $email_sent = mail(
                        $recipient['email'],
                        $subject,
                        $message_text,
                        "From: " . APP_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">"
                    );
                    if ($email_sent) {
                        $sent_count++;
                    }
                } else {
                    $sent_count++;
                }
            }
            
            $message = "Notification sent to " . count($recipients) . " recipient(s) successfully!";
            if ($send_email) {
                $message .= " Email notifications sent to " . $sent_count . " recipient(s).";
            }
            
        } catch (Exception $e) {
            $error = "Error sending notification: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in subject and message";
    }
}

// Get landlord's tenants for specific selection
$myTenants = $db->fetchAll(
    "SELECT DISTINCT u.id, u.full_name, u.email, p.property_name
     FROM users u
     JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
     JOIN properties p ON tp.property_id = p.id
     WHERE p.landlord_id = ? AND u.role = 'tenant'
     ORDER BY u.full_name",
    [$user['id']]
);

// Get all tenants for general selection
$allTenants = $db->fetchAll(
    "SELECT * FROM users WHERE role = 'tenant' AND is_active = 1 ORDER BY full_name"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications - <?php echo APP_NAME; ?></title>
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
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
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
        
        .mobile-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .mobile-header h1 {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .hamburger {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.3s ease;
        }
        
        .hamburger:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .hamburger.active {
            background: rgba(255,255,255,0.2);
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
            width: calc(100% - 250px);
            overflow-x: hidden;
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
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #f0f0f0;
            margin-bottom: 20px;
            width: 100%;
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
        
        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #555;
            background: white;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        
        /* Buttons */
        .btn {
            background: #666;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        
        .btn:hover {
            background: #555;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #229954, #27ae60);
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
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
        
        /* Quick Templates */
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .template-btn {
            background: #666;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .template-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .template-btn.rent-reminder {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .template-btn.maintenance {
            background: linear-gradient(135deg, #e67e22, #d35400);
        }
        
        .template-btn.welcome {
            background: linear-gradient(135deg, #27ae60, #229954);
        }
        
        .template-btn.general {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .mobile-header {
                display: block;
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
                padding: 80px 15px 15px 15px;
                width: 100%;
            }
            
            .content-header {
                margin-top: 0;
                padding: 15px 20px;
            }
            
            .content-header h1 {
                font-size: 1.3rem;
            }
            
            .card-body {
                padding: 12px 15px;
            }
            
            .template-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .template-btn {
                padding: 10px 12px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 80px 10px 10px 10px;
            }
            
            .content-header {
                padding: 12px 15px;
            }
            
            .content-header h1 {
                font-size: 1.2rem;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }
        
        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        @media (max-width: 768px) {
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <h1>Send Notifications</h1>
            <button class="hamburger" onclick="toggleSidebar()">☰</button>
        </div>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
            </div>
            <ul class="sidebar-menu">
                <?php if ($user['role'] === 'admin'): ?>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="tenant_messages.php">Tenant Messages</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="send_notifications.php" class="active">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php else: ?>
                    <li><a href="landlord_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="manage_tenants.php">Manage Tenants</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="send_notifications.php" class="active">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>📧 Send Notifications</h1>
                <p><?php echo $user['role'] === 'admin' ? 'Send messages and notifications to all tenants' : 'Send messages and notifications to your tenants'; ?></p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Send Notification Form -->
            <div class="card">
                <div class="card-header">
                    <h3>📨 Create New Notification</h3>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label for="recipient_type">Send To *</label>
                        <select id="recipient_type" name="recipient_type" required onchange="toggleSpecificTenant()">
                            <option value="">Choose recipients</option>
                            <?php if ($user['role'] === 'admin'): ?>
                                <option value="all">All Tenants (<?php echo count($allTenants); ?>)</option>
                                <option value="specific">Specific Tenant</option>
                            <?php else: ?>
                                <option value="my_tenants">My Tenants Only (<?php echo count($myTenants); ?>)</option>
                                <option value="all">All Tenants (<?php echo count($allTenants); ?>)</option>
                                <option value="specific">Specific Tenant</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="specific_tenant_group" style="display: none;">
                        <label for="specific_tenant">Select Tenant</label>
                        <select id="specific_tenant" name="specific_tenant">
                            <option value="">Choose a tenant</option>
                            <?php foreach ($allTenants as $tenant): ?>
                                <option value="<?php echo $tenant['id']; ?>">
                                    <?php echo htmlspecialchars($tenant['full_name'] . ' (' . $tenant['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <input type="text" id="subject" name="subject" required
                               placeholder="e.g., Rent Reminder, Maintenance Notice">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" required rows="6"
                                  placeholder="Type your message here..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="send_email" value="1">
                            Also send as email notification
                        </label>
                    </div>
                    
                    <button type="submit" name="send_notification" class="btn btn-primary">Send Notification</button>
                </form>
            </div>
            
            <!-- Quick Templates -->
            <div class="card">
                <div class="card-header">
                    <h3>📝 Quick Templates</h3>
                </div>
                <div class="template-grid">
                    <button type="button" class="template-btn rent-reminder" onclick="useTemplate('rent_reminder')">
                        💰 Rent Reminder
                    </button>
                    <button type="button" class="template-btn maintenance" onclick="useTemplate('maintenance')">
                        🔧 Maintenance Notice
                    </button>
                    <button type="button" class="template-btn welcome" onclick="useTemplate('welcome')">
                        👋 Welcome Message
                    </button>
                    <button type="button" class="template-btn general" onclick="useTemplate('general')">
                        📢 General Notice
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mobile sidebar functions
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const hamburger = document.querySelector('.hamburger');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
            hamburger.classList.toggle('active');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const hamburger = document.querySelector('.hamburger');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            hamburger.classList.remove('active');
        }
        
        // Close sidebar when clicking on menu links (mobile)
        document.addEventListener('DOMContentLoaded', function() {
            const menuLinks = document.querySelectorAll('.sidebar-menu a');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });
        });
        
        // Form functions
        function toggleSpecificTenant() {
            const recipientType = document.getElementById('recipient_type').value;
            const specificGroup = document.getElementById('specific_tenant_group');
            
            if (recipientType === 'specific') {
                specificGroup.style.display = 'block';
            } else {
                specificGroup.style.display = 'none';
            }
        }
        
        function useTemplate(template) {
            const templates = {
                rent_reminder: {
                    subject: 'Rent Payment Reminder',
                    message: 'Dear Tenant,\n\nThis is a friendly reminder that your rent payment is due. Please ensure payment is made by the due date to avoid any late fees.\n\nThank you for your prompt attention to this matter.\n\nBest regards,\nProperty Management'
                },
                maintenance: {
                    subject: 'Maintenance Notice',
                    message: 'Dear Tenant,\n\nWe will be conducting scheduled maintenance on the property. Please be advised of any temporary inconveniences this may cause.\n\nIf you have any questions or concerns, please contact us immediately.\n\nThank you for your understanding.\n\nProperty Management'
                },
                welcome: {
                    subject: 'Welcome to Your New Home',
                    message: 'Dear New Tenant,\n\nWelcome to your new home! We are excited to have you as part of our community.\n\nIf you need any assistance or have questions, please don\'t hesitate to contact us.\n\nWe hope you enjoy your stay!\n\nBest regards,\nProperty Management'
                },
                general: {
                    subject: 'Important Notice',
                    message: 'Dear Tenant,\n\nPlease be advised of the following important information regarding your tenancy.\n\nWe appreciate your attention to this matter.\n\nThank you,\nProperty Management'
                }
            };
            
            if (templates[template]) {
                document.getElementById('subject').value = templates[template].subject;
                document.getElementById('message').value = templates[template].message;
            }
        }
    </script>
</body>
</html>

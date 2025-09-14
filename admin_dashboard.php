<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Handle message reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message_id = $_POST['message_id'];
    $reply = sanitizeInput($_POST['reply']);
    
    if ($reply) {
        try {
            $db->query(
                "UPDATE tenant_messages SET admin_reply = ?, status = 'replied', replied_at = NOW() WHERE id = ?",
                [$reply, $message_id]
            );
            $success_message = "Reply sent successfully!";
        } catch (Exception $e) {
            $error_message = "Error sending reply: " . $e->getMessage();
        }
    }
}

// Handle sending notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $send_to_type = $_POST['send_to_type'];
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    $type = $_POST['type'];
    
    // Debug: Log the received data
    error_log("Notification Debug - Send to type: " . $send_to_type);
    error_log("Notification Debug - Subject: " . $subject);
    
    if ($subject && $message) {
        try {
            $recipients = [];
            
            // Determine recipients based on send_to_type
            switch ($send_to_type) {
                case 'all_tenants':
                    $recipients = $db->fetchAll("SELECT id FROM users WHERE role = 'tenant' AND is_active = 1");
                    error_log("Notification Debug - Found " . count($recipients) . " tenants");
                    break;
                case 'specific_tenant':
                    if (isset($_POST['recipient_id']) && $_POST['recipient_id']) {
                        $recipients = [['id' => $_POST['recipient_id']]];
                    }
                    break;
                case 'all_landlords':
                    $recipients = $db->fetchAll("SELECT id FROM users WHERE role = 'landlord' AND is_active = 1");
                    break;
                case 'specific_landlord':
                    if (isset($_POST['recipient_id']) && $_POST['recipient_id']) {
                        $recipients = [['id' => $_POST['recipient_id']]];
                    }
                    break;
                case 'all_admins':
                    $recipients = $db->fetchAll("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
                    break;
                case 'specific_admin':
                    if (isset($_POST['recipient_id']) && $_POST['recipient_id']) {
                        $recipients = [['id' => $_POST['recipient_id']]];
                    }
                    break;
            }
            
            // Send notification to all recipients
            $sent_count = 0;
            foreach ($recipients as $recipient) {
                $db->query(
                    "INSERT INTO notifications (user_id, created_by, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                    [$recipient['id'], $user['id'], $subject, $message, $type]
                );
                $sent_count++;
            }
            
            if ($sent_count > 0) {
                $success_message = "Notification sent successfully to {$sent_count} recipient(s)!";
            } else {
                $error_message = "No recipients found for the selected option.";
            }
        } catch (Exception $e) {
            $error_message = "Error sending notification: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Get statistics
$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
$totalProperties = $db->fetchOne("SELECT COUNT(*) as count FROM properties")['count'];
$totalPayments = $db->fetchOne("SELECT COUNT(*) as count FROM rent_payments")['count'];
$totalMessages = $db->fetchOne("SELECT COUNT(*) as count FROM tenant_messages")['count'];

// Get recent tenant messages
$recentMessages = $db->fetchAll(
    "SELECT tm.*, u.full_name as tenant_name, u.email as tenant_email
     FROM tenant_messages tm
     JOIN users u ON tm.tenant_id = u.id
     ORDER BY tm.created_at DESC
     LIMIT 10"
);

// Get all users for notification dropdown
$allUsers = $db->fetchAll("SELECT id, full_name, email, role FROM users WHERE is_active = 1 ORDER BY role, full_name");

// Get recent payments
$recentPayments = $db->fetchAll(
    "SELECT rp.*, u.full_name as tenant_name, p.property_name
     FROM rent_payments rp
     JOIN users u ON rp.tenant_id = u.id
     JOIN properties p ON rp.property_id = p.id
     ORDER BY rp.payment_date DESC
     LIMIT 10"
);

// Get recent user registrations
$recentUsers = $db->fetchAll(
    "SELECT * FROM users ORDER BY created_at DESC LIMIT 5"
);

// Get pending messages count
$pendingMessages = $db->fetchOne("SELECT COUNT(*) as count FROM tenant_messages WHERE status = 'pending'")['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
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
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
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
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 4px solid #666;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card:nth-child(1) { border-left-color: #3498db; }
        .stat-card:nth-child(2) { border-left-color: #e74c3c; }
        .stat-card:nth-child(3) { border-left-color: #f39c12; }
        .stat-card:nth-child(4) { border-left-color: #27ae60; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
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
        
        .message-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .message-subject {
            font-weight: 600;
            color: #333;
            flex: 1;
            min-width: 200px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-replied {
            background: #d4edda;
            color: #155724;
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
            min-height: 80px;
        }
        
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
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 12px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
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
            }
            
            .content-header {
                margin-top: 0;
                padding: 15px 20px;
            }
            
            .content-header h1 {
                font-size: 1.3rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .card-body {
                padding: 12px 15px;
            }
            
            .message-item {
                padding: 12px;
            }
            
            .message-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .message-subject {
                min-width: auto;
                margin-bottom: 8px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 80px 10px 10px 10px;
            }
            
            .content-header {
                padding: 12px 15px;
            }
            
            .content-header h1 {
                font-size: 1.2rem;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.5rem;
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
            <h1>Admin Dashboard</h1>
            <button class="hamburger" onclick="toggleSidebar()">☰</button>
        </div>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role">Admin</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="manage_payments.php">Manage Payments</a></li>
                <li><a href="admin_payment_receipts.php">Payment Receipts</a></li>
                <li><a href="tenant_messages.php">Tenant Messages</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="system_reports.php">Reports</a></li>
                <li><a href="system_settings.php">System Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Admin Dashboard</h1>
                <p>Manage the entire rent collection system</p>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalProperties; ?></div>
                    <div class="stat-label">Properties</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalPayments; ?></div>
                    <div class="stat-label">Total Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pendingMessages; ?></div>
                    <div class="stat-label">Pending Messages</div>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Recent Tenant Messages -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Tenant Messages</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentMessages)): ?>
                            <p>No messages from tenants yet.</p>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($recentMessages as $msg): ?>
                                    <div class="message-item">
                                        <div class="message-header">
                                            <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                            <div>
                                                <span class="status-badge status-<?php echo $msg['status']; ?>">
                                                    <?php echo ucfirst($msg['status']); ?>
                                                </span>
                                                <span class="status-badge priority-<?php echo $msg['priority']; ?>">
                                                    <?php echo ucfirst($msg['priority']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div style="margin-bottom: 10px; color: #666; font-size: 0.9rem;">
                                            From: <?php echo htmlspecialchars($msg['tenant_name']); ?> (<?php echo htmlspecialchars($msg['tenant_email']); ?>)
                                        </div>
                                        <div style="margin-bottom: 10px; color: #333;">
                                            <?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 100))); ?>
                                            <?php if (strlen($msg['message']) > 100): ?>...<?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #666; margin-bottom: 10px;">
                                            Category: <?php echo ucfirst(str_replace('_', ' ', $msg['category'])); ?> | 
                                            <?php echo formatDate($msg['created_at']); ?>
                                        </div>
                                        <?php if ($msg['status'] === 'pending'): ?>
                                            <button class="btn btn-primary btn-small" onclick="openReplyModal(<?php echo $msg['id']; ?>, '<?php echo htmlspecialchars($msg['subject']); ?>')">
                                                Reply
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Payments -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Payments</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentPayments)): ?>
                            <p>No payments recorded yet.</p>
                        <?php else: ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Tenant</th>
                                            <th>Property</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPayments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <button class="btn btn-primary" onclick="openNotificationModal()">Send Notification</button>
                        <a href="manage_users.php" class="btn">Manage Users</a>
                        <a href="manage_properties.php" class="btn">Manage Properties</a>
                        <a href="system_reports.php" class="btn">View Reports</a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Templates -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Templates (Tenants Only)</h3>
                </div>
                <div class="card-body">
                    <div style="padding: 10px; background: #e8f4fd; border: 1px solid #b3d9ff; border-radius: 6px; margin-bottom: 15px; color: #0066cc; font-size: 0.9rem;">
                        <strong>ℹ️ Note:</strong> These templates are designed for tenant communications. Landlords are excluded from recipient lists.
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <button type="button" class="btn" onclick="useTemplate('rent_reminder')" 
                                style="background: #3498db; color: white; padding: 10px;">
                            Rent Reminder
                        </button>
                        <button type="button" class="btn" onclick="useTemplate('maintenance')" 
                                style="background: #e67e22; color: white; padding: 10px;">
                            Maintenance Notice
                        </button>
                        <button type="button" class="btn" onclick="useTemplate('welcome')" 
                                style="background: #27ae60; color: white; padding: 10px;">
                            Welcome Message
                        </button>
                        <button type="button" class="btn" onclick="useTemplate('general')" 
                                style="background: #9b59b6; color: white; padding: 10px;">
                            General Notice
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reply Modal -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeReplyModal()">&times;</span>
            <h3>Reply to Message</h3>
            <form method="POST">
                <input type="hidden" id="reply_message_id" name="message_id">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" id="reply_subject" readonly>
                </div>
                <div class="form-group">
                    <label>Your Reply</label>
                    <textarea name="reply" required placeholder="Type your reply here..."></textarea>
                </div>
                <button type="submit" name="reply_message" class="btn btn-primary">Send Reply</button>
            </form>
        </div>
    </div>
    
    <!-- Notification Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeNotificationModal()">&times;</span>
            <h3>📢 Send Notification</h3>
            <form method="POST" id="notificationForm">
                <div class="form-group">
                    <label>Send To</label>
                    <select name="send_to_type" id="sendToType" required onchange="toggleRecipientOptions()">
                        <option value="">Select recipient type</option>
                        <option value="all_tenants">👥 All Tenants</option>
                        <option value="specific_tenant">👤 Specific Tenant</option>
                        <option value="all_landlords">🏢 All Landlords</option>
                        <option value="specific_landlord">👤 Specific Landlord</option>
                        <option value="all_admins">⚙️ All Admins</option>
                        <option value="specific_admin">👤 Specific Admin</option>
                    </select>
                    <div id="recipientInfo" style="margin-top: 5px; font-size: 0.9rem; color: #666; display: none;">
                        <span id="recipientInfoText"></span>
                    </div>
                </div>
                
                <div class="form-group" id="specificRecipientGroup" style="display: none;">
                    <label id="specificRecipientLabel">Select Recipient</label>
                    <select name="recipient_id" id="specificRecipient">
                        <option value="">Select recipient</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="system">System Notification</option>
                        <option value="email">Email Notification</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required placeholder="Notification subject">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" required placeholder="Type your message here..." rows="4"></textarea>
                </div>
                <button type="submit" name="send_notification" class="btn btn-primary">📤 Send Notification</button>
            </form>
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
        
        // Modal functions
        function openReplyModal(messageId, subject) {
            document.getElementById('reply_message_id').value = messageId;
            document.getElementById('reply_subject').value = subject;
            document.getElementById('replyModal').style.display = 'block';
        }
        
        function closeReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
        }
        
        function openNotificationModal() {
            // Reset form when opening modal
            document.getElementById('notificationForm').reset();
            document.getElementById('specificRecipientGroup').style.display = 'none';
            document.getElementById('recipientInfo').style.display = 'none';
            
            // Show all recipient options for regular notification
            const sendToType = document.getElementById('sendToType');
            sendToType.innerHTML = `
                <option value="">Select recipient type</option>
                <option value="all_tenants">👥 All Tenants</option>
                <option value="specific_tenant">👤 Specific Tenant</option>
                <option value="all_landlords">🏢 All Landlords</option>
                <option value="specific_landlord">👤 Specific Landlord</option>
                <option value="all_admins">⚙️ All Admins</option>
                <option value="specific_admin">👤 Specific Admin</option>
            `;
            
            document.getElementById('notificationModal').style.display = 'block';
        }
        
        function openNotificationModalForTemplates() {
            // Reset form when opening modal
            document.getElementById('notificationForm').reset();
            document.getElementById('specificRecipientGroup').style.display = 'none';
            document.getElementById('recipientInfo').style.display = 'none';
            
            // Show only tenant options for templates
            const sendToType = document.getElementById('sendToType');
            sendToType.innerHTML = `
                <option value="">Select recipient type</option>
                <option value="all_tenants">👥 All Tenants</option>
                <option value="specific_tenant">👤 Specific Tenant</option>
            `;
            
            document.getElementById('notificationModal').style.display = 'block';
        }
        
        function closeNotificationModal() {
            document.getElementById('notificationModal').style.display = 'none';
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
                // Open notification modal and fill with template
                openNotificationModalForTemplates();
                setTimeout(() => {
                    // Fill in the template content
                    document.querySelector('input[name="subject"]').value = templates[template].subject;
                    document.querySelector('textarea[name="message"]').value = templates[template].message;
                    
                    // Set to all tenants for templates (TENANTS ONLY)
                    document.getElementById('sendToType').value = 'all_tenants';
                    toggleRecipientOptions();
                    
                    // Debug: Log the selected value
                    console.log('Template selected:', template);
                    console.log('Send to type set to:', document.getElementById('sendToType').value);
                }, 200);
            }
        }
        
        function toggleRecipientOptions() {
            const sendToType = document.getElementById('sendToType').value;
            const specificGroup = document.getElementById('specificRecipientGroup');
            const specificSelect = document.getElementById('specificRecipient');
            const specificLabel = document.getElementById('specificRecipientLabel');
            const recipientInfo = document.getElementById('recipientInfo');
            const recipientInfoText = document.getElementById('recipientInfoText');
            
            // Clear previous options
            specificSelect.innerHTML = '<option value="">Select recipient</option>';
            
            // Show recipient info
            if (sendToType) {
                recipientInfo.style.display = 'block';
                switch (sendToType) {
                    case 'all_tenants':
                        recipientInfoText.textContent = '✅ Will send to ALL tenants only';
                        recipientInfoText.style.color = '#27ae60';
                        break;
                    case 'all_landlords':
                        recipientInfoText.textContent = '✅ Will send to ALL landlords only';
                        recipientInfoText.style.color = '#e67e22';
                        break;
                    case 'all_admins':
                        recipientInfoText.textContent = '✅ Will send to ALL admins only';
                        recipientInfoText.style.color = '#9b59b6';
                        break;
                    default:
                        recipientInfo.style.display = 'none';
                }
            } else {
                recipientInfo.style.display = 'none';
            }
            
            if (sendToType === 'specific_tenant' || sendToType === 'specific_landlord' || sendToType === 'specific_admin') {
                specificGroup.style.display = 'block';
                recipientInfo.style.display = 'none';
                
                // Get users data from PHP
                const allUsers = <?php echo json_encode($allUsers); ?>;
                
                if (sendToType === 'specific_tenant') {
                    specificLabel.textContent = 'Select Tenant';
                    allUsers.forEach(user => {
                        if (user.role === 'tenant') {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.full_name + ' (Tenant)';
                            specificSelect.appendChild(option);
                        }
                    });
                } else if (sendToType === 'specific_landlord') {
                    specificLabel.textContent = 'Select Landlord';
                    allUsers.forEach(user => {
                        if (user.role === 'landlord') {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.full_name + ' (Landlord)';
                            specificSelect.appendChild(option);
                        }
                    });
                } else if (sendToType === 'specific_admin') {
                    specificLabel.textContent = 'Select Admin';
                    allUsers.forEach(user => {
                        if (user.role === 'admin') {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.full_name + ' (Admin)';
                            specificSelect.appendChild(option);
                        }
                    });
                }
            } else {
                specificGroup.style.display = 'none';
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const replyModal = document.getElementById('replyModal');
            const notificationModal = document.getElementById('notificationModal');
            
            if (event.target === replyModal) {
                replyModal.style.display = 'none';
            }
            if (event.target === notificationModal) {
                notificationModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
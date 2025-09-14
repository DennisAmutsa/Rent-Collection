<?php
require_once 'config.php';
requireRole('admin');

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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $message_id = $_POST['message_id'];
    $status = $_POST['status'];
    
    try {
        $db->query(
            "UPDATE tenant_messages SET status = ? WHERE id = ?",
            [$status, $message_id]
        );
        $success_message = "Status updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating status: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "tm.status = ?";
    $params[] = $status_filter;
}

if ($category_filter) {
    $where_conditions[] = "tm.category = ?";
    $params[] = $category_filter;
}

if ($priority_filter) {
    $where_conditions[] = "tm.priority = ?";
    $params[] = $priority_filter;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all tenant messages with filters
$messages = $db->fetchAll(
    "SELECT tm.*, u.full_name as tenant_name, u.email as tenant_email, u.phone as tenant_phone
     FROM tenant_messages tm
     JOIN users u ON tm.tenant_id = u.id
     $where_clause
     ORDER BY tm.created_at DESC",
    $params
);

// Get statistics
$totalMessages = $db->fetchOne("SELECT COUNT(*) as count FROM tenant_messages")['count'];
$pendingMessages = $db->fetchOne("SELECT COUNT(*) as count FROM tenant_messages WHERE status = 'pending'")['count'];
$repliedMessages = $db->fetchOne("SELECT COUNT(*) as count FROM tenant_messages WHERE status = 'replied'")['count'];
$resolvedMessages = $db->fetchOne("SELECT COUNT(*) as count FROM tenant_messages WHERE status = 'resolved'")['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Messages - <?php echo APP_NAME; ?></title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
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
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            width: 100%;
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
        
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fafafa;
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
        
        .message-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
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
            margin-bottom: 15px;
        }
        
        .message-subject {
            font-weight: 600;
            color: #333;
            font-size: 1.1rem;
        }
        
        .message-meta {
            display: flex;
            gap: 10px;
            align-items: center;
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
        
        .message-content {
            color: #333;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .message-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        .tenant-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .admin-reply {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }
        
        .admin-reply h4 {
            color: #155724;
            margin-bottom: 10px;
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
        
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fafafa;
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #555;
            background: white;
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
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .filters {
                grid-template-columns: 1fr;
            }
            
            .card-body {
                padding: 12px 15px;
            }
            
            .message-item {
                padding: 15px;
            }
            
            .message-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .message-meta {
                flex-wrap: wrap;
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
            <h1>Tenant Messages</h1>
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
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="manage_payments.php">Manage Payments</a></li>
                <li><a href="tenant_messages.php" class="active">Tenant Messages</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="system_reports.php">Reports</a></li>
                <li><a href="system_settings.php">System Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Tenant Messages</h1>
                <p>View and respond to messages from tenants</p>
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
                    <div class="stat-number"><?php echo $totalMessages; ?></div>
                    <div class="stat-label">Total Messages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pendingMessages; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $repliedMessages; ?></div>
                    <div class="stat-label">Replied</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $resolvedMessages; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3>Filter Messages</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="filters">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="">All Categories</option>
                                <option value="rent_payment" <?php echo $category_filter === 'rent_payment' ? 'selected' : ''; ?>>Rent Payment</option>
                                <option value="repair_request" <?php echo $category_filter === 'repair_request' ? 'selected' : ''; ?>>Repair Request</option>
                                <option value="maintenance" <?php echo $category_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="general_inquiry" <?php echo $category_filter === 'general_inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="complaint" <?php echo $category_filter === 'complaint' ? 'selected' : ''; ?>>Complaint</option>
                                <option value="suggestion" <?php echo $category_filter === 'suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                                <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority">
                                <option value="">All Priorities</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="tenant_messages.php" class="btn">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Messages List -->
            <div class="card">
                <div class="card-header">
                    <h3>Messages (<?php echo count($messages); ?> found)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <div style="text-align: center; color: #666; padding: 40px 20px;">
                            <div style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;">📧</div>
                            <h4 style="margin: 0 0 8px 0; color: #333; font-weight: 600;">No messages found</h4>
                            <p style="margin: 0; font-size: 0.9rem;">No messages match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                    <div class="message-meta">
                                        <span class="status-badge status-<?php echo $msg['status']; ?>">
                                            <?php echo ucfirst($msg['status']); ?>
                                        </span>
                                        <span class="status-badge priority-<?php echo $msg['priority']; ?>">
                                            <?php echo ucfirst($msg['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="tenant-info">
                                    <strong>From:</strong> <?php echo htmlspecialchars($msg['tenant_name']); ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($msg['tenant_email']); ?><br>
                                    <?php if ($msg['tenant_phone']): ?>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($msg['tenant_phone']); ?><br>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>
                                
                                <div class="message-footer">
                                    <span><strong>Category:</strong> <?php echo ucfirst(str_replace('_', ' ', $msg['category'])); ?></span>
                                    <span><?php echo formatDate($msg['created_at']); ?></span>
                                </div>
                                
                                <?php if ($msg['admin_reply']): ?>
                                    <div class="admin-reply">
                                        <h4>Admin Reply:</h4>
                                        <p><?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></p>
                                        <small>Replied on: <?php echo formatDate($msg['replied_at']); ?></small>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 15px;">
                                    <?php if ($msg['status'] === 'pending'): ?>
                                        <button class="btn btn-primary btn-small" onclick="openReplyModal(<?php echo $msg['id']; ?>, '<?php echo htmlspecialchars($msg['subject']); ?>')">
                                            Reply
                                        </button>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="btn btn-small">
                                            <option value="pending" <?php echo $msg['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="replied" <?php echo $msg['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                            <option value="resolved" <?php echo $msg['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                    <input type="text" id="reply_subject" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Your Reply</label>
                    <textarea name="reply" required placeholder="Type your reply here..."></textarea>
                </div>
                <button type="submit" name="reply_message" class="btn btn-primary">Send Reply</button>
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
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('replyModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>

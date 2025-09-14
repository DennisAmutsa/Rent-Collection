<?php
require_once 'config.php';
requireRole('tenant');

$user = getCurrentUser();
$db = new Database();

// Handle marking notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $db->query(
        "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
        [$_GET['mark_read'], $user['id']]
    );
    redirect('my_notifications.php');
}

// Handle marking all as read
if (isset($_GET['mark_all_read'])) {
    $db->query(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ?",
        [$user['id']]
    );
    redirect('my_notifications.php');
}

// Get all notifications for this tenant
$notifications = $db->fetchAll(
    "SELECT n.*, u.full_name as sender_name 
     FROM notifications n 
     JOIN users u ON n.created_by = u.id 
     WHERE n.user_id = ? 
     ORDER BY n.created_at DESC",
    [$user['id']]
);

// Get notification statistics
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_notifications,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count
     FROM notifications 
     WHERE user_id = ?",
    [$user['id']]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - <?php echo APP_NAME; ?></title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #666;
        }
        
        .stat-card:nth-child(2) { border-left-color: #e74c3c; }
        .stat-card:nth-child(3) { border-left-color: #f39c12; }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
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
        
        .notification-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .notification-item.unread {
            border-left: 4px solid #f39c12;
            background: #fff3cd;
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
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin: 15px 0;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-number {
                font-size: 1.2rem;
            }
            
            .stat-label {
                font-size: 0.8rem;
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
            
            .notification-item {
                padding: 15px;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 8px;
                margin: 10px 0;
            }
            
            .stat-card {
                padding: 10px;
            }
            
            .stat-number {
                font-size: 1.1rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
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
            
            .notification-item {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <span class="mobile-title">Notifications</span>
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
                <li><a href="my_notifications.php" class="active">Notifications</a></li>
                <li><a href="my_profile.php">Profile</a></li>
                <li><a href="contact_management.php">Contact Management</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>My Notifications</h1>
                <p>View all your notifications and messages</p>
            </div>
            
            <!-- Notification Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_notifications']; ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #e74c3c;"><?php echo $stats['unread_count']; ?></div>
                    <div class="stat-label">Unread</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #27ae60;"><?php echo $stats['total_notifications'] - $stats['unread_count']; ?></div>
                    <div class="stat-label">Read</div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 Notification Actions</h3>
                </div>
                <div style="padding: 20px;">
                    <a href="?mark_all_read=1" class="btn btn-primary" 
                       onclick="return confirm('Mark all notifications as read?')">
                        ✅ Mark All as Read
                    </a>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h3>📨 All Notifications (<?php echo count($notifications); ?>)</h3>
                </div>
                <?php if (empty($notifications)): ?>
                    <div style="padding: 40px; text-align: center; color: #7f8c8d;">
                        <h3>No notifications found</h3>
                        <p>You don't have any notifications yet.</p>
                    </div>
                <?php else: ?>
                    <div style="padding: 20px;">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item" style="
                                border: 1px solid #ecf0f1; 
                                border-radius: 8px; 
                                padding: 20px; 
                                margin-bottom: 15px; 
                                background: <?php echo $notification['is_read'] ? '#f8f9fa' : '#fff3cd'; ?>;
                                border-left: 4px solid <?php echo $notification['is_read'] ? '#95a5a6' : '#f39c12'; ?>;
                            ">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 10px;">
                                            <?php if (!$notification['is_read']): ?>
                                                <span style="background: #e74c3c; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px;">NEW</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h4>
                                        <small style="color: #7f8c8d;">
                                            From: <?php echo htmlspecialchars($notification['sender_name']); ?> - 
                                            <?php echo formatDate($notification['created_at']); ?>
                                        </small>
                                    </div>
                                    <div style="text-align: right;">
                                        <?php if (!$notification['is_read']): ?>
                                            <a href="?mark_read=<?php echo $notification['id']; ?>" 
                                               class="btn" style="background: #3498db; color: white; padding: 5px 10px; font-size: 12px;">
                                                Mark as Read
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #27ae60; font-size: 12px;">✅ Read</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                                    <p style="margin: 0; line-height: 1.6; color: #34495e;">
                                        <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                    </p>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #7f8c8d;">
                                    <div>
                                        Notification ID: #<?php echo $notification['id']; ?>
                                    </div>
                                    <div>
                                        <?php if ($notification['is_read']): ?>
                                            Read on <?php echo formatDate($notification['created_at']); ?>
                                        <?php else: ?>
                                            <strong style="color: #e74c3c;">Unread</strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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

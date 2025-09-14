<?php
require_once 'config.php';
require_once 'email_notification.php';
requireRole('admin');

$user = getCurrentUser();
$db = new Database();
$message = '';
$error = '';

// Handle sending notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_type = sanitizeInput($_POST['recipient_type']);
    $recipient_id = sanitizeInput($_POST['recipient_id']);
    $subject = sanitizeInput($_POST['subject']);
    $notification_message = sanitizeInput($_POST['message']);
    $type = sanitizeInput($_POST['type']);
    
    if ($subject && $notification_message) {
        try {
            if ($recipient_type === 'all_tenants') {
                $count = sendNotificationToAllTenants($subject, $notification_message, $type);
                $message = "Notification sent to $count tenants successfully!";
            } else if ($recipient_type === 'specific_user' && $recipient_id) {
                if (sendNotificationToUser($recipient_id, $subject, $notification_message, $type)) {
                    $message = "Notification sent successfully!";
                } else {
                    $error = "Failed to send notification";
                }
            } else {
                $error = "Please select a recipient";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields";
    }
}

// Get all users for dropdown
$allUsers = $db->fetchAll("SELECT id, full_name, email, role FROM users WHERE is_active = 1 ORDER BY role, full_name");

// Get recent notifications
$recentNotifications = $db->fetchAll(
    "SELECT n.*, s.full_name as sender_name, r.full_name as recipient_name, r.role as recipient_role
     FROM notifications n 
     JOIN users s ON n.sender_id = s.id 
     JOIN users r ON n.recipient_id = r.id 
     ORDER BY n.sent_at DESC 
     LIMIT 20"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role">Administrator</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="send_notification.php" class="active">Send Notifications</a></li>
                <li><a href="system_reports.php">System Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Send Notifications</h1>
                <p>Send notifications to users via system or email</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Send Notification Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>Send New Notification</h3>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label for="recipient_type">Recipient Type</label>
                            <select id="recipient_type" name="recipient_type" required onchange="toggleRecipientSelection()">
                                <option value="">Select recipient type</option>
                                <option value="all_tenants">All Tenants</option>
                                <option value="specific_user">Specific User</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="specific_user_group" style="display: none;">
                            <label for="recipient_id">Select User</label>
                            <select id="recipient_id" name="recipient_id">
                                <option value="">Select a user</option>
                                <?php foreach ($allUsers as $userOption): ?>
                                    <option value="<?php echo $userOption['id']; ?>">
                                        <?php echo htmlspecialchars($userOption['full_name'] . ' (' . $userOption['role'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="type">Notification Type</label>
                            <select id="type" name="type" required>
                                <option value="system">System Notification Only</option>
                                <option value="email">Email + System Notification</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" required 
                                   placeholder="Enter notification subject">
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" required rows="5" 
                                      placeholder="Enter your message here..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Notification</button>
                    </form>
                </div>
                
                <!-- Notification Templates -->
                <div class="card">
                    <div class="card-header">
                        <h3>Quick Templates</h3>
                    </div>
                    <div style="display: grid; gap: 10px;">
                        <button type="button" class="btn" onclick="useTemplate('rent_reminder')" 
                                style="background: #e2e8f0; color: #4a5568;">
                            Rent Payment Reminder
                        </button>
                        <button type="button" class="btn" onclick="useTemplate('maintenance')" 
                                style="background: #e2e8f0; color: #4a5568;">
                            Maintenance Notice
                        </button>
                        <button type="button" class="btn" onclick="useTemplate('meeting')" 
                                style="background: #e2e8f0; color: #4a5568;">
                            Meeting Announcement
                        </button>
                        <button type="button" class="btn" onclick="useTemplate('policy')" 
                                style="background: #e2e8f0; color: #4a5568;">
                            Policy Update
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Notifications -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Notifications</h3>
                </div>
                <?php if (empty($recentNotifications)): ?>
                    <p>No notifications sent yet</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Recipient</th>
                                    <th>Type</th>
                                    <th>Sent Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentNotifications as $notification): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($notification['subject']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($notification['recipient_name']); ?>
                                            <br><small style="color: #718096;">(<?php echo ucfirst($notification['recipient_role']); ?>)</small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $notification['type'] === 'email' ? 'paid' : 'pending'; ?>">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($notification['sent_at']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $notification['is_read'] ? 'paid' : 'pending'; ?>">
                                                <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function toggleRecipientSelection() {
            const recipientType = document.getElementById('recipient_type').value;
            const specificUserGroup = document.getElementById('specific_user_group');
            const recipientId = document.getElementById('recipient_id');
            
            if (recipientType === 'specific_user') {
                specificUserGroup.style.display = 'block';
                recipientId.required = true;
            } else {
                specificUserGroup.style.display = 'none';
                recipientId.required = false;
                recipientId.value = '';
            }
        }
        
        function useTemplate(templateType) {
            const templates = {
                rent_reminder: {
                    subject: 'Rent Payment Reminder',
                    message: 'This is a friendly reminder that your monthly rent payment is due. Please ensure payment is made by the due date to avoid any late fees. Thank you for your prompt attention to this matter.'
                },
                maintenance: {
                    subject: 'Scheduled Maintenance Notice',
                    message: 'We would like to inform you that scheduled maintenance will be performed on the property. Please ensure access is available during the specified time. We apologize for any inconvenience this may cause.'
                },
                meeting: {
                    subject: 'Important Meeting Announcement',
                    message: 'We are organizing a meeting for all tenants to discuss important matters regarding the property. Your attendance is highly encouraged. Please confirm your availability.'
                },
                policy: {
                    subject: 'Policy Update Notice',
                    message: 'We are writing to inform you about important updates to our property policies. Please review the attached document and contact us if you have any questions or concerns.'
                }
            };
            
            if (templates[templateType]) {
                document.getElementById('subject').value = templates[templateType].subject;
                document.getElementById('message').value = templates[templateType].message;
            }
        }
    </script>
</body>
</html>

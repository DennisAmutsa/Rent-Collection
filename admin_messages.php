<?php
require_once 'config.php';
requireRole('admin');

$user = getCurrentUser();
$db = new Database();

// Handle message reply
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message_id = sanitizeInput($_POST['message_id']);
    $reply_text = sanitizeInput($_POST['reply']);
    
    if ($message_id && $reply_text) {
        try {
            $db->query(
                "UPDATE tenant_messages SET admin_reply = ?, replied_by = ?, replied_at = NOW(), status = 'replied' WHERE id = ?",
                [$reply_text, $user['id'], $message_id]
            );
            $message = "Reply sent successfully!";
        } catch (Exception $e) {
            $error = "Error sending reply: " . $e->getMessage();
        }
    } else {
        $error = "Please provide a reply message";
    }
}

// Get all messages for admin
$messages = $db->fetchAll(
    "SELECT tm.*, u.full_name as tenant_name, u.email as tenant_email, 
            p.property_name, p.address as property_address
     FROM tenant_messages tm
     JOIN users u ON tm.tenant_id = u.id
     LEFT JOIN tenant_properties tp ON tm.tenant_id = tp.tenant_id AND tp.is_active = 1
     LEFT JOIN properties p ON tp.property_id = p.id
     WHERE tm.recipient_type = 'admin'
     ORDER BY tm.created_at DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages - <?php echo APP_NAME; ?></title>
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #333;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            margin: 0 0 5px 0;
        }
        
        .header p {
            margin: 0;
            opacity: 0.8;
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
        }
        
        .card-body {
            padding: 20px;
        }
        
        .message-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
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
        
        .message-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 6px;
        }
        
        .info-item {
            font-size: 0.9rem;
        }
        
        .info-item strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .message-content {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #666;
        }
        
        .reply-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #27ae60;
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
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
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
        
        .existing-reply {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #27ae60;
        }
        
        .existing-reply strong {
            display: block;
            margin-bottom: 10px;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Messages</h1>
            <p>View and respond to tenant messages</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3>Messages from Tenants</h3>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <div style="text-align: center; color: #666; padding: 40px;">
                        <p>No messages received yet.</p>
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
                                    <span class="priority-badge priority-<?php echo $msg['priority']; ?>">
                                        <?php echo ucfirst($msg['priority']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="message-info">
                                <div class="info-item">
                                    <strong>From:</strong>
                                    <?php echo htmlspecialchars($msg['tenant_name']); ?><br>
                                    <small><?php echo htmlspecialchars($msg['tenant_email']); ?></small>
                                </div>
                                <div class="info-item">
                                    <strong>Property:</strong>
                                    <?php echo htmlspecialchars($msg['property_name'] ?: 'N/A'); ?><br>
                                    <small><?php echo htmlspecialchars($msg['property_address'] ?: 'N/A'); ?></small>
                                </div>
                                <div class="info-item">
                                    <strong>Category:</strong>
                                    <?php echo ucfirst(str_replace('_', ' ', $msg['category'])); ?>
                                </div>
                                <div class="info-item">
                                    <strong>Date:</strong>
                                    <?php echo formatDate($msg['created_at']); ?>
                                </div>
                            </div>
                            
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </div>
                            
                            <?php if ($msg['admin_reply']): ?>
                                <div class="existing-reply">
                                    <strong>Your Reply:</strong>
                                    <?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?>
                                    <br><br>
                                    <small>Replied on: <?php echo formatDate($msg['replied_at']); ?></small>
                                </div>
                            <?php else: ?>
                                <div class="reply-section">
                                    <form method="POST">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <div class="form-group">
                                            <label for="reply_<?php echo $msg['id']; ?>">Reply to Tenant:</label>
                                            <textarea id="reply_<?php echo $msg['id']; ?>" name="reply" required
                                                      placeholder="Type your reply here..."></textarea>
                                        </div>
                                        <button type="submit" name="reply_message" class="btn">Send Reply</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

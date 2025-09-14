<?php
require_once 'config.php';

class EmailNotification {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
        $this->from_email = FROM_EMAIL;
        $this->from_name = FROM_NAME;
    }
    
    public function sendEmail($to_email, $to_name, $subject, $message) {
        // For demo purposes, we'll use PHP's mail() function
        // In production, you should use PHPMailer or similar library
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $this->from_name . " <" . $this->from_email . ">" . "\r\n";
        $headers .= "Reply-To: " . $this->from_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $html_message = $this->getEmailTemplate($to_name, $subject, $message);
        
        return mail($to_email, $subject, $html_message, $headers);
    }
    
    private function getEmailTemplate($recipient_name, $subject, $message) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . htmlspecialchars($subject) . "</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . APP_NAME . "</h1>
                    <p>Notification System</p>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($recipient_name) . ",</h2>
                    <p>" . nl2br(htmlspecialchars($message)) . "</p>
                    <p>If you have any questions, please contact your administrator.</p>
                    <p>Best regards,<br>" . APP_NAME . " Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from " . APP_NAME . "</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}

// Function to send notification to user
function sendNotificationToUser($recipient_id, $subject, $message, $type = 'system') {
    $db = new Database();
    $emailNotification = new EmailNotification();
    
    // Get recipient information
    $recipient = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$recipient_id]);
    if (!$recipient) {
        return false;
    }
    
    // Save notification to database
    $sender_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to admin
    
    try {
        $db->query(
            "INSERT INTO notifications (sender_id, recipient_id, subject, message, type) VALUES (?, ?, ?, ?, ?)",
            [$sender_id, $recipient_id, $subject, $message, $type]
        );
        
        // Send email if type is email
        if ($type === 'email') {
            $emailNotification->sendEmail($recipient['email'], $recipient['full_name'], $subject, $message);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Function to send notification to all tenants
function sendNotificationToAllTenants($subject, $message, $type = 'system') {
    $db = new Database();
    $emailNotification = new EmailNotification();
    
    // Get all active tenants
    $tenants = $db->fetchAll("SELECT * FROM users WHERE role = 'tenant' AND is_active = 1");
    $sender_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
    
    $success_count = 0;
    
    foreach ($tenants as $tenant) {
        try {
            // Save notification to database
            $db->query(
                "INSERT INTO notifications (sender_id, recipient_id, subject, message, type) VALUES (?, ?, ?, ?, ?)",
                [$sender_id, $tenant['id'], $subject, $message, $type]
            );
            
            // Send email if type is email
            if ($type === 'email') {
                $emailNotification->sendEmail($tenant['email'], $tenant['full_name'], $subject, $message);
            }
            
            $success_count++;
        } catch (Exception $e) {
            // Continue with other tenants even if one fails
            continue;
        }
    }
    
    return $success_count;
}
?>

<?php
require_once 'config.php';
requireRole('landlord');

$user = getCurrentUser();
$db = new Database();

// Get receipt ID from POST
$receipt_id = isset($_POST['receipt_id']) ? intval($_POST['receipt_id']) : 0;

if (!$receipt_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid receipt ID']);
    exit;
}

// Get receipt details
$receipt = $db->fetchOne(
    "SELECT rp.*, u.full_name as tenant_name, u.email as tenant_email, 
            p.property_name, p.address, p.monthly_rent, p.landlord_id,
            landlord.full_name as landlord_name, landlord.email as landlord_email
     FROM rent_payments rp
     JOIN users u ON rp.tenant_id = u.id
     JOIN properties p ON rp.property_id = p.id
     JOIN users landlord ON p.landlord_id = landlord.id
     WHERE rp.id = ? AND p.landlord_id = ?",
    [$receipt_id, $user['id']]
);

if (!$receipt) {
    echo json_encode(['success' => false, 'message' => 'Receipt not found']);
    exit;
}

// Create email content
$subject = "Payment Receipt #" . $receipt['id'] . " - " . APP_NAME;
$message = createReceiptEmail($receipt);

// Send email
$headers = [
    'From: ' . APP_NAME . ' <noreply@' . $_SERVER['HTTP_HOST'] . '>',
    'Reply-To: ' . $receipt['landlord_email'],
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion()
];

$email_sent = mail($receipt['tenant_email'], $subject, $message, implode("\r\n", $headers));

if ($email_sent) {
    // Log the email notification
    $db->query(
        "INSERT INTO notifications (user_id, title, message, created_by) VALUES (?, ?, ?, ?)",
        [$receipt['tenant_id'], "Receipt Emailed - " . date('M j, Y'), "Payment receipt #" . $receipt['id'] . " has been emailed to you.", $user['id']]
    );
    
    echo json_encode(['success' => true, 'message' => 'Receipt emailed successfully to ' . $receipt['tenant_email']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
}

function createReceiptEmail($receipt) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
            .amount { font-size: 32px; font-weight: bold; color: #27ae60; text-align: center; margin: 20px 0; }
            .details { background: white; padding: 20px; border-radius: 6px; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
            .detail-label { font-weight: bold; color: #2c3e50; }
            .detail-value { color: #34495e; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #7f8c8d; font-size: 14px; }
            .notes { background: #e8f4fd; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #3498db; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>PAYMENT RECEIPT</h1>
            <p>' . APP_NAME . '</p>
        </div>
        
        <div class="content">
            <div class="amount">' . formatCurrency($receipt['amount']) . '</div>
            <p style="text-align: center; color: #7f8c8d; margin: 0;">Payment Received</p>
            
            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Receipt ID:</span>
                    <span class="detail-value">#' . $receipt['id'] . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Date:</span>
                    <span class="detail-value">' . formatDate($receipt['payment_date']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tenant:</span>
                    <span class="detail-value">' . htmlspecialchars($receipt['tenant_name']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Property:</span>
                    <span class="detail-value">' . htmlspecialchars($receipt['property_name']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value">' . htmlspecialchars($receipt['address']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">' . htmlspecialchars($receipt['payment_method']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Monthly Rent:</span>
                    <span class="detail-value">' . formatCurrency($receipt['monthly_rent']) . '</span>
                </div>
            </div>';
    
    if ($receipt['notes']) {
        $html .= '
            <div class="notes">
                <strong>Notes:</strong><br>
                ' . htmlspecialchars($receipt['notes']) . '
            </div>';
    }
    
    $html .= '
            <div class="footer">
                <p><strong>Receipt #' . $receipt['id'] . '</strong></p>
                <p>Generated on ' . date('F j, Y \a\t g:i A') . '</p>
                <p>This is an official receipt from ' . APP_NAME . '</p>
                <p>Thank you for your payment!</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>

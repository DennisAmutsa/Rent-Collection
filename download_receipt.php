<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

$user = getCurrentUser();
$db = new Database();

// Get receipt ID from URL
$receipt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$receipt_id) {
    die('Invalid receipt ID');
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
     WHERE rp.id = ?",
    [$receipt_id]
);

if (!$receipt) {
    die('Receipt not found');
}

// Check permissions
$can_view = false;
if ($user['role'] === 'landlord' && $receipt['landlord_id'] == $user['id']) {
    $can_view = true;
} elseif ($user['role'] === 'tenant' && $receipt['tenant_id'] == $user['id']) {
    $can_view = true;
} elseif ($user['role'] === 'admin') {
    $can_view = true;
}

if (!$can_view) {
    die('You do not have permission to view this receipt');
}

// Generate PDF content using HTML
$html_content = generateReceiptHTML($receipt);

// Set headers for PDF download
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="rent_receipt_' . $receipt_id . '_' . date('Y-m-d') . '.html"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output HTML content (can be opened in browser and printed as PDF)
echo $html_content;

function generateReceiptHTML($receipt) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Rent Payment Receipt #' . $receipt['id'] . '</title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none !important; }
                .receipt-container { border: none !important; box-shadow: none !important; }
            }
            
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background: white;
                line-height: 1.6;
            }
            
            .receipt-container {
                max-width: 800px;
                margin: 0 auto;
                border: 2px solid #2c3e50;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            
            .receipt-header {
                background: linear-gradient(135deg, #2c3e50, #34495e);
                color: white;
                padding: 30px;
                text-align: center;
            }
            
            .receipt-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: bold;
            }
            
            .receipt-header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                opacity: 0.9;
            }
            
            .receipt-body {
                padding: 30px;
            }
            
            .receipt-info {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-bottom: 30px;
            }
            
            .info-section h3 {
                color: #2c3e50;
                margin: 0 0 15px 0;
                font-size: 18px;
                border-bottom: 2px solid #ecf0f1;
                padding-bottom: 10px;
            }
            
            .info-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                padding: 8px 0;
                border-bottom: 1px solid #ecf0f1;
            }
            
            .info-label {
                font-weight: bold;
                color: #34495e;
            }
            
            .info-value {
                color: #2c3e50;
            }
            
            .payment-details {
                background: #f8f9fa;
                padding: 25px;
                border-radius: 8px;
                margin: 30px 0;
            }
            
            .payment-details h3 {
                color: #2c3e50;
                margin: 0 0 20px 0;
                text-align: center;
                font-size: 20px;
            }
            
            .payment-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            
            .amount-highlight {
                background: #27ae60;
                color: white;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
                margin: 20px 0;
            }
            
            .amount-highlight .amount {
                font-size: 32px;
                font-weight: bold;
                margin: 0;
            }
            
            .amount-highlight .label {
                font-size: 16px;
                margin: 5px 0 0 0;
                opacity: 0.9;
            }
            
            .receipt-footer {
                background: #ecf0f1;
                padding: 20px;
                text-align: center;
                color: #7f8c8d;
            }
            
            .receipt-footer p {
                margin: 5px 0;
            }
            
            .status-badge {
                background: #27ae60;
                color: white;
                padding: 8px 16px;
                border-radius: 20px;
                font-weight: bold;
                display: inline-block;
            }
            
            .receipt-id {
                background: #3498db;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                font-weight: bold;
                text-align: center;
                margin: 20px 0;
            }
            
            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #3498db;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                z-index: 1000;
            }
            
            .print-button:hover {
                background: #2980b9;
            }
        </style>
    </head>
    <body>
        <button class="print-button no-print" onclick="window.print()">🖨️ Print Receipt</button>
        
        <div class="receipt-container">
            <div class="receipt-header">
                <h1>🏠 RENT PAYMENT RECEIPT</h1>
                <p>Official Payment Confirmation</p>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-id">
                    Receipt ID: #' . $receipt['id'] . ' | Date: ' . date('F j, Y', strtotime($receipt['payment_date'])) . '
                </div>
                
                <div class="receipt-info">
                    <div class="info-section">
                        <h3>👤 Tenant Information</h3>
                        <div class="info-item">
                            <span class="info-label">Name:</span>
                            <span class="info-value">' . htmlspecialchars($receipt['tenant_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value">' . htmlspecialchars($receipt['tenant_email']) . '</span>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>🏢 Landlord Information</h3>
                        <div class="info-item">
                            <span class="info-label">Name:</span>
                            <span class="info-value">' . htmlspecialchars($receipt['landlord_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value">' . htmlspecialchars($receipt['landlord_email']) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="payment-details">
                    <h3>💰 Payment Details</h3>
                    <div class="payment-grid">
                        <div class="info-item">
                            <span class="info-label">Property:</span>
                            <span class="info-value">' . htmlspecialchars($receipt['property_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Address:</span>
                            <span class="info-value">' . htmlspecialchars($receipt['address']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Payment Method:</span>
                            <span class="info-value">' . htmlspecialchars($receipt['payment_method']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Payment Date:</span>
                            <span class="info-value">' . date('F j, Y', strtotime($receipt['payment_date'])) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Due Date:</span>
                            <span class="info-value">' . date('F j, Y', strtotime($receipt['due_date'])) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="info-value"><span class="status-badge">' . ucfirst($receipt['status']) . '</span></span>
                        </div>
                    </div>
                </div>
                
                <div class="amount-highlight">
                    <p class="amount">' . formatCurrency($receipt['amount']) . '</p>
                    <p class="label">Total Amount Paid</p>
                </div>
                
                ' . (!empty($receipt['notes']) ? '
                <div class="payment-details">
                    <h3>📝 Additional Notes</h3>
                    <p style="margin: 0; line-height: 1.6;">' . nl2br(htmlspecialchars($receipt['notes'])) . '</p>
                </div>
                ' : '') . '
            </div>
            
            <div class="receipt-footer">
                <p><strong>This is an official receipt for rent payment.</strong></p>
                <p>Generated on ' . date('F j, Y \a\t g:i A') . ' | ' . APP_NAME . '</p>
                <p>For any queries, please contact your landlord or property manager.</p>
            </div>
        </div>
        
        <script>
            // Auto-print when opened
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 1000);
            };
        </script>
    </body>
    </html>';
    
    return $html;
}
?>

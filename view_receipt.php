<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$db = new Database();

// Get receipt ID from URL
$receipt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$receipt_id) {
    redirect('tenant_receipts.php');
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
    redirect('tenant_receipts.php');
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
    redirect('unauthorized.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $receipt['id']; ?> - <?php echo APP_NAME; ?></title>
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
        
        /* Receipt Container */
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #f0f0f0;
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
        
        .receipt-id {
            background: #3498db;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.1rem;
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
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            margin: 30px 0;
        }
        
        .amount-highlight .amount {
            font-size: 36px;
            font-weight: bold;
            margin: 0;
        }
        
        .amount-highlight .label {
            font-size: 18px;
            margin: 10px 0 0 0;
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
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #666;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #555;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f8b);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #229954, #1e8449);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
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
            
            .receipt-container {
                margin: 0;
            }
            
            .receipt-header {
                padding: 20px;
            }
            
            .receipt-header h1 {
                font-size: 24px;
            }
            
            .receipt-body {
                padding: 20px;
            }
            
            .receipt-info {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .payment-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .amount-highlight .amount {
                font-size: 28px;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
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
            
            .receipt-header {
                padding: 15px;
            }
            
            .receipt-header h1 {
                font-size: 20px;
            }
            
            .receipt-body {
                padding: 15px;
            }
            
            .amount-highlight .amount {
                font-size: 24px;
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
            <h1>Receipt #<?php echo $receipt['id']; ?></h1>
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
                <?php if ($user['role'] === 'tenant'): ?>
                    <li><a href="tenant_dashboard.php">Dashboard</a></li>
                    <li><a href="tenant_receipts.php">Receipts</a></li>
                    <li><a href="my_notifications.php">Notifications</a></li>
                    <li><a href="my_profile.php">Profile</a></li>
                    <li><a href="contact_management.php">Contact Management</a></li>
                <?php elseif ($user['role'] === 'admin'): ?>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="tenant_messages.php">Tenant Messages</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php else: ?>
                    <li><a href="landlord_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="manage_tenants.php">Manage Tenants</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="landlord_receipts.php">Payment Receipts</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>🏠 Payment Receipt</h1>
                <p>Official payment confirmation and receipt details</p>
            </div>
            
            <div class="receipt-container">
                <div class="receipt-header">
                    <h1>💰 RENT PAYMENT RECEIPT</h1>
                    <p>Official Payment Confirmation</p>
                </div>
                
                <div class="receipt-body">
                    <div class="receipt-id">
                        Receipt ID: #<?php echo $receipt['id']; ?> | Date: <?php echo date('F j, Y', strtotime($receipt['payment_date'])); ?>
                    </div>
                    
                    <div class="receipt-info">
                        <div class="info-section">
                            <h3>👤 Tenant Information</h3>
                            <div class="info-item">
                                <span class="info-label">Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt['tenant_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt['tenant_email']); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <h3>🏢 Landlord Information</h3>
                            <div class="info-item">
                                <span class="info-label">Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt['landlord_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt['landlord_email']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="payment-details">
                        <h3>💰 Payment Details</h3>
                        <div class="payment-grid">
                            <div class="info-item">
                                <span class="info-label">Property:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt['property_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Address:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt['address']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Payment Method:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt['payment_method']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Payment Date:</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($receipt['payment_date'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Due Date:</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($receipt['due_date'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span class="info-value"><span class="status-badge"><?php echo ucfirst($receipt['status']); ?></span></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="amount-highlight">
                        <p class="amount"><?php echo formatCurrency($receipt['amount']); ?></p>
                        <p class="label">Total Amount Paid</p>
                    </div>
                    
                    <?php if (!empty($receipt['notes'])): ?>
                    <div class="payment-details">
                        <h3>📝 Additional Notes</h3>
                        <p style="margin: 0; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($receipt['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <button onclick="printReceipt()" class="btn btn-primary">🖨️ Print Receipt</button>
                        <a href="download_receipt.php?id=<?php echo $receipt['id']; ?>" class="btn btn-success">📥 Download PDF</a>
                        <?php if ($user['role'] === 'tenant'): ?>
                            <a href="tenant_receipts.php" class="btn btn-secondary">← Back to Receipts</a>
                        <?php elseif ($user['role'] === 'landlord'): ?>
                            <a href="landlord_receipts.php" class="btn btn-secondary">← Back to Receipts</a>
                        <?php elseif ($user['role'] === 'admin'): ?>
                            <a href="admin_payment_receipts.php" class="btn btn-secondary">← Back to Payment Receipts</a>
                        <?php else: ?>
                            <a href="manage_payments.php" class="btn btn-secondary">← Back to Payments</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="receipt-footer">
                    <p><strong>This is an official receipt for rent payment.</strong></p>
                    <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?> | <?php echo APP_NAME; ?></p>
                    <p>For any queries, please contact your landlord or property manager.</p>
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
        
        // Print receipt function
        function printReceipt() {
            window.print();
        }
    </script>
</body>
</html>
<?php
require_once 'config.php';
requireRole('tenant');

$user = getCurrentUser();
$db = new Database();

// Get tenant's payment receipts
$receipts = $db->fetchAll(
    "SELECT rp.*, p.property_name, p.address
     FROM rent_payments rp
     JOIN properties p ON rp.property_id = p.id
     WHERE rp.tenant_id = ?
     ORDER BY rp.payment_date DESC",
    [$user['id']]
);

// Get tenant's property info
$property = $db->fetchOne(
    "SELECT p.*, tp.move_in_date
     FROM properties p
     JOIN tenant_properties tp ON p.id = tp.property_id
     WHERE tp.tenant_id = ? AND tp.is_active = 1",
    [$user['id']]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipts - <?php echo APP_NAME; ?></title>
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
        
        .receipt-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .receipt-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
            
            .content-header h1 {
                font-size: 1.3rem;
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
            
            .receipt-card {
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
            
            .receipt-card {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <span class="mobile-title">Receipts</span>
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
                <li><a href="tenant_receipts.php" class="active">Receipts</a></li>
                <li><a href="my_notifications.php">Notifications</a></li>
                <li><a href="my_profile.php">Profile</a></li>
                <li><a href="contact_management.php">Contact Management</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Payment Receipts</h1>
                <p>View all your payment receipts and transaction history</p>
            </div>
            
            <!-- Property Info -->
            <?php if ($property): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Your Property</h3>
                </div>
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <strong>Property:</strong><br>
                            <?php echo htmlspecialchars($property['property_name']); ?>
                        </div>
                        <div>
                            <strong>Address:</strong><br>
                            <?php echo htmlspecialchars($property['address']); ?>
                        </div>
                        <div>
                            <strong>Monthly Rent:</strong><br>
                            <?php echo formatCurrency($property['monthly_rent']); ?>
                        </div>
                        <div>
                            <strong>Move-in Date:</strong><br>
                            <?php echo formatDate($property['move_in_date']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Payment Receipts -->
            <div class="card">
                <div class="card-header">
                    <h3>💰 Payment History (<?php echo count($receipts); ?> receipts)</h3>
                </div>
                <?php if (empty($receipts)): ?>
                    <div style="padding: 40px; text-align: center; color: #7f8c8d;">
                        <h3>No payment receipts found</h3>
                        <p>Your payment receipts will appear here once payments are recorded.</p>
                    </div>
                <?php else: ?>
                    <div style="padding: 20px;">
                        <?php foreach ($receipts as $receipt): ?>
                            <div class="receipt-card" style="border: 1px solid #ecf0f1; border-radius: 8px; padding: 20px; margin-bottom: 15px; background: #f8f9fa;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div>
                                        <h4 style="margin: 0; color: #2c3e50; cursor: pointer;" onclick="viewReceipt(<?php echo $receipt['id']; ?>)">
                                            Payment Receipt #<?php echo $receipt['id']; ?> 👁️
                                        </h4>
                                        <small style="color: #7f8c8d;"><?php echo formatDate($receipt['payment_date']); ?></small>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 24px; font-weight: bold; color: #27ae60;">
                                            <?php echo formatCurrency($receipt['amount']); ?>
                                        </div>
                                        <span class="status-badge status-paid">✅ Paid</span>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                    <div>
                                        <strong>Property:</strong><br>
                                        <?php echo htmlspecialchars($receipt['property_name']); ?>
                                    </div>
                                    <div>
                                        <strong>Payment Method:</strong><br>
                                        <?php echo htmlspecialchars($receipt['payment_method']); ?>
                                    </div>
                                    <div>
                                        <strong>Payment Date:</strong><br>
                                        <?php echo formatDate($receipt['payment_date']); ?>
                                    </div>
                                    <div>
                                        <strong>Receipt ID:</strong><br>
                                        #<?php echo $receipt['id']; ?>
                                    </div>
                                </div>
                                
                                <?php if ($receipt['notes']): ?>
                                    <div style="background: #e8f4fd; padding: 10px; border-radius: 4px; margin-top: 10px;">
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($receipt['notes']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ecf0f1; text-align: center;">
                                    <button onclick="viewReceipt(<?php echo $receipt['id']; ?>)" 
                                            class="btn" style="background: #2c3e50; color: white; padding: 8px 16px; margin-right: 10px;">
                                        👁️ View Receipt
                                    </button>
                                    <button onclick="printReceipt(<?php echo $receipt['id']; ?>)" 
                                            class="btn" style="background: #3498db; color: white; padding: 8px 16px; margin-right: 10px;">
                                        🖨️ Print Receipt
                                    </button>
                                    <button onclick="downloadReceipt(<?php echo $receipt['id']; ?>)" 
                                            class="btn" style="background: #27ae60; color: white; padding: 8px 16px;">
                                        📥 Download PDF
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function viewReceipt(receiptId) {
            // Open the detailed receipt view in a new tab
            window.open('view_receipt.php?id=' + receiptId, '_blank');
        }
        
        function printReceipt(receiptId) {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            const receiptCard = document.querySelector(`[onclick="printReceipt(${receiptId})"]`).closest('.receipt-card');
            
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Payment Receipt #${receiptId}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .receipt { border: 2px solid #333; padding: 20px; max-width: 500px; }
                            .header { text-align: center; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                            .amount { font-size: 24px; font-weight: bold; color: #27ae60; text-align: center; margin: 20px 0; }
                            .details { margin: 10px 0; }
                            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class="receipt">
                            <div class="header">
                                <h2>PAYMENT RECEIPT</h2>
                                <p>Rent Collection System</p>
                            </div>
                            <div class="amount">${receiptCard.querySelector('.status-badge').previousElementSibling.textContent}</div>
                            <div class="details">
                                <p><strong>Receipt ID:</strong> #${receiptId}</p>
                                <p><strong>Date:</strong> ${receiptCard.querySelector('small').textContent}</p>
                                <p><strong>Property:</strong> ${receiptCard.querySelectorAll('div')[2].textContent.trim()}</p>
                                <p><strong>Payment Method:</strong> ${receiptCard.querySelectorAll('div')[3].textContent.trim()}</p>
                            </div>
                            <div class="footer">
                                <p>Thank you for your payment!</p>
                                <p>Generated on ${new Date().toLocaleDateString()}</p>
                            </div>
                        </div>
                    </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }
        
        function downloadReceipt(receiptId) {
            // Open the download receipt page in a new window
            window.open('download_receipt.php?id=' + receiptId, '_blank');
        }
        
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



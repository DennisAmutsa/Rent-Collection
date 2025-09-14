<?php
require_once 'config.php';
requireRole('tenant');

$user = getCurrentUser();
$db = new Database();

// Handle rent payment
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $amount = floatval($_POST['amount']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    
    if ($amount > 0 && $payment_method) {
        // Get tenant's property
        $tenantProperty = $db->fetchOne(
            "SELECT tp.*, p.* FROM tenant_properties tp 
             JOIN properties p ON tp.property_id = p.id 
             WHERE tp.tenant_id = ? AND tp.is_active = 1",
            [$user['id']]
        );
        
        if ($tenantProperty) {
            try {
                // Create payment record
                $db->query(
                    "INSERT INTO rent_payments (tenant_id, property_id, amount, payment_date, due_date, status, payment_method) 
                     VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 'paid', ?)",
                    [$user['id'], $tenantProperty['property_id'], $amount, $payment_method]
                );
                $message = "Payment of " . formatCurrency($amount) . " recorded successfully!";
            } catch (Exception $e) {
                $message = "Error recording payment: " . $e->getMessage();
            }
        } else {
            $message = "No active property found for this tenant";
        }
    } else {
        $message = "Please fill in all fields correctly";
    }
}

// Get tenant's property information
$tenantProperty = $db->fetchOne(
    "SELECT tp.*, p.*, u.full_name as landlord_name 
     FROM tenant_properties tp 
     JOIN properties p ON tp.property_id = p.id 
     JOIN users u ON p.landlord_id = u.id 
     WHERE tp.tenant_id = ? AND tp.is_active = 1",
    [$user['id']]
);

// Get payment history
$paymentHistory = $db->fetchAll(
    "SELECT * FROM rent_payments 
     WHERE tenant_id = ? 
     ORDER BY payment_date DESC 
     LIMIT 10",
    [$user['id']]
);

// Get notifications for this tenant
$notifications = $db->fetchAll(
    "SELECT n.*, u.full_name as sender_name 
     FROM notifications n 
     JOIN users u ON n.created_by = u.id 
     WHERE n.user_id = ? 
     ORDER BY n.created_at DESC 
     LIMIT 5",
    [$user['id']]
);

// Get current month's payment status
$currentMonthPayment = $db->fetchOne(
    "SELECT * FROM rent_payments 
     WHERE tenant_id = ? 
     AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') 
     AND status = 'paid'",
    [$user['id']]
);

// Calculate next due date
$nextDueDate = $tenantProperty ? date('Y-m-d', strtotime('+1 month')) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard - <?php echo APP_NAME; ?></title>
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
        .stat-card:nth-child(4) { border-left-color: #27ae60; }
        
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #666;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f8f8f8;
            font-weight: 600;
            color: #333;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .card-body {
                padding: 12px 15px;
            }
            
            .table-container {
                overflow-x: auto;
                margin: 0 -15px;
                padding: 0 15px;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 8px 6px;
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
            
            table {
                font-size: 0.75rem;
            }
            
            th, td {
                padding: 6px 4px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <span class="mobile-title">Dashboard</span>
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
                <li><a href="tenant_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="tenant_receipts.php">Receipts</a></li>
                <li><a href="my_notifications.php">Notifications</a></li>
                <li><a href="my_profile.php">Profile</a></li>
                <li><a href="contact_management.php">Contact Management</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Tenant Dashboard</h1>
                <p>Manage your rent payments and view notifications</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'error' : 'success'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($tenantProperty): ?>
                <!-- Property Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>My Property</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div>
                                <h4><?php echo htmlspecialchars($tenantProperty['property_name']); ?></h4>
                                <p><?php echo htmlspecialchars($tenantProperty['address']); ?></p>
                                <p><strong>Landlord:</strong> <?php echo htmlspecialchars($tenantProperty['landlord_name']); ?></p>
                            </div>
                            <div>
                                <p><strong>Monthly Rent:</strong> <?php echo formatCurrency($tenantProperty['monthly_rent']); ?></p>
                                <p><strong>Move-in Date:</strong> <?php echo formatDate($tenantProperty['move_in_date']); ?></p>
                                <p><strong>Next Due Date:</strong> <?php echo formatDate($nextDueDate); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Status -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo formatCurrency($tenantProperty['monthly_rent']); ?></div>
                        <div class="stat-label">Monthly Rent</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php echo $currentMonthPayment ? 'Paid' : 'Pending'; ?>
                        </div>
                        <div class="stat-label">This Month Status</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($paymentHistory); ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($notifications); ?></div>
                        <div class="stat-label">Unread Notifications</div>
                    </div>
                </div>
                
                <!-- Make Payment -->
                <?php if (!$currentMonthPayment): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3>Make Payment</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" id="amount" name="amount" 
                                               value="<?php echo $tenantProperty['monthly_rent']; ?>" 
                                               step="0.01" min="0" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select id="payment_method" name="payment_method" required>
                                            <option value="">Select method</option>
                                            <option value="cash">Cash</option>
                                            <option value="check">Check</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="online">Online Payment</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" name="make_payment" class="btn btn-primary">Record Payment</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <strong>Payment Confirmed!</strong> Your rent payment for this month has been recorded.
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>No Property Assigned</strong> You are not currently assigned to any property. 
                    Please contact your landlord or administrator.
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Payment History -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Payments</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($paymentHistory)): ?>
                            <p>No payment history found</p>
                        <?php else: ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paymentHistory as $payment): ?>
                                            <tr>
                                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                        <?php echo ucfirst($payment['status']); ?>
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
                
                <!-- Notifications -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Notifications</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <p>No notifications</p>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($notifications as $notification): ?>
                                    <div style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div style="font-size: 14px; color: #718096; margin: 5px 0;">
                                            <?php echo htmlspecialchars(substr($notification['message'], 0, 100)) . '...'; ?>
                                        </div>
                                        <div style="font-size: 12px; color: #a0aec0;">
                                            From: <?php echo htmlspecialchars($notification['sender_name']); ?> - 
                                            <?php echo formatDate($notification['created_at']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
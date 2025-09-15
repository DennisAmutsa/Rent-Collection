<?php
require_once 'config.php';
requireRole('tenant');

$user = getCurrentUser();
$db = new Database();

// Automatically update overdue payments
$db->query("
    UPDATE rent_payments 
    SET status = 'overdue' 
    WHERE status = 'pending' 
    AND due_date < CURDATE()
");

$message = '';
$error = '';

// Handle rent payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $amount = floatval($_POST['amount']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
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
                // Create payment record with pending status (admin needs to confirm)
                $db->query(
                    "INSERT INTO rent_payments (tenant_id, property_id, amount, payment_date, due_date, status, payment_method, notes, recorded_by) 
                     VALUES (?, ?, ?, CURDATE(), CURDATE(), 'pending', ?, ?, ?)",
                    [$user['id'], $tenantProperty['property_id'], $amount, $payment_method, $notes, $user['id']]
                );
                
                $payment_id = $db->lastInsertId();
                
                // Create notification for admin about new payment
                $adminUsers = $db->fetchAll("SELECT id FROM users WHERE role = 'admin'");
                foreach ($adminUsers as $admin) {
                    $db->query(
                        "INSERT INTO notifications (user_id, title, message, type, created_by, created_at) 
                         VALUES (?, ?, ?, 'payment_submitted', ?, NOW())",
                        [
                            $admin['id'],
                            'New Payment Submitted',
                            "Tenant " . $user['full_name'] . " has submitted a payment of KES " . number_format($amount) . 
                            " via " . $payment_method . ". Payment ID: #" . $payment_id . ". Please review and confirm.",
                            $user['id']
                        ]
                    );
                }
                
                $message = "Payment of " . formatCurrency($amount) . " submitted successfully! Admin will review and confirm your payment.";
            } catch (Exception $e) {
                $error = "Error submitting payment: " . $e->getMessage();
            }
        } else {
            $error = "No active property found for this tenant";
        }
    } else {
        $error = "Please fill in all required fields";
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

// Get current month's payment status
$currentMonthPayment = $db->fetchOne(
    "SELECT * FROM rent_payments 
     WHERE tenant_id = ? 
     AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') 
     AND status IN ('paid', 'pending')",
    [$user['id']]
);

// Get recent payments
$recentPayments = $db->fetchAll(
    "SELECT * FROM rent_payments 
     WHERE tenant_id = ? 
     ORDER BY payment_date DESC 
     LIMIT 5",
    [$user['id']]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Rent Collection System</title>
    <link rel="stylesheet" href="style.css">
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
        
        .payment-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .payment-form {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .payment-form h3 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background: #ffffff;
            color: #333;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #666;
        }
        
        .form-group input[readonly] {
            background: #f8f8f8;
            color: #666;
            cursor: not-allowed;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #666;
            margin-top: 5px;
        }
        
        .btn {
            background: #666;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
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
        
        .alert {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .payment-info {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .payment-info h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .recent-payments {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            overflow: hidden;
        }
        
        .recent-payments h3 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .payment-details {
            flex: 1;
        }
        
        .payment-amount {
            font-weight: 600;
            color: #333;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
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
            color: white;
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
            color: white;
        }
        
        .content-header p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
            color: white;
        }
        
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
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
                padding: 60px 10px 20px;
            }
            
            .content-header {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .content-header h1 {
                font-size: 1.3rem;
            }
            
            .content-header p {
                font-size: 0.85rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .payment-container {
                padding: 0;
            }
            
            .payment-form {
                padding: 20px 15px;
                margin-bottom: 15px;
            }
            
            .payment-form h3 {
                font-size: 1.3rem;
                margin-bottom: 15px;
            }
            
            .payment-info {
                padding: 15px;
            }
            
            .recent-payments {
                padding: 15px;
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .payment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .payment-amount {
                text-align: left;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <div class="mobile-title">Make Payment</div>
        <div></div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
            <div class="user-role">Tenant</div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="tenant_dashboard.php">Dashboard</a></li>
            <li><a href="make_payment.php" class="active">Make Payment</a></li>
            <li><a href="tenant_receipts.php">Receipts</a></li>
            <li><a href="my_notifications.php">Notifications</a></li>
            <li><a href="my_profile.php">Profile</a></li>
            <li><a href="contact_management.php">Contact Management</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>Make Payment</h1>
            <p>Submit your rent payment for admin review and confirmation</p>
        </div>

        <div class="payment-container">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($tenantProperty): ?>
                <!-- Property Information -->
                <div class="payment-info">
                    <h3>Property Information</h3>
                    <div class="info-row">
                        <span class="info-label">Property:</span>
                        <span class="info-value"><?php echo htmlspecialchars($tenantProperty['property_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-value"><?php echo htmlspecialchars($tenantProperty['address']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Monthly Rent:</span>
                        <span class="info-value">KES <?php echo number_format($tenantProperty['monthly_rent']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Landlord:</span>
                        <span class="info-value"><?php echo htmlspecialchars($tenantProperty['landlord_name']); ?></span>
                    </div>
                </div>

                <!-- Payment Status Messages -->
                <?php if ($currentMonthPayment): ?>
                    <?php if ($currentMonthPayment['status'] === 'paid'): ?>
                        <div class="alert alert-success">
                            <strong>Payment Confirmed!</strong> Your rent payment for this month has been recorded.
                        </div>
                    <?php elseif ($currentMonthPayment['status'] === 'pending'): ?>
                        <div class="alert alert-warning">
                            <strong>Payment Submitted!</strong> Your payment is pending admin review and confirmation.
                        </div>
                    <?php elseif ($currentMonthPayment['status'] === 'rejected'): ?>
                        <div class="alert alert-danger">
                            <strong>Payment Rejected!</strong> Your payment was rejected. Please submit a new payment.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Payment Form - Always Available -->
                <div class="payment-form">
                    <h3>Submit New Payment</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="amount">Amount (KES)</label>
                                <input type="number" id="amount" name="amount" 
                                       value="<?php echo $tenantProperty['monthly_rent']; ?>" 
                                       step="0.01" min="0" required>
                                <small class="form-text">Enter the amount you are paying (default: monthly rent)</small>
                            </div>
                            <div class="form-group">
                                <label for="payment_method">Payment Method *</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="">Select method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Check">Check</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Online Payment">Online Payment</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="notes">Payment Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="e.g., Reference number, M-Pesa code, additional details..."></textarea>
                        </div>
                        <button type="submit" name="make_payment" class="btn btn-primary">Submit Payment</button>
                        <p class="form-text mt-2">
                            <small>Your payment will be submitted for admin review and confirmation.</small>
                        </p>
                    </form>
                </div>

                <!-- Recent Payments -->
                <?php if (!empty($recentPayments)): ?>
                <div class="recent-payments">
                    <h3>Recent Payments</h3>
                    <?php foreach ($recentPayments as $payment): ?>
                        <div class="payment-item">
                            <div class="payment-details">
                                <div><strong>
                                    <?php 
                                    $paymentDate = $payment['payment_date'];
                                    if ($paymentDate && $paymentDate !== '0000-00-00' && strtotime($paymentDate) > 0) {
                                        echo date('M j, Y', strtotime($paymentDate));
                                    } else {
                                        echo 'Date not set';
                                    }
                                    ?>
                                </strong></div>
                                <div><?php echo htmlspecialchars($payment['payment_method']); ?></div>
                                <?php if ($payment['notes']): ?>
                                    <div><small><?php echo htmlspecialchars($payment['notes']); ?></small></div>
                                <?php endif; ?>
                            </div>
                            <div class="payment-amount">
                                KES <?php echo number_format($payment['amount']); ?>
                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-danger">
                    <strong>No Property Assigned</strong> You are not currently assigned to any property. 
                    Please contact your landlord or administrator.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        }
        
        // Close sidebar when clicking outside on mobile
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>

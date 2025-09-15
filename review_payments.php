<?php
require_once 'config.php';
requireLogin();
requireRole(['admin']);

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

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = (int)$_POST['payment_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'confirm') {
            // Confirm payment
            $db->query("UPDATE rent_payments SET status = 'paid' WHERE id = ?", [$payment_id]);
            
            // Get payment details for notification
            $payment = $db->fetchOne("
                SELECT rp.*, u.full_name as tenant_name, u.email as tenant_email
                FROM rent_payments rp
                JOIN users u ON rp.tenant_id = u.id
                WHERE rp.id = ?
            ", [$payment_id]);
            
            // Notify tenant
            $db->query("
                INSERT INTO notifications (user_id, title, message, type, created_by, created_at) 
                VALUES (?, ?, ?, 'payment_confirmed', ?, NOW())
            ", [
                $payment['tenant_id'],
                'Payment Confirmed',
                "Your payment of KES " . number_format($payment['amount']) . " has been confirmed and recorded. Thank you!",
                $user['id']
            ]);
            
            $message = "Payment confirmed successfully!";
            
        } elseif ($action === 'reject') {
            $reason = sanitizeInput($_POST['reason'] ?? '');
            
            // Reject payment
            $db->query("UPDATE rent_payments SET status = 'rejected', notes = CONCAT(COALESCE(notes, ''), ' REJECTED: ', ?) WHERE id = ?", [$reason, $payment_id]);
            
            // Get payment details for notification
            $payment = $db->fetchOne("
                SELECT rp.*, u.full_name as tenant_name, u.email as tenant_email
                FROM rent_payments rp
                JOIN users u ON rp.tenant_id = u.id
                WHERE rp.id = ?
            ", [$payment_id]);
            
            // Notify tenant
            $db->query("
                INSERT INTO notifications (user_id, title, message, type, created_by, created_at) 
                VALUES (?, ?, ?, 'payment_rejected', ?, NOW())
            ", [
                $payment['tenant_id'],
                'Payment Rejected',
                "Your payment of KES " . number_format($payment['amount']) . " has been rejected. Reason: " . $reason . ". Please contact admin for assistance.",
                $user['id']
            ]);
            
            $message = "Payment rejected successfully!";
        }
    } catch (Exception $e) {
        $error = "Error processing payment: " . $e->getMessage();
    }
}

// Get pending payments
$pendingPayments = $db->fetchAll("
    SELECT 
        rp.*,
        u.full_name as tenant_name,
        u.email as tenant_email,
        u.phone as tenant_phone,
        p.property_name,
        p.address as property_address,
        p.monthly_rent
    FROM rent_payments rp
    JOIN users u ON rp.tenant_id = u.id
    JOIN properties p ON rp.property_id = p.id
    WHERE rp.status = 'pending'
    ORDER BY rp.created_at DESC
");

// Get recent confirmed payments
$recentPayments = $db->fetchAll("
    SELECT 
        rp.*,
        u.full_name as tenant_name,
        p.property_name
    FROM rent_payments rp
    JOIN users u ON rp.tenant_id = u.id
    JOIN properties p ON rp.property_id = p.id
    WHERE rp.status = 'paid'
    ORDER BY rp.payment_date DESC
    LIMIT 10
");

// Get statistics
$stats = [
    'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM rent_payments WHERE status = 'pending'")['count'],
    'paid_today' => $db->fetchOne("SELECT COUNT(*) as count FROM rent_payments WHERE status = 'paid' AND DATE(payment_date) = CURDATE()")['count'],
    'total_amount_pending' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM rent_payments WHERE status = 'pending'")['total'],
    'total_amount_today' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM rent_payments WHERE status = 'paid' AND DATE(payment_date) = CURDATE()")['total']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Payments - Rent Collection System</title>
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
        
        .payment-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .payment-header {
            background: #f8f8f8;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .payment-header h4 {
            margin: 0 0 5px 0;
            color: #333;
            font-weight: 600;
        }
        
        .payment-header small {
            color: #666;
        }
        
        .payment-body {
            padding: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group strong {
            color: #333;
            font-weight: 500;
        }
        
        .payment-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #666;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #555;
        }
        
        .btn-confirm {
            background: #27ae60;
        }
        
        .btn-confirm:hover {
            background: #229954;
        }
        
        .btn-reject {
            background: #e74c3c;
        }
        
        .btn-reject:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .reject-reason {
            margin-top: 10px;
            display: none;
        }
        
        .reject-reason textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            margin-bottom: 10px;
        }
        
        .no-payments {
            text-align: center;
            padding: 40px;
            color: #666;
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
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .payment-actions {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Admin Panel</h2>
                <div class="user-role">Admin</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="manage_payments.php">Manage Payments</a></li>
                <li><a href="review_payments.php" class="active">Review Payments</a></li>
                <li><a href="financial_analytics.php">Financial Analytics</a></li>
                <li><a href="tenant_messages.php">Tenant Messages</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="system_reports.php">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Review Payments</h1>
                <p>Review and confirm tenant payment submissions</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['paid_today']; ?></div>
                    <div class="stat-label">Paid Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">KES <?php echo number_format($stats['total_amount_pending']); ?></div>
                    <div class="stat-label">Amount Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">KES <?php echo number_format($stats['total_amount_today']); ?></div>
                    <div class="stat-label">Amount Today</div>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="card">
                <div class="card-header">
                    <h3>Pending Payments (<?php echo count($pendingPayments); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingPayments)): ?>
                        <div class="no-payments">
                            <h4>No pending payments</h4>
                            <p>All payments have been reviewed.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingPayments as $payment): ?>
                            <div class="payment-card">
                                <div class="payment-header">
                                    <h4>Payment #<?php echo $payment['id']; ?> - <?php echo htmlspecialchars($payment['tenant_name']); ?></h4>
                                    <small>Submitted: <?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></small>
                                </div>
                                <div class="payment-body">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <strong>Tenant:</strong> <?php echo htmlspecialchars($payment['tenant_name']); ?><br>
                                            <strong>Email:</strong> <?php echo htmlspecialchars($payment['tenant_email']); ?><br>
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($payment['tenant_phone']); ?>
                                        </div>
                                        <div class="form-group">
                                            <strong>Property:</strong> <?php echo htmlspecialchars($payment['property_name']); ?><br>
                                            <strong>Address:</strong> <?php echo htmlspecialchars($payment['property_address']); ?><br>
                                            <strong>Monthly Rent:</strong> KES <?php echo number_format($payment['monthly_rent']); ?>
                                        </div>
                                        <div class="form-group">
                                            <strong>Amount Paid:</strong> KES <?php echo number_format($payment['amount']); ?><br>
                                            <strong>Payment Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?><br>
                                            <strong>Payment Date:</strong> <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($payment['notes']): ?>
                                        <div class="form-group">
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="payment-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button type="submit" class="btn-confirm">✓ Confirm Payment</button>
                                        </form>
                                        
                                        <button type="button" class="btn-reject" onclick="showRejectForm(<?php echo $payment['id']; ?>)">✗ Reject Payment</button>
                                        
                                        <form method="POST" id="reject-form-<?php echo $payment['id']; ?>" style="display: none;">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <div class="reject-reason">
                                                <textarea name="reason" placeholder="Reason for rejection..." required></textarea>
                                                <button type="submit" class="btn-reject">Reject Payment</button>
                                                <button type="button" onclick="hideRejectForm(<?php echo $payment['id']; ?>)" class="btn btn-secondary">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Confirmed Payments -->
            <?php if (!empty($recentPayments)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Recent Confirmed Payments</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Tenant</th>
                                    <th>Property</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                        <td>KES <?php echo number_format($payment['amount']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showRejectForm(paymentId) {
            document.getElementById('reject-form-' + paymentId).style.display = 'block';
        }
        
        function hideRejectForm(paymentId) {
            document.getElementById('reject-form-' + paymentId).style.display = 'none';
        }
    </script>
</body>
</html>

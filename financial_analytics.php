<?php
require_once 'config.php';

// Check if user is logged in and is admin
requireLogin();
requireRole(['admin']);

$user = getCurrentUser();
$db = new Database();

// Automatically update overdue payments every time dashboard is accessed
$db->query("
    UPDATE rent_payments 
    SET status = 'overdue' 
    WHERE status = 'pending' 
    AND due_date < CURDATE()
");

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'send_reminder':
                    $payment_id = (int)$_POST['payment_id'];
                    $tenant_id = (int)$_POST['tenant_id'];
                    $amount = (float)$_POST['amount'];
                    $days_overdue = (int)$_POST['days_overdue'];
                    
                    // Create notification for tenant
                    $db->query("
                        INSERT INTO notifications (user_id, title, message, type, created_by, created_at) 
                        VALUES (?, ?, ?, 'payment_reminder', ?, NOW())
                    ", [
                        $tenant_id,
                        'Payment Reminder',
                        "Your rent payment of KES " . number_format($amount) . " is " . 
                        ($days_overdue > 0 ? $days_overdue . " days overdue" : "due") . 
                        ". Please make payment as soon as possible to avoid additional charges.",
                        $user['id'] // Admin/Landlord ID who sent the reminder
                    ]);
                    
                    $success_message = "Payment reminder sent successfully!";
                    break;
                    
                case 'mark_collected':
                    $payment_id = (int)$_POST['payment_id'];
                    
                    // Update payment status to paid
                    $db->query("UPDATE rent_payments SET status = 'paid', payment_date = CURDATE() WHERE id = ?", [$payment_id]);
                    
                    $success_message = "Payment marked as collected successfully!";
                    break;
                    
                case 'update_overdue':
                    // Update overdue status for all payments
                    $updatedCount = $db->query("
                        UPDATE rent_payments 
                        SET status = 'overdue' 
                        WHERE status = 'pending' 
                        AND due_date < CURDATE()
                    ")->rowCount();
                    
                    $success_message = "Overdue status updated successfully! " . $updatedCount . " payments marked as overdue.";
                    break;
                    
                case 'export_excel':
                    // Export financial data to Excel format
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment; filename="financial_analytics_' . date('Y-m-d') . '.csv"');
                    
                    // Get outstanding data for export
                    $exportData = $db->query("
                        SELECT 
                            p.property_name,
                            u.full_name as tenant_name,
                            rp.amount,
                            rp.due_date,
                            DATEDIFF(CURDATE(), rp.due_date) as days_overdue,
                            rp.status,
                            rp.payment_date
                        FROM rent_payments rp
                        JOIN properties p ON rp.property_id = p.id
                        JOIN users u ON rp.tenant_id = u.id
                        WHERE rp.status IN ('pending', 'overdue')
                        ORDER BY rp.due_date ASC
                    ")->fetchAll();
                    
                    echo "Property,Tenant,Amount,Due Date,Days Overdue,Status,Payment Date\n";
                    foreach ($exportData as $row) {
                        echo '"' . $row['property_name'] . '","' . $row['tenant_name'] . '",' . 
                             $row['amount'] . ',"' . $row['due_date'] . '",' . 
                             $row['days_overdue'] . ',"' . $row['status'] . '","' . 
                             ($row['payment_date'] ?? '') . '"' . "\n";
                    }
                    exit;
                    break;
                    
                case 'bulk_reminders':
                    // Send reminders to all overdue tenants
                    $overduePayments = $db->query("
                        SELECT rp.*, u.id as tenant_id, u.full_name as tenant_name
                        FROM rent_payments rp
                        JOIN users u ON rp.tenant_id = u.id
                        WHERE rp.status IN ('pending', 'overdue')
                        AND rp.due_date < CURDATE()
                    ")->fetchAll();
                    
                    $reminderCount = 0;
                    foreach ($overduePayments as $payment) {
                        $daysOverdue = max(0, (strtotime(date('Y-m-d')) - strtotime($payment['due_date'])) / (60 * 60 * 24));
                        
                        $db->query("
                            INSERT INTO notifications (user_id, title, message, type, created_by, created_at) 
                            VALUES (?, ?, ?, 'payment_reminder', ?, NOW())
                        ", [
                            $payment['tenant_id'],
                            'Urgent: Overdue Rent Payment',
                            "Dear " . $payment['tenant_name'] . ", your rent payment of KES " . 
                            number_format($payment['amount']) . " is " . $daysOverdue . 
                            " days overdue. Please make payment immediately to avoid additional charges.",
                            $user['id'] // Admin/Landlord ID who sent the reminder
                        ]);
                        $reminderCount++;
                    }
                    
                    $success_message = "Bulk reminders sent to " . $reminderCount . " overdue tenants!";
                    break;
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get financial analytics data
try {
    // Total revenue (all paid payments)
    $totalRevenue = $db->fetchOne("SELECT SUM(amount) as total FROM rent_payments WHERE status = 'paid'")['total'] ?? 0;
    
    // Outstanding rent (pending + overdue payments)
    $outstandingRent = $db->fetchOne("SELECT SUM(amount) as total FROM rent_payments WHERE status IN ('pending', 'overdue')")['total'] ?? 0;
    
    // Total properties
    $totalProperties = $db->fetchOne("SELECT COUNT(*) as count FROM properties")['count'] ?? 0;
    
    // Occupied properties (properties with active tenants)
    $occupiedProperties = $db->fetchOne("
        SELECT COUNT(DISTINCT property_id) as count 
        FROM tenant_properties tp 
        JOIN users u ON tp.tenant_id = u.id 
        WHERE u.is_active = 1
    ")['count'] ?? 0;
    
    // Vacant properties
    $vacantProperties = $totalProperties - $occupiedProperties;
    
    // Collection rate (percentage of payments that are paid vs total)
    $totalPayments = $db->fetchOne("SELECT COUNT(*) as count FROM rent_payments")['count'] ?? 0;
    $paidPayments = $db->fetchOne("SELECT COUNT(*) as count FROM rent_payments WHERE status = 'paid'")['count'] ?? 0;
    $collectionRate = $totalPayments > 0 ? round(($paidPayments / $totalPayments) * 100, 1) : 0;
    
    // Monthly revenue for the last 12 months
    $monthlyRevenue = $db->query("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount) as revenue,
            COUNT(*) as payment_count
        FROM rent_payments 
        WHERE status = 'paid' 
        AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll();
    
    
    // Year-over-year comparison
    $currentYearRevenue = $db->fetchOne("
        SELECT SUM(amount) as total 
        FROM rent_payments 
        WHERE status = 'paid' 
        AND YEAR(payment_date) = YEAR(CURDATE())
    ")['total'] ?? 0;
    
    $lastYearRevenue = $db->fetchOne("
        SELECT SUM(amount) as total 
        FROM rent_payments 
        WHERE status = 'paid' 
        AND YEAR(payment_date) = YEAR(CURDATE()) - 1
    ")['total'] ?? 0;
    
    $revenueGrowth = $lastYearRevenue > 0 ? round((($currentYearRevenue - $lastYearRevenue) / $lastYearRevenue) * 100, 1) : 0;
    
    // Collection rate trend (last 6 months) - using due_date for better trend analysis
    $collectionTrend = $db->query("
        SELECT 
            DATE_FORMAT(due_date, '%Y-%m') as month,
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_payments,
            ROUND((SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as collection_rate
        FROM rent_payments 
        WHERE due_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(due_date, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll();
    
    
    // Outstanding rent by property
    $outstandingByProperty = $db->query("
        SELECT 
            rp.id,
            rp.tenant_id,
            p.property_name,
            p.address,
            p.unit_number,
            p.block_wing,
            u.full_name as tenant_name,
            rp.amount,
            rp.due_date,
            rp.status,
            DATEDIFF(CURDATE(), rp.due_date) as days_overdue
        FROM rent_payments rp
        JOIN properties p ON rp.property_id = p.id
        JOIN users u ON rp.tenant_id = u.id
        WHERE rp.status IN ('pending', 'overdue')
        ORDER BY rp.due_date ASC
    ")->fetchAll();
    
    // Top performing properties (by revenue)
    $topProperties = $db->query("
        SELECT 
            p.property_name,
            p.address,
            COUNT(rp.id) as total_payments,
            SUM(rp.amount) as total_revenue,
            AVG(rp.amount) as avg_payment
        FROM properties p
        LEFT JOIN rent_payments rp ON p.id = rp.property_id AND rp.status = 'paid'
        GROUP BY p.id, p.property_name, p.address
        ORDER BY total_revenue DESC
        LIMIT 10
    ")->fetchAll();
    
    // Payment status breakdown
    $paymentStatusBreakdown = $db->query("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM rent_payments
        GROUP BY status
    ")->fetchAll();
    
    // Recent payments
    $recentPayments = $db->query("
        SELECT 
            rp.id,
            rp.amount,
            rp.payment_date,
            rp.status,
            u.full_name as tenant_name,
            p.property_name,
            p.unit_number
        FROM rent_payments rp
        JOIN users u ON rp.tenant_id = u.id
        JOIN properties p ON rp.property_id = p.id
        ORDER BY rp.payment_date DESC
        LIMIT 10
    ")->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Error fetching financial data: " . $e->getMessage();
    $totalRevenue = $outstandingRent = $totalProperties = $occupiedProperties = $vacantProperties = $collectionRate = 0;
    $monthlyRevenue = $outstandingByProperty = $topProperties = $paymentStatusBreakdown = $recentPayments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Analytics - Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
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
        
        .mobile-header h1 {
            font-size: 1.2rem;
            margin: 0;
        }
        
        .mobile-user-info {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .hamburger {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
        }
        
        /* Overlay */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .overlay.show {
            display: block;
        }
        
        /* Dashboard Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
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
        }
        
        .content-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px 24px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .content-header h1 {
            margin: 0 0 8px 0;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .content-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
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
        
        /* Key Metrics */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #28a745);
        }
        
        .metric-card.revenue::before {
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        
        .metric-card.outstanding::before {
            background: linear-gradient(90deg, #ffc107, #fd7e14);
        }
        
        .metric-card.occupancy::before {
            background: linear-gradient(90deg, #17a2b8, #6f42c1);
        }
        
        .metric-card.collection::before {
            background: linear-gradient(90deg, #dc3545, #e83e8c);
        }
        
        .metric-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }
        
        .metric-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .metric-change {
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .metric-change.positive {
            color: #28a745;
        }
        
        .metric-change.negative {
            color: #dc3545;
        }
        
        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .chart-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
        }
        
        .chart-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.2rem;
        }
        
        .chart-body {
            padding: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Tables */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
        }
        
        .table-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.2rem;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
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
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .overdue-days {
            color: #dc3545;
            font-weight: 600;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }
            
            .metrics-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .table-container {
                border-radius: 8px;
                overflow-x: auto;
            }
            
            table {
                min-width: 600px;
            }
            
            th, td {
                padding: 8px 10px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <h1>Financial Analytics</h1>
        <div class="mobile-user-info">
            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role">Admin</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="manage_payments.php">Manage Payments</a></li>
                <li><a href="admin_payment_receipts.php">Payment Receipts</a></li>
                <li><a href="tenant_messages.php">Tenant Messages</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="system_reports.php">Reports</a></li>
                <li><a href="financial_analytics.php" class="active">Financial Analytics</a></li>
                <li><a href="system_settings.php">System Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Financial Analytics</h1>
                <p>Comprehensive financial overview and performance metrics</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <!-- Key Metrics -->
            <div class="metrics-grid">
                <div class="metric-card revenue">
                    <div class="metric-number">KES <?php echo number_format($totalRevenue); ?></div>
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-change <?php echo $revenueGrowth >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $revenueGrowth >= 0 ? '+' : ''; ?><?php echo $revenueGrowth; ?>% vs last year
                    </div>
                </div>
                
                <div class="metric-card outstanding">
                    <div class="metric-number">KES <?php echo number_format($outstandingRent); ?></div>
                    <div class="metric-label">Outstanding Rent</div>
                    <div class="metric-change negative">
                        <?php echo count($outstandingByProperty); ?> payments pending
                    </div>
                </div>
                
                <div class="metric-card occupancy">
                    <div class="metric-number"><?php echo $occupiedProperties; ?>/<?php echo $totalProperties; ?></div>
                    <div class="metric-label">Occupied Properties</div>
                    <div class="metric-change">
                        <?php echo round(($occupiedProperties / max($totalProperties, 1)) * 100, 1); ?>% occupancy rate
                    </div>
                </div>
                
                <div class="metric-card collection">
                    <div class="metric-number"><?php echo $collectionRate; ?>%</div>
                    <div class="metric-label">Collection Rate</div>
                    <div class="metric-change <?php echo $collectionRate >= 80 ? 'positive' : 'negative'; ?>">
                        <?php echo $paidPayments; ?>/<?php echo $totalPayments; ?> payments
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0; color: #333;">Quick Actions</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="update_overdue">
                        <button type="submit" class="btn btn-warning">Update Overdue Status</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="bulk_reminders">
                        <button type="submit" class="btn btn-danger">Send Bulk Reminders</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="export_excel">
                        <button type="submit" class="btn btn-success">Export to Excel</button>
                    </form>
                    
                    <a href="manage_payments.php" class="btn btn-primary">Manage Payments</a>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Monthly Revenue Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Revenue by Period</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Status Breakdown -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Payment Status Distribution</h3>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (count($collectionTrend) > 0): ?>
            <!-- Collection Rate Trend -->
            <div class="chart-card" style="margin-bottom: 20px;">
                <div class="chart-header">
                    <h3>Collection Rate Trend (Last 6 Months)</h3>
                </div>
                <div class="chart-body">
                    <div class="chart-container">
                        <canvas id="collectionChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Outstanding Rent Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3>Outstanding Rent by Property</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Tenant</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($outstandingByProperty)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                                        No outstanding rent payments.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($outstandingByProperty as $outstanding): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo htmlspecialchars($outstanding['property_name']); ?></div>
                                            <small style="color: #666;">
                                                <?php echo htmlspecialchars($outstanding['address']); ?>
                                                <?php if ($outstanding['unit_number']): ?>
                                                    - Unit <?php echo htmlspecialchars($outstanding['unit_number']); ?>
                                                <?php endif; ?>
                                                <?php if ($outstanding['block_wing']): ?>
                                                    (<?php echo htmlspecialchars($outstanding['block_wing']); ?>)
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($outstanding['tenant_name']); ?></td>
                                        <td>KES <?php echo number_format($outstanding['amount']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($outstanding['due_date'])); ?></td>
                                        <td>
                                            <?php if ($outstanding['days_overdue'] > 0): ?>
                                                <span class="overdue-days"><?php echo $outstanding['days_overdue']; ?> days</span>
                                            <?php else: ?>
                                                <span style="color: #28a745;">On time</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $outstanding['status']; ?>">
                                                <?php echo ucfirst($outstanding['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="send_reminder">
                                                    <input type="hidden" name="payment_id" value="<?php echo $outstanding['id']; ?>">
                                                    <input type="hidden" name="tenant_id" value="<?php echo $outstanding['tenant_id']; ?>">
                                                    <input type="hidden" name="amount" value="<?php echo $outstanding['amount']; ?>">
                                                    <input type="hidden" name="days_overdue" value="<?php echo $outstanding['days_overdue']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm">Send Reminder</button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="mark_collected">
                                                    <input type="hidden" name="payment_id" value="<?php echo $outstanding['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">Mark Collected</button>
                                                </form>
                                                
                                                <a href="manage_payments.php" class="btn btn-primary btn-sm">View Details</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Top Performing Properties -->
            <div class="table-card">
                <div class="table-header">
                    <h3>Top Performing Properties</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Total Payments</th>
                                <th>Total Revenue</th>
                                <th>Average Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topProperties)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: #666;">
                                        No payment data available.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($topProperties as $property): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo htmlspecialchars($property['property_name']); ?></div>
                                            <small style="color: #666;"><?php echo htmlspecialchars($property['address']); ?></small>
                                        </td>
                                        <td><?php echo $property['total_payments']; ?></td>
                                        <td>KES <?php echo number_format($property['total_revenue'] ?? 0); ?></td>
                                        <td>KES <?php echo number_format($property['avg_payment'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('overlay');
                const hamburger = document.querySelector('.hamburger');
                
                if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                }
            }
        });
        
        // Close sidebar when window is resized to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?php echo json_encode($monthlyRevenue); ?>;
        
        if (revenueData.length > 0) {
            const revenueLabels = revenueData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            });
            
            const revenueValues = revenueData.map(item => parseFloat(item.revenue) || 0);
            
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: revenueLabels,
                    datasets: [{
                        label: 'Revenue',
                        data: revenueValues,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#007bff',
                        pointBorderColor: '#007bff',
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Revenue by Period',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: 20
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: true,
                                color: 'rgba(0,0,0,0.1)',
                                lineWidth: 1
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0,0,0,0.1)',
                                lineWidth: 1
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return 'KES ' + (value / 1000) + 'K';
                                }
                            }
                        }
                    }
                }
            });
        } else {
            // Show message when no data
            revenueCtx.font = '16px Arial';
            revenueCtx.fillStyle = '#666';
            revenueCtx.textAlign = 'center';
            revenueCtx.fillText('No revenue data available', revenueCtx.canvas.width / 2, revenueCtx.canvas.height / 2);
        }
        
        // Status Chart - Bar Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($paymentStatusBreakdown); ?>;
        
        const statusLabels = statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1));
        const statusValues = statusData.map(item => parseInt(item.count));
        const statusColors = ['#20c997', '#ffc107', '#dc3545']; // Teal, Yellow, Red
        
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Payment Count',
                    data: statusValues,
                    backgroundColor: statusColors,
                    borderColor: statusColors,
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Payment Status Distribution',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: 20
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: 'rgba(0,0,0,0.1)',
                            lineWidth: 1
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            color: 'rgba(0,0,0,0.1)',
                            lineWidth: 1
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Collection Rate Trend Chart (only if data exists)
        <?php if (count($collectionTrend) > 0): ?>
        const collectionCtx = document.getElementById('collectionChart').getContext('2d');
        const collectionData = <?php echo json_encode($collectionTrend); ?>;
        
        const collectionLabels = collectionData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        
        const collectionValues = collectionData.map(item => parseFloat(item.collection_rate) || 0);
        
        new Chart(collectionCtx, {
            type: 'line',
            data: {
                labels: collectionLabels,
                datasets: [{
                    label: 'Collection Rate',
                    data: collectionValues,
                    borderColor: '#fd7e14',
                    backgroundColor: 'rgba(253, 126, 20, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: '#fd7e14',
                    pointBorderColor: '#fd7e14',
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Collection Rate Trend',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: 20
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: 'rgba(0,0,0,0.1)',
                            lineWidth: 1
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            display: true,
                            color: 'rgba(0,0,0,0.1)',
                            lineWidth: 1
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>

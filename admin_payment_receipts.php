<?php
require_once 'config.php';

// Check if user is logged in and is admin
requireLogin();
requireRole(['admin']);

$user = getCurrentUser();
$db = new Database();

// Get all payment receipts with tenant and property information
try {
    $receipts = $db->query("
        SELECT 
            rp.id,
            rp.amount,
            rp.payment_date,
            rp.payment_method,
            rp.status,
            rp.created_at,
            rp.due_date,
            u.full_name as tenant_name,
            u.email as tenant_email,
            u.phone as tenant_phone,
            p.property_name,
            p.address as property_address,
            p.unit_number,
            p.block_wing,
            p.floor_number,
            p.door_number,
            p.monthly_rent,
            l.full_name as landlord_name,
            l.email as landlord_email
        FROM rent_payments rp
        JOIN users u ON rp.tenant_id = u.id
        JOIN properties p ON rp.property_id = p.id
        JOIN users l ON p.landlord_id = l.id
        ORDER BY rp.payment_date DESC, rp.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching payment receipts: " . $e->getMessage();
    $receipts = [];
}

// Handle receipt actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['receipt_id'])) {
        $receipt_id = (int)$_POST['receipt_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'view_receipt':
                    // Redirect to view receipt page
                    header("Location: view_receipt.php?id=" . $receipt_id);
                    exit;
                    break;
                    
                case 'download_receipt':
                    // Redirect to download receipt page
                    header("Location: download_receipt.php?id=" . $receipt_id);
                    exit;
                    break;
            }
        } catch (Exception $e) {
            $error = "Error processing receipt action: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipts - Admin Dashboard</title>
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
            background: linear-gradient(135deg, #555, #666);
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Filters */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #117a8b;
        }
        
        /* Receipts Table */
        .receipts-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .receipts-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
        }
        
        .receipts-header h3 {
            margin: 0;
            color: #333;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
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
            position: sticky;
            top: 0;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 10px;
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
            
            .action-buttons {
                flex-direction: column;
                gap: 3px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <h1>Payment Receipts</h1>
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
                <li><a href="admin_payment_receipts.php" class="active">Payment Receipts</a></li>
                <li><a href="tenant_messages.php">Tenant Messages</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="system_reports.php">Reports</a></li>
                <li><a href="system_settings.php">System Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Payment Receipts</h1>
                <p>View and manage all payment receipts from all landlords across the entire system</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($receipts); ?></div>
                    <div class="stat-label">Total Receipts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($receipts, function($r) { return $r['status'] === 'paid'; })); ?></div>
                    <div class="stat-label">Paid Receipts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">KES <?php echo number_format(array_sum(array_column(array_filter($receipts, function($r) { return $r['status'] === 'paid'; }), 'amount'))); ?></div>
                    <div class="stat-label">Total Amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($receipts, function($r) { return $r['status'] === 'pending'; })); ?></div>
                    <div class="stat-label">Pending Receipts</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <h3>Filter Receipts</h3>
                <form method="GET" class="filter-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">All Statuses</option>
                            <option value="paid" <?php echo (isset($_GET['status']) && $_GET['status'] === 'paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="overdue" <?php echo (isset($_GET['status']) && $_GET['status'] === 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tenant">Tenant</label>
                        <input type="text" name="tenant" id="tenant" placeholder="Search by tenant name" value="<?php echo htmlspecialchars($_GET['tenant'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="property">Property</label>
                        <input type="text" name="property" id="property" placeholder="Search by property" value="<?php echo htmlspecialchars($_GET['property'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="landlord">Landlord</label>
                        <input type="text" name="landlord" id="landlord" placeholder="Search by landlord" value="<?php echo htmlspecialchars($_GET['landlord'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="admin_payment_receipts.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Receipts Table -->
            <div class="receipts-container">
                <div class="receipts-header">
                    <h3>All Payment Receipts (System-Wide)</h3>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">Showing receipts from all landlords across the entire system</p>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Receipt ID</th>
                                <th>Tenant</th>
                                <th>Property</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Status</th>
                                <th>Landlord</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($receipts)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                        No payment receipts found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($receipts as $receipt): ?>
                                    <tr>
                                        <td>#<?php echo $receipt['id']; ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($receipt['tenant_name']); ?></div>
                                            <small style="color: #666;"><?php echo htmlspecialchars($receipt['tenant_email']); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($receipt['property_name']); ?></div>
                                            <small style="color: #666;">
                                                <?php echo htmlspecialchars($receipt['property_address']); ?>
                                                <?php if ($receipt['unit_number']): ?>
                                                    - Unit <?php echo htmlspecialchars($receipt['unit_number']); ?>
                                                <?php endif; ?>
                                                <?php if ($receipt['block_wing']): ?>
                                                    (<?php echo htmlspecialchars($receipt['block_wing']); ?>)
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>KES <?php echo number_format($receipt['amount']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($receipt['payment_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $receipt['status']; ?>">
                                                <?php echo ucfirst($receipt['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($receipt['landlord_name']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="receipt_id" value="<?php echo $receipt['id']; ?>">
                                                    <input type="hidden" name="action" value="view_receipt">
                                                    <button type="submit" class="btn btn-info btn-sm">View</button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="receipt_id" value="<?php echo $receipt['id']; ?>">
                                                    <input type="hidden" name="action" value="download_receipt">
                                                    <button type="submit" class="btn btn-success btn-sm">Download</button>
                                                </form>
                                            </div>
                                        </td>
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
    </script>
</body>
</html>

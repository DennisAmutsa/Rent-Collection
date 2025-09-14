<?php
require_once 'config.php';
requireRole('landlord');

$user = getCurrentUser();
$db = new Database();

// Get landlord's properties
$properties = $db->fetchAll(
    "SELECT p.*, COUNT(tp.tenant_id) as tenant_count 
     FROM properties p 
     LEFT JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1
     WHERE p.landlord_id = ? 
     GROUP BY p.id",
    [$user['id']]
);

// Get total rent collected this month
$currentMonth = date('Y-m');
$totalRentCollected = $db->fetchOne(
    "SELECT COALESCE(SUM(amount), 0) as total 
     FROM rent_payments rp 
     JOIN properties p ON rp.property_id = p.id 
     WHERE p.landlord_id = ? AND rp.status = 'paid' AND DATE_FORMAT(rp.payment_date, '%Y-%m') = ?",
    [$user['id'], $currentMonth]
)['total'];

// Get pending payments
$pendingPayments = $db->fetchAll(
    "SELECT rp.*, u.full_name as tenant_name, p.property_name 
     FROM rent_payments rp 
     JOIN users u ON rp.tenant_id = u.id 
     JOIN properties p ON rp.property_id = p.id 
     WHERE p.landlord_id = ? AND rp.status = 'pending' 
     ORDER BY rp.due_date ASC",
    [$user['id']]
);

// Get overdue payments
$overduePayments = $db->fetchAll(
    "SELECT rp.*, u.full_name as tenant_name, p.property_name 
     FROM rent_payments rp 
     JOIN users u ON rp.tenant_id = u.id 
     JOIN properties p ON rp.property_id = p.id 
     WHERE p.landlord_id = ? AND rp.status = 'overdue' 
     ORDER BY rp.due_date ASC",
    [$user['id']]
);

// Get recent activities
$recentActivities = $db->fetchAll(
    "SELECT 'payment' as type, rp.payment_date as date, 
            CONCAT('Rent payment of $', rp.amount, ' received from ', u.full_name) as description
     FROM rent_payments rp 
     JOIN users u ON rp.tenant_id = u.id 
     JOIN properties p ON rp.property_id = p.id 
     WHERE p.landlord_id = ? AND rp.status = 'paid'
     ORDER BY rp.payment_date DESC 
     LIMIT 10",
    [$user['id']]
);

// Calculate total properties and tenants
$totalProperties = count($properties);
$totalTenants = $db->fetchOne(
    "SELECT COUNT(DISTINCT tp.tenant_id) as count 
     FROM tenant_properties tp 
     JOIN properties p ON tp.property_id = p.id 
     WHERE p.landlord_id = ? AND tp.is_active = 1",
    [$user['id']]
)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Mobile-first responsive design */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Mobile Header */
        .mobile-header {
            display: none;
            background: #ffffff;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .hamburger {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
            padding: 0.5rem;
        }

        .mobile-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .mobile-user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 999;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .user-role {
            background: #f1f5f9;
            color: #475569;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .sidebar-menu a:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .sidebar-menu a.active {
            background: #3b82f6;
            color: #ffffff;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            width: 100%;
            max-width: 100%;
        }

        .content-header {
            margin-bottom: 2rem;
        }

        .content-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .content-header p {
            color: #64748b;
            font-size: 1.125rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Cards */
        .card {
            background: #ffffff;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem 1.5rem 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .card-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .card-content {
            padding: 0 1.5rem 1.5rem;
        }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            padding: 0 1.5rem 1.5rem;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
            background: #3b82f6;
            color: #ffffff;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            text-align: center;
        }

        .quick-action-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            padding: 0 1.5rem 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        td {
            color: #64748b;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        /* Overlay */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        .overlay.active {
            display: block;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 5rem 1rem 2rem;
            }

            .content-header h1 {
                font-size: 1.5rem;
            }

            .content-header p {
                font-size: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .quick-actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 0.75rem;
            }

            .quick-action-btn {
                padding: 0.75rem;
                font-size: 0.875rem;
            }

            .card-header {
                padding: 1rem 1rem 0;
            }

            .card-content {
                padding: 0 1rem 1rem;
            }

            .table-container {
                padding: 0 1rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 4.5rem 0.75rem 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
            }

            .content-header h1 {
                font-size: 1.25rem;
            }

            .content-header p {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <h1>Landlord Dashboard</h1>
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
                <div class="user-role">Landlord</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="landlord_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="manage_tenants.php">Manage Tenants</a></li>
                <li><a href="manage_payments.php">Manage Payments</a></li>
                <li><a href="landlord_receipts.php">Payment Receipts</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="system_reports.php">System Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Landlord Control Center</h1>
                <p>Complete control over your rental empire - manage everything from here!</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalProperties; ?></div>
                    <div class="stat-label">Properties</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalTenants; ?></div>
                    <div class="stat-label">Active Tenants</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatCurrency($totalRentCollected); ?></div>
                    <div class="stat-label">Rent Collected (This Month)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($overduePayments); ?></div>
                    <div class="stat-label">Overdue Payments</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="quick-actions-grid">
                    <a href="manage_properties.php" class="quick-action-btn">
                        Add Property
                    </a>
                    <a href="manage_tenants.php" class="quick-action-btn">
                        Add Tenant
                    </a>
                    <a href="send_notifications.php" class="quick-action-btn">
                        Send Notification
                    </a>
                    <a href="manage_users.php" class="quick-action-btn">
                        Manage Users
                    </a>
                    <a href="system_reports.php" class="quick-action-btn">
                        View Reports
                    </a>
                    <a href="manage_payments.php" class="quick-action-btn">
                        Manage Payments
                    </a>
                </div>
            </div>
            
            <div class="content-grid">
                <!-- Properties Overview -->
                <div class="card">
                    <div class="card-header">
                        <h3>My Properties</h3>
                    </div>
                    <div class="card-content">
                        <?php if (empty($properties)): ?>
                            <p>No properties found. <a href="add_property.php">Add your first property</a></p>
                        <?php else: ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Property</th>
                                            <th>Monthly Rent</th>
                                            <th>Tenants</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($properties as $property): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($property['property_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($property['address']); ?></small>
                                                </td>
                                                <td><?php echo formatCurrency($property['monthly_rent']); ?></td>
                                                <td><?php echo $property['tenant_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activities</h3>
                    </div>
                    <div class="card-content">
                        <?php if (empty($recentActivities)): ?>
                            <p>No recent activities</p>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($activity['description']); ?></div>
                                        <small style="color: #718096;"><?php echo formatDate($activity['date']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pending Payments -->
            <?php if (!empty($pendingPayments)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Pending Payments</h3>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingPayments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                                            <td><?php echo formatDate($payment['due_date']); ?></td>
                                            <td><span class="status-badge status-pending">Pending</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Overdue Payments -->
            <?php if (!empty($overduePayments)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Overdue Payments</h3>
                    </div>
                    <div class="card-content">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Days Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overduePayments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                                            <td><?php echo formatDate($payment['due_date']); ?></td>
                                            <td><?php echo max(0, (strtotime('now') - strtotime($payment['due_date'])) / (60 * 60 * 24)); ?> days</td>
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
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }

        // Close sidebar when clicking on a menu item (mobile)
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        // Close sidebar on window resize if screen becomes larger
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>

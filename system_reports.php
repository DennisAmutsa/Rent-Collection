<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin') && !hasRole('landlord')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Get comprehensive system statistics
$stats = [];

if ($user['role'] === 'admin') {
    // Admin sees system-wide statistics
    $stats['total_properties'] = $db->fetchOne("SELECT COUNT(*) as count FROM properties")['count'];
    $stats['occupied_properties'] = $db->fetchOne(
        "SELECT COUNT(DISTINCT p.id) as count FROM properties p 
         JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1"
    )['count'];
    
    $stats['total_tenants'] = $db->fetchOne(
        "SELECT COUNT(DISTINCT u.id) as count FROM users u
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         WHERE u.role = 'tenant'"
    )['count'];
    
    $stats['total_payments'] = $db->fetchOne("SELECT COUNT(*) as count FROM rent_payments")['count'];
    $stats['total_collected'] = $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM rent_payments")['total'];
} else {
    // Landlord sees only their own statistics
    $stats['total_properties'] = $db->fetchOne("SELECT COUNT(*) as count FROM properties WHERE landlord_id = ?", [$user['id']])['count'];
    $stats['occupied_properties'] = $db->fetchOne(
        "SELECT COUNT(DISTINCT p.id) as count FROM properties p 
         JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1 
         WHERE p.landlord_id = ?", 
        [$user['id']]
    )['count'];
    
    $stats['total_tenants'] = $db->fetchOne(
        "SELECT COUNT(DISTINCT u.id) as count FROM users u
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         WHERE p.landlord_id = ? AND u.role = 'tenant'", 
        [$user['id']]
    )['count'];
    
    $stats['total_payments'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         WHERE p.landlord_id = ?", 
        [$user['id']]
    )['count'];
    
    $stats['total_collected'] = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         WHERE p.landlord_id = ?", 
        [$user['id']]
    )['total'];
}

// Recent payments
if ($user['role'] === 'admin') {
    // Admin sees all recent payments
    $recentPayments = $db->fetchAll(
        "SELECT rp.*, u.full_name as tenant_name, p.property_name
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         ORDER BY rp.payment_date DESC
         LIMIT 10"
    );
} else {
    // Landlord sees only their payments
    $recentPayments = $db->fetchAll(
        "SELECT rp.*, u.full_name as tenant_name, p.property_name
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         WHERE p.landlord_id = ?
         ORDER BY rp.payment_date DESC
         LIMIT 10",
        [$user['id']]
    );
}

// Property occupancy details
if ($user['role'] === 'admin') {
    // Admin sees all properties
    $propertyDetails = $db->fetchAll(
        "SELECT p.*, COUNT(tp.tenant_id) as tenant_count,
                COALESCE(SUM(rp.amount), 0) as total_collected
         FROM properties p
         LEFT JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1
         LEFT JOIN rent_payments rp ON tp.tenant_id = rp.tenant_id
         GROUP BY p.id
         ORDER BY p.property_name"
    );
    
    // Monthly income (last 6 months) - all properties
    $monthlyIncome = $db->fetchAll(
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount) as total
         FROM rent_payments rp
         WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
         ORDER BY month DESC"
    );
} else {
    // Landlord sees only their properties
    $propertyDetails = $db->fetchAll(
        "SELECT p.*, COUNT(tp.tenant_id) as tenant_count,
                COALESCE(SUM(rp.amount), 0) as total_collected
         FROM properties p
         LEFT JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1
         LEFT JOIN rent_payments rp ON tp.tenant_id = rp.tenant_id
         WHERE p.landlord_id = ?
         GROUP BY p.id
         ORDER BY p.property_name",
        [$user['id']]
    );
    
    // Monthly income (last 6 months) - landlord's properties only
    $monthlyIncome = $db->fetchAll(
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount) as total
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         WHERE p.landlord_id = ? 
         AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
         ORDER BY month DESC",
        [$user['id']]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 4px solid #666;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card:nth-child(1) { border-left-color: #3498db; }
        .stat-card:nth-child(2) { border-left-color: #27ae60; }
        .stat-card:nth-child(3) { border-left-color: #f39c12; }
        .stat-card:nth-child(4) { border-left-color: #e74c3c; }
        .stat-card:nth-child(5) { border-left-color: #9b59b6; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #f0f0f0;
            margin-bottom: 20px;
            width: 100%;
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 16px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 16px 20px;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Recent Payments */
        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-amount {
            text-align: right;
        }
        
        .payment-info strong {
            color: #333;
            font-size: 0.95rem;
        }
        
        .payment-info small {
            color: #7f8c8d;
            font-size: 0.85rem;
        }
        
        .payment-amount strong {
            color: #27ae60;
            font-size: 0.95rem;
        }
        
        .payment-amount small {
            color: #7f8c8d;
            font-size: 0.85rem;
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
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .card-body {
                padding: 12px 15px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 80px 10px 10px 10px;
            }
            
            .content-header {
                padding: 12px 15px;
            }
            
            .content-header h1 {
                font-size: 1.2rem;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .chart-container {
                height: 200px;
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
            <h1>System Reports</h1>
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
                <?php if ($user['role'] === 'admin'): ?>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="tenant_messages.php">Tenant Messages</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php" class="active">System Reports</a></li>
                <?php else: ?>
                    <li><a href="landlord_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="manage_tenants.php">Manage Tenants</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php" class="active">System Reports</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>📊 System Reports</h1>
                <p>Visual overview of your rental business performance</p>
            </div>
            
            <!-- Key Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_properties']; ?></div>
                    <div class="stat-label">Total Properties</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['occupied_properties']; ?></div>
                    <div class="stat-label">Occupied</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatCurrency($stats['total_collected']); ?></div>
                    <div class="stat-label">Total Collected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_properties'] > 0 ? round(($stats['occupied_properties'] / $stats['total_properties']) * 100, 1) : 0; ?>%</div>
                    <div class="stat-label">Occupancy Rate</div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Occupancy Chart -->
                <div class="card">
                    <div class="card-header">
                        <h3>🏠 Property Occupancy</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="occupancyChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Income Chart -->
                <div class="card">
                    <div class="card-header">
                        <h3>💰 Monthly Income</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="incomeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Payments (Simplified) -->
            <div class="card">
                <div class="card-header">
                    <h3>💰 Recent Payments (Last 5)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recentPayments)): ?>
                        <p style="text-align: center; color: #7f8c8d;">No payments recorded yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($recentPayments, 0, 5) as $payment): ?>
                            <div class="payment-item">
                                <div class="payment-info">
                                    <strong><?php echo htmlspecialchars($payment['tenant_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($payment['property_name']); ?></small>
                                </div>
                                <div class="payment-amount">
                                    <strong><?php echo formatCurrency($payment['amount']); ?></strong><br>
                                    <small><?php echo formatDate($payment['payment_date']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
        
        // Occupancy Chart
        const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
        new Chart(occupancyCtx, {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Vacant'],
                datasets: [{
                    data: [<?php echo $stats['occupied_properties']; ?>, <?php echo $stats['total_properties'] - $stats['occupied_properties']; ?>],
                    backgroundColor: ['#27ae60', '#e74c3c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Monthly Income Chart
        const incomeCtx = document.getElementById('incomeChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthlyIncome); ?>;
        const labels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
        }).reverse();
        const amounts = monthlyData.map(item => parseFloat(item.total)).reverse();
        
        new Chart(incomeCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Monthly Income (KES)',
                    data: amounts,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>

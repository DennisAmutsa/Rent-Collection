<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin') && !hasRole('landlord')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Get filter parameters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$property_filter = $_GET['property'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build filter conditions
$where_conditions = [];
$params = [];

if ($date_from) {
    $where_conditions[] = "rp.payment_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "rp.payment_date <= ?";
    $params[] = $date_to;
}

if ($property_filter) {
    $where_conditions[] = "p.id = ?";
    $params[] = $property_filter;
}

if ($status_filter) {
    $where_conditions[] = "rp.status = ?";
    $params[] = $status_filter;
}

$filter_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get properties for filter dropdown
if ($user['role'] === 'admin') {
    $properties = $db->fetchAll("SELECT id, property_name FROM properties ORDER BY property_name");
} else {
    $properties = $db->fetchAll("SELECT id, property_name FROM properties WHERE landlord_id = ? ORDER BY property_name", [$user['id']]);
}

// Get comprehensive system statistics with filters applied
$stats = [];

// Build base conditions for filtered statistics
$stats_where_conditions = [];
$stats_params = [];

if ($property_filter) {
    $stats_where_conditions[] = "p.id = ?";
    $stats_params[] = $property_filter;
}

if ($date_from) {
    $stats_where_conditions[] = "rp.payment_date >= ?";
    $stats_params[] = $date_from;
}

if ($date_to) {
    $stats_where_conditions[] = "rp.payment_date <= ?";
    $stats_params[] = $date_to;
}

if ($status_filter) {
    $stats_where_conditions[] = "rp.status = ?";
    $stats_params[] = $status_filter;
}

$stats_where_clause = $stats_where_conditions ? "WHERE " . implode(" AND ", $stats_where_conditions) : "";

if ($user['role'] === 'admin') {
    // Admin sees system-wide statistics with filters
    $stats['total_properties'] = $db->fetchOne("SELECT COUNT(DISTINCT p.id) as count FROM properties p")['count'];
    
    // Calculate total units across all properties (with property filter if applied)
    try {
        $units_where = $property_filter ? "WHERE p.id = ?" : "";
        $units_params = $property_filter ? [$property_filter] : [];
        $stats['total_units'] = $db->fetchOne("SELECT COALESCE(SUM(total_units), 0) as count FROM properties p $units_where", $units_params)['count'];
    } catch (Exception $e) {
        // Fallback if total_units column doesn't exist yet
        $stats['total_units'] = $db->fetchOne("SELECT COUNT(*) as count FROM properties p $units_where", $units_params)['count'];
    }
    
    // Calculate occupied units (tenants with active property assignments) with filters
    $occupied_where = $stats_where_conditions ? "WHERE " . implode(" AND ", $stats_where_conditions) : "";
    $stats['occupied_units'] = $db->fetchOne(
        "SELECT COUNT(DISTINCT tp.tenant_id) as count 
         FROM tenant_properties tp
         JOIN properties p ON tp.property_id = p.id
         LEFT JOIN rent_payments rp ON tp.tenant_id = rp.tenant_id
         $occupied_where
         AND tp.is_active = 1",
        $stats_params
    )['count'];
    
    // Calculate vacant units
    $stats['vacant_units'] = $stats['total_units'] - $stats['occupied_units'];
    
    // Calculate occupancy rate
    $stats['occupancy_rate'] = $stats['total_units'] > 0 ? round(($stats['occupied_units'] / $stats['total_units']) * 100, 1) : 0;
    
    $stats['total_tenants'] = $db->fetchOne(
        "SELECT COUNT(DISTINCT u.id) as count 
         FROM users u
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         LEFT JOIN rent_payments rp ON u.id = rp.tenant_id
         $stats_where_clause
         AND u.role = 'tenant'",
        $stats_params
    )['count'];
    
    $stats['total_payments'] = $db->fetchOne(
        "SELECT COUNT(*) as count 
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         $stats_where_clause",
        $stats_params
    )['count'];
    
    $stats['total_collected'] = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total 
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         $stats_where_clause",
        $stats_params
    )['total'];
} else {
    // Landlord sees only their own statistics with filters
    $landlord_conditions = ["p.landlord_id = ?"];
    $landlord_conditions = array_merge($landlord_conditions, $stats_where_conditions);
    $landlord_where = "WHERE " . implode(" AND ", $landlord_conditions);
    $landlord_params = array_merge([$user['id']], $stats_params);
    
    $stats['total_properties'] = $db->fetchOne("SELECT COUNT(DISTINCT p.id) as count FROM properties p $landlord_where", $landlord_params)['count'];
    
    // Calculate total units for landlord's properties
    try {
        $units_where = $property_filter ? "WHERE p.landlord_id = ? AND p.id = ?" : "WHERE p.landlord_id = ?";
        $units_params = $property_filter ? [$user['id'], $property_filter] : [$user['id']];
        $stats['total_units'] = $db->fetchOne("SELECT COALESCE(SUM(total_units), 0) as count FROM properties p $units_where", $units_params)['count'];
    } catch (Exception $e) {
        // Fallback if total_units column doesn't exist yet
        $stats['total_units'] = $db->fetchOne("SELECT COUNT(*) as count FROM properties p $units_where", $units_params)['count'];
    }
    
    // Calculate occupied units for landlord's properties with filters
    $stats['occupied_units'] = $db->fetchOne(
        "SELECT COUNT(DISTINCT tp.tenant_id) as count 
         FROM tenant_properties tp
         JOIN properties p ON tp.property_id = p.id
         LEFT JOIN rent_payments rp ON tp.tenant_id = rp.tenant_id
         $landlord_where
         AND tp.is_active = 1",
        $landlord_params
    )['count'];
    
    // Calculate vacant units
    $stats['vacant_units'] = $stats['total_units'] - $stats['occupied_units'];
    
    // Calculate occupancy rate
    $stats['occupancy_rate'] = $stats['total_units'] > 0 ? round(($stats['occupied_units'] / $stats['total_units']) * 100, 1) : 0;
    
    $stats['total_tenants'] = $db->fetchOne(
        "SELECT COUNT(DISTINCT u.id) as count 
         FROM users u
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         LEFT JOIN rent_payments rp ON u.id = rp.tenant_id
         $landlord_where
         AND u.role = 'tenant'",
        $landlord_params
    )['count'];
    
    $stats['total_payments'] = $db->fetchOne(
        "SELECT COUNT(*) as count 
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         $landlord_where",
        $landlord_params
    )['count'];
    
    $stats['total_collected'] = $db->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total 
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         $landlord_where",
        $landlord_params
    )['total'];
}

// Recent payments with filters
if ($user['role'] === 'admin') {
    // Admin sees all recent payments
    $admin_where = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    $admin_params = $params;
    
    $recentPayments = $db->fetchAll(
        "SELECT rp.*, u.full_name as tenant_name, p.property_name
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         $admin_where
         ORDER BY rp.payment_date DESC
         LIMIT 10",
        $admin_params
    );
} else {
    // Landlord sees only their payments
    $landlord_conditions = ["p.landlord_id = ?"];
    $landlord_conditions = array_merge($landlord_conditions, $where_conditions);
    $landlord_where = "WHERE " . implode(" AND ", $landlord_conditions);
    $landlord_params = array_merge([$user['id']], $params);
    
    $recentPayments = $db->fetchAll(
        "SELECT rp.*, u.full_name as tenant_name, p.property_name
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         $landlord_where
         ORDER BY rp.payment_date DESC
         LIMIT 10",
        $landlord_params
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
    
    // Monthly income (last 6 months) - all properties with filters
    $monthly_where_conditions = ["rp.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)"];
    $monthly_params = [];
    
    if ($date_from) {
        $monthly_where_conditions[] = "rp.payment_date >= ?";
        $monthly_params[] = $date_from;
    }
    
    if ($date_to) {
        $monthly_where_conditions[] = "rp.payment_date <= ?";
        $monthly_params[] = $date_to;
    }
    
    if ($property_filter) {
        $monthly_where_conditions[] = "p.id = ?";
        $monthly_params[] = $property_filter;
    }
    
    if ($status_filter) {
        $monthly_where_conditions[] = "rp.status = ?";
        $monthly_params[] = $status_filter;
    }
    
    $monthly_where_clause = "WHERE " . implode(" AND ", $monthly_where_conditions);
    
    $monthlyIncome = $db->fetchAll(
        "SELECT DATE_FORMAT(rp.payment_date, '%Y-%m') as month,
                SUM(rp.amount) as total
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         $monthly_where_clause
         GROUP BY DATE_FORMAT(rp.payment_date, '%Y-%m')
         ORDER BY month DESC",
        $monthly_params
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
    
    // Monthly income (last 6 months) - landlord's properties only with filters
    $landlord_monthly_conditions = [
        "p.landlord_id = ?",
        "rp.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)"
    ];
    $landlord_monthly_params = [$user['id']];
    
    if ($date_from) {
        $landlord_monthly_conditions[] = "rp.payment_date >= ?";
        $landlord_monthly_params[] = $date_from;
    }
    
    if ($date_to) {
        $landlord_monthly_conditions[] = "rp.payment_date <= ?";
        $landlord_monthly_params[] = $date_to;
    }
    
    if ($property_filter) {
        $landlord_monthly_conditions[] = "p.id = ?";
        $landlord_monthly_params[] = $property_filter;
    }
    
    if ($status_filter) {
        $landlord_monthly_conditions[] = "rp.status = ?";
        $landlord_monthly_params[] = $status_filter;
    }
    
    $landlord_monthly_where_clause = "WHERE " . implode(" AND ", $landlord_monthly_conditions);
    
    $monthlyIncome = $db->fetchAll(
        "SELECT DATE_FORMAT(rp.payment_date, '%Y-%m') as month,
                SUM(rp.amount) as total
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         $landlord_monthly_where_clause
         GROUP BY DATE_FORMAT(rp.payment_date, '%Y-%m')
         ORDER BY month DESC",
        $landlord_monthly_params
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
        
        /* Filter Form */
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fafafa;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #555;
            background: white;
        }
        
        .btn {
            background: #666;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        
        .btn:hover {
            background: #555;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #229954, #27ae60);
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
            
            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3>🔍 Filter Reports</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="filters">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_from">From Date</label>
                                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_to">To Date</label>
                                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="form-group">
                                <label for="property">Property</label>
                                <select id="property" name="property">
                                    <option value="">All Properties</option>
                                    <?php foreach ($properties as $property): ?>
                                        <option value="<?php echo $property['id']; ?>" <?php echo $property_filter == $property['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($property['property_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Payment Status</label>
                                <select id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="system_reports.php" class="btn">Clear Filters</a>
                                <button type="button" onclick="exportToPDF()" class="btn btn-success">📄 Export PDF</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Key Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_properties']; ?></div>
                    <div class="stat-label">Total Properties</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_units']; ?></div>
                    <div class="stat-label">Total Units</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #27ae60;"><?php echo $stats['occupied_units']; ?></div>
                    <div class="stat-label">Occupied Units</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #e74c3c;"><?php echo $stats['vacant_units']; ?></div>
                    <div class="stat-label">Vacant Units</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['occupancy_rate']; ?>%</div>
                    <div class="stat-label">Occupancy Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatCurrency($stats['total_collected']); ?></div>
                    <div class="stat-label">Total Collected</div>
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
            
            <!-- Filtered Results Summary -->
            <?php if ($date_from || $date_to || $property_filter || $status_filter): ?>
            <div class="card">
                <div class="card-header">
                    <h3>📊 Filtered Results Summary</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Applied Filters:</strong>
                        <?php if ($date_from): ?>From: <?php echo formatDate($date_from); ?><?php endif; ?>
                        <?php if ($date_to): ?> | To: <?php echo formatDate($date_to); ?><?php endif; ?>
                        <?php if ($property_filter): ?>
                            <?php 
                            $selected_property = array_filter($properties, function($p) use ($property_filter) { 
                                return $p['id'] == $property_filter; 
                            });
                            $selected_property = reset($selected_property);
                            ?>
                            | Property: <?php echo htmlspecialchars($selected_property['property_name']); ?>
                        <?php endif; ?>
                        <?php if ($status_filter): ?> | Status: <?php echo ucfirst($status_filter); ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Payments (Simplified) -->
            <div class="card">
                <div class="card-header">
                    <h3>💰 Recent Payments <?php echo ($date_from || $date_to || $property_filter || $status_filter) ? '(Filtered)' : '(Last 5)'; ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recentPayments)): ?>
                        <p style="text-align: center; color: #7f8c8d;">
                            No payments found<?php echo ($date_from || $date_to || $property_filter || $status_filter) ? ' matching the applied filters' : ' recorded yet'; ?>.
                        </p>
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
                        <div style="margin-top: 15px; text-align: center;">
                            <small style="color: #666;">
                                Showing <?php echo count($recentPayments); ?> payment(s)
                                <?php echo ($date_from || $date_to || $property_filter || $status_filter) ? 'matching your filters' : 'from recent activity'; ?>
                            </small>
                        </div>
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
        
        // PDF Export function
        function exportToPDF() {
            // Get current filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            const dateFrom = urlParams.get('date_from') || '';
            const dateTo = urlParams.get('date_to') || '';
            const property = urlParams.get('property') || '';
            const status = urlParams.get('status') || '';
            
            // Build export URL with current filters
            let exportUrl = 'export_reports_pdf.php?';
            if (dateFrom) exportUrl += 'date_from=' + encodeURIComponent(dateFrom) + '&';
            if (dateTo) exportUrl += 'date_to=' + encodeURIComponent(dateTo) + '&';
            if (property) exportUrl += 'property=' + encodeURIComponent(property) + '&';
            if (status) exportUrl += 'status=' + encodeURIComponent(status) + '&';
            
            // Remove trailing &
            exportUrl = exportUrl.replace(/&$/, '');
            
            // Open in new window to trigger download
            window.open(exportUrl, '_blank');
        }
        
        // Occupancy Chart
        const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
        new Chart(occupancyCtx, {
            type: 'doughnut',
            data: {
                labels: ['Occupied Units', 'Vacant Units'],
                datasets: [{
                    data: [<?php echo $stats['occupied_units']; ?>, <?php echo $stats['vacant_units']; ?>],
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

<?php
require_once 'config.php';

// Check if user is logged in and has permission
requireLogin();
if (!hasRole(['admin', 'landlord'])) {
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
         LIMIT 50",
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
         LIMIT 50",
        $landlord_params
    );
}

// Monthly income with filters (same logic as main page)
if ($user['role'] === 'admin') {
    // Admin monthly income with filters
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
    // Landlord monthly income with filters
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

// Generate filename with current date and filters
$filename = 'rent_reports_' . date('Y-m-d');
if ($date_from || $date_to || $property_filter || $status_filter) {
    $filename .= '_filtered';
}
$filename .= '.html';

// Set headers for download
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Collection Reports - <?php echo date('F j, Y'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #27ae60;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #27ae60;
            margin: 0;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #27ae60;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #27ae60;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .filters-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #27ae60;
        }
        .filters-info h3 {
            margin: 0 0 10px 0;
            color: #27ae60;
        }
        .filters-info p {
            margin: 5px 0;
            color: #555;
        }
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .payments-table th,
        .payments-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .payments-table th {
            background-color: #27ae60;
            color: white;
            font-weight: bold;
        }
        .payments-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-paid { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-overdue { background-color: #f8d7da; color: #721c24; }
        .status-rejected { background-color: #f5c6cb; color: #721c24; }
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏠 Rent Collection System Reports</h1>
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p>Report for: <?php echo ucfirst($user['role']); ?> - <?php echo htmlspecialchars($user['full_name']); ?></p>
    </div>

    <?php if ($date_from || $date_to || $property_filter || $status_filter): ?>
    <div class="filters-info">
        <h3>📊 Applied Filters</h3>
        <?php if ($date_from): ?><p><strong>From Date:</strong> <?php echo formatDate($date_from); ?></p><?php endif; ?>
        <?php if ($date_to): ?><p><strong>To Date:</strong> <?php echo formatDate($date_to); ?></p><?php endif; ?>
        <?php if ($property_filter): ?>
            <?php 
            $selected_property = array_filter($properties, function($p) use ($property_filter) { 
                return $p['id'] == $property_filter; 
            });
            $selected_property = reset($selected_property);
            ?>
            <p><strong>Property:</strong> <?php echo htmlspecialchars($selected_property['property_name']); ?></p>
        <?php endif; ?>
        <?php if ($status_filter): ?><p><strong>Status:</strong> <?php echo ucfirst($status_filter); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

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

    <h2>📈 Monthly Income Trend</h2>
    <?php if (empty($monthlyIncome)): ?>
        <div class="no-data">
            No monthly income data found<?php echo ($date_from || $date_to || $property_filter || $status_filter) ? ' matching the applied filters' : ''; ?>.
        </div>
    <?php else: ?>
        <table class="payments-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Total Income</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($monthlyIncome) as $income): ?>
                    <tr>
                        <td><?php echo date('F Y', strtotime($income['month'] . '-01')); ?></td>
                        <td><?php echo formatCurrency($income['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>💰 Payment Details</h2>
    <?php if (empty($recentPayments)): ?>
        <div class="no-data">
            No payments found<?php echo ($date_from || $date_to || $property_filter || $status_filter) ? ' matching the applied filters' : ''; ?>.
        </div>
    <?php else: ?>
        <table class="payments-table">
            <thead>
                <tr>
                    <th>Tenant Name</th>
                    <th>Property</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentPayments as $payment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                        <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                        <td><?php echo formatDate($payment['due_date']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top: 15px; color: #666; font-size: 0.9em;">
            Showing <?php echo count($recentPayments); ?> payment(s)
            <?php echo ($date_from || $date_to || $property_filter || $status_filter) ? 'matching your filters' : 'from recent activity'; ?>.
        </p>
    <?php endif; ?>

    <div class="footer">
        <p>This report was generated by the Rent Collection System</p>
        <p>For questions or support, please contact your system administrator</p>
    </div>
</body>
</html>

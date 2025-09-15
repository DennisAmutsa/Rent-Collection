<?php
require_once 'config.php';
requireLogin();
requireRole(['admin']);

$db = new Database();

// Function to automatically generate monthly rent payments for all active tenants
function generateMonthlyRent($db) {
    // Get all active tenant-property relationships
    $activeTenants = $db->query("
        SELECT 
            tp.tenant_id,
            tp.property_id,
            p.monthly_rent,
            u.full_name as tenant_name,
            p.property_name
        FROM tenant_properties tp
        JOIN properties p ON tp.property_id = p.id
        JOIN users u ON tp.tenant_id = u.id
        WHERE tp.is_active = 1
        AND u.role = 'tenant'
    ")->fetchAll();
    
    $generatedCount = 0;
    $currentMonth = date('Y-m-01'); // First day of current month
    
    foreach ($activeTenants as $tenant) {
        // Check if payment already exists for this month
        $existingPayment = $db->fetchOne("
            SELECT id FROM rent_payments 
            WHERE tenant_id = ? 
            AND property_id = ? 
            AND DATE_FORMAT(due_date, '%Y-%m') = ?
        ", [$tenant['tenant_id'], $tenant['property_id'], $currentMonth]);
        
        if (!$existingPayment) {
            // Generate new payment for this month
            $dueDate = $currentMonth; // Due on 1st of month
            
            $db->query("
                INSERT INTO rent_payments (tenant_id, property_id, amount, due_date, status, payment_method, notes, created_at) 
                VALUES (?, ?, ?, ?, 'pending', 'Not Specified', ?, NOW())
            ", [
                $tenant['tenant_id'],
                $tenant['property_id'],
                $tenant['monthly_rent'],
                $dueDate,
                'Monthly rent for ' . date('F Y')
            ]);
            
            $generatedCount++;
            echo "<p>✅ Generated rent for {$tenant['tenant_name']} - {$tenant['property_name']}: KES " . number_format($tenant['monthly_rent']) . "</p>";
        } else {
            echo "<p>⏭️ Payment already exists for {$tenant['tenant_name']} - {$tenant['property_name']} this month</p>";
        }
    }
    
    return $generatedCount;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_rent'])) {
    echo "<h2>Generating Monthly Rent Payments...</h2>";
    $count = generateMonthlyRent($db);
    echo "<h3>✅ Generated {$count} new rent payments for " . date('F Y') . "</h3>";
    echo "<p><a href='financial_analytics.php'>View in Financial Analytics</a></p>";
} else {
    // Show current status
    $currentMonth = date('Y-m');
    $existingPayments = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM rent_payments 
        WHERE DATE_FORMAT(due_date, '%Y-%m') = ?
    ", [$currentMonth])['count'];
    
    $activeTenants = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM tenant_properties tp
        JOIN users u ON tp.tenant_id = u.id
        WHERE tp.is_active = 1 AND u.role = 'tenant'
    ")['count'];
    
    echo "<h2>Monthly Rent Generation</h2>";
    echo "<p><strong>Current Month:</strong> " . date('F Y') . "</p>";
    echo "<p><strong>Active Tenants:</strong> {$activeTenants}</p>";
    echo "<p><strong>Existing Payments This Month:</strong> {$existingPayments}</p>";
    
    if ($existingPayments < $activeTenants) {
        echo "<p><strong>Missing Payments:</strong> " . ($activeTenants - $existingPayments) . "</p>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='generate_rent' class='btn btn-primary'>Generate Missing Rent Payments</button>";
        echo "</form>";
    } else {
        echo "<p>✅ All tenants have rent payments for this month</p>";
    }
    
    echo "<h3>Recent Payments:</h3>";
    $recentPayments = $db->query("
        SELECT 
            rp.*,
            u.full_name as tenant_name,
            p.property_name
        FROM rent_payments rp
        JOIN users u ON rp.tenant_id = u.id
        JOIN properties p ON rp.property_id = p.id
        ORDER BY rp.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Tenant</th><th>Property</th><th>Amount</th><th>Due Date</th><th>Status</th></tr>";
    foreach ($recentPayments as $payment) {
        $statusColor = $payment['status'] === 'paid' ? 'green' : ($payment['status'] === 'overdue' ? 'red' : 'orange');
        echo "<tr>";
        echo "<td>{$payment['tenant_name']}</td>";
        echo "<td>{$payment['property_name']}</td>";
        echo "<td>KES " . number_format($payment['amount']) . "</td>";
        echo "<td>{$payment['due_date']}</td>";
        echo "<td style='color: {$statusColor};'>{$payment['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>


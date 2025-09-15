<?php
require_once 'config.php';

// This script adds sample payments to demonstrate the charts
// You can delete this file after testing

$db = new Database();

// Get some properties and tenants to work with
$properties = $db->fetchAll("SELECT id FROM properties LIMIT 3");
$tenants = $db->fetchAll("SELECT id FROM users WHERE role = 'tenant' LIMIT 5");

if (empty($properties) || empty($tenants)) {
    echo "No properties or tenants found. Please add some first.";
    exit;
}

// Add sample payments for the last 8 months
$months = [];
for ($i = 7; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i months"));
}

$sampleAmounts = [45000, 52000, 38000, 61000, 48000, 55000, 42000, 58000]; // Varying amounts

foreach ($months as $index => $month) {
    $property = $properties[array_rand($properties)];
    $tenant = $tenants[array_rand($tenants)];
    $amount = $sampleAmounts[$index];
    
    // Create payment record
    $db->query("
        INSERT INTO rent_payments (tenant_id, property_id, amount, due_date, payment_date, status, payment_method, created_at) 
        VALUES (?, ?, ?, ?, ?, 'paid', 'bank_transfer', ?)
    ", [
        $tenant['id'],
        $property['id'],
        $amount,
        $month . '-01',
        $month . '-' . rand(1, 28), // Random day in the month
        $month . '-01'
    ]);
}

echo "Sample payments added successfully! You can now see the charts with multiple data points.";
echo "<br><a href='financial_analytics.php'>View Financial Analytics</a>";
echo "<br><br><strong>Note:</strong> These are sample payments for demonstration. You can delete them later if needed.";
?>
<?php
require_once 'config.php';
requireLogin();
requireRole(['admin']);

$db = new Database();

// Create some test payments with past due dates to demonstrate overdue system
$testPayments = [
    [
        'tenant_id' => 3, // Assuming tenant ID 3 exists
        'property_id' => 1, // Assuming property ID 1 exists
        'amount' => 25000,
        'due_date' => date('Y-m-d', strtotime('-5 days')), // 5 days ago
        'status' => 'pending',
        'payment_method' => 'Cash',
        'notes' => 'Test overdue payment - 5 days late'
    ],
    [
        'tenant_id' => 4, // Assuming tenant ID 4 exists
        'property_id' => 3, // Assuming property ID 3 exists
        'amount' => 30000,
        'due_date' => date('Y-m-d', strtotime('-10 days')), // 10 days ago
        'status' => 'pending',
        'payment_method' => 'Bank Transfer',
        'notes' => 'Test overdue payment - 10 days late'
    ],
    [
        'tenant_id' => 3,
        'property_id' => 1,
        'amount' => 20000,
        'due_date' => date('Y-m-d', strtotime('-2 days')), // 2 days ago
        'status' => 'pending',
        'payment_method' => 'Mobile Money',
        'notes' => 'Test overdue payment - 2 days late'
    ]
];

echo "<h2>Creating Test Overdue Payments...</h2>";

foreach ($testPayments as $payment) {
    try {
        $db->query("
            INSERT INTO rent_payments (tenant_id, property_id, amount, due_date, status, payment_method, notes, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ", [
            $payment['tenant_id'],
            $payment['property_id'],
            $payment['amount'],
            $payment['due_date'],
            $payment['status'],
            $payment['payment_method'],
            $payment['notes']
        ]);
        
        echo "<p>✅ Created payment: KES " . number_format($payment['amount']) . " due " . $payment['due_date'] . " (" . $payment['notes'] . ")</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error creating payment: " . $e->getMessage() . "</p>";
    }
}

echo "<h3>Now you can test the overdue system:</h3>";
echo "<ol>";
echo "<li>Go to <a href='financial_analytics.php'>Financial Analytics Dashboard</a></li>";
echo "<li>Click 'Update Overdue Status' to mark payments as overdue</li>";
echo "<li>Click 'Send Bulk Reminders' to send notifications to tenants</li>";
echo "<li>Check the Outstanding Rent table to see overdue payments</li>";
echo "</ol>";

echo "<p><strong>Note:</strong> These are test payments. You can delete them later from the database.</p>";
?>

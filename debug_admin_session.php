<?php
require_once 'config.php';

echo "<h2>Admin Session Debug</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ No user_id in session</p>";
} else {
    echo "<p style='color: green;'>✅ User ID in session: " . $_SESSION['user_id'] . "</p>";
}

// Get current user
$user = getCurrentUser();
if (!$user) {
    echo "<p style='color: red;'>❌ getCurrentUser() returned null</p>";
} else {
    echo "<p style='color: green;'>✅ User data retrieved:</p>";
    echo "<ul>";
    echo "<li>ID: " . $user['id'] . "</li>";
    echo "<li>Name: " . $user['full_name'] . "</li>";
    echo "<li>Email: " . $user['email'] . "</li>";
    echo "<li>Role: " . $user['role'] . "</li>";
    echo "</ul>";
}

// Check if user is admin
if ($user && $user['role'] === 'admin') {
    echo "<p style='color: green;'>✅ User has admin role</p>";
} else {
    echo "<p style='color: red;'>❌ User does not have admin role</p>";
}

// Test requireRole function
echo "<h3>Testing requireRole(['admin'])</h3>";
try {
    requireRole(['admin']);
    echo "<p style='color: green;'>✅ requireRole(['admin']) passed</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ requireRole(['admin']) failed: " . $e->getMessage() . "</p>";
}

echo "<br><a href='admin_payment_receipts.php'>Try Admin Payment Receipts</a>";
?>

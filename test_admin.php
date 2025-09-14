<?php
require_once 'config.php';

echo "<h2>Admin Access Test</h2>";

echo "<p>Is Logged In: " . (isLoggedIn() ? 'Yes' : 'No') . "</p>";

if (isLoggedIn()) {
    $user = getCurrentUser();
    echo "<p>User Role: " . ($user['role'] ?? 'NOT SET') . "</p>";
    echo "<p>Has Admin Role: " . (hasRole('admin') ? 'Yes' : 'No') . "</p>";
    
    if (hasRole('admin')) {
        echo "<h3 style='color: green;'>✅ You have admin access!</h3>";
        echo "<a href='admin_dashboard.php'>Go to Admin Dashboard</a>";
    } else {
        echo "<h3 style='color: red;'>❌ You don't have admin access</h3>";
        echo "<p>Your role is: " . ($user['role'] ?? 'NOT SET') . "</p>";
    }
} else {
    echo "<p>You are not logged in!</p>";
    echo "<a href='login.php'>Login</a>";
}
?>

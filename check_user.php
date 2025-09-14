<?php
require_once 'config.php';

echo "<h2>Current User Debug Information</h2>";
echo "<pre>";

echo "Session Data:\n";
print_r($_SESSION);

echo "\nIs Logged In: " . (isLoggedIn() ? 'Yes' : 'No') . "\n";

if (isLoggedIn()) {
    $user = getCurrentUser();
    echo "\nCurrent User Data:\n";
    print_r($user);
    
    echo "\nUser Role: '" . ($user['role'] ?? 'NOT SET') . "'\n";
    echo "Has Admin Role: " . (hasRole('admin') ? 'Yes' : 'No') . "\n";
    echo "Has Landlord Role: " . (hasRole('landlord') ? 'Yes' : 'No') . "\n";
    echo "Has Tenant Role: " . (hasRole('tenant') ? 'Yes' : 'No') . "\n";
    
    // Check database directly
    $db = new Database();
    $dbUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    echo "\nDirect Database Query Result:\n";
    print_r($dbUser);
}

echo "</pre>";

echo "<br><a href='manage_properties.php'>Go to Manage Properties</a>";
echo "<br><a href='admin_dashboard.php'>Go to Admin Dashboard</a>";
echo "<br><a href='logout.php'>Logout</a>";
?>

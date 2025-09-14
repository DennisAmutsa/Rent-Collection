<?php
require_once 'config.php';

echo "<h2>Session Debug Information</h2>";
echo "<pre>";

echo "Session Data:\n";
print_r($_SESSION);

echo "\nIs Logged In: " . (isLoggedIn() ? 'Yes' : 'No') . "\n";

if (isLoggedIn()) {
    echo "\nSession User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
    echo "Session User Role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
    
    // Check database directly
    $db = new Database();
    $dbUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    echo "\nDatabase User Data:\n";
    print_r($dbUser);
    
    if ($dbUser) {
        echo "\nDatabase Role: " . $dbUser['role'] . "\n";
        echo "Session Role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
        echo "Roles Match: " . (($dbUser['role'] === $_SESSION['user_role']) ? 'Yes' : 'No') . "\n";
        
        // Force update session
        $_SESSION['user_role'] = $dbUser['role'];
        $_SESSION['user_name'] = $dbUser['full_name'];
        
        echo "\nSession Updated!\n";
        echo "New Session Role: " . $_SESSION['user_role'] . "\n";
        
        echo "\nRole Checks:\n";
        echo "hasRole('admin'): " . (hasRole('admin') ? 'Yes' : 'No') . "\n";
        echo "hasRole('landlord'): " . (hasRole('landlord') ? 'Yes' : 'No') . "\n";
        echo "hasRole('tenant'): " . (hasRole('tenant') ? 'Yes' : 'No') . "\n";
    }
}

echo "</pre>";

echo "<br><a href='admin_dashboard.php'>Try Admin Dashboard</a>";
echo "<br><a href='manage_properties.php'>Try Manage Properties</a>";
echo "<br><a href='logout.php'>Logout</a>";
?>

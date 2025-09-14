<?php
require_once 'config.php';

echo "<h2>Debug User Information</h2>";
echo "<pre>";

echo "Session Data:\n";
print_r($_SESSION);

echo "\nIs Logged In: " . (isLoggedIn() ? 'Yes' : 'No') . "\n";

if (isLoggedIn()) {
    $user = getCurrentUser();
    echo "\nCurrent User Data:\n";
    print_r($user);
    
    echo "\nUser Role: " . ($user['role'] ?? 'NOT SET') . "\n";
    echo "Has Admin Role: " . (hasRole('admin') ? 'Yes' : 'No') . "\n";
    echo "Has Landlord Role: " . (hasRole('landlord') ? 'Yes' : 'No') . "\n";
    echo "Has Tenant Role: " . (hasRole('tenant') ? 'Yes' : 'No') . "\n";
}

echo "</pre>";
?>

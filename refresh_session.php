<?php
require_once 'config.php';

if (isLoggedIn()) {
    $db = new Database();
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE id = ? AND is_active = 1",
        [$_SESSION['user_id']]
    );
    
    if ($user) {
        // Update session with current user data
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        
        echo "<h2>Session Refreshed!</h2>";
        echo "<p>Your role has been updated to: <strong>" . $user['role'] . "</strong></p>";
        echo "<p>Your name: <strong>" . $user['full_name'] . "</strong></p>";
        
        echo "<br><a href='admin_dashboard.php'>Go to Admin Dashboard</a>";
        echo "<br><a href='manage_properties.php'>Go to Manage Properties</a>";
        echo "<br><a href='check_user.php'>Check User Again</a>";
    } else {
        echo "<p>User not found!</p>";
    }
} else {
    echo "<p>You are not logged in!</p>";
    echo "<a href='login.php'>Login</a>";
}
?>

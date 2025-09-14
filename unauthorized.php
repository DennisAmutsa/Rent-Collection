<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Access Denied</h1>
                <p>You don't have permission to access this page</p>
            </div>
            
            <div class="alert alert-error">
                <strong>Unauthorized Access</strong><br>
                You are not authorized to view this page. Please contact your administrator if you believe this is an error.
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="btn btn-primary">Back to Login</a>
                <?php if (isLoggedIn()): ?>
                    <a href="logout.php" class="btn" style="background: #e2e8f0; color: #4a5568; margin-left: 10px;">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

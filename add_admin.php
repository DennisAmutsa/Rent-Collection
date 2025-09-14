<?php
require_once 'config.php';

$message = '';
$error = '';

// Handle admin creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        try {
            $db = new Database();
            
            // Check if username or email already exists
            $existing = $db->fetchOne(
                "SELECT id FROM users WHERE username = ? OR email = ?",
                [$username, $email]
            );
            
            if ($existing) {
                $error = 'Username or email already exists';
            } else {
                // Create admin user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'admin')",
                    [$username, $email, $hashed_password, $full_name]
                );
                
                $message = "Admin user created successfully! You can now login.";
            }
        } catch (Exception $e) {
            $error = "Error creating admin: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Create Admin User</h1>
                <p>Set up your first administrator account</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!$message): ?>
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" name="create_admin" class="btn btn-primary btn-full">Create Admin User</button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn" style="background: #e2e8f0; color: #4a5568;">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

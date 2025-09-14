<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    switch ($user['role']) {
        case 'landlord':
            redirect('landlord_dashboard.php');
            break;
        case 'admin':
            redirect('admin_dashboard.php');
            break;
        case 'tenant':
            redirect('tenant_dashboard.php');
            break;
    }
}

$error = '';
$success = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = new Database();
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    redirect('admin_dashboard.php');
                    break;
                case 'landlord':
                    redirect('landlord_dashboard.php');
                    break;
                case 'tenant':
                    redirect('tenant_dashboard.php');
                    break;
                default:
                    redirect('tenant_dashboard.php');
                    break;
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-body {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .login-form {
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
        }
        
        .btn-full {
            width: 100%;
        }
        
        .login-footer {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #ecf0f1;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .login-footer p {
            margin-bottom: 8px;
        }
        
        .login-footer strong {
            color: #2c3e50;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fdf2f2;
            color: #e53e3e;
            border: 1px solid #feb2b2;
        }
        
        .alert-success {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        
        
        @media (max-width: 768px) {
            .login-body {
                padding: 5px;
                align-items: flex-start;
                padding-top: 20px;
            }
            
            .login-container {
                max-width: 100%;
            }
            
            .login-card {
                padding: 25px 20px;
                margin: 0;
                border-radius: 15px;
            }
            
            .login-header {
                margin-bottom: 25px;
            }
            
            .login-header h1 {
                font-size: 2rem;
                margin-bottom: 5px;
            }
            
            .login-header p {
                font-size: 0.9rem;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                font-size: 0.85rem;
                margin-bottom: 5px;
            }
            
            .form-group input {
                padding: 12px 15px;
                font-size: 16px; /* Prevents zoom on iOS */
                border-radius: 8px;
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 1rem;
            }
            
            .login-footer {
                padding-top: 20px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .login-body {
                padding: 0;
                padding-top: 10px;
            }
            
            .login-card {
                padding: 20px 15px;
                margin: 0 5px;
                border-radius: 10px;
            }
            
            .login-header {
                margin-bottom: 20px;
            }
            
            .login-header h1 {
                font-size: 1.8rem;
            }
            
            .login-header p {
                font-size: 0.8rem;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-group label {
                font-size: 0.8rem;
            }
            
            .form-group input {
                padding: 10px 12px;
                font-size: 16px;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .login-footer {
                padding-top: 15px;
                font-size: 0.75rem;
            }
        }
    </style>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '🙈';
            } else {
                field.type = 'password';
                button.textContent = '👁️';
            }
        }
    </script>
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           placeholder="Enter your username or email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" required
                               placeholder="Enter your password" style="padding-right: 50px;">
                        <button type="button" onclick="togglePassword('password')" 
                                style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); 
                                       background: none; border: none; cursor: pointer; font-size: 18px; 
                                       color: #7f8c8d; padding: 0;">
                            👁️
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p>Don't have an account? <a href="register.php" style="color: #3498db; text-decoration: none; font-weight: 600;">Create one here</a></p>
            </div>
        </div>
    </div>
</body>
</html>

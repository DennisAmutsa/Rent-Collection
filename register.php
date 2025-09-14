<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    switch ($user['role']) {
        case 'landlord':
            redirect('login.php');
            break;
        case 'admin':
            redirect('admin_dashboard.php');
            break;
        case 'tenant':
            redirect('tenant_dashboard.php');
            break;
    }
}

$message = '';
$error = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $message = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

// Handle user registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields';
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
                // Create user as tenant by default
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO users (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, 'tenant')",
                    [$username, $email, $hashed_password, $full_name, $phone]
                );
                
                // Redirect to login page with success message
                $_SESSION['registration_success'] = "Registration successful! You can now login with your credentials.";
                redirect('login.php');
            }
        } catch (Exception $e) {
            $error = "Error creating account: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .register-body {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin: 0 10px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .register-header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .register-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .register-form {
            margin-bottom: 30px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
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
        
        .register-footer {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #ecf0f1;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .register-footer a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-footer a:hover {
            text-decoration: underline;
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
            .register-body {
                padding: 5px;
                align-items: flex-start;
                padding-top: 20px;
            }
            
            .register-container {
                max-width: 100%;
            }
            
            .register-card {
                padding: 20px 15px;
                margin: 0;
                border-radius: 10px;
            }
            
            .register-header {
                margin-bottom: 25px;
            }
            
            .register-header h1 {
                font-size: 1.8rem;
                margin-bottom: 5px;
            }
            
            .register-header p {
                font-size: 0.9rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
                margin-bottom: 15px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-group label {
                font-size: 0.85rem;
                margin-bottom: 5px;
            }
            
            .form-group input {
                padding: 10px 12px;
                font-size: 16px; /* Prevents zoom on iOS */
                border-radius: 8px;
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 1rem;
                margin-top: 10px;
            }
            
            .register-footer {
                padding-top: 20px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .register-body {
                padding: 0;
                padding-top: 10px;
            }
            
            .register-card {
                padding: 15px 12px;
                margin: 0 5px;
                border-radius: 8px;
            }
            
            .register-header {
                margin-bottom: 20px;
            }
            
            .register-header h1 {
                font-size: 1.5rem;
            }
            
            .register-header p {
                font-size: 0.8rem;
            }
            
            .form-group {
                margin-bottom: 12px;
            }
            
            .form-group label {
                font-size: 0.8rem;
            }
            
            .form-group input {
                padding: 8px 10px;
                font-size: 16px;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .register-footer {
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
<body class="register-body">
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1>Create Account</h1>
                <p>Join our rental management system</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            
            <?php if (!$message): ?>
                <form method="POST" class="register-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo (!$message && isset($_POST['username'])) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   placeholder="Choose a username">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo (!$message && isset($_POST['email'])) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="Enter your email">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo (!$message && isset($_POST['full_name'])) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                               placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo (!$message && isset($_POST['phone'])) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                               placeholder="Enter your phone number (optional)">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <div style="position: relative;">
                                <input type="password" id="password" name="password" required
                                       placeholder="Min 6 characters" style="padding-right: 50px;">
                                <button type="button" onclick="togglePassword('password')" 
                                        style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); 
                                               background: none; border: none; cursor: pointer; font-size: 18px; 
                                               color: #7f8c8d; padding: 0;">
                                    👁️
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <div style="position: relative;">
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       placeholder="Confirm password" style="padding-right: 50px;">
                                <button type="button" onclick="togglePassword('confirm_password')" 
                                        style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); 
                                               background: none; border: none; cursor: pointer; font-size: 18px; 
                                               color: #7f8c8d; padding: 0;">
                                    👁️
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="register" class="btn btn-primary btn-full">Create Account</button>
                </form>
            <?php endif; ?>
            
            <div class="register-footer">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>
</body>
</html>

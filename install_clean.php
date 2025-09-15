<?php
// Rent Collection System - Clean Installation (No Sample Data)

// Database configuration for XAMPP
$host = 'localhost';
$username = 'root';
$password = '';
$database_name = 'rent_collection_system';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // Connect to MySQL server (without selecting database)
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database_name`");
        $pdo->exec("USE `$database_name`");
        
        // Create tables only (no sample data)
        $sql = "
        -- Users table with roles
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            role ENUM('tenant', 'admin', 'landlord') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        );

        -- Properties table
        CREATE TABLE IF NOT EXISTS properties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_name VARCHAR(100) NOT NULL,
            address TEXT NOT NULL,
            landlord_id INT NOT NULL,
            monthly_rent DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- Tenants table (linking users to properties)
        CREATE TABLE IF NOT EXISTS tenant_properties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            property_id INT NOT NULL,
            move_in_date DATE NOT NULL,
            move_out_date DATE NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        );

        -- Rent payments table
        CREATE TABLE IF NOT EXISTS rent_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            property_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_date DATE NOT NULL,
            due_date DATE NOT NULL,
            status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
            payment_method VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        );

        -- Notifications table
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            recipient_id INT NOT NULL,
            subject VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('email', 'system') NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
        );
        ";
        
        $pdo->exec($sql);
        
        $message = "✅ Installation completed successfully! Database and tables have been created. You can now add your own users and data.";
        
    } catch (PDOException $e) {
        $error = "❌ Installation failed: " . $e->getMessage();
    }
}

// Check if database already exists and has tables
$tables_exist = false;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$database_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) >= 5) {
        $tables_exist = true;
    }
} catch (PDOException $e) {
    // Database doesn't exist yet
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Installation - Rent Collection System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🚀 Clean Installation</h1>
                <p>Create tables without sample data</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="register.php" class="btn btn-primary">Register First User</a>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php elseif ($tables_exist): ?>
                <div class="alert alert-info">
                    <strong>✅ Tables Already Exist!</strong><br>
                    The database tables are already set up. You can start adding your own data.
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="register.php" class="btn btn-primary">Register First User</a>
                    <a href="install_clean.php?reinstall=1" class="btn" style="background: #e2e8f0; color: #4a5568; margin-left: 10px;">Reinstall</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>📋 Clean Installation</strong><br>
                    This will create only the database tables without any sample data.
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4>Tables that will be created:</h4>
                    <ul style="text-align: left; margin: 10px 0;">
                        <li><strong>users</strong> - For all user types (tenant, landlord, admin)</li>
                        <li><strong>properties</strong> - Property information</li>
                        <li><strong>tenant_properties</strong> - Tenant-property relationships</li>
                        <li><strong>rent_payments</strong> - Payment records</li>
                        <li><strong>notifications</strong> - System notifications</li>
                    </ul>
                    <p style="color: #e53e3e; font-weight: bold;">⚠️ No sample data will be added - you'll need to create your own users.</p>
                </div>
                
                <form method="POST">
                    <div style="text-align: center;">
                        <button type="submit" name="install" class="btn btn-primary btn-full">
                            🚀 Create Tables Only
                        </button>
                    </div>
                </form>
            <?php endif; ?>
            
            <?php if (isset($_GET['reinstall']) && $_GET['reinstall'] == '1'): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Reinstall Warning</strong><br>
                    This will recreate all tables. Any existing data will be lost!
                </div>
                <form method="POST">
                    <div style="text-align: center;">
                        <button type="submit" name="install" class="btn btn-primary btn-full">
                            🔄 Reinstall Tables
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

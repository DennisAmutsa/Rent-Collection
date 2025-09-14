<?php
// Rent Collection System - Automatic Installation Script

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
        
        // Create tables
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
        
        // Insert sample data
        $sampleData = "
        -- Insert default admin user (password: admin123)
        INSERT IGNORE INTO users (username, email, password, full_name, role) VALUES 
        ('admin', 'admin@rentsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

        -- Insert sample landlord
        INSERT IGNORE INTO users (username, email, password, full_name, phone, role) VALUES 
        ('landlord1', 'landlord@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Landlord', '1234567890', 'landlord');

        -- Insert sample tenant
        INSERT IGNORE INTO users (username, email, password, full_name, phone, role) VALUES 
        ('tenant1', 'tenant@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Tenant', '0987654321', 'tenant');

        -- Insert sample property
        INSERT IGNORE INTO properties (property_name, address, landlord_id, monthly_rent) VALUES 
        ('Apartment 101', '123 Main Street, City', 2, 1200.00);

        -- Link tenant to property
        INSERT IGNORE INTO tenant_properties (tenant_id, property_id, move_in_date) VALUES 
        (3, 1, '2024-01-01');
        ";
        
        $pdo->exec($sampleData);
        
        $message = "✅ Installation completed successfully! Database and tables have been created with sample data.";
        
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
    <title>Installation - Rent Collection System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🚀 System Installation</h1>
                <p>Rent Collection System Setup</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php elseif ($tables_exist): ?>
                <div class="alert alert-info">
                    <strong>✅ Database Already Exists!</strong><br>
                    The database and tables are already set up. You can proceed to use the system.
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                    <a href="install.php?reinstall=1" class="btn" style="background: #e2e8f0; color: #4a5568; margin-left: 10px;">Reinstall</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>📋 Installation Information</strong><br>
                    This will create the database and all necessary tables with sample data.
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4>What will be created:</h4>
                    <ul style="text-align: left; margin: 10px 0;">
                        <li>Database: <strong>rent_collection_system</strong></li>
                        <li>Tables: users, properties, tenant_properties, rent_payments, notifications</li>
                        <li>Sample users: admin, landlord1, tenant1</li>
                        <li>Sample property and relationships</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <div style="text-align: center;">
                        <button type="submit" name="install" class="btn btn-primary btn-full">
                            🚀 Install Database & Tables
                        </button>
                    </div>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: #e2e8f0; border-radius: 8px;">
                    <h4>Demo Credentials (after installation):</h4>
                    <p><strong>Admin:</strong> admin / admin123</p>
                    <p><strong>Landlord:</strong> landlord1 / admin123</p>
                    <p><strong>Tenant:</strong> tenant1 / admin123</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['reinstall']) && $_GET['reinstall'] == '1'): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Reinstall Warning</strong><br>
                    This will recreate all tables and data. Existing data will be lost!
                </div>
                <form method="POST">
                    <div style="text-align: center;">
                        <button type="submit" name="install" class="btn btn-primary btn-full">
                            🔄 Reinstall Database
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
require_once 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_database'])) {
    try {
        $db = new Database();
        
        // Add unit columns to properties table
        $db->query("ALTER TABLE properties 
                   ADD COLUMN IF NOT EXISTS property_type VARCHAR(50) DEFAULT 'Apartment',
                   ADD COLUMN IF NOT EXISTS block_wing VARCHAR(50),
                   ADD COLUMN IF NOT EXISTS floor_number VARCHAR(20),
                   ADD COLUMN IF NOT EXISTS door_number VARCHAR(20),
                   ADD COLUMN IF NOT EXISTS unit_number VARCHAR(20),
                   ADD COLUMN IF NOT EXISTS total_units INT DEFAULT 1,
                   ADD COLUMN IF NOT EXISTS available_units INT DEFAULT 1,
                   ADD COLUMN IF NOT EXISTS description TEXT,
                   ADD COLUMN IF NOT EXISTS image_path VARCHAR(255)");
        
        // Update existing properties to have default unit values
        $db->query("UPDATE properties 
                   SET total_units = 1, available_units = 1 
                   WHERE total_units IS NULL OR available_units IS NULL");
        
        $message = "Database updated successfully! Unit columns have been added to the properties table.";
        
    } catch (Exception $e) {
        $error = "Error updating database: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Database - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🔄 Database Update</h1>
                <p>Add unit columns to properties table</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="system_reports.php" class="btn btn-primary">View Reports</a>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!$message): ?>
                <div class="alert alert-info">
                    <strong>⚠️ Database Update Required</strong><br>
                    The properties table needs to be updated to include unit columns for accurate reporting.
                </div>
                
                <form method="POST">
                    <button type="submit" name="update_database" class="btn btn-primary btn-full">Update Database</button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="system_reports.php" class="btn">Skip Update</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
echo "<h1>System Check</h1>";
echo "<p>✅ PHP is working!</p>";
echo "<p>📁 Current directory: " . __DIR__ . "</p>";
echo "<p>🕐 Server time: " . date('Y-m-d H:i:s') . "</p>";

// Test database connection
try {
    require_once 'config.php';
    $db = new Database();
    echo "<p>✅ Database connection successful!</p>";
    
    // Test if tables exist
    $tables = $db->fetchAll("SHOW TABLES");
    echo "<p>✅ Tables found: " . count($tables) . "</p>";
    
    if (count($tables) > 0) {
        echo "<h3>Available Tables:</h3><ul>";
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            echo "<li>" . $tableName . "</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Quick Links:</h3>";
echo "<p><a href='index.php'>🏠 Homepage</a></p>";
echo "<p><a href='login.php'>🔐 Login</a></p>";
echo "<p><a href='add_admin.php'>👤 Create Admin</a></p>";
?>

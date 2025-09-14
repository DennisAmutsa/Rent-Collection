<?php
// Application configuration
session_start();

// Base URL configuration
define('BASE_URL', 'http://localhost:8000/');

// Email configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'noreply@rentsystem.com');
define('FROM_NAME', 'Rent Collection System');

// Application settings
define('APP_NAME', 'Rent Collection System');
define('APP_VERSION', '1.0.0');

// Security settings
define('PASSWORD_MIN_LENGTH', 6);
define('SESSION_TIMEOUT', 3600); // 1 hour

// File upload settings
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Include database configuration
require_once 'database.php';

// Helper functions
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function hasRole($role) {
    // First check session
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role) {
        return true;
    }
    
    // If session check fails, verify with database
    if (isLoggedIn()) {
        $db = new Database();
        $user = $db->fetchOne(
            "SELECT role FROM users WHERE id = ? AND is_active = 1",
            [$_SESSION['user_id']]
        );
        
        if ($user && $user['role'] === $role) {
            // Update session with correct role
            $_SESSION['user_role'] = $user['role'];
            return true;
        }
    }
    
    return false;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        redirect('unauthorized.php');
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function formatCurrency($amount) {
    return 'KES ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = new Database();
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE id = ? AND is_active = 1",
        [$_SESSION['user_id']]
    );
    
    // Update session with current user data to keep it in sync
    if ($user) {
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
    }
    
    return $user;
}

function refreshUserSession() {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        if ($user) {
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            return true;
        }
    }
    return false;
}
?>

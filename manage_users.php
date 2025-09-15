<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin') && !hasRole('landlord')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Handle user operations
$message = '';
$error = '';

// Create new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    $role = sanitizeInput($_POST['role']);
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $full_name)) {
        $error = 'Full name can only contain letters, spaces, hyphens, apostrophes, and periods';
    } elseif (!empty($phone) && !preg_match('/^[0-9\s\-\+\(\)]+$/', $phone)) {
        $error = 'Phone number can only contain numbers, spaces, hyphens, plus signs, and parentheses';
    } elseif (!in_array($role, ['admin', 'landlord', 'tenant'])) {
        $error = 'Invalid role selected';
    } else {
        try {
            // Check if username or email already exists
            $existing = $db->fetchOne(
                "SELECT id FROM users WHERE username = ? OR email = ?",
                [$username, $email]
            );
            
            if ($existing) {
                $error = 'Username or email already exists';
            } else {
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $db->query(
                    "INSERT INTO users (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)",
                    [$username, $email, $hashed_password, $full_name, $phone, $role]
                );
                
                $message = "User created successfully!";
            }
        } catch (Exception $e) {
            $error = "Error creating user: " . $e->getMessage();
        }
    }
}

// Update user role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = sanitizeInput($_POST['user_id']);
    $new_role = sanitizeInput($_POST['new_role']);
    
    if ($user_id && $new_role) {
        // Get the target user's current role
        $targetUser = $db->fetchOne("SELECT role FROM users WHERE id = ?", [$user_id]);
        
        // Check restrictions
        $canChange = true;
        $restrictionMessage = "";
        
        // Admin cannot change their own role
        if ($user_id == $user['id']) {
            $canChange = false;
            $restrictionMessage = "You cannot change your own role.";
        }
        // Admin cannot change landlord roles
        elseif ($targetUser && $targetUser['role'] === 'landlord') {
            $canChange = false;
            $restrictionMessage = "Admins cannot change landlord roles.";
        }
        // Admin cannot change other admin roles
        elseif ($targetUser && $targetUser['role'] === 'admin') {
            $canChange = false;
            $restrictionMessage = "Admins cannot change other admin roles.";
        }
        
        if ($canChange) {
            try {
                $db->query(
                    "UPDATE users SET role = ? WHERE id = ?",
                    [$new_role, $user_id]
                );
                $message = "User role updated successfully!";
            } catch (Exception $e) {
                $error = "Error updating user role: " . $e->getMessage();
            }
        } else {
            $error = $restrictionMessage;
        }
    } else {
        $error = "Please select a user and role";
    }
}

// Deactivate user
if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate'])) {
    $targetUserId = $_GET['deactivate'];
    
    // Get the target user's role
    $targetUser = $db->fetchOne("SELECT role FROM users WHERE id = ?", [$targetUserId]);
    
    // Check restrictions
    $canDeactivate = true;
    $restrictionMessage = "";
    
    // Admin cannot deactivate themselves
    if ($targetUserId == $user['id']) {
        $canDeactivate = false;
        $restrictionMessage = "You cannot deactivate your own account.";
    }
    // Admin cannot deactivate landlords
    elseif ($targetUser && $targetUser['role'] === 'landlord') {
        $canDeactivate = false;
        $restrictionMessage = "Admins cannot deactivate landlord accounts.";
    }
    // Admin cannot deactivate other admins
    elseif ($targetUser && $targetUser['role'] === 'admin') {
        $canDeactivate = false;
        $restrictionMessage = "Admins cannot deactivate other admin accounts.";
    }
    
    if ($canDeactivate) {
        try {
            $db->query(
                "UPDATE users SET is_active = 0 WHERE id = ?",
                [$targetUserId]
            );
            $message = "User deactivated successfully!";
        } catch (Exception $e) {
            $error = "Error deactivating user: " . $e->getMessage();
        }
    } else {
        $error = $restrictionMessage;
    }
}

// Activate user
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    try {
        $db->query(
            "UPDATE users SET is_active = 1 WHERE id = ?",
            [$_GET['activate']]
        );
        $message = "User activated successfully!";
    } catch (Exception $e) {
        $error = "Error activating user: " . $e->getMessage();
    }
}

// Get all users
$allUsers = $db->fetchAll(
    "SELECT * FROM users ORDER BY created_at DESC"
);

// Count users by role
$userStats = $db->fetchAll(
    "SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo APP_NAME; ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }
        
        /* Mobile Header */
        .mobile-header {
            display: none;
            background: #333;
            color: white;
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .mobile-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .mobile-header h1 {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .hamburger {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.3s ease;
        }
        
        .hamburger:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .hamburger.active {
            background: rgba(255,255,255,0.2);
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #333;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            background: #222;
            border-bottom: 1px solid #444;
        }
        
        .sidebar-header h2 {
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }
        
        .user-role {
            background: #555;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu a {
            color: #ccc;
            padding: 15px 20px;
            display: block;
            text-decoration: none;
            border-bottom: 1px solid #444;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #444;
            color: white;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            width: calc(100% - 250px);
            overflow-x: hidden;
        }
        
        .content-header {
            background: linear-gradient(135deg, #555, #666);
            color: white;
            padding: 20px 24px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .content-header h1 {
            margin: 0 0 8px 0;
            font-size: 1.6rem;
            font-weight: 700;
        }
        
        .content-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 4px solid #666;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card:nth-child(1) { border-left-color: #3498db; }
        .stat-card:nth-child(2) { border-left-color: #e74c3c; }
        .stat-card:nth-child(3) { border-left-color: #f39c12; }
        .stat-card:nth-child(4) { border-left-color: #27ae60; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #f0f0f0;
            margin-bottom: 20px;
            width: 100%;
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 16px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 16px 20px;
        }
        
        /* Forms */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #555;
            background: white;
        }
        
        .form-group select option:disabled {
            color: #999;
            background: #f5f5f5;
        }
        
        /* Buttons */
        .btn {
            background: #666;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        
        .btn:hover {
            background: #555;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #229954, #27ae60);
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .status-tenant {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-admin {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .status-landlord {
            background: #e8f5e8;
            color: #388e3c;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
            width: 100%;
            max-width: 100%;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 600px;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .mobile-header {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 80px 15px 15px 15px;
                width: 100%;
            }
            
            .content-header {
                margin-top: 0;
                padding: 15px 20px;
            }
            
            .content-header h1 {
                font-size: 1.3rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .card-body {
                padding: 12px 15px;
            }
            
            .table-container {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 8px 6px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 80px 10px 10px 10px;
                width: 100%;
            }
            
            .content-header {
                padding: 12px 15px;
            }
            
            .content-header h1 {
                font-size: 1.2rem;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .table-container {
                font-size: 0.75rem;
            }
            
            th, td {
                padding: 6px 4px;
            }
            
            table {
                min-width: 500px;
            }
        }
        
        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        @media (max-width: 768px) {
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <h1>Manage Users</h1>
            <button class="hamburger" onclick="toggleSidebar()">☰</button>
        </div>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
            </div>
            <ul class="sidebar-menu">
                <?php if ($user['role'] === 'admin'): ?>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="tenant_messages.php">Tenant Messages</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php" class="active">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php else: ?>
                    <li><a href="landlord_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="manage_tenants.php">Manage Tenants</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php" class="active">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>👥 Manage Users</h1>
                <p>Control user roles and account status across the system</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- User Statistics -->
            <div class="stats-grid">
                <?php foreach ($userStats as $stat): ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stat['count']; ?></div>
                        <div class="stat-label"><?php echo ucfirst($stat['role']); ?>s</div>
                    </div>
                <?php endforeach; ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($allUsers); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            
            <!-- Create New User -->
            <div class="card">
                <div class="card-header">
                    <h3>➕ Create New User</h3>
                </div>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_username">Username *</label>
                            <input type="text" id="create_username" name="username" required
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   placeholder="Choose a username">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_email">Email *</label>
                            <input type="email" id="create_email" name="email" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="Enter email address">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_full_name">Full Name *</label>
                            <input type="text" id="create_full_name" name="full_name" required
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                   placeholder="Enter full name">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_phone">Phone Number</label>
                            <input type="tel" id="create_phone" name="phone"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                   placeholder="Enter phone number (optional)">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_password">Password *</label>
                            <input type="password" id="create_password" name="password" required
                                   placeholder="Min 6 characters">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_confirm_password">Confirm Password *</label>
                            <input type="password" id="create_confirm_password" name="confirm_password" required
                                   placeholder="Confirm password">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_role">Role *</label>
                            <select id="create_role" name="role" required>
                                <option value="">Select role</option>
                                <option value="tenant" <?php echo (isset($_POST['role']) && $_POST['role'] === 'tenant') ? 'selected' : ''; ?>>Tenant</option>
                                <option value="landlord" <?php echo (isset($_POST['role']) && $_POST['role'] === 'landlord') ? 'selected' : ''; ?>>Landlord</option>
                                <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Change User Role -->
            <div class="card">
                <div class="card-header">
                    <h3>🔄 Change User Role</h3>
                </div>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="user_id">Select User *</label>
                            <select id="user_id" name="user_id" required>
                                <option value="">Choose a user</option>
                                <?php foreach ($allUsers as $systemUser): ?>
                                    <?php 
                                    $canChange = true;
                                    $restriction = "";
                                    
                                    // Check if this user can have their role changed
                                    if ($systemUser['id'] == $user['id']) {
                                        $canChange = false;
                                        $restriction = " (Cannot change your own role)";
                                    } elseif ($systemUser['role'] === 'landlord') {
                                        $canChange = false;
                                        $restriction = " (Cannot change landlord roles)";
                                    } elseif ($systemUser['role'] === 'admin') {
                                        $canChange = false;
                                        $restriction = " (Cannot change admin roles)";
                                    }
                                    ?>
                                    <option value="<?php echo $systemUser['id']; ?>" <?php echo !$canChange ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($systemUser['full_name'] . ' (' . $systemUser['email'] . ') - ' . ucfirst($systemUser['role']) . $restriction); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new_role">New Role *</label>
                            <select id="new_role" name="new_role" required>
                                <option value="">Select new role</option>
                                <option value="tenant">Tenant</option>
                                <option value="admin">Admin</option>
                                <option value="landlord">Landlord</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="update_role" class="btn btn-primary">Update Role</button>
                </form>
            </div>
            
            <!-- All Users List -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 All Users (<?php echo count($allUsers); ?>)</h3>
                </div>
                <?php if (empty($allUsers)): ?>
                    <p>No users found in the system.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $systemUser): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($systemUser['full_name']); ?></strong>
                                            <?php if ($systemUser['id'] == $user['id']): ?>
                                                <small style="color: #3498db;">(You)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($systemUser['email']); ?></td>
                                        <td><?php echo htmlspecialchars($systemUser['phone'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $systemUser['role']; ?>">
                                                <?php echo ucfirst($systemUser['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($systemUser['is_active']): ?>
                                                <span class="status-badge status-paid">Active</span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($systemUser['created_at']); ?></td>
                                        <td>
                                            <?php if ($systemUser['id'] != $user['id']): ?>
                                                <?php 
                                                $canDeactivate = true;
                                                $restrictionReason = "";
                                                
                                                // Check if this user can be deactivated
                                                if ($systemUser['role'] === 'landlord') {
                                                    $canDeactivate = false;
                                                    $restrictionReason = "Cannot deactivate landlords";
                                                } elseif ($systemUser['role'] === 'admin') {
                                                    $canDeactivate = false;
                                                    $restrictionReason = "Cannot deactivate admins";
                                                }
                                                ?>
                                                
                                                <?php if ($systemUser['is_active']): ?>
                                                    <?php if ($canDeactivate): ?>
                                                        <a href="?deactivate=<?php echo $systemUser['id']; ?>" 
                                                           onclick="return confirm('Are you sure you want to deactivate this user?')"
                                                           class="btn" style="background: #e74c3c; color: white; padding: 5px 10px; font-size: 12px;">Deactivate</a>
                                                    <?php else: ?>
                                                        <small style="color: #e74c3c; font-size: 11px;"><?php echo $restrictionReason; ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="?activate=<?php echo $systemUser['id']; ?>" 
                                                       class="btn" style="background: #27ae60; color: white; padding: 5px 10px; font-size: 12px;">Activate</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <small style="color: #7f8c8d;">Current User</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Mobile sidebar functions
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const hamburger = document.querySelector('.hamburger');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
            hamburger.classList.toggle('active');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const hamburger = document.querySelector('.hamburger');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            hamburger.classList.remove('active');
        }
        
        // Close sidebar when clicking on menu links (mobile)
        document.addEventListener('DOMContentLoaded', function() {
            const menuLinks = document.querySelectorAll('.sidebar-menu a');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });

            // Real-time validation for user creation form
            // Full name validation (letters only)
            const fullNameField = document.getElementById('create_full_name');
            if (fullNameField) {
                fullNameField.addEventListener('input', function(e) {
                    const value = e.target.value;
                    const namePattern = /^[a-zA-Z\s\-\'\.]*$/;
                    
                    if (value && !namePattern.test(value)) {
                        e.target.style.borderColor = '#e53e3e';
                        e.target.style.backgroundColor = '#fdf2f2';
                        showFieldError('create_full_name', 'Full name can only contain letters, spaces, hyphens, apostrophes, and periods');
                    } else {
                        e.target.style.borderColor = '#e2e8f0';
                        e.target.style.backgroundColor = 'white';
                        hideFieldError('create_full_name');
                    }
                });
            }

            // Phone number validation (numbers only)
            const phoneField = document.getElementById('create_phone');
            if (phoneField) {
                phoneField.addEventListener('input', function(e) {
                    const value = e.target.value;
                    const phonePattern = /^[0-9\s\-\+\(\)]*$/;
                    
                    if (value && !phonePattern.test(value)) {
                        e.target.style.borderColor = '#e53e3e';
                        e.target.style.backgroundColor = '#fdf2f2';
                        showFieldError('create_phone', 'Phone number can only contain numbers, spaces, hyphens, plus signs, and parentheses');
                    } else {
                        e.target.style.borderColor = '#e2e8f0';
                        e.target.style.backgroundColor = 'white';
                        hideFieldError('create_phone');
                    }
                });
            }

            // Password confirmation validation
            const passwordField = document.getElementById('create_password');
            const confirmPasswordField = document.getElementById('create_confirm_password');
            
            function validatePasswordMatch() {
                if (passwordField.value && confirmPasswordField.value) {
                    if (passwordField.value !== confirmPasswordField.value) {
                        confirmPasswordField.style.borderColor = '#e53e3e';
                        confirmPasswordField.style.backgroundColor = '#fdf2f2';
                        showFieldError('create_confirm_password', 'Passwords do not match');
                    } else {
                        confirmPasswordField.style.borderColor = '#e2e8f0';
                        confirmPasswordField.style.backgroundColor = 'white';
                        hideFieldError('create_confirm_password');
                    }
                }
            }
            
            if (passwordField && confirmPasswordField) {
                passwordField.addEventListener('input', validatePasswordMatch);
                confirmPasswordField.addEventListener('input', validatePasswordMatch);
            }

            // Form submission validation
            const createUserForm = document.querySelector('form[method="POST"]');
            if (createUserForm) {
                createUserForm.addEventListener('submit', function(e) {
                    const fullName = document.getElementById('create_full_name').value;
                    const phone = document.getElementById('create_phone').value;
                    const password = document.getElementById('create_password').value;
                    const confirmPassword = document.getElementById('create_confirm_password').value;
                    const namePattern = /^[a-zA-Z\s\-\'\.]+$/;
                    const phonePattern = /^[0-9\s\-\+\(\)]+$/;
                    
                    let hasError = false;
                    
                    if (fullName && !namePattern.test(fullName)) {
                        showFieldError('create_full_name', 'Full name can only contain letters, spaces, hyphens, apostrophes, and periods');
                        hasError = true;
                    }
                    
                    if (phone && !phonePattern.test(phone)) {
                        showFieldError('create_phone', 'Phone number can only contain numbers, spaces, hyphens, plus signs, and parentheses');
                        hasError = true;
                    }
                    
                    if (password && confirmPassword && password !== confirmPassword) {
                        showFieldError('create_confirm_password', 'Passwords do not match');
                        hasError = true;
                    }
                    
                    if (hasError) {
                        e.preventDefault();
                    }
                });
            }
        });

        function showFieldError(fieldId, message) {
            let errorDiv = document.getElementById(fieldId + '_error');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = fieldId + '_error';
                errorDiv.className = 'field-error';
                errorDiv.style.color = '#e53e3e';
                errorDiv.style.fontSize = '0.8rem';
                errorDiv.style.marginTop = '5px';
                document.getElementById(fieldId).parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = message;
        }

        function hideFieldError(fieldId) {
            const errorDiv = document.getElementById(fieldId + '_error');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
    </script>
</body>
</html>

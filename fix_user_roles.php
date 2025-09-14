<?php
require_once 'config.php';

$message = '';
$error = '';

// Handle role updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];
    
    try {
        $db = new Database();
        $db->query(
            "UPDATE users SET role = ? WHERE id = ?",
            [$new_role, $user_id]
        );
        $message = "User role updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating role: " . $e->getMessage();
    }
}

// Get all users
$db = new Database();
$users = $db->fetchAll("SELECT id, username, email, full_name, role FROM users ORDER BY id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix User Roles - <?php echo APP_NAME; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .role-admin {
            background: #dc3545;
            color: white;
        }
        .role-landlord {
            background: #28a745;
            color: white;
        }
        .role-tenant {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Fix User Roles</h1>
        <p>This page helps you manage user roles in the system.</p>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div style="margin-bottom: 20px;">
            <a href="add_admin.php" class="btn">Create New Admin</a>
            <a href="debug_user.php" class="btn">Debug Current User</a>
            <a href="login.php" class="btn">Back to Login</a>
        </div>
        
        <h3>Current Users</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Full Name</th>
                    <th>Current Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="role" onchange="this.form.submit()" style="padding: 4px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="tenant" <?php echo $user['role'] === 'tenant' ? 'selected' : ''; ?>>Tenant</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="landlord" <?php echo $user['role'] === 'landlord' ? 'selected' : ''; ?>>Landlord</option>
                                </select>
                                <input type="hidden" name="update_role" value="1">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; padding: 20px; background: #e9ecef; border-radius: 6px;">
            <h4>Quick Fix Instructions:</h4>
            <ol>
                <li><strong>If you don't have an admin user:</strong> Click "Create New Admin" to create one</li>
                <li><strong>If you have a user but wrong role:</strong> Use the dropdown above to change their role to "Admin"</li>
                <li><strong>If you're logged in but getting unauthorized:</strong> Click "Debug Current User" to see your current role</li>
                <li><strong>After fixing roles:</strong> Logout and login again to refresh your session</li>
            </ol>
        </div>
    </div>
</body>
</html>

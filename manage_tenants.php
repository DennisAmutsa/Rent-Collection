<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin') && !hasRole('landlord')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Handle tenant operations
$message = '';
$error = '';

// Assign tenant to property
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_tenant'])) {
    $tenant_id = sanitizeInput($_POST['tenant_id']);
    $property_id = sanitizeInput($_POST['property_id']);
    $move_in_date = sanitizeInput($_POST['move_in_date']);
    
    if ($tenant_id && $property_id && $move_in_date) {
        try {
            // Check if tenant is already assigned to a property
            $existing = $db->fetchOne(
                "SELECT id FROM tenant_properties WHERE tenant_id = ? AND is_active = 1",
                [$tenant_id]
            );
            
            if ($existing) {
                $error = "This tenant is already assigned to a property";
            } else {
                $db->query(
                    "INSERT INTO tenant_properties (tenant_id, property_id, move_in_date) VALUES (?, ?, ?)",
                    [$tenant_id, $property_id, $move_in_date]
                );
                $message = "Tenant assigned to property successfully!";
            }
        } catch (Exception $e) {
            $error = "Error assigning tenant: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all fields";
    }
}

// Remove tenant from property
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    try {
        $db->query(
            "UPDATE tenant_properties SET is_active = 0, move_out_date = CURDATE() WHERE id = ?",
            [$_GET['remove']]
        );
        $message = "Tenant removed from property successfully!";
    } catch (Exception $e) {
        $error = "Error removing tenant: " . $e->getMessage();
    }
}

// Get all tenants
$allTenants = $db->fetchAll("SELECT * FROM users WHERE role = 'tenant' AND is_active = 1 ORDER BY full_name");

// Get landlord's properties
$properties = $db->fetchAll(
    "SELECT * FROM properties WHERE landlord_id = ? ORDER BY property_name",
    [$user['id']]
);

// Get current tenant assignments
$tenantAssignments = $db->fetchAll(
    "SELECT tp.*, u.full_name as tenant_name, u.email as tenant_email, p.property_name 
     FROM tenant_properties tp 
     JOIN users u ON tp.tenant_id = u.id 
     JOIN properties p ON tp.property_id = p.id 
     WHERE p.landlord_id = ? AND tp.is_active = 1
     ORDER BY p.property_name, u.full_name",
    [$user['id']]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tenants - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Mobile-first responsive design */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Mobile Header */
        .mobile-header {
            display: none;
            background: #ffffff;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .hamburger {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
            padding: 0.5rem;
        }

        .mobile-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .mobile-user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 999;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .user-role {
            background: #f1f5f9;
            color: #475569;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .sidebar-menu a:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .sidebar-menu a.active {
            background: #3b82f6;
            color: #ffffff;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            width: 100%;
            max-width: 100%;
        }

        .content-header {
            margin-bottom: 2rem;
        }

        .content-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .content-header p {
            color: #64748b;
            font-size: 1.125rem;
        }

        /* Cards */
        .card {
            background: #ffffff;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem 1.5rem 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .card-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .card-content {
            padding: 0 1.5rem 1.5rem;
        }

        /* Forms */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #3b82f6;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-danger {
            background: #ef4444;
            color: #ffffff;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            padding: 0 1.5rem 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        td {
            color: #64748b;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Overlay */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        .overlay.active {
            display: block;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 5rem 1rem 2rem;
            }

            .content-header h1 {
                font-size: 1.5rem;
            }

            .content-header p {
                font-size: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .card-header {
                padding: 1rem 1rem 0;
            }

            .card-content {
                padding: 0 1rem 1rem;
            }

            .table-container {
                padding: 0 1rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 4.5rem 0.75rem 1.5rem;
            }

            .content-header h1 {
                font-size: 1.25rem;
            }

            .content-header p {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <h1>Manage Tenants</h1>
        <div class="mobile-user-info">
            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role"><?php echo ucfirst($user['role']); ?></div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo $user['role'] === 'admin' ? 'admin_dashboard.php' : 'landlord_dashboard.php'; ?>">Dashboard</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="manage_tenants.php" class="active">Manage Tenants</a></li>
                <li><a href="manage_payments.php">Manage Payments</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="system_reports.php">System Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>Manage Tenants</h1>
                <p>Assign tenants to properties and manage tenant relationships</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Assign Tenant Form -->
            <div class="card">
                <div class="card-header">
                    <h3>Assign Tenant to Property</h3>
                </div>
                <div class="card-content">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="tenant_id">Select Tenant *</label>
                                <select id="tenant_id" name="tenant_id" required>
                                    <option value="">Choose a tenant</option>
                                    <?php foreach ($allTenants as $tenant): ?>
                                        <option value="<?php echo $tenant['id']; ?>">
                                            <?php echo htmlspecialchars($tenant['full_name'] . ' (' . $tenant['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="property_id">Select Property *</label>
                                <select id="property_id" name="property_id" required>
                                    <option value="">Choose a property</option>
                                    <?php foreach ($properties as $property): ?>
                                        <option value="<?php echo $property['id']; ?>">
                                            <?php echo htmlspecialchars($property['property_name'] . ' - ' . formatCurrency($property['monthly_rent'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="move_in_date">Move-in Date *</label>
                            <input type="date" id="move_in_date" name="move_in_date" required>
                        </div>
                        <button type="submit" name="assign_tenant" class="btn btn-primary">Assign Tenant</button>
                    </form>
                </div>
            </div>
            
            <!-- Current Tenant Assignments -->
            <div class="card">
                <div class="card-header">
                    <h3>Current Tenant Assignments (<?php echo count($tenantAssignments); ?>)</h3>
                </div>
                <div class="card-content">
                    <?php if (empty($tenantAssignments)): ?>
                        <p>No tenants assigned to properties yet.</p>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Move-in Date</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tenantAssignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($assignment['tenant_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($assignment['property_name']); ?></td>
                                            <td><?php echo formatDate($assignment['move_in_date']); ?></td>
                                            <td><?php echo htmlspecialchars($assignment['tenant_email']); ?></td>
                                            <td>
                                                <a href="?remove=<?php echo $assignment['id']; ?>" 
                                                   onclick="return confirm('Are you sure you want to remove this tenant from the property?')"
                                                   class="btn btn-danger">Remove</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- All Tenants List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Tenants (<?php echo count($allTenants); ?>)</h3>
                </div>
                <div class="card-content">
                    <?php if (empty($allTenants)): ?>
                        <p>No tenants registered in the system yet.</p>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Registered</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allTenants as $tenant): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($tenant['full_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                                            <td><?php echo htmlspecialchars($tenant['phone'] ?: 'N/A'); ?></td>
                                            <td><?php echo formatDate($tenant['created_at']); ?></td>
                                            <td>
                                                <?php 
                                                $assigned = $db->fetchOne(
                                                    "SELECT id FROM tenant_properties WHERE tenant_id = ? AND is_active = 1",
                                                    [$tenant['id']]
                                                );
                                                if ($assigned) {
                                                    echo '<span class="status-badge status-paid">Assigned</span>';
                                                } else {
                                                    echo '<span class="status-badge status-pending">Available</span>';
                                                }
                                                ?>
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
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }

        // Close sidebar when clicking on a menu item (mobile)
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        // Close sidebar on window resize if screen becomes larger
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>

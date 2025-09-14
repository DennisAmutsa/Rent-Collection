<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin') && !hasRole('landlord')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Handle property operations
$message = '';
$error = '';

// Add new property
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_property'])) {
    $property_name = sanitizeInput($_POST['property_name']);
    $address = sanitizeInput($_POST['address']);
    $monthly_rent = floatval($_POST['monthly_rent']);
    $property_type = sanitizeInput($_POST['property_type']);
    $block_wing = sanitizeInput($_POST['block_wing']);
    $floor_number = sanitizeInput($_POST['floor_number']);
    $door_number = sanitizeInput($_POST['door_number']);
    $unit_number = sanitizeInput($_POST['unit_number']);
    $total_units = intval($_POST['total_units']);
    $available_units = intval($_POST['available_units']);
    $description = sanitizeInput($_POST['description']);
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['property_image']) && $_FILES['property_image']['error'] == 0) {
        $upload_dir = 'uploads/properties/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['property_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['property_image']['tmp_name'], $upload_path)) {
                $image_path = $upload_path;
            }
        }
    }
    
    if ($property_name && $address && $monthly_rent > 0 && $property_type && $total_units > 0 && $available_units >= 0 && $available_units <= $total_units) {
        try {
            $db->query(
                "INSERT INTO properties (property_name, address, landlord_id, monthly_rent, property_type, block_wing, floor_number, door_number, unit_number, total_units, available_units, description, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$property_name, $address, $user['id'], $monthly_rent, $property_type, $block_wing, $floor_number, $door_number, $unit_number, $total_units, $available_units, $description, $image_path]
            );
            $message = "Property added successfully!";
        } catch (Exception $e) {
            $error = "Error adding property: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields correctly. Available units must be less than or equal to total units.";
    }
}

// Delete property
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        if ($user['role'] === 'admin') {
            // Admin can delete any property
            $db->query("DELETE FROM properties WHERE id = ?", [$_GET['delete']]);
        } else {
            // Landlord can only delete their own properties
            $db->query("DELETE FROM properties WHERE id = ? AND landlord_id = ?", [$_GET['delete'], $user['id']]);
        }
        $message = "Property deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting property: " . $e->getMessage();
    }
}

// Get landlord's properties with occupancy status
if ($user['role'] === 'admin') {
    // Admin can see ALL properties
    $properties = $db->fetchAll(
        "SELECT p.*, COUNT(tp.tenant_id) as occupied_units,
                (p.total_units - COUNT(tp.tenant_id)) as vacant_units,
                CASE 
                    WHEN COUNT(tp.tenant_id) = p.total_units THEN 'Fully Occupied'
                    WHEN COUNT(tp.tenant_id) > 0 THEN 'Partially Occupied'
                    ELSE 'Vacant'
                END as occupancy_status
         FROM properties p 
         LEFT JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1
         GROUP BY p.id
         ORDER BY p.created_at DESC"
    );
} else {
    // Landlord can only see their own properties
    $properties = $db->fetchAll(
        "SELECT p.*, COUNT(tp.tenant_id) as occupied_units,
                (p.total_units - COUNT(tp.tenant_id)) as vacant_units,
                CASE 
                    WHEN COUNT(tp.tenant_id) = p.total_units THEN 'Fully Occupied'
                    WHEN COUNT(tp.tenant_id) > 0 THEN 'Partially Occupied'
                    ELSE 'Vacant'
                END as occupancy_status
         FROM properties p 
         LEFT JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1
         WHERE p.landlord_id = ? 
         GROUP BY p.id
         ORDER BY p.created_at DESC",
        [$user['id']]
    );
}

// Get occupancy statistics
if ($user['role'] === 'admin') {
    // Admin sees statistics for ALL properties
    $occupancyStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_properties,
            SUM(p.total_units) as total_units,
            SUM(CASE WHEN tp.tenant_id IS NOT NULL THEN 1 ELSE 0 END) as occupied_units,
            SUM(p.total_units - COALESCE(occupied_count.occupied, 0)) as vacant_units
         FROM properties p 
         LEFT JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1
         LEFT JOIN (
             SELECT property_id, COUNT(*) as occupied 
             FROM tenant_properties 
             WHERE is_active = 1 
             GROUP BY property_id
         ) occupied_count ON p.id = occupied_count.property_id"
    );
} else {
    // Landlord sees statistics for their properties only
    $occupancyStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_properties,
            SUM(p.total_units) as total_units,
            SUM(CASE WHEN tp.tenant_id IS NOT NULL THEN 1 ELSE 0 END) as occupied_units,
            SUM(p.total_units - COALESCE(occupied_count.occupied, 0)) as vacant_units
         FROM properties p 
         LEFT JOIN tenant_properties tp ON p.id = tp.property_id AND tp.is_active = 1
         LEFT JOIN (
             SELECT property_id, COUNT(*) as occupied 
             FROM tenant_properties 
             WHERE is_active = 1 
             GROUP BY property_id
         ) occupied_count ON p.id = occupied_count.property_id
         WHERE p.landlord_id = ?",
        [$user['id']]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Properties - <?php echo APP_NAME; ?></title>
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
        .stat-card:nth-child(5) { border-left-color: #9b59b6; }
        
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
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group input[type="file"] {
            padding: 6px 8px;
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
            min-width: 800px;
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
                min-width: 600px;
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
            <h1>Manage Properties</h1>
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
                    <li><a href="manage_properties.php" class="active">Manage Properties</a></li>
                    <li><a href="tenant_messages.php">Tenant Messages</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php else: ?>
                    <li><a href="landlord_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php" class="active">Manage Properties</a></li>
                    <li><a href="manage_tenants.php">Manage Tenants</a></li>
                    <li><a href="manage_payments.php">Manage Payments</a></li>
                    <li><a href="landlord_receipts.php">Payment Receipts</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>🏠 Manage Properties</h1>
                <p>Add, edit, and manage your rental properties</p>
            </div>
            
            <!-- Occupancy Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $occupancyStats['total_properties']; ?></div>
                    <div class="stat-label">Total Properties</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $occupancyStats['total_units']; ?></div>
                    <div class="stat-label">Total Units</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #27ae60;"><?php echo $occupancyStats['occupied_units']; ?></div>
                    <div class="stat-label">Occupied Units</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #e74c3c;"><?php echo $occupancyStats['vacant_units']; ?></div>
                    <div class="stat-label">Vacant Units</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $occupancyStats['total_units'] > 0 ? round(($occupancyStats['occupied_units'] / $occupancyStats['total_units']) * 100, 1) : 0; ?>%</div>
                    <div class="stat-label">Occupancy Rate</div>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Add Property Form -->
            <div class="card">
                <div class="card-header">
                    <h3>➕ Add New Property</h3>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_name">Property Name *</label>
                            <input type="text" id="property_name" name="property_name" required
                                   placeholder="e.g., Ole House, Shelter Link, Kileleshwa Apartments">
                        </div>
                        <div class="form-group">
                            <label for="property_type">Property Type *</label>
                            <input type="text" id="property_type" name="property_type" required
                                   placeholder="e.g., Bedsitter, 2 Bedroom, Studio, Commercial">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="block_wing">Block/Wing</label>
                            <select id="block_wing" name="block_wing">
                                <option value="">Select block</option>
                                <option value="Block A">Block A</option>
                                <option value="Block B">Block B</option>
                                <option value="Block C">Block C</option>
                                <option value="Block D">Block D</option>
                                <option value="Wing A">Wing A</option>
                                <option value="Wing B">Wing B</option>
                                <option value="Wing C">Wing C</option>
                                <option value="Ground Floor">Ground Floor</option>
                                <option value="First Floor">First Floor</option>
                                <option value="Second Floor">Second Floor</option>
                                <option value="Third Floor">Third Floor</option>
                                <option value="Fourth Floor">Fourth Floor</option>
                                <option value="Fifth Floor">Fifth Floor</option>
                                <option value="Sixth Floor">Sixth Floor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="floor_number">Floor Number</label>
                            <input type="number" id="floor_number" name="floor_number" min="0" max="50"
                                   placeholder="e.g., 1, 2, 3">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="door_number">Door Number</label>
                            <input type="text" id="door_number" name="door_number" 
                                   placeholder="e.g., A1, B2, 101, 205">
                        </div>
                        <div class="form-group">
                            <label for="unit_number">Unit Number</label>
                            <input type="text" id="unit_number" name="unit_number" 
                                   placeholder="e.g., Unit 1, Apt 2, Room 3">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="total_units">Total Units *</label>
                            <input type="number" id="total_units" name="total_units" required
                                   min="1" max="1000" placeholder="e.g., 29, 50, 100" onchange="updateAvailableUnits()">
                        </div>
                        <div class="form-group">
                            <label for="available_units">Available Units *</label>
                            <input type="number" id="available_units" name="available_units" required
                                   min="0" max="1000" placeholder="e.g., 15, 25, 80">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="monthly_rent">Monthly Rent per Unit (KES) *</label>
                            <input type="number" id="monthly_rent" name="monthly_rent" required
                                   step="100" min="0" placeholder="25000">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_image">Property Image</label>
                            <input type="file" id="property_image" name="property_image" 
                                   accept="image/*">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea id="address" name="address" required rows="2"
                                  placeholder="e.g., Kileleshwa, Nairobi or Westlands, Nairobi"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  placeholder="Additional details about the property..."></textarea>
                    </div>
                    
                    <button type="submit" name="add_property" class="btn btn-primary">Add Property</button>
                </form>
            </div>
            
            <!-- Properties List -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 Your Properties (<?php echo count($properties); ?>)</h3>
                </div>
                <?php if (empty($properties)): ?>
                    <p>No properties found. Add your first property above!</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Property</th>
                                    <th>Type</th>
                                    <th>Units</th>
                                    <th>Status</th>
                                    <th>Address</th>
                                    <th>Rent per Unit</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($properties as $property): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($property['property_name']); ?></strong>
                                            <?php if (!empty($property['floor_number'])): ?>
                                                <br><small>Floor <?php echo $property['floor_number']; ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($property['door_number'])): ?>
                                                <br><small>Door: <?php echo htmlspecialchars($property['door_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-paid"><?php echo htmlspecialchars($property['property_type'] ?: 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo $property['occupied_units']; ?>/<?php echo $property['total_units']; ?></strong>
                                            <br><small style="color: #27ae60;"><?php echo $property['occupied_units']; ?> occupied</small>
                                            <br><small style="color: #e74c3c;"><?php echo $property['vacant_units']; ?> vacant</small>
                                        </td>
                                        <td>
                                            <?php if ($property['occupancy_status'] == 'Fully Occupied'): ?>
                                                <span class="status-badge status-paid">✅ Fully Occupied</span>
                                            <?php elseif ($property['occupancy_status'] == 'Partially Occupied'): ?>
                                                <span class="status-badge" style="background: #f39c12; color: white;">⚠️ Partially Occupied</span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending">❌ Vacant</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($property['address']); ?></td>
                                        <td><?php echo formatCurrency($property['monthly_rent']); ?></td>
                                        <td>
                                            <a href="edit_property.php?id=<?php echo $property['id']; ?>" 
                                               class="btn" style="background: #3498db; color: white; padding: 5px 10px; font-size: 12px; margin-right: 5px;">Edit</a>
                                            <?php if ($property['vacant_units'] > 0): ?>
                                                <a href="manage_tenants.php" 
                                                   class="btn" style="background: #27ae60; color: white; padding: 5px 10px; font-size: 12px; margin-right: 5px;">Add Tenant</a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $property['id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this property?')"
                                               class="btn" style="background: #e74c3c; color: white; padding: 5px 10px; font-size: 12px;">Delete</a>
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
        });
        
        // Form functions
        function updateAvailableUnits() {
            const totalUnits = document.getElementById('total_units').value;
            const availableUnits = document.getElementById('available_units');
            
            if (totalUnits) {
                availableUnits.max = totalUnits;
                if (parseInt(availableUnits.value) > parseInt(totalUnits)) {
                    availableUnits.value = totalUnits;
                }
            }
        }
        
        // Validate available units on input
        document.getElementById('available_units').addEventListener('input', function() {
            const totalUnits = parseInt(document.getElementById('total_units').value);
            const availableUnits = parseInt(this.value);
            
            if (totalUnits && availableUnits > totalUnits) {
                this.value = totalUnits;
                alert('Available units cannot be more than total units!');
            }
        });
    </script>
</body>
</html>

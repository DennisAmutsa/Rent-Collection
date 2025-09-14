<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin') && !hasRole('landlord')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Get property ID from URL
$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$property_id) {
    redirect('manage_properties.php');
}

// Get property details
$property = $db->fetchOne(
    "SELECT * FROM properties WHERE id = ? AND landlord_id = ?",
    [$property_id, $user['id']]
);

if (!$property) {
    redirect('manage_properties.php');
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_property'])) {
    $property_name = sanitizeInput($_POST['property_name']);
    $address = sanitizeInput($_POST['address']);
    $monthly_rent = floatval($_POST['monthly_rent']);
    $property_type = sanitizeInput($_POST['property_type']);
    $block_wing = sanitizeInput($_POST['block_wing']);
    $floor_number = sanitizeInput($_POST['floor_number']);
    $door_number = sanitizeInput($_POST['door_number']);
    $unit_number = sanitizeInput($_POST['unit_number']);
    $description = sanitizeInput($_POST['description']);
    
    // Handle image upload
    $image_path = $property['image_path']; // Keep existing image
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
                // Delete old image if exists
                if ($image_path && file_exists($image_path)) {
                    unlink($image_path);
                }
                $image_path = $upload_path;
            }
        }
    }
    
    if ($property_name && $address && $monthly_rent > 0 && $property_type) {
        try {
            $db->query(
                "UPDATE properties SET 
                 property_name = ?, address = ?, monthly_rent = ?, property_type = ?, 
                 block_wing = ?, floor_number = ?, door_number = ?, unit_number = ?, 
                 description = ?, image_path = ?
                 WHERE id = ? AND landlord_id = ?",
                [$property_name, $address, $monthly_rent, $property_type, $block_wing, 
                 $floor_number, $door_number, $unit_number, $description, $image_path, 
                 $property_id, $user['id']]
            );
            $message = "Property updated successfully!";
            
            // Refresh property data
            $property = $db->fetchOne(
                "SELECT * FROM properties WHERE id = ? AND landlord_id = ?",
                [$property_id, $user['id']]
            );
        } catch (Exception $e) {
            $error = "Error updating property: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields correctly";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Property - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
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
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>✏️ Edit Property</h1>
                <p>Update property details and information</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Edit Property Form -->
            <div class="card">
                <div class="card-header">
                    <h3>📝 Update Property Details</h3>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_name">Property Name *</label>
                            <input type="text" id="property_name" name="property_name" required
                                   value="<?php echo htmlspecialchars($property['property_name']); ?>"
                                   placeholder="e.g., Ole House, Shelter Link, Kileleshwa Apartments">
                        </div>
                        <div class="form-group">
                            <label for="property_type">Property Type *</label>
                            <input type="text" id="property_type" name="property_type" required
                                   value="<?php echo htmlspecialchars($property['property_type']); ?>"
                                   placeholder="e.g., Bedsitter, 2 Bedroom, Studio, Commercial">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="block_wing">Block/Wing</label>
                            <select id="block_wing" name="block_wing">
                                <option value="">Select block</option>
                                <option value="Block A" <?php echo $property['block_wing'] == 'Block A' ? 'selected' : ''; ?>>Block A</option>
                                <option value="Block B" <?php echo $property['block_wing'] == 'Block B' ? 'selected' : ''; ?>>Block B</option>
                                <option value="Block C" <?php echo $property['block_wing'] == 'Block C' ? 'selected' : ''; ?>>Block C</option>
                                <option value="Block D" <?php echo $property['block_wing'] == 'Block D' ? 'selected' : ''; ?>>Block D</option>
                                <option value="Wing A" <?php echo $property['block_wing'] == 'Wing A' ? 'selected' : ''; ?>>Wing A</option>
                                <option value="Wing B" <?php echo $property['block_wing'] == 'Wing B' ? 'selected' : ''; ?>>Wing B</option>
                                <option value="Wing C" <?php echo $property['block_wing'] == 'Wing C' ? 'selected' : ''; ?>>Wing C</option>
                                <option value="Ground Floor" <?php echo $property['block_wing'] == 'Ground Floor' ? 'selected' : ''; ?>>Ground Floor</option>
                                <option value="First Floor" <?php echo $property['block_wing'] == 'First Floor' ? 'selected' : ''; ?>>First Floor</option>
                                <option value="Second Floor" <?php echo $property['block_wing'] == 'Second Floor' ? 'selected' : ''; ?>>Second Floor</option>
                                <option value="Third Floor" <?php echo $property['block_wing'] == 'Third Floor' ? 'selected' : ''; ?>>Third Floor</option>
                                <option value="Fourth Floor" <?php echo $property['block_wing'] == 'Fourth Floor' ? 'selected' : ''; ?>>Fourth Floor</option>
                                <option value="Fifth Floor" <?php echo $property['block_wing'] == 'Fifth Floor' ? 'selected' : ''; ?>>Fifth Floor</option>
                                <option value="Sixth Floor" <?php echo $property['block_wing'] == 'Sixth Floor' ? 'selected' : ''; ?>>Sixth Floor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="floor_number">Floor Number</label>
                            <input type="number" id="floor_number" name="floor_number" min="0" max="50"
                                   value="<?php echo htmlspecialchars($property['floor_number']); ?>"
                                   placeholder="e.g., 1, 2, 3">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="door_number">Door Number</label>
                            <input type="text" id="door_number" name="door_number" 
                                   value="<?php echo htmlspecialchars($property['door_number']); ?>"
                                   placeholder="e.g., A1, B2, 101, 205">
                        </div>
                        <div class="form-group">
                            <label for="unit_number">Unit Number</label>
                            <input type="text" id="unit_number" name="unit_number" 
                                   value="<?php echo htmlspecialchars($property['unit_number']); ?>"
                                   placeholder="e.g., Unit 1, Apt 2, Room 3">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="monthly_rent">Monthly Rent (KES) *</label>
                            <input type="number" id="monthly_rent" name="monthly_rent" required
                                   step="100" min="0" value="<?php echo $property['monthly_rent']; ?>"
                                   placeholder="25000">
                        </div>
                        <div class="form-group">
                            <label for="property_image">Property Image</label>
                            <input type="file" id="property_image" name="property_image" 
                                   accept="image/*">
                            <?php if ($property['image_path']): ?>
                                <small>Current: <?php echo basename($property['image_path']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea id="address" name="address" required rows="2"
                                  placeholder="e.g., Kileleshwa, Nairobi or Westlands, Nairobi"><?php echo htmlspecialchars($property['address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  placeholder="Additional details about the property..."><?php echo htmlspecialchars($property['description']); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="update_property" class="btn btn-primary">Update Property</button>
                        <a href="manage_properties.php" class="btn" style="background: #95a5a6; color: white;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

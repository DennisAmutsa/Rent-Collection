<?php
require_once 'config.php';
requireLogin();
if (!hasRole('admin') && !hasRole('landlord')) {
    redirect('unauthorized.php');
}

$user = getCurrentUser();
$db = new Database();

// Handle payment operations
$message = '';
$error = '';

// Record payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $tenant_id = sanitizeInput($_POST['tenant_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = sanitizeInput($_POST['payment_date']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $notes = sanitizeInput($_POST['notes']);
    
    if ($tenant_id && $amount > 0 && $payment_date) {
        try {
            // Get the property_id for this tenant
            if ($user['role'] === 'admin') {
                // Admin can record payments for any tenant
                $tenant_property = $db->fetchOne(
                    "SELECT tp.property_id FROM tenant_properties tp 
                     WHERE tp.tenant_id = ? AND tp.is_active = 1",
                    [$tenant_id]
                );
            } else {
                // Landlord can only record payments for their own tenants
                $tenant_property = $db->fetchOne(
                    "SELECT tp.property_id FROM tenant_properties tp 
                     JOIN properties p ON tp.property_id = p.id 
                     WHERE tp.tenant_id = ? AND tp.is_active = 1 AND p.landlord_id = ?",
                    [$tenant_id, $user['id']]
                );
            }
            
            if ($tenant_property) {
                // Set due_date same as payment_date for recorded payments
                $db->query(
                    "INSERT INTO rent_payments (tenant_id, property_id, amount, payment_date, due_date, payment_method, notes, recorded_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid')",
                    [$tenant_id, $tenant_property['property_id'], $amount, $payment_date, $payment_date, $payment_method, $notes, $user['id']]
                );
                
                // Get the payment ID for the receipt
                $payment_id = $db->lastInsertId();
                
                // Create receipt notification for tenant
                $receipt_message = "Payment Receipt: KES " . number_format($amount, 2) . " received on " . date('F j, Y', strtotime($payment_date)) . " via " . $payment_method . ". Payment ID: #" . $payment_id;
                if ($notes) {
                    $receipt_message .= "\n\nNotes: " . $notes;
                }
                
                $db->query(
                    "INSERT INTO notifications (user_id, title, message, created_by) VALUES (?, ?, ?, ?)",
                    [$tenant_id, "Payment Receipt - " . date('M j, Y', strtotime($payment_date)), $receipt_message, $user['id']]
                );
                
                $message = "Payment recorded successfully! Receipt sent to tenant.";
            } else {
                if ($user['role'] === 'admin') {
                    $error = "Tenant not found or not assigned to any property";
                } else {
                    $error = "Tenant not found or not assigned to your properties";
                }
            }
        } catch (Exception $e) {
            $error = "Error recording payment: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields correctly";
    }
}

// Get all payments
if ($user['role'] === 'admin') {
    // Admin sees all payments
    $payments = $db->fetchAll(
        "SELECT rp.*, u.full_name as tenant_name, u.email as tenant_email, p.property_name
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         ORDER BY rp.payment_date DESC"
    );
    
    // Admin sees all tenants
    $tenants = $db->fetchAll(
        "SELECT DISTINCT u.id, u.full_name, u.email, p.property_name
         FROM users u
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         ORDER BY u.full_name"
    );
} else {
    // Landlord sees only their payments
    $payments = $db->fetchAll(
        "SELECT rp.*, u.full_name as tenant_name, u.email as tenant_email, p.property_name
         FROM rent_payments rp
         JOIN users u ON rp.tenant_id = u.id
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         WHERE p.landlord_id = ?
         ORDER BY rp.payment_date DESC",
        [$user['id']]
    );
    
    // Landlord sees only their tenants
    $tenants = $db->fetchAll(
        "SELECT DISTINCT u.id, u.full_name, u.email, p.property_name
         FROM users u
         JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
         JOIN properties p ON tp.property_id = p.id
         WHERE p.landlord_id = ?
         ORDER BY u.full_name",
        [$user['id']]
    );
}

// Calculate totals
$totalPaid = array_sum(array_column($payments, 'amount'));
$totalPayments = count($payments);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - <?php echo APP_NAME; ?></title>
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
        .stat-card:nth-child(2) { border-left-color: #27ae60; }
        .stat-card:nth-child(3) { border-left-color: #f39c12; }
        .stat-card:nth-child(4) { border-left-color: #e74c3c; }
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
            <h1>Manage Payments</h1>
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
                    <li><a href="manage_payments.php" class="active">Manage Payments</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php else: ?>
                    <li><a href="landlord_dashboard.php">Dashboard</a></li>
                    <li><a href="manage_properties.php">Manage Properties</a></li>
                    <li><a href="manage_tenants.php">Manage Tenants</a></li>
                    <li><a href="manage_payments.php" class="active">Manage Payments</a></li>
                    <li><a href="send_notifications.php">Send Notifications</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="system_reports.php">System Reports</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>💰 Manage Payments</h1>
                <p>Record and track rent payments from your tenants</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Payment Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalPayments; ?></div>
                    <div class="stat-label">Total Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatCurrency($totalPaid); ?></div>
                    <div class="stat-label">Total Collected</div>
                </div>
            </div>
            
            <!-- Record Payment Form -->
            <div class="card">
                <div class="card-header">
                    <h3>💳 Record New Payment</h3>
                </div>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tenant_id">Select Tenant *</label>
                            <select id="tenant_id" name="tenant_id" required>
                                <option value="">Choose a tenant</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>">
                                        <?php echo htmlspecialchars($tenant['full_name'] . ' - ' . $tenant['property_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount *</label>
                            <input type="number" id="amount" name="amount" required
                                   step="0.01" min="0" placeholder="1200.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="payment_date">Payment Date *</label>
                            <input type="date" id="payment_date" name="payment_date" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Select method</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                                <option value="Online Payment">Online Payment</option>
                                <option value="Mobile Money">Mobile Money</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="2" placeholder="Additional payment details..."></textarea>
                    </div>
                    <button type="submit" name="record_payment" class="btn btn-primary">Record Payment</button>
                </form>
            </div>
            
            <!-- Payments History -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 Payment History (<?php echo count($payments); ?>)</h3>
                </div>
                <?php if (empty($payments)): ?>
                    <p>No payments recorded yet.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Tenant</th>
                                    <th>Property</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['tenant_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($payment['tenant_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['notes'] ?: 'N/A'); ?></td>
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
    </script>
</body>
</html>

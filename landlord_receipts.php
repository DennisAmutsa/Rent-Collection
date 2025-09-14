<?php
require_once 'config.php';
requireRole('landlord');

$user = getCurrentUser();
$db = new Database();

// Get all receipts issued by this landlord
$receipts = $db->fetchAll(
    "SELECT rp.*, u.full_name as tenant_name, u.email as tenant_email, p.property_name, p.address
     FROM rent_payments rp
     JOIN users u ON rp.tenant_id = u.id
     JOIN properties p ON rp.property_id = p.id
     WHERE p.landlord_id = ?
     ORDER BY rp.payment_date DESC",
    [$user['id']]
);

// Get receipt statistics
$receiptStats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_receipts,
        SUM(rp.amount) as total_amount,
        COUNT(DISTINCT rp.tenant_id) as unique_tenants,
        COUNT(DISTINCT DATE(rp.payment_date)) as payment_days
     FROM rent_payments rp
     JOIN properties p ON rp.property_id = p.id
     WHERE p.landlord_id = ?",
    [$user['id']]
);

// Filter options
$filter_property = isset($_GET['property']) ? sanitizeInput($_GET['property']) : '';
$filter_tenant = isset($_GET['tenant']) ? sanitizeInput($_GET['tenant']) : '';
$filter_date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Apply filters
$where_conditions = ["p.landlord_id = ?"];
$params = [$user['id']];

if ($filter_property) {
    $where_conditions[] = "p.id = ?";
    $params[] = $filter_property;
}

if ($filter_tenant) {
    $where_conditions[] = "u.id = ?";
    $params[] = $filter_tenant;
}

if ($filter_date_from) {
    $where_conditions[] = "rp.payment_date >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $where_conditions[] = "rp.payment_date <= ?";
    $params[] = $filter_date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get filtered receipts
$filteredReceipts = $db->fetchAll(
    "SELECT rp.*, u.full_name as tenant_name, u.email as tenant_email, p.property_name, p.address
     FROM rent_payments rp
     JOIN users u ON rp.tenant_id = u.id
     JOIN properties p ON rp.property_id = p.id
     WHERE $where_clause
     ORDER BY rp.payment_date DESC",
    $params
);

// Get properties for filter dropdown
$properties = $db->fetchAll(
    "SELECT * FROM properties WHERE landlord_id = ? ORDER BY property_name",
    [$user['id']]
);

// Get tenants for filter dropdown
$tenants = $db->fetchAll(
    "SELECT DISTINCT u.id, u.full_name, u.email
     FROM users u
     JOIN tenant_properties tp ON u.id = tp.tenant_id AND tp.is_active = 1
     JOIN properties p ON tp.property_id = p.id
     WHERE p.landlord_id = ?
     ORDER BY u.full_name",
    [$user['id']]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipts - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="user-role">👑 Landlord</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="landlord_dashboard.php">Dashboard</a></li>
                <li><a href="manage_properties.php">Manage Properties</a></li>
                <li><a href="manage_tenants.php">Manage Tenants</a></li>
                <li><a href="manage_payments.php">Manage Payments</a></li>
                <li><a href="landlord_receipts.php" class="active">Payment Receipts</a></li>
                <li><a href="send_notifications.php">Send Notifications</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="system_reports.php">System Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="content-header">
                <h1>🧾 Payment Receipts</h1>
                <p>View and manage all payment receipts issued to your tenants</p>
            </div>
            
            <!-- Receipt Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $receiptStats['total_receipts']; ?></div>
                    <div class="stat-label">Total Receipts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatCurrency($receiptStats['total_amount']); ?></div>
                    <div class="stat-label">Total Amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $receiptStats['unique_tenants']; ?></div>
                    <div class="stat-label">Unique Tenants</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $receiptStats['payment_days']; ?></div>
                    <div class="stat-label">Payment Days</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3>🔍 Filter Receipts</h3>
                </div>
                <form method="GET" style="padding: 20px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property">Property</label>
                            <select id="property" name="property">
                                <option value="">All Properties</option>
                                <?php foreach ($properties as $property): ?>
                                    <option value="<?php echo $property['id']; ?>" <?php echo $filter_property == $property['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($property['property_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tenant">Tenant</label>
                            <select id="tenant" name="tenant">
                                <option value="">All Tenants</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>" <?php echo $filter_tenant == $tenant['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tenant['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $filter_date_from; ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $filter_date_to; ?>">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="landlord_receipts.php" class="btn" style="background: #95a5a6; color: white;">Clear Filters</a>
                    </div>
                </form>
            </div>
            
            <!-- Receipts List -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 All Receipts (<?php echo count($filteredReceipts); ?>)</h3>
                </div>
                <?php if (empty($filteredReceipts)): ?>
                    <div style="padding: 40px; text-align: center; color: #7f8c8d;">
                        <h3>No receipts found</h3>
                        <p>Receipts will appear here once payments are recorded.</p>
                    </div>
                <?php else: ?>
                    <div style="padding: 20px;">
                        <?php foreach ($filteredReceipts as $receipt): ?>
                            <div class="receipt-card" style="border: 1px solid #ecf0f1; border-radius: 8px; padding: 20px; margin-bottom: 15px; background: #f8f9fa;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div>
                                        <h4 style="margin: 0; color: #2c3e50;">Receipt #<?php echo $receipt['id']; ?></h4>
                                        <small style="color: #7f8c8d;">Issued on <?php echo formatDate($receipt['payment_date']); ?></small>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 24px; font-weight: bold; color: #27ae60;">
                                            <?php echo formatCurrency($receipt['amount']); ?>
                                        </div>
                                        <span class="status-badge status-paid">✅ Paid</span>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                    <div>
                                        <strong>Tenant:</strong><br>
                                        <?php echo htmlspecialchars($receipt['tenant_name']); ?><br>
                                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars($receipt['tenant_email']); ?></small>
                                    </div>
                                    <div>
                                        <strong>Property:</strong><br>
                                        <?php echo htmlspecialchars($receipt['property_name']); ?><br>
                                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars($receipt['address']); ?></small>
                                    </div>
                                    <div>
                                        <strong>Payment Method:</strong><br>
                                        <?php echo htmlspecialchars($receipt['payment_method']); ?>
                                    </div>
                                    <div>
                                        <strong>Payment Date:</strong><br>
                                        <?php echo formatDate($receipt['payment_date']); ?>
                                    </div>
                                </div>
                                
                                <?php if ($receipt['notes']): ?>
                                    <div style="background: #e8f4fd; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($receipt['notes']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <button onclick="viewReceipt(<?php echo $receipt['id']; ?>)" 
                                            class="btn" style="background: #3498db; color: white; padding: 8px 16px;">
                                        👁️ View Receipt
                                    </button>
                                    <button onclick="printReceipt(<?php echo $receipt['id']; ?>)" 
                                            class="btn" style="background: #f39c12; color: white; padding: 8px 16px;">
                                        🖨️ Print
                                    </button>
                                    <button onclick="emailReceipt(<?php echo $receipt['id']; ?>)" 
                                            class="btn" style="background: #27ae60; color: white; padding: 8px 16px;">
                                        📧 Email to Tenant
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function viewReceipt(receiptId) {
            // Open receipt in new window for viewing
            window.open(`view_receipt.php?id=${receiptId}`, '_blank', 'width=800,height=600');
        }
        
        function printReceipt(receiptId) {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            const receiptCard = document.querySelector(`[onclick="printReceipt(${receiptId})"]`).closest('.receipt-card');
            
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Payment Receipt #${receiptId}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .receipt { border: 2px solid #333; padding: 20px; max-width: 500px; }
                            .header { text-align: center; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                            .amount { font-size: 24px; font-weight: bold; color: #27ae60; text-align: center; margin: 20px 0; }
                            .details { margin: 10px 0; }
                            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class="receipt">
                            <div class="header">
                                <h2>PAYMENT RECEIPT</h2>
                                <p>Rent Collection System</p>
                            </div>
                            <div class="amount">${receiptCard.querySelector('.status-badge').previousElementSibling.textContent}</div>
                            <div class="details">
                                <p><strong>Receipt ID:</strong> #${receiptId}</p>
                                <p><strong>Date:</strong> ${receiptCard.querySelector('small').textContent.replace('Issued on ', '')}</p>
                                <p><strong>Tenant:</strong> ${receiptCard.querySelectorAll('div')[2].textContent.trim()}</p>
                                <p><strong>Property:</strong> ${receiptCard.querySelectorAll('div')[3].textContent.trim()}</p>
                                <p><strong>Payment Method:</strong> ${receiptCard.querySelectorAll('div')[4].textContent.trim()}</p>
                            </div>
                            <div class="footer">
                                <p>Thank you for your payment!</p>
                                <p>Generated on ${new Date().toLocaleDateString()}</p>
                            </div>
                        </div>
                    </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }
        
        function emailReceipt(receiptId) {
            if (confirm('Send this receipt to the tenant via email?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '📧 Sending...';
                button.disabled = true;
                
                // Send AJAX request
                fetch('email_receipt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'receipt_id=' + receiptId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    alert('❌ Error sending email. Please try again.');
                    console.error('Error:', error);
                })
                .finally(() => {
                    // Reset button
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }
        }
    </script>
</body>
</html>

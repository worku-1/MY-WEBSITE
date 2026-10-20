<?php
// [file name]: inventory.php
session_start();

// Include database connection
require_once 'db_connect.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get user info from session
$user_role = $_SESSION['role'] ?? 'Customer';
$username = $_SESSION['username'] ?? 'User';
$full_name = $_SESSION['full_name'] ?? $username;

// Handle Add Inventory
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_inventory'])) {
    $type = sanitize($_POST['type']);
    $quantity = (int)$_POST['quantity'];
    $warehouse = sanitize($_POST['warehouse']);
    
    $sql = "INSERT INTO inventory (fertilizer_type, quantity, warehouse) VALUES ('$type', $quantity, '$warehouse')";
    
    if ($conn->query($sql)) {
        header("Location: inventory.php?msg=added");
        exit();
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Handle Update Inventory
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_inventory'])) {
    $id = (int)$_POST['inventory_id'];
    $type = sanitize($_POST['type']);
    $quantity = (int)$_POST['quantity'];
    $warehouse = sanitize($_POST['warehouse']);
    
    $sql = "UPDATE inventory SET fertilizer_type='$type', quantity=$quantity, warehouse='$warehouse' WHERE id=$id";
    
    if ($conn->query($sql)) {
        header("Location: inventory.php?msg=updated");
        exit();
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($user_role == 'Admin' || $user_role == 'Supplier') {
        $conn->query("DELETE FROM inventory WHERE id=$id");
        header("Location: inventory.php?msg=deleted");
        exit();
    }
}

// Get inventory data for editing if ID is provided
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_result = $conn->query("SELECT * FROM inventory WHERE id = $edit_id");
    if ($edit_result && $edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    }
}

// Fetch inventory data
$result = $conn->query("SELECT * FROM inventory ORDER BY id DESC");

// Get statistics
$total_items = 0;
$total_quantity = 0;
$warehouses = [];
$dap_total = 0;
$urea_total = 0;

if ($result && $result->num_rows > 0) {
    $total_items = $result->num_rows;
    $result->data_seek(0);
    while($row = $result->fetch_assoc()) {
        $total_quantity += $row['quantity'];
        if (!in_array($row['warehouse'], $warehouses)) {
            $warehouses[] = $row['warehouse'];
        }
        if ($row['fertilizer_type'] == 'DAP') $dap_total += $row['quantity'];
        if ($row['fertilizer_type'] == 'Urea') $urea_total += $row['quantity'];
    }
    $result->data_seek(0); // Reset pointer
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory - Fertilizer Supply Chain</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="inventory.css">
</head>

<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] == 'true' ? 'dark-mode' : ''; ?>">

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>🌱 FSC <span class="role-badge"><?php echo $user_role; ?></span></h2>
    <a href="dashboard.php"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
    <a href="inventory.php" class="active"><i class="fas fa-box"></i> <span>Inventory</span></a>
    <a href="distribution.php"><i class="fas fa-truck"></i> <span>Distribution</span></a>
    <a href="shipments.php"><i class="fas fa-truck-loading"></i> <span>Shipments</span></a>
    <a href="farmer.php"><i class="fas fa-users"></i> <span>Farmer Registry</span></a>
    <a href="reports.php"><i class="fas fa-file-alt"></i> <span>Reports</span></a>
    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>

<div class="main-content">

    <div class="topbar">
        <div class="toggle-btn" onclick="toggleSidebar()">☰</div>
        <div class="user-info">
            <span><i class="fas fa-user"></i> <?php echo $full_name; ?> (<?php echo $user_role; ?>)</span>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <button onclick="toggleMode()"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <?php 
            if ($_GET['msg'] == 'added') echo "Inventory added successfully!";
            if ($_GET['msg'] == 'updated') echo "Inventory updated successfully!";
            if ($_GET['msg'] == 'deleted') echo "Inventory deleted successfully!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_items; ?></div>
            <div class="stat-label">Total Batches</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($total_quantity); ?> Qtl</div>
            <div class="stat-label">Total Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($warehouses); ?></div>
            <div class="stat-label">Warehouses</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($dap_total); ?> Qtl</div>
            <div class="stat-label">DAP Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($urea_total); ?> Qtl</div>
            <div class="stat-label">Urea Stock</div>
        </div>
    </div>

    <h2 id="pageTitle" style="margin-bottom: 20px;">
        <?php 
        if ($user_role == 'Admin') echo 'Inventory Management (Admin)';
        elseif ($user_role == 'Supplier') echo 'Supplier Inventory Management';
        else echo 'Local Inventory (View Only)';
        ?>
    </h2>

    <!-- Add/Edit Inventory Form - Only for Admin and Supplier -->
    <?php if ($user_role == 'Admin' || $user_role == 'Supplier'): ?>
    <div class="inventory-card">
        <h3 id="formTitle"><?php echo $edit_data ? 'Edit Inventory' : 'Add New Inventory'; ?></h3>
        <form method="POST" action="inventory.php">
            <?php if ($edit_data): ?>
                <input type="hidden" name="inventory_id" value="<?php echo $edit_data['id']; ?>">
            <?php endif; ?>
            
            <div class="form-grid">
                <div>
                    <label>Fertilizer Type</label>
                    <select name="type" required>
                        <option value="">Select Type</option>
                        <option value="DAP" <?php echo ($edit_data && $edit_data['fertilizer_type'] == 'DAP') ? 'selected' : ''; ?>>DAP</option>
                        <option value="Urea" <?php echo ($edit_data && $edit_data['fertilizer_type'] == 'Urea') ? 'selected' : ''; ?>>Urea</option>
                        <option value="NPS" <?php echo ($edit_data && $edit_data['fertilizer_type'] == 'NPS') ? 'selected' : ''; ?>>NPS</option>
                        <option value="NPSB" <?php echo ($edit_data && $edit_data['fertilizer_type'] == 'NPSB') ? 'selected' : ''; ?>>NPSB</option>
                        <option value="Potash" <?php echo ($edit_data && $edit_data['fertilizer_type'] == 'Potash') ? 'selected' : ''; ?>>Potash</option>
                    </select>
                </div>
                <div>
                    <label>Quantity (Qtl)</label>
                    <input type="number" name="quantity" value="<?php echo $edit_data ? $edit_data['quantity'] : ''; ?>" placeholder="0" min="1" required>
                </div>
                <div>
                    <label>Warehouse</label>
                    <select name="warehouse" required>
                        <option value="">Select Warehouse</option>
                        <option value="Debark Central" <?php echo ($edit_data && $edit_data['warehouse'] == 'Debark Central') ? 'selected' : ''; ?>>Debark Central</option>
                        <option value="Gondar Hub" <?php echo ($edit_data && $edit_data['warehouse'] == 'Gondar Hub') ? 'selected' : ''; ?>>Gondar Hub</option>
                        <option value="Axum Depot" <?php echo ($edit_data && $edit_data['warehouse'] == 'Axum Depot') ? 'selected' : ''; ?>>Axum Depot</option>
                        <option value="Bahir Dar" <?php echo ($edit_data && $edit_data['warehouse'] == 'Bahir Dar') ? 'selected' : ''; ?>>Bahir Dar</option>
                        <option value="Addis Ababa" <?php echo ($edit_data && $edit_data['warehouse'] == 'Addis Ababa') ? 'selected' : ''; ?>>Addis Ababa</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <?php if ($edit_data): ?>
                    <button type="submit" name="update_inventory" class="btn-submit"><i class="fas fa-save"></i> Update Inventory</button>
                    <a href="inventory.php" class="btn-submit btn-cancel"><i class="fas fa-times"></i> Cancel</a>
                <?php else: ?>
                    <button type="submit" name="add_inventory" class="btn-submit"><i class="fas fa-plus"></i> Add Inventory</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Inventory Table -->
    <table id="inventoryTable">
        <thead>
            <tr>
                <th>Type</th>
                <th>Quantity (Qtl)</th>
                <th>Warehouse</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['fertilizer_type']); ?></strong></td>
                    <td><?php echo number_format($row['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($row['warehouse']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($row['updated_at'] ?? $row['created_at'])); ?></td>
                    <td>
                        <?php if ($user_role == 'Admin' || $user_role == 'Supplier'): ?>
                        <a href="inventory.php?edit=<?php echo $row['id']; ?>" class="edit-btn">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="inventory.php?delete=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this record?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                        <?php else: ?>
                        <button class="edit-btn" disabled><i class="fas fa-edit"></i> Edit</button>
                        <button class="delete-btn" disabled><i class="fas fa-trash"></i> Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; padding:30px;">No inventory records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<script>
// Sidebar toggle
function toggleSidebar(){
    document.getElementById("sidebar").classList.toggle("collapsed");
}

// Dark mode toggle
function toggleMode(){
    document.body.classList.toggle("dark-mode");
    document.cookie = "darkMode=" + document.body.classList.contains('dark-mode');
}

// Load dark mode preference
if (document.cookie.includes('darkMode=true')) {
    document.body.classList.add('dark-mode');
}

// Auto-hide alert after 3 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.style.display = 'none';
        }, 500);
    });
}, 3000);
</script>

</body>
</html>
<?php $conn->close(); ?>
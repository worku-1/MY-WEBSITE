<?php
// [file name]: shipments.php
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

// Check if user has permission
// Customer can only view shipments
$can_view = ($user_role == 'Admin' || $user_role == 'Supplier' || $user_role == 'Customer');
$can_add = ($user_role == 'Admin' || $user_role == 'Supplier');
$can_edit_delete = ($user_role == 'Admin'); // Only Admin can delete shipments

if (!$can_view) {
    header("Location: dashboard.php");
    exit();
}

// Handle Add Shipment (Only for Admin and Supplier)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shipment']) && $can_add) {
    $errors = [];
    
    // Validate Truck Plate (letters and numbers only)
    $truck_plate = strtoupper(sanitize($_POST['truckPlate']));
    if (!preg_match('/^[A-Z0-9\s-]+$/', $truck_plate)) {
        $errors[] = "Truck plate number can only contain letters, numbers, spaces, and hyphens.";
    }
    
    // Validate Driver Name (letters only)
    $driver_name = strtoupper(sanitize($_POST['driverName']));
    if (!preg_match('/^[A-Z\s]+$/', $driver_name)) {
        $errors[] = "Driver name can only contain letters and spaces.";
    }
    
    $region = sanitize($_POST['destRegion']);
    
    // Validate Destination Woreda (letters only)
    $woreda = strtoupper(sanitize($_POST['destWoreda']));
    if (!preg_match('/^[A-Z\s]+$/', $woreda)) {
        $errors[] = "Destination Woreda can only contain letters and spaces.";
    }
    
    // Validate Hub/Kebele (letters and numbers only)
    $hub = strtoupper(sanitize($_POST['destHub']));
    if (!preg_match('/^[A-Z0-9\s-]+$/', $hub)) {
        $errors[] = "Hub/Kebele can only contain letters, numbers, spaces, and hyphens.";
    }
    
    $eta = sanitize($_POST['etaTime']);
    
    // If no errors, proceed with insertion
    if (empty($errors)) {
        $sql = "INSERT INTO shipments (truck_plate, driver_name, region, woreda, hub, eta, status, supplier_id) 
                VALUES ('$truck_plate', '$driver_name', '$region', '$woreda', '$hub', '$eta', 'In-Transit', '{$_SESSION['user_id']}')";
        
        if ($conn->query($sql)) {
            header("Location: shipments.php?msg=added");
            exit();
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Handle Update Status (Only for Admin)
if (isset($_GET['update_status']) && $user_role == 'Admin') {
    $id = (int)$_GET['update_status'];
    $new_status = sanitize($_GET['status']);
    $conn->query("UPDATE shipments SET status = '$new_status' WHERE id = $id");
    header("Location: shipments.php?msg=updated");
    exit();
}

// Handle Delete (Admin only)
if (isset($_GET['delete']) && $user_role == 'Admin') {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM shipments WHERE id = $id");
    header("Location: shipments.php?msg=deleted");
    exit();
}

// Fetch shipments
$result = $conn->query("SELECT * FROM shipments ORDER BY created_at DESC");

// Get statistics
$total_shipments = $result->num_rows;
$in_transit = 0;
$delivered = 0;
$delayed = 0;

if ($result->num_rows > 0) {
    $result->data_seek(0);
    while($row = $result->fetch_assoc()) {
        if ($row['status'] == 'In-Transit') $in_transit++;
        if ($row['status'] == 'Delivered') $delivered++;
        if ($row['status'] == 'Delayed') $delayed++;
    }
    $result->data_seek(0); // Reset pointer
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriTrack - National Shipment Tracking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" type="text/css" href="Shipments.css">
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] == 'true' ? 'dark-mode' : ''; ?>">

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h2>🌱 FSC <span class="role-badge"><?php echo $user_role; ?></span></h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="inventory.php"><i class="fas fa-warehouse"></i> <span>Inventory</span></a>
        <a href="distribution.php"><i class="fas fa-truck"></i> <span>Distribution</span></a>
        <a href="shipments.php" class="active"><i class="fas fa-truck-loading"></i> <span>Shipments</span></a>
        <a href="farmer.php"><i class="fas fa-users"></i> <span>Farmer Registry</span></a>
        <a href="reports.php"><i class="fas fa-file-alt"></i> <span>Reports</span></a>
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="toggle-btn" onclick="toggleSidebar()">☰</div>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo $full_name; ?></span>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <button onclick="toggleMode()"><i class="fas fa-moon"></i> Dark Mode</button>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <?php 
                if ($_GET['msg'] == 'added') echo "Shipment created successfully!";
                if ($_GET['msg'] == 'updated') echo "Shipment status updated!";
                if ($_GET['msg'] == 'deleted') echo "Shipment deleted successfully!";
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Validation Errors:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_shipments; ?></div>
                <div class="stat-label">Total Shipments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $in_transit; ?></div>
                <div class="stat-label">In Transit</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $delivered; ?></div>
                <div class="stat-label">Delivered</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $delayed; ?></div>
                <div class="stat-label">Delayed</div>
            </div>
        </div>

        <!-- Page Title -->
        <h1 class="page-title" id="pageTitle">
            <?php 
            if ($user_role == 'Customer') {
                echo 'Shipment Tracking (View Only)';
            } elseif ($user_role == 'Supplier') {
                echo 'Supplier Shipment Management';
            } else {
                echo 'National Logistics & Shipment Tracking';
            }
            ?>
        </h1>

        <!-- Content Container -->
        <div class="content-container">
            <div class="container-header">
                <h2 id="sectionTitle">Cross-Regional Supply Chain Routes</h2>
                <?php if ($can_add): ?>
                <button class="btn-add" onclick="openModal()">
                    <i class="fas fa-plus"></i> Create New Shipment
                </button>
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Truck Plate</th>
                        <th>Driver Name</th>
                        <th>Target Region</th>
                        <th>Target Woreda</th>
                        <th>Specific Hub/Kebele</th>
                        <th>ETA</th>
                        <th>Status</th>
                        <?php if ($can_edit_delete): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['truck_plate']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                            <td><span class="region-tag"><?php echo htmlspecialchars(strtoupper($row['region'])); ?></span></td>
                            <td><?php echo htmlspecialchars($row['woreda']); ?></td>
                            <td><?php echo htmlspecialchars($row['hub']); ?></td>
                            <td><?php echo htmlspecialchars($row['eta']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace('-', '', $row['status'])); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <?php if ($can_edit_delete): ?>
                            <td>
                                <select onchange="updateStatus(<?php echo $row['id']; ?>, this.value)" style="padding:5px; border-radius:4px;">
                                    <option value="">Change Status</option>
                                    <option value="In-Transit">In-Transit</option>
                                    <option value="Delivered">Delivered</option>
                                    <option value="Delayed">Delayed</option>
                                </select>
                                <a href="shipments.php?delete=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Delete this shipment?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo $can_edit_delete ? '8' : '7'; ?>" style="text-align:center; padding:30px;">No shipments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($user_role == 'Customer'): ?>
            <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; text-align: center;">
                <i class="fas fa-info-circle"></i> You are viewing shipment records. Shipment creation and modification is restricted to administrators and suppliers.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Shipment Modal (Only shown for Admin and Supplier) -->
    <?php if ($can_add): ?>
    <div id="shipmentModal" class="modal-overlay">
        <div class="modal">
            <h2><i class="fas fa-truck"></i> Create New Shipment</h2>
            <form method="POST" action="shipments.php" onsubmit="return validateForm()">
                <div>
                    <label>Truck Plate Number</label>
                    <input type="text" name="truckPlate" id="truckPlate" placeholder="e.g. ET-3-A1234" required 
                           pattern="[A-Za-z0-9\s-]+" title="Only letters, numbers, spaces, and hyphens are allowed">
                </div>
                
                <div>
                    <label>Driver Full Name</label>
                    <input type="text" name="driverName" id="driverName" placeholder="Driver full name" required 
                           pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed">
                </div>
                
                <div>
                    <label>Destination Region</label>
                    <select name="destRegion" required>
                        <option value="">-- Select Destination Region --</option>
                        <option value="Amhara">Amhara</option>
                        <option value="Oromia">Oromia</option>
                        <option value="Tigray">Tigray</option>
                        <option value="Sidama">Sidama</option>
                        <option value="Afar">Afar</option>
                        <option value="Somali">Somali</option>
                        <option value="SNNPR">SNNPR</option>
                        <option value="Gambela">Gambela</option>
                        <option value="Benishangul">Benishangul</option>
                    </select>
                </div>
                
                <div>
                    <label>Destination Woreda</label>
                    <input type="text" name="destWoreda" id="destWoreda" placeholder="Woreda name" required 
                           pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed">
                </div>
                
                <div>
                    <label>Specific Hub/Kebele</label>
                    <input type="text" name="destHub" id="destHub" placeholder="Hub or Kebele name" required 
                           pattern="[A-Za-z0-9\s-]+" title="Only letters, numbers, spaces, and hyphens are allowed">
                </div>
                
                <div>
                    <label>Estimated Arrival Time</label>
                    <input type="text" name="etaTime" placeholder="e.g. 2 hours, 14:00, Tomorrow" required>
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" name="add_shipment" class="btn-add" style="flex:2;">
                        <i class="fas fa-check"></i> Launch Tracking
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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

    // Modal functions
    function openModal() {
        document.getElementById('shipmentModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('shipmentModal').style.display = 'none';
    }

    // Client-side validation
    function validateForm() {
        var driverName = document.getElementById('driverName').value;
        var woreda = document.getElementById('destWoreda').value;
        var truckPlate = document.getElementById('truckPlate').value;
        var hub = document.getElementById('destHub').value;
        
        var driverNameRegex = /^[A-Za-z\s]+$/;
        var alphanumericRegex = /^[A-Za-z0-9\s-]+$/;
        
        if (!driverNameRegex.test(driverName)) {
            alert('Driver name can only contain letters and spaces!');
            return false;
        }
        
        if (!driverNameRegex.test(woreda)) {
            alert('Destination Woreda can only contain letters and spaces!');
            return false;
        }
        
        if (!alphanumericRegex.test(truckPlate)) {
            alert('Truck plate number can only contain letters, numbers, spaces, and hyphens!');
            return false;
        }
        
        if (!alphanumericRegex.test(hub)) {
            alert('Hub/Kebele can only contain letters, numbers, spaces, and hyphens!');
            return false;
        }
        
        return true;
    }

    // Update status function (only available for Admin)
    <?php if ($user_role == 'Admin'): ?>
    function updateStatus(id, status) {
        if (status) {
            window.location.href = 'shipments.php?update_status=' + id + '&status=' + encodeURIComponent(status);
        }
    }
    <?php endif; ?>

    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('shipmentModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Load dark mode preference
    if (document.cookie.includes('darkMode=true')) {
        document.body.classList.add('dark-mode');
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>
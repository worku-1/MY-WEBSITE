<?php
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

// Check if user has permission (Admin, Retailer, and Customer can view)
// Customer can only view, not add/edit/delete
$can_view = ($user_role == 'Admin' || $user_role == 'Retailer' || $user_role == 'Customer');
$can_edit_delete = ($user_role == 'Admin' || $user_role == 'Retailer');
$can_add = ($user_role == 'Admin' || $user_role == 'Retailer');

if (!$can_view) {
    header("Location: dashboard.php");
    exit();
}

// Error variables for inline display
$name_error = $woreda_error = $kebele_error = '';
$show_modal = false; // flag to keep modal open

// Handle Add Farmer (Only for Admin and Retailer)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_farmer']) && $can_add) {
    $region = sanitize($_POST['region']);
    $name = strtoupper(sanitize($_POST['name']));
    $woreda = strtoupper(sanitize($_POST['woreda']));
    $kebele = strtoupper(sanitize($_POST['kebele']));
    $land = floatval($_POST['land']);
    
    $valid = true;
    
    // Validate Full Name - Only letters and spaces
    if (!preg_match("/^[A-Za-z\s]+$/", $name)) {
        $name_error = "Full Name can only contain letters and spaces!";
        $valid = false;
    }
    // Validate Woreda - Only letters and spaces
    if (!preg_match("/^[A-Za-z\s]+$/", $woreda)) {
        $woreda_error = "Woreda can only contain letters and spaces!";
        $valid = false;
    }
    // Validate Kebele - Letters, numbers, and spaces only
    if (!preg_match("/^[A-Za-z0-9\s]+$/", $kebele)) {
        $kebele_error = "Kebele can only contain letters, numbers, and spaces!";
        $valid = false;
    }
    
    if ($valid) {
        // Calculate requirement (land * 3 as per your logic)
        $requirement = $land * 3;
        
        // Generate farmer ID
        $farmer_id = "FARM-" . date('Y') . "-" . rand(1000, 9999);
        
        $sql = "INSERT INTO farmers (farmer_id, region, full_name, woreda, kebele, land_size, requirement, status) 
                VALUES ('$farmer_id', '$region', '$name', '$woreda', '$kebele', $land, $requirement, 'Active')";
        
        if ($conn->query($sql)) {
            header("Location: farmer.php?msg=added");
            exit();
        } else {
            $error = "Error: " . $conn->error;
        }
    } else {
        // Keep modal open and show errors
        $show_modal = true;
    }
}

// Handle Delete (Admin and Retailer only) - Customer cannot delete
if (isset($_GET['delete']) && $can_edit_delete) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM farmers WHERE id = $id");
    header("Location: farmer.php?msg=deleted");
    exit();
}

// Handle Status Update (Admin and Retailer only) - Customer cannot update
if (isset($_GET['toggle_status']) && $can_edit_delete) {
    $id = (int)$_GET['toggle_status'];
    $conn->query("UPDATE farmers SET status = CASE WHEN status = 'Active' THEN 'Inactive' ELSE 'Active' END WHERE id = $id");
    header("Location: farmer.php?msg=updated");
    exit();
}

// Fetch farmers
$result = $conn->query("SELECT * FROM farmers ORDER BY id DESC");

// Get statistics
$total_farmers = $result->num_rows;
$active_farmers = 0;
$total_land = 0;
$total_requirement = 0;
$regions = [];

if ($result->num_rows > 0) {
    $result->data_seek(0);
    while($row = $result->fetch_assoc()) {
        if ($row['status'] == 'Active') $active_farmers++;
        $total_land += $row['land_size'];
        $total_requirement += $row['requirement'];
        if (!in_array($row['region'], $regions)) {
            $regions[] = $row['region'];
        }
    }
    $result->data_seek(0); // Reset pointer
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriTrack - National Farmer Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" type="text/css" href="farmer.css">
   <style>
       /* Additional inline error styles */
       .error-message {
           color: #dc3545;
           font-size: 12px;
           margin-top: 4px;
           display: block;
       }
       .error-input {
           border-color: #dc3545 !important;
       }
   </style>
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] == 'true' ? 'dark-mode' : ''; ?>">

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h2>🌱 FSC <span class="role-badge"><?php echo $user_role; ?></span></h2>
        <a href="dashboard.php"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="inventory.php"><i class="fas fa-warehouse"></i> <span>Inventory</span></a>
        <a href="distribution.php"><i class="fas fa-truck"></i> <span>Distribution</span></a>
        <a href="shipments.php"><i class="fas fa-truck-loading"></i> <span>Shipments</span></a>
        <a href="farmer.php" class="active"><i class="fas fa-users"></i> <span>Farmer Registry</span></a>
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
                if ($_GET['msg'] == 'added') echo "Farmer registered successfully!";
                if ($_GET['msg'] == 'deleted') echo "Farmer record deleted successfully!";
                if ($_GET['msg'] == 'updated') echo "Farmer status updated successfully!";
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_farmers); ?></div>
                <div class="stat-label">Total Farmers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($active_farmers); ?></div>
                <div class="stat-label">Active Farmers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_land, 1); ?> Hectare</div>
                <div class="stat-label">Total Land</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_requirement, 0); ?> Qtl</div>
                <div class="stat-label">Total Requirement</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($regions); ?></div>
                <div class="stat-label">Regions Covered</div>
            </div>
        </div>

        <!-- Main Content Container -->
        <div class="content-container">
            <div class="container-header">
                <h2 class="page-title"><i class="fas fa-globe-africa"></i> National Farmer Database</h2>
                <div>
                    <span style="color: var(--gray);">Total Records: <strong style="color: var(--primary);"><?php echo $total_farmers; ?></strong></span>
                    <?php if ($can_add): ?>
                    <button class="btn-add" onclick="openModal()" style="margin-left: 20px;">
                        <i class="fas fa-user-plus"></i> Register New Farmer
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Farmer ID</th>
                        <th>Region</th>
                        <th>Full Name</th>
                        <th>Woreda</th>
                        <th>Kebele</th>
                        <th>Land Size</th>
                        <th>Requirement</th>
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
                                <td><small style="color:#888"><?php echo htmlspecialchars($row['farmer_id']); ?></small></td>
                                <td><span class="region-badge"><?php echo htmlspecialchars($row['region']); ?></span></td>
                                <td style="font-weight:600"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['woreda']); ?></td>
                                <td><?php echo htmlspecialchars($row['kebele']); ?></td>
                                <td><?php echo number_format($row['land_size'], 1); ?> Hectare</td>
                                <td style="color:var(--primary); font-weight:700"><?php echo number_format($row['requirement'], 0); ?> Qtl</td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <?php if ($can_edit_delete): ?>
                                <td>
                                    <a href="farmer.php?toggle_status=<?php echo $row['id']; ?>" class="btn-toggle" onclick="return confirm('Toggle status for this farmer?')">
                                        <i class="fas fa-sync-alt"></i>
                                    </a>
                                    <a href="farmer.php?delete=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Delete this farmer record?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo $can_edit_delete ? '9' : '8'; ?>" style="text-align:center; padding:30px;">No farmers registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($user_role == 'Customer'): ?>
            <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; text-align: center;">
                <i class="fas fa-info-circle"></i> You are viewing the farmer registry. Contact an administrator if you need to register or update farmer information.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Registration Modal (Only shown for Admin and Retailer) -->
    <?php if ($can_add): ?>
    <div id="farmerModal" class="modal-overlay" style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
        <div class="modal">
            <h2><i class="fas fa-user-plus"></i> Register New Farmer</h2>
            <form method="POST" action="farmer.php" id="farmerForm" onsubmit="return validateForm()">
                <div>
                    <label>Region</label>
                    <select name="region" id="region" required>
                        <option value="">-- Choose Region --</option>
                        <option value="AMHARA">Amhara</option>
                        <option value="OROMIA">Oromia</option>
                        <option value="TIGRAY">Tigray</option>
                        <option value="SIDAMA">Sidama</option>
                        <option value="AFAR">Afar</option>
                        <option value="SOMALI">Somali</option>
                        <option value="SNNPR">SNNPR</option>
                        <option value="GAMBELA">Gambela</option>
                        <option value="BENISHANGUL">Benishangul-Gumuz</option>
                    </select>
                </div>

                <div>
                    <label>Full Name </label>
                    <input type="text" name="name" id="fullName" placeholder="e.g. Abebe Balcha" required 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                           onkeyup="validateField(this, 'letters')">
                    <span class="error-message" id="fullNameError"><?php echo $name_error; ?></span>
                </div>

                <div>
                    <label>Woreda </label>
                    <input type="text" name="woreda" id="woreda" placeholder="Woreda name" required
                           value="<?php echo isset($_POST['woreda']) ? htmlspecialchars($_POST['woreda']) : ''; ?>"
                           onkeyup="validateField(this, 'letters')">
                    <span class="error-message" id="woredaError"><?php echo $woreda_error; ?></span>
                </div>

                <div>
                    <label>Kebele </label>
                    <input type="text" name="kebele" id="kebele" placeholder="Kebele name" required
                           value="<?php echo isset($_POST['kebele']) ? htmlspecialchars($_POST['kebele']) : ''; ?>"
                           onkeyup="validateField(this, 'alphanumeric')">
                    <span class="error-message" id="kebeleError"><?php echo $kebele_error; ?></span>
                </div>

                <div>
                    <label>Land Size (Hectares)</label>
                    <input type="number" name="land" id="land" step="0.1" min="0.1" placeholder="e.g. 2.5" required
                           value="<?php echo isset($_POST['land']) ? htmlspecialchars($_POST['land']) : ''; ?>">
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" name="register_farmer" class="btn-add" style="width:100%" id="submitBtn">
                        <i class="fas fa-check-circle"></i> Confirm Registration
                    </button>
                </div>
                <button type="button" class="cancel-btn" onclick="closeModal()" style="width:100%;">
                    Cancel
                </button>
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
        document.getElementById('farmerModal').style.display = 'flex';
        // Reset form and validation styles
        document.getElementById('farmerForm').reset();
        removeValidationStyles();
        clearInlineErrors();
    }

    function closeModal() {
        document.getElementById('farmerModal').style.display = 'none';
    }

    function removeValidationStyles() {
        const inputs = ['fullName', 'woreda', 'kebele'];
        inputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.classList.remove('error-input');
            }
        });
    }

    function clearInlineErrors() {
        const errorSpans = ['fullNameError', 'woredaError', 'kebeleError'];
        errorSpans.forEach(id => {
            const span = document.getElementById(id);
            if (span) span.innerHTML = '';
        });
    }

    // Validate field based on type and update inline error
    function validateField(element, type) {
        const value = element.value;
        let isValid = true;
        let errorMsg = '';
        
        if (type === 'letters') {
            isValid = /^[A-Za-z\s]*$/.test(value);
            if (!isValid && value.length > 0) {
                errorMsg = 'Only letters and spaces allowed';
            }
        } else if (type === 'alphanumeric') {
            isValid = /^[A-Za-z0-9\s]*$/.test(value);
            if (!isValid && value.length > 0) {
                errorMsg = 'Only letters, numbers, and spaces allowed';
            }
        }
        
        // Get corresponding error span
        let errorSpanId = element.id + 'Error';
        let errorSpan = document.getElementById(errorSpanId);
        if (errorSpan) {
            errorSpan.innerHTML = errorMsg;
            errorSpan.style.color = '#dc3545';
        }
        
        if (!isValid && value.length > 0) {
            element.classList.add('error-input');
        } else {
            element.classList.remove('error-input');
        }
        
        return isValid;
    }

    // Form validation with inline errors
    function validateForm() {
        const fullName = document.getElementById('fullName');
        const woreda = document.getElementById('woreda');
        const kebele = document.getElementById('kebele');
        
        let isValid = true;
        
        // Validate Full Name (letters only)
        if (!/^[A-Za-z\s]+$/.test(fullName.value)) {
            document.getElementById('fullNameError').innerHTML = 'Full Name can only contain letters and spaces!';
            fullName.classList.add('error-input');
            isValid = false;
        } else {
            document.getElementById('fullNameError').innerHTML = '';
            fullName.classList.remove('error-input');
        }
        
        // Validate Woreda (letters only)
        if (!/^[A-Za-z\s]+$/.test(woreda.value)) {
            document.getElementById('woredaError').innerHTML = 'Woreda can only contain letters and spaces!';
            woreda.classList.add('error-input');
            isValid = false;
        } else {
            document.getElementById('woredaError').innerHTML = '';
            woreda.classList.remove('error-input');
        }
        
        // Validate Kebele (letters and numbers only)
        if (!/^[A-Za-z0-9\s]+$/.test(kebele.value)) {
            document.getElementById('kebeleError').innerHTML = 'Kebele can only contain letters, numbers, and spaces!';
            kebele.classList.add('error-input');
            isValid = false;
        } else {
            document.getElementById('kebeleError').innerHTML = '';
            kebele.classList.remove('error-input');
        }
        
        // If any error, prevent submission
        if (!isValid) {
            // Focus on first invalid field
            if (!/^[A-Za-z\s]+$/.test(fullName.value)) fullName.focus();
            else if (!/^[A-Za-z\s]+$/.test(woreda.value)) woreda.focus();
            else if (!/^[A-Za-z0-9\s]+$/.test(kebele.value)) kebele.focus();
            return false;
        }
        
        return true;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('farmerModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Load dark mode preference
    if (document.cookie.includes('darkMode=true')) {
        document.body.classList.add('dark-mode');
    }

    // Real-time validation on input
    document.addEventListener('DOMContentLoaded', function() {
        const fullNameInput = document.getElementById('fullName');
        const woredaInput = document.getElementById('woreda');
        const kebeleInput = document.getElementById('kebele');
        
        if (fullNameInput) {
            fullNameInput.addEventListener('input', function() {
                validateField(this, 'letters');
            });
        }
        
        if (woredaInput) {
            woredaInput.addEventListener('input', function() {
                validateField(this, 'letters');
            });
        }
        
        if (kebeleInput) {
            kebeleInput.addEventListener('input', function() {
                validateField(this, 'alphanumeric');
            });
        }
        
        // Keep modal open if errors exist (PHP flag)
        <?php if ($show_modal): ?>
        document.getElementById('farmerModal').style.display = 'flex';
        <?php endif; ?>
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>
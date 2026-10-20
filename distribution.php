<?php
// [file name]: distribution.php
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

// Only Admin and Supplier can access distribution
if ($user_role != 'Admin' && $user_role != 'Supplier') {
    header("Location: dashboard.php");
    exit();
}

// Validation function for region and type
function validateText($input) {
    // Allow only letters, spaces, and hyphens
    return preg_match('/^[a-zA-Z\s\-]+$/', $input);
}

// Handle Add Distribution with validation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_distribution'])) {
    $errors = [];
    
    // Sanitize inputs
    $region = sanitize($_POST['region']);
    $type = sanitize($_POST['type']);
    $quantity = $_POST['quantity'];
    $date = $_POST['date'];
    $status = sanitize($_POST['status']);
    
    // Validate Region - no special characters
    if (empty($region)) {
        $errors[] = "Region is required.";
    } elseif (!validateText($region)) {
        $errors[] = "Region should contain only letters, spaces, and hyphens. No special characters allowed.";
    }
    
    // Validate Fertilizer Type
    $valid_types = ['DAP', 'Urea', 'NPS', 'NPSB'];
    if (empty($type)) {
        $errors[] = "Fertilizer type is required.";
    } elseif (!in_array($type, $valid_types)) {
        $errors[] = "Invalid fertilizer type selected.";
    }
    
    // Validate Quantity - must be positive number and not minimized
    if (empty($quantity)) {
        $errors[] = "Quantity is required.";
    } elseif (!is_numeric($quantity)) {
        $errors[] = "Quantity must be a number.";
    } elseif ($quantity <= 0) {
        $errors[] = "Quantity must be a positive number greater than zero.";
    } elseif ($quantity < 1) {
        $errors[] = "Quantity cannot be minimized. Minimum quantity is 1 quintal.";
    } elseif (floor($quantity) != $quantity) {
        $errors[] = "Quantity must be a whole number (no decimals).";
    }
    
    // Validate Date
    if (empty($date)) {
        $errors[] = "Distribution date is required.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = "Invalid date format.";
    }
    
    // Validate Status
    $valid_statuses = ['Pending', 'Delivered', 'Leakage Alert'];
    if (empty($status)) {
        $errors[] = "Status is required.";
    } elseif (!in_array($status, $valid_statuses)) {
        $errors[] = "Invalid status selected.";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        $quantity = (int)$quantity;
        $sql = "INSERT INTO distributions (region, fertilizer_type, quantity, distribution_date, status) 
                VALUES ('$region', '$type', $quantity, '$date', '$status')";
        
        if ($conn->query($sql)) {
            header("Location: distribution.php?msg=added");
            exit();
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM distributions WHERE id = $id");
    header("Location: distribution.php?msg=deleted");
    exit();
}

// Fetch distributions
$result = $conn->query("SELECT * FROM distributions ORDER BY distribution_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution - Fertilizer Supply Chain</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="distribution.css">
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] == 'true' ? 'dark-mode' : ''; ?>">

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h2>🌱 FSC <span class="role-badge"><?php echo $user_role; ?></span></h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="inventory.php"><i class="fas fa-warehouse"></i> <span>Inventory</span></a>
        <a href="distribution.php" class="active"><i class="fas fa-share-alt"></i> <span>Distribution</span></a>
        <a href="shipments.php"><i class="fas fa-truck"></i> <span>Shipments</span></a>
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
                if ($_GET['msg'] == 'added') echo "Distribution record added successfully!";
                if ($_GET['msg'] == 'deleted') echo "Distribution record deleted successfully!";
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Please fix the following errors:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <h2>Fertilizer Distribution Tracking</h2>

        <!-- Add Distribution Form -->
        <div class="form-container">
            <h3>Record New Distribution</h3>
            <form method="POST" action="distribution.php" id="distributionForm">
                <div class="form-grid">
                    <div>
                        <input type="text" name="region" placeholder="Target Region/Zone" required
                               value="<?php echo isset($_POST['region']) ? htmlspecialchars($_POST['region']) : ''; ?>"
                               pattern="[A-Za-z\s\-]+" 
                               title="Only letters, spaces, and hyphens are allowed">
                        <small class="error-message" id="regionError"></small>
                    </div>
                    <div>
                        <select name="type" required>
                            <option value="">Select Fertilizer</option>
                            <option value="DAP" <?php echo (isset($_POST['type']) && $_POST['type'] == 'DAP') ? 'selected' : ''; ?>>DAP Fertilizer</option>
                            <option value="Urea" <?php echo (isset($_POST['type']) && $_POST['type'] == 'Urea') ? 'selected' : ''; ?>>Urea Fertilizer</option>
                            <option value="NPS" <?php echo (isset($_POST['type']) && $_POST['type'] == 'NPS') ? 'selected' : ''; ?>>NPS Fertilizer</option>
                            <option value="NPSB" <?php echo (isset($_POST['type']) && $_POST['type'] == 'NPSB') ? 'selected' : ''; ?>>NPSB Fertilizer</option>
                        </select>
                    </div>
                    <div>
                        <input type="number" name="quantity" placeholder="Quantity (Quintals)" 
                               min="1" step="1" required
                               value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : ''; ?>"
                               oninput="validateQuantity(this)">
                        <small class="error-message" id="quantityError"></small>
                    </div>
                    <div>
                        <input type="date" name="date" required value="<?php echo isset($_POST['date']) ? $_POST['date'] : date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <select name="status">
                            <option value="Pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Delivered" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Leakage Alert" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Leakage Alert') ? 'selected' : ''; ?>>Leakage Alert</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_distribution" class="btn-submit" id="submitBtn">
                    <i class="fas fa-save"></i> Save Distribution Data
                </button>
            </form>
        </div>

        <!-- Distributions Table -->
        <table>
            <thead>
                <tr>
                    <th>Target Region</th>
                    <th>Type</th>
                    <th>Quantity (Qtl)</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['region']); ?></strong></td>
                        <td><?php echo $row['fertilizer_type']; ?></td>
                        <td><?php echo number_format($row['quantity']); ?></td>
                        <td><?php echo $row['distribution_date']; ?></td>
                        <td>
                            <span class="status <?php echo str_replace(' ', '', $row['status']); ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="distribution.php?delete=<?php echo $row['id']; ?>" 
                               class="btn-delete" 
                               onclick="return confirm('Are you sure you want to delete this record?')">
                               <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px;">No distribution records found.</td></tr>
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

    // Client-side quantity validation
    function validateQuantity(input) {
        const value = input.value;
        const errorElement = document.getElementById('quantityError');
        const submitBtn = document.getElementById('submitBtn');
        
        if (value === '') {
            errorElement.textContent = 'Quantity is required';
            input.classList.add('input-error');
            submitBtn.disabled = true;
        } else if (isNaN(value)) {
            errorElement.textContent = 'Quantity must be a number';
            input.classList.add('input-error');
            submitBtn.disabled = true;
        } else if (value <= 0) {
            errorElement.textContent = 'Quantity must be greater than zero';
            input.classList.add('input-error');
            submitBtn.disabled = true;
        } else if (value < 1) {
            errorElement.textContent = 'Quantity cannot be minimized. Minimum is 1 quintal';
            input.classList.add('input-error');
            submitBtn.disabled = true;
        } else if (!Number.isInteger(Number(value))) {
            errorElement.textContent = 'Quantity must be a whole number (no decimals)';
            input.classList.add('input-error');
            submitBtn.disabled = true;
        } else {
            errorElement.textContent = '';
            input.classList.remove('input-error');
            submitBtn.disabled = false;
        }
    }

    // Region validation
    document.querySelector('input[name="region"]').addEventListener('input', function(e) {
        const value = e.target.value;
        const errorElement = document.getElementById('regionError');
        const regionRegex = /^[A-Za-z\s\-]*$/;
        
        if (value && !regionRegex.test(value)) {
            errorElement.textContent = 'Only letters, spaces, and hyphens are allowed';
            e.target.classList.add('input-error');
        } else {
            errorElement.textContent = '';
            e.target.classList.remove('input-error');
        }
    });

    // Form submission validation
    document.getElementById('distributionForm').addEventListener('submit', function(e) {
        const quantity = document.querySelector('input[name="quantity"]').value;
        const region = document.querySelector('input[name="region"]').value;
        const regionRegex = /^[A-Za-z\s\-]+$/;
        let hasError = false;
        
        // Final quantity validation
        if (!quantity || quantity <= 0 || quantity < 1 || !Number.isInteger(Number(quantity))) {
            e.preventDefault();
            alert('Please enter a valid quantity (positive whole number, minimum 1 quintal)');
            hasError = true;
        }
        
        // Final region validation
        if (region && !regionRegex.test(region)) {
            e.preventDefault();
            alert('Region should contain only letters, spaces, and hyphens. No special characters allowed.');
            hasError = true;
        }
        
        if (hasError) {
            return false;
        }
    });

    // Initialize quantity validation on page load
    window.addEventListener('load', function() {
        const quantityInput = document.querySelector('input[name="quantity"]');
        if (quantityInput && quantityInput.value) {
            validateQuantity(quantityInput);
        }
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>
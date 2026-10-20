<?php
// [file name]: dashboard.php
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

// Only Admin can access this dashboard
if ($user_role !== 'Admin') {
    // Redirect to appropriate page based on role
    switch($user_role) {
        case 'Supplier':
            header("Location: inventory.php");
            break;
        case 'Retailer':
            header("Location: farmer.php");
            break;
        default:
            header("Location: index.php");
    }
    exit();
}

// Get dashboard statistics from database
$stats = [];

// Total farmers
$result = $conn->query("SELECT COUNT(*) as count FROM farmers");
$stats['total_farmers'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total inventory
$result = $conn->query("SELECT SUM(quantity) as total FROM inventory");
$stats['total_inventory'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;

// Total distributions this month
$result = $conn->query("SELECT SUM(quantity) as total FROM distributions WHERE MONTH(distribution_date) = MONTH(CURDATE())");
$stats['monthly_distributions'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;

// Active shipments
$result = $conn->query("SELECT COUNT(*) as count FROM shipments WHERE status = 'In-Transit'");
$stats['active_shipments'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Recent distributions
$recent_distributions = $conn->query("SELECT * FROM distributions ORDER BY distribution_date DESC LIMIT 5");

// Recent shipments
$recent_shipments = $conn->query("SELECT * FROM shipments ORDER BY created_at DESC LIMIT 5");

// Chart data for last 6 months
$chart_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    
    $result = $conn->query("SELECT SUM(quantity) as total FROM distributions WHERE DATE_FORMAT(distribution_date, '%Y-%m') = '$month'");
    $total = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
    
    $chart_data[] = [
        'month' => $month_name,
        'total' => $total
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fertilizer Supply Chain (Admin)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" type="text/css" href="dashboard.css">
</head>

<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>🌱 FSC Admin <span class="role-badge"><?php echo $user_role; ?></span></h2>
    <a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
    <a href="inventory.php"><i class="fas fa-warehouse"></i> <span>Inventory</span></a>
    <a href="distribution.php"><i class="fas fa-truck"></i> <span>Distribution</span></a>
    <a href="shipments.php"><i class="fas fa-truck-loading"></i> <span>Shipments</span></a>
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
            <span id="userDisplay"><?php echo $full_name; ?></span>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
            <button onclick="toggleMode()">🌙 Dark Mode</button>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="card-container">
        <div class="card">
            <h3>Total Stock</h3>
            <p><?php echo number_format($stats['total_inventory'] ?? 0, 2); ?> Qtl</p>
            <span>Updated Today</span>
        </div>
        <div class="card">
            <h3>Distributed</h3>
            <p><?php echo number_format($stats['monthly_distributions'] ?? 0, 2); ?> Qtl</p>
            <span>This Month</span>
        </div>
        <div class="card">
            <h3>Registered Farmers</h3>
            <p><?php echo number_format($stats['total_farmers'] ?? 0); ?></p>
            <span>Active Farmers</span>
        </div>
        <div class="card">
            <h3>Active Shipments</h3>
            <p><?php echo $stats['active_shipments'] ?? 0; ?></p>
            <span>In Transit</span>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-section">
        <canvas id="distributionChart"></canvas>
        <canvas id="stockChart"></canvas>
    </div>

    <!-- Recent Activities Table -->
    <div class="activity-section">
        <h3>Recent Distribution Activities</h3>
        <table>
            <thead>
                <tr>
                    <th>Region</th>
                    <th>Fertilizer</th>
                    <th>Quantity</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_distributions && $recent_distributions->num_rows > 0): ?>
                    <?php while($row = $recent_distributions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['region']); ?></td>
                        <td><?php echo htmlspecialchars($row['fertilizer_type']); ?></td>
                        <td><?php echo number_format($row['quantity']); ?> Qtl</td>
                        <td><?php echo date('Y-m-d', strtotime($row['distribution_date'])); ?></td>
                        <td><span class="status <?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;">No recent distributions</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Shipments -->
    <div class="activity-section">
        <h3>Recent Shipments</h3>
        <table>
            <thead>
                <tr>
                    <th>Truck Plate</th>
                    <th>Driver</th>
                    <th>Destination</th>
                    <th>ETA</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_shipments && $recent_shipments->num_rows > 0): ?>
                    <?php while($row = $recent_shipments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['truck_plate']); ?></td>
                        <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['region']); ?></td>
                        <td><?php echo htmlspecialchars($row['eta']); ?></td>
                        <td><span class="status <?php echo strtolower(str_replace('-', '', $row['status'])); ?>"><?php echo $row['status']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;">No recent shipments</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Sidebar toggle
function toggleSidebar(){
    document.getElementById("sidebar").classList.toggle("collapsed");
}

// Dark mode toggle
function toggleMode(){
    document.body.classList.toggle("dark-mode");
}

// Charts
const ctx1 = document.getElementById('distributionChart').getContext('2d');
new Chart(ctx1,{
    type:'bar',
    data:{
        labels:<?php echo json_encode(array_column($chart_data, 'month')); ?>,
        datasets:[{
            label:'Distribution (Qtl)',
            data:<?php echo json_encode(array_column($chart_data, 'total')); ?>,
            backgroundColor:['#4caf50','#81c784','#388e3c','#66bb6a','#43a047','#2e7d32']
        }]
    },
    options:{responsive:true}
});

const ctx2 = document.getElementById('stockChart').getContext('2d');
new Chart(ctx2,{
    type:'pie',
    data:{
        labels:['DAP','Urea'],
        datasets:[{
            data:[3000, 2000], // This should come from database in real implementation
            backgroundColor:['#4caf50','#1b5e20']
        }]
    },
    options:{responsive:true}
});
</script>

</body>
</html>
<?php $conn->close(); ?>
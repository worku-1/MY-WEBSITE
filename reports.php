<?php
// [file name]: reports.php
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

// Only Admin can access reports
if ($user_role != 'Admin') {
    header("Location: dashboard.php");
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');
$region_filter = isset($_GET['region']) ? sanitize($_GET['region']) : '';
$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'summary';
$active_tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'summary';

// Validate dates
if ($start_date > $end_date) {
    $error = "Start date cannot be after end date!";
    $start_date = $end_date;
}

// 1. Regional Distribution Summary - FIXED
$regional_query = "SELECT 
                    COALESCE(region, 'Unknown') as region,
                    COUNT(*) as total_distributions,
                    COALESCE(SUM(quantity), 0) as total_distributed,
                    COALESCE(SUM(CASE WHEN fertilizer_type = 'DAP' THEN quantity ELSE 0 END), 0) as dap_distributed,
                    COALESCE(SUM(CASE WHEN fertilizer_type = 'Urea' THEN quantity ELSE 0 END), 0) as urea_distributed,
                    COUNT(DISTINCT farmer_id) as farmers_reached,
                    SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as completed
                   FROM distributions
                   WHERE distribution_date BETWEEN '$start_date' AND '$end_date'";
                   
if (!empty($region_filter)) {
    $regional_query .= " AND region = '$region_filter'";
}

$regional_query .= " GROUP BY region ORDER BY total_distributed DESC";
$regional_result = $conn->query($regional_query);

if (!$regional_result) {
    $regional_result = $conn->query("SELECT 'No Data' as region, 0 as total_distributions, 0 as total_distributed, 0 as dap_distributed, 0 as urea_distributed, 0 as farmers_reached, 0 as completed");
}

// 2. Monthly Performance (Last 12 months)
$monthly_query = "SELECT 
                    DATE_FORMAT(distribution_date, '%Y-%m') as month,
                    DATE_FORMAT(distribution_date, '%M %Y') as month_name,
                    COALESCE(SUM(quantity), 0) as total_distributed,
                    COALESCE(SUM(CASE WHEN fertilizer_type = 'DAP' THEN quantity ELSE 0 END), 0) as dap_distributed,
                    COALESCE(SUM(CASE WHEN fertilizer_type = 'Urea' THEN quantity ELSE 0 END), 0) as urea_distributed,
                    COUNT(DISTINCT farmer_id) as unique_farmers,
                    COUNT(*) as distribution_count
                  FROM distributions
                  WHERE distribution_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(distribution_date, '%Y-%m')
                  ORDER BY month DESC";
$monthly_result = $conn->query($monthly_query);

if (!$monthly_result) {
    $monthly_result = $conn->query("SELECT 'No Data' as month_name, 0 as total_distributed, 0 as dap_distributed, 0 as urea_distributed, 0 as unique_farmers, 0 as distribution_count");
}

// 3. Inventory Status
$inventory_query = "SELECT 
                    fertilizer_type,
                    COUNT(*) as batch_count,
                    COALESCE(SUM(quantity), 0) as total_quantity,
                    COALESCE(AVG(quantity), 0) as avg_batch_size,
                    COUNT(DISTINCT warehouse) as warehouse_count
                   FROM inventory
                   GROUP BY fertilizer_type";
$inventory_report = $conn->query($inventory_query);

if (!$inventory_report) {
    $inventory_report = $conn->query("SELECT 'No Data' as fertilizer_type, 0 as batch_count, 0 as total_quantity, 0 as avg_batch_size, 0 as warehouse_count");
}

// 4. Warehouse Status
$warehouse_query = "SELECT 
                    warehouse,
                    COUNT(*) as batch_count,
                    COALESCE(SUM(quantity), 0) as total_stock,
                    COUNT(DISTINCT fertilizer_type) as types_available
                   FROM inventory
                   GROUP BY warehouse
                   ORDER BY total_stock DESC";
$warehouse_result = $conn->query($warehouse_query);

if (!$warehouse_result) {
    $warehouse_result = $conn->query("SELECT 'No Data' as warehouse, 0 as batch_count, 0 as total_stock, 0 as types_available");
}

// 5. Farmer Statistics
$farmer_stats_query = "SELECT 
                        COUNT(*) as total_farmers,
                        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_farmers,
                        COALESCE(SUM(land_size), 0) as total_land,
                        COALESCE(AVG(land_size), 0) as avg_land_size,
                        COALESCE(SUM(requirement), 0) as total_requirement,
                        COUNT(DISTINCT region) as regions_covered
                       FROM farmers";
$farmer_stats_result = $conn->query($farmer_stats_query);

if (!$farmer_stats_result) {
    $farmer_stats_result = $conn->query("SELECT 0 as total_farmers, 0 as active_farmers, 0 as total_land, 0 as avg_land_size, 0 as total_requirement, 0 as regions_covered");
}
$farmer_stats = $farmer_stats_result->fetch_assoc();

// 6. Farmer Distribution by Region
$farmer_region_query = "SELECT 
                        region,
                        COUNT(*) as farmer_count,
                        COALESCE(SUM(land_size), 0) as total_land,
                        COALESCE(SUM(requirement), 0) as total_requirement
                       FROM farmers
                       WHERE status = 'Active'
                       GROUP BY region
                       ORDER BY farmer_count DESC";
$farmer_region_result = $conn->query($farmer_region_query);

if (!$farmer_region_result) {
    $farmer_region_result = $conn->query("SELECT 'No Data' as region, 0 as farmer_count, 0 as total_land, 0 as total_requirement");
}

// 7. Shipment Statistics - FIXED (removed 'delayed' as column name, using backticks for reserved words)
$shipment_stats_query = "SELECT 
                        COUNT(*) as total_shipments,
                        SUM(CASE WHEN status = 'In-Transit' THEN 1 ELSE 0 END) as in_transit,
                        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered,
                        SUM(CASE WHEN status = 'Delayed' THEN 1 ELSE 0 END) as delayed_count
                       FROM shipments";
$shipment_stats_result = $conn->query($shipment_stats_query);

if (!$shipment_stats_result) {
    $shipment_stats_result = $conn->query("SELECT 0 as total_shipments, 0 as in_transit, 0 as delivered, 0 as delayed_count");
}
$shipment_stats = $shipment_stats_result->fetch_assoc();

// 8. Get distinct regions for filter
$regions_result = $conn->query("SELECT DISTINCT region FROM distributions UNION SELECT DISTINCT region FROM farmers ORDER BY region");

if (!$regions_result) {
    $regions_result = $conn->query("SELECT 'No Regions' as region");
}

// Calculate totals for summary
$total_distributed = 0;
$total_inventory = 0;
$regional_data = [];
$monthly_data = [];

if ($regional_result && $regional_result->num_rows > 0) {
    while($row = $regional_result->fetch_assoc()) {
        $total_distributed += $row['total_distributed'];
        $regional_data[] = $row;
    }
    $regional_result->data_seek(0);
}

if ($monthly_result && $monthly_result->num_rows > 0) {
    while($row = $monthly_result->fetch_assoc()) {
        $monthly_data[] = $row;
    }
    $monthly_result->data_seek(0);
}

if ($inventory_report && $inventory_report->num_rows > 0) {
    while($row = $inventory_report->fetch_assoc()) {
        $total_inventory += $row['total_quantity'];
    }
    $inventory_report->data_seek(0);
}

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="fertilizer_report_' . date('Y-m-d') . '.xls"');
    
    echo "<html>";
    echo "<body>";
    echo "<table border='1'>";
    
    echo "<tr><th colspan='10'>Fertilizer Supply Chain Report - " . date('Y-m-d H:i:s') . "</th></tr>";
    echo "<tr><th colspan='10'>Period: $start_date to $end_date</th></tr>";
    echo "<tr><th colspan='10'>Region: " . ($region_filter ?: 'All') . "</th></tr>";
    echo "<tr><td colspan='10'>&nbsp;</td></tr>";
    
    // Regional Summary
    echo "<tr><th colspan='10'>Regional Distribution Summary</th></tr>";
    echo "<tr><th>Region</th><th>Total Distributions</th><th>Total Quantity</th><th>DAP</th><th>Urea</th><th>Farmers Reached</th><th>Completed</th></tr>";
    
    foreach ($regional_data as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['region']) . "</td>";
        echo "<td>" . $row['total_distributions'] . "</td>";
        echo "<td>" . number_format($row['total_distributed'], 2) . "</td>";
        echo "<td>" . number_format($row['dap_distributed'], 2) . "</td>";
        echo "<td>" . number_format($row['urea_distributed'], 2) . "</td>";
        echo "<td>" . $row['farmers_reached'] . "</td>";
        echo "<td>" . $row['completed'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</body>";
    echo "</html>";
    exit();
}

// Handle PDF Export using HTML2PDF method
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="fertilizer_report_' . date('Y-m-d') . '.pdf"');
    
    // Create PDF content using HTML
    $pdf_content = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #1b5e20; text-align: center; }
            h2 { color: #1b5e20; border-bottom: 2px solid #c8e6c9; padding-bottom: 5px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            th { background: #e8f5e9; color: #1b5e20; padding: 8px; text-align: left; border: 1px solid #c8e6c9; }
            td { padding: 8px; border: 1px solid #c8e6c9; }
            .header-info { text-align: center; margin-bottom: 20px; }
            .stat-box { display: inline-block; width: 23%; margin: 1%; padding: 10px; background: #f5f5f5; border-radius: 5px; text-align: center; }
            .stat-value { font-size: 18px; font-weight: bold; color: #1b5e20; }
        </style>
    </head>
    <body>
        <h1>Fertilizer Supply Chain Report</h1>
        <div class="header-info">
            <p><strong>Period:</strong> ' . $start_date . ' to ' . $end_date . '</p>
            <p><strong>Region:</strong> ' . ($region_filter ?: 'All') . '</p>
            <p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>
        </div>';
    
    // Summary Statistics
    $pdf_content .= '
        <h2>Summary Statistics</h2>
        <div style="text-align: center;">
            <div class="stat-box">
                <div class="stat-value">' . number_format($farmer_stats['total_farmers'] ?? 0) . '</div>
                <div>Total Farmers</div>
                <div>(Active: ' . number_format($farmer_stats['active_farmers'] ?? 0) . ')</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">' . number_format($total_inventory, 2) . ' Qtl</div>
                <div>Total Stock</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">' . number_format($total_distributed, 2) . ' Qtl</div>
                <div>Distributed</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">' . number_format($shipment_stats['total_shipments'] ?? 0) . '</div>
                <div>Total Shipments</div>
                <div>(In Transit: ' . number_format($shipment_stats['in_transit'] ?? 0) . ')</div>
            </div>
        </div>';
    
    // Shipment Summary
    $pdf_content .= '
        <h2>Shipment Summary</h2>
        <table>
            <tr>
                <th>Total Shipments</th>
                <th>In Transit</th>
                <th>Delivered</th>
                <th>Delayed</th>
            </tr>
            <tr>
                <td>' . number_format($shipment_stats['total_shipments'] ?? 0) . '</td>
                <td>' . number_format($shipment_stats['in_transit'] ?? 0) . '</td>
                <td>' . number_format($shipment_stats['delivered'] ?? 0) . '</td>
                <td>' . number_format($shipment_stats['delayed_count'] ?? 0) . '</td>
            </tr>
        </table>';
    
    // Regional Distribution Summary
    $pdf_content .= '
        <h2>Regional Distribution Summary</h2>
        <table>
            <tr>
                <th>Region</th>
                <th>Distributions</th>
                <th>Total Quantity</th>
                <th>DAP</th>
                <th>Urea</th>
                <th>Farmers</th>
                <th>Completed</th>
            </tr>';
    
    foreach ($regional_data as $row) {
        $pdf_content .= '
            <tr>
                <td>' . htmlspecialchars($row['region']) . '</td>
                <td>' . $row['total_distributions'] . '</td>
                <td>' . number_format($row['total_distributed'], 2) . '</td>
                <td>' . number_format($row['dap_distributed'], 2) . '</td>
                <td>' . number_format($row['urea_distributed'], 2) . '</td>
                <td>' . $row['farmers_reached'] . '</td>
                <td>' . $row['completed'] . '</td>
            </tr>';
    }
    
    $pdf_content .= '</table>';
    
    // Inventory Status
    $pdf_content .= '
        <h2>Inventory Status</h2>
        <table>
            <tr>
                <th>Fertilizer Type</th>
                <th>Batches</th>
                <th>Total Stock</th>
                <th>Avg Batch Size</th>
                <th>Warehouses</th>
            </tr>';
    
    if ($inventory_report && $inventory_report->num_rows > 0) {
        $inventory_report->data_seek(0);
        while($row = $inventory_report->fetch_assoc()) {
            $pdf_content .= '
                <tr>
                    <td>' . htmlspecialchars($row['fertilizer_type']) . '</td>
                    <td>' . $row['batch_count'] . '</td>
                    <td>' . number_format($row['total_quantity'], 2) . ' Qtl</td>
                    <td>' . number_format($row['avg_batch_size'], 2) . ' Qtl</td>
                    <td>' . $row['warehouse_count'] . '</td>
                </tr>';
        }
    }
    
    $pdf_content .= '</table>';
    
    // Farmers by Region
    $pdf_content .= '
        <h2>Farmers by Region</h2>
        <table>
            <tr>
                <th>Region</th>
                <th>Farmers</th>
                <th>Total Land (ha)</th>
                <th>Total Requirement (Qtl)</th>
            </tr>';
    
    if ($farmer_region_result && $farmer_region_result->num_rows > 0) {
        $farmer_region_result->data_seek(0);
        while($row = $farmer_region_result->fetch_assoc()) {
            $pdf_content .= '
                <tr>
                    <td>' . htmlspecialchars($row['region']) . '</td>
                    <td>' . number_format($row['farmer_count']) . '</td>
                    <td>' . number_format($row['total_land'], 2) . '</td>
                    <td>' . number_format($row['total_requirement'], 2) . '</td>
                </tr>';
        }
    }
    
    $pdf_content .= '</table>';
    
    $pdf_content .= '
    </body>
    </html>';
    
    // Use mPDF if available, otherwise output HTML for browser printing
    if (class_exists('mPDF')) {
        $mpdf = new mPDF();
        $mpdf->WriteHTML($pdf_content);
        $mpdf->Output('fertilizer_report_' . date('Y-m-d') . '.pdf', 'D');
    } else {
        // Fallback: Output HTML that can be saved as PDF via browser print
        echo $pdf_content;
        echo '<script>window.print();</script>';
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Fertilizer Supply Chain (Admin Only)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   <link rel="stylesheet" type="text/css" href="reports.css">
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] == 'true' ? 'dark-mode' : ''; ?>">

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h2>🌱 FSC <span class="role-badge"><?php echo $user_role; ?></span></h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="inventory.php"><i class="fas fa-warehouse"></i> <span>Inventory</span></a>
        <a href="distribution.php"><i class="fas fa-truck"></i> <span>Distribution</span></a>
        <a href="shipments.php"><i class="fas fa-shipping-fast"></i> <span>Shipments</span></a>
        <a href="farmer.php"><i class="fas fa-users"></i> <span>Farmer Registry</span></a>
        <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> <span>Reports</span></a>
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <i class="fas fa-chart-bar"></i>
                <h1>Reports & Analytics</h1>
            </div>
            <div class="user-info">
                <span class="user-badge"><i class="fas fa-user"></i> <?php echo $full_name; ?> (Admin)</span>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <button onclick="toggleMode()" style="background:rgba(0,0,0,0.1); border:none; padding:8px 15px; border-radius:6px; cursor:pointer;">
                    <i class="fas fa-moon"></i> Dark Mode
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="reports.php" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%; align-items: flex-end;">
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-map-marker-alt"></i> Region</label>
                    <select name="region">
                        <option value="">All Regions</option>
                        <?php if ($regions_result && $regions_result->num_rows > 0): ?>
                            <?php while($region = $regions_result->fetch_assoc()): ?>
                                <?php if (!empty($region['region'])): ?>
                                <option value="<?php echo htmlspecialchars($region['region']); ?>" <?php echo $region_filter == $region['region'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($region['region']); ?>
                                </option>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-chart-line"></i> Report Type</label>
                    <select name="report_type">
                        <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                        <option value="distribution" <?php echo $report_type == 'distribution' ? 'selected' : ''; ?>>Distribution Report</option>
                        <option value="inventory" <?php echo $report_type == 'inventory' ? 'selected' : ''; ?>>Inventory Report</option>
                        <option value="farmers" <?php echo $report_type == 'farmers' ? 'selected' : ''; ?>>Farmers Report</option>
                    </select>
                </div>
                <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                <button type="submit" class="btn"><i class="fas fa-filter"></i> Apply</button>
                <a href="reports.php" class="btn btn-sm" style="background: #666;"><i class="fas fa-undo"></i> Reset</a>
            </form>
        </div>

        <!-- Export Buttons -->
        <div class="export-buttons">
            <a href="?export=excel&<?php echo http_build_query(array_merge($_GET, ['tab' => $active_tab])); ?>" class="btn btn-excel">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
            <a href="?export=pdf&<?php echo http_build_query(array_merge($_GET, ['tab' => $active_tab])); ?>" class="btn btn-pdf">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </a>
            <button onclick="window.print()" class="btn btn-sm" style="background: #0288d1;">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>

        <!-- Display Error -->
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Report Tabs -->
        <div class="report-tabs">
            <div class="report-tab <?php echo $active_tab == 'summary' ? 'active' : ''; ?>" onclick="location.href='?tab=summary&<?php echo http_build_query(array_merge($_GET, ['tab' => 'summary'])); ?>'">
                <i class="fas fa-chart-pie"></i> Summary
            </div>
            <div class="report-tab <?php echo $active_tab == 'distribution' ? 'active' : ''; ?>" onclick="location.href='?tab=distribution&<?php echo http_build_query(array_merge($_GET, ['tab' => 'distribution'])); ?>'">
                <i class="fas fa-truck"></i> Distribution
            </div>
            <div class="report-tab <?php echo $active_tab == 'inventory' ? 'active' : ''; ?>" onclick="location.href='?tab=inventory&<?php echo http_build_query(array_merge($_GET, ['tab' => 'inventory'])); ?>'">
                <i class="fas fa-warehouse"></i> Inventory
            </div>
            <div class="report-tab <?php echo $active_tab == 'farmers' ? 'active' : ''; ?>" onclick="location.href='?tab=farmers&<?php echo http_build_query(array_merge($_GET, ['tab' => 'farmers'])); ?>'">
                <i class="fas fa-users"></i> Farmers
            </div>
        </div>

        <!-- Summary Statistics Cards (Visible in all tabs) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Farmers</div>
                <div class="stat-value"><?php echo number_format($farmer_stats['total_farmers'] ?? 0); ?></div>
                <div class="stat-label">Active: <?php echo number_format($farmer_stats['active_farmers'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Stock</div>
                <div class="stat-value"><?php echo number_format($total_inventory, 2); ?> Qtl</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Distributed</div>
                <div class="stat-value"><?php echo number_format($total_distributed, 2); ?> Qtl</div>
                <div class="stat-label">Selected Period</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Shipments</div>
                <div class="stat-value"><?php echo number_format($shipment_stats['total_shipments'] ?? 0); ?></div>
                <div class="stat-label">In Transit: <?php echo number_format($shipment_stats['in_transit'] ?? 0); ?></div>
            </div>
        </div>

        <!-- Charts Section (Visible only in Summary tab) -->
        <div class="charts-grid <?php echo $active_tab != 'summary' ? 'tab-content' : ''; ?>" id="summary-charts">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Distribution by Region</h3>
                <canvas id="regionChart"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Monthly Distribution Trend</h3>
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- Shipment Summary (Visible in Summary tab) -->
        <div class="report-card <?php echo $active_tab != 'summary' ? 'tab-content' : ''; ?>" id="summary-shipment">
            <h2><i class="fas fa-shipping-fast"></i> Shipment Summary</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div style="text-align: center;">
                    <div style="font-size: 2rem; color: var(--primary);"><?php echo number_format($shipment_stats['total_shipments'] ?? 0); ?></div>
                    <div>Total Shipments</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; color: #ef6c00;"><?php echo number_format($shipment_stats['in_transit'] ?? 0); ?></div>
                    <div>In Transit</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; color: #2e7d32;"><?php echo number_format($shipment_stats['delivered'] ?? 0); ?></div>
                    <div>Delivered</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; color: #c62828;"><?php echo number_format($shipment_stats['delayed_count'] ?? 0); ?></div>
                    <div>Delayed</div>
                </div>
            </div>
        </div>

        <!-- Regional Distribution Summary (Visible in Summary and Distribution tabs) -->
        <div class="report-card <?php echo ($active_tab != 'summary' && $active_tab != 'distribution') ? 'tab-content' : ''; ?>" id="distribution-regional">
            <h2><i class="fas fa-map-marked-alt"></i> Regional Distribution Summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>Region</th>
                        <th>Distributions</th>
                        <th>Total Quantity</th>
                        <th>DAP</th>
                        <th>Urea</th>
                        <th>Farmers</th>
                        <th>Completed</th>
                        <th>Success Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($regional_data)): ?>
                        <?php foreach($regional_data as $row): 
                            $success_rate = $row['total_distributions'] > 0 ? round(($row['completed'] / $row['total_distributions']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['region']); ?></strong></td>
                            <td><?php echo $row['total_distributions']; ?></td>
                            <td><?php echo number_format($row['total_distributed'], 2); ?></td>
                            <td><?php echo number_format($row['dap_distributed'], 2); ?></td>
                            <td><?php echo number_format($row['urea_distributed'], 2); ?></td>
                            <td><?php echo $row['farmers_reached']; ?></td>
                            <td><?php echo $row['completed']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $success_rate >= 80 ? 'success' : ($success_rate >= 50 ? 'warning' : 'danger'); ?>">
                                    <?php echo $success_rate; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Monthly Performance (Visible in Distribution tab) -->
        <div class="report-card <?php echo $active_tab != 'distribution' ? 'tab-content' : ''; ?>" id="distribution-monthly">
            <h2><i class="fas fa-calendar-alt"></i> Monthly Performance</h2>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Distributions</th>
                        <th>Total Quantity</th>
                        <th>DAP</th>
                        <th>Urea</th>
                        <th>Unique Farmers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($monthly_data)): ?>
                        <?php foreach($monthly_data as $row): ?>
                        <tr>
                            <td><strong><?php echo $row['month_name']; ?></strong></td>
                            <td><?php echo $row['distribution_count']; ?></td>
                            <td><?php echo number_format($row['total_distributed'], 2); ?></td>
                            <td><?php echo number_format($row['dap_distributed'], 2); ?></td>
                            <td><?php echo number_format($row['urea_distributed'], 2); ?></td>
                            <td><?php echo $row['unique_farmers']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No monthly data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Inventory Status (Visible in Summary and Inventory tabs) -->
        <div class="report-card <?php echo ($active_tab != 'summary' && $active_tab != 'inventory') ? 'tab-content' : ''; ?>" id="inventory-status">
            <h2><i class="fas fa-boxes"></i> Inventory Status</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fertilizer Type</th>
                        <th>Batches</th>
                        <th>Total Stock</th>
                        <th>Avg Batch Size</th>
                        <th>Warehouses</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($inventory_report && $inventory_report->num_rows > 0): ?>
                        <?php $inventory_report->data_seek(0); while($row = $inventory_report->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['fertilizer_type']); ?></strong></td>
                            <td><?php echo $row['batch_count']; ?></td>
                            <td><?php echo number_format($row['total_quantity'], 2); ?> Qtl</td>
                            <td><?php echo number_format($row['avg_batch_size'], 2); ?> Qtl</td>
                            <td><?php echo $row['warehouse_count']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">No inventory data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Warehouse Status (Visible in Inventory tab) -->
        <div class="report-card <?php echo $active_tab != 'inventory' ? 'tab-content' : ''; ?>" id="inventory-warehouse">
            <h2><i class="fas fa-warehouse"></i> Warehouse Status</h2>
            <table>
                <thead>
                    <tr>
                        <th>Warehouse</th>
                        <th>Batches</th>
                        <th>Total Stock</th>
                        <th>Types Available</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($warehouse_result && $warehouse_result->num_rows > 0): ?>
                        <?php $warehouse_result->data_seek(0); while($row = $warehouse_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['warehouse']); ?></strong></td>
                            <td><?php echo $row['batch_count']; ?></td>
                            <td><?php echo number_format($row['total_stock'], 2); ?> Qtl</td>
                            <td><?php echo $row['types_available']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;">No warehouse data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Farmer Distribution (Visible in Summary and Farmers tabs) -->
        <div class="report-card <?php echo ($active_tab != 'summary' && $active_tab != 'farmers') ? 'tab-content' : ''; ?>" id="farmers-region">
            <h2><i class="fas fa-users"></i> Farmers by Region</h2>
            <table>
                <thead>
                    <tr>
                        <th>Region</th>
                        <th>Farmers</th>
                        <th>Total Land (ha)</th>
                        <th>Total Requirement (Qtl)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($farmer_region_result && $farmer_region_result->num_rows > 0): ?>
                        <?php $farmer_region_result->data_seek(0); while($row = $farmer_region_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['region']); ?></strong></td>
                            <td><?php echo number_format($row['farmer_count']); ?></td>
                            <td><?php echo number_format($row['total_land'], 2); ?></td>
                            <td><?php echo number_format($row['total_requirement'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;">No farmer data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Farmer Statistics (Visible in Farmers tab) -->
        <div class="report-card <?php echo $active_tab != 'farmers' ? 'tab-content' : ''; ?>" id="farmers-stats">
            <h2><i class="fas fa-chart-bar"></i> Farmer Statistics</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: #f5f5f5; border-radius: 10px;">
                    <div style="font-size: 2rem; color: var(--primary);"><?php echo number_format($farmer_stats['total_farmers'] ?? 0); ?></div>
                    <div>Total Farmers</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f5f5f5; border-radius: 10px;">
                    <div style="font-size: 2rem; color: #2e7d32;"><?php echo number_format($farmer_stats['active_farmers'] ?? 0); ?></div>
                    <div>Active Farmers</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f5f5f5; border-radius: 10px;">
                    <div style="font-size: 2rem; color: #1565c0;"><?php echo number_format($farmer_stats['total_land'] ?? 0, 2); ?> ha</div>
                    <div>Total Land</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f5f5f5; border-radius: 10px;">
                    <div style="font-size: 2rem; color: #ef6c00;"><?php echo number_format($farmer_stats['total_requirement'] ?? 0, 2); ?> Qtl</div>
                    <div>Total Requirement</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f5f5f5; border-radius: 10px;">
                    <div style="font-size: 2rem; color: #6a1b9a;"><?php echo number_format($farmer_stats['regions_covered'] ?? 0); ?></div>
                    <div>Regions Covered</div>
                </div>
                <div style="text-align: center; padding: 20px; background: #f5f5f5; border-radius: 10px;">
                    <div style="font-size: 2rem; color: #c2185b;"><?php echo number_format($farmer_stats['avg_land_size'] ?? 0, 2); ?> ha</div>
                    <div>Avg Land Size</div>
                </div>
            </div>
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
        document.cookie = "darkMode=" + document.body.classList.contains('dark-mode');
    }

    // Load dark mode preference
    if (document.cookie.includes('darkMode=true')) {
        document.body.classList.add('dark-mode');
    }

    // Region Chart
    <?php
    $region_labels = [];
    $region_data = [];
    foreach ($regional_data as $row) {
        $region_labels[] = $row['region'];
        $region_data[] = $row['total_distributed'];
    }
    ?>
    
    const regionCtx = document.getElementById('regionChart').getContext('2d');
    new Chart(regionCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($region_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($region_data); ?>,
                backgroundColor: ['#4caf50', '#1b5e20', '#81c784', '#66bb6a', '#43a047', '#2e7d32']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Trend Chart
    <?php
    $trend_labels = [];
    $trend_data = [];
    foreach (array_reverse($monthly_data) as $row) {
        $trend_labels[] = $row['month_name'];
        $trend_data[] = $row['total_distributed'];
    }
    ?>
    
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [{
                label: 'Distributed Quantity (Qtl)',
                data: <?php echo json_encode($trend_data); ?>,
                borderColor: '#1b5e20',
                backgroundColor: 'rgba(27, 94, 32, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantity (Qtl)'
                    }
                }
            }
        }
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>
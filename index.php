<?php
// [file name]: index.php
session_start();

// Include database connection
require_once 'db_connect.php';

// Get some statistics for display
$stats = [];

// Total farmers
$result = $conn->query("SELECT COUNT(*) as count FROM farmers");
$stats['total_farmers'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total inventory
$result = $conn->query("SELECT SUM(quantity) as total FROM inventory");
$stats['total_inventory'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;

// Active shipments
$result = $conn->query("SELECT COUNT(*) as count FROM shipments WHERE status = 'In-Transit'");
$stats['active_shipments'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Fertilizer Supply Chain - Debark, Ethiopia</title>
    <link rel="stylesheet" type="text/css" href="index.css">
</head>
<body style="background: url(w.png);">

<header>
    <div class="logo-area">
        <span style="font-size: 2rem;">🚜</span>
        <h2 style="margin:0">EF-SCM DEBARK</h2>
    </div>
    <nav>
        <a href="#features">Services</a>
        <a href="#debark">Regional Info</a>
        <a href="login.php" class="login-btn"  style="background-color: whitesmoke;">Login</a>
    </nav>
</header>

<section class="hero">
    <div class="location-tag">📍 DEBARK HUB | NORTH GONDAR</div>
    <h1>Digital Fertilizer Supply Chain</h1>
    <p>Ensuring the farmers of Debark and the Simien Mountains regions receive DAP and Urea fertilizer with transparency, efficiency, and zero leakage.</p>
    <div class="btn-container">
        <a href="login.php" class="btn btn-primary"  style="background-color: whitesmoke;">Enter System</a>
        <a href="#features" class="btn btn-secondary">Learn More</a>
    </div>
</section>

<section class="debark-info" id="debark">
    <div class="debark-text">
        <h2 style="color: var(--primary-green);">The Debark Strategic Gateway</h2>
        <p>Debark is not just a tourist destination for the Simien Mountains; it is a vital agricultural corridor. Our system tracks fertilizer movement from national ports to the <strong>Debark Central Warehouse</strong>, and then to local cooperatives in the surrounding woredas.</p>
        <ul style="margin-top: 15px; list-style: none;">
            <li><strong>📍 Zone:</strong> North Gondar</li>
            <li><strong>🌾 Focus:</strong> High-altitude Crop Support</li>
            <li><strong>👥 Target:</strong> 50,000+ Local Smallholder Farmers</li>
        </ul>
    </div>
    <div class="stats-box"  style="color: black;">
        <h3>Live Stats (Debark)</h3>
        <hr style="border-color: rgba(255,255,255,0.2); margin: 15px 0;">
        <p>Current Stock: <span class="stat-number"><?php echo number_format($stats['total_inventory'] ?? 0, 2); ?></span> Qtl</p>
        <p>Registered Farmers: <span class="stat-number"><?php echo number_format($stats['total_farmers'] ?? 0); ?></span></p>
        <p>Active Shipments: <span class="stat-number"><?php echo $stats['active_shipments'] ?? 0; ?></span> Trucks</p>
    </div>
</section>

<section class="features" id="features">
    <h2 class="section-title">System Capabilities</h2>
    <div class="grid">
        <div class="card">
            <h3>Real-Time Tracking</h3>
            <p>Monitor every quintal of fertilizer as it travels through the highlands to ensure it reaches its destination.</p>
        </div>
        <div class="card">
            <h3>Leakage Detection</h3>
            <p>Smart alerts if fertilizer is diverted from the authorized Debark-Gondar-Axum trade routes.</p>
        </div>
        <div class="card">
            <h3>Direct Farmer SMS</h3>
            <p>Notifying local farmers in Debark when stock arrives at their nearby cooperative center.</p>
        </div>
    </div>
</section>

<footer>
    <p>© 2026 Digital Fertilizer Supply Chain Management System</p>
    <p style="color: #666;">Debark Branch, Amhara Region, Ethiopia</p>
    <p>"Securing Ethiopia's Food Future, One Seed at a Time."</p>
</footer>

</body>
</html>
<?php $conn->close(); ?>
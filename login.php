<?php
// [file name]: login.php
session_start();

// Include database connection
require_once 'db_connect.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    redirectToDashboard($_SESSION['role']);
    exit();
}

// Function to redirect based on role
function redirectToDashboard($role) {
    switch($role) {
        case 'Admin':
            header("Location: dashboard.php");
            break;
        case 'Supplier':
            header("Location: inventory.php");
            break;
        case 'Retailer':
            header("Location: farmer.php");
            break;
        case 'Customer':
            header("Location: farmer.php");
            break;
        default:
            header("Location: index.php");
    }
    exit();
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $role = sanitize($_POST['role']);
    
    // For demo purposes - in production, use password_hash()
    // Simple check for demo users
    $valid_credentials = [
        'admin' => ['password' => 'admin123', 'role' => 'Admin', 'name' => 'Administrator'],
        'supplier' => ['password' => 'supplier123', 'role' => 'Supplier', 'name' => 'Main Supplier'],
        'retailer' => ['password' => 'retailer123', 'role' => 'Retailer', 'name' => 'Local Retailer'],
        'customer' => ['password' => 'customer123', 'role' => 'Customer', 'name' => 'Demo Farmer']
    ];
    
    $username_lower = strtolower($username);
    
    if (isset($valid_credentials[$username_lower]) && 
        $valid_credentials[$username_lower]['password'] === $password &&
        $valid_credentials[$username_lower]['role'] === $role) {
        
        // Set session variables
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['full_name'] = $valid_credentials[$username_lower]['name'];
        $_SESSION['user_id'] = $username_lower;
        
        // Redirect to appropriate dashboard
        redirectToDashboard($role);
        exit();
    } else {
        $error = "Invalid username, password, or role combination!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🌿 GreenLogin | Fertilizer Supply Chain</title>
  <link rel="stylesheet" type="text/css" href="login.css">
</head>
<body style="background: url(q.png);">

<div class="login-card">
  <div class="login-header">
    <h2>
      <span>🌱</span> System Login
    </h2>
    <p>Fertilizer supply chain · green access</p>
  </div>

  <?php if (isset($error)): ?>
    <div class="error-message">
      <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <div class="form-group">
      <label><i>👤</i> Username</label>
      <div class="input-wrapper">
        <input type="text" name="username" id="username" placeholder="e.g. admin" autocomplete="off" required>
        <span class="input-icon">🌿</span>
      </div>
    </div>

    <div class="form-group">
      <label><i>🔒</i> Password</label>
      <div class="input-wrapper">
        <input type="password" name="password" id="password" placeholder="••••••••" required>
        <span class="input-icon">🔐</span>
      </div>
    </div>

    <div class="form-group">
      <label><i>🌾</i> I am a</label>
      <select name="role" id="role" required>
        <option value="" disabled selected>-- select role --</option>
        <option value="Admin">Admin (ዋና አስተዳዳሪ)</option>
        <option value="Supplier">Supplier (አቅራቢ)</option>
        <option value="Retailer">Retailer (ቸርቻሪ)</option>
        <option value="Customer">Customer / Farmer (አርሶ አደር)</option>
      </select>
    </div>

    <button type="submit" name="login" class="login-btn">
      <span>🔑</span> Sign in to dashboard
    </button>

    <div class="extra-links">
      <a href="#">🌼 Forgot password? · reset</a>
    </div>
  </form>
  <div class="demo-credentials">
    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span  style="background-color: greenyellow ; text-align: center ;
    border: 1px solid; font-family: arial; font-size: 20px;">Logout</span></a>
    <p><strong> check in this sample manager:</strong></p>
    <p>Admin: admin / admin123</p>
    <p>Supplier: supplier / supplier123</p>
    <p>Retailer: retailer / retailer123</p>
    <p>Customer: customer / customer123</p>
  </div>
</div>

</body>
</html>
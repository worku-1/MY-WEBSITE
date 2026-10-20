<?php
// [file name]: db_connect.php
// Central database connection file for all pages

$host = "localhost";
$username = "root";
$password = "";
$database = "fsc_inventory";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Function to sanitize input
function sanitize($input) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

// Function to get user role from session
function getUserRole() {
    return $_SESSION['role'] ?? 'Customer';
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}
?>
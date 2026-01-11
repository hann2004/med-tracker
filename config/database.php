<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'med_tracker_pro');

// Create connection
function getDatabaseConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Password helper function
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }
}

// Redirect based on user type
function redirectToDashboard() {
    if (isset($_SESSION['user_type'])) {
        switch ($_SESSION['user_type']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'pharmacy':
                header('Location: pharmacy/dashboard.php');
                break;
            case 'user':
                header('Location: index.php');
                break;
            default:
                header('Location: index.php');
        }
        exit();
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
?>
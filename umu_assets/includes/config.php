<?php
// =====================================================
// Database Configuration
// Uganda Martyrs University - Assets Management System
// =====================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change to your MySQL username
define('DB_PASS', '');            // Change to your MySQL password
define('DB_NAME', 'umu_assets_db');

define('APP_NAME', 'UMU Assets Management System');
define('APP_SHORT', 'UMU-AMS');
define('BASE_URL', 'http://localhost/umu_assets/');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit();
    }
}

function sanitize($conn, $data) {
    return $conn->real_escape_string(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return 'UGX ' . number_format($amount, 0);
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d == 0) return 'Today';
    if ($diff->d == 1) return 'Yesterday';
    if ($diff->d < 7) return $diff->d . ' days ago';
    return date('d M Y', strtotime($datetime));
}

function isOverdue($expected_date) {
    return strtotime($expected_date) < strtotime(date('Y-m-d'));
}

function logActivity($conn, $action, $details = '') {
    $user_id = $_SESSION['id'] ?? NULL;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $details_json = json_encode(['message' => $details]);
    $conn->query("INSERT INTO activity_logs (user_id, action, details, ip) VALUES ($user_id, '$action', '$details_json', '$ip')");
}

function createNotification($conn, $user_id, $title, $message, $type = 'info') {
    $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($user_id, '$title', '$message', '$type')");
}

function getUnreadCount($conn, $user_id) {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND is_read = 0");
    return $result->fetch_assoc()['cnt'];
}
?>


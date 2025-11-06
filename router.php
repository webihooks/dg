<?php
// router.php - Advanced Profile Router
require_once 'config/db_connection.php';
require_once 'functions/profile_functions.php';

date_default_timezone_set('Asia/Kolkata');

if (!isset($_GET['profile_url'])) {
    header("HTTP/1.0 400 Bad Request");
    die("Profile URL is required");
}

$profile_url = $_GET['profile_url'];

// Get user data to determine type
$profile_data = getUserByProfileUrl($conn, $profile_url);
if (!$profile_data) {
    header("Location: page-not-found.php");
    exit();
}

$user_id = $profile_data['user_id'];

// Check user role to determine redirect
$user_sql = "SELECT role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user_role = $user_stmt->fetch(PDO::FETCH_ASSOC)['role'];

// Redirect based on role
if (in_array($user_role, ['room', 'resort', 'hotel'])) {
    // Hotel/Room booking
    require_once 'rooms.php';
} else {
    // Restaurant/Food ordering
    require_once 'post.php';
}

$conn = null;
?>
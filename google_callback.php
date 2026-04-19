<?php
// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db_connection.php';
require_once __DIR__ . '/includes/google_login_authentication.php';

if (!isset($_GET['code'])) {
    die('No authorization code provided.');
}

$code = $_GET['code'];

// Get Google user info
$googleUser = getGoogleUser($code);
if (!$googleUser) {
    die('Google authentication failed. Please try again.');
}

// Get restaurant user_id from the 'state' parameter
$state = $_GET['state'] ?? '';
parse_str($state, $stateParams);
$user_id = $stateParams['user_id'] ?? 0;
$profile_url = $stateParams['profile_url'] ?? '';

if (!$user_id) {
    die('Restaurant ID missing.');
}

// Save or update customer
$customer_id = saveOrUpdateCustomer($conn, $user_id, $googleUser);

// Set session variables
$_SESSION['customer_logged_in'] = true;
$_SESSION['customer_id'] = $customer_id;
$_SESSION['customer_restaurant_id'] = $user_id;
$_SESSION['customer_email'] = $googleUser['email'];
$_SESSION['customer_name'] = $googleUser['name'];

// Redirect back to the profile page
$redirect = 'post.php?profile_url=' . urlencode($profile_url);
header('Location: ' . $redirect);
exit;
?>
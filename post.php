<?php
// Start session for Google Login
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db_connection.php';
require_once 'functions/profile_functions.php';
require_once 'config/google_config.php';

// Include Google Authentication
require_once 'includes/google_login_authentication.php';

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    logoutCustomer();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

if (!isset($_GET['profile_url'])) {
    header("HTTP/1.0 400 Bad Request");
    die("Profile URL is required");
}

$profile_url = $_GET['profile_url'];

// Get user ID from profile URL
$profile_data = getUserByProfileUrl($conn, $profile_url);
if (!$profile_data) {
    header("Location: page-not-found.php");
    exit();
}

$user_id = $profile_data['user_id'];

// Get all profile data including role, name, and country
$user_sql = "SELECT id, Name as name, Email, role, phone, address, country FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
if ($user_stmt) {
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare user SQL statement.");
    $user = false;
}

if (!$user) {
    die("User not found");
}

// Set timezone based on user's country
$user_country = $user['country'] ?? 'India';
switch ($user_country) {
    case 'India':
        date_default_timezone_set('Asia/Kolkata');
        break;
    case 'UAE':
        date_default_timezone_set('Asia/Dubai');
        break;
    case 'UK':
        date_default_timezone_set('Europe/London');
        break;
    case 'USA':
        date_default_timezone_set('America/New_York');
        break;
    default:
        date_default_timezone_set('Asia/Kolkata'); // Default fallback
}

// Check for active subscription
$subscription_sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()";
$subscription_stmt = $conn->prepare($subscription_sql);
if ($subscription_stmt) {
    $subscription_stmt->execute([$user_id]);
    $active_subscription = $subscription_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare subscription SQL statement.");
    $active_subscription = false;
}

$show_subscription_popup = !$active_subscription;

$is_restuarant_user = ($user['role'] === 'user');
$is_room_user = ($user['role'] === 'room');
$is_vegetable_seller = ($user['role'] === 'vegetable_seller');

// Set currency configuration
require_once 'config/currency_helper.php';

$currency_symbol = CurrencyHelper::getSymbol($user_country);
$currency_code = CurrencyHelper::getCode($user_country);

// Pass currency info to included files
$currency_info = [
    'symbol' => $currency_symbol,
    'code' => $currency_code,
    'country' => $user_country
];

// Fetch theme data
$theme_sql = "SELECT primary_color, secondary_color FROM theme WHERE user_id = ?";
$theme_stmt = $conn->prepare($theme_sql);
if ($theme_stmt) {
    $theme_stmt->execute([$user_id]);
    $theme_data = $theme_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare theme SQL statement.");
    $theme_data = [];
}

$primary_color = $theme_data['primary_color'] ?? '#000000';
$secondary_color = $theme_data['secondary_color'] ?? '#ffffff';

// Pass variables to included files
$delivery_active = isset($delivery_active) ? $delivery_active : false;
$dining_active = isset($dining_active) ? $dining_active : false;

// Only for Role is User
if ($is_restuarant_user) {
    // Get customer data for logged-in user
    $customer_data = null;
    if (isCustomerLoggedIn($user_id)) {
        $customer_data = getCustomerData($conn, $user_id);
    }
    // Make it available in included files
    $GLOBALS['customer_data'] = $customer_data;
    $GLOBALS['user_id'] = $user_id;
    $GLOBALS['profile_url'] = $profile_url;


    
    // Pass currency info to all included files
    $GLOBALS['currency_info'] = $currency_info;
    
    require_once 'includes/restaurant_functions.php';
    require_once 'includes/header.php';
    require_once 'includes/navigation.php';
    require_once 'includes/profile_header.php';
    require_once 'includes/download_apk_button.php';
    require_once 'includes/business_info.php';
    require_once 'includes/offer_popup.php';
    require_once 'includes/products.php';
    require_once 'includes/services.php';
    require_once 'includes/gallery.php';
    require_once 'includes/ratings.php';
    require_once 'includes/bank_details.php';
    require_once 'includes/qr_codes.php';
    require_once 'includes/share_section.php';
    require_once 'includes/footer.php';
}

// Only for Role is vegetable_seller
if ($is_vegetable_seller) {
    // Pass currency info to all included files
    $GLOBALS['currency_info'] = $currency_info;
    
    require_once 'includes/vegetable_seller_functions.php';
    require_once 'includes/header.php';
    require_once 'includes/navigation.php';
    require_once 'includes/profile_header.php';
    require_once 'includes/download_apk_button.php';
    require_once 'includes/business_info.php';
    require_once 'includes/vegetable_products.php';
    
    // require_once 'includes/ratings.php';
    // require_once 'includes/qr_codes.php';
    // require_once 'includes/share_section.php';
    // require_once 'includes/footer.php';
}

// Only for Role is Room
if ($is_room_user) {
    // Pass currency info to all included files
    $GLOBALS['currency_info'] = $currency_info;
    
    require_once 'includes/room_functions.php';
    require_once 'includes/room_header.php';
    require_once 'includes/room_profile_header.php';
    require_once 'includes/available_rooms.php';
    require_once 'includes/room_ratings.php';
    require_once 'includes/room_qr_codes.php';
    require_once 'includes/room_share_section.php';
    require_once 'includes/room_footer.php';
}

// Close connection
$conn = null;
?>
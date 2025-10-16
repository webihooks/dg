<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Ensure these paths are correct relative to where this script is executed
require_once 'config/db_connection.php';
require_once 'functions/profile_functions.php';

date_default_timezone_set('Asia/Kolkata');

// Validate profile URL
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

// Get all profile data
$user = getUserById($conn, $user_id);
if (!$user) {
    die("User not found");
}

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

// Get delivery charges
$delivery_charges_sql = "SELECT delivery_charge, free_delivery_minimum FROM delivery_charges WHERE user_id = ?";
$delivery_charges_stmt = $conn->prepare($delivery_charges_sql);
if ($delivery_charges_stmt) {
    $delivery_charges_stmt->execute([$user_id]);
    $delivery_charges = $delivery_charges_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare delivery charges SQL statement.");
    $delivery_charges = ['delivery_charge' => 0, 'free_delivery_minimum' => 0];
}

// Get GST charge
$gst_sql = "SELECT gst_percent FROM gst_charge WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$gst_stmt = $conn->prepare($gst_sql);
if ($gst_stmt) {
    $gst_stmt->execute([$user_id]);
    $gst_data = $gst_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare GST charge SQL statement.");
    $gst_data = ['gst_percent' => 0];
}
$gst_percent = $gst_data['gst_percent'] ?? 0;

// Get discounts
$discounts_sql = "SELECT min_cart_value, discount_in_percent, discount_in_flat, image_path FROM discount WHERE user_id = ? ORDER BY min_cart_value ASC";
$discounts_stmt = $conn->prepare($discounts_sql);
if ($discounts_stmt) {
    $discounts_stmt->execute([$user_id]);
    $discounts = $discounts_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare discount SQL statement.");
    $discounts = [];
}

// Get other profile data
$business_info = getBusinessInfo($conn, $user_id);
$photos = getProfilePhotos($conn, $user_id);
$social_link = getSocialLinks($conn, $user_id);
$products = getProducts($conn, $user_id);
$services = getServices($conn, $user_id);
$gallery = getGallery($conn, $user_id);
$ratings = getRatings($conn, $user_id);
$bank_details = getBankDetails($conn, $user_id);
$qr_codes = getQrCodes($conn, $user_id);

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rating_data = [
        'reviewer_name' => $_POST['reviewer_name'] ?? '',
        'reviewer_email' => $_POST['reviewer_email'] ?? '',
        'reviewer_phone' => $_POST['reviewer_phone'] ?? '',
        'rating' => intval($_POST['rating'] ?? 0),
        'feedback' => $_POST['feedback'] ?? ''
    ];

    if (submitRating($conn, $user_id, $rating_data)) {
        header("Location: ?profile_url=" . urlencode($profile_url));
        exit();
    } else {
        echo "<script>alert('Failed to submit rating. Please try again.');</script>";
    }
}

// Fetch dining tables count
$table_sql = "SELECT table_count FROM dining_tables WHERE user_id = ?";
$table_stmt = $conn->prepare($table_sql);
if ($table_stmt) {
    $table_stmt->execute([$user_id]);
    $table_data = $table_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare dining tables SQL statement.");
    $table_data = ['table_count' => 0];
}
$table_count = $table_data['table_count'] ?? 0;

// Check dining and delivery status
$dining_delivery_sql = "SELECT dining_active, delivery_active FROM dining_and_delivery WHERE user_id = ?";
$dining_delivery_stmt = $conn->prepare($dining_delivery_sql);
if ($dining_delivery_stmt) {
    $dining_delivery_stmt->execute([$user_id]);
    $dining_delivery_data = $dining_delivery_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare dining and delivery SQL statement.");
    $dining_delivery_data = ['dining_active' => 0, 'delivery_active' => 0];
}

$dining_active = $dining_delivery_data['dining_active'] ?? 0;
$delivery_active = $dining_delivery_data['delivery_active'] ?? 0;

// Get tags
$tags_sql = "SELECT id, tag FROM tags WHERE user_id = ? ORDER BY position ASC";
$tags_stmt = $conn->prepare($tags_sql);
if ($tags_stmt) {
    $tags_stmt->execute([$user_id]);
    $tags = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare tags SQL statement.");
    $tags = [];
}

// Get products with tags
$products_sql = "SELECT p.*, t.tag 
                 FROM products p 
                 LEFT JOIN tags t ON p.tag_id = t.id 
                 WHERE p.user_id = ?";
$products_stmt = $conn->prepare($products_sql);
$products_stmt->execute([$user_id]);
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for active subscription and get package_id
$subscription_sql = "SELECT package_id FROM subscriptions 
                    WHERE user_id = ? 
                    AND status = 'active' 
                    AND end_date >= CURDATE()
                    LIMIT 1";
$subscription_stmt = $conn->prepare($subscription_sql);
if ($subscription_stmt) {
    $subscription_stmt->execute([$user_id]);
    $active_subscription = $subscription_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare subscription SQL statement.");
    $active_subscription = false;
}

$show_subscription_popup = !$active_subscription;
$package_id = $active_subscription ? $active_subscription['package_id'] : null;

// ==================== STORE TIMING CHECK ====================
// Get current date and time
$current_datetime = new DateTime();
$current_time = $current_datetime->format('H:i:s');
$current_day_of_week = $current_datetime->format('w'); // 0=Sunday, 6=Saturday

// Check if store is currently open
$store_timing_sql = "SELECT open_time, close_time, is_closed 
                    FROM store_timing 
                    WHERE user_id = ? AND day_of_week = ?";
$store_timing_stmt = $conn->prepare($store_timing_sql);

$is_store_open = false;
$store_timing_data = null;
$next_opening_time = null;

if ($store_timing_stmt) {
    $store_timing_stmt->execute([$user_id, $current_day_of_week]);
    $store_timing_data = $store_timing_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($store_timing_data && !$store_timing_data['is_closed']) {
        $open_time = strtotime($store_timing_data['open_time']);
        $close_time = strtotime($store_timing_data['close_time']);
        $current_time_stamp = strtotime($current_time);
        
        $is_store_open = ($current_time_stamp >= $open_time && $current_time_stamp <= $close_time);
        
        // If closed, find next opening time
        if (!$is_store_open) {
            if ($current_time_stamp < $open_time) {
                $next_opening_time = date('g:i A', $open_time);
            } else {
                // Store closed for today, find next open day
                $next_day = ($current_day_of_week + 1) % 7;
                $next_day_sql = "SELECT open_time FROM store_timing 
                                WHERE user_id = ? AND day_of_week = ? AND is_closed = 0 
                                ORDER BY day_of_week ASC LIMIT 1";
                $next_day_stmt = $conn->prepare($next_day_sql);
                if ($next_day_stmt) {
                    $next_day_stmt->execute([$user_id, $next_day]);
                    $next_day_data = $next_day_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($next_day_data) {
                        $next_opening_time = date('g:i A', strtotime($next_day_data['open_time'])) . ' tomorrow';
                    }
                }
            }
        }
    }
} else {
    error_log("Failed to prepare store timing SQL statement.");
}

// Get weekly schedule for display
$weekly_schedule_sql = "SELECT day_of_week, open_time, close_time, is_closed 
                       FROM store_timing 
                       WHERE user_id = ? 
                       ORDER BY day_of_week ASC";
$weekly_schedule_stmt = $conn->prepare($weekly_schedule_sql);
$weekly_schedule = [];

if ($weekly_schedule_stmt) {
    $weekly_schedule_stmt->execute([$user_id]);
    $weekly_schedule = $weekly_schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare weekly schedule SQL statement.");
}
// ==================== END STORE TIMING CHECK ====================

// Include HTML components
require_once 'includes/header.php';
require_once 'includes/navigation.php';
require_once 'includes/profile_header.php';
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

// Close connection
$conn = null;
?>
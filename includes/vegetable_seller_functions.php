<?php 
// Fetch profile and cover photos
$photos_sql = "SELECT profile_photo, cover_photo FROM profile_cover_photo WHERE user_id = ?";
$photos_stmt = $conn->prepare($photos_sql);
if ($photos_stmt) {
    $photos_stmt->execute([$user_id]);
    $photos_data = $photos_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare photos SQL statement.");
    $photos_data = [];
}

// Fetch business info
$business_sql = "SELECT business_name, business_description, business_address, designation, website FROM business_info WHERE user_id = ?";
$business_stmt = $conn->prepare($business_sql);
if ($business_stmt) {
    $business_stmt->execute([$user_id]);
    $business_info = $business_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare business info SQL statement.");
    $business_info = [];
}

// Get vegetable seller settings
$veg_settings_table = "vegetable_settings_" . $user_id;
$check_settings_table = $conn->prepare("SHOW TABLES LIKE ?");
$check_settings_table->execute([$veg_settings_table]);
$settings_table_exists = $check_settings_table->fetch(PDO::FETCH_ASSOC);

if ($settings_table_exists) {
    $veg_settings_sql = "SELECT instant_delivery_enabled, instant_delivery_charge, delivery_charge, tax_rate, business_name, business_phone, business_address 
                         FROM " . $veg_settings_table . " 
                         WHERE id = 1";
    $veg_settings_stmt = $conn->prepare($veg_settings_sql);
    if ($veg_settings_stmt) {
        $veg_settings_stmt->execute();
        $veg_settings = $veg_settings_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        error_log("Failed to prepare vegetable settings SQL statement.");
        $veg_settings = [];
    }
} else {
    $veg_settings = [];
}

$delivery_charge = $veg_settings['delivery_charge'] ?? 0;
$instant_delivery_charge = $veg_settings['instant_delivery_charge'] ?? 0;
$instant_delivery_enabled = $veg_settings['instant_delivery_enabled'] ?? 0;
$tax_rate = $veg_settings['tax_rate'] ?? 0;

// Get delivery charges (for backward compatibility)
$delivery_charges = [
    'delivery_charge' => $delivery_charge,
    'free_delivery_minimum' => 0
];

// Get GST charge (use tax_rate from vegetable settings)
$gst_percent = $tax_rate;

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
$gallery = getGallery($conn, $user_id);
$ratings = getRatings($conn, $user_id);
$qr_codes = getQrCodes($conn, $user_id);

// Get user APK information
$apk_data = getUserApk($conn, $user_id);

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

// Get vegetable products from user-specific table
$table_name = "vegetable_products_" . $user_id;

// Check if the user-specific vegetable products table exists
$check_table = $conn->prepare("SHOW TABLES LIKE ?");
$check_table->execute([$table_name]);
$table_exists = $check_table->fetch(PDO::FETCH_ASSOC);

if ($table_exists) {
    // Fetch vegetable products from user-specific table
    $products_sql = "SELECT vp.*, mp.image_path as master_image 
                     FROM $table_name vp 
                     LEFT JOIN master_vegetable_products mp ON vp.master_id = mp.id 
                     WHERE vp.is_active = 1 
                     ORDER BY vp.product_name_en ASC";
    $products_stmt = $conn->prepare($products_sql);
    if ($products_stmt) {
        $products_stmt->execute();
        $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log("Failed to prepare products SQL statement.");
        $products = [];
    }
} else {
    $products = []; // Empty array if table doesn't exist
}

// Get time slots for vegetable delivery
$time_slots_table = "vegetable_time_slots_" . $user_id;
$check_slots_table = $conn->prepare("SHOW TABLES LIKE ?");
$check_slots_table->execute([$time_slots_table]);
$slots_table_exists = $check_slots_table->fetch(PDO::FETCH_ASSOC);

if ($slots_table_exists) {
    $time_slots_sql = "SELECT id, slot_name, start_time, end_time, is_active, display_order 
                       FROM " . $time_slots_table . " 
                       WHERE is_active = 1 
                       ORDER BY display_order ASC, start_time ASC";
    $time_slots_stmt = $conn->prepare($time_slots_sql);
    if ($time_slots_stmt) {
        $time_slots_stmt->execute();
        $time_slots = $time_slots_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log("Failed to prepare time slots SQL statement.");
        $time_slots = [];
    }
} else {
    $time_slots = [];
}

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

// ==================== STORE TIMING CHECK ====================
// Get current date and time
$current_datetime = new DateTime();
$current_time = $current_datetime->format('H:i:s');
$current_day_of_week = $current_datetime->format('w');

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

// Get order types (delivery/takeaway availability)
$delivery_active = 1; // Vegetable sellers typically offer delivery
$dining_active = 0; // No dining for vegetable sellers

// Function to get vegetable seller settings (if needed elsewhere)
function getVegetableSettings($conn, $user_id) {
    $settings_table = "vegetable_settings_" . $user_id;
    $check_table = $conn->prepare("SHOW TABLES LIKE ?");
    $check_table->execute([$settings_table]);
    
    if ($check_table->fetch(PDO::FETCH_ASSOC)) {
        $sql = "SELECT * FROM " . $settings_table . " WHERE id = 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    return null;
}

// Function to get vegetable products with stock check
function getVegetableProducts($conn, $user_id) {
    $products_table = "vegetable_products_" . $user_id;
    $check_table = $conn->prepare("SHOW TABLES LIKE ?");
    $check_table->execute([$products_table]);
    
    if ($check_table->fetch(PDO::FETCH_ASSOC)) {
        $sql = "SELECT vp.*, mp.image_path as master_image 
                FROM " . $products_table . " vp 
                LEFT JOIN master_vegetable_products mp ON vp.master_id = mp.id 
                WHERE vp.is_active = 1 AND vp.quantity > 0
                ORDER BY vp.product_name_en ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    return [];
}

// Function to get available time slots
function getAvailableTimeSlots($conn, $user_id) {
    $slots_table = "vegetable_time_slots_" . $user_id;
    $check_table = $conn->prepare("SHOW TABLES LIKE ?");
    $check_table->execute([$slots_table]);
    
    if ($check_table->fetch(PDO::FETCH_ASSOC)) {
        $sql = "SELECT * FROM " . $slots_table . " 
                WHERE is_active = 1 
                ORDER BY display_order ASC, start_time ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    return [];
}
?>
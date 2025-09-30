<?php
// In your server configuration or PHP file
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
// order_status.php
require_once 'config/db_connection.php';

date_default_timezone_set('Asia/Kolkata');

// Check if order_id and profile_url are provided
if (!isset($_GET['order_id']) || !isset($_GET['profile_url'])) {
    header("Location: page-not-found.php");
    exit();
}

$order_id = $_GET['order_id'];
$profile_url = $_GET['profile_url'];

// Function to fetch order data
function getOrderData($conn, $order_id) {
    $order_sql = "SELECT * FROM orders WHERE order_id = ?";
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->execute([$order_id]);
    return $order_stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch initial order details
$order = getOrderData($conn, $order_id);

if (!$order) {
    header("Location: page-not-found.php");
    exit();
}

// Fetch order items
$items_sql = "SELECT * FROM order_items WHERE order_id = ?";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch QR code details for payment
$qr_sql = "SELECT * FROM qrcode_details WHERE user_id = ? AND is_default = 1 LIMIT 1";
$qr_stmt = $conn->prepare($qr_sql);
$qr_stmt->execute([$order['user_id']]);
$qr_code = $qr_stmt->fetch(PDO::FETCH_ASSOC);

// Get restaurant name from business_info table
$business_sql = "SELECT business_name FROM business_info WHERE user_id = ? LIMIT 1";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->execute([$order['user_id']]);
$business_info = $business_stmt->fetch(PDO::FETCH_ASSOC);

$business_name = $business_info['business_name'] ?? 'Restaurant';

// Get user data for header components
require_once 'functions/profile_functions.php';
$user_id = $order['user_id'];
$user = getUserById($conn, $user_id);
$profile_data = getUserByProfileUrl($conn, $profile_url);

if (!$profile_data) {
    header("Location: page-not-found.php");
    exit();
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

// Get other profile data for components
$business_info = getBusinessInfo($conn, $user_id);
$photos = getProfilePhotos($conn, $user_id);
$social_link = getSocialLinks($conn, $user_id);

// --- MODIFICATION START: Simplified status logic ---
$simplified_statuses = [
    'Placed' => ['icon' => 'bi-check-circle', 'description' => '✅ Your order has been placed successfully!'],
    'Preparing' => ['icon' => 'bi-egg-fried', 'description' => '🍜 Order accepted! We’re preparing your food.'],
    'Ready' => ['icon' => 'bi-check2-circle', 'description' => '🍱 Your order is ready!'],
    'Out for Delivery' => ['icon' => 'bi-truck', 'description' => '🛵 Your order is out for delivery!'],
    'Completed' => ['icon' => 'bi-star', 'description' => '👍🏻 Order delivered – We hope you enjoy your meal!!']
];

$order_status_lower = strtolower($order['status']);
$is_cancelled = $order_status_lower === 'cancelled';

$current_step_index = 0; // Default to 'Placed'

// Determine current step based on order status
if (in_array($order_status_lower, ['confirmed', 'preparing'])) {
    $current_step_index = 1; // 'Preparing'
} elseif ($order_status_lower === 'ready') {
    $current_step_index = 2; // 'Ready'
} elseif ($order_status_lower === 'out_for_delivery') {
    $current_step_index = 3; // 'Out for Delivery'
} elseif (in_array($order_status_lower, ['completed', 'delivered'])) {
    $current_step_index = 4; // 'Completed'
}

// Check if this is a delivery order to show/hide delivery step
$is_delivery_order = $order['order_type'] === 'delivery';

// Special logic: When order is "ready", show both "Ready" and "Out for Delivery" as completed for delivery orders
$show_both_ready_and_delivery_completed = $is_delivery_order && $order_status_lower === 'ready';

// If it's not a delivery order, skip the "Out for Delivery" step
if (!$is_delivery_order && $current_step_index >= 3) {
    $current_step_index = min($current_step_index + 1, 4);
}
// --- MODIFICATION END ---

// Calculate countdown time (30 minutes from order creation)
$order_created_time = strtotime($order['created_at']);
$estimated_completion_time = $order_created_time + (30 * 60); // 30 minutes
$current_time = time();
$time_remaining = $estimated_completion_time - $current_time;

// Determine if we should show countdown or saved time message
$show_countdown = $time_remaining > 0 && !in_array($order_status_lower, ['completed', 'cancelled']);
$show_saved_time = $time_remaining <= 0 && !in_array($order_status_lower, ['completed', 'cancelled']);
// --- MODIFICATION END ---

// Build the back URL - use relative path to go to the profile URL
$back_url = '/' . $profile_url;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order ID - <?= htmlspecialchars($order_id) ?> | <?= htmlspecialchars($business_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: <?= $primary_color ?>;
            --secondary-color: <?= $secondary_color ?>;
        }
        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        @keyframes pulse-active {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.7); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(255, 107, 53, 0); }
        }
        .animate-pulse-active {
            animation: pulse-active 2s infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .spin { animation: spin 1s linear infinite; }
        .burger-menu, .social_networks, .designation { display: none !important; }
        
        /* Additional styling for order status page */
        .grid {
            display: grid;
        }
        .grid-cols-1 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        .grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .gap-4 {
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .md\:grid-cols-2 {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
        }

        /* Badge styling */
        .bg-orange-500 {
            background-color: #ff6b35;
        }
        .text-xs {
            font-size: 0.75rem;
        }
        .px-3 {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        .py-1\.5 {
            padding-top: 0.375rem;
            padding-bottom: 0.375rem;
        }

        /* Countdown styling */
        .countdown-digit {
            background: linear-gradient(135deg, #ff6b35, #ff8c42);
            color: white;
            border-radius: 8px;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 1.5rem;
            min-width: 50px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .countdown-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 4px;
        }
        .blink {
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .cover_photo {
            max-height: 140px !important;
        }
        .profile_photo {
            top: 60px !important;
        }
        .personal_info {
            margin-top: 190px !important;
        }
        .cover_photo.small {
            max-height: 60px !important;
        }
        .profile_photo.with-burger {
            left: 20px !important;
        }
        .profile_photo.small {
          width: 60px !important;
          height: 60px !important;
          margin-left: 0 !important;
          top: 30px !important;
          border-width: 3px !important;
          box-shadow: 0 0 2px #b5b5b5 !important;
        }
        #successMessage {
            width: 60%;
            text-align: center;
        }
        .write_feedback {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .write_feedback:hover {
            background-color: #e55a2e !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

<?php if (isset($_GET['review_submitted'])): ?>
<div id="successMessage" class="fixed top-5 left-1/2 transform -translate-x-1/2 bg-green-500 text-white py-3 px-6 rounded-lg shadow-lg z-[1000]">
    <i class="bi bi-check-circle-fill mr-2"></i> Thank you for your review!
</div>
<script>
    // Check if we've already shown this message
    const reviewShown = localStorage.getItem('reviewSubmitted_<?= $order_id ?>');
    
    if (!reviewShown) {
        // Show message and mark as shown
        document.getElementById('successMessage').style.display = 'block';
        localStorage.setItem('reviewSubmitted_<?= $order_id ?>', 'true');
        
        // Remove parameter from URL
        if (window.history.replaceState) {
            const newUrl = window.location.pathname + '?order_id=<?= $order_id ?>&profile_url=<?= $profile_url ?>';
            window.history.replaceState({}, document.title, newUrl);
        }
    } else {
        // Hide the message if already shown
        document.getElementById('successMessage').style.display = 'none';
    }
    
    setTimeout(() => {
        const successMsg = document.getElementById('successMessage');
        if (successMsg) successMsg.style.display = 'none';
    }, 3000);
</script>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div id="errorMessage" class="fixed top-5 left-1/2 transform -translate-x-1/2 bg-red-500 text-white py-3 px-6 rounded-lg shadow-lg z-[1000]">
    <i class="bi bi-exclamation-circle-fill mr-2"></i> Error submitting review. Please try again.
</div>
<script>
    setTimeout(() => {
        const errorMsg = document.getElementById('errorMessage');
        if (errorMsg) errorMsg.style.display = 'none';
    }, 5000);
</script>
<?php endif; ?>












    <?php
    // Include header components
    require_once 'includes/header.php';
    require_once 'includes/navigation.php';
    require_once 'includes/profile_header.php';
    ?>

    <div id="refreshIndicator" class="fixed top-5 right-5 bg-[#ff6b35] text-white py-2 px-4 rounded-full text-sm shadow-lg z-[1000] hidden">
        <i class="bi bi-arrow-clockwise spin"></i> Updating status...
    </div>

    <div class="container mx-auto max-w-2xl">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden my-6">
            <div class="bg-gradient-to-br from-[#ff6b35] to-[#ff8c42] text-white p-2 text-center">
                <h1 class="text-2xl font-bold tracking-wide">Order #<?= htmlspecialchars($order_id) ?></h1>
                <p class="opacity-90 text-sm mt-1">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>

                <?php if ($is_cancelled): ?>
                    <div class="bg-white/20 text-white mt-2 py-2 px-4 rounded-lg border border-white/30">
                        <i class="bi bi-exclamation-triangle mr-2"></i> This order has been cancelled.
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_cancelled): ?>
                <div id="countdownSection" class="p-4 border-b border-gray-200 bg-gradient-to-r from-orange-50 to-orange-50">
                    <div class="text-center">
                        <?php if ($show_countdown): ?>
                            <div class="mb-3">
                                <h3 class="text-lg font-bold text-gray-800 mb-2">
                                    <i class="bi bi-clock-history text-blue-500 mr-2"></i>
                                    <?php if ($order_status_lower === 'out_for_delivery'): ?>
                                        Estimated Arrival Time
                                    <?php else: ?>
                                        Estimated Delivery Time
                                    <?php endif; ?>
                                </h3>
                                <p class="text-sm text-gray-600 mb-4">
                                    <?php if ($order_status_lower === 'out_for_delivery'): ?>
                                        Your order will arrive in approximately
                                    <?php else: ?>
                                        Your order will be ready in approximately
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex justify-center items-center space-x-4 mb-3">
                                <div class="text-center">
                                    <div id="countdownMinutes" class="countdown-digit">30</div>
                                    <div class="countdown-label">MINUTES</div>
                                </div>
                                <div class="text-2xl font-bold text-gray-400">:</div>
                                <div class="text-center">
                                    <div id="countdownSeconds" class="countdown-digit">00</div>
                                    <div class="countdown-label">SECONDS</div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">
                                <?php if ($order_status_lower === 'out_for_delivery'): ?>
                                    Estimated arrival by: <span id="estimatedTime" class="font-semibold"></span>
                                <?php else: ?>
                                    Estimated delivery by: <span id="estimatedTime" class="font-semibold"></span>
                                <?php endif; ?>
                            </p>
                        <?php elseif ($show_saved_time): ?>
                            <div class="bg-yellow-100 border border-yellow-400 rounded-2xl p-4">
                                <div class="flex items-center justify-center">
                                    <i class="bi bi-exclamation-triangle-fill text-yellow-600 text-2xl mr-3"></i>
                                    <div class="text-left">
                                        <h3 class="text-lg font-bold text-yellow-800">High Demand Notice</h3>
                                        <p class="text-yellow-700 text-sm mt-1">
                                            <i class="bi bi-clock-fill mr-1"></i>
                                            <?php if ($order_status_lower === 'out_for_delivery'): ?>
                                                Due to traffic conditions, your delivery is taking a little longer.
                                            <?php else: ?>
                                                Due to high volume of orders, your food is taking a little longer to prepare.
                                            <?php endif; ?>
                                            We appreciate your patience!
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-center text-yellow-600">
                                    <?php if ($order_status_lower === 'out_for_delivery'): ?>
                                        <i class="bi bi-truck mr-2 blink"></i>
                                        <span class="text-sm font-semibold">Your order is on the way with extra care</span>
                                    <?php else: ?>
                                        <i class="bi bi-cup-hot-fill mr-2 blink"></i>
                                        <span class="text-sm font-semibold">Your order is being prepared with extra care</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($order_status_lower === 'completed'): ?>
                            <div class="bg-green-100 border border-green-400 rounded-2xl p-2">
                                <div class="flex items-center justify-center">
                                    <i class="bi bi-check-circle-fill text-green-600 text-2xl mr-3"></i>
                                    <div class="text-left">
                                        <h3 class="text-lg font-bold text-green-800">Order Completed!</h3>
                                        <p class="text-green-700 text-sm mt-1">
                                            <?php if ($is_delivery_order): ?>
                                                Your order has been delivered. Thank you for your order!
                                            <?php else: ?>
                                                Your order has been completed. Thank you for your order!
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

                        <div class="order-status-container p-3 sm:p-3">
                <?php if ($is_cancelled): ?>
                    <div class="bg-red-100 border-2 border-red-500 rounded-2xl p-6 text-center">
                        <div class="flex items-center justify-center">
                             <div class="w-16 h-16 flex items-center justify-center rounded-full bg-red-600 text-white text-4xl shrink-0 mr-4">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div class="text-left">
                                <h3 class="text-2xl font-bold text-red-700">Order Cancelled</h3>
                                <p class="text-gray-600">This order was cancelled by the restaurant.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="relative w-full">
                        <div class="absolute top-6 left-0 w-full h-1 bg-gray-200" style="transform: translateY(-50%);"></div>
                        <div class="absolute top-6 left-0 h-1 bg-orange-500" style="transform: translateY(-50%); width: <?= ($current_step_index / (count($simplified_statuses) - 1)) * 100 ?>%; transition: width 0.5s ease;"></div>
                        
                        <div class="flex justify-between items-start relative">
                            <?php 
                            $visible_steps = $is_delivery_order ? count($simplified_statuses) : count($simplified_statuses) - 1;
                            $step_width = 100 / ($visible_steps - 1);
                            
                            foreach ($simplified_statuses as $step_name => $step_info):
                                $step_index = array_search($step_name, array_keys($simplified_statuses));
                                
                                // Skip "Out for Delivery" step for non-delivery orders
                                if (!$is_delivery_order && $step_name === 'Out for Delivery') {
                                    continue;
                                }
                                
                                // Adjust step index for non-delivery orders
                                $display_step_index = $step_index;
                                if (!$is_delivery_order && $step_index > 2) {
                                    $display_step_index = $step_index - 1;
                                }
                                
                                // Special logic: When order is "ready" for delivery orders, show both Ready and Out for Delivery as completed
                                $is_completed = $display_step_index < $current_step_index;
                                $is_active = $display_step_index === $current_step_index;
                                
                                // If order is ready and this is a delivery order, mark both Ready and Out for Delivery as completed
                                if ($show_both_ready_and_delivery_completed) {
                                    if ($step_name === 'Ready' || $step_name === 'Out for Delivery') {
                                        $is_completed = true;
                                        $is_active = false;
                                    } elseif ($step_name === 'Completed') {
                                        $is_completed = false;
                                        $is_active = false;
                                    }
                                }
                                
                                $circle_class = 'bg-gray-300 border-gray-300';
                                $icon_class = 'text-gray-500';
                                $text_class = 'text-gray-500';
                                $animation_class = '';

                                if ($is_completed) {
                                    $circle_class = 'bg-green-500 border-green-500';
                                    $icon_class = 'text-white';
                                    $text_class = 'text-green-600';
                                } elseif ($is_active) {
                                    $circle_class = 'bg-orange-500 border-orange-500';
                                    $icon_class = 'text-white';
                                    $text_class = 'text-orange-600 font-bold';
                                    $animation_class = 'animate-pulse-active';
                                }
                            ?>
                                <div class="flex flex-col items-center text-center z-10" style="width: <?= $step_width ?>%;">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center border-4 transition-colors duration-300 <?= $circle_class ?> <?= $animation_class ?>">
                                        <i class="bi <?= $step_info['icon'] ?> text-2xl <?= $icon_class ?>"></i>
                                    </div>
                                    <h3 class="mt-2 text-sm sm:text-base font-semibold transition-colors duration-300 <?= $text_class ?>"><?= $step_name ?></h3>
                                </div>
                            <?php endforeach; ?>
                        </div>
                         <?php
                            $active_step_key = array_keys($simplified_statuses)[$current_step_index];
                            $active_description = $simplified_statuses[$active_step_key]['description'];
                            
                            // Special description when order is ready for delivery
                            if ($show_both_ready_and_delivery_completed) {
                                $active_description = '✅ Your order is out for delivery.';
                            }
                         ?>
                        <p class="text-center text-gray-700 mt-2 text-m"><strong>Current Status:</strong> <?= $active_description ?></p>

                        <div class="text-center mt-4 mb-4">
                            <span class="write_feedback bg-orange-500 text-white text-sm font-semibold px-3 py-1.5 rounded-full">Click to rate your order ⭐</span>
                        </div>

                        <style>
.write_feedback {
    cursor: pointer;
    border: 2px solid #fd7e14;
    text-align: center;
    transition: 0.3s;
    color: #fff;
    transform: scale(1);
    animation: borderPulse 2s infinite;
    font-size: 15px;
}

@keyframes borderPulse {
    0% {
        box-shadow: 0 0 0 0 #fd7e14b3;
    }
    70% {
        box-shadow: 0 0 0 10px #fd7e1400;
    }
    100% {
        box-shadow: 0 0 0 0 #fd7e1400;
    }
}
                        </style>

                        <script>
                        // Scroll to review section when clicking on feedback text with 150px offset
                        document.addEventListener('DOMContentLoaded', function() {
                            const feedbackElement = document.querySelector('.write_feedback');
                            
                            if (feedbackElement) {
                                feedbackElement.addEventListener('click', function() {
                                    // Find the review section
                                    const reviewSection = document.querySelector('.rating-card');
                                    
                                    if (reviewSection) {
                                        // Calculate the position with 150px offset from top
                                        const elementPosition = reviewSection.getBoundingClientRect().top;
                                        const offsetPosition = elementPosition + window.pageYOffset - 120;
                                        
                                        // Smooth scroll to the position
                                        window.scrollTo({
                                            top: offsetPosition,
                                            behavior: 'smooth'
                                        });
                                        
                                        // Optional: Add a visual highlight effect
                                        reviewSection.style.transition = 'all 0.5s ease';
                                        reviewSection.style.boxShadow = '0 0 0 3px rgba(255, 107, 53, 0.3)';
                                        
                                        setTimeout(() => {
                                            reviewSection.style.boxShadow = 'none';
                                        }, 2000);
                                    }
                                });
                                
                                // Change cursor to pointer to indicate it's clickable
                                feedbackElement.style.cursor = 'pointer';
                            }
                        });
                        </script>
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-3 bg-gray-50 border-t border-b border-gray-200">
                <div class="bg-white rounded-2xl shadow-inner p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800"><i class="bi bi-receipt mr-2 text-orange-500"></i>Order Summary</h2>
                    
                    <div class="space-y-4 mb-2">
                        <h3 class="font-semibold text-gray-700 mb-3">Items Ordered:</h3>
                        <?php foreach ($order_items as $item): ?>
                            <div class="flex justify-between items-center pb-2 border-b border-gray-300 last:border-b-0">
                                <div>
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($item['product_name']) ?></span>
                                    <small class="text-gray-500 block">x <?= $item['quantity'] ?> @ ₹<?= number_format($item['price']) ?> each</small>
                                </div>
                                <span class="font-bold text-gray-900">₹<?= number_format($item['price'] * $item['quantity']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t pt-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">Subtotal:</span>
                            <span class="font-medium text-gray-900">₹<?= number_format($order['subtotal']) ?></span>
                        </div>

                        <?php if ($order['discount_amount'] > 0): ?>
                            <div class="flex justify-between items-center text-green-600">
                                <span>
                                    Discount 
                                    <?php if (!empty($order['discount_type'])): ?>
                                        <small class="text-gray-500">(<?= htmlspecialchars($order['discount_type']) ?>)</small>
                                    <?php endif; ?>
                                </span>
                                <span class="font-medium">-₹<?= number_format($order['discount_amount']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($order['gst_amount'] > 0): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">GST Charges:</span>
                                <span class="font-medium text-gray-900">₹<?= number_format($order['gst_amount']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($order['delivery_charge'] > 0): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Delivery Charge:</span>
                                <span class="font-medium text-gray-900">₹<?= number_format($order['delivery_charge']) ?></span>
                            </div>
                        <?php elseif ($order['order_type'] === 'delivery'): ?>
                            <div class="flex justify-between items-center text-green-600">
                                <span class="text-gray-700">Delivery Charge:</span>
                                <span class="font-medium">FREE</span>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between items-center font-bold mt-4 pt-4 border-t-2 text-2xl">
                            <span class="text-gray-900">Total Amount:</span>
                            <span class="text-orange-600">₹<?= number_format($order['total_amount']) ?></span>
                        </div>
                    </div>
                </div>






<!-- QR Code Payment Section -->
<?php 
// Check if we should show payment section
$show_payment_section = !$is_cancelled && 
                       $order['status'] !== 'completed' && 
                       $order['status'] !== 'delivered' && 
                       $qr_code; // Only show if QR code exists
?>

<?php if ($show_payment_section): ?>
    <div class="bg-white rounded-2xl shadow-inner p-6">
        <h2 class="text-xl font-bold mb-4 text-gray-800">
            <i class="bi bi-qr-code mr-2 text-green-500"></i>Payment Information
        </h2>
        
        <div class="text-center">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-700 mb-2">
                    Scan QR Code to Pay
                </h3>
                <p class="text-sm text-gray-600 mb-4">
                    Total Amount: <span class="font-bold text-green-600">₹<?= number_format($order['total_amount']) ?></span>
                </p>
            </div>
            
            <div class="flex flex-col md:flex-row items-center justify-center gap-8">
                <!-- QR Code Image -->
                <div class="text-center">
                    <div class="bg-white p-2 rounded-2xl shadow-lg inline-block border-2 border-green-200">
                        <img src="uploads/qrcodes/<?= htmlspecialchars($qr_code['upload_qr_code']) ?>" 
                             alt="Payment QR Code" 
                             class="w-48 h-48 object-contain mx-auto"
                             id="qrCodeImage">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <?= htmlspecialchars($qr_code['payment_type']) ?> QR Code
                    </p>
                    
                    <!-- Download QR Button -->
                    <button onclick="downloadQRCode()" 
                            class="mt-3 bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-300 flex items-center justify-center mx-auto">
                        <i class="bi bi-download mr-2"></i> Download & Scan QR
                    </button>
                </div>
                
                <!-- Payment Details -->
                <div class="text-left space-y-3">


<?php if (!empty($qr_code['upi_id'])): ?>
    <div class="bg-gray-50 p-3 rounded-lg text-center">
        <p class="text-sm text-gray-500 font-medium">UPI ID</p>
        <p class="text-gray-800 font-semibold text-lg"><?= htmlspecialchars($qr_code['upi_id']) ?></p>
        <button onclick="copyToClipboard('<?= htmlspecialchars($qr_code['upi_id']) ?>', this)" 
                class="mt-1 text-xs bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded transition-colors">
            <i class="bi bi-copy mr-1"></i>Copy UPI ID
        </button>
    </div>
<?php endif; ?>
                    
                    <?php if (!empty($qr_code['mobile_number'])): ?>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="text-sm text-gray-500 font-medium">Mobile Number</p>
                            <p class="text-gray-800 font-semibold"><?= htmlspecialchars($qr_code['mobile_number']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="bg-green-50 border border-green-200 p-3 rounded-lg">
                        <p class="text-sm text-green-700">
                            <i class="bi bi-info-circle mr-1"></i>
                            Scan the QR code using any UPI app to complete your payment
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Payment Instructions -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl p-1">
                <h4 class="font-semibold text-blue-800 mb-2 mt-3">How to Pay</h4>
                <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1 text-left max-w-md mx-auto">
                    <li>Open your UPI payment app (Google Pay, PhonePe, Paytm, etc.)</li>
                    <li>Tap on "Scan QR Code"</li>
                    <li>Scan the QR code shown above</li>
                    <li>Verify the amount (₹<?= number_format($order['total_amount']) ?>) and pay</li>
                    <li>Take a screenshot of the payment confirmation</li>
                </ol>
            </div>
        </div>
    </div>

    <script>
function copyToClipboard(text, buttonElement) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const originalText = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="bi bi-check2 mr-1"></i>Copied!';
        buttonElement.classList.remove('bg-blue-500', 'hover:bg-blue-600');
        buttonElement.classList.add('bg-green-500', 'hover:bg-green-600');
        
        setTimeout(() => {
            buttonElement.innerHTML = originalText;
            buttonElement.classList.remove('bg-green-500', 'hover:bg-green-600');
            buttonElement.classList.add('bg-blue-500', 'hover:bg-blue-600');
        }, 2000);
    }).catch(function(err) {
        console.error('Failed to copy text: ', err);
        alert('Failed to copy UPI ID. Please copy manually.');
    });
}

    function downloadQRCode() {
        const qrCodeImage = document.getElementById('qrCodeImage');
        const qrCodeUrl = qrCodeImage.src;
        
        // Create a temporary anchor element
        const downloadLink = document.createElement('a');
        downloadLink.href = qrCodeUrl;
        
        // Extract filename from URL or create a custom one
        const fileName = 'payment-qr-code-<?= htmlspecialchars($order_id) ?>.png';
        downloadLink.download = fileName;
        
        // Append to body, click and remove
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        
        // Optional: Show download confirmation
        const downloadButton = event.target;
        const originalText = downloadButton.innerHTML;
        downloadButton.innerHTML = '<i class="bi bi-check2 mr-2"></i>Downloaded!';
        downloadButton.classList.remove('bg-green-500', 'hover:bg-green-600');
        downloadButton.classList.add('bg-blue-500', 'hover:bg-blue-600');
        
        setTimeout(() => {
            downloadButton.innerHTML = originalText;
            downloadButton.classList.remove('bg-blue-500', 'hover:bg-blue-600');
            downloadButton.classList.add('bg-green-500', 'hover:bg-green-600');
        }, 2000);
    }
    </script>
<?php endif; ?>







                <div class="bg-white rounded-2xl shadow-inner p-6 mt-6">
                    <h2 class="text-xl font-bold mb-4 text-gray-800"><i class="bi bi-person mr-2 text-orange-500"></i>Customer Information</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Name</p>
                                <p class="text-gray-800 font-semibold"><?= htmlspecialchars($order['customer_name']) ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Phone</p>
                                <p class="text-gray-800 font-semibold"><?= htmlspecialchars($order['customer_phone']) ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Order Type</p>
                                <span class="bg-orange-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full">
                                    <?= ucfirst($order['order_type']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <?php if ($order['order_type'] === 'delivery' && !empty($order['delivery_address'])): ?>
                                <div>
                                    <p class="text-sm text-gray-500 font-medium">Delivery Address</p>
                                    <p class="text-gray-800"><?= htmlspecialchars($order['delivery_address']) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($order['order_type'] === 'dining' && !empty($order['table_number'])): ?>
                                <div>
                                    <p class="text-sm text-gray-500 font-medium">Table Number</p>
                                    <p class="text-gray-800 font-semibold">Table <?= htmlspecialchars($order['table_number']) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($order['order_notes'])): ?>
                                <div>
                                    <p class="text-sm text-gray-500 font-medium">Order Notes</p>
                                    <p class="text-gray-800 italic">"<?= htmlspecialchars($order['order_notes']) ?>"</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <div class="flex justify-between items-center text-sm text-gray-500">
                            <div>
                                <p class="font-medium">Order Placed</p>
                                <p><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
                            </div>
                            <?php if (!empty($order['updated_at']) && $order['updated_at'] !== $order['created_at']): ?>
                                <div class="text-right">
                                    <p class="font-medium">Last Updated</p>
                                    <p><?= date('M j, Y g:i A', strtotime($order['updated_at'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>













<?php
// Get phone number from multiple sources
$phone_number = $business_info['phone_number'] ?? $user['phone'] ?? '';

// Get WhatsApp number
$whatsapp_url = $social_link['whatsapp'] ?? '';
$whatsapp_phone = '';

if (!empty($whatsapp_url)) {
    // Extract phone number from WhatsApp URL
    if (preg_match('/wa\.me\/(\d+)/', $whatsapp_url, $matches)) {
        $whatsapp_phone = $matches[1];
    } elseif (preg_match('/phone=(\d+)/', $whatsapp_url, $matches)) {
        $whatsapp_phone = $matches[1];
    }
}

// If no WhatsApp URL found, use phone number for WhatsApp
if (empty($whatsapp_phone) && !empty($phone_number)) {
    $whatsapp_phone = preg_replace('/[^0-9]/', '', $phone_number);
    // Add country code if it's a 10-digit number
    if (strlen($whatsapp_phone) === 10) {
        $whatsapp_phone = '91' . $whatsapp_phone;
    }
}

// Only show the section if we have at least one contact method
if (!empty($phone_number) || !empty($whatsapp_phone)):
?>

<!-- Order Status Contact Section -->
<div class="p-6 text-center border-t border-gray-200 bg-gradient-to-r from-purple-50 to-indigo-50">
    <div class="max-w-md mx-auto">
        <h3 class="text-xl font-bold text-gray-800 mb-3">
            <i class="bi bi-info-circle-fill text-purple-600 mr-2"></i>
            To know your order status
        </h3>
        <p class="text-gray-600 mb-4 text-sm">
            For real-time updates on your order #<?= htmlspecialchars(substr($order_id, -6)) ?>, contact us directly
        </p>
        
        <div class="flex flex-col sm:flex-row justify-center items-center gap-3 mb-4">
            <?php if (!empty($phone_number)): ?>
                <a href="tel:<?= htmlspecialchars($phone_number) ?>"
                   class="w-full sm:w-auto flex items-center justify-center bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-full transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="bi bi-telephone-fill mr-2"></i> Call Now
                </a>
            <?php endif; ?>
            
            <?php if (!empty($whatsapp_phone)): ?>
                <a href="https://wa.me/<?= htmlspecialchars($whatsapp_phone) ?>?text=Hi, I would like to know the status of my Order id : <?= htmlspecialchars($order_id) ?> - DEEGEECARD"
                   target="_blank"
                   class="w-full sm:w-auto flex items-center justify-center bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-3 px-6 rounded-full transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="bi bi-whatsapp mr-2"></i> WhatsApp Chat Support
                </a>
            <?php endif; ?>
        </div>
        
        <p class="text-xs text-gray-500 mt-2">
            We're here to help you track your order in real-time
        </p>
    </div>
</div>

<?php endif; ?>






<!-- Customer Reviews Section -->
<div class="p-6 border-t border-gray-200 bg-white">
    

   


<!-- Review Form -->
<div class="rating-card bg-gradient-to-br from-orange-50 to-orange-100 rounded-2xl p-6 border border-orange-200 max-w-md w-full">
        <h6 class="text-xl font-bold mb-4 text-gray-800">
            <i class="bi bi-pencil-square mr-2 text-orange-500"></i>Leave a Review
        </h6>
        <form method="POST" action="submit_review.php">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <input type="hidden" name="profile_url" value="<?= htmlspecialchars($profile_url) ?>">
            
            <!-- Name field is now full width -->
            <div class="mb-4">
                <label for="reviewer_name" class="block text-sm font-medium text-gray-700 mb-1">Your Name*</label>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                       id="reviewer_name" name="reviewer_name" required>
            </div>
            
            <!-- Hidden email field -->
            <div style="display: none;">
                <label for="reviewer_email" class="block text-sm font-medium text-gray-700 mb-1">Your Email</label>
                <input type="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                       id="reviewer_email" name="reviewer_email">
            </div>
            
            <!-- Phone field -->
            <div class="mb-4">
                <label for="reviewer_phone" class="block text-sm font-medium text-gray-700 mb-1">Your Phone*</label>
                <input type="tel" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                       id="reviewer_phone" name="reviewer_phone" 
                       pattern="[0-9]{10}" title="Please enter exactly 10 digits" 
                       maxlength="10" required>
            </div>

            <!-- Rating section -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Rating*</label>
                <div class="rating-input flex space-x-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="flex items-center">
                        <input class="hidden" type="radio" name="rating" id="rating<?= $i ?>" 
                               value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?> required>
                        <label for="rating<?= $i ?>" class="cursor-pointer text-2xl <?= $i === 5 ? 'text-orange-500' : 'text-gray-300' ?> hover:text-orange-400 transition-colors">
                            ★
                        </label>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Feedback section -->
            <div class="mb-4">
                <label for="feedback" class="block text-sm font-medium text-gray-700 mb-1">Your Feedback*</label>
                <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                          id="feedback" name="feedback" rows="3" placeholder="Share your experience with us..." required></textarea>
            </div>
            
            <!-- Submit button -->
            <button type="submit" name="submit_rating" 
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-6 rounded-lg transition-all duration-300 transform hover:scale-105 shadow-md hover:shadow-lg">
                <i class="bi bi-star-fill mr-2"></i>Submit Review
            </button>
        </form>
    </div>

<!-- JavaScript for star rating interaction -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const starInputs = document.querySelectorAll('.rating-input input[type="radio"]');
    const starLabels = document.querySelectorAll('.rating-input label');
    
    starInputs.forEach((input, index) => {
        input.addEventListener('change', function() {
            // Update all stars
            starLabels.forEach((label, labelIndex) => {
                if (labelIndex <= index) {
                    label.classList.remove('text-gray-300');
                    label.classList.add('text-orange-500');
                } else {
                    label.classList.remove('text-orange-500');
                    label.classList.add('text-gray-300');
                }
            });
        });
    });
    
    // Add hover effect
    starLabels.forEach((label, index) => {
        label.addEventListener('mouseenter', function() {
            starLabels.forEach((label, labelIndex) => {
                if (labelIndex <= index) {
                    label.classList.add('text-orange-400');
                }
            });
        });
        
        label.addEventListener('mouseleave', function() {
            const checkedInput = document.querySelector('.rating-input input[type="radio"]:checked');
            if (checkedInput) {
                const checkedIndex = Array.from(starInputs).indexOf(checkedInput);
                starLabels.forEach((label, labelIndex) => {
                    label.classList.remove('text-orange-400');
                    if (labelIndex <= checkedIndex) {
                        label.classList.add('text-orange-500');
                    } else {
                        label.classList.add('text-gray-300');
                    }
                });
            }
        });
    });
});
</script>






        </div>
    </div>










<!-- Then the existing Back to Menu button -->
<div class="p-6 text-center">
    <button onclick="goBackToMenu('<?= htmlspecialchars($order_id) ?>')"
            class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-full transition-all duration-300 shadow-md hover:shadow-lg">
        <i class="bi bi-arrow-left mr-2"></i> Back to Menu
    </button>
</div>





<script>
function goBackToMenu(orderId) {
    // Store the order ID in localStorage
    localStorage.setItem('lastOrderId', orderId);
    localStorage.setItem('lastOrderUserId', '<?= $user_id ?>');
    
    // Redirect to profile page
    window.location.href = 'https://deegeecard.com/<?= htmlspecialchars($back_url) ?>';
}
</script>


<script>
// Update the progress bar width calculation
function updateProgressBar() {
    const progressBar = document.querySelector('.absolute.top-6.left-0.h-1.bg-orange-500');
    const visibleSteps = <?= $is_delivery_order ? count($simplified_statuses) : count($simplified_statuses) - 1 ?>;
    
    // Special case: when order is ready for delivery, show progress up to "Out for Delivery" step
    let progressWidth;
    if (<?= $show_both_ready_and_delivery_completed ? 'true' : 'false' ?>) {
        progressWidth = (3 / (visibleSteps - 1)) * 100; // Show progress up to "Out for Delivery"
    } else {
        progressWidth = (<?= $current_step_index ?> / (visibleSteps - 1)) * 100;
    }
    
    if (progressBar) {
        progressBar.style.width = progressWidth + '%';
    }
}

// Call this on page load
document.addEventListener('DOMContentLoaded', function() {
    updateProgressBar();
});
</script>





    <script>
        // Countdown Timer Functionality
        <?php if ($show_countdown): ?>
        let countdownTime = <?= $time_remaining ?>; // Time in seconds
        
        function updateCountdown() {
            if (countdownTime <= 0) {
                // Countdown finished, show high demand message
                document.getElementById('countdownSection').innerHTML = `
                    <div class="bg-yellow-100 border border-yellow-400 rounded-2xl p-4">
                        <div class="flex items-center justify-center">
                            <i class="bi bi-exclamation-triangle-fill text-yellow-600 text-2xl mr-3"></i>
                            <div class="text-left">
                                <h3 class="text-lg font-bold text-yellow-800">High Demand Notice</h3>
                                <p class="text-yellow-700 text-sm mt-1">
                                    <i class="bi bi-clock-fill mr-1"></i>
                                    Due to high volume of orders, your food is taking a little longer to prepare.
                                    We appreciate your patience!
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-center text-yellow-600">
                            <i class="bi bi-cup-hot-fill mr-2 blink"></i>
                            <span class="text-sm font-semibold">Your order is being prepared with extra care</span>
                        </div>
                    </div>
                `;
                return;
            }
            
            const minutes = Math.floor(countdownTime / 60);
            const seconds = countdownTime % 60;
            
            document.getElementById('countdownMinutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('countdownSeconds').textContent = seconds.toString().padStart(2, '0');
            
            countdownTime--;
            
            setTimeout(updateCountdown, 1000);
        }
        
        // Calculate and display estimated completion time
        const estimatedTime = new Date(<?= $estimated_completion_time * 1000 ?>);
        document.getElementById('estimatedTime').textContent = estimatedTime.toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        // Start the countdown
        updateCountdown();
        <?php endif; ?>

        // Live order status updates
        let currentStatus = '<?= $order_status_lower ?>';
        let isCancelled = <?= $is_cancelled ? 'true' : 'false' ?>;

        function refreshOrderStatus() {
            const refreshIndicator = document.getElementById('refreshIndicator');
            refreshIndicator.style.display = 'block';

            // --- MODIFICATION START: Added cache: 'no-cache' option ---
            fetch(`get_order_status.php?order_id=<?= $order_id ?>`, { cache: 'no-cache' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const newStatus = data.status.toLowerCase();
                        const newIsCancelled = data.status.toLowerCase() === 'cancelled';
                        if (newStatus !== currentStatus || newIsCancelled !== isCancelled) {
                            location.reload();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing status:', error);
                })
                .finally(() => {
                    setTimeout(() => {
                        refreshIndicator.style.display = 'none';
                    }, 1000);
                });
            // --- MODIFICATION END ---
        }

        // Auto-refresh every 10 seconds if order is not completed or cancelled
        <?php if (!in_array($order_status_lower, ['completed', 'cancelled', 'delivered'])): ?>
        setInterval(() => {
            refreshOrderStatus();
        }, 10000); // 10 seconds
        <?php endif; ?>
    </script>










<!-- Add this to order_status.php -->
<script>
// Send pending WhatsApp message when order status page loads
document.addEventListener('DOMContentLoaded', function() {
    // Wait a moment for the page to fully load
    setTimeout(function() {
        const message = localStorage.getItem('pendingWhatsAppMessage');
        const orderId = localStorage.getItem('pendingWhatsAppOrderId');
        
        if (message && orderId) {
            // Get WhatsApp number from PHP variables (you'll need to pass these to order_status.php)
            const whatsappLink = '<?= $social_link['whatsapp'] ?? '' ?>';
            let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || '<?= $user['phone'] ?? '' ?>';
            
            if (phoneNumber) {
                const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
                
                // Open WhatsApp in new tab
                window.open(whatsappUrl, '_blank');
            }
            
            // Clean up
            localStorage.removeItem('pendingWhatsAppMessage');
            localStorage.removeItem('pendingWhatsAppOrderId');
        }
    }, 1000); // 1 second delay to ensure page is fully loaded
});
</script>
</body>
</html>
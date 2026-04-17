<?php
// orders.php - Order Management System with Bill Printing
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// Enhanced Android Session Protection
require_once 'enhanced_android_manager.php';


// Force session maintenance for Android
if (isset($_SESSION['user_id'])) {
    $androidSessionManager->maintainAndroidSession();
}

require 'db_connection.php';

// Authentication check - ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success';

// Fetch user details for display including country
$sql = "SELECT name, email, phone, address, role, country FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role, $user_country);
$stmt->fetch();
$stmt->close();

// Set timezone based on user's country
switch ($user_country) {
    case 'India':
        date_default_timezone_set('Asia/Kolkata');
        $db_timezone = 'Asia/Kolkata';
        break;
    case 'UAE':
        date_default_timezone_set('Asia/Dubai');
        $db_timezone = 'Asia/Kolkata'; // Important: Keep database timezone as India for consistency
        break;
    case 'UK':
        date_default_timezone_set('Europe/London');
        $db_timezone = 'Europe/London';
        break;
    case 'USA':
        date_default_timezone_set('America/New_York');
        $db_timezone = 'America/New_York';
        break;
    default:
        date_default_timezone_set('Asia/Kolkata'); // Default fallback
        $db_timezone = 'Asia/Kolkata';
}

// Function to adjust time for UAE users (subtract 1 hour 30 minutes)
function adjustTimeForUAE($dateTime, $user_country) {
    if ($user_country == 'UAE') {
        $date = new DateTime($dateTime);
        $date->modify('-1 hour -30 minutes');
        return $date->format('Y-m-d H:i:s');
    }
    return $dateTime;
}

// Function to display time with UAE adjustment
function displayTime($dateTime, $user_country) {
    $date = new DateTime($dateTime);
    
    if ($user_country == 'UAE') {
        $date->modify('-1 hour -30 minutes');
    }
    
    return $date->format('d/m/Y h:i A');
}

// Date range handling with validation
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Validate date format to prevent SQL injection
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = date('Y-m-d');
}
if ($to_date < $from_date) {
    $to_date = $from_date;
}

// Function to get currency symbol based on country
function getCurrencySymbol($country) {
    $currencySymbols = [
        'India' => '₹',
        'UAE' => 'AED',
        'UK' => '£',
        'USA' => '$'
    ];
    
    return isset($currencySymbols[$country]) ? $currencySymbols[$country] : '₹';
}

// Get currency symbol for current user
$currencySymbol = getCurrencySymbol($user_country);

// Set tax label based on country (GST for India, VAT for UAE)
$taxLabel = ($user_country == 'UAE') ? 'VAT' : 'GST';

// Fetch business information for bill header
$business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $user_id);
$business_stmt->execute();
$business_stmt->bind_result($business_name, $business_address);
$business_stmt->fetch();
$business_stmt->close();

// Set default business info if not available
if (empty($business_name)) {
    $business_name = "Your Restaurant";
    $business_address = "123 Restaurant Street, City";
}

// Handle order status update via POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $allowed_statuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        $message = "Invalid status selected";
        $message_type = "danger";
    } else {
        // Verify order belongs to current user
        $check_sql = "SELECT user_id FROM orders WHERE order_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $order_id);
        $check_stmt->execute();
        $check_stmt->bind_result($order_user_id);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($order_user_id == $user_id) {
            $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_status, $order_id);
            
            if ($update_stmt->execute()) {
                $message = "Order status updated successfully!";
            } else {
                $message = "Error updating order status: " . $conn->error;
                $message_type = "danger";
            }
            $update_stmt->close();
        } else {
            $message = "You don't have permission to update this order.";
            $message_type = "danger";
        }
    }
}

// Handle order cancellation via POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    
    $check_sql = "SELECT user_id, status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_user_id, $current_status);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_user_id == $user_id) {
        // Only allow cancellation for orders in certain statuses
        if (in_array($current_status, ['Pending', 'Confirmed', 'Preparing'])) {
            $update_sql = "UPDATE orders SET status = 'Cancelled' WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $order_id);
            
            if ($update_stmt->execute()) {
                $message = "Order cancelled successfully!";
            } else {
                $message = "Error cancelling order: " . $conn->error;
                $message_type = "danger";
            }
            $update_stmt->close();
        } else {
            $message = "Order cannot be cancelled at this stage.";
            $message_type = "danger";
        }
    } else {
        $message = "You don't have permission to cancel this order.";
        $message_type = "danger";
    }
}

// Handle AJAX status updates for real-time updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_status'])) {
    header('Content-Type: application/json');
    
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $allowed_statuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    $check_sql = "SELECT user_id FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_user_id);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_user_id == $user_id) {
        $update_sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating order status']);
        }
        $update_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
    }
    exit();
}

// Handle AJAX order cancellation for real-time updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cancel_order'])) {
    header('Content-Type: application/json');
    
    $order_id = $_POST['order_id'];
    
    $check_sql = "SELECT user_id, status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_user_id, $current_status);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_user_id == $user_id) {
        if (in_array($current_status, ['Pending', 'Confirmed', 'Preparing'])) {
            $update_sql = "UPDATE orders SET status = 'Cancelled', updated_at = NOW() WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $order_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Order cancelled successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error cancelling order']);
            }
            $update_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled at this stage']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
    }
    exit();
}

// Pagination setup for better performance with large datasets
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 200;
$offset = ($page - 1) * $per_page;

// FIX FOR UAE TIMEZONE ISSUE: Adjust date filtering for UAE users
if ($user_country == 'UAE') {
    // For UAE users, we need to adjust the date range to account for timezone difference
    // When UAE user selects a date, we need to include orders from Indian time perspective
    
    // Convert UAE date to Indian time for filtering
    $from_date_obj = new DateTime($from_date . ' 00:00:00', new DateTimeZone('Asia/Dubai'));
    $from_date_obj->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $from_date_adjusted = $from_date_obj->format('Y-m-d');
    
    $to_date_obj = new DateTime($to_date . ' 23:59:59', new DateTimeZone('Asia/Dubai'));
    $to_date_obj->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $to_date_adjusted = $to_date_obj->format('Y-m-d');
    
    // Use CONVERT_TZ to properly handle timezone conversion in query
    $date_condition = "DATE(CONVERT_TZ(created_at, '+00:00', '+05:30')) BETWEEN ? AND ?";
    $date_params = [$from_date_adjusted, $to_date_adjusted];
} else {
    // For other countries, use standard date filtering
    $date_condition = "DATE(created_at) BETWEEN ? AND ?";
    $date_params = [$from_date, $to_date];
}

// Get total count of orders for pagination
$count_sql = "SELECT COUNT(*) FROM orders WHERE user_id = ? AND $date_condition";
$count_stmt = $conn->prepare($count_sql);
if ($user_country == 'UAE') {
    $count_stmt->bind_param("iss", $user_id, $date_params[0], $date_params[1]);
} else {
    $count_stmt->bind_param("iss", $user_id, $date_params[0], $date_params[1]);
}
$count_stmt->execute();
$count_stmt->bind_result($total_orders);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_orders / $per_page);

// Fetch orders with items for display
$orders_sql = "SELECT 
    o.order_id, 
    o.customer_name, 
    o.customer_phone, 
    o.order_type, 
    o.delivery_address, 
    o.table_number, 
    o.status, 
    o.subtotal, 
    o.discount_amount, 
    o.discount_type, 
    o.gst_amount, 
    o.delivery_charge, 
    o.total_amount, 
    o.created_at,
    o.order_notes,
    o.updated_at,
    COUNT(oi.item_id) as item_count
FROM orders o
LEFT JOIN order_items oi ON o.order_id = oi.order_id
WHERE o.user_id = ? AND $date_condition
GROUP BY o.order_id
ORDER BY o.created_at DESC
LIMIT ? OFFSET ?";

$orders_stmt = $conn->prepare($orders_sql);

// Bind parameters based on country
if ($user_country == 'UAE') {
    // For UAE, $date_params contains [$from_date_adjusted, $to_date_adjusted]
    $orders_stmt->bind_param("issii", $user_id, $date_params[0], $date_params[1], $per_page, $offset);
} else {
    // For other countries, $date_params contains [$from_date, $to_date]
    $orders_stmt->bind_param("issii", $user_id, $date_params[0], $date_params[1], $per_page, $offset);
}

$orders_stmt->execute();
$result = $orders_stmt->get_result();
$orders = [];

// Process each order and fetch its items
while ($order = $result->fetch_assoc()) {
    $items_sql = "SELECT product_name, price, quantity FROM order_items WHERE order_id = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $order['order_id']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $order['items'] = $items_result->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();
    
    // Calculate timer information for order preparation tracking
    $order_created = strtotime($order['created_at']);
    $current_time = time();
    $time_elapsed = $current_time - $order_created;
    $time_limit = 30 * 60; // 30 minutes in seconds
    $time_remaining = $time_limit - $time_elapsed;
    
    $order['timer_remaining'] = max(0, $time_remaining);
    $order['is_delayed'] = $time_elapsed > $time_limit;
    $order['is_completed_on_time'] = ($order['status'] === 'Completed' && !$order['is_delayed']);
    
    // Store original created_at for JavaScript
    $order['created_at_original'] = $order['created_at'];
    
    $orders[] = $order;
}
$orders_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Order Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="msapplication-TileImage" content="https://deegeecard.com/images/dg_logo.png">
    <meta name="application-name" content="DeeGeeCard">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- PWA Meta Tags -->

    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css?<?php echo time(); ?>" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">

<style>
    /* Table and layout styling */
    .table tbody tr:last-child td {
        border-bottom: 1px solid #dee2e6 !important;
    }
    .btn {
      padding: 20px 5px;
      min-width: 85px;
    }
    .table > :not(caption) > * > * {
        padding: 5px;
    }
    
    /* Status badge styling for visual order status indication */
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-weight: 700;
        font-size: .8em
    }
    .status-Pending { background-color: #ffc107; color: #000 }
    .status-Confirmed { background-color: #17a2b8; color: #fff }
    .status-Preparing { background-color: #fd7e14; color: #fff }
    .status-Ready { background-color: #28a745; color: #fff }
    .status-Completed { background-color: orange; color: #fff }
    .status-Cancelled { background-color: #dc3545; color: #fff }
    
    /* Animation for refresh icons */
    .bi-arrow-repeat.spin {
        animation: 1s linear infinite spin
    }
    @keyframes spin {
        from { transform: rotate(0) }
        to { transform: rotate(360deg) }
    }
    
    /* Timer styling for order preparation tracking */
    .timer {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 6px;
        background-color: #000;
        font-weight: 700;
        color: #fff
    }
    .timer.warning {
        background-color: orange;
        color: #000
    }
    .timer.danger {
        background-color: red;
        color: #fff;
        animation: 1s infinite blink
    }
    @keyframes blink {
        0%, 50% { opacity: 1 }
        100%, 51% { opacity: .5 }
    }
    .timer-column {
        min-width: 120px
    }
    
    /* Button animations for better user interaction */
    .btn-info.update-status-btn,
    .btn-info.update-status-btn.wave-pulse,
    .btn-success.update-status-btn {
        position: relative;
        overflow: hidden
    }
    
    /* Success button animations */
    .btn-success.update-status-btn {
        border: 2px solid #198754
    }
    .btn-success.update-status-btn.pulse-border {
        animation: 2s infinite borderPulse
    }
    @keyframes borderPulse {
        0% { box-shadow: 0 0 0 0 rgba(25,135,84,.7); border-color: #198754 }
        70% { box-shadow: 0 0 0 10px rgba(25,135,84,0); border-color: #20c997 }
        100% { box-shadow: 0 0 0 0 rgba(25,135,84,0); border-color: #198754 }
    }
    
    /* Info button animations */
    .btn-info.update-status-btn {
        border: 2px solid #ff6c2f
    }
    .btn-info.update-status-btn.pulse-border {
        animation: 2s infinite borderPulseOrange
    }
    @keyframes borderPulseOrange {
        0% { box-shadow: 0 0 0 0 rgba(255,108,47,.7); border-color: #ff6c2f }
        70% { box-shadow: 0 0 0 10px rgba(255,108,47,0); border-color: #ff8c5a }
        100% { box-shadow: 0 0 0 0 rgba(255,108,47,0); border-color: #ff6c2f }
    }
    
    /* Mobile responsive design */
    @media (max-width: 768px) {
        .mobile_table .update-status-btn[data-new-status=Completed],
        .mobile_table .update-status-btn[data-new-status=Ready] {
            width: 100%;
            margin: 5px 0;
            display: block;
            padding: 10px 20px;
            font-size: 15px;
            text-align: left
        }
        .mobile_table td[data-label=Actions] {
            text-align: center
        }
        .timer-column {
            min-width: 100px
        }
        .mobile_table tr {
            position: relative
        }
        .mobile_table .table td.timer-column:before {
            display: none
        }
        .mobile_table .table td.timer-column {
            border-bottom: 0 !important;
        }
        .clountdown_group {
            position: absolute;
            top: 180px;
            z-index: 99;
            right: 28px
        }
        .mobile_table td[data-label="Actions"] {
            min-height: 80px;
        }
        .btn.btn-sm.btn-primary.view-order {
            margin-top: 0;
        }
        .copy-btn {
            padding: 5px 0;
        }
        #statusUpdateForm {
            width: 70%;
        }
        .btn.btn-secondary {
            padding: 20px 10px;
            min-width: 0;
        }
        #modalStatusSelect {
            padding: 8px;
        }
        .mobile_table .print-bill {
            width: 100%;
            margin: 5px 0;
            display: block;
            padding: 10px 20px;
            font-size: 15px;
            text-align: left;
        }
    }
    
    /* Print button styling */
    .btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #000;
    }
    .btn-warning:hover {
        background-color: #e0a800;
        border-color: #e0a800;
        color: #000;
    }
    
    /* Bill Preview Styling for 65mm thermal printer */
.bill-container {
    width: 65mm;
    max-width: 65mm;
    font-family: 'Arial';
    font-size: 12px;
    line-height: 1.2;
    background: white;
    padding: 0;
    margin: 0 auto;
    color: #000 !important; /* Force black text color */
}
.bill-header {
    text-align: center;
    margin-bottom: 5px;
    color: #000 !important;
}
.bill-header .business-name {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 2px;
    color: #000 !important;
}
.bill-header .business-address {
    font-size: 10px;
    margin-bottom: 2px;
    color: #000 !important;
}
.bill-header .business-phone {
    font-size: 10px;
    margin-bottom: 3px;
    color: #000 !important;
}
.bill-divider {
    border-bottom: 1px solid #000;
    margin: 3px 0;
}
.bill-double-divider {
    border-bottom: 2px solid #000;
    margin: 3px 0;
}
.bill-row {
    display: flex;
    justify-content: space-between;
    margin: 1px 0;
    color: #000 !important;
}
.bill-item-name {
    flex: 2;
    text-align: left;
    font-size: 11px;
    color: #000 !important;
}
.bill-item-qty {
    flex: 1;
    text-align: center;
    color: #000 !important;
}
.bill-item-price {
    flex: 1;
    text-align: right;
    color: #000 !important;
}
.bill-item-total {
    flex: 1;
    text-align: right;
    color: #000 !important;
}
.bill-summary-row {
    display: flex;
    justify-content: space-between;
    margin: 1px 0;
    color: #000 !important;
}
.bill-summary-label {
    flex: 2;
    text-align: left;
    color: #000 !important;
}
.bill-summary-value {
    flex: 1;
    text-align: right;
    color: #000 !important;
}
.bill-footer {
    margin-top: 5px;
    font-size: 10px;
    text-align: center;
    color: #000 !important;
}

/* Print specific styles for thermal printer output */
@media print {
    body * {
        visibility: hidden;
    }
    .bill-container, .bill-container * {
        visibility: visible;
        color: #000 !important; /* Force black text in print */
    }
    .bill-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 65mm;
        max-width: 65mm;
        color: #000 !important;
    }
    .modal-footer, .modal-header {
        display: none !important;
    }
}
@media (min-width: 576px) {
    .modal-sm {
        --bs-modal-width: 330px !important;
    }
}

/* Bill Preview Modal Responsive Styles */
#billPreviewModal .modal-dialog {
    max-width: 100%;
    margin: 0 auto;
}

#billPreviewModal .modal-content {
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

#billPreviewModal .modal-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 12px 15px;
}

#billPreviewModal .modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

#billPreviewModal .modal-body {
    padding: 15px;
    max-height: 70vh;
    overflow-y: auto;
    background: #fff;
}

#billPreviewModal .modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 12px 15px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

#billPreviewModal .btn {
    flex: 1;
    min-width: 120px;
    padding: 10px 15px;
    font-size: 14px;
    border-radius: 6px;
}

@media (min-width: 601px) {
    #billPreviewModal {
        width: 500px;
        left: 50%;
        top: 50px;
        margin: 0;
        margin-left: -250px;
        padding: 0;
    }
}


@media (max-width: 600px) {
    #billPreviewModal {
        width: 300px;
        left: 50%;
        top: 150px;
        margin: 0;
        margin-left: -150px;
        padding: 0;
    }
    #billPreviewModal .modal-dialog {
        margin: 0 auto;
        padding: 0 0 0 0;
    }
}

/* Mobile First - 400px and below */
@media (max-width: 400px) {
    #billPreviewModal .modal-dialog {
        max-width: calc(100% - 20px);
    }
    
    #billPreviewModal .modal-content {
        border-radius: 6px;
    }
    
    #billPreviewModal .modal-header {
        padding: 10px 12px;
    }
    
    #billPreviewModal .modal-title {
        font-size: 14px;
        text-align: center;
    }
    
    #billPreviewModal .btn-close {
        width: 25px;
        height: 25px;
        padding: 0;
        margin: 0;
        position: absolute;
        right: 10px;
        top: 10px;
    }
    
    #billPreviewModal .modal-body {
        padding: 10px 8px;
        max-height: 60vh;
    }
    
    /* Bill container adjustments for small screens */
    #billPreviewModal .bill-container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 8px !important;
        transform: scale(0.85);
        transform-origin: top center;
    }
    
    #billPreviewModal .bill-header .business-name {
        font-size: 13px !important;
    }
    
    #billPreviewModal .bill-header .business-address,
    #billPreviewModal .bill-header .business-phone {
        font-size: 9px !important;
    }
    
    #billPreviewModal .bill-row,
    #billPreviewModal .bill-summary-row {
        font-size: 10px !important;
    }
    
    #billPreviewModal .bill-item-name {
        font-size: 9px !important;
    }
    
    #billPreviewModal .modal-footer {
        padding: 10px 12px;
        flex-direction: column;
    }
    
    #billPreviewModal .modal-footer .btn {
        flex: none;
        width: 100%;
        margin: 2px 0;
        padding: 12px 15px;
        font-size: 14px;
    }
    
    #billPreviewModal .modal-footer .btn-secondary {
        order: 2;
    }
    
    #billPreviewModal .modal-footer .btn-primary {
        order: 1;
    }
}

/* Extra Small Devices - 320px and below */
@media (max-width: 320px) {
    #billPreviewModal .modal-dialog {
        margin: 5px;
        max-width: calc(100% - 10px);
    }
    
    #billPreviewModal .modal-header {
        padding: 8px 10px;
    }
    
    #billPreviewModal .modal-title {
        font-size: 13px;
        padding-right: 25px; /* Space for close button */
    }
    
    #billPreviewModal .modal-body {
        padding: 8px 5px;
        max-height: 55vh;
    }
    
    #billPreviewModal .bill-container {
        transform: scale(0.8);
        padding: 5px !important;
    }
    
    #billPreviewModal .bill-header .business-name {
        font-size: 12px !important;
    }
    
    #billPreviewModal .bill-row,
    #billPreviewModal .bill-summary-row {
        font-size: 9px !important;
        margin: 0.5px 0 !important;
    }
    
    #billPreviewModal .modal-footer {
        padding: 8px 10px;
    }
    
    #billPreviewModal .modal-footer .btn {
        padding: 10px 12px;
        font-size: 13px;
    }
}

/* Landscape Mode for Small Screens */
@media (max-width: 400px) and (orientation: landscape) {
    #billPreviewModal .modal-body {
        max-height: 50vh;
    }
    
    #billPreviewModal .bill-container {
        transform: scale(0.75);
    }
    
    #billPreviewModal .modal-footer {
        flex-direction: row;
        padding: 8px 10px;
    }
    
    #billPreviewModal .modal-footer .btn {
        flex: 1;
        min-width: auto;
        padding: 8px 10px;
    }
}

/* Touch Device Optimizations */
@media (max-width: 400px) and (hover: none) and (pointer: coarse) {
    #billPreviewModal .btn {
        min-height: 44px; /* Minimum touch target size */
    }
    
    #billPreviewModal .modal-body {
        -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
    }
}

/* High DPI Screens */
@media (max-width: 400px) and (-webkit-min-device-pixel-ratio: 2), 
       (max-width: 400px) and (min-resolution: 192dpi) {
    #billPreviewModal .modal-content {
        border: 0.5px solid #ccc;
    }
    
    #billPreviewModal .bill-divider,
    #billPreviewModal .bill-double-divider {
        border-width: 0.5px;
    }
}

/* Dark Mode Support */
@media (max-width: 400px) and (prefers-color-scheme: dark) {
    #billPreviewModal .modal-content {
        background: #2d3748;
        color: #e2e8f0;
    }
    
    #billPreviewModal .modal-header {
        background: #4a5568;
        border-bottom-color: #718096;
    }
    
    #billPreviewModal .modal-footer {
        background: #4a5568;
        border-top-color: #718096;
    }
    
    #billPreviewModal .modal-title {
        color: #e2e8f0;
    }
    
    #billPreviewModal .bill-container {
        background: #2d3748 !important;
        color: #e2e8f0 !important;
    }
}

/* Print button emphasis for mobile */
@media (max-width: 400px) {
    #billPreviewModal .btn-primary {
        border: none;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
    }
    
    #billPreviewModal .btn-primary:active {
        transform: translateY(1px);
        box-shadow: 0 1px 2px rgba(0, 123, 255, 0.3);
    }
}

/* Scrollbar styling for mobile */
@media (max-width: 400px) {
    #billPreviewModal .modal-body::-webkit-scrollbar {
        width: 4px;
    }
    
    #billPreviewModal .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 2px;
    }
    
    #billPreviewModal .modal-body::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 2px;
    }
    
    #billPreviewModal .modal-body::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
}


/* KOT Preview Styling for thermal printer */
.kot-container {
    width: 65mm;
    max-width: 65mm;
    font-family: 'Arial';
    font-size: 12px;
    line-height: 1.2;
    background: white;
    padding: 0;
    margin: 0 auto;
    color: #000 !important;
}
.kot-header {
    text-align: center;
    margin-bottom: 5px;
    color: #000 !important;
}
.kot-header .business-name {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 2px;
    color: #000 !important;
}
.kot-header .kot-title {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 3px;
    color: #000 !important;
    text-transform: uppercase;
}
.kot-divider {
    border-bottom: 1px solid #000;
    margin: 3px 0;
}
.kot-double-divider {
    border-bottom: 2px solid #000;
    margin: 3px 0;
}
.kot-row {
    display: flex;
    justify-content: space-between;
    margin: 1px 0;
    color: #000 !important;
}
.kot-item-name {
    flex: 2;
    text-align: left;
    font-size: 11px;
    color: #000 !important;
}
.kot-item-qty {
    flex: 1;
    text-align: center;
    color: #000 !important;
    font-weight: bold;
}
.kot-item-special {
    flex: 3;
    text-align: left;
    font-size: 10px;
    font-style: italic;
    color: #000 !important;
    margin-top: -2px;
}
.kot-footer {
    margin-top: 5px;
    font-size: 10px;
    text-align: center;
    color: #000 !important;
}

/* KOT button styling */
.btn-dark {
    background-color: #343a40;
    border-color: #343a40;
    color: #fff;
}
.btn-dark:hover {
    background-color: #23272b;
    border-color: #23272b;
    color: #fff;
}

/* KOT Preview Modal Responsive Styles */
#kotPreviewModal .modal-dialog {
    max-width: 100%;
    margin: 0 auto;
}

#kotPreviewModal .modal-content {
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

#kotPreviewModal .modal-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 12px 15px;
}

#kotPreviewModal .modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

#kotPreviewModal .modal-body {
    padding: 15px;
    max-height: 70vh;
    overflow-y: auto;
    background: #fff;
}

#kotPreviewModal .modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    padding: 12px 15px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

#kotPreviewModal .btn {
    flex: 1;
    min-width: 120px;
    padding: 10px 15px;
    font-size: 14px;
    border-radius: 6px;
}

@media (min-width: 601px) {
    #kotPreviewModal {
        width: 500px;
        left: 50%;
        top: 50px;
        margin: 0;
        margin-left: -250px;
        padding: 0;
    }
}

@media (max-width: 600px) {
    #kotPreviewModal {
        width: 300px;
        left: 50%;
        top: 150px;
        margin: 0;
        margin-left: -150px;
        padding: 0;
    }
    #kotPreviewModal .modal-dialog {
        margin: 0 auto;
        padding: 0 0 0 0;
    }
}

/* Mobile First - 400px and below */
@media (max-width: 400px) {
    #kotPreviewModal .modal-dialog {
        max-width: calc(100% - 20px);
    }
    
    #kotPreviewModal .modal-content {
        border-radius: 6px;
    }
    
    #kotPreviewModal .modal-header {
        padding: 10px 12px;
    }
    
    #kotPreviewModal .modal-title {
        font-size: 14px;
        text-align: center;
    }
    
    #kotPreviewModal .btn-close {
        width: 25px;
        height: 25px;
        padding: 0;
        margin: 0;
        position: absolute;
        right: 10px;
        top: 10px;
    }
    
    #kotPreviewModal .modal-body {
        padding: 10px 8px;
        max-height: 60vh;
    }
    
    /* KOT container adjustments for small screens */
    #kotPreviewModal .kot-container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 8px !important;
        transform: scale(0.85);
        transform-origin: top center;
    }
    
    #kotPreviewModal .kot-header .business-name {
        font-size: 13px !important;
    }
    
    #kotPreviewModal .kot-header .kot-title {
        font-size: 14px !important;
    }
    
    #kotPreviewModal .kot-row,
    #kotPreviewModal .kot-item-name,
    #kotPreviewModal .kot-item-qty {
        font-size: 10px !important;
    }
    
    #kotPreviewModal .kot-item-special {
        font-size: 9px !important;
    }
    
    #kotPreviewModal .modal-footer {
        padding: 10px 12px;
        flex-direction: column;
    }
    
    #kotPreviewModal .modal-footer .btn {
        flex: none;
        width: 100%;
        margin: 2px 0;
        padding: 12px 15px;
        font-size: 14px;
    }
    
    #kotPreviewModal .modal-footer .btn-secondary {
        order: 2;
    }
    
    #kotPreviewModal .modal-footer .btn-success {
        order: 1;
        border: none;
        font-weight: 600;
    }
}

/* Extra Small Devices - 320px and below */
@media (max-width: 320px) {
    #kotPreviewModal .modal-dialog {
        margin: 5px;
        max-width: calc(100% - 10px);
    }
    
    #kotPreviewModal .modal-header {
        padding: 8px 10px;
    }
    
    #kotPreviewModal .modal-title {
        font-size: 13px;
        padding-right: 25px; /* Space for close button */
    }
    
    #kotPreviewModal .modal-body {
        padding: 8px 5px;
        max-height: 55vh;
    }
    
    #kotPreviewModal .kot-container {
        transform: scale(0.8);
        padding: 5px !important;
    }
    
    #kotPreviewModal .kot-header .business-name {
        font-size: 12px !important;
    }
    
    #kotPreviewModal .kot-header .kot-title {
        font-size: 13px !important;
    }
    
    #kotPreviewModal .kot-row,
    #kotPreviewModal .kot-item-name,
    #kotPreviewModal .kot-item-qty {
        font-size: 9px !important;
        margin: 0.5px 0 !important;
    }
    
    #kotPreviewModal .kot-item-special {
        font-size: 8px !important;
    }
    
    #kotPreviewModal .modal-footer {
        padding: 8px 10px;
    }
    
    #kotPreviewModal .modal-footer .btn {
        padding: 10px 12px;
        font-size: 13px;
    }
}

/* Landscape Mode for Small Screens */
@media (max-width: 400px) and (orientation: landscape) {
    #kotPreviewModal .modal-body {
        max-height: 50vh;
    }
    
    #kotPreviewModal .kot-container {
        transform: scale(0.75);
    }
    
    #kotPreviewModal .modal-footer {
        flex-direction: row;
        padding: 8px 10px;
    }
    
    #kotPreviewModal .modal-footer .btn {
        flex: 1;
        min-width: auto;
        padding: 8px 10px;
    }
}

/* Touch Device Optimizations */
@media (max-width: 400px) and (hover: none) and (pointer: coarse) {
    #kotPreviewModal .btn {
        min-height: 44px; /* Minimum touch target size */
    }
    
    #kotPreviewModal .btn-close {
        min-width: 25px;
        min-height: 25px;
    }
    
    #kotPreviewModal .modal-body {
        -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
    }
}

/* High DPI Screens */
@media (max-width: 400px) and (-webkit-min-device-pixel-ratio: 2), 
       (max-width: 400px) and (min-resolution: 192dpi) {
    #kotPreviewModal .modal-content {
        border: 0.5px solid #ccc;
    }
    
    #kotPreviewModal .kot-divider,
    #kotPreviewModal .kot-double-divider {
        border-width: 0.5px;
    }
}

/* Dark Mode Support */
@media (max-width: 400px) and (prefers-color-scheme: dark) {
    #kotPreviewModal .modal-content {
        background: #2d3748;
        color: #e2e8f0;
    }
    
    #kotPreviewModal .modal-header {
        background: #4a5568;
        border-bottom-color: #718096;
    }
    
    #kotPreviewModal .modal-footer {
        background: #4a5568;
        border-top-color: #718096;
    }
    
    #kotPreviewModal .modal-title {
        color: #e2e8f0;
    }
    
    #kotPreviewModal .kot-container {
        background: #2d3748 !important;
        color: #e2e8f0 !important;
    }
    
    #kotPreviewModal .kot-divider,
    #kotPreviewModal .kot-double-divider {
        border-bottom-color: #e2e8f0;
    }
}

/* KOT Print button emphasis for mobile */
@media (max-width: 400px) {
    #kotPreviewModal .btn-success {
        background: linear-gradient(135deg, #198754, #13653f);
        border: none;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(25, 135, 84, 0.3);
    }
    
    #kotPreviewModal .btn-success:active {
        transform: translateY(1px);
        box-shadow: 0 1px 2px rgba(25, 135, 84, 0.3);
    }
}

/* Scrollbar styling for mobile */
@media (max-width: 400px) {
    #kotPreviewModal .modal-body::-webkit-scrollbar {
        width: 4px;
    }
    
    #kotPreviewModal .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 2px;
    }
    
    #kotPreviewModal .modal-body::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 2px;
    }
    
    #kotPreviewModal .modal-body::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
}

/* Animation for KOT modal */
#kotPreviewModal .modal-content {
    animation: kotSlideIn 0.3s ease-out;
}

@keyframes kotSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Focus states for accessibility */
#kotPreviewModal .btn:focus {
    box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.25);
    outline: none;
}

/* Loading state for KOT content */
#kotPreviewModal .kot-container.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Print-specific optimizations */
@media print {
    #kotPreviewModal .modal-header,
    #kotPreviewModal .modal-footer {
        display: none !important;
    }
    
    #kotPreviewModal .modal-body {
        max-height: none;
        overflow: visible;
        padding: 0;
    }
    
    #kotPreviewModal .kot-container {
        transform: none !important;
        width: 65mm !important;
        max-width: 65mm !important;
        padding: 5px !important;
    }
}

th {
    text-align: center;
}

th:last-child,
th:nth-last-child(2) {
  width: 190px;
}
.scroll-to-top {
    bottom: 15px;
}
</style>

</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include ($role === 'admin') ? 'admin_menu.php' : 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Order Management
                                    <div class="float-end order_section">
                                        <!-- Date range filter for order viewing -->
                                        <form method="GET" class="d-inline-flex align-items-center">
                                            <div class="me-2">
                                                <label class="form-label small mb-0">From</label>
                                                <input type="date" name="from_date" class="form-control" 
                                                       value="<?php echo htmlspecialchars($from_date); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            <div class="me-2">
                                                <label class="form-label small mb-0">To</label>
                                                <input type="date" name="to_date" class="form-control" 
                                                       value="<?php echo htmlspecialchars($to_date); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            <button type="submit" class="btn btn-primary align-self-end">View 
                                                <br> Orders</button>  
                                        </form>
                                    </div>
                                </h4>
                            </div>
                            <div class="card-body">
                                <!-- Message display for user feedback -->
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <h5 class="mb-3">
                                    <?php 
                                    // Display appropriate heading based on date range
                                    $today = date('Y-m-d');
                                    if ($from_date == $today && $to_date == $today) {
                                        echo "Today's Orders (" . date('F j, Y', strtotime($from_date)) . ")";
                                    } else {
                                        echo "Orders from " . date('F j, Y', strtotime($from_date)) . " to " . date('F j, Y', strtotime($to_date));
                                    }
                                    ?>
                                </h5>

                                <?php if (empty($orders)): ?>
                                    <!-- No orders message -->
                                    <div class="alert alert-info">
                                        <?php 
                                        if ($from_date == $today && $to_date == $today) {
                                            echo "No orders found for today (" . date('F j, Y', strtotime($from_date)) . ")";
                                        } else {
                                            echo "No orders found from " . date('F j, Y', strtotime($from_date)) . " to " . date('F j, Y', strtotime($to_date));
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Orders table with responsive design -->
                                    <div class="table-responsive mobile_table">
                                        <table class="table order table-hover mb-0">
    <thead>
        <tr>
            <th>Sr.</th>
            <th>Order ID</th>
            <th>Date & Time</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Timer</th>
            <th>Print</th> 
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="ordersTableBody">
        <?php foreach ($orders as $index => $order): ?>
            <tr id="order-<?php echo $order['order_id']; ?>" data-order-id="<?php echo $order['order_id']; ?>">
                <td data-label="Sr. No."><?php echo $index + 1 + $offset; ?></td>
                <td data-label="Order ID">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                <td data-label="Date & Time">
                    <?php echo displayTime($order['created_at'], $user_country); ?>
                </td>
                <td data-label="Customer"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                <td data-label="Type">
                    <?php 
                    // Format order type for display
                    if ($order['order_type'] === 'dining') {
                        echo 'Dining - Table ' . htmlspecialchars($order['table_number']);
                    } else {
                        echo ucfirst(htmlspecialchars($order['order_type']));
                    }
                    ?>
                </td>
                <td data-label="Items"><?php echo htmlspecialchars($order['item_count']); ?></td>
                <td data-label="Total"><?php echo $currencySymbol; ?> <?php echo number_format($order['total_amount'], 2); ?></td>
                <td data-label="Status">
                    <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                        <?php echo htmlspecialchars($order['status']); ?>
                    </span>
                </td>
                <td data-label="Timer" class="timer-column">
                    <div class="clountdown_group">
                        <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing', 'Ready'])): ?>
                            <!-- Timer display for active orders -->
                            <div class="timer" 
                                 data-created-at="<?php echo $order['created_at_original']; ?>"
                                 data-order-id="<?php echo $order['order_id']; ?>">
                                <i class="bi bi-clock"></i>
                                <span class="timer-display">
                                    <?php
                                    $minutes = floor($order['timer_remaining'] / 60);
                                    $seconds = $order['timer_remaining'] % 60;
                                    echo sprintf('%02d:%02d', $minutes, $seconds);
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td data-label="Print">

                    <button class="btn btn-sm btn-warning preview-kot" 
                            data-order-id="<?php echo $order['order_id']; ?>"
                            title="Print KOT (Kitchen Order Ticket)">
                        <i class="bi bi-printer-fill"></i> KOT
                    </button>


                    <!-- Bill print button -->
                    <button class="btn btn-sm btn-warning preview-bill" 
                            data-order-id="<?php echo $order['order_id']; ?>"
                            title="Preview & Print Bill">
                        <i class="bi bi-printer-fill"></i> Bill
                    </button>
                </td>
                <td data-label="Actions">
                    <!-- View order details button -->
                    <button class="btn btn-sm btn-primary view-order" 
                            data-order-id="<?php echo $order['order_id']; ?>"
                            data-bs-toggle="modal" 
                            data-bs-target="#orderModal">
                        <i class="bi bi-eye"></i> View
                    </button>

                    <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing'])): ?>
                        <!-- Cancel order button (hidden by default) -->
                        <button class="btn btn-sm btn-danger cancel-order" 
                                data-order-id="<?php echo $order['order_id']; ?>" style="display: none;">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing'])): ?>
                        <!-- Mark as Ready button -->
                        <button class="btn btn-sm btn-success update-status-btn" 
                                data-order-id="<?php echo $order['order_id']; ?>"
                                data-new-status="Ready">
                            <i class="bi bi-check-circle"></i> Ready
                        </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['Ready'])): ?>
                        <!-- Mark as Complete button -->
                        <button class="btn btn-sm btn-info update-status-btn" 
                                data-order-id="<?php echo $order['order_id']; ?>"
                                data-new-status="Completed">
                            <i class="bi bi-check-all"></i> Complete
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
                                    </div>

                                    <!-- Pagination for large datasets -->
                                    <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-center mt-3">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                                            <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Order Details Modal for viewing complete order information -->
    <div class="modal fade order-modal" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalLabel">Order Details #<span id="modalOrderId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Customer Information</h6>
                            
                            <!-- Customer Name with Copy Button -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <p class="mb-0"><strong>Name:</strong> <span id="modalCustomerName"></span></p>
                                <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="modalCustomerName">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            
                            <!-- Customer Phone with Copy Button -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <p class="mb-0"><strong>Phone:</strong> <span id="modalCustomerPhone"></span></p>
                                <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="modalCustomerPhone">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            
                            <!-- Delivery Address with Copy Button -->
                            <div class="d-flex justify-content-between align-items-center mb-2" id="modalDeliveryAddress">
                                <p class="mb-0"><strong>Address:</strong> <span id="modalAddressText"></span></p>
                                <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="modalAddressText">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            
                            <!-- Table Number -->
                            <p id="modalTableNumber" class="mb-2"><strong>Table Number:</strong> <span id="modalTableText"></span></p>
                            
                            <!-- Order Notes -->
                            <div id="modalOrderNotesContainer">
                                <h6>Order Notes</h6>
                                <p id="modalOrderNotes"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Order Summary</h6>
                            <p><strong>Order Type:</strong> <span id="modalOrderType"></span></p>
                            <p><strong>Order Date:</strong> <span id="modalOrderDate"></span></p>
                            <p><strong>Status:</strong> <span id="modalOrderStatus" class="status-badge"></span></p>
                        </div>
                    </div>
                    
                    <h6>Order Items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm order-items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="modalOrderItems">
                                <!-- Items will be inserted here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 offset-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Subtotal:</strong></td>
                                    <td><?php echo $currencySymbol; ?> <span id="modalSubtotal"></span></td>
                                </tr>
                                <tr id="modalDiscountRow">
                                    <td><strong>Discount:</strong></td>
                                    <td>-<?php echo $currencySymbol; ?> <span id="modalDiscountAmount"></span> (<span id="modalDiscountType"></span>)</td>
                                </tr>
                                <tr id="modalGstRow">
                                    <td><strong><?php echo $taxLabel; ?>:</strong></td>
                                    <td><?php echo $currencySymbol; ?> <span id="modalGstAmount"></span></td>
                                </tr>
                                <tr id="modalDeliveryRow">
                                    <td><strong>Delivery Charge:</strong></td>
                                    <td><?php echo $currencySymbol; ?> <span id="modalDeliveryCharge"></span></td>
                                </tr>
                                <tr class="table-active">
                                    <td><strong>Total Amount:</strong></td>
                                    <td><strong><?php echo $currencySymbol; ?> <span id="modalTotalAmount"></span></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- Status update form -->
                    <form method="POST" action="orders.php" class="d-inline" id="statusUpdateForm">
                        <input type="hidden" name="order_id" id="modalFormOrderId">
                        <div class="input-group">
                            <select class="form-select" name="new_status" id="modalStatusSelect">
                                <option value="Pending">Pending</option>
                                <option value="Confirmed">Confirmed</option>
                                <option value="Ready">Ready</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>

                    <!-- Cancel order form -->
                    <form method="POST" action="orders.php" class="d-inline ms-2" id="cancelOrderForm">
                        <input type="hidden" name="order_id" id="modalCancelOrderId">
                        <button type="submit" name="cancel_order" class="btn btn-danger" style="display:none;">
                            <i class="bi bi-x-circle"></i> Cancel Order
                        </button>
                    </form>
                    
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- KOT Preview Modal for kitchen order ticket printing -->
    <div class="modal fade" id="kotPreviewModal" tabindex="-1" aria-labelledby="kotPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="kotPreviewModalLabel">KOT Preview - Order #<span id="kotOrderId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <!-- KOT Content will be loaded here -->
                    <div id="kotContent" class="kot-container">
                        <!-- KOT content will be dynamically loaded -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="printKOT()">
                        <i class="bi bi-printer-fill"></i> Print KOT
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bill Preview Modal for thermal printer bill printing -->
    <div class="modal fade" id="billPreviewModal" tabindex="-1" aria-labelledby="billPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="billPreviewModalLabel">Bill Preview - Order #<span id="billOrderId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <!-- Bill Content will be loaded here -->
                    <div id="billContent" class="bill-container">
                        <!-- Bill content will be dynamically loaded -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printBill()">
                        <i class="bi bi-printer"></i> Print Bill
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
<script>
// Main JavaScript functionality for order management system

// Get currency symbol and user country from PHP
const currencySymbol = '<?php echo $currencySymbol; ?>';
const userCountry = '<?php echo $user_country; ?>';
const taxLabel = '<?php echo $taxLabel; ?>';

/**
 * Adjust date for UAE users (subtract 1 hour 30 minutes)
 * @param {Date|string} date - Date to adjust
 * @returns {Date} Adjusted date
 */
function adjustTimeForUAE(date) {
    if (userCountry !== 'UAE') {
        return date instanceof Date ? date : new Date(date);
    }
    
    const dateObj = date instanceof Date ? new Date(date.getTime()) : new Date(date);
    dateObj.setHours(dateObj.getHours() - 1);
    dateObj.setMinutes(dateObj.getMinutes() - 30);
    return dateObj;
}

/**
 * Format date for display with UAE adjustment
 * @param {string} dateString - Date string from server
 * @returns {string} Formatted date string
 */
function formatDisplayDate(dateString) {
    const adjustedDate = adjustTimeForUAE(dateString);
    return adjustedDate.toLocaleDateString('en-IN') + ' ' + adjustedDate.toLocaleTimeString('en-IN', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}

/**
 * Highlight specific order when coming from notification
 * Scrolls to and highlights the order mentioned in URL
 */
function highlightOrderFromNotification() {
    const urlParams = new URLSearchParams(window.location.search);
    const highlightOrderId = urlParams.get('highlight_order');
    
    if (highlightOrderId) {
        console.log('🔍 Highlighting order from notification:', highlightOrderId);
        
        // Remove the parameter from URL without page reload
        const newUrl = window.location.pathname + window.location.search.replace(/highlight_order=[^&]*&?/, '').replace(/&$/, '').replace(/\?$/, '');
        window.history.replaceState({}, document.title, newUrl);
        
        // Find and highlight the order
        const $orderRow = $(`tr[data-order-id="${highlightOrderId}"]`);
        if ($orderRow.length) {
            // Scroll to the order
            $('html, body').animate({
                scrollTop: $orderRow.offset().top - 100
            }, 1000);
            
            // Add highlight animation
            $orderRow.addClass('table-success');
            
            // Pulse animation
            let pulseCount = 0;
            const pulseInterval = setInterval(() => {
                $orderRow.toggleClass('table-warning');
                pulseCount++;
                if (pulseCount >= 6) { // 3 pulses
                    clearInterval(pulseInterval);
                    $orderRow.removeClass('table-warning table-success');
                }
            }, 500);
            
            // Auto-open modal after highlighting
            setTimeout(() => {
                $orderRow.find('.view-order').click();
            }, 1500);
            
        } else {
            console.log('Order not found in current view:', highlightOrderId);
            showToast('Order #' + highlightOrderId + ' not found in current view', 'info');
        }
    }
}

/**
 * Timer countdown functionality
 * Updates all visible timers every second for order preparation tracking
 */
function updateTimers() {
    $('.timer').each(function() {
        const $timer = $(this);
        const $display = $timer.find('.timer-display');
        const createdAt = $timer.data('created-at');
        const orderId = $timer.data('order-id');
        
        // Get the order status from multiple possible sources
        let orderStatus = '';
        
        // Try to get status from status badge
        const $statusBadge = $timer.closest('tr').find('.status-badge');
        if ($statusBadge.length) {
            orderStatus = $statusBadge.text();
        }
        
        // If status badge not found, try to get from global ordersData
        if (!orderStatus && window.ordersData) {
            const order = window.ordersData.find(o => o.order_id == orderId);
            if (order) {
                orderStatus = order.status;
            }
        }
        
        // Remove timer completely for completed orders
        if (orderStatus === 'Completed') {
            $timer.closest('.clountdown_group').html('');
            return;
        }
        
        // Also remove timer for cancelled orders if needed
        if (orderStatus === 'Cancelled') {
            $timer.closest('.clountdown_group').html('');
            return;
        }
        
        const createdTime = new Date(createdAt).getTime();
        const currentTime = new Date().getTime();
        
        // Check if createdTime is valid
        if (isNaN(createdTime)) {
            console.warn('Invalid created time for order:', orderId);
            $display.text('00:00');
            return;
        }
        
        const timeElapsed = Math.floor((currentTime - createdTime) / 1000);
        const timeLimit = 30 * 60;
        const timeRemaining = timeLimit - timeElapsed;
        
        if (timeRemaining <= 0) {
            $display.text('00:00');
            $timer.removeClass('warning').addClass('danger');
            return;
        }
        
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        $display.text(`${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
        
        $timer.removeClass('warning danger');
        if (timeRemaining <= 10 * 60) {
            $timer.addClass('danger');
        } else if (timeRemaining <= 15 * 60) {
            $timer.addClass('warning');
        }
    });
}

/**
 * Apply border pulse animation to Complete buttons
 * Adds visual emphasis to the action button
 */
function applyCompleteButtonAnimations() {
    // Apply pulse animation to all Complete buttons
    $('.update-status-btn[data-new-status="Completed"]').addClass('pulse-border');
    
    // Optional: Apply different animations based on conditions
    $('.update-status-btn[data-new-status="Completed"]').each(function() {
        const $btn = $(this);
        const orderId = $btn.data('order-id');
        
        // Example: Apply different animations based on order age
        const $createdAt = $btn.closest('tr').find('td[data-label="Date & Time"]').text();
        
        // You can add custom logic here based on your requirements
        if (orderId % 2 === 0) { // Example condition
            $btn.addClass('fire-pulse');
        } else {
            $btn.addClass('pulse-border');
        }
    });
}

/**
 * Apply border pulse animation to Ready buttons
 * Adds visual emphasis to the action button
 */
function applyButtonAnimations() {
    // Apply pulse animation to all Ready buttons
    $('.update-status-btn[data-new-status="Ready"]').addClass('pulse-border');
    
    // Optional: Apply different animations based on order status or other conditions
    $('.update-status-btn[data-new-status="Ready"]').each(function() {
        const $btn = $(this);
        const orderId = $btn.data('order-id');
        
        // Example: Apply different animations based on timer status
        const $timer = $btn.closest('tr').find('.timer');
        if ($timer.hasClass('danger')) {
            // If timer is in danger state, use heartbeat animation
            $btn.addClass('heartbeat');
        } else if ($timer.hasClass('warning')) {
            // If timer is in warning state, use double pulse
            $btn.addClass('double-pulse');
        } else {
            // Default pulse animation
            $btn.addClass('pulse-border');
        }
    });
}

/**
 * Bill Preview functionality
 * Shows bill preview in modal and handles printing
 */

// Initialize bill preview handlers
function initializeBillPreviewHandlers() {
    $('.preview-bill').off('click').on('click', function(e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        showBillPreview(orderId);
    });
}

/**
 * Show bill preview for specific order
 * @param {number} orderId - ID of the order to preview
 */
function showBillPreview(orderId) {
    console.log('🖨️ Loading bill preview for order:', orderId);
    
    // Find the order data from our global ordersData
    const order = window.ordersData?.find(o => o.order_id == orderId);
    if (!order) {
        showToast('Order data not found for bill preview', 'danger');
        return;
    }
    
    // Update modal title
    $('#billOrderId').text(orderId);
    
    // Generate and display bill content
    const billHtml = generateBillHTML(order);
    $('#billContent').html(billHtml);
    
    // Show the modal
    const billModal = new bootstrap.Modal(document.getElementById('billPreviewModal'));
    billModal.show();
}

/**
 * Generate bill HTML for thermal printer (65mm)
 * @param {Object} order - Order data object
 * @returns {string} HTML string for the bill
 */
function generateBillHTML(order) {
    const businessName = "<?php echo addslashes($business_name); ?>";
    const businessAddress = "<?php echo addslashes($business_address); ?>";
    
    // Format order type
    const orderTypeDisplay = order.order_type === 'delivery' ? 'Home Delivery' : 
                           order.order_type === 'dining' ? `Dine-In (Table ${order.table_number})` : 'Takeaway';
    
    // Format date and time with UAE adjustment
    const adjustedDate = adjustTimeForUAE(order.created_at);
    const orderDate = adjustedDate.toLocaleDateString('en-IN');
    const orderTime = adjustedDate.toLocaleTimeString('en-IN', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    
    // Helper function to format currency - remove .00 if whole number
    const formatCurrency = (amount) => {
        const num = parseFloat(amount || 0);
        // Check if it's a whole number
        if (num % 1 === 0) {
            return num.toString(); // Return without decimals
        } else {
            return num.toFixed(2); // Return with 2 decimal places
        }
    };
    
    // Get current time for footer with UAE adjustment
    const now = new Date();
    const adjustedNow = userCountry === 'UAE' ? adjustTimeForUAE(now) : now;
    const currentDate = adjustedNow.toLocaleDateString('en-IN');
    const currentTime = adjustedNow.toLocaleTimeString('en-IN', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    
    let billHtml = `
        <div class="bill-container">
            <div class="bill-header">
                <div class="business-name">${escapeHtml(businessName)}</div>
                <div class="business-address">${escapeHtml(businessAddress)}</div>
                <?php if (!empty($phone)): ?>
                <div class="business-phone">Ph: <?php echo htmlspecialchars($phone); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="bill-double-divider"></div>
            
            <!-- Order Information -->
            <div class="bill-row">
                <div class="bill-item-name">Order #</div>
                <div class="bill-item-total">${order.order_id}</div>
            </div>
            <div class="bill-row">
                <div class="bill-item-name">Date</div>
                <div class="bill-item-total">${orderDate}</div>
            </div>
            <div class="bill-row">
                <div class="bill-item-name">Time</div>
                <div class="bill-item-total">${orderTime}</div>
            </div>
            <div class="bill-row">
                <div class="bill-item-name">Type</div>
                <div class="bill-item-total">${orderTypeDisplay}</div>
            </div>
            
            <div class="bill-divider"></div>
            
            <!-- Customer Information -->
            <div class="bill-row" style="font-weight: bold;">Customer Details</div>
            <div class="bill-row">
                <div class="bill-item-name">Name:</div>
                <div class="bill-item-total">${escapeHtml(order.customer_name)}</div>
            </div>
    `;
    
    if (order.customer_phone) {
        billHtml += `
            <div class="bill-row">
                <div class="bill-item-name">Phone:</div>
                <div class="bill-item-total">${escapeHtml(order.customer_phone)}</div>
            </div>
        `;
    }
    
    if (order.order_type === 'delivery' && order.delivery_address) {
        billHtml += `
            <div class="bill-row">
                <div style="flex: 3; text-align: left; font-size: 10px;">
                    Address: ${escapeHtml(order.delivery_address)}
                </div>
            </div>
        `;
    }
    
    if (order.order_notes) {
        billHtml += `
            <div class="bill-row">
                <div style="flex: 3; text-align: left; font-size: 10px;">
                    Notes: ${escapeHtml(order.order_notes)}
                </div>
            </div>
        `;
    }
    
    billHtml += `
            <div class="bill-divider"></div>
            
            <!-- Order Items -->
            <div class="bill-row" style="font-weight: bold; text-align: center;">ORDER ITEMS</div>
            <div class="bill-double-divider"></div>
            
            <!-- Header for items -->
            <div class="bill-row" style="font-weight: bold;">
                <div class="bill-item-name">Item</div>
                <div class="bill-item-qty">Qty</div>
                <div class="bill-item-price">Price</div>
                <div class="bill-item-total">Total</div>
            </div>
            <div class="bill-divider"></div>
    `;
    
    // Add order items
    if (order.items && order.items.length > 0) {
        order.items.forEach(item => {
            const itemTotal = (parseFloat(item.price) * parseInt(item.quantity));
            billHtml += `
            <div class="bill-row">
                <div class="bill-item-name">${escapeHtml(item.product_name)}</div>
                <div class="bill-item-qty">${item.quantity}</div>
                <div class="bill-item-price">${currencySymbol} ${formatCurrency(item.price)}</div>
                <div class="bill-item-total">${currencySymbol} ${formatCurrency(itemTotal)}</div>
            </div>
            `;
        });
    }
    
    billHtml += `
            <div class="bill-double-divider"></div>
            
            <!-- Bill Summary -->
            <div class="bill-row" style="font-weight: bold; text-align: center;">BILL SUMMARY</div>
            
            <div class="bill-summary-row">
                <div class="bill-summary-label">Subtotal:</div>
                <div class="bill-summary-value">${currencySymbol} ${formatCurrency(order.subtotal)}</div>
            </div>
    `;
    
    if (parseFloat(order.discount_amount) > 0) {
        billHtml += `
            <div class="bill-summary-row">
                <div class="bill-summary-label">Discount (${escapeHtml(order.discount_type)}):</div>
                <div class="bill-summary-value">-${currencySymbol} ${formatCurrency(order.discount_amount)}</div>
            </div>
        `;
    }
    
    if (parseFloat(order.gst_amount) > 0) {
        billHtml += `
            <div class="bill-summary-row">
                <div class="bill-summary-label">${taxLabel}:</div>
                <div class="bill-summary-value">${currencySymbol} ${formatCurrency(order.gst_amount)}</div>
            </div>
        `;
    }
    
    if (parseFloat(order.delivery_charge) > 0) {
        billHtml += `
            <div class="bill-summary-row">
                <div class="bill-summary-label">Delivery Charge:</div>
                <div class="bill-summary-value">${currencySymbol} ${formatCurrency(order.delivery_charge)}</div>
            </div>
        `;
    }
    
    billHtml += `
            <div class="bill-double-divider"></div>
            
            <div class="bill-summary-row" style="font-weight: bold;">
                <div class="bill-summary-label">GRAND TOTAL:</div>
                <div class="bill-summary-value">${currencySymbol} ${formatCurrency(order.total_amount)}</div>
            </div>
            
            <div class="bill-double-divider"></div>
            
            <!-- Footer -->
            <div class="bill-footer">
                <div>Thank you for your order!</div>
                <div>Visit again</div>
                <div style="margin-top: 3px;">
                    ${currentDate} ${currentTime}
                </div>
            </div>
        </div>
    `;
    
    return billHtml;
}

/**
 * Print the bill
 * Uses browser's print functionality with thermal printer styling
 */
function printBill() {
    const billContent = document.getElementById('billContent');
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=65mm,height=600,scrollbars=no,toolbar=no,location=no');
    
    if (!printWindow) {
        showToast('Please allow popups for printing', 'warning');
        return;
    }
    
    // Write the bill content to the new window with proper thermal printer styling
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Bill - Order #${$('#billOrderId').text()}</title>
            <style>
                @page {
                    margin: 0;
                    padding: 0;
                    size: 65mm auto;
                }
                body {
                    margin: 0;
                    padding: 5px;
                    font-family: 'Arial';
                    font-size: 12px;
                    line-height: 1.2;
                    width: 65mm;
                    background: white;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .bill-container {
                    width: 65mm;
                    max-width: 65mm;
                    font-family: 'Arial';
                    font-size: 12px;
                    line-height: 1.2;
                    background: white;
                    padding: 0;
                    margin: 0 auto;
                }
                .bill-header {
                    text-align: center;
                    margin-bottom: 5px;
                }
                .bill-header .business-name {
                    font-weight: bold;
                    font-size: 14px;
                    margin-bottom: 2px;
                }
                .bill-header .business-address {
                    font-size: 10px;
                    margin-bottom: 2px;
                }
                .bill-header .business-phone {
                    font-size: 10px;
                    margin-bottom: 3px;
                }
                .bill-divider {
                    border-bottom: 1px solid #000;
                    margin: 3px 0;
                }
                .bill-double-divider {
                    border-bottom: 2px solid #000;
                    margin: 3px 0;
                }
                .bill-row {
                    display: flex;
                    justify-content: space-between;
                    margin: 1px 0;
                }
                .bill-item-name {
                    flex: 2;
                    text-align: left;
                    font-size: 11px;
                }
                .bill-item-qty {
                    flex: 1;
                    text-align: center;
                }
                .bill-item-price {
                    flex: 1;
                    text-align: right;
                }
                .bill-item-total {
                    flex: 1;
                    text-align: right;
                }
                .bill-summary-row {
                    display: flex;
                    justify-content: space-between;
                    margin: 1px 0;
                }
                .bill-summary-label {
                    flex: 2;
                    text-align: left;
                }
                .bill-summary-value {
                    flex: 1;
                    text-align: right;
                }
                .bill-footer {
                    margin-top: 5px;
                    font-size: 10px;
                    text-align: center;
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 5px;
                        width: 65mm;
                    }
                }
            </style>
        </head>
        <body>
            ${billContent.innerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Wait for content to load then trigger print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
            // Optional: Close the window after printing
            // printWindow.close();
        }, 500);
    };
    
    // Fallback: if onload doesn't fire, try printing after a delay
    setTimeout(() => {
        if (!printWindow.closed) {
            printWindow.print();
        }
    }, 1000);
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize all handlers when page loads
$(document).ready(function() {
    // Update timers every second
    setInterval(updateTimers, 1000);
    
    // Initial timer update
    updateTimers();

    // Initialize Data - store orders data globally for fallback use
    window.ordersData = <?php echo json_encode($orders); ?>;

    // Initialize all handlers
    initializeAllHandlers();
    
    // Highlight order if coming from notification
    highlightOrderFromNotification();
    
    // Apply button animations
    applyButtonAnimations();
    applyCompleteButtonAnimations();

    console.log('🚀 Order management system initialized');
    console.log('User Country:', userCountry);
    console.log('Currency Symbol:', currencySymbol);
    console.log('Tax Label:', taxLabel);
});

/**
 * Bind order handlers for view and cancel buttons
 * Uses event delegation to handle dynamically created elements
 */
function bindOrderHandlers() {
    $('.view-order').off('click').on('click', viewOrderHandler);
    $('.cancel-order').off('click').on('click', cancelOrderHandler);
}

/**
 * Handle status update buttons (Ready, Complete)
 * Binds click events to status update buttons
 */
function handleStatusUpdateButtons() {
    $('.update-status-btn').off('click').on('click', function(e) {
        e.preventDefault();
        
        const orderId = $(this).data('order-id');
        const newStatus = $(this).data('new-status');
        
        updateOrderStatusDirect(orderId, newStatus, $(this));
    });
}

/**
 * View order handler - opens modal with order details
 * Always fetches fresh data from server to ensure accuracy
 * @param {Event} e - Click event
 */
function viewOrderHandler() {
    const orderId = $(this).data('order-id');
    console.log('🔍 Opening modal for order:', orderId);
    
    // Always fetch fresh data from server for modal to ensure accuracy
    fetchOrderDetailsForModal(orderId);
}

/**
 * Cancel order handler - confirms and cancels order via AJAX
 * @param {Event} e - Click event
 */
function cancelOrderHandler(e) {
    e.preventDefault();
    const orderId = $(this).data('order-id');
    
    if (confirm('Are you sure you want to cancel this order?')) {
        const button = $(this);
        const originalText = button.html();
        button.html('<i class="bi bi-arrow-repeat spin"></i>').prop('disabled', true);
        
        $.ajax({
            url: 'orders.php',
            type: 'POST',
            data: {
                ajax_cancel_order: true,
                order_id: orderId
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        showToast(result.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        throw new Error(result.message || 'Cancellation failed');
                    }
                } catch (e) {
                    showToast(e.message, 'danger');
                    button.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Cancellation error:', error);
                showToast('Error cancelling order. Please try again.', 'danger');
                button.html(originalText).prop('disabled', false);
            }
        });
    }
}

/**
 * Update order status directly via AJAX
 * Handles status updates for Ready and Complete buttons
 * @param {number} orderId - ID of the order to update
 * @param {string} newStatus - New status to set
 * @param {jQuery} button - Button element that triggered the update
 */
function updateOrderStatusDirect(orderId, newStatus, button) {
    const originalText = button.html();
    button.html('<i class="bi bi-arrow-repeat spin"></i>').prop('disabled', true);
    
    $.ajax({
        url: 'orders.php',
        type: 'POST',
        data: {
            ajax_update_status: true,
            order_id: orderId,
            new_status: newStatus
        },
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success) {
                    showToast(result.message || `Order marked as ${newStatus}!`, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    throw new Error(result.message || 'Update failed');
                }
            } catch (e) {
                showToast(e.message || 'Error updating order status', 'danger');
                button.html(originalText).prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('Status update error:', error);
            showToast('Error updating order status. Please try again.', 'danger');
            button.html(originalText).prop('disabled', false);
        }
    });
}

/**
 * Show toast notification
 * Displays temporary notification messages to user
 * @param {string} message - Message to display
 * @param {string} type - Bootstrap alert type (success, danger, warning, info)
 */
function showToast(message, type) {
    $('.custom-toast').remove();
    
    const toast = $(`
        <div class="alert alert-${type} alert-dismissible fade show custom-toast" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(toast);
    
    setTimeout(() => {
        toast.alert('close');
    }, 5000);
}

/**
 * Fetch fresh order details from server for modal
 * Always gets latest data from server to ensure accuracy
 * @param {number} orderId - ID of the order to fetch details for
 */
function fetchOrderDetailsForModal(orderId) {
    console.log('🔄 Fetching fresh order details for modal - order #' + orderId);
    
    // Show loading state
    $('#modalOrderId').text('Loading...');
    $('#modalCustomerName').text('Loading...');
    $('#modalCustomerPhone').text('Loading...');
    
    fetch(`get_restaurant_order_details.php?order_id=${orderId}&t=${Date.now()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.order) {
                console.log('✅ Fresh order data loaded for order #' + orderId, data.order);
                updateOrderModal(data.order);
            } else {
                throw new Error(data.error || 'Failed to load order details');
            }
        })
        .catch(error => {
            console.error('Error fetching order details:', error);
            showToast('Error loading order details. Please try again.', 'danger');
            
            // Fallback: Try to use existing data if server fetch fails
            const order = window.ordersData?.find(o => o.order_id == orderId);
            if (order) {
                console.log('🔄 Using fallback data for order #' + orderId);
                updateOrderModal(order);
            } else {
                showToast('Could not load order details.', 'danger');
            }
        });
}

/**
 * Update order modal with order data
 * Populates all modal fields with order information
 * @param {Object} order - Order data object
 */
function updateOrderModal(order) {
    console.log('📋 Updating modal with order data for order #' + order.order_id, order);
    
    // Reset modal first to avoid stale data
    $('#modalOrderId').text('');
    $('#modalCustomerName').text('');
    $('#modalCustomerPhone').text('');
    $('#modalOrderItems').empty();
    
    // Basic info
    $('#modalOrderId').text(order.order_id || 'N/A');
    $('#modalCustomerName').text(order.customer_name || 'Not specified');
    $('#modalCustomerPhone').text(order.customer_phone || 'Not specified');
    
    // Order type specifics
    if (order.order_type === 'delivery') {
        $('#modalDeliveryAddress').show().find('#modalAddressText').text(order.delivery_address || 'Not specified');
        $('#modalTableNumber').hide();
    } else if (order.order_type === 'dining') {
        $('#modalDeliveryAddress').hide();
        $('#modalTableNumber').show().find('#modalTableText').text(order.table_number || 'Not specified');
    } else {
        $('#modalDeliveryAddress').hide();
        $('#modalTableNumber').hide();
    }
    
    // Order summary - Use displayTime function for UAE adjustment
    $('#modalOrderType').text(formatOrderType(order));
    $('#modalOrderDate').text(formatDisplayDate(order.created_at));
    
    // Status
    const statusBadge = $('#modalOrderStatus');
    statusBadge.text(order.status || 'Unknown')
        .removeClass().addClass('status-badge status-' + (order.status || 'Pending'));
    
    // Items
    renderOrderItems(order.items || []);
    
    // Order notes
    const $notesContainer = $('#modalOrderNotesContainer');
    const $notesText = $('#modalOrderNotes');
    
    if (order.order_notes) {
        $notesContainer.show();
        $notesText.text(order.order_notes);
    } else {
        $notesContainer.hide();
    }
    
    // Financials - Update tax label in the modal
    updateFinancials(order);
    
    // Form fields
    $('#modalFormOrderId').val(order.order_id);
    $('#modalCancelOrderId').val(order.order_id);
    $('#modalStatusSelect').val(order.status);
    
    // Action buttons
    const showActions = ['Pending', 'Confirmed', 'Preparing'].includes(order.status);
    $('#statusUpdateForm, #cancelOrderForm').toggle(showActions);
}

/**
 * Render order items in modal table
 * Creates table rows for each order item
 * @param {Array} items - Array of order items
 */
function renderOrderItems(items) {
    const $container = $('#modalOrderItems').empty();
    
    if (!items || items.length === 0) {
        $container.append('<tr><td colspan="4" class="text-center">No items found</td></tr>');
        return;
    }
    
    items.forEach(item => {
        const total = (parseFloat(item.price || 0) * parseInt(item.quantity || 0));
        $container.append(`
            <tr>
                <td>${item.product_name || 'Unnamed Item'}</td>
                <td>${currencySymbol} ${parseFloat(item.price || 0).toFixed(2)}</td>
                <td>${item.quantity || 1}</td>
                <td>${currencySymbol} ${total.toFixed(2)}</td>
            </tr>
        `);
    });
}

/**
 * Update financial information in modal
 * Displays subtotal, discounts, tax, delivery charges, and total
 * @param {Object} order - Order data object
 */
function updateFinancials(order) {
    $('#modalSubtotal').text(parseFloat(order.subtotal || 0).toFixed(2));
    
    // Toggle and set discount
    const discountAmount = parseFloat(order.discount_amount || 0);
    $('#modalDiscountRow').toggle(discountAmount > 0);
    if (discountAmount > 0) {
        $('#modalDiscountAmount').text(discountAmount.toFixed(2));
        $('#modalDiscountType').text(order.discount_type || 'Discount');
    }
    
    // Toggle and set tax - update label based on country
    const gstAmount = parseFloat(order.gst_amount || 0);
    $('#modalGstRow').toggle(gstAmount > 0);
    // Update the label in the modal row
    $('#modalGstRow td strong').text(taxLabel + ':');
    if (gstAmount > 0) $('#modalGstAmount').text(gstAmount.toFixed(2));
    
    // Toggle and set delivery
    const deliveryCharge = parseFloat(order.delivery_charge || 0);
    $('#modalDeliveryRow').toggle(deliveryCharge > 0);
    if (deliveryCharge > 0) $('#modalDeliveryCharge').text(deliveryCharge.toFixed(2));
    
    // Total
    $('#modalTotalAmount').text(parseFloat(order.total_amount || 0).toFixed(2));
}

/**
 * Cancel order form submission handler
 * Prevents default form submission and triggers cancel order via button click
 */
$('#cancelOrderForm').submit(function(e) {
    e.preventDefault();
    const orderId = $('#modalCancelOrderId').val();
    $(`.cancel-order[data-order-id="${orderId}"]`).click();
});

/**
 * Status update form submission handler
 * Prevents default form submission and triggers status update
 */
$('#statusUpdateForm').submit(function(e) {
    e.preventDefault();
    const orderId = $('#modalFormOrderId').val();
    const newStatus = $('#modalStatusSelect').val();
    
    updateOrderStatusDirect(orderId, newStatus, $(this).find('button[type="submit"]'));
});

/**
 * Format order type for display
 * Converts order type to user-friendly display text
 * @param {Object} order - Order data object
 * @returns {string} Formatted order type string
 */
function formatOrderType(order) {
    if (!order.order_type) return 'Unknown type';
    return order.order_type === 'dining' 
        ? `Dining (Table ${order.table_number || 'N/A'})` 
        : order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1);
}

/**
 * Initialize all event handlers
 * Sets up click handlers for view, cancel, status update, and bill preview buttons
 */
function initializeAllHandlers() {
    bindOrderHandlers();
    handleStatusUpdateButtons();
    initializeBillPreviewHandlers(); // Added bill preview handlers
    initializeKOTPreviewHandlers();
}

/**
 * Copy functionality for text elements
 * Allows copying of customer name, phone, and address to clipboard
 */
$(document).on('click', '.copy-btn', function() {
    const targetId = $(this).data('target');
    const textToCopy = $(`#${targetId}`).text().trim();
    
    // Use the modern Clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        // Use the Clipboard API for secure contexts (HTTPS)
        navigator.clipboard.writeText(textToCopy).then(() => {
            showCopyFeedback($(this));
        }).catch(err => {
            console.error('Failed to copy text: ', err);
            fallbackCopyText(textToCopy, $(this));
        });
    } else {
        // Fallback for non-secure contexts (HTTP)
        fallbackCopyText(textToCopy, $(this));
    }
});

/**
 * Fallback copy text method for older browsers
 * Uses deprecated execCommand for clipboard operations
 * @param {string} text - Text to copy
 * @param {jQuery} button - Button element that triggered copy
 */
function fallbackCopyText(text, button) {
    // Create a temporary textarea for fallback method
    const tempTextArea = document.createElement('textarea');
    tempTextArea.value = text;
    tempTextArea.style.position = 'fixed';
    tempTextArea.style.left = '-999999px';
    tempTextArea.style.top = '-999999px';
    document.body.appendChild(tempTextArea);
    tempTextArea.focus();
    tempTextArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopyFeedback(button);
        } else {
            throw new Error('Fallback copy failed');
        }
    } catch (err) {
        console.error('Fallback copy failed: ', err);
        // Last resort - show text for manual copy
        alert('Please copy manually: ' + text);
    } finally {
        document.body.removeChild(tempTextArea);
    }
}

/**
 * Show copy feedback on button
 * Temporarily changes button text to indicate successful copy
 * @param {jQuery} button - Button element to show feedback on
 */
function showCopyFeedback(button) {
    const originalHtml = button.html();
    button.html('<i class="bi bi-check"></i> Copied!').prop('disabled', true);
    
    // Revert button text after 2 seconds
    setTimeout(() => {
        button.html(originalHtml).prop('disabled', false);
    }, 2000);
}
</script>

<script>
/**
 * KOT Preview functionality
 * Shows KOT preview in modal and handles printing
 */

// Initialize KOT preview handlers
function initializeKOTPreviewHandlers() {
    $('.preview-kot').off('click').on('click', function(e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        showKOTPreview(orderId);
    });
}

/**
 * Show KOT preview for specific order
 * @param {number} orderId - ID of the order to preview
 */
function showKOTPreview(orderId) {
    console.log('👨‍🍳 Loading KOT preview for order:', orderId);
    
    // Find the order data from our global ordersData
    const order = window.ordersData?.find(o => o.order_id == orderId);
    if (!order) {
        showToast('Order data not found for KOT preview', 'danger');
        return;
    }
    
    // Update modal title
    $('#kotOrderId').text(orderId);
    
    // Generate and display KOT content
    const kotHtml = generateKOTHTML(order);
    $('#kotContent').html(kotHtml);
    
    // Show the modal
    const kotModal = new bootstrap.Modal(document.getElementById('kotPreviewModal'));
    kotModal.show();
}

/**
 * Generate KOT HTML for thermal printer (65mm)
 * @param {Object} order - Order data object
 * @returns {string} HTML string for the KOT
 */
function generateKOTHTML(order) {
    const businessName = "<?php echo addslashes($business_name); ?>";
    
    // Format order type
    const orderTypeDisplay = order.order_type === 'delivery' ? 'HOME DELIVERY' : 
                           order.order_type === 'dining' ? `DINE-IN (TABLE ${order.table_number})` : 'TAKEAWAY';
    
    // Format date and time with UAE adjustment
    const adjustedDate = adjustTimeForUAE(order.created_at);
    const orderDate = adjustedDate.toLocaleDateString('en-IN');
    const orderTime = adjustedDate.toLocaleTimeString('en-IN', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    
    // Get current time for footer with UAE adjustment
    const now = new Date();
    const adjustedNow = userCountry === 'UAE' ? adjustTimeForUAE(now) : now;
    const currentTime = adjustedNow.toLocaleTimeString('en-IN', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    
    let kotHtml = `
        <div class="kot-container">
            <div class="kot-header">
                <div class="business-name">${escapeHtml(businessName)}</div>
                <div class="kot-title">KITCHEN ORDER TICKET</div>
            </div>
            
            <div class="kot-double-divider"></div>
            
            <!-- Order Information -->
            <div class="kot-row">
                <div class="kot-item-name">Order #</div>
                <div class="kot-item-qty">${order.order_id}</div>
            </div>
            <div class="kot-row">
                <div class="kot-item-name">Date</div>
                <div class="kot-item-qty">${orderDate}</div>
            </div>
            <div class="kot-row">
                <div class="kot-item-name">Time</div>
                <div class="kot-item-qty">${orderTime}</div>
            </div>
            <div class="kot-row">
                <div class="kot-item-name">Type</div>
                <div class="kot-item-qty">${orderTypeDisplay}</div>
            </div>
            
            <div class="kot-divider"></div>
            
            <!-- Customer Information -->
            <div class="kot-row">
                <div class="kot-item-name">Customer:</div>
                <div class="kot-item-qty">${escapeHtml(order.customer_name)}</div>
            </div>
    `;
    
    if (order.order_type === 'dining') {
        kotHtml += `
            <div class="kot-row">
                <div class="kot-item-name">Table No:</div>
                <div class="kot-item-qty">${order.table_number}</div>
            </div>
        `;
    }
    
    kotHtml += `
            <div class="kot-double-divider"></div>
            
            <!-- Order Items -->
            <div class="kot-row" style="font-weight: bold;">
                <div class="kot-item-name">ITEMS</div>
                <div class="kot-item-qty">QTY</div>
            </div>
            <div class="kot-divider"></div>
    `;
    
    // Add order items
    if (order.items && order.items.length > 0) {
        order.items.forEach(item => {
            kotHtml += `
            <div class="kot-row">
                <div class="kot-item-name">${escapeHtml(item.product_name)}</div>
                <div class="kot-item-qty">${item.quantity}x</div>
            </div>
            `;
            
            // Add special instructions if available in order notes
            if (order.order_notes && order.order_notes.toLowerCase().includes(item.product_name.toLowerCase())) {
                kotHtml += `
                <div class="kot-row">
                    <div class="kot-item-special">* Special: ${escapeHtml(order.order_notes)}</div>
                </div>
                `;
            }
        });
    }
    
    // Add general order notes if available
    if (order.order_notes && !order.items.some(item => 
        order.order_notes.toLowerCase().includes(item.product_name.toLowerCase()))) {
        kotHtml += `
            <div class="kot-double-divider"></div>
            <div class="kot-row">
                <div class="kot-item-special" style="text-align: center; font-weight: bold;">
                    SPECIAL INSTRUCTIONS:
                </div>
            </div>
            <div class="kot-row">
                <div class="kot-item-special" style="text-align: center;">
                    ${escapeHtml(order.order_notes)}
                </div>
            </div>
        `;
    }
    
    kotHtml += `
            <div class="kot-double-divider"></div>
            
            <!-- Footer -->
            <div class="kot-footer">
                <div>*** KITCHEN COPY ***</div>
                <div>Order Time: ${orderTime}</div>
                <div style="margin-top: 3px;">
                    ${currentTime}
                </div>
            </div>
        </div>
    `;
    
    return kotHtml;
}

/**
 * Print the KOT
 * Uses browser's print functionality with thermal printer styling
 */
function printKOT() {
    const kotContent = document.getElementById('kotContent');
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=65mm,height=600,scrollbars=no,toolbar=no,location=no');
    
    if (!printWindow) {
        showToast('Please allow popups for printing', 'warning');
        return;
    }
    
    // Write the KOT content to the new window with proper thermal printer styling
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>KOT - Order #${$('#kotOrderId').text()}</title>
            <style>
                @page {
                    margin: 0;
                    padding: 0;
                    size: 65mm auto;
                }
                body {
                    margin: 0;
                    padding: 5px;
                    font-family: 'Arial';
                    font-size: 12px;
                    line-height: 1.2;
                    width: 65mm;
                    background: white;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                    color: #000 !important;
                }
                .kot-container {
                    width: 65mm;
                    max-width: 65mm;
                    font-family: 'Arial';
                    font-size: 12px;
                    line-height: 1.2;
                    background: white;
                    padding: 0;
                    margin: 0 auto;
                    color: #000 !important;
                }
                .kot-header {
                    text-align: center;
                    margin-bottom: 5px;
                    color: #000 !important;
                }
                .kot-header .business-name {
                    font-weight: bold;
                    font-size: 14px;
                    margin-bottom: 2px;
                    color: #000 !important;
                }
                .kot-header .kot-title {
                    font-weight: bold;
                    font-size: 16px;
                    margin-bottom: 3px;
                    color: #000 !important;
                    text-transform: uppercase;
                }
                .kot-divider {
                    border-bottom: 1px solid #000;
                    margin: 3px 0;
                }
                .kot-double-divider {
                    border-bottom: 2px solid #000;
                    margin: 3px 0;
                }
                .kot-row {
                    display: flex;
                    justify-content: space-between;
                    margin: 1px 0;
                    color: #000 !important;
                }
                .kot-item-name {
                    flex: 2;
                    text-align: left;
                    font-size: 11px;
                    color: #000 !important;
                }
                .kot-item-qty {
                    flex: 1;
                    text-align: center;
                    color: #000 !important;
                    font-weight: bold;
                }
                .kot-item-special {
                    flex: 3;
                    text-align: left;
                    font-size: 10px;
                    font-style: italic;
                    color: #000 !important;
                    margin-top: -2px;
                }
                .kot-footer {
                    margin-top: 5px;
                    font-size: 10px;
                    text-align: center;
                    color: #000 !important;
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 5px;
                        width: 65mm;
                        color: #000 !important;
                    }
                    * {
                        color: #000 !important;
                    }
                }
            </style>
        </head>
        <body>
            ${kotContent.innerHTML}
        </body>
        </html>
    `);
    
    
    printWindow.document.close();
    
    // Wait for content to load then trigger print
    printWindow.onload = function() {
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
    
    // Fallback: if onload doesn't fire, try printing after a delay
    setTimeout(() => {
        if (!printWindow.closed) {
            printWindow.print();
        }
    }, 1000);
}
</script>
</body>
</html>
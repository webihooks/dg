<?php
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
require_once 'includes/loyalty_helper.php';   // Added loyalty helper

// Authentication check - ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success';

// Get loyalty settings for this restaurant
$loyalty_settings = getLoyaltySettings($conn, $user_id);
$redemption_points = $loyalty_settings['redemption_points'];
$redemption_amount = $loyalty_settings['redemption_currency_amount'];

// Check if user has Borzo API key configured
$hasBorzoApi = false;
$borzoApiWarning = '';

// Check borzo_api table for existing API key
$check_api_sql = "SELECT id FROM borzo_api WHERE user_id = ?";
$check_api_stmt = $conn->prepare($check_api_sql);
$check_api_stmt->bind_param("i", $user_id);
$check_api_stmt->execute();
$check_api_result = $check_api_stmt->get_result();
$hasBorzoApi = ($check_api_result->num_rows > 0);
$check_api_stmt->close();

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
        $db_timezone = 'Asia/Kolkata';
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
        date_default_timezone_set('Asia/Kolkata');
        $db_timezone = 'Asia/Kolkata';
}

function adjustTimeForUAE($dateTime, $user_country) {
    if ($user_country == 'UAE') {
        $date = new DateTime($dateTime);
        $date->modify('-1 hour -30 minutes');
        return $date->format('Y-m-d H:i:s');
    }
    return $dateTime;
}

function displayTime($dateTime, $user_country) {
    $date = new DateTime($dateTime);
    if ($user_country == 'UAE') {
        $date->modify('-1 hour -30 minutes');
    }
    return $date->format('d/m/Y h:i A');
}

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = date('Y-m-d');
}
if ($to_date < $from_date) {
    $to_date = $from_date;
}

function getCurrencySymbol($country) {
    $currencySymbols = [
        'India' => '₹',
        'UAE' => 'AED',
        'UK' => '£',
        'USA' => '$'
    ];
    return isset($currencySymbols[$country]) ? $currencySymbols[$country] : '₹';
}

$currencySymbol = getCurrencySymbol($user_country);
$taxLabel = ($user_country == 'UAE') ? 'VAT' : 'GST';

$business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $user_id);
$business_stmt->execute();
$business_stmt->bind_result($business_name, $business_address);
$business_stmt->fetch();
$business_stmt->close();

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

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 200;
$offset = ($page - 1) * $per_page;

if ($user_country == 'UAE') {
    $from_date_obj = new DateTime($from_date . ' 00:00:00', new DateTimeZone('Asia/Dubai'));
    $from_date_obj->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $from_date_adjusted = $from_date_obj->format('Y-m-d');
    
    $to_date_obj = new DateTime($to_date . ' 23:59:59', new DateTimeZone('Asia/Dubai'));
    $to_date_obj->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $to_date_adjusted = $to_date_obj->format('Y-m-d');
    
    $date_condition = "DATE(CONVERT_TZ(created_at, '+00:00', '+05:30')) BETWEEN ? AND ?";
    $date_params = [$from_date_adjusted, $to_date_adjusted];
} else {
    $date_condition = "DATE(created_at) BETWEEN ? AND ?";
    $date_params = [$from_date, $to_date];
}

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

// Fetch orders with all Borzo fields including address components AND loyalty points
$orders_sql = "SELECT 
    o.order_id, 
    o.customer_name, 
    o.customer_phone, 
    o.order_type, 
    o.delivery_address, 
    o.building,
    o.floor,
    o.flat_unit,
    o.landmark,
    o.table_number, 
    o.status, 
    o.subtotal, 
    o.discount_amount, 
    o.discount_type, 
    o.loyalty_points_redeemed,
    o.loyalty_points_value,
    o.loyalty_points_earned,
    o.gst_amount, 
    o.delivery_charge, 
    o.total_amount, 
    o.created_at,
    o.order_notes,
    o.updated_at,
    o.borzo_order_id,
    o.borzo_order_name,
    o.borzo_status,
    o.borzo_status_description,
    o.delivery_fee,
    o.delivery_tracking_url,
    o.borzo_geocoded_address,
    o.estimated_pickup_time,
    o.estimated_delivery_time,
    o.actual_pickup_time,
    o.actual_delivery_time,
    o.courier_name,
    o.courier_phone,
    o.courier_latitude,
    o.courier_longitude,
    o.borzo_last_sync,
    COUNT(oi.item_id) as item_count
FROM orders o
LEFT JOIN order_items oi ON o.order_id = oi.order_id
WHERE o.user_id = ? AND $date_condition
GROUP BY o.order_id
ORDER BY o.created_at DESC
LIMIT ? OFFSET ?";

$orders_stmt = $conn->prepare($orders_sql);

if ($user_country == 'UAE') {
    $orders_stmt->bind_param("issii", $user_id, $date_params[0], $date_params[1], $per_page, $offset);
} else {
    $orders_stmt->bind_param("issii", $user_id, $date_params[0], $date_params[1], $per_page, $offset);
}

$orders_stmt->execute();
$result = $orders_stmt->get_result();
$orders = [];

while ($order = $result->fetch_assoc()) {
    $items_sql = "SELECT product_name, price, quantity FROM order_items WHERE order_id = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $order['order_id']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $order['items'] = $items_result->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();
    
    $order_created = strtotime($order['created_at']);
    $current_time = time();
    $time_elapsed = $current_time - $order_created;
    $time_limit = 30 * 60;
    $time_remaining = $time_limit - $time_elapsed;
    
    $order['timer_remaining'] = max(0, $time_remaining);
    $order['is_delayed'] = $time_elapsed > $time_limit;
    $order['is_completed_on_time'] = ($order['status'] === 'Completed' && !$order['is_delayed']);
    $order['created_at_original'] = $order['created_at'];
    
    $orders[] = $order;
}
$orders_stmt->close();
$conn->close();

// Function to format full address with all components
function formatFullAddress($order) {
    $addressParts = [];
    
    if (!empty($order['building'])) {
        $addressParts[] = trim($order['building']);
    }
    if (!empty($order['floor'])) {
        $addressParts[] = 'Floor ' . trim($order['floor']);
    }
    if (!empty($order['flat_unit'])) {
        $addressParts[] = 'Unit ' . trim($order['flat_unit']);
    }
    if (!empty($order['landmark'])) {
        $addressParts[] = 'Near ' . trim($order['landmark']);
    }
    if (!empty($order['delivery_address'])) {
        $addressParts[] = trim($order['delivery_address']);
    }
    
    return implode(', ', array_filter($addressParts));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Order Management - DeeGeeCard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
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
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css?<?php echo time(); ?>" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
*{margin:0;padding:0;box-sizing:border-box}.table tbody tr:last-child td{border-bottom:1px solid #dee2e6!important}.btn{padding:20px 5px;min-width:85px}.table>:not(caption)>*>*{padding:5px}.status-badge{padding:5px 10px;border-radius:20px;font-weight:700;font-size:.8em}.borzo-status-pending,.status-Pending{background-color:#ffc107;color:#000}.status-Confirmed{background-color:#17a2b8;color:#fff}.status-Preparing{background-color:#fd7e14;color:#fff}.status-Ready{background-color:#28a745;color:#fff}.status-Completed{background-color:orange;color:#fff}.status-Cancelled{background-color:#dc3545;color:#fff}.borzo-status-badge{font-size:.7em;padding:2px 5px;border-radius:10px;background-color:#6c757d;color:#fff;margin-left:5px}.borzo-status-active{background-color:#28a745}.borzo-status-delivered{background-color:#17a2b8}.bi-arrow-repeat.spin{animation:1s linear infinite spin}@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}.timer{display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:6px;background-color:#000;font-weight:700;color:#fff}.timer.warning{background-color:orange;color:#000}.timer.danger{background-color:red;color:#fff;animation:1s infinite blink}@keyframes blink{0%,50%{opacity:1}100%,51%{opacity:.5}}.timer-column{min-width:120px}.btn-info.update-status-btn,.btn-info.update-status-btn.wave-pulse,.btn-success.update-status-btn{position:relative;overflow:hidden}.btn-success.update-status-btn{border:2px solid #198754}.btn-success.update-status-btn.pulse-border{animation:2s infinite borderPulse}@keyframes borderPulse{0%{box-shadow:0 0 0 0 rgba(25,135,84,.7);border-color:#198754}70%{box-shadow:0 0 0 10px rgba(25,135,84,0);border-color:#20c997}100%{box-shadow:0 0 0 0 rgba(25,135,84,0);border-color:#198754}}.btn-info.update-status-btn{border:2px solid #ff6c2f}.btn-info.update-status-btn.pulse-border{animation:2s infinite borderPulseOrange}@keyframes borderPulseOrange{0%{box-shadow:0 0 0 0 rgba(255,108,47,.7);border-color:#ff6c2f}70%{box-shadow:0 0 0 10px rgba(255,108,47,0);border-color:#ff8c5a}100%{box-shadow:0 0 0 0 rgba(255,108,47,0);border-color:#ff6c2f}}@media (max-width:768px){.mobile_table .print-bill,.mobile_table .update-status-btn[data-new-status=Completed],.mobile_table .update-status-btn[data-new-status=Ready]{width:100%;margin:5px 0;display:block;padding:10px 20px;font-size:15px;text-align:left}.mobile_table td[data-label=Actions]{text-align:center;min-height:80px}.timer-column{min-width:100px}.mobile_table tr{position:relative}.mobile_table .table td.timer-column:before{display:none}.mobile_table .table td.timer-column{border-bottom:0!important}.clountdown_group{position:absolute;top:193px;z-index:99;right:28px}.btn.btn-sm.btn-primary.view-order{margin-top:0}.copy-btn{padding:5px 0}#statusUpdateForm{width:70%}.btn.btn-secondary{padding:20px 10px;min-width:0}#modalStatusSelect{padding:8px}}.btn-warning{background-color:#ffc107;border-color:#ffc107;color:#000}.btn-warning:hover{background-color:#e0a800;border-color:#e0a800;color:#000}.bill-container,.kot-container{width:65mm;max-width:65mm;font-family:Arial;font-size:12px;line-height:1.2;background:#fff;padding:0;margin:0 auto;color:#000!important}.bill-header,.kot-header{text-align:center;margin-bottom:5px;color:#000!important}.bill-header .business-name,.kot-header .business-name{font-weight:700;font-size:14px;margin-bottom:2px;color:#000!important}.bill-header .business-address{font-size:10px;margin-bottom:2px;color:#000!important}.bill-header .business-phone{font-size:10px;margin-bottom:3px;color:#000!important}.bill-divider,.kot-divider{border-bottom:1px solid #000;margin:3px 0}.bill-double-divider,.kot-double-divider{border-bottom:2px solid #000;margin:3px 0}.bill-row,.bill-summary-row,.kot-row{display:flex;justify-content:space-between;margin:1px 0;color:#000!important}.bill-item-name,.kot-item-name{flex:2;text-align:left;font-size:11px;color:#000!important}.bill-item-qty{flex:1;text-align:center;color:#000!important}.bill-item-price,.bill-item-total,.bill-summary-value{flex:1;text-align:right;color:#000!important}.bill-summary-label{flex:2;text-align:left;color:#000!important}.bill-footer,.kot-footer,.kot-item-qty{text-align:center;color:#000!important}.bill-footer,.kot-footer{margin-top:5px;font-size:10px}@media (min-width:576px){.modal-sm{--bs-modal-width:330px!important}}#billPreviewModal .modal-dialog,#kotPreviewModal .modal-dialog{max-width:100%;margin:0 auto}#billPreviewModal .modal-content{border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.3)}#billPreviewModal .modal-header,#kotPreviewModal .modal-header{background:#f8f9fa;border-bottom:1px solid #dee2e6;padding:12px 15px}#billPreviewModal .modal-title,#kotPreviewModal .modal-title{font-size:16px;font-weight:600;color:#333}#billPreviewModal .modal-body,#kotPreviewModal .modal-body{padding:15px;max-height:70vh;overflow-y:auto;background:#fff}#billPreviewModal .modal-footer,#kotPreviewModal .modal-footer{background:#f8f9fa;border-top:1px solid #dee2e6;padding:12px 15px;display:flex;flex-wrap:wrap;gap:8px}#billPreviewModal .btn,#kotPreviewModal .btn{flex:1;min-width:120px;padding:10px 15px;font-size:14px;border-radius:6px}@media (max-width:400px){#billPreviewModal .modal-dialog{max-width:calc(100% - 20px)}#billPreviewModal .modal-content{border-radius:6px}#billPreviewModal .modal-header{padding:10px 12px}#billPreviewModal .modal-title{font-size:14px;text-align:center}#billPreviewModal .btn-close{width:25px;height:25px;padding:0;margin:0;position:absolute;right:10px;top:10px}#billPreviewModal .modal-body{padding:10px 8px;max-height:60vh}#billPreviewModal .bill-container{width:100%!important;max-width:100%!important;padding:8px!important;transform:scale(.85);transform-origin:top center}#billPreviewModal .bill-header .business-name{font-size:13px!important}#billPreviewModal .bill-header .business-address,#billPreviewModal .bill-header .business-phone,#billPreviewModal .bill-item-name{font-size:9px!important}#billPreviewModal .bill-row,#billPreviewModal .bill-summary-row{font-size:10px!important}#billPreviewModal .modal-footer{padding:10px 12px;flex-direction:column}#billPreviewModal .modal-footer .btn{flex:none;width:100%;margin:2px 0;padding:12px 15px;font-size:14px}#billPreviewModal .modal-footer .btn-secondary{order:2}#billPreviewModal .modal-footer .btn-primary{order:1}#billPreviewModal .btn-primary{border:none;font-weight:600;box-shadow:0 2px 4px rgba(0,123,255,.3)}#billPreviewModal .btn-primary:active{transform:translateY(1px);box-shadow:0 1px 2px rgba(0,123,255,.3)}}.kot-header .kot-title{font-weight:700;font-size:16px;margin-bottom:3px;color:#000!important;text-transform:uppercase}.kot-item-qty{flex:1;font-weight:700}.kot-item-special{flex:3;text-align:left;font-size:10px;font-style:italic;color:#000!important;margin-top:-2px}.loading-spinner,th{text-align:center}.btn-dark{background-color:#343a40;border-color:#343a40;color:#fff}.btn-dark:hover{background-color:#23272b;border-color:#23272b;color:#fff}#kotPreviewModal .modal-content{border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.3);animation:.3s ease-out kotSlideIn}@media (min-width:601px){#billPreviewModal,#kotPreviewModal{width:500px;left:50%;top:50px;margin:0 0 0 -250px;padding:0}}@media (max-width:600px){#billPreviewModal,#kotPreviewModal{width:300px;left:50%;top:150px;margin:0 0 0 -150px;padding:0}#billPreviewModal .modal-dialog,#kotPreviewModal .modal-dialog{margin:0 auto;padding:0}}@media (max-width:400px){#billPreviewModal .modal-body::-webkit-scrollbar{width:4px}#billPreviewModal .modal-body::-webkit-scrollbar-track{background:#f1f1f1;border-radius:2px}#billPreviewModal .modal-body::-webkit-scrollbar-thumb{background:#c1c1c1;border-radius:2px}#billPreviewModal .modal-body::-webkit-scrollbar-thumb:hover{background:#a8a8a8}#kotPreviewModal .modal-dialog{max-width:calc(100% - 20px)}#kotPreviewModal .modal-content{border-radius:6px}#kotPreviewModal .modal-header{padding:10px 12px}#kotPreviewModal .modal-title{font-size:14px;text-align:center}#kotPreviewModal .btn-close{width:25px;height:25px;padding:0;margin:0;position:absolute;right:10px;top:10px}#kotPreviewModal .modal-body{padding:10px 8px;max-height:60vh}#kotPreviewModal .kot-container{width:100%!important;max-width:100%!important;padding:8px!important;transform:scale(.85);transform-origin:top center}#kotPreviewModal .kot-header .business-name{font-size:13px!important}#kotPreviewModal .kot-header .kot-title{font-size:14px!important}#kotPreviewModal .kot-item-name,#kotPreviewModal .kot-item-qty,#kotPreviewModal .kot-row{font-size:10px!important}#kotPreviewModal .kot-item-special{font-size:9px!important}#kotPreviewModal .modal-footer{padding:10px 12px;flex-direction:column}#kotPreviewModal .modal-footer .btn{flex:none;width:100%;margin:2px 0;padding:12px 15px;font-size:14px}#kotPreviewModal .modal-footer .btn-secondary{order:2}#kotPreviewModal .modal-footer .btn-success{order:1;border:none;font-weight:600}#kotPreviewModal .btn-success{background:linear-gradient(135deg,#198754,#13653f);border:none;font-weight:600;box-shadow:0 2px 4px rgba(25,135,84,.3)}#kotPreviewModal .btn-success:active{transform:translateY(1px);box-shadow:0 1px 2px rgba(25,135,84,.3)}}@media (max-width:320px){#billPreviewModal .modal-dialog,#kotPreviewModal .modal-dialog{margin:5px;max-width:calc(100% - 10px)}#billPreviewModal .modal-footer,#billPreviewModal .modal-header,#kotPreviewModal .modal-footer,#kotPreviewModal .modal-header{padding:8px 10px}#billPreviewModal .modal-title,#kotPreviewModal .modal-title{font-size:13px;padding-right:25px}#billPreviewModal .modal-body,#kotPreviewModal .modal-body{padding:8px 5px;max-height:55vh}#billPreviewModal .bill-container,#kotPreviewModal .kot-container{transform:scale(.8);padding:5px!important}#billPreviewModal .bill-header .business-name,#kotPreviewModal .kot-header .business-name{font-size:12px!important}#billPreviewModal .bill-row,#billPreviewModal .bill-summary-row,#kotPreviewModal .kot-item-name,#kotPreviewModal .kot-item-qty,#kotPreviewModal .kot-row{font-size:9px!important;margin:.5px 0!important}#billPreviewModal .modal-footer .btn,#kotPreviewModal .modal-footer .btn{padding:10px 12px;font-size:13px}#kotPreviewModal .kot-header .kot-title{font-size:13px!important}#kotPreviewModal .kot-item-special{font-size:8px!important}}@media (max-width:400px) and (orientation:landscape){#billPreviewModal .modal-body,#kotPreviewModal .modal-body{max-height:50vh}#billPreviewModal .bill-container,#kotPreviewModal .kot-container{transform:scale(.75)}#billPreviewModal .modal-footer,#kotPreviewModal .modal-footer{flex-direction:row;padding:8px 10px}#billPreviewModal .modal-footer .btn,#kotPreviewModal .modal-footer .btn{flex:1;min-width:auto;padding:8px 10px}}@media (max-width:400px) and (hover:none) and (pointer:coarse){#billPreviewModal .btn,#kotPreviewModal .btn{min-height:44px}#billPreviewModal .modal-body,#kotPreviewModal .modal-body{-webkit-overflow-scrolling:touch}#kotPreviewModal .btn-close{min-width:25px;min-height:25px}}@media (max-width:400px) and (-webkit-min-device-pixel-ratio:2),(max-width:400px) and (min-resolution:192dpi){#billPreviewModal .modal-content,#kotPreviewModal .modal-content{border:.5px solid #ccc}#billPreviewModal .bill-divider,#billPreviewModal .bill-double-divider,#kotPreviewModal .kot-divider,#kotPreviewModal .kot-double-divider{border-width:.5px}}@media (max-width:400px) and (prefers-color-scheme:dark){#billPreviewModal .modal-content,#kotPreviewModal .modal-content{background:#2d3748;color:#e2e8f0}#billPreviewModal .modal-header,#kotPreviewModal .modal-header{background:#4a5568;border-bottom-color:#718096}#billPreviewModal .modal-footer,#kotPreviewModal .modal-footer{background:#4a5568;border-top-color:#718096}#billPreviewModal .modal-title,#kotPreviewModal .modal-title{color:#e2e8f0}#billPreviewModal .bill-container,#kotPreviewModal .kot-container{background:#2d3748!important;color:#e2e8f0!important}#kotPreviewModal .kot-divider,#kotPreviewModal .kot-double-divider{border-bottom-color:#e2e8f0}}@media (max-width:400px){#kotPreviewModal .modal-body::-webkit-scrollbar{width:4px}#kotPreviewModal .modal-body::-webkit-scrollbar-track{background:#f1f1f1;border-radius:2px}#kotPreviewModal .modal-body::-webkit-scrollbar-thumb{background:#c1c1c1;border-radius:2px}#kotPreviewModal .modal-body::-webkit-scrollbar-thumb:hover{background:#a8a8a8}}@keyframes kotSlideIn{from{opacity:0;transform:translateY(-50px) scale(.9)}to{opacity:1;transform:translateY(0) scale(1)}}#kotPreviewModal .btn:focus{box-shadow:0 0 0 3px rgba(25,135,84,.25);outline:0}#kotPreviewModal .kot-container.loading{opacity:.6;pointer-events:none}@media print{body *{visibility:hidden}.bill-container,.bill-container *{visibility:visible;color:#000!important}.bill-container{position:absolute;left:0;top:0;width:65mm;max-width:65mm;color:#000!important}#kotPreviewModal .modal-footer,#kotPreviewModal .modal-header,.modal-footer,.modal-header{display:none!important}#kotPreviewModal .modal-body{max-height:none;overflow:visible;padding:0}#kotPreviewModal .kot-container{transform:none!important;width:65mm!important;max-width:65mm!important;padding:5px!important}}th:last-child,th:nth-last-child(2){width:100px}.scroll-to-top{bottom:15px}.borzo-fare{font-weight:600;color:#28a745}.borzo-status{font-size:.8em;padding:3px 6px;border-radius:12px}.borzo-details {background: #f8f9fa;padding: 2px 5px;border-radius: 6px;border: 1px solid #c9c9c9;}.borzo-id-badge{margin:3px;background:#17a2b8;color:#fff;padding:3px 8px;border-radius:12px;font-size:.75em;font-weight:600}.action-buttons{display:flex;flex-wrap:wrap;gap:5px;justify-content:flex-end}.action-buttons .btn{margin:2px}.btn-cancel-borzo{background:#dc3545;color:#fff;border:none}.btn-cancel-borzo:hover{background:#c82333}.btn-book-borzo{background:#28a745;color:#fff;border:none}.btn-book-borzo:hover,.refresh-courier-btn:hover{background:#218838}.print-section{background:#f1f1f1;padding:5px;border-radius:6px;margin:5px 0}.borzo-courier-info{padding:8px;margin-top:8px;font-size:.9em}.print-buttons{display:flex;gap:5px;justify-content:center}.date-time-cell .date-display{font-weight:600;color:#333}.date-time-cell .time-display{font-size:.85em;color:#666}.borzo-status-badge.borzo-status-canceled{background:red}.borzo-status-badge.borzo-status-new{background:green}.clountdown_group{float:right}.btn-success.book-borzo-delivery{background-color:#06c!important;border-color:#0052a3!important;color:#fff!important;transition:.3s;box-shadow:0 2px 4px rgba(0,102,204,.2)}.btn-success.book-borzo-delivery:hover{background-color:#0052a3!important;border-color:#003d7a!important;transform:translateY(-1px);box-shadow:0 4px 8px rgba(0,102,204,.3)}.btn-success.book-borzo-delivery:active{background-color:#003d7a!important;border-color:#002856!important;transform:translateY(0);box-shadow:0 1px 2px rgba(0,102,204,.2)}.btn-success.book-borzo-delivery:focus{outline:0;box-shadow:0 0 0 3px rgba(0,102,204,.4)}.btn-success.book-borzo-delivery:disabled{background-color:#99c2ff!important;border-color:#80b3ff!important;cursor:not-allowed;opacity:.65;transform:none;box-shadow:none}.btn-success.book-borzo-delivery .spinner-border,.btn-success.book-borzo-delivery .spinner-grow{width:1rem;height:1rem;margin-right:5px}@keyframes borzo-pulse{0%{box-shadow:0 0 0 0 rgba(0,102,204,.7)}70%{box-shadow:0 0 0 10px rgba(0,102,204,0)}100%{box-shadow:0 0 0 0 rgba(0,102,204,0)}}.btn-success.book-borzo-delivery.pulse{animation:2s infinite borzo-pulse}.btn-success.book-borzo-delivery i,.btn-success.book-borzo-delivery svg{margin-right:4px;font-size:1rem}.btn-sm.btn-success.book-borzo-delivery i,.btn-sm.btn-success.book-borzo-delivery svg{font-size:.875rem;margin-right:3px}@media (prefers-color-scheme:dark){.btn-success.book-borzo-delivery{background-color:#1a75ff!important;border-color:#06c!important}.btn-success.book-borzo-delivery:hover{background-color:#06c!important;border-color:#0052a3!important}}.btn-success.book-borzo-delivery.gradient{background:linear-gradient(135deg,#06c,#0052a3)!important;border:none!important}.btn-success.book-borzo-delivery.gradient:hover{background:linear-gradient(135deg,#0052a3,#003d7a)!important}@media(min-width:768px){.table td,.table th{vertical-align:middle;min-height:150px}}@media(max-width:600px){.borzo-details{margin-left:110px}}.borzo-courier-info{background:#e8f4fd;border-left:3px solid #06c;border-radius:4px}.borzo-courier-info i{color:#06c;margin-right:5px}.sync-borzo-btn{padding:2px 6px;font-size:.8em;margin-left:5px}.track-live-btn{padding:4px 10px;font-size:.85em;color:#fff}.track-live-btn{background:#06c;border:none;border-radius:4px;text-decoration:none;display:inline-block;margin-top:5px}.track-live-btn:hover{background:#0052a3;color:#fff}.loading-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;display:none;justify-content:center;align-items:center}.loading-spinner{background:#fff;padding:20px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,.2)}.loading-spinner i{font-size:40px;color:#06c;margin-bottom:10px}.refresh-courier-btn{padding:2px 6px;font-size:.8em;margin-left:5px;background:#28a745;color:#fff;border:none;border-radius:4px}



@media (max-width:600px) {
    .borzo-details-cell {
      min-height: 82px;
    }
    .borzo-estimate {
      height: 100px;
      float: right;
    }
    .cancel-borzo-delivery {
      float: right;
    }
}

/* Loyalty points badge */
.loyalty-badge {
    display: inline-block;
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 12px;
    background: #f0f0f0;
    color: #333;
    margin-top: 3px;
}
.loyalty-badge.earned {
    background: #d4edda;
    color: #155724;
}
.loyalty-badge.redeemed {
    background: #f8d7da;
    color: #721c24;
}
    </style>

</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="bi bi-arrow-repeat spin"></i>
            <h5>Processing...</h5>
        </div>
    </div>

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
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($borzoApiWarning)) echo $borzoApiWarning; ?>

                                <h5 class="mb-3">
                                    <?php 
                                    $today = date('Y-m-d');
                                    if ($from_date == $today && $to_date == $today) {
                                        echo "Today's Orders (" . date('F j, Y', strtotime($from_date)) . ")";
                                    } else {
                                        echo "Orders from " . date('F j, Y', strtotime($from_date)) . " to " . date('F j, Y', strtotime($to_date));
                                    }
                                    ?>
                                </h5>

                                <?php if (empty($orders)): ?>
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
                                                    <?php if ($hasBorzoApi): ?>
                                                        <th>Borzo Details</th>
                                                    <?php endif; ?>
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
                                                        <td data-label="Date & Time" class="date-time-cell">
                                                            <?php 
                                                            $displayTime = displayTime($order['created_at'], $user_country);
                                                            if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(.+)$/', $displayTime, $matches)) {
                                                                echo '<div class="date-display">' . $matches[1] . '</div>';
                                                                echo '<div class="time-display small">' . $matches[2] . '</div>';
                                                            } else {
                                                                echo $displayTime;
                                                            }
                                                            ?>
                                                        </td>
                                                        <td data-label="Customer"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                        <td data-label="Type">
                                                            <?php 
                                                            if ($order['order_type'] === 'dining') {
                                                                echo '<span class="badge bg-info">Dining</span> Table ' . htmlspecialchars($order['table_number']);
                                                            } elseif ($order['order_type'] === 'delivery') {
                                                                echo '<span class="badge bg-success">Delivery</span>';
                                                            } else {
                                                                echo '<span class="badge bg-warning">Takeaway</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td data-label="Items"><?php echo htmlspecialchars($order['item_count']); ?></td>
                                                        <td data-label="Total"><strong><?php echo $currencySymbol; ?> <?php echo number_format($order['total_amount']); ?></strong></td>
                                                        <td data-label="Status">
                                                            <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                                                <?php echo htmlspecialchars($order['status']); ?>
                                                            </span>
                                                        </td>
                                                        <?php if ($hasBorzoApi): ?>
                                                            <td data-label="Borzo Details" class="borzo-details-cell">
                                                                <?php if (!empty($order['borzo_order_id'])): ?>
                                                                    <div class="borzo-details">
                                                                        <div class="d-flex align-items-center mb-1 flex-wrap">
                                                                            <span class="borzo-id-badge me-1">ID: <?php echo $order['borzo_order_id']; ?></span>
                                                                            <span class="borzo-status-badge borzo-status-<?php echo $order['borzo_status'] ?? 'pending'; ?>">
                                                                                <?php echo $order['borzo_status'] ?? 'Booked'; ?>
                                                                            </span>
                                                                            <button class="btn btn-sm btn-outline-secondary sync-borzo-btn sync-borzo" 
                                                                                    data-order-id="<?php echo $order['order_id']; ?>"
                                                                                    title="Sync with Borzo">
                                                                                <i class="bi bi-arrow-repeat"></i>
                                                                            </button>
                                                                        </div>
                                                                        
                                                                        <div class="small">
                                                                            <div class="d-flex align-items-center flex-wrap">
                                                                                <span class="borzo-fare me-2"><?php echo $currencySymbol; ?> <?php echo number_format($order['delivery_fee'] ?? $order['delivery_charge']); ?></span>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <?php if (!empty($order['courier_name'])): ?>
                                                                            <div class="borzo-courier-info">
                                                                                <div class="d-flex align-items-center">
                                                                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($order['courier_name']); ?>
                                                                                    <?php if (!empty($order['courier_latitude']) && !empty($order['courier_longitude'])): ?>
                                                                                        <button class="refresh-courier-btn ms-2" onclick="refreshCourierLocation(<?php echo $order['order_id']; ?>)">
                                                                                            <i class="bi bi-arrow-repeat"></i>
                                                                                        </button>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                                <?php if (!empty($order['courier_phone'])): ?>
                                                                                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($order['courier_phone']); ?>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($order['courier_latitude']) && !empty($order['courier_longitude'])): ?>
                                                                                    <a href="track-order.php?id=<?php echo $order['order_id']; ?>" target="_blank" class="track-live-btn btn-sm w-100 text-center mt-1">
                                                                                        <i class="bi bi-map"></i> Track Live
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <?php if ($order['order_type'] === 'delivery' && $order['status'] === 'Confirmed' && $hasBorzoApi): ?>
                                                                        <div class="borzo-estimate">
                                                                            <div class="d-flex gap-1">
                                                                                <button class="btn btn-sm btn-success book-borzo-delivery" 
                                                                                        data-order-id="<?php echo $order['order_id']; ?>">
                                                                                    <i class="bi bi-truck"></i> Book
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php endif; ?>
                                                        <td data-label="Timer" class="timer-column">
                                                            <div class="clountdown_group">
                                                                <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing', 'Ready'])): ?>
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
                                                        <td data-label="Print" class="print-section">
                                                            <div class="print-buttons">
                                                                <button class="btn btn-sm btn-warning preview-kot" 
                                                                        data-order-id="<?php echo $order['order_id']; ?>"
                                                                        title="Print KOT (Kitchen Order Ticket)">
                                                                    <i class="bi bi-printer-fill"></i> KOT
                                                                </button>
                                                                <button class="btn btn-sm btn-warning preview-bill" 
                                                                        data-order-id="<?php echo $order['order_id']; ?>"
                                                                        title="Preview & Print Bill">
                                                                    <i class="bi bi-receipt"></i> Bill
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td data-label="Actions" class="action-buttons">
                                                            <button class="btn btn-sm btn-primary view-order" 
                                                                    data-order-id="<?php echo $order['order_id']; ?>"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#orderModal"
                                                                    title="View Order Details">
                                                                <i class="bi bi-eye"></i> View
                                                            </button>

                                                            <?php if (!empty($order['borzo_order_id']) && in_array($order['borzo_status'], ['new', 'available', 'active', 'delayed'])): ?>
                                                                <button class="btn btn-sm btn-danger cancel-borzo-delivery" 
                                                                        data-order-id="<?php echo $order['order_id']; ?>"
                                                                        data-borzo-id="<?php echo $order['borzo_order_id']; ?>"
                                                                        title="Cancel Borzo Delivery">
                                                                    <i class="bi bi-x-circle"></i> Borzo
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing'])): ?>
                                                                <button class="btn btn-sm btn-success update-status-btn" 
                                                                        data-order-id="<?php echo $order['order_id']; ?>"
                                                                        data-new-status="Ready"
                                                                        title="Mark as Ready">
                                                                    <i class="bi bi-check-circle"></i> Ready
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (in_array($order['status'], ['Ready'])): ?>
                                                                <button class="btn btn-sm btn-info update-status-btn" 
                                                                        data-order-id="<?php echo $order['order_id']; ?>"
                                                                        data-new-status="Completed"
                                                                        title="Mark as Completed">
                                                                    <i class="bi bi-check-all"></i> Completed
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing'])): ?>
                                                                <button class="btn btn-sm btn-danger cancel-order" 
                                                                        data-order-id="<?php echo $order['order_id']; ?>" 
                                                                        style="display: none;"
                                                                        title="Cancel Order">
                                                                    <i class="bi bi-x-lg"></i> Order
                                                                </button>
                                                            <?php endif; ?>
                                                           </td>
                                                       </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

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

    <!-- Order Details Modal (Enhanced) -->
    <div class="modal fade order-modal" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="orderModalLabel">Order Details #<span id="modalOrderId" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Customer Information Card -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header text-white py-2">
                                    <h6 class="mb-0"><i class="bi bi-person-circle me-2"></i>Customer Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><strong>Name:</strong> <span id="modalCustomerName"></span></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="modalCustomerName">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><strong>Phone:</strong> <span id="modalCustomerPhone"></span></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="modalCustomerPhone">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-start" id="modalDeliveryAddress">
                                        <div class="flex-grow-1">
                                            <strong>Address:</strong>
                                            <div id="modalAddressText" class="small mt-1"></div>
                                            <div id="modalAddressComponents" class="small text-muted mt-1">
                                                <div id="modalBuilding"></div>
                                                <div id="modalFloor"></div>
                                                <div id="modalFlatUnit"></div>
                                                <div id="modalLandmark"></div>
                                            </div>
                                            <div id="borzoGeocodedAddressContainer" style="display: none;" class="text-muted small mt-1">
                                                <i class="bi bi-info-circle"></i> 
                                                Borzo normalized: <span id="borzoGeocodedAddress"></span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary copy-btn ms-2" data-target="modalAddressText">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <div id="modalTableNumber" class="mt-2" style="display: none;">
                                        <strong>Table Number:</strong> <span id="modalTableText"></span>
                                    </div>
                                    <div id="modalOrderNotesContainer" class="mt-2" style="display: none;">
                                        <strong>Order Notes:</strong>
                                        <p id="modalOrderNotes" class="small text-muted mt-1"></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary Card -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header text-white py-2">
                                    <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Order Summary</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Order Type:</strong> <span id="modalOrderType" class="badge bg-info"></span></p>
                                    <p><strong>Order Date:</strong> <span id="modalOrderDate"></span></p>
                                    <p><strong>Status:</strong> <span id="modalOrderStatus" class="status-badge"></span></p>
                                    <div id="modalSyncButton" class="mt-2" style="display: none;">
                                        <button class="btn btn-sm btn-outline-primary w-100 sync-borzo-modal" data-order-id="">
                                            <i class="bi bi-arrow-repeat"></i> Sync with Borzo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Borzo Delivery Card -->
                        <div class="col-12" id="modalBorzoInfo" style="display: none;">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white py-2">
                                    <h6 class="mb-0"><i class="bi bi-truck me-2"></i>Borzo Delivery Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Order ID:</strong>
                                            <div><span id="modalBorzoOrderId" class="badge bg-dark"></span></div>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Status:</strong>
                                            <div><span id="modalBorzoStatus" class="borzo-status-badge"></span></div>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Fare:</strong>
                                            <div><span class="borzo-fare"><?php echo $currencySymbol; ?> <span id="modalBorzoFare"></span></span></div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <strong>Courier:</strong>
                                            <span id="modalCourierName"></span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Phone:</strong>
                                            <span id="modalCourierPhone"></span>
                                        </div>
                                    </div>
                                    <div class="row mt-2" id="modalCourierLocationRow" style="display: none;">
                                        <div class="col-12">
                                            <a href="#" id="modalTrackLiveLink" target="_blank" class="btn btn-sm btn-success">
                                                <i class="bi bi-map"></i> Track Live on Map
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header text-white py-2">
                                    <h6 class="mb-0"><i class="bi bi-basket me-2"></i>Order Items</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Item</th>
                                                    <th class="text-end">Price</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody id="modalOrderItems"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Summary -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header text-white py-2">
                                    <h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Financial Summary</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 offset-md-6">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td><strong>Subtotal:</strong></td>
                                                    <td class="text-end"><?php echo $currencySymbol; ?> <span id="modalSubtotal"></span></td>
                                                </tr>
                                                <tr id="modalDiscountRow" style="display: none;">
                                                    <td><strong>Discount:</strong></td>
                                                    <td class="text-end text-danger">-<?php echo $currencySymbol; ?> <span id="modalDiscountAmount"></span> <small>(<span id="modalDiscountType"></span>)</small></td>
                                                </tr>
                                                <!-- Loyalty Points Redeemed Row -->
                                                <tr id="modalLoyaltyRedeemedRow" style="display: none;">
                                                    <td><strong>Loyalty Redeemed:</strong> <span id="modalLoyaltyRedeemedPoints"></span> pts</span></td>
                                                    <td class="text-end text-success">-<?php echo $currencySymbol; ?> <span id="modalLoyaltyRedeemedValue"></span></td>
                                                </tr>
                                                <!-- Loyalty Points Earned Row -->
                                                <tr id="modalLoyaltyEarnedRow" style="display: none;">
                                                    <td><strong>Loyalty Earned:</strong> <span id="modalLoyaltyEarnedPoints"></span> pts</span></td>
                                                    <td class="text-end text-info">+<?php echo $currencySymbol; ?> <span id="modalLoyaltyEarnedValue"></span></span></td>
                                                </tr>
                                                <tr id="modalGstRow">
                                                    <td><strong><?php echo $taxLabel; ?>:</strong></td>
                                                    <td class="text-end"><?php echo $currencySymbol; ?> <span id="modalGstAmount"></span></td>
                                                </tr>
                                                <tr id="modalDeliveryRow">
                                                    <td><strong>Delivery Charge:</strong></td>
                                                    <td class="text-end"><?php echo $currencySymbol; ?> <span id="modalDeliveryCharge"></span></td>
                                                </tr>
                                                <tr id="modalBorzoFareRow" style="display: none;" class="table-info">
                                                    <td><strong>Borzo Fare:</strong></td>
                                                    <td class="text-end"><?php echo $currencySymbol; ?> <span id="modalBorzoFareDetail"></span></td>
                                                </tr>
                                                <tr class="table-active">
                                                    <td><strong>Total Amount:</strong></td>
                                                    <td class="text-end"><strong><?php echo $currencySymbol; ?> <span id="modalTotalAmount"></span></strong></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <div class="d-flex flex-wrap gap-2 w-100 justify-content-between">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Close
                            </button>
                        </div>
                        <div class="btn-group">
                            <form method="POST" action="orders.php" class="d-inline" id="statusUpdateForm">
                                <input type="hidden" name="order_id" id="modalFormOrderId">
                                <div class="input-group">
                                    <select class="form-select form-select-sm" name="new_status" id="modalStatusSelect" style="width: auto;">
                                        <option value="Pending">Pending</option>
                                        <option value="Confirmed">Confirmed</option>
                                        <option value="Ready">Ready</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                        <i class="bi bi-arrow-repeat"></i> Update
                                    </button>
                                </div>
                            </form>
                            <form method="POST" action="orders.php" class="d-inline" id="cancelOrderForm">
                                <input type="hidden" name="order_id" id="modalCancelOrderId">
                                <button type="submit" name="cancel_order" class="btn btn-sm btn-danger" style="display: none;">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KOT Preview Modal -->
    <div class="modal fade" id="kotPreviewModal" tabindex="-1" aria-labelledby="kotPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title" id="kotPreviewModalLabel">KOT Preview - Order #<span id="kotOrderId"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <div id="kotContent" class="kot-container"></div>
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

    <!-- Bill Preview Modal -->
    <div class="modal fade" id="billPreviewModal" tabindex="-1" aria-labelledby="billPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title" id="billPreviewModalLabel">Bill Preview - Order #<span id="billOrderId"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <div id="billContent" class="bill-container"></div>
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
    // Debug mode - set to true to see console logs
    const DEBUG_MODE = true;
    function debugLog(...args) {
        if (DEBUG_MODE) {
            console.log('[DEBUG]', ...args);
        }
    }

    const currencySymbol = '<?php echo $currencySymbol; ?>';
    const userCountry = '<?php echo $user_country; ?>';
    const taxLabel = '<?php echo $taxLabel; ?>';
    const hasBorzoApi = <?php echo $hasBorzoApi ? 'true' : 'false'; ?>;
    
    // Loyalty settings from server
    const loyaltyRedemptionPoints = <?php echo $redemption_points; ?>;
    const loyaltyRedemptionAmount = <?php echo $redemption_amount; ?>;

    // Function to refresh courier location
    function refreshCourierLocation(orderId) {
        debugLog('Refreshing courier location for order:', orderId);
        
        $.ajax({
            url: '/borzo/api/get-order-details.php',
            type: 'GET',
            data: { order_id: orderId },
            success: function(response) {
                debugLog('Refresh response:', response);
                if (response.success) {
                    // Update the UI with new courier info
                    updateCourierInfoInTable(orderId, response);
                    showToast('✅ Courier location updated', 'success');
                }
            },
            error: function(xhr) {
                debugLog('Refresh error:', xhr);
                showToast('❌ Failed to refresh courier location', 'danger');
            }
        });
    }

    // Function to update courier info in table
    function updateCourierInfoInTable(orderId, data) {
        const $row = $(`#order-${orderId}`);
        if ($row.length) {
            const $courierDiv = $row.find('.borzo-courier-info');
            if ($courierDiv.length) {
                // Update existing courier info
                if (data.courier_latitude && data.courier_longitude) {
                    if (!$courierDiv.find('.track-live-btn').length) {
                        // Add track button if not present
                        $courierDiv.append(`
                            <a href="track-order.php?id=${orderId}" target="_blank" class="track-live-btn btn-sm w-100 text-center mt-1">
                                <i class="bi bi-map"></i> Track Live
                            </a>
                        `);
                    }
                }
            }
        }
    }

    function adjustTimeForUAE(date) {
        if (userCountry !== 'UAE') {
            return date instanceof Date ? date : new Date(date);
        }
        const dateObj = date instanceof Date ? new Date(date.getTime()) : new Date(date);
        dateObj.setHours(dateObj.getHours() - 1);
        dateObj.setMinutes(dateObj.getMinutes() - 30);
        return dateObj;
    }

    function formatDisplayDate(dateString) {
        const adjustedDate = adjustTimeForUAE(dateString);
        return adjustedDate.toLocaleDateString('en-IN') + ' ' + adjustedDate.toLocaleTimeString('en-IN', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    function highlightOrderFromNotification() {
        const urlParams = new URLSearchParams(window.location.search);
        const highlightOrderId = urlParams.get('highlight_order');
        
        if (highlightOrderId) {
            const newUrl = window.location.pathname + window.location.search.replace(/highlight_order=[^&]*&?/, '').replace(/&$/, '').replace(/\?$/, '');
            window.history.replaceState({}, document.title, newUrl);
            
            const $orderRow = $(`tr[data-order-id="${highlightOrderId}"]`);
            if ($orderRow.length) {
                $('html, body').animate({ scrollTop: $orderRow.offset().top - 100 }, 1000);
                $orderRow.addClass('table-success');
                let pulseCount = 0;
                const pulseInterval = setInterval(() => {
                    $orderRow.toggleClass('table-warning');
                    pulseCount++;
                    if (pulseCount >= 6) {
                        clearInterval(pulseInterval);
                        $orderRow.removeClass('table-warning table-success');
                    }
                }, 500);
                setTimeout(() => $orderRow.find('.view-order').click(), 1500);
            } else {
                showToast('Order #' + highlightOrderId + ' not found in current view', 'info');
            }
        }
    }

    function updateTimers() {
        $('.timer').each(function() {
            const $timer = $(this);
            const $display = $timer.find('.timer-display');
            const createdAt = $timer.data('created-at');
            const orderId = $timer.data('order-id');
            
            let orderStatus = '';
            const $statusBadge = $timer.closest('tr').find('.status-badge');
            if ($statusBadge.length) orderStatus = $statusBadge.text();
            if (!orderStatus && window.ordersData) {
                const order = window.ordersData.find(o => o.order_id == orderId);
                if (order) orderStatus = order.status;
            }
            
            if (orderStatus === 'Completed' || orderStatus === 'Cancelled') {
                $timer.closest('.clountdown_group').html('');
                return;
            }
            
            const createdTime = new Date(createdAt).getTime();
            const currentTime = new Date().getTime();
            
            if (isNaN(createdTime)) {
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
            if (timeRemaining <= 10 * 60) $timer.addClass('danger');
            else if (timeRemaining <= 15 * 60) $timer.addClass('warning');
        });
    }

    function initializeBillPreviewHandlers() {
        $('.preview-bill').off('click').on('click', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            showBillPreview(orderId);
        });
    }

    function showBillPreview(orderId) {
        const order = window.ordersData?.find(o => o.order_id == orderId);
        if (!order) {
            showToast('Order data not found for bill preview', 'danger');
            return;
        }
        $('#billOrderId').text(orderId);
        $('#billContent').html(generateBillHTML(order));
        new bootstrap.Modal(document.getElementById('billPreviewModal')).show();
    }

    function generateBillHTML(order) {
        const businessName = "<?php echo addslashes($business_name); ?>";
        const businessAddress = "<?php echo addslashes($business_address); ?>";
        
        const orderTypeDisplay = order.order_type === 'delivery' ? 'Home Delivery' : 
                               order.order_type === 'dining' ? `Dine-In (Table ${order.table_number})` : 'Takeaway';
        
        const adjustedDate = adjustTimeForUAE(order.created_at);
        const orderDate = adjustedDate.toLocaleDateString('en-IN');
        const orderTime = adjustedDate.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        const formatCurrency = (amount) => {
            const num = parseFloat(amount || 0);
            return num % 1 === 0 ? num.toString() : num.toFixed(2);
        };
        
        const now = new Date();
        const adjustedNow = userCountry === 'UAE' ? adjustTimeForUAE(now) : now;
        const currentDate = adjustedNow.toLocaleDateString('en-IN');
        const currentTime = adjustedNow.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        
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
        
        if (order.order_type === 'delivery') {
            // Build complete address
            let addressParts = [];
            if (order.building) addressParts.push(order.building);
            if (order.floor) addressParts.push('Floor ' + order.floor);
            if (order.flat_unit) addressParts.push('Unit ' + order.flat_unit);
            if (order.landmark) addressParts.push('Near ' + order.landmark);
            if (order.delivery_address) addressParts.push(order.delivery_address);
            
            let fullAddress = addressParts.length > 0 ? addressParts.join(', ') : (order.delivery_address || '');
            
            billHtml += `
                <div class="bill-row">
                    <div style="flex:3;text-align:left;font-size:10px;">Address: ${escapeHtml(fullAddress)}</div>
                </div>
            `;
        }
        
        if (order.order_notes) {
            billHtml += `
                <div class="bill-row">
                    <div style="flex:3;text-align:left;font-size:10px;">Notes: ${escapeHtml(order.order_notes)}</div>
                </div>
            `;
        }
        
        billHtml += `
                <div class="bill-divider"></div>
                <div class="bill-row" style="font-weight: bold; text-align: center;">ORDER ITEMS</div>
                <div class="bill-double-divider"></div>
                <div class="bill-row" style="font-weight: bold;">
                    <div class="bill-item-name">Item</div>
                    <div class="bill-item-qty">Qty</div>
                    <div class="bill-item-price">Price</div>
                    <div class="bill-item-total">Total</div>
                </div>
                <div class="bill-divider"></div>
        `;
        
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
        
        // Loyalty Points Redeemed
        if (parseFloat(order.loyalty_points_redeemed) > 0) {
            billHtml += `
                <div class="bill-summary-row">
                    <div class="bill-summary-label">Loyalty Redeemed (${order.loyalty_points_redeemed} pts):</div>
                    <div class="bill-summary-value">-${currencySymbol} ${formatCurrency(order.loyalty_points_value)}</div>
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
        
        // Loyalty Points Earned - using dynamic conversion
        if (parseFloat(order.loyalty_points_earned) > 0) {
            const earnedValue = (order.loyalty_points_earned / loyaltyRedemptionPoints * loyaltyRedemptionAmount).toFixed(2);
            billHtml += `
                <div class="bill-summary-row">
                    <div class="bill-summary-label">Loyalty Earned (${order.loyalty_points_earned} pts):</div>
                    <div class="bill-summary-value">+${currencySymbol} ${formatCurrency(earnedValue)}</div>
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
                <div class="bill-footer">
                    <div>Thank you for your order!</div>
                    <div>Visit again</div>
                    <div style="margin-top:3px;">${currentDate} ${currentTime}</div>
                </div>
            </div>
        `;
        
        return billHtml;
    }

    function printBill() {
        const billContent = document.getElementById('billContent');
        const printWindow = window.open('', '_blank', 'width=65mm,height=600,scrollbars=no,toolbar=no,location=no');
        if (!printWindow) {
            showToast('Please allow popups for printing', 'warning');
            return;
        }
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Bill - Order #${$('#billOrderId').text()}</title>
                <style>
                    @page{margin:0;padding:0;size:65mm auto}
                    body{margin:0;padding:5px;font-family:'Arial';font-size:12px;line-height:1.2;width:65mm;background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
                    .bill-container{width:65mm;max-width:65mm;font-family:'Arial';font-size:12px;line-height:1.2;background:#fff;padding:0;margin:0 auto}
                    .bill-header{text-align:center;margin-bottom:5px}
                    .bill-header .business-name{font-weight:700;font-size:14px;margin-bottom:2px}
                    .bill-header .business-address{font-size:10px;margin-bottom:2px}
                    .bill-header .business-phone{font-size:10px;margin-bottom:3px}
                    .bill-divider{border-bottom:1px solid #000;margin:3px 0}
                    .bill-double-divider{border-bottom:2px solid #000;margin:3px 0}
                    .bill-row{display:flex;justify-content:space-between;margin:1px 0}
                    .bill-item-name{flex:2;text-align:left;font-size:11px}
                    .bill-item-qty{flex:1;text-align:center}
                    .bill-item-price{flex:1;text-align:right}
                    .bill-item-total{flex:1;text-align:right}
                    .bill-summary-row{display:flex;justify-content:space-between;margin:1px 0}
                    .bill-summary-label{flex:2;text-align:left}
                    .bill-summary-value{flex:1;text-align:right}
                    .bill-footer{margin-top:5px;font-size:10px;text-align:center}
                    @media print{body{margin:0;padding:5px;width:65mm}}
                </style>
            </head>
            <body>${billContent.innerHTML}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.onload = () => setTimeout(() => printWindow.print(), 500);
        setTimeout(() => { if (!printWindow.closed) printWindow.print(); }, 1000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    $(document).ready(function() {
        setInterval(updateTimers, 1000);
        updateTimers();
        window.ordersData = <?php echo json_encode($orders); ?>;
        initializeAllHandlers();
        highlightOrderFromNotification();
    });

    function bindOrderHandlers() {
        $('.view-order').off('click').on('click', viewOrderHandler);
        $('.cancel-order').off('click').on('click', cancelOrderHandler);
    }

    function handleStatusUpdateButtons() {
        $('.update-status-btn').off('click').on('click', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            const newStatus = $(this).data('new-status');
            updateOrderStatusDirect(orderId, newStatus, $(this));
        });
    }

    function viewOrderHandler() {
        const orderId = $(this).data('order-id');
        fetchOrderDetailsForModal(orderId);
    }

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
                data: { ajax_cancel_order: true, order_id: orderId },
                success: function(response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        if (result.success) {
                            showToast(result.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else throw new Error(result.message || 'Cancellation failed');
                    } catch (e) {
                        showToast(e.message, 'danger');
                        button.html(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    showToast('Error cancelling order. Please try again.', 'danger');
                    button.html(originalText).prop('disabled', false);
                }
            });
        }
    }

    function updateOrderStatusDirect(orderId, newStatus, button) {
        const originalText = button.html();
        button.html('<i class="bi bi-arrow-repeat spin"></i>').prop('disabled', true);
        $.ajax({
            url: 'orders.php',
            type: 'POST',
            data: { ajax_update_status: true, order_id: orderId, new_status: newStatus },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        showToast(result.message || `Order marked as ${newStatus}!`, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else throw new Error(result.message || 'Update failed');
                } catch (e) {
                    showToast(e.message || 'Error updating order status', 'danger');
                    button.html(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showToast('Error updating order status. Please try again.', 'danger');
                button.html(originalText).prop('disabled', false);
            }
        });
    }

    function showToast(message, type) {
        $('.custom-toast').remove();
        const toast = $(`
            <div class="alert alert-${type} alert-dismissible fade show custom-toast" role="alert" style="position:fixed;top:20px;right:20px;z-index:9999;max-width:300px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        $('body').append(toast);
        setTimeout(() => toast.alert('close'), 5000);
    }

    function fetchOrderDetailsForModal(orderId) {
        $('#modalOrderId').text('Loading...');
        $('#modalCustomerName').text('Loading...');
        $('#modalCustomerPhone').text('Loading...');
        
        fetch(`get_restaurant_order_details.php?order_id=${orderId}&t=${Date.now()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.order) updateOrderModal(data.order);
                else throw new Error(data.error || 'Failed to load order details');
            })
            .catch(error => {
                showToast('Error loading order details. Please try again.', 'danger');
                const order = window.ordersData?.find(o => o.order_id == orderId);
                if (order) updateOrderModal(order);
            });
    }

    function updateOrderModal(order) {
        $('#modalOrderId').text(order.order_id || 'N/A');
        $('#modalCustomerName').text(order.customer_name || 'Not specified');
        $('#modalCustomerPhone').text(order.customer_phone || 'Not specified');
        
        if (order.order_type === 'delivery') {
            $('#modalDeliveryAddress').show();
            
            // Build complete address string
            let addressParts = [];
            if (order.building) addressParts.push(order.building);
            if (order.floor) addressParts.push('Floor ' + order.floor);
            if (order.flat_unit) addressParts.push('Unit ' + order.flat_unit);
            if (order.landmark) addressParts.push('Near ' + order.landmark);
            if (order.delivery_address) addressParts.push(order.delivery_address);
            
            let fullAddress = addressParts.length > 0 ? addressParts.join(', ') : (order.delivery_address || 'Not specified');
            $('#modalAddressText').text(fullAddress);
            
            // Show individual components
            $('#modalBuilding').html(order.building ? '<strong>Building:</strong> ' + order.building : '');
            $('#modalFloor').html(order.floor ? '<strong>Floor:</strong> ' + order.floor : '');
            $('#modalFlatUnit').html(order.flat_unit ? '<strong>Flat/Unit:</strong> ' + order.flat_unit : '');
            $('#modalLandmark').html(order.landmark ? '<strong>Landmark:</strong> ' + order.landmark : '');
            
            $('#modalTableNumber').hide();
            
            // Show Borzo geocoded address if available
            if (order.borzo_geocoded_address) {
                $('#borzoGeocodedAddress').text(order.borzo_geocoded_address);
                $('#borzoGeocodedAddressContainer').show();
            } else {
                $('#borzoGeocodedAddressContainer').hide();
            }
        } else if (order.order_type === 'dining') {
            $('#modalDeliveryAddress').hide();
            $('#modalTableNumber').show().find('#modalTableText').text(order.table_number || 'Not specified');
        } else {
            $('#modalDeliveryAddress').hide();
            $('#modalTableNumber').hide();
        }
        
        $('#modalOrderType').text(formatOrderType(order));
        $('#modalOrderDate').text(formatDisplayDate(order.created_at));
        
        const statusBadge = $('#modalOrderStatus');
        statusBadge.text(order.status || 'Unknown').removeClass().addClass('status-badge status-' + (order.status || 'Pending'));
        
        renderOrderItems(order.items || []);
        
        const $notesContainer = $('#modalOrderNotesContainer');
        const $notesText = $('#modalOrderNotes');
        if (order.order_notes) {
            $notesContainer.show();
            $notesText.text(order.order_notes);
        } else $notesContainer.hide();
        
        if (order.borzo_order_id) {
            $('#modalBorzoInfo').show();
            $('#modalBorzoOrderId').text(order.borzo_order_id);
            $('#modalBorzoStatus').text(order.borzo_status || 'Booked').removeClass().addClass('borzo-status-badge borzo-status-' + (order.borzo_status || 'pending'));
            $('#modalBorzoFare').text(parseFloat(order.delivery_fee || order.delivery_charge || 0).toFixed(2));
            $('#modalBorzoFareDetail').text(parseFloat(order.delivery_fee || order.delivery_charge || 0).toFixed(2));
            $('#modalBorzoFareRow').show();
            
            if (order.courier_name) {
                $('#modalCourierName').text(order.courier_name);
                $('#modalCourierPhone').text(order.courier_phone || '');
                if (order.courier_latitude && order.courier_longitude) {
                    $('#modalTrackLiveLink').attr('href', 'track-order.php?id=' + order.order_id);
                    $('#modalCourierLocationRow').show();
                } else {
                    $('#modalCourierLocationRow').hide();
                }
            } else {
                $('#modalCourierName').text('Not assigned yet');
                $('#modalCourierPhone').text('');
                $('#modalCourierLocationRow').hide();
            }
            
            // Show sync button in modal
            $('#modalSyncButton').show().find('.sync-borzo-modal').data('order-id', order.order_id);
        } else {
            $('#modalBorzoInfo').hide();
            $('#modalBorzoFareRow').hide();
            $('#modalSyncButton').hide();
        }
        
        updateFinancials(order);
        
        $('#modalFormOrderId').val(order.order_id);
        $('#modalCancelOrderId').val(order.order_id);
        $('#modalStatusSelect').val(order.status);
        
        const showActions = ['Pending', 'Confirmed', 'Preparing'].includes(order.status);
        $('#statusUpdateForm, #cancelOrderForm').toggle(showActions);
    }

    function renderOrderItems(items) {
        const $container = $('#modalOrderItems').empty();
        if (!items || items.length === 0) {
            $container.append('<tr><td colspan="4" class="text-center">No items found</span></td></tr>');
            return;
        }
        items.forEach(item => {
            const total = (parseFloat(item.price || 0) * parseInt(item.quantity || 0));
            $container.append(`
                <tr>
                    <td>${item.product_name || 'Unnamed Item'}</td>
                    <td class="text-end">${currencySymbol} ${parseFloat(item.price || 0).toFixed(2)}</td>
                    <td class="text-center">${item.quantity || 1}</td>
                    <td class="text-end">${currencySymbol} ${total.toFixed(2)}</td>
                </tr>
            `);
        });
    }

    function updateFinancials(order) {
        $('#modalSubtotal').text(parseFloat(order.subtotal || 0).toFixed(2));
        
        const discountAmount = parseFloat(order.discount_amount || 0);
        $('#modalDiscountRow').toggle(discountAmount > 0);
        if (discountAmount > 0) {
            $('#modalDiscountAmount').text(discountAmount.toFixed(2));
            $('#modalDiscountType').text(order.discount_type || 'Discount');
        }
        
        // Loyalty Points Redeemed
        const redeemedPoints = parseInt(order.loyalty_points_redeemed || 0);
        const redeemedValue = parseFloat(order.loyalty_points_value || 0);
        if (redeemedPoints > 0 && redeemedValue > 0) {
            $('#modalLoyaltyRedeemedRow').show();
            $('#modalLoyaltyRedeemedPoints').text(redeemedPoints);
            $('#modalLoyaltyRedeemedValue').text(redeemedValue.toFixed(2));
        } else {
            $('#modalLoyaltyRedeemedRow').hide();
        }
        
        // Loyalty Points Earned - using dynamic conversion
        const earnedPoints = parseInt(order.loyalty_points_earned || 0);
        if (earnedPoints > 0) {
            const earnedValue = (earnedPoints / loyaltyRedemptionPoints * loyaltyRedemptionAmount).toFixed(2);
            $('#modalLoyaltyEarnedRow').show();
            $('#modalLoyaltyEarnedPoints').text(earnedPoints);
            $('#modalLoyaltyEarnedValue').text(earnedValue);
        } else {
            $('#modalLoyaltyEarnedRow').hide();
        }
        
        const gstAmount = parseFloat(order.gst_amount || 0);
        $('#modalGstRow').toggle(gstAmount > 0);
        $('#modalGstRow td strong').text(taxLabel + ':');
        if (gstAmount > 0) $('#modalGstAmount').text(gstAmount.toFixed(2));
        
        const deliveryCharge = parseFloat(order.delivery_charge || 0);
        $('#modalDeliveryRow').toggle(deliveryCharge > 0);
        if (deliveryCharge > 0) $('#modalDeliveryCharge').text(deliveryCharge.toFixed(2));
        
        $('#modalTotalAmount').text(parseFloat(order.total_amount || 0).toFixed(2));
    }

    $('#cancelOrderForm').submit(function(e) {
        e.preventDefault();
        const orderId = $('#modalCancelOrderId').val();
        $(`.cancel-order[data-order-id="${orderId}"]`).click();
    });

    $('#statusUpdateForm').submit(function(e) {
        e.preventDefault();
        const orderId = $('#modalFormOrderId').val();
        const newStatus = $('#modalStatusSelect').val();
        updateOrderStatusDirect(orderId, newStatus, $(this).find('button[type="submit"]'));
    });

    function formatOrderType(order) {
        if (!order.order_type) return 'Unknown type';
        return order.order_type === 'dining' 
            ? `Dining (Table ${order.table_number || 'N/A'})` 
            : order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1);
    }

    function initializeAllHandlers() {
        bindOrderHandlers();
        handleStatusUpdateButtons();
        initializeBillPreviewHandlers();
        initializeKOTPreviewHandlers();
        initializeBorzoBookingHandler();
        initializeBorzoCancelHandler();
        initializeSyncHandler();
        initializeModalSyncHandler();
    }

    $(document).on('click', '.copy-btn', function() {
        const targetId = $(this).data('target');
        const textToCopy = $(`#${targetId}`).text().trim();
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(() => showCopyFeedback($(this))).catch(() => fallbackCopyText(textToCopy, $(this)));
        } else fallbackCopyText(textToCopy, $(this));
    });

    function fallbackCopyText(text, button) {
        const tempTextArea = document.createElement('textarea');
        tempTextArea.value = text;
        tempTextArea.style.position = 'fixed';
        tempTextArea.style.left = '-999999px';
        tempTextArea.style.top = '-999999px';
        document.body.appendChild(tempTextArea);
        tempTextArea.focus();
        tempTextArea.select();
        try {
            if (document.execCommand('copy')) showCopyFeedback(button);
            else throw new Error('Fallback copy failed');
        } catch (err) {
            alert('Please copy manually: ' + text);
        } finally {
            document.body.removeChild(tempTextArea);
        }
    }

    function showCopyFeedback(button) {
        const originalHtml = button.html();
        button.html('<i class="bi bi-check"></i> Copied!').prop('disabled', true);
        setTimeout(() => button.html(originalHtml).prop('disabled', false), 2000);
    }
    </script>

    <script>
    function initializeKOTPreviewHandlers() {
        $('.preview-kot').off('click').on('click', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            showKOTPreview(orderId);
        });
    }

    function showKOTPreview(orderId) {
        const order = window.ordersData?.find(o => o.order_id == orderId);
        if (!order) {
            showToast('Order data not found for KOT preview', 'danger');
            return;
        }
        $('#kotOrderId').text(orderId);
        $('#kotContent').html(generateKOTHTML(order));
        new bootstrap.Modal(document.getElementById('kotPreviewModal')).show();
    }

    function generateKOTHTML(order) {
        const businessName = "<?php echo addslashes($business_name); ?>";
        
        const orderTypeDisplay = order.order_type === 'delivery' ? 'HOME DELIVERY' : 
                               order.order_type === 'dining' ? `DINE-IN (TABLE ${order.table_number})` : 'TAKEAWAY';
        
        const adjustedDate = adjustTimeForUAE(order.created_at);
        const orderDate = adjustedDate.toLocaleDateString('en-IN');
        const orderTime = adjustedDate.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        const now = new Date();
        const adjustedNow = userCountry === 'UAE' ? adjustTimeForUAE(now) : now;
        const currentTime = adjustedNow.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        let kotHtml = `
            <div class="kot-container">
                <div class="kot-header">
                    <div class="business-name">${escapeHtml(businessName)}</div>
                    <div class="kot-title">KITCHEN ORDER TICKET</div>
                </div>
                <div class="kot-double-divider"></div>
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
                <div class="kot-row" style="font-weight: bold;">
                    <div class="kot-item-name">ITEMS</div>
                    <div class="kot-item-qty">QTY</div>
                </div>
                <div class="kot-divider"></div>
        `;
        
        if (order.items && order.items.length > 0) {
            order.items.forEach(item => {
                kotHtml += `
                    <div class="kot-row">
                        <div class="kot-item-name">${escapeHtml(item.product_name)}</div>
                        <div class="kot-item-qty">${item.quantity}x</div>
                    </div>
                `;
                if (order.order_notes && order.order_notes.toLowerCase().includes(item.product_name.toLowerCase())) {
                    kotHtml += `
                        <div class="kot-row">
                            <div class="kot-item-special">* Special: ${escapeHtml(order.order_notes)}</div>
                        </div>
                    `;
                }
            });
        }
        
        if (order.order_notes && !order.items.some(item => order.order_notes.toLowerCase().includes(item.product_name.toLowerCase()))) {
            kotHtml += `
                <div class="kot-double-divider"></div>
                <div class="kot-row">
                    <div class="kot-item-special" style="text-align:center;font-weight:bold;">SPECIAL INSTRUCTIONS:</div>
                </div>
                <div class="kot-row">
                    <div class="kot-item-special" style="text-align:center;">${escapeHtml(order.order_notes)}</div>
                </div>
            `;
        }
        
        kotHtml += `
                <div class="kot-double-divider"></div>
                <div class="kot-footer">
                    <div>*** KITCHEN COPY ***</div>
                    <div>Order Time: ${orderTime}</div>
                    <div style="margin-top:3px;">${currentTime}</div>
                </div>
            </div>
        `;
        
        return kotHtml;
    }

    function printKOT() {
        const kotContent = document.getElementById('kotContent');
        const printWindow = window.open('', '_blank', 'width=65mm,height=600,scrollbars=no,toolbar=no,location=no');
        if (!printWindow) {
            showToast('Please allow popups for printing', 'warning');
            return;
        }
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>KOT - Order #${$('#kotOrderId').text()}</title>
                <style>
                    @page{margin:0;padding:0;size:65mm auto}
                    body{margin:0;padding:5px;font-family:'Arial';font-size:12px;line-height:1.2;width:65mm;background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact;color:#000!important}
                    .kot-container{width:65mm;max-width:65mm;font-family:'Arial';font-size:12px;line-height:1.2;background:#fff;padding:0;margin:0 auto;color:#000!important}
                    .kot-header{text-align:center;margin-bottom:5px;color:#000!important}
                    .kot-header .business-name{font-weight:700;font-size:14px;margin-bottom:2px;color:#000!important}
                    .kot-header .kot-title{font-weight:700;font-size:16px;margin-bottom:3px;color:#000!important;text-transform:uppercase}
                    .kot-divider{border-bottom:1px solid #000;margin:3px 0}
                    .kot-double-divider{border-bottom:2px solid #000;margin:3px 0}
                    .kot-row{display:flex;justify-content:space-between;margin:1px 0;color:#000!important}
                    .kot-item-name{flex:2;text-align:left;font-size:11px;color:#000!important}
                    .kot-item-qty{flex:1;text-align:center;color:#000!important;font-weight:700}
                    .kot-item-special{flex:3;text-align:left;font-size:10px;font-style:italic;color:#000!important;margin-top:-2px}
                    .kot-footer{margin-top:5px;font-size:10px;text-align:center;color:#000!important}
                    @media print{body{margin:0;padding:5px;width:65mm;color:#000!important}*{color:#000!important}}
                </style>
            </head>
            <body>${kotContent.innerHTML}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.onload = () => setTimeout(() => printWindow.print(), 500);
        setTimeout(() => { if (!printWindow.closed) printWindow.print(); }, 1000);
    }

    // SYNC HANDLER - Sync with Borzo
    function initializeSyncHandler() {
        $('.sync-borzo').off('click').on('click', function(e) {
            e.preventDefault();
            const button = $(this);
            const orderId = button.data('order-id');
            const originalHtml = button.html();
            
            button.html('<i class="bi bi-arrow-repeat spin"></i>').prop('disabled', true);
            
            $.ajax({
                url: '/borzo/api/sync-order.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ order_id: orderId }),
                success: function(response) {
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Failed to parse response:', response);
                        }
                    }
                    
                    if (response && response.success) {
                        showToast('✅ Order synced successfully', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        const errorMsg = response && response.error ? response.error : 'Sync failed';
                        showToast('❌ ' + errorMsg, 'danger');
                        button.html(originalHtml).prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    showToast('❌ Error syncing order', 'danger');
                    button.html(originalHtml).prop('disabled', false);
                }
            });
        });
    }

    function initializeModalSyncHandler() {
        $(document).on('click', '.sync-borzo-modal', function(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            const button = $(this);
            const originalHtml = button.html();
            
            button.html('<i class="bi bi-arrow-repeat spin"></i> Syncing...').prop('disabled', true);
            
            $.ajax({
                url: '/borzo/api/sync-order.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ order_id: orderId }),
                success: function(response) {
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {}
                    }
                    
                    if (response && response.success) {
                        showToast('✅ Order synced successfully', 'success');
                        // Refresh modal data
                        fetchOrderDetailsForModal(orderId);
                    } else {
                        const errorMsg = response && response.error ? response.error : 'Sync failed';
                        showToast('❌ ' + errorMsg, 'danger');
                        button.html(originalHtml).prop('disabled', false);
                    }
                },
                error: function() {
                    showToast('❌ Error syncing order', 'danger');
                    button.html(originalHtml).prop('disabled', false);
                }
            });
        });
    }

    // Helper function for escaping HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // BORZO BOOKING HANDLER - Enhanced with button replacement
    function initializeBorzoBookingHandler() {
        $(document).on('click', '.book-borzo-delivery', function(e) {
            e.preventDefault();
            const button = $(this);
            const orderId = button.data('order-id');
            const originalText = button.html();

            if (!hasBorzoApi) {
                showToast('❌ Please configure your Borzo API key first', 'warning');
                setTimeout(() => window.location.href = 'borzo_api.php', 2000);
                return;
            }

            if (!confirm('Book Borzo delivery for this order?')) return;

            button.html('<i class="bi bi-arrow-repeat spin"></i> Booking...').prop('disabled', true);

            $.ajax({
                url: '/borzo/api/create-order.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ order_id: orderId }),
                success: function(response) {
                    debugLog('Booking response:', response);
                    
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Failed to parse response:', response);
                            showToast('Server returned invalid response', 'danger');
                            button.html(originalText).prop('disabled', false);
                            return;
                        }
                    }
                    
                    if (response && response.success) {
                        // Replace button with success badge
                        const successBadge = $(`<span class="badge bg-success p-2">✅ Booked: ${response.borzo_order_id}</span>`);
                        button.replaceWith(successBadge);
                        showToast('✅ Delivery booked! Borzo ID: ' + response.borzo_order_id, 'success');
                        // Reload after 2 seconds to show updated data
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        const errorMsg = response && response.errors ? 
                            response.errors.join(', ') : 
                            (response && response.error ? response.error : 'Booking failed');
                        showToast('❌ Error: ' + errorMsg, 'danger');
                        button.html(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
                    let errorMsg = 'Failed to book delivery. ';
                    try {
                        if (xhr.responseText) {
                            const res = JSON.parse(xhr.responseText);
                            if (res.errors) {
                                errorMsg += res.errors.join(', ');
                            } else if (res.error) {
                                errorMsg += res.error;
                            } else {
                                errorMsg += 'Unknown error (see console)';
                            }
                        } else {
                            errorMsg += 'Server returned status ' + xhr.status;
                        }
                    } catch (e) {
                        errorMsg += xhr.responseText || 'No response from server';
                    }
                    
                    showToast('❌ ' + errorMsg, 'danger');
                    button.html(originalText).prop('disabled', false);
                }
            });
        });
    }

    // Cancel Borzo Delivery Handler
    function initializeBorzoCancelHandler() {
        debugLog('Initializing Borzo cancel handler');
        
        $(document).on('click', '.cancel-borzo-delivery', function(e) {
            e.preventDefault();
            debugLog('Cancel button clicked');
            
            const button = $(this);
            const orderId = button.data('order-id');
            const borzoId = button.data('borzo-id');
            const originalText = button.html();

            if (!hasBorzoApi) {
                showToast('❌ Please configure your Borzo API key first', 'warning');
                return;
            }

            debugLog('Cancel attempt - Order ID:', orderId, 'Borzo ID:', borzoId);

            if (!confirm('Are you sure you want to cancel this Borzo delivery? This action cannot be undone.')) {
                return;
            }

            button.html('<i class="bi bi-arrow-repeat spin"></i> Cancelling...').prop('disabled', true);

            $.ajax({
                url: '/borzo/api/cancel-borzo-order.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ order_id: orderId }),
                success: function(response) {
                    debugLog('Cancel response:', response);
                    
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Failed to parse response:', response);
                            showToast('Server returned invalid response', 'danger');
                            button.html(originalText).prop('disabled', false);
                            return;
                        }
                    }
                    
                    if (response.success) {
                        showToast('✅ Borzo delivery cancelled successfully!', 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        const errorMsg = response.errors ? response.errors.join(', ') : 'Cancellation failed';
                        showToast('❌ Error: ' + errorMsg, 'danger');
                        button.html(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
                    let errorMsg = 'Failed to cancel delivery. ';
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.errors) {
                            errorMsg += res.errors.join(', ');
                        } else if (res.error) {
                            errorMsg += res.error;
                        } else {
                            errorMsg += 'Server returned: ' + xhr.status;
                        }
                    } catch (e) {
                        errorMsg += xhr.responseText || 'No response from server';
                    }
                    
                    showToast('❌ ' + errorMsg, 'danger');
                    button.html(originalText).prop('disabled', false);
                }
            });
        });
    }
    </script>
</body>
</html>
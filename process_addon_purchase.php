<?php
session_start();
require 'config.php';
require 'db_connection.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'payment_errors.log');

error_log("\n\n=== New Addon Purchase Request ===");
error_log("Time: " . date('Y-m-d H:i:s'));

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    die(json_encode(['status' => 'error', 'message' => 'Not logged in']));
}

$user_id = $_SESSION['user_id'];
error_log("User ID: $user_id");

// Validate all required parameters
$required = ['addon_id', 'razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        error_log("Missing required field: $field");
        die(json_encode(['status' => 'error', 'message' => "Missing $field"]));
    }
}

$addon_id = (int)$_POST['addon_id'];
$payment_id = $_POST['razorpay_payment_id'];
$order_id = $_POST['razorpay_order_id'];
$signature = $_POST['razorpay_signature'];

error_log("Addon ID: $addon_id, Order ID: $order_id");

// Verify payment signature
$generated_signature = hash_hmac('sha256', $order_id . "|" . $payment_id, RAZORPAY_KEY_SECRET);

if ($generated_signature !== $signature) {
    error_log("Signature verification failed");
    error_log("Expected: $generated_signature");
    error_log("Received: $signature");
    die(json_encode(['status' => 'error', 'message' => 'Invalid payment signature']));
}

error_log("Signature verified successfully");

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// Get complete addon details
$current_date = date('Y-m-d');
$stmt = $conn->prepare("SELECT id, price as original_price, special_price, valid_until, name
                        FROM addons 
                        WHERE id = ? AND status = 1");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

if (!$stmt->bind_param("i", $addon_id)) {
    error_log("Bind failed: " . $stmt->error);
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

$result = $stmt->get_result();
$addon = $result->fetch_assoc();
$stmt->close();

if (!$addon) {
    error_log("Addon not found or inactive: $addon_id");
    die(json_encode(['status' => 'error', 'message' => 'Invalid addon']));
}

error_log("Addon details: " . print_r($addon, true));

// Determine final price to charge
$final_price = $addon['original_price'];
$price_applied = 'original';
$special_price = null;

if ($addon['special_price'] !== null && 
    (empty($addon['valid_until']) || strtotime($addon['valid_until']) >= time())) {
    $final_price = $addon['special_price'];
    $price_applied = 'special';
    $special_price = $addon['special_price'];
}

error_log("Price calculation - Original: {$addon['original_price']}, Special: $special_price, Final: $final_price, Applied: $price_applied");

// Record the purchase with all price information
$stmt = $conn->prepare("INSERT INTO user_addons 
                        (user_id, addon_id, payment_id, order_id, 
                         amount, original_price, special_price, price_applied, purchase_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

if (!$stmt->bind_param("iissddss", 
    $user_id, $addon_id, $payment_id, $order_id,
    $final_price, $addon['original_price'], $special_price, $price_applied)) {
    error_log("Bind failed: " . $stmt->error);
    die(json_encode(['status' => 'error', 'message' => 'Database error']));
}

$success = $stmt->execute();
$stmt->close();

if ($success) {
    error_log("Addon purchase recorded successfully. Amount: $final_price");
    echo json_encode(['status' => 'success', 'message' => 'Addon purchased successfully']);
} else {
    error_log("Failed to record purchase: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Failed to record purchase']);
}

$conn->close();
error_log("=== End of Request ===\n");
?>
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/update_customer_error.log');

require_once __DIR__ . '/config/db_connection.php';
require_once __DIR__ . '/includes/google_login_authentication.php';

header('Content-Type: application/json');

// Get raw input
$rawInput = file_get_contents('php://input');
error_log("Update customer - Raw input: " . $rawInput);

$data = json_decode($rawInput, true);

if (!$data) {
    error_log("Update customer - Invalid JSON");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$required = ['user_id', 'customer_id', 'phone', 'address'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        error_log("Update customer - Missing field: $field");
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$user_id = intval($data['user_id']);
$customer_id = intval($data['customer_id']);
$phone = trim($data['phone']);
$address_data = $data['address'];

error_log("Update customer - user_id=$user_id, customer_id=$customer_id, phone=$phone, address=" . json_encode($address_data));

// Call update function
$result = updateCustomerDetails($conn, $user_id, $customer_id, $phone, $address_data);

if ($result) {
    error_log("Update customer - SUCCESS");
    echo json_encode(['success' => true]);
} else {
    error_log("Update customer - FAILED");
    echo json_encode(['success' => false, 'message' => 'Database update failed']);
}
?>
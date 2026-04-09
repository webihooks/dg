<?php
session_start();
require_once 'db_connection.php';
require_once 'vegetable_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vegetable_seller') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
ensureVegetableSellerTables($user_id, $conn);
$orders_table = "vegetable_orders_{$user_id}";

$order_id = (int)$_POST['order_id'];
$new_status = $_POST['status'];
$allowed = ['pending','confirmed','preparing','ready','completed','cancelled'];

if (!in_array($new_status, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Invalid status']);
    exit();
}

$stmt = $conn->prepare("UPDATE `$orders_table` SET status = ? WHERE order_id = ?");
$stmt->bind_param("si", $new_status, $order_id);
if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>'Update failed']);
}
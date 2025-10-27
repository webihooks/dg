<?php
// Check for new orders for the logged-in user
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['newOrders' => []]);
    exit;
}

// Your database logic to check for new orders
$user_id = $_SESSION['user_id'];
// Query orders table for new orders since last check
// Return: { "newOrders": [...], "orderCount": 5 }
?>
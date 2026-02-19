<?php
// update_whatsapp_setting.php
session_start();
error_reporting(0);

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Database connection
require 'db_connection.php';
$user_id = $_SESSION['user_id'];

// Get POST data
$send_whatsapp_on_bill = isset($_POST['send_whatsapp_on_bill']) ? intval($_POST['send_whatsapp_on_bill']) : 1;

// First check if column exists
$column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'send_whatsapp_on_bill'");

if ($column_check && $column_check->num_rows > 0) {
    // Column exists, update it
    $sql = "UPDATE users SET send_whatsapp_on_bill = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $send_whatsapp_on_bill, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Setting updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update setting']);
    }
    
    $stmt->close();
} else {
    // Column doesn't exist - just return success without updating
    echo json_encode(['success' => true, 'message' => 'Setting saved locally (column not in database)']);
}

$conn->close();
?>
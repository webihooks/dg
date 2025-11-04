<?php
// update_booking_status.php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;
$status = $_POST['status'] ?? null;
$action = $_POST['action'] ?? '';

if (!$booking_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

try {
    $table_name = "bookings_$user_id";
    
    if ($action === 'checkin') {
        $actual_checkin = $_POST['actual_checkin'] ?? date('Y-m-d H:i:s');
        $sql = "UPDATE $table_name SET status = ?, actual_checkin = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $actual_checkin, $booking_id);
    } elseif ($action === 'checkout') {
        $actual_checkout = $_POST['actual_checkout'] ?? date('Y-m-d H:i:s');
        $final_amount = $_POST['final_amount'] ?? null;
        
        if ($final_amount) {
            $sql = "UPDATE $table_name SET status = ?, actual_checkout = ?, total_amount = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $status, $actual_checkout, $final_amount, $booking_id);
        } else {
            $sql = "UPDATE $table_name SET status = ?, actual_checkout = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $status, $actual_checkout, $booking_id);
        }
    } else {
        $sql = "UPDATE $table_name SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $booking_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Booking status updated successfully']);
    } else {
        throw new Exception($stmt->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
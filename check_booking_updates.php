<?php
// check_booking_updates.php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['updated' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];
$last_check = $_GET['last_check'] ?? time();

// Check if any bookings were updated since last check
$table_name = "bookings_$user_id";
$sql = "SELECT COUNT(*) as update_count FROM $table_name 
        WHERE updated_at > FROM_UNIXTIME(?) AND check_in_date >= CURDATE()";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $last_check);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

echo json_encode([
    'updated' => $result['update_count'] > 0,
    'update_count' => $result['update_count']
]);
?>
<?php
// update_amenity_status.php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$amenity_id = $_POST['amenity_id'] ?? null;
$is_active = $_POST['is_active'] ?? 0;

if (!$amenity_id) {
    echo json_encode(['success' => false, 'message' => 'Amenity ID required']);
    exit();
}

// Check if table exists
$table_name = "room_amenities_$user_id";
$check_table = $conn->query("SHOW TABLES LIKE '$table_name'");
if ($check_table->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Amenities table not found']);
    exit();
}

// Update status
$update_sql = "UPDATE $table_name SET is_active = ? WHERE id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("ii", $is_active, $amenity_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
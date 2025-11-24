<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$photo_id = intval($_POST['photo_id']);
$room_type_id = intval($_POST['room_type_id']);

// First, set all photos for this room type as not primary
$reset_sql = "UPDATE room_photos_$user_id SET is_primary = 0 WHERE room_type_id = ?";
$reset_stmt = $conn->prepare($reset_sql);
$reset_stmt->bind_param("i", $room_type_id);
$reset_stmt->execute();
$reset_stmt->close();

// Then set the selected photo as primary
$set_sql = "UPDATE room_photos_$user_id SET is_primary = 1 WHERE id = ?";
$set_stmt = $conn->prepare($set_sql);
$set_stmt->bind_param("i", $photo_id);

if ($set_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$set_stmt->close();
$conn->close();
?>
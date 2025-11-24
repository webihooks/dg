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

// Get photo path
$photo_sql = "SELECT photo_path FROM room_photos_$user_id WHERE id = ?";
$photo_stmt = $conn->prepare($photo_sql);
$photo_stmt->bind_param("i", $photo_id);
$photo_stmt->execute();
$photo_result = $photo_stmt->get_result();

if ($photo_row = $photo_result->fetch_assoc()) {
    $photo_path = $photo_row['photo_path'];
    
    // Delete from database
    $delete_sql = "DELETE FROM room_photos_$user_id WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $photo_id);
    
    if ($delete_stmt->execute()) {
        // Delete file from server
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }
        
        // If deleted photo was primary, set a new primary
        $check_primary_sql = "SELECT id FROM room_photos_$user_id WHERE room_type_id = ? AND is_primary = 1";
        $check_stmt = $conn->prepare($check_primary_sql);
        $check_stmt->bind_param("i", $room_type_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            // Set first photo as primary
            $set_primary_sql = "UPDATE room_photos_$user_id SET is_primary = 1 WHERE room_type_id = ? ORDER BY id LIMIT 1";
            $set_stmt = $conn->prepare($set_primary_sql);
            $set_stmt->bind_param("i", $room_type_id);
            $set_stmt->execute();
            $set_stmt->close();
        }
        $check_stmt->close();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $delete_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Photo not found']);
}

$photo_stmt->close();
$conn->close();
?>
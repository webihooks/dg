<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$room_type_id = intval($_GET['room_type_id']);

$sql = "SELECT * FROM room_photos_$user_id WHERE room_type_id = ? ORDER BY is_primary DESC, id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $room_type_id);
$stmt->execute();
$result = $stmt->get_result();

while ($photo = $result->fetch_assoc()) {
    echo '<div class="photo-thumbnail">';
    echo '<img src="' . $photo['photo_path'] . '" alt="Room photo">';
    if ($photo['is_primary']) {
        echo '<span class="primary-badge">Primary</span>';
    }
    echo '<div class="photo-actions">';
    if (!$photo['is_primary']) {
        echo '<button class="btn btn-sm btn-success set-primary-btn" data-photo-id="' . $photo['id'] . '" title="Set as Primary">';
        echo '<i class="fas fa-star"></i>';
        echo '</button>';
    }
    echo '<button class="btn btn-sm btn-danger delete-photo-btn" data-photo-id="' . $photo['id'] . '" title="Delete Photo">';
    echo '<i class="fas fa-trash"></i>';
    echo '</button>';
    echo '</div>';
    echo '</div>';
}

$stmt->close();
$conn->close();
?>
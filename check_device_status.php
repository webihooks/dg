<?php
// check_device_status.php - Check if device is properly activated
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];
$playerId = $_SESSION['current_player_id'] ?? null;

if (!$playerId) {
    echo json_encode(['success' => false, 'message' => 'No device registered']);
    exit();
}

$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT is_active, device_type, platform, updated_at FROM user_devices WHERE user_id = ? AND player_id = ?");
    $stmt->execute([$userId, $playerId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($device) {
        echo json_encode([
            'success' => true,
            'device' => $device,
            'is_active' => $device['is_active'] == 1,
            'last_updated' => $device['updated_at']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Device not found']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
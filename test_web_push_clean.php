<?php
// Turn off all error reporting to prevent output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Start session
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    require_once 'config/db_connection.php';
    require_once 'web_push_notification.php';
    
    ob_end_clean();
    
    error_log("=== PUSH WITH RING TEST STARTED ===");
    error_log("Push with ring test for user: $userId");

    // Send test notification with ring sound
    $result = WebPushNotification::sendNewOrderNotification(
        $userId,
        'RING-TEST-' . time(),
        'Test Customer',
        '299.00'
    );
    
    if ($result) {
        error_log("=== PUSH WITH RING TEST SUCCESS ===");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Push notification with ring sound sent successfully! Check your device.'
        ]);
    } else {
        error_log("=== PUSH WITH RING TEST FAILED ===");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Push notification failed. Check server logs for details.'
        ]);
    }
    
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    
    error_log("=== PUSH WITH RING TEST ERROR: " . $e->getMessage() . " ===");
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit();
?>
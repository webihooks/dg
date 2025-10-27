<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Start session first
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Include database connection FIRST
    require_once 'config/db_connection.php';
    
    // Then include web push notification
    require_once 'web_push_notification.php';
    
    error_log("=== WEB PUSH TEST STARTED ===");
    error_log("Web push test for user: $userId");

    // Send test notification using web push
    $result = WebPushNotification::sendNewOrderNotification(
        $userId,
        'WEBPUSH-TEST-' . time(),
        'Web Push Test Customer',
        '399.00'
    );
    
    if ($result) {
        error_log("=== WEB PUSH TEST SUCCESS ===");
        echo json_encode([
            'success' => true, 
            'message' => 'Web push notification sent successfully! Check your device for notification with ring sound.'
        ]);
    } else {
        error_log("=== WEB PUSH TEST FAILED ===");
        echo json_encode([
            'success' => false, 
            'message' => 'Web push test failed. Check server logs for details.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("=== WEB PUSH TEST ERROR: " . $e->getMessage() . " ===");
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
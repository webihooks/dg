<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Check if required files exist
    if (!file_exists('fcm_notification.php')) {
        throw new Exception('fcm_notification.php file not found');
    }
    
    if (!file_exists('config/db_connection.php')) {
        throw new Exception('Database configuration file not found');
    }
    
    // Include database connection FIRST
    require_once 'config/db_connection.php';
    
    // Then include FCM notification
    require_once 'fcm_notification.php';
    
    error_log("=== TEST PUSH STARTED ===");
    error_log("Test push for user: $userId");

    // Send test notification with ring sound
    $result = FCMNotification::sendNewOrderNotification(
        $userId,
        'TEST-' . time(),
        'Test Customer',
        '299.00'
    );
    
    if ($result) {
        error_log("=== TEST PUSH SUCCESS ===");
        echo json_encode([
            'success' => true, 
            'message' => 'Test push notification sent successfully with ring sound'
        ]);
    } else {
        error_log("=== TEST PUSH FAILED ===");
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send test notification. Check server logs for details.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("=== TEST PUSH ERROR: " . $e->getMessage() . " ===");
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
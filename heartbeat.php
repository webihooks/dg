<?php
// heartbeat.php - Enhanced heartbeat for session maintenance
session_set_cookie_params([
    'lifetime' => 31536000,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'None'
]);

ini_set('session.gc_maxlifetime', 31536000);
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();

$response = [
    'success' => false,
    'timestamp' => time(),
    'heartbeat_id' => uniqid('hb_', true),
    'session_maintained' => false,
    'android_app' => $sessionManager->isAndroidApp(),
    'user_id' => $_SESSION['user_id'] ?? null
];

try {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        
        // Update session activity
        $_SESSION['last_activity'] = time();
        $_SESSION['heartbeat_count'] = ($_SESSION['heartbeat_count'] ?? 0) + 1;
        $_SESSION['last_heartbeat'] = time();
        
        // For Android apps, use aggressive session maintenance
        if ($sessionManager->isAndroidApp()) {
            $_SESSION['android_heartbeats'] = ($_SESSION['android_heartbeats'] ?? 0) + 1;
            $_SESSION['android_last_heartbeat'] = time();
            
            // Extend session cookie for Android
            setcookie(session_name(), session_id(), [
                'expires' => time() + 31536000,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'None'
            ]);
            
            // Log Android heartbeat (less frequent to avoid spam)
            if ($_SESSION['android_heartbeats'] % 10 === 0) {
                error_log("📱 Android Heartbeat #{$_SESSION['android_heartbeats']} - User: $userId");
            }
        } else {
            // Web heartbeat logging (less frequent)
            if ($_SESSION['heartbeat_count'] % 20 === 0) {
                error_log("🌐 Web Heartbeat #{$_SESSION['heartbeat_count']} - User: $userId");
            }
        }
        
        $response['success'] = true;
        $response['session_maintained'] = true;
        $response['heartbeat_count'] = $_SESSION['heartbeat_count'];
        $response['session_duration'] = time() - ($_SESSION['login_time'] ?? time());
        
    } else {
        $response['error'] = 'No active session';
        error_log("💔 Heartbeat failed - No active session");
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("🚨 Heartbeat Error: " . $e->getMessage());
}

echo json_encode($response);
?>
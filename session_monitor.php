<?php
// session_monitor.php - Monitor and repair sessions
session_start();

header('Content-Type: application/json');

$response = [
    'session_active' => false,
    'android_app' => false,
    'session_age' => 0,
    'issues' => []
];

try {
    if (isset($_SESSION['user_id'])) {
        $response['session_active'] = true;
        $response['user_id'] = $_SESSION['user_id'];
        $response['session_age'] = time() - ($_SESSION['login_time'] ?? time());
        $response['android_app'] = (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false);
        
        // Force session extension
        $_SESSION['last_activity'] = time();
        $_SESSION['session_expires'] = time() + 31536000;
        
        // Force cookie update
        setcookie(session_name(), session_id(), [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
        
        error_log("✅ Session Monitor - User {$_SESSION['user_id']} session maintained");
        
    } else {
        $response['issues'][] = "No active session found";
    }
    
} catch (Exception $e) {
    $response['issues'][] = "Session monitoring error: " . $e->getMessage();
}

echo json_encode($response);
?>
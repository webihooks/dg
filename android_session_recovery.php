<?php
// android_session_recovery.php - Aggressive Android session recovery
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

$response = [
    'success' => false,
    'recovered' => false,
    'is_android' => false,
    'session_id' => session_id(),
    'timestamp' => time()
];

try {
    $isAndroidApp = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false;
    $response['is_android'] = $isAndroidApp;
    
    if ($isAndroidApp) {
        $_SESSION['is_android_app'] = true;
        $_SESSION['android_last_activity'] = time();
        $_SESSION['session_recovery_attempts'] = ($_SESSION['session_recovery_attempts'] ?? 0) + 1;
        
        // Force session persistence
        session_write_close();
        session_start();
        
        // Update all timestamps
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
        
        $response['success'] = true;
        $response['recovered'] = true;
        $response['recovery_attempts'] = $_SESSION['session_recovery_attempts'];
        
        error_log("🔧 Android Session Recovery - Session: " . session_id() . ", Attempts: " . $_SESSION['session_recovery_attempts']);
        
    } else {
        $response['message'] = 'Not an Android app';
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("🚨 Android Session Recovery Error: " . $e->getMessage());
}

echo json_encode($response);
?>
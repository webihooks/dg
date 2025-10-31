<?php
// android_session_persistence.php - Dedicated WebToNative session handler
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
    'session_restored' => false,
    'is_webtonative' => $sessionManager->isWebToNative(),
    'timestamp' => time()
];

try {
    if (isset($_SESSION['user_id'])) {
        // Force WebToNative session persistence
        $sessionManager->handleWebToNativeSessionPersistence();
        
        $response['success'] = true;
        $response['user_id'] = $_SESSION['user_id'];
        $response['session_id'] = session_id();
        $response['session_age'] = time() - ($_SESSION['login_time'] ?? time());
        
        error_log("🔧 WebToNative Session Persistence - User: {$_SESSION['user_id']}, Session: " . session_id());
        
    } else {
        // Attempt session restoration for WebToNative
        if ($sessionManager->restoreWebToNativeSession()) {
            $response['session_restored'] = true;
            $response['success'] = true;
            $response['user_id'] = $_SESSION['user_id'];
        } else {
            $response['session_restored'] = false;
        }
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("🚨 WebToNative Session Persistence Error: " . $e->getMessage());
}

echo json_encode($response);
?>
<?php
// session-keepalive.php - Enhanced with WebToNative support
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
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

// WebToNative specific headers
$isWebToNative = isset($_SERVER['HTTP_X_WEBTONATIVE']) || 
                 strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false ||
                 isset($_GET['webtonative']);

if (isset($_SESSION['user_id'])) {
    // Update session activity
    $_SESSION['last_activity'] = time();
    $_SESSION['keep_alive_count'] = ($_SESSION['keep_alive_count'] ?? 0) + 1;
    
    // WebToNative specific session maintenance
    if ($isWebToNative) {
        $_SESSION['webtonative_last_ping'] = time();
        $_SESSION['webtonative_keep_alive'] = true;
        $_SESSION['is_android_app'] = true;
        
        // Force session cookie update for WebToNative
        setcookie(session_name(), session_id(), [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
        
        error_log("🔧 WebToNative Keep-alive: User {$_SESSION['user_id']}, Session: " . session_id());
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Session kept alive',
        'user_id' => $_SESSION['user_id'],
        'last_activity' => $_SESSION['last_activity'],
        'session_id' => session_id(),
        'is_webtonative' => $isWebToNative,
        'keep_alive_count' => $_SESSION['keep_alive_count']
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No active session',
        'is_webtonative' => $isWebToNative
    ]);
}
exit();
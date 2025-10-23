<?php
// session-keepalive.php
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

if (isset($_SESSION['user_id'])) {
    // Update session activity
    $_SESSION['last_activity'] = time();
    $_SESSION['keep_alive_count'] = ($_SESSION['keep_alive_count'] ?? 0) + 1;
    
    // For Android apps, maintain specific session data
    if (isset($_SESSION['is_android_app'])) {
        $_SESSION['android_last_ping'] = time();
        $_SESSION['android_keep_alive'] = true;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Session kept alive',
        'user_id' => $_SESSION['user_id'],
        'last_activity' => $_SESSION['last_activity'],
        'session_id' => session_id()
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No active session'
    ]);
}
exit();
<?php
// session-keepalive.php - Clean version
session_set_cookie_params([
    'lifetime' => 31536000,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'None'
]);

ini_set('session.gc_maxlifetime', 31536000);
ini_set('session.cookie_lifetime', 31536000);
session_start();

// Update last activity time
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    
    // Extend session cookie
    setcookie(session_name(), session_id(), time() + 31536000, '/', $_SERVER['HTTP_HOST'], isset($_SERVER['HTTPS']), true);
    
    echo json_encode(['status' => 'success', 'message' => 'Session kept alive']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No active session']);
}
?>
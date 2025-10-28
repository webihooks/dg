<?php
// session_health_check.php - Simple session validation endpoint
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
    'timestamp' => time(),
    'session_active' => false,
    'user_id' => null,
    'is_android_app' => $sessionManager->isAndroidApp(),
    'session_id' => session_id(),
    'session_age' => null,
    'issues' => []
];

try {
    if (isset($_SESSION['user_id'])) {
        $response['session_active'] = true;
        $response['user_id'] = $_SESSION['user_id'];
        $response['session_age'] = time() - ($_SESSION['login_time'] ?? time());
        
        // Validate session data integrity
        $required_session_fields = ['user_id', 'login_time', 'last_activity'];
        foreach ($required_session_fields as $field) {
            if (!isset($_SESSION[$field])) {
                $response['issues'][] = "Missing session field: $field";
            }
        }
        
        // Check session expiration
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
            $response['issues'][] = "Session appears stale (last activity: " . (time() - $_SESSION['last_activity']) . " seconds ago)";
        }
        
        // Update activity timestamp
        $_SESSION['last_activity'] = time();
        $_SESSION['health_checks'] = ($_SESSION['health_checks'] ?? 0) + 1;
        
        // For Android apps, maintain session aggressively
        if ($sessionManager->isAndroidApp()) {
            $sessionManager->maintainAndroidSession($_SESSION['user_id']);
            $response['android_session_maintained'] = true;
        }
        
        error_log("✅ Session Health Check - User: {$_SESSION['user_id']}, Android: " . ($sessionManager->isAndroidApp() ? 'Yes' : 'No'));
        
    } else {
        $response['issues'][] = "No active user session";
        error_log("❌ Session Health Check - No active session");
    }
    
} catch (Exception $e) {
    $response['issues'][] = "Session validation error: " . $e->getMessage();
    error_log("🚨 Session Health Check Error: " . $e->getMessage());
}

echo json_encode($response);
?>
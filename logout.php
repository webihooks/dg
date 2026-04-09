<?php
// logout.php - Enhanced with proper error handling and redirects
error_reporting(0); // Turn off error reporting to prevent output


// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
} else {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$playerId = $_SESSION['current_player_id'] ?? null;

// Initialize variables
$logoutSuccess = false;

// Database connection - ONLY clear remember token, DO NOT deactivate device
if ($userId) {
    $host = 'localhost';
    $dbname = 'doctorie_webihooks_card';
    $username = 'doctorie_webihooks';
    $password = 'S@g@r4834';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 1. Clear remember token from database ONLY
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        // 2. DO NOT deactivate the device - keep is_active = 1 for push notifications
        // This ensures push notifications continue after logout
        
        $logoutSuccess = true;
        
        error_log("🚪 User {$userId} logged out - Device {$playerId} REMAINS ACTIVE for push notifications");
        
        // Log the logout event
        if (file_exists('enhanced_logger.php')) {
            require_once 'enhanced_logger.php';
            $logger = new EnhancedSessionLogger($userId);
            $logger->logSessionEvent('DEVICE_LOGOUT_SESSION_ONLY', [
                'user_id' => $userId,
                'player_id' => $playerId,
                'device_remains_active' => true,
                'push_notifications_continue' => true
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Logout database error: " . $e->getMessage());
        // Continue with session destruction even if DB fails
    }
} else {
    error_log("Logout attempted without user_id. User: " . ($userId ?? 'null'));
}

// Clear all session data
$_SESSION = [];

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'None'
    ]);
}

// Destroy remember token cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'None'
    ]);
}

// Destroy the session
session_destroy();

// For Android WebView, we need to handle the redirect properly
$isAndroidApp = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false;

if ($isAndroidApp) {
    // For Android apps, use JavaScript redirect to avoid HTTP response issues
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Logging out...</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script>
            // Clear any local storage data
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem('sessionPreserved');
                localStorage.removeItem('lastKeepAlive');
                localStorage.removeItem('sessionInitialized');
                localStorage.removeItem('current_player_id');
                localStorage.removeItem('heartbeatCount');
            }
            
            // Redirect to login page
            setTimeout(function() {
                window.location.href = 'https://deegeecard.com/login.php?logout=success';
            }, 100);
        </script>
    </head>
    <body>
        <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
            <h2>Logging out...</h2>
            <p>Please wait while we securely log you out.</p>
            <p><small>Push notifications will continue to work on this device.</small></p>
        </div>
    </body>
    </html>
    <?php
    exit();
} else {
    // For web browsers, use standard redirect
    header('Location: https://deegeecard.com/login.php?logout=success');
    exit();
}
?>
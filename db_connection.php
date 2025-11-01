<?php
// Enhanced db_connection.php with 365-day session management and recovery
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// =========================================
// 365-DAY SESSION CONFIGURATION
// =========================================
if (session_status() === PHP_SESSION_NONE) {
    // Force 365-day session configuration
    ini_set('session.gc_maxlifetime', 31536000);
    ini_set('session.cookie_lifetime', 31536000);
    ini_set('session.gc_probability', 0); // Disable garbage collection
    ini_set('session.gc_divisor', 1);
    
    // Session cookie parameters for 365 days
    session_set_cookie_params([
        'lifetime' => 31536000,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'None'
    ]);
    
    session_start();
}

// =========================================
// ANDROID SESSION RECOVERY & MAINTENANCE
// =========================================
$isAndroidApp = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
               isset($_SERVER['HTTP_X_WEBTONATIVE']);

if ($isAndroidApp) {
    $_SESSION['is_android_app'] = true;
    $_SESSION['android_last_activity'] = time();
    
    // If user is logged in, maintain Android session aggressively
    if (isset($_SESSION['user_id'])) {
        $_SESSION['session_expires'] = time() + 31536000;
        $_SESSION['last_activity'] = time();
        
        // Force cookie update for WebToNative
        setcookie(session_name(), session_id(), [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
        
        error_log("🔧 Android Session Maintained - User: {$_SESSION['user_id']}, Session: " . session_id());
    }
}

// =========================================
// DATABASE CONFIGURATION
// =========================================
$host = 'localhost';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';
$database = 'doctorie_webihooks_card';

// Create a connection to the database
$conn = new mysqli($host, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    // Log error but don't die - allow session to continue
    error_log("Database connection failed: " . $conn->connect_error);
    // Continue without database connection for session-only operations
}

// Optional: Set charset to utf8mb4 for better compatibility
if ($conn) {
    $conn->set_charset("utf8mb4");
}

// Function to close the database connection (optional)
function closeConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// =========================================
// ANDROID SESSION HELPER FUNCTIONS
// =========================================
function isAndroidApp() {
    return strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
           isset($_SERVER['HTTP_X_WEBTONATIVE']) ||
           isset($_SESSION['is_android_app']);
}

function maintainAndroidSession($userId = null) {
    if (!isAndroidApp()) return false;
    
    if ($userId) {
        $_SESSION['user_id'] = $userId;
    }
    
    if (isset($_SESSION['user_id'])) {
        $_SESSION['is_android_app'] = true;
        $_SESSION['android_last_activity'] = time();
        $_SESSION['session_expires'] = time() + 31536000;
        $_SESSION['last_activity'] = time();
        
        // Force cookie update
        setcookie(session_name(), session_id(), [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
        
        return true;
    }
    
    return false;
}

// Auto-maintain Android session if user is logged in
if (isAndroidApp() && isset($_SESSION['user_id'])) {
    maintainAndroidSession();
}
?>
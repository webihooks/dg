<?php
// logout.php - Enhanced with complete device cleanup
require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();

session_start();

$userId = $_SESSION['user_id'] ?? null;

// Database connection to clear remember token AND OneSignal devices
if ($userId) {
    $host = 'localhost';
    $dbname = 'doctorie_webihooks_card';
    $username = 'doctorie_webihooks';
    $password = 'S@g@r4834';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 1. Clear remember token from database
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        // 2. DEACTIVATE OneSignal devices for this user
        $deviceStmt = $conn->prepare("UPDATE user_devices SET is_active = 0 WHERE user_id = :user_id");
        $deviceStmt->bindParam(':user_id', $userId);
        $deviceStmt->execute();
        
        error_log("🚪 User {$userId} logged out - tokens cleared and devices deactivated");
        
    } catch (PDOException $e) {
        error_log("Logout database error: " . $e->getMessage());
    }
}

// Destroy all session data
$_SESSION = [];

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
}

// Destroy remember token cookie
setcookie('remember_token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>
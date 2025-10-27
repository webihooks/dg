<?php
// logout.php - Enhanced with complete session destruction
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'None'
]);

session_start();

// Database connection to clear remember token
if (isset($_SESSION['user_id'])) {
    $host = 'localhost';
    $dbname = 'doctorie_webihooks_card';
    $username = 'root';
    $password = '';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Clear remember token from database
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        error_log("🚪 User {$_SESSION['user_id']} logged out manually");
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
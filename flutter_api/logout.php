<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

session_start();

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Clear remember_token from database if user_id exists in session
if (isset($_SESSION['user_id'])) {
    // Database connection
    $host = 'localhost';
    $dbname = 'doctorie_webihooks_card';
    $username = 'doctorie_webihooks';
    $password = 'S@g@r4834';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Continue with logout even if database update fails
    }
}

// Destroy session
session_destroy();

// Clear remember me cookie
setcookie('remember_token', '', time() - 3600, '/', 'dgcard.online', isset($_SERVER['HTTPS']), true);

echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>
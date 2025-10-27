<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$response = [
    'session' => [
        'user_id' => $_SESSION['user_id'] ?? null,
        'is_android_app' => $_SESSION['is_android_app'] ?? false,
        'email' => $_SESSION['email'] ?? null
    ],
    'user_devices' => [],
    'debug_info' => []
];

if (isset($_SESSION['user_id'])) {
    // Get user devices
    $stmt = $conn->prepare("SELECT * FROM user_devices WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $response['user_devices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
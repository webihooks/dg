<?php
// api/subscription_info.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Database connection with ACTUAL credentials
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Fetch subscription info
$subscription_info = [];
$sql = "SELECT s.*, p.name as package_name, p.price, p.description 
        FROM subscriptions s 
        JOIN packages p ON s.package_id = p.id 
        WHERE s.user_id = ? AND s.status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bindParam(1, $user_id);
$stmt->execute();
$subscription_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch available packages
$packages = [];
$sql_packages = "SELECT id, name, price, description, duration FROM packages WHERE status = 'active'";
$stmt_packages = $conn->query($sql_packages);
while ($row = $stmt_packages->fetch(PDO::FETCH_ASSOC)) {
    $packages[] = $row;
}

echo json_encode([
    'success' => true,
    'subscription' => $subscription_info,
    'packages' => $packages
]);

$conn = null;
?>
<?php
// api/orders.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

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

// Get filters from query parameters
$status = $_GET['status'] ?? 'all';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query based on status filter
$sql = "SELECT * FROM orders";
$params = [];

if ($status !== 'all') {
    $sql .= " WHERE status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sql);

// Bind parameters
if ($status !== 'all') {
    $stmt->bindParam(':status', $status);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders";
if ($status !== 'all') {
    $count_sql .= " WHERE status = :status";
}

$count_stmt = $conn->prepare($count_sql);
if ($status !== 'all') {
    $count_stmt->bindParam(':status', $status);
}
$count_stmt->execute();
$total = $count_stmt->fetch()['total'];

echo json_encode([
    'success' => true,
    'orders' => $orders,
    'total' => $total,
    'page' => $page,
    'total_pages' => ceil($total / $limit)
]);
?>
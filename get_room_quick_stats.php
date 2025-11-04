<?php
// get_room_quick_stats.php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'stats' => []];

try {
    // Check if rooms table exists
    $check_table = "SHOW TABLES LIKE 'rooms_$user_id'";
    $table_result = $conn->query($check_table);
    
    if ($table_result->num_rows > 0) {
        // Get room statistics
        $stats_sql = "SELECT 
            COUNT(*) as total_rooms,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
            FROM rooms_$user_id";
        
        $stats_result = $conn->query($stats_sql);
        if ($stats_result) {
            $response['stats'] = $stats_result->fetch_assoc();
            $response['success'] = true;
        }
    } else {
        $response['stats'] = [
            'total_rooms' => 0,
            'available' => 0,
            'occupied' => 0,
            'maintenance' => 0
        ];
        $response['success'] = true;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
?>
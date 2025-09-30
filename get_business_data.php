<?php
require 'db_connection.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get business info
    $business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
    $business_stmt = $conn->prepare($business_sql);
    $business_stmt->bind_param("i", $user_id);
    $business_stmt->execute();
    $business_result = $business_stmt->get_result();
    $business_info = $business_result->fetch_assoc() ?: [];
    $business_stmt->close();
    
    // Get user phone
    $user_sql = "SELECT phone FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_phone = $user_data['phone'] ?? '';
    $user_stmt->close();
    
    // Get profile URL from profile_url_details table
    $profile_url = '';
    $profile_sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
    $profile_stmt = $conn->prepare($profile_sql);
    $profile_stmt->bind_param("i", $user_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    
    if ($profile_result && $profile_data = $profile_result->fetch_assoc()) {
        $profile_url = $profile_data['profile_url'] ?? '';
    }
    $profile_stmt->close();
    
    echo json_encode([
        'success' => true,
        'business_info' => $business_info,
        'user_phone' => $user_phone,
        'profile_url' => $profile_url
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
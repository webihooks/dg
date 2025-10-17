<?php
// // debug_webpush.php
// session_start();
// header('Content-Type: application/json');

// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['error' => 'Not authenticated']);
//     exit;
// }

// require_once 'config/db_connection.php';
// require_once 'web_push_notification.php';

// $userId = $_SESSION['user_id'];

// try {
//     // Get subscriptions
//     $stmt = $conn->prepare("SELECT subscription_data FROM fcm_tokens WHERE user_id = ?");
//     $stmt->execute([$userId]);
//     $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
//     $debugInfo = [
//         'user_id' => $userId,
//         'subscriptions_count' => count($subscriptions),
//         'subscriptions' => []
//     ];
    
//     foreach ($subscriptions as $index => $sub) {
//         $data = json_decode($sub['subscription_data'], true);
//         $debugInfo['subscriptions'][] = [
//             'index' => $index,
//             'endpoint' => isset($data['endpoint']) ? substr($data['endpoint'], 0, 50) . '...' : 'missing',
//             'has_keys' => isset($data['keys']) ? 'yes' : 'no',
//             'p256dh_length' => isset($data['keys']['p256dh']) ? strlen($data['keys']['p256dh']) : 0,
//             'auth_length' => isset($data['keys']['auth']) ? strlen($data['keys']['auth']) : 0
//         ];
//     }
    
//     echo json_encode($debugInfo);
    
// } catch (Exception $e) {
//     echo json_encode(['error' => $e->getMessage()]);
// }
?>
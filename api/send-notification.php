<?php
header('Content-Type: application/json');
require_once '../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = $input['title'] ?? 'New Order!';
    $body = $input['body'] ?? 'You have received a new order';
    $sound = $input['sound'] ?? '/assets/sounds/new_order.mp3';
    $userId = $input['user_id'] ?? null;
    
    // Get user's push subscription from database
    $stmt = $pdo->prepare("SELECT push_subscription FROM user_push_subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($subscription) {
        $subscriptionData = json_decode($subscription['push_subscription'], true);
        
        // Send push notification
        $result = sendPushNotification($subscriptionData, $title, $body, $sound);
        
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No subscription found']);
    }
}

function sendPushNotification($subscription, $title, $body, $sound) {
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'sound' => $sound,
        'icon' => 'https://deegeecard.com/images/dg_logo.png',
        'vibrate' => [200, 100, 200],
        'data' => [
            'url' => '/admin-dashboard.php',
            'timestamp' => time()
        ]
    ]);
    
    $headers = [
        'Authorization: key=YOUR_VAPID_PUBLIC_KEY', // Replace with your VAPID key
        'Content-Type: application/json',
        'TTL: 60'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $subscription['endpoint']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result !== false;
}
?>
<?php
// date_default_timezone_set('Asia/Kolkata');
// require_once 'firebase-config.php';

// class FCMNotification {
    
//     public static function sendNewOrderNotification($userId, $orderId, $customerName, $totalAmount) {
//         try {
//             error_log("=== FCM NOTIFICATION START ===");
//             error_log("FCM: User: $userId, Order: $orderId, Customer: $customerName, Amount: $totalAmount");
            
//             $accessToken = FirebaseConfig::getAccessToken();
//             if (!$accessToken) {
//                 throw new Exception('Failed to get access token');
//             }
            
//             error_log("FCM: Access token obtained");
            
//             // Get FCM subscriptions for the user
//             $subscriptions = self::getUserFCMSubscriptions($userId);
//             error_log("FCM: Found " . count($subscriptions) . " subscriptions for user $userId");
            
//             if (empty($subscriptions)) {
//                 error_log("FCM: No FCM subscriptions found for user: $userId");
//                 return false;
//             }
            
//             $successCount = 0;
//             foreach ($subscriptions as $subscriptionData) {
//                 if (self::sendToSubscription($accessToken, $subscriptionData, $orderId, $customerName, $totalAmount)) {
//                     $successCount++;
//                 }
//             }
            
//             error_log("FCM: Notifications sent successfully: $successCount/" . count($subscriptions));
//             error_log("=== FCM NOTIFICATION END ===\n");
            
//             return $successCount > 0;
            
//         } catch (Exception $e) {
//             error_log("FCM Notification error: " . $e->getMessage());
//             error_log("=== FCM NOTIFICATION FAILED ===\n");
//             return false;
//         }
//     }
    
//     private static function getUserFCMSubscriptions($userId) {
//         global $conn;
        
//         if (!isset($conn)) {
//             error_log("FCM: Database connection not available");
//             return [];
//         }
        
//         try {
//             $stmt = $conn->prepare("SELECT subscription_data FROM fcm_tokens WHERE user_id = ?");
//             $stmt->execute([$userId]);
//             $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
//             $subscriptions = [];
//             foreach ($results as $row) {
//                 if (!empty($row['subscription_data'])) {
//                     $subscription = json_decode($row['subscription_data'], true);
//                     if ($subscription && isset($subscription['endpoint'])) {
//                         $subscriptions[] = $subscription;
//                     }
//                 }
//             }
            
//             error_log("FCM: Retrieved " . count($subscriptions) . " valid subscriptions from database");
//             return $subscriptions;
            
//         } catch (Exception $e) {
//             error_log("FCM: Error getting subscriptions: " . $e->getMessage());
//             return [];
//         }
//     }
    
//     private static function sendToSubscription($accessToken, $subscription, $orderId, $customerName, $totalAmount) {
//         error_log("FCM: Attempting to send to subscription endpoint: " . substr($subscription['endpoint'], 0, 50) . "...");
        
//         // Create the web push notification
//         $message = [
//             'message' => [
//                 'token' => $subscription['endpoint'], // Use the endpoint as token for web push
//                 'notification' => [
//                     'title' => '🔔 New Order Received!',
//                     'body' => "Order #$orderId from $customerName - ₹$totalAmount"
//                 ],
//                 'webpush' => [
//                     'headers' => [
//                         'Urgency' => 'high'
//                     ],
//                     'notification' => [
//                         'icon' => 'https://dgcard.online/assets/images/logo-sm.png',
//                         'badge' => 'https://dgcard.online/assets/images/logo-sm.png',
//                         'sound' => 'https://dgcard.online/assets/sounds/new_order.wav',
//                         'requireInteraction' => true,
//                         'actions' => [
//                             [
//                                 'action' => 'view',
//                                 'title' => 'View Order'
//                             ],
//                             [
//                                 'action' => 'dismiss', 
//                                 'title' => 'Dismiss'
//                             ]
//                         ]
//                     ],
//                     'fcm_options' => [
//                         'link' => 'https://dgcard.online/orders.php?order_id=' . $orderId
//                     ]
//                 ],
//                 'data' => [
//                     'order_id' => (string)$orderId,
//                     'type' => 'new_order',
//                     'click_action' => 'https://dgcard.online/orders.php'
//                 ]
//             ]
//         ];
        
//         $jsonMessage = json_encode($message, JSON_UNESCAPED_SLASHES);
//         error_log("FCM: Message JSON prepared");
        
//         $ch = curl_init();
//         curl_setopt_array($ch, [
//             CURLOPT_URL => 'https://fcm.googleapis.com/v1/projects/' . FirebaseConfig::PROJECT_ID . '/messages:send',
//             CURLOPT_RETURNTRANSFER => true,
//             CURLOPT_POST => true,
//             CURLOPT_POSTFIELDS => $jsonMessage,
//             CURLOPT_HTTPHEADER => [
//                 'Authorization: Bearer ' . $accessToken,
//                 'Content-Type: ' . 'application/json'
//             ],
//             CURLOPT_SSL_VERIFYPEER => true,
//             CURLOPT_TIMEOUT => 30
//         ]);
        
//         $response = curl_exec($ch);
//         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//         $curlError = curl_error($ch);
//         curl_close($ch);
        
//         error_log("FCM: HTTP Code: $httpCode");
        
//         if ($httpCode === 200) {
//             $responseData = json_decode($response, true);
//             if (isset($responseData['name'])) {
//                 error_log("FCM: ✅ Message sent successfully: " . $responseData['name']);
//                 return true;
//             }
//         } else {
//             error_log("FCM: ❌ HTTP Error $httpCode");
//             error_log("FCM: Response: " . $response);
            
//             if ($httpCode === 404 || $httpCode === 410) {
//                 // Subscription is invalid, we should remove it from database
//                 self::removeInvalidSubscription($subscription['endpoint']);
//             }
//             return false;
//         }
        
//         return false;
//     }
    
//     private static function removeInvalidSubscription($endpoint) {
//         global $conn;
//         try {
//             // Find and remove subscription by endpoint
//             $stmt = $conn->prepare("DELETE FROM fcm_tokens WHERE subscription_data LIKE ?");
//             $stmt->execute(['%' . $endpoint . '%']);
//             $deletedCount = $stmt->rowCount();
//             error_log("FCM: Removed $deletedCount invalid subscription(s) from database");
//         } catch (Exception $e) {
//             error_log("FCM: Error removing invalid subscription: " . $e->getMessage());
//         }
//     }
// }
?>
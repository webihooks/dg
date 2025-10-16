<?php
require_once 'vendor/autoload.php';
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class WebPushNotification {
    
    // VAPID keys - using your actual keys
    private static $vapidKeys = [
        'publicKey' => 'BA_40giep4c7wQZcDwmq_u23SFwDrgPwoCFrrPt2MR-aCMBW324yqvAsATjlzowX4cCtSbh1a7fC10rxi_3IY3U',
        'privateKey' => 'itFjBUn_SazcZQKlG3aUDBoenrg1Y64nsBq8UXDDWZI'
    ];
    
    public static function sendNewOrderNotification($userId, $orderId, $customerName, $customerAddress, $totalAmount, $orderType = 'delivery') {
        try {
            error_log("=== WEB PUSH NOTIFICATION START ===");
            error_log("WebPush: User: $userId, Order: $orderId, Customer: $customerName, Amount: $totalAmount");
            
            // Get web push subscriptions for the user
            $subscriptions = self::getUserWebPushSubscriptions($userId);
            error_log("WebPush: Found " . count($subscriptions) . " subscriptions for user $userId");
            
            if (empty($subscriptions)) {
                error_log("WebPush: No web push subscriptions found for user: $userId");
                return false;
            }
            
            // Format address for display
            $displayAddress = self::formatAddress($customerAddress, $orderType);
            
            // Create notification payload with all required information
            $payload = json_encode([
                'title' => '🔔 New Order Received!',
                'body' => "From: $customerName\nAmount: ₹$totalAmount\n$displayAddress",
                'icon' => 'https://dgcard.online/assets/images/logo-sm.png',
                'badge' => 'https://dgcard.online/assets/images/logo-sm.png',
                'sound' => 'https://dgcard.online/assets/sounds/new_order.mp3',
                'data' => [
                    'order_id' => $orderId,
                    'customer_name' => $customerName,
                    'customer_address' => $customerAddress,
                    'total_amount' => $totalAmount,
                    'order_type' => $orderType,
                    'type' => 'new_order',
                    'click_action' => 'https://dgcard.online/orders.php',
                    'timestamp' => time()
                ],
                'actions' => [
                    [
                        'action' => 'view',
                        'title' => '📋 View Order'
                    ],
                    [
                        'action' => 'dismiss',
                        'title' => '❌ Dismiss'
                    ]
                ]
            ]);
            
            $successCount = 0;
            foreach ($subscriptions as $subscriptionData) {
                if (self::sendWebPush($subscriptionData, $payload)) {
                    $successCount++;
                }
            }
            
            error_log("WebPush: Notifications sent successfully: $successCount/" . count($subscriptions));
            error_log("=== WEB PUSH NOTIFICATION END ===\n");
            
            return $successCount > 0;
            
        } catch (Exception $e) {
            error_log("WebPush Notification error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    private static function formatAddress($address, $orderType) {
        if ($orderType === 'dining') {
            return "Dining Order";
        } elseif (!empty($address)) {
            // Shorten address for notification display
            $address = strip_tags($address);
            if (strlen($address) > 50) {
                return substr($address, 0, 47) . '...';
            }
            return $address;
        } else {
            return "No address provided";
        }
    }
    
    private static function getUserWebPushSubscriptions($userId) {
        global $conn;
        
        if (!isset($conn)) {
            error_log("WebPush: Database connection not available");
            return [];
        }
        
        try {
            $stmt = $conn->prepare("SELECT subscription_data FROM fcm_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $subscriptions = [];
            foreach ($results as $row) {
                if (!empty($row['subscription_data'])) {
                    $subscription = json_decode($row['subscription_data'], true);
                    if ($subscription && isset($subscription['endpoint']) && isset($subscription['keys'])) {
                        $subscriptions[] = $subscription;
                    }
                }
            }
            
            error_log("WebPush: Valid subscriptions: " . count($subscriptions));
            return $subscriptions;
            
        } catch (Exception $e) {
            error_log("WebPush: Error getting subscriptions: " . $e->getMessage());
            return [];
        }
    }
    
    private static function sendWebPush($subscriptionData, $payload) {
        try {
            error_log("WebPush: Sending to endpoint: " . substr($subscriptionData['endpoint'], 0, 50) . "...");
            
            // Validate subscription data
            if (empty($subscriptionData['endpoint']) || empty($subscriptionData['keys']['p256dh']) || empty($subscriptionData['keys']['auth'])) {
                error_log("WebPush: ❌ Invalid subscription data - missing required fields");
                return false;
            }
            
            // Create web push instance with VAPID authentication
            $auth = [
                'VAPID' => [
                    'subject' => 'https://dgcard.online',
                    'publicKey' => self::$vapidKeys['publicKey'],
                    'privateKey' => self::$vapidKeys['privateKey'],
                ],
            ];
            
            $webPush = new WebPush($auth, [
                'TTL' => 300, // 5 minutes TTL
                'urgency' => 'high', // high priority
                'topic' => 'new-order', // topic for coalescing
            ]);
            
            // Create subscription object
            $subscription = Subscription::create([
                'endpoint' => $subscriptionData['endpoint'],
                'publicKey' => $subscriptionData['keys']['p256dh'],
                'authToken' => $subscriptionData['keys']['auth'],
                'contentEncoding' => 'aesgcm'
            ]);
            
            error_log("WebPush: Sending notification...");
            
            // Send notification
            $report = $webPush->sendOneNotification(
                $subscription,
                $payload
            );
            
            // Check result
            $endpoint = $subscriptionData['endpoint'];
            
            if ($report->isSuccess()) {
                error_log("WebPush: ✅ Notification sent successfully to: " . substr($endpoint, 0, 30) . "...");
                return true;
            } else {
                $reason = $report->getReason();
                error_log("WebPush: ❌ Failed to send to " . substr($endpoint, 0, 30) . "...: " . $reason);
                
                // If subscription is invalid, remove it
                if ($report->isSubscriptionExpired()) {
                    error_log("WebPush: Subscription expired, removing...");
                    self::removeInvalidSubscription($endpoint);
                }
                return false;
            }
            
        } catch (Exception $e) {
            error_log("WebPush: Exception: " . $e->getMessage());
            error_log("WebPush: Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    private static function removeInvalidSubscription($endpoint) {
        global $conn;
        try {
            $stmt = $conn->prepare("DELETE FROM fcm_tokens WHERE subscription_data LIKE ?");
            $stmt->execute(['%' . $endpoint . '%']);
            $deletedCount = $stmt->rowCount();
            error_log("WebPush: Removed $deletedCount invalid subscription(s) for endpoint: " . substr($endpoint, 0, 30) . "...");
        } catch (Exception $e) {
            error_log("WebPush: Error removing invalid subscription: " . $e->getMessage());
        }
    }
}
?>
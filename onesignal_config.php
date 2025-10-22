<?php
/**
 * OneSignal v2 API Implementation - WITH YOUR CHANNEL ID
 */
class OneSignalNotification {
    private $appId;
    private $restApiKey;
    private $apiUrl = 'https://onesignal.com/api/v1/notifications';
    
    public function __construct() {
        $this->appId = "9d512a16-1b7c-4d2c-ae9f-07c36c963086";
        $this->restApiKey = "os_v2_app_tvisufq3prgszlu7a7bwzfrqq3wmhbl53lmem2fmf2cqjrkae2izj4uohbajanp2dnpyxhcmbtru53c5jkczqqovrathaohvyoxhpxq";
    }
    
    private function getDBConnection() {
        $host = 'localhost';
        $dbname = 'doctorie_webihooks_card';
        $username = 'doctorie_webihooks';
        $password = 'S@g@r4834';
        
        try {
            $conn = new mysqli($host, $username, $password, $dbname);
            return $conn;
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send notification with YOUR channel ID
     */
    public function sendNewOrderNotification($userId, $orderId, $customerName, $totalAmount) {
        error_log("SENDING NOTIFICATION: User $userId, Order $orderId");
        
        try {
            $playerIds = $this->getUserPlayerIds($userId);
            
            if (empty($playerIds)) {
                error_log("No registered devices for user: $userId");
                return ['success' => false, 'message' => 'No registered devices found'];
            }
            
            error_log("Found " . count($playerIds) . " devices for user $userId");
            
            // Notification data WITH YOUR CHANNEL ID
            $notificationData = [
                'app_id' => $this->appId,
                'include_player_ids' => $playerIds,
                'headings' => ['en' => 'New Order Received! 🔥🔥🔥'],
                'contents' => ['en' => "Order Id: {$orderId} from {$customerName} - ₹{$totalAmount}"],
                'data' => [
                    'order_id' => $orderId,
                    'type' => 'new_order',
                    'user_id' => $userId,
                    'customer_name' => $customerName,
                    'total_amount' => $totalAmount
                ],
                'android_channel_id' => '98231266-7763-4604-9817-fd3871a27ca5', // YOUR CHANNEL ID
                'small_icon' => 'ic_stat_onesignal_default',
                'large_icon' => 'ic_launcher', 
                'android_accent_color' => 'FF267ABD',
                'priority' => 10,
                'ttl' => 3600
            ];
            
            $result = $this->sendCurlRequest($notificationData);
            
            if ($result['success']) {
                error_log("✅ Notification sent to " . count($playerIds) . " devices");
                return [
                    'success' => true,
                    'message' => 'Notification sent to ' . count($playerIds) . ' device(s)',
                    'player_ids' => $playerIds
                ];
            } else {
                error_log("❌ Notification failed: " . $result['error']);
                return [
                    'success' => false,
                    'message' => 'Failed: ' . $result['error'],
                    'http_code' => $result['http_code']
                ];
            }
            
        } catch (Exception $e) {
            error_log("❌ Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }
    
    private function sendCurlRequest($data) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer ' . $this->restApiKey
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true, 'http_code' => $httpCode, 'data' => json_decode($response, true)];
        } else {
            return ['success' => false, 'http_code' => $httpCode, 'error' => $error, 'response' => $response];
        }
    }
    
    private function getUserPlayerIds($userId) {
        $playerIds = [];
        $conn = $this->getDBConnection();
        
        if (!$conn) return $playerIds;
        
        try {
            $stmt = $conn->prepare("SELECT player_id FROM user_devices WHERE user_id = ? AND is_active = 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['player_id'])) {
                    $playerIds[] = $row['player_id'];
                }
            }
            
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
        }
        
        return $playerIds;
    }
    
    /**
     * Test notification with your channel
     */
    public function sendTestNotification($userId) {
        return $this->sendNewOrderNotification(
            $userId, 
            "TEST-" . time(), 
            "Test Customer", 
            "199.00"
        );
    }
}
?>
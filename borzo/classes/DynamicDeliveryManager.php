<?php
// borzo/classes/DynamicDeliveryManager.php - COMPLETE with per-user environment support

require_once __DIR__ . '/DeliveryManager.php';
require_once __DIR__ . '/DynamicBorzoAPI.php';

class DynamicDeliveryManager {
    private $dynamicAPI;
    private $deliveryManager;
    private $user_id;
    private $config;
    private $logger;
    
    /**
     * Constructor
     * @param int $user_id
     * @param array $baseConfig
     * @param object $logger
     */
    public function __construct($user_id, $baseConfig, $logger = null) {
        $this->user_id = $user_id;
        $this->config = $baseConfig;
        $this->logger = $logger;
        
        try {
            // Initialize dynamic API (this loads user's credentials and environment)
            $this->dynamicAPI = new DynamicBorzoAPI($user_id, $baseConfig, $logger);
            
            // Get the dynamic config with user's API key and environment
            $dynamicConfig = $this->getDynamicConfig();
            
            // Initialize DeliveryManager with dynamic config
            $this->deliveryManager = new DeliveryManager($dynamicConfig, $logger);
            
        } catch (Exception $e) {
            error_log("DynamicDeliveryManager Error for user $user_id: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get dynamic config with user's API key and environment
     * @return array
     */
    private function getDynamicConfig() {
        global $conn;
        if (!$conn) {
            require_once dirname(__DIR__, 2) . '/db_connection.php';
        }
        
        $sql = "SELECT borzo_api_key, api_environment FROM borzo_api WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $userEnvironment = $row['api_environment'];
            $userApiKey = $row['borzo_api_key'];
            
            // Override the global environment with user's choice
            $dynamicConfig = $this->config;
            $dynamicConfig['environment'] = $userEnvironment;
            
            // Ensure the api array for this environment exists
            if (!isset($dynamicConfig['api'][$userEnvironment])) {
                $dynamicConfig['api'][$userEnvironment] = [
                    'url' => '',
                    'token' => ''
                ];
            }
            
            // Set the token for that environment
            $dynamicConfig['api'][$userEnvironment]['token'] = $userApiKey;
            
            $stmt->close();
            return $dynamicConfig;
        }
        
        $stmt->close();
        return $this->config; // fallback to global config (should not happen)
    }
    
    /**
     * Check if user has valid API key
     * @return bool
     */
    public function hasValidApiKey() {
        try {
            return $this->dynamicAPI->hasApiKey();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get user's current environment
     * @return string|null 'test' or 'production'
     */
    public function getUserEnvironment() {
        global $conn;
        if (!$conn) {
            require_once dirname(__DIR__, 2) . '/db_connection.php';
        }
        
        $sql = "SELECT api_environment FROM borzo_api WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['api_environment'];
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Create order with user's API key and environment
     * @param array $orderData
     * @return array
     */
    public function createOrder($orderData) {
        if (!$this->deliveryManager) {
            return [
                'success' => false,
                'errors' => ['API key not configured']
            ];
        }
        
        return $this->deliveryManager->createOrder($orderData);
    }
    
    /**
     * Calculate delivery with user's API key and environment
     * @param string $deliveryAddress
     * @param array $orderDetails
     * @return array
     */
    public function calculateDelivery($deliveryAddress, $orderDetails) {
        if (!$this->deliveryManager) {
            return [
                'success' => false,
                'errors' => ['API key not configured']
            ];
        }
        
        return $this->deliveryManager->calculateDelivery($deliveryAddress, $orderDetails);
    }
    
    /**
     * Track order with user's API key and environment
     * @param int $orderId
     * @return array
     */
    public function trackOrder($orderId) {
        if (!$this->deliveryManager) {
            return [
                'success' => false,
                'error' => 'API key not configured'
            ];
        }
        
        return $this->deliveryManager->trackOrder($orderId);
    }
    
    /**
     * Cancel order with user's API key and environment
     * @param int $orderId
     * @return array
     */
    public function cancelOrder($orderId) {
        if (!$this->deliveryManager) {
            return [
                'success' => false,
                'errors' => ['API key not configured']
            ];
        }
        
        return $this->deliveryManager->cancelOrder($orderId);
    }
    
    /**
     * Get user's API key info (masked)
     * @return array
     */
    public function getApiKeyInfo() {
        global $conn;
        if (!$conn) {
            require_once dirname(__DIR__, 2) . '/db_connection.php';
        }
        
        $sql = "SELECT borzo_api_key, api_environment, created_at, updated_at FROM borzo_api WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $apiKey = $row['borzo_api_key'];
            $maskedKey = substr($apiKey, 0, 10) . '...' . substr($apiKey, -4);
            
            $stmt->close();
            return [
                'exists' => true,
                'environment' => $row['api_environment'],
                'key_masked' => $maskedKey,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        $stmt->close();
        return ['exists' => false];
    }
}
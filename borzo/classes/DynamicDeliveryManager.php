<?php
// borzo/classes/DynamicDeliveryManager.php

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
            // Initialize dynamic API
            $this->dynamicAPI = new DynamicBorzoAPI($user_id, $baseConfig, $logger);
            
            // Get the dynamic config with user's API key
            $dynamicConfig = $this->getDynamicConfig();
            
            // Initialize DeliveryManager with dynamic config
            $this->deliveryManager = new DeliveryManager($dynamicConfig, $logger);
            
        } catch (Exception $e) {
            error_log("DynamicDeliveryManager Error for user $user_id: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get dynamic config with user's API key
     * @return array
     */
    private function getDynamicConfig() {
        $dynamicConfig = $this->config;
        
        // Load user's API key from database
        global $conn;
        if (!$conn) {
            require_once dirname(__DIR__, 2) . '/db_connection.php';
        }
        
        $sql = "SELECT borzo_api_key FROM borzo_api WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $environment = $this->config['environment'];
            $dynamicConfig['api'][$environment]['token'] = $row['borzo_api_key'];
        }
        
        $stmt->close();
        return $dynamicConfig;
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
     * Create order with user's API key
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
     * Calculate delivery with user's API key
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
     * Track order with user's API key
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
     * Cancel order with user's API key
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
     * Get user's API key info
     * @return array
     */
    public function getApiKeyInfo() {
        global $conn;
        if (!$conn) {
            require_once dirname(__DIR__, 2) . '/db_connection.php';
        }
        
        $sql = "SELECT * FROM borzo_api WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return [
                'exists' => true,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'key_masked' => substr($row['borzo_api_key'], 0, 10) . '...' . substr($row['borzo_api_key'], -4)
            ];
        }
        
        $stmt->close();
        return ['exists' => false];
    }
}
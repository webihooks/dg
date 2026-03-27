<?php
// borzo/classes/DynamicBorzoAPI.php - FIXED VERSION

require_once __DIR__ . '/BorzoAPI.php';

class DynamicBorzoAPI {
    private $db;
    private $user_id;
    private $config;
    private $borzoAPI;
    private $logger;
    
    /**
     * Constructor
     * @param int $user_id The user ID to load API key for
     * @param array $baseConfig Base configuration from borzo.php
     * @param object $logger Optional logger
     */
    public function __construct($user_id, $baseConfig, $logger = null) {
        $this->user_id = $user_id;
        $this->config = $baseConfig;
        $this->logger = $logger;
        
        // Validate baseConfig
        if (!is_array($baseConfig)) {
            throw new Exception("DynamicBorzoAPI: baseConfig must be an array");
        }
        
        if (!isset($baseConfig['environment'])) {
            throw new Exception("DynamicBorzoAPI: environment not set in config");
        }
        
        // Get database connection
        global $conn;
        if (!$conn) {
            require_once dirname(__DIR__, 2) . '/db_connection.php';
        }
        $this->db = $conn;
        
        if (!$this->db) {
            throw new Exception("Database connection failed in DynamicBorzoAPI");
        }
        
        // Load user's API key
        $apiKey = $this->loadUserApiKey();
        
        if (!$apiKey) {
            throw new Exception("No Borzo API key found for user ID: " . $user_id);
        }
        
        // Create dynamic config with user's API key
        $dynamicConfig = $this->createDynamicConfig($apiKey);
        
        // Initialize BorzoAPI with dynamic config
        $this->borzoAPI = new BorzoAPI($dynamicConfig, $this->logger);
    }
    
    /**
     * Load user's API key from database
     * @return string|null
     */
    private function loadUserApiKey() {
        if (!$this->db) {
            throw new Exception("Database connection not available");
        }
        
        $sql = "SELECT borzo_api_key FROM borzo_api WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare SQL: " . $this->db->error);
        }
        
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['borzo_api_key'];
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Create dynamic config with user's API key
     * @param string $apiKey
     * @return array
     */
    private function createDynamicConfig($apiKey) {
        // Start with a copy of the base config
        $dynamicConfig = $this->config;
        
        // Ensure the api array exists
        if (!isset($dynamicConfig['api'])) {
            $dynamicConfig['api'] = [];
        }
        
        // Override the API token with user's key
        $environment = $this->config['environment'];
        
        // Ensure the environment array exists
        if (!isset($dynamicConfig['api'][$environment])) {
            $dynamicConfig['api'][$environment] = [
                'url' => '',
                'token' => ''
            ];
        }
        
        // Set the token
        $dynamicConfig['api'][$environment]['token'] = $apiKey;
        
        return $dynamicConfig;
    }
    
    /**
     * Get the BorzoAPI instance
     * @return BorzoAPI
     */
    public function getBorzoAPI() {
        return $this->borzoAPI;
    }
    
    /**
     * Make API request using user's credentials
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     */
    public function request($endpoint, $data = [], $method = 'POST') {
        return $this->borzoAPI->request($endpoint, $data, $method);
    }
    
    /**
     * Check if user has API key configured
     * @return bool
     */
    public function hasApiKey() {
        if (!$this->db) {
            return false;
        }
        
        $sql = "SELECT id FROM borzo_api WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $hasKey = $result->num_rows > 0;
        $stmt->close();
        
        return $hasKey;
    }
    
    /**
     * Validate user's API key with Borzo
     * @return array
     */
    public function validateApiKey() {
        try {
            // Try to make a simple test request
            $testData = [
                'matter' => 'API Key Validation',
                'points' => [
                    [
                        'address' => 'Saket, New Delhi, Delhi',
                        'contact_person' => ['phone' => '918880000001']
                    ],
                    [
                        'address' => 'Janakpuri, New Delhi, Delhi',
                        'contact_person' => ['phone' => '918880000001']
                    ]
                ]
            ];
            
            $result = $this->borzoAPI->request('/calculate-order', $testData);
            
            if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
                return [
                    'success' => true,
                    'message' => 'API key is valid',
                    'data' => $result['response']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'API key validation failed',
                    'errors' => $result['response']['errors'] ?? ['Unknown error']
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error validating API key',
                'error' => $e->getMessage()
            ];
        }
    }
}
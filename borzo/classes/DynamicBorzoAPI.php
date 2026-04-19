<?php
// borzo/classes/DynamicBorzoAPI.php - COMPLETE with per-user environment support

require_once __DIR__ . '/BorzoAPI.php';

class DynamicBorzoAPI {
    private $db;
    private $user_id;
    private $config;
    private $borzoAPI;
    private $logger;
    private $userEnvironment;
    private $userApiKey;
    
    /**
     * Constructor
     * @param int $user_id The user ID to load API credentials for
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
        
        // Get database connection
        global $conn;
        if (!$conn) {
            require_once dirname(__DIR__, 2) . '/db_connection.php';
        }
        $this->db = $conn;
        
        if (!$this->db) {
            throw new Exception("Database connection failed in DynamicBorzoAPI");
        }
        
        // Load user's API credentials (key + environment)
        $credentials = $this->loadUserApiCredentials();
        
        if (!$credentials) {
            throw new Exception("No Borzo API credentials found for user ID: " . $user_id);
        }
        
        $this->userApiKey = $credentials['api_key'];
        $this->userEnvironment = $credentials['environment'];
        
        // Create dynamic config with user's environment and API key
        $dynamicConfig = $this->createDynamicConfig($this->userApiKey, $this->userEnvironment);
        
        // Initialize BorzoAPI with dynamic config
        $this->borzoAPI = new BorzoAPI($dynamicConfig, $this->logger);
    }
    
    /**
     * Load user's API key and environment from database
     * @return array|null Associative array with 'api_key' and 'environment'
     */
    private function loadUserApiCredentials() {
        if (!$this->db) {
            throw new Exception("Database connection not available");
        }
        
        $sql = "SELECT borzo_api_key, api_environment FROM borzo_api WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare SQL: " . $this->db->error);
        }
        
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return [
                'api_key' => $row['borzo_api_key'],
                'environment' => $row['api_environment']
            ];
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Create dynamic config with user's API key and environment
     * @param string $apiKey
     * @param string $environment 'test' or 'production'
     * @return array
     */
    private function createDynamicConfig($apiKey, $environment) {
        // Start with a copy of the base config
        $dynamicConfig = $this->config;
        
        // Override the environment
        $dynamicConfig['environment'] = $environment;
        
        // Ensure the api array exists
        if (!isset($dynamicConfig['api'])) {
            $dynamicConfig['api'] = [];
        }
        
        // Ensure the environment array exists
        if (!isset($dynamicConfig['api'][$environment])) {
            // Get the base URL from the appropriate environment config
            $baseUrl = '';
            if (isset($this->config['api'][$environment]['url'])) {
                $baseUrl = $this->config['api'][$environment]['url'];
            } elseif ($environment === 'test' && isset($this->config['api']['test']['url'])) {
                $baseUrl = $this->config['api']['test']['url'];
            } elseif ($environment === 'production' && isset($this->config['api']['production']['url'])) {
                $baseUrl = $this->config['api']['production']['url'];
            }
            
            $dynamicConfig['api'][$environment] = [
                'url' => $baseUrl,
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
     * Get user's current environment
     * @return string|null
     */
    public function getUserEnvironment() {
        return $this->userEnvironment;
    }
    
    /**
     * Get masked API key for display
     * @return string
     */
    public function getMaskedApiKey() {
        if (empty($this->userApiKey)) {
            return '';
        }
        $length = strlen($this->userApiKey);
        if ($length > 14) {
            return substr($this->userApiKey, 0, 10) . '...' . substr($this->userApiKey, -4);
        }
        return str_repeat('•', $length);
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
                        'address' => 'Saket, New Delhi, Delhi, India',
                        'contact_person' => ['phone' => '918880000001']
                    ],
                    [
                        'address' => 'Connaught Place, New Delhi, Delhi, India',
                        'contact_person' => ['phone' => '918880000001']
                    ]
                ],
                'total_weight_kg' => 1
            ];
            
            $result = $this->borzoAPI->request('/calculate-order', $testData);
            
            if ($result['http_code'] == 200 && isset($result['response']['is_successful']) && $result['response']['is_successful']) {
                return [
                    'success' => true,
                    'message' => 'API key is valid',
                    'environment' => $this->userEnvironment,
                    'api_url' => $this->borzoAPI->getApiUrl(),
                    'data' => $result['response']
                ];
            } else {
                $errors = $result['response']['errors'] ?? ['Unknown error'];
                return [
                    'success' => false,
                    'message' => 'API key validation failed',
                    'environment' => $this->userEnvironment,
                    'errors' => $errors
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error validating API key',
                'environment' => $this->userEnvironment,
                'error' => $e->getMessage()
            ];
        }
    }
}
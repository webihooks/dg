<?php
// borzo/classes/BorzoAPI.php

class BorzoAPI {
    private $apiUrl;
    private $authToken;
    private $config;
    private $logger;
    
    public function __construct($config, $logger = null) {
        if (!is_array($config)) {
            throw new Exception('BorzoAPI constructor: $config must be an array');
        }
        
        $this->config = $config;
        $this->logger = $logger ?: new class {
            public function log($message, $data = []) {
                $logFile = __DIR__ . '/../logs/borzo-api.log';
                $logDir = dirname($logFile);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                $logEntry = date('Y-m-d H:i:s') . " - $message - " . json_encode($data) . "\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
            }
        };
        
        if (!isset($config['environment'])) {
            throw new Exception('BorzoAPI: environment not set in config');
        }
        
        $environment = $config['environment'];
        
        if (!isset($config['api'][$environment])) {
            throw new Exception("BorzoAPI: API config not found for environment: $environment");
        }
        
        $this->apiUrl = $config['api'][$environment]['url'];
        $this->authToken = $config['api'][$environment]['token'];
        
        if (empty($this->apiUrl)) {
            throw new Exception("BorzoAPI: API URL is empty for environment: $environment");
        }
        if (empty($this->authToken)) {
            throw new Exception("BorzoAPI: Auth token is empty for environment: $environment");
        }
    }
    
    public function request($endpoint, $data = [], $method = 'POST') {
        $ch = curl_init();
        $url = $this->apiUrl . $endpoint;
        $jsonData = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        
        $headers = [
            'X-DV-Auth-Token: ' . $this->authToken,
            'Content-Type: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            }
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        $this->logger->log('API Request', [
            'endpoint' => $endpoint,
            'method' => $method,
            'http_code' => $httpCode,
            'error' => $error
        ]);
        
        if ($error) {
            throw new Exception('Curl error: ' . $error);
        }
        
        $response = json_decode($result, true);
        
        if ($httpCode !== 200) {
            $this->logger->log('API Error', $response);
        }
        
        return [
            'http_code' => $httpCode,
            'response' => $response
        ];
    }
}
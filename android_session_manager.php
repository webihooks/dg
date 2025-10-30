<?php
// android_session_manager.php - Enhanced with WebToNative support and session start detection
require_once 'enhanced_logger.php';

class AndroidSessionManager {
    private $conn;
    private $logger;
    private $sessionStarted = false;
    
    public function __construct() {
        $this->logger = new EnhancedSessionLogger($_SESSION['user_id'] ?? null);
        $this->initSession();
        $this->initDB();
    }
    
    private function initSession() {
        // Check if session is already started
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->sessionStarted = true;
            $this->logger->logSessionEvent('SESSION_ALREADY_ACTIVE', [
                'session_id' => session_id(),
                'is_android' => $this->isAndroidApp(),
                'is_webtonative' => $this->isWebToNative()
            ]);
            return; // Session already active, don't modify settings
        }
        
        // Only set session parameters if session is not already started
        session_set_cookie_params([
            'lifetime' => 31536000, // 1 year
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
        
        ini_set('session.gc_maxlifetime', 31536000);
        
        session_start();
        $this->sessionStarted = true;
        
        $this->logger->logSessionEvent('SESSION_STARTED', [
            'session_id' => session_id(),
            'is_android' => $this->isAndroidApp(),
            'is_webtonative' => $this->isWebToNative(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    }
    
    private function initDB() {
        $host = 'localhost';
        $dbname = 'doctorie_webihooks_card';
        $username = 'doctorie_webihooks';
        $password = 'S@g@r4834';
        
        try {
            $this->conn = new mysqli($host, $username, $password, $dbname);
            if ($this->conn->connect_error) {
                $this->logger->logSessionError("DB Connection failed: " . $this->conn->connect_error);
            } else {
                $this->logger->logSessionEvent('DB_CONNECTION_SUCCESS', [
                    'host' => $host,
                    'database' => $dbname
                ]);
            }
        } catch (Exception $e) {
            $this->logger->logSessionError("DB Connection exception: " . $e->getMessage());
        }
    }
    
    public function isAndroidApp() {
        $isAndroid = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
                    isset($_SERVER['HTTP_X_WEBTONATIVE']) ||
                    isset($_SESSION['is_android_app']) ||
                    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'com.webtonative.app');
        
        return $isAndroid;
    }
    
    public function isWebToNative() {
        $isWebToNative = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
                        isset($_SERVER['HTTP_X_WEBTONATIVE']) ||
                        (isset($_SESSION['webtonative_detected']) && $_SESSION['webtonative_detected'] === true);
        
        return $isWebToNative;
    }
    
    public function maintainAndroidSession($userId) {
        if (!$this->isAndroidApp()) {
            $this->logger->logSessionEvent('MAINTENANCE_SKIPPED', [
                'reason' => 'Not Android app',
                'user_id' => $userId
            ]);
            return false;
        }
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['is_android_app'] = true;
        $_SESSION['android_last_activity'] = time();
        $_SESSION['session_expires'] = time() + 31536000;
        $_SESSION['android_session_created'] = time();
        
        // WebToNative specific session data
        if ($this->isWebToNative()) {
            $_SESSION['webtonative_detected'] = true;
            $_SESSION['webtonative_user'] = true;
            $_SESSION['webtonative_session_start'] = time();
            $_SESSION['webtonative_last_activity'] = time();
            
            $this->logger->logAndroidEvent('WEBTONATIVE_SESSION_MAINTAINED', [
                'user_id' => $userId,
                'session_id' => session_id(),
                'timestamp' => time()
            ]);
        }
        
        // Only update cookie if session was started by this manager
        if (!$this->sessionStarted) {
            $this->updateSessionCookie();
        }
        
        $this->logger->logAndroidEvent('SESSION_MAINTAINED', [
            'user_id' => $userId,
            'session_id' => session_id(),
            'session_started_by_manager' => !$this->sessionStarted,
            'is_webtonative' => $this->isWebToNative()
        ]);
        
        return true;
    }
    
    public function maintainWebToNativeSession($userId) {
        if (!$this->isWebToNative()) {
            $this->logger->logSessionEvent('WEBTONATIVE_MAINTENANCE_SKIPPED', [
                'reason' => 'Not WebToNative',
                'user_id' => $userId
            ]);
            return false;
        }
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['is_android_app'] = true;
        $_SESSION['webtonative_detected'] = true;
        $_SESSION['webtonative_user'] = true;
        $_SESSION['webtonative_session_start'] = time();
        $_SESSION['webtonative_last_activity'] = time();
        $_SESSION['android_last_activity'] = time();
        $_SESSION['session_expires'] = time() + 31536000;
        
        // Force cookie update for WebToNative
        $this->updateSessionCookie();
        
        $this->logger->logAndroidEvent('WEBTONATIVE_SESSION_FORCE_MAINTAINED', [
            'user_id' => $userId,
            'session_id' => session_id(),
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        return true;
    }
    
    private function updateSessionCookie() {
        if (headers_sent()) {
            $this->logger->logSessionEvent('COOKIE_UPDATE_SKIPPED', [
                'reason' => 'Headers already sent',
                'session_id' => session_id()
            ]);
            return false;
        }
        
        setcookie(session_name(), session_id(), [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
        
        $this->logger->logSessionEvent('COOKIE_UPDATED', [
            'session_id' => session_id(),
            'expires' => time() + 31536000,
            'is_webtonative' => $this->isWebToNative()
        ]);
        
        return true;
    }
    
    public function validateAndroidSession() {
        if (!$this->isAndroidApp()) {
            return true; // Not an Android app, validation not needed
        }
        
        // For Android apps, always maintain session if user_id exists
        if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
            $userId = $_SESSION['user_id'];
            
            // Use WebToNative specific maintenance if detected
            if ($this->isWebToNative()) {
                $this->maintainWebToNativeSession($userId);
            } else {
                $this->maintainAndroidSession($userId);
            }
            
            // Update last activity timestamp
            $_SESSION['last_activity'] = time();
            $_SESSION['android_last_validation'] = time();
            
            $this->logger->logAndroidEvent('SESSION_VALIDATION_SUCCESS', [
                'user_id' => $userId,
                'session_id' => session_id(),
                'is_webtonative' => $this->isWebToNative()
            ]);
            
            return true;
        }
        
        $this->logger->logAndroidEvent('SESSION_VALIDATION_FAILED', [
            'has_user_id' => isset($_SESSION['user_id']),
            'user_id_value' => $_SESSION['user_id'] ?? null,
            'is_webtonative' => $this->isWebToNative(),
            'session_id' => session_id()
        ]);
        
        return false;
    }
    
    public function forceSessionRefresh() {
        if (!$this->isAndroidApp()) {
            return false;
        }
        
        // Regenerate session ID for security while maintaining data
        session_regenerate_id(true);
        
        // Update session cookie with new ID
        $this->updateSessionCookie();
        
        $_SESSION['session_refreshed'] = time();
        $_SESSION['session_refresh_count'] = ($_SESSION['session_refresh_count'] ?? 0) + 1;
        
        $this->logger->logAndroidEvent('SESSION_FORCE_REFRESHED', [
            'user_id' => $_SESSION['user_id'] ?? null,
            'old_session_id' => session_id(),
            'new_session_id' => session_id(),
            'refresh_count' => $_SESSION['session_refresh_count'],
            'is_webtonative' => $this->isWebToNative()
        ]);
        
        return true;
    }
    
    public function getSessionInfo() {
        $sessionAge = null;
        if (isset($_SESSION['login_time'])) {
            $sessionAge = time() - $_SESSION['login_time'];
        }
        
        $webToNativeAge = null;
        if (isset($_SESSION['webtonative_session_start'])) {
            $webToNativeAge = time() - $_SESSION['webtonative_session_start'];
        }
        
        return [
            'is_android' => $this->isAndroidApp(),
            'is_webtonative' => $this->isWebToNative(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'session_id' => session_id(),
            'session_age' => $sessionAge,
            'webtonative_age' => $webToNativeAge,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'android_last_activity' => $_SESSION['android_last_activity'] ?? null,
            'webtonative_last_activity' => $_SESSION['webtonative_last_activity'] ?? null,
            'session_started_by_manager' => !$this->sessionStarted,
            'session_refresh_count' => $_SESSION['session_refresh_count'] ?? 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
    }
    
    public function getDebugInfo() {
        $sessionInfo = $this->getSessionInfo();
        
        return [
            'session_info' => $sessionInfo,
            'server_info' => [
                'http_host' => $_SERVER['HTTP_HOST'] ?? 'Not set',
                'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Not set',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Not set',
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'Not set'
            ],
            'cookie_info' => [
                'session_cookie_received' => isset($_COOKIE[session_name()]) ? 'Yes' : 'No',
                'remember_token_received' => isset($_COOKIE['remember_token']) ? 'Yes' : 'No',
                'cookies_count' => count($_COOKIE)
            ],
            'timestamp' => time(),
            'php_session_status' => session_status()
        ];
    }
    
    public function logSessionHealth() {
        $healthInfo = $this->getSessionInfo();
        $healthInfo['timestamp'] = time();
        $healthInfo['memory_usage'] = memory_get_usage(true);
        $healthInfo['memory_peak'] = memory_get_peak_usage(true);
        
        $this->logger->logSessionEvent('SESSION_HEALTH_CHECK', $healthInfo);
        
        return $healthInfo;
    }
    
    public function wasSessionStartedByManager() {
        return !$this->sessionStarted;
    }
    
    public function cleanup() {
        if ($this->conn) {
            $this->conn->close();
        }
        
        $this->logger->logSessionEvent('SESSION_MANAGER_CLEANUP', [
            'session_id' => session_id(),
            'is_webtonative' => $this->isWebToNative()
        ]);
    }
    
    public function __destruct() {
        $this->cleanup();
    }
}

// Global helper functions
function is_android_app() {
    static $manager = null;
    if ($manager === null) {
        $manager = new AndroidSessionManager();
    }
    return $manager->isAndroidApp();
}

function is_webtonative_app() {
    static $manager = null;
    if ($manager === null) {
        $manager = new AndroidSessionManager();
    }
    return $manager->isWebToNative();
}

function get_android_session_info() {
    static $manager = null;
    if ($manager === null) {
        $manager = new AndroidSessionManager();
    }
    return $manager->getSessionInfo();
}

function log_android_session_event($event, $details = []) {
    static $manager = null;
    if ($manager === null) {
        $manager = new AndroidSessionManager();
    }
    
    if ($manager->isAndroidApp()) {
        $manager->logger->logAndroidEvent($event, $details);
    }
}
?>
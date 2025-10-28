<?php
// android_session_manager.php - Enhanced with session start detection
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
                'is_android' => $this->isAndroidApp()
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
            'is_android' => $this->isAndroidApp()
        ]);
    }
    
    private function initDB() {
        $host = 'localhost';
        $dbname = 'doctorie_webihooks_card';
        $username = 'doctorie_webihooks';
        $password = 'S@g@r4834';
        
        $this->conn = new mysqli($host, $username, $password, $dbname);
        if ($this->conn->connect_error) {
            $this->logger->logSessionError("DB Connection failed: " . $this->conn->connect_error);
        }
    }
    
    public function isAndroidApp() {
        $isAndroid = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
                    isset($_SESSION['is_android_app']);
        
        if ($isAndroid && !isset($_SESSION['android_detected'])) {
            $_SESSION['android_detected'] = true;
            $this->logger->logAndroidEvent('DETECTED', [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
        
        return $isAndroid;
    }
    
    public function maintainAndroidSession($userId) {
        if (!$this->isAndroidApp()) return false;
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['is_android_app'] = true;
        $_SESSION['android_last_activity'] = time();
        $_SESSION['session_expires'] = time() + 31536000;
        
        // Only update cookie if session was started by this manager
        if (!$this->sessionStarted) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + 31536000,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'None'
            ]);
        }
        
        $this->logger->logAndroidEvent('SESSION_MAINTAINED', [
            'user_id' => $userId,
            'session_id' => session_id(),
            'session_started_by_manager' => !$this->sessionStarted
        ]);
        
        return true;
    }
    
    public function validateAndroidSession() {
        if (!$this->isAndroidApp()) return true;
        
        // For Android apps, always maintain session if user_id exists
        if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
            $this->maintainAndroidSession($_SESSION['user_id']);
            return true;
        }
        
        $this->logger->logAndroidEvent('SESSION_VALIDATION_FAILED', [
            'has_user_id' => isset($_SESSION['user_id']),
            'user_id_value' => $_SESSION['user_id'] ?? null
        ]);
        
        return false;
    }
    
    public function getSessionInfo() {
        return [
            'is_android' => $this->isAndroidApp(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'session_id' => session_id(),
            'session_age' => isset($_SESSION['login_time']) ? time() - $_SESSION['login_time'] : null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'session_started_by_manager' => !$this->sessionStarted
        ];
    }
    
    public function wasSessionStartedByManager() {
        return !$this->sessionStarted;
    }
}
?>
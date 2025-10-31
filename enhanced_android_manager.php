<?php
// enhanced_android_manager.php - Aggressive Android Session Management
class EnhancedAndroidSessionManager {
    private $logger;
    
    public function __construct() {
        $this->initSession();
    }
    
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Force 365-day session configuration
            $this->setSessionConfig();
            session_start();
        }
        
        $this->maintainAndroidSession();
    }
    
    private function setSessionConfig() {
        // Override any PHP.INI limitations
        ini_set('session.gc_maxlifetime', 31536000);
        ini_set('session.cookie_lifetime', 31536000);
        ini_set('session.gc_probability', 0);
        ini_set('session.gc_divisor', 1);
        
        // Custom session path to avoid server cleanup
        $custom_path = $_SERVER['DOCUMENT_ROOT'] . '/android_sessions';
        if (!is_dir($custom_path)) {
            mkdir($custom_path, 0755, true);
        }
        ini_set('session.save_path', $custom_path);
        
        // Session cookie for 365 days
        session_set_cookie_params([
            'lifetime' => 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
    }
    
    public function maintainAndroidSession() {
        if ($this->isAndroidApp() && isset($_SESSION['user_id'])) {
            // Aggressive session maintenance for Android
            $_SESSION['last_activity'] = time();
            $_SESSION['android_heartbeat'] = time();
            $_SESSION['session_expires'] = time() + 31536000;
            
            // Force cookie update every time
            setcookie(session_name(), session_id(), [
                'expires' => time() + 31536000,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'None'
            ]);
            
            // WebToNative specific maintenance
            if ($this->isWebToNative()) {
                $this->forceWebToNativeCookieUpdate();
            }
        }
    }
    
    public function isAndroidApp() {
        return strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
               isset($_SERVER['HTTP_X_WEBTONATIVE']) ||
               isset($_SESSION['is_android_app']);
    }
    
    public function isWebToNative() {
        return strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
               isset($_SERVER['HTTP_X_WEBTONATIVE']);
    }
    
    private function forceWebToNativeCookieUpdate() {
        // This will be handled by JavaScript, but we log it
        error_log("🔧 WebToNative Session Maintained - User: " . ($_SESSION['user_id'] ?? 'Unknown'));
    }
    
    public function validateSession() {
        if (isset($_SESSION['user_id'])) {
            $this->maintainAndroidSession();
            return true;
        }
        return false;
    }
}

// Initialize enhanced session manager
$androidSessionManager = new EnhancedAndroidSessionManager();
?>
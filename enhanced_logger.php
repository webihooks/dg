<?php
// enhanced_logger.php - Comprehensive session logging
class EnhancedSessionLogger {
    private $logFile;
    private $userId;
    private $isAndroid;
    
    public function __construct($userId = null) {
        $this->logFile = __DIR__ . '/logs/session_logs_' . date('Y-m-d') . '.log';
        $this->userId = $userId;
        $this->isAndroid = $this->detectAndroid();
        
        // Ensure log directory exists
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    private function detectAndroid() {
        return strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
               isset($_SESSION['is_android_app']);
    }
    
    public function logSessionEvent($event, $details = []) {
        $timestamp = date('Y-m-d H:i:s');
        $userInfo = $this->userId ? "User:{$this->userId}" : "NoUser";
        $platform = $this->isAndroid ? 'Android' : 'Web';
        $sessionId = session_id() ?? 'NoSession';
        
        $logEntry = [
            'timestamp' => $timestamp,
            'event' => $event,
            'user' => $userInfo,
            'platform' => $platform,
            'session_id' => substr($sessionId, 0, 10) . '...',
            'details' => $details
        ];
        
        $logLine = "[$timestamp] [$platform] [$userInfo] $event - " . 
                  json_encode($details) . " - Session: " . substr($sessionId, 0, 10) . "...\n";
        
        // Write to file
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log for important events
        if (in_array($event, ['SESSION_START', 'SESSION_END', 'SESSION_ERROR', 'ANDROID_LOGIN', 'LOGOUT'])) {
            error_log("SESSION_LOG: $event - $userInfo - $platform");
        }
        
        return $logEntry;
    }
    
    public function logSessionError($error, $context = []) {
        $context['error'] = $error;
        $context['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        return $this->logSessionEvent('SESSION_ERROR', $context);
    }
    
    public function logAndroidEvent($event, $details = []) {
        if ($this->isAndroid) {
            $details['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            return $this->logSessionEvent("ANDROID_$event", $details);
        }
        return null;
    }
}

// Global logger function
function log_session_event($event, $details = [], $userId = null) {
    static $logger = null;
    if ($logger === null) {
        $logger = new EnhancedSessionLogger($userId ?? ($_SESSION['user_id'] ?? null));
    }
    return $logger->logSessionEvent($event, $details);
}
?>
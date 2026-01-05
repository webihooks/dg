<?php
function checkSession() {
    // Session should already be started by db_connection.php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if session is valid
    if (isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
        // Check if session is not too old (max 365 days of inactivity)
        if (time() - $_SESSION['login_time'] > 31536000) { // 365 days
            session_destroy();
            header("Location: login.php");
            exit();
        }
        
        // Update session time
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    
    // Check for remember me token
    if (isset($_COOKIE['remember_token'])) {
        require 'db_connection.php'; // Your database connection file
        
        $token = $_COOKIE['remember_token'];
        
        // Use mysqli instead of PDO
        $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ? AND token_expires > ?");
        $current_time = time();
        $stmt->bind_param("si", $token, $current_time);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            // Restore session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Android app specific session data
            $isAndroidApp = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false;
            if ($isAndroidApp) {
                $_SESSION['is_android_app'] = true;
                $_SESSION['android_last_activity'] = time();
                $_SESSION['session_expires'] = time() + 31536000;
            }
            
            return true;
        }
    }
    
    return false;
}

// Auto-check session on every page that includes this file
checkSession();
?>
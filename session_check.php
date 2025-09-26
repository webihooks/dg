<?php

function checkSession() {
    session_start();
    
    // Check if session is valid
    if (isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
        // Check if session is not too old (max 30 days of inactivity)
        if (time() - $_SESSION['login_time'] > 2592000) { // 30 days
            session_destroy();
            header("Location: login.php");
            exit();
        }
        
        // Update session time
        $_SESSION['login_time'] = time();
        return true;
    }
    
    // Check for remember me token
    if (isset($_COOKIE['remember_token'])) {
        require 'db_connection.php'; // Your database connection file
        
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = :token AND token_expires > :now");
        $stmt->bindParam(':token', $token);
        $stmt->bindValue(':now', time());
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Restore session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['login_time'] = time();
            return true;
        }
    }
    
    return false;
}

// Auto-check session on every page that includes this file
checkSession();
?>
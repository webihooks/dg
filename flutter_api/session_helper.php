<?php
// Session helper for Flutter app WebView sessions
function handleFlutterSession() {
    // Check if request is coming from Flutter app
    if (isset($_GET['source']) && $_GET['source'] === 'flutter_app') {
        session_start();
        
        // If flutter_user_id is provided, authenticate the user
        if (isset($_GET['flutter_user_id']) && !empty($_GET['flutter_user_id'])) {
            $flutter_user_id = intval($_GET['flutter_user_id']);
            
            // Database connection
            $host = 'localhost';
            $dbname = 'doctorie_webihooks_card';
            $username = 'doctorie_webihooks';
            $password = 'S@g@r4834';
            
            try {
                $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Verify user exists
                $stmt = $conn->prepare("SELECT id, Email, role FROM users WHERE id = ?");
                $stmt->execute([$flutter_user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['Email'];
                    $_SESSION['flutter_auth'] = true;
                    
                    return true;
                }
            } catch (PDOException $e) {
                error_log("Flutter session error: " . $e->getMessage());
            }
        }
    }
    return false;
}

// Check if user is logged in (supports both web and Flutter sessions)
function isUserLoggedIn() {
    session_start();
    
    // Check regular web session
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    
    // Check Flutter app session
    return handleFlutterSession();
}
?>
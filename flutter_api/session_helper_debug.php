<?php
// Debug session helper for Flutter app
function handleFlutterSession() {
    error_log("Flutter session check started");
    
    // Check if request is coming from Flutter app
    if (isset($_GET['source']) && $_GET['source'] === 'flutter_app') {
        error_log("Flutter source detected");
        session_start();
        
        // If flutter_user_id is provided, authenticate the user
        if (isset($_GET['flutter_user_id']) && !empty($_GET['flutter_user_id'])) {
            $flutter_user_id = intval($_GET['flutter_user_id']);
            error_log("Flutter user ID: " . $flutter_user_id);
            
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
                    error_log("User found: " . $user['Email']);
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['Email'];
                    $_SESSION['flutter_auth'] = true;
                    
                    return true;
                } else {
                    error_log("User not found with ID: " . $flutter_user_id);
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
            }
        } else {
            error_log("No flutter_user_id provided");
        }
    } else {
        error_log("Not a Flutter request");
    }
    return false;
}

function isUserLoggedIn() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check regular web session
    if (isset($_SESSION['user_id'])) {
        error_log("Regular session found: " . $_SESSION['user_id']);
        return true;
    }
    
    error_log("No regular session, checking Flutter session");
    // Check Flutter app session
    return handleFlutterSession();
}
?>
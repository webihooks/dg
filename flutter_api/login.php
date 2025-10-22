<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Start session with extended lifetime for mobile app
session_start();
ini_set('session.gc_maxlifetime', 31536000); // 1 year
session_set_cookie_params([
    'lifetime' => 31536000,
    'path' => '/',
    'domain' => 'dgcard.online',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Log the request for debugging
error_log("Login API called: " . $_SERVER['REQUEST_METHOD']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Debug logging
    error_log("Raw input: " . file_get_contents('php://input'));
    error_log("Parsed input: " . print_r($input, true));
    
    // Fallback to form data if JSON parsing fails
    if ($input === null || json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
        error_log("Using form data: " . print_r($input, true));
    }
    
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $input['password'] ?? '';
    $remember_me = isset($input['remember_me']) ? (bool)$input['remember_me'] : true;

    error_log("Login attempt - Email: $email, Password length: " . strlen($password));

    if (empty($email) || empty($password)) {
        error_log("Empty email or password");
        echo json_encode([
            'success' => false, 
            'message' => 'Email and password are required',
            'debug' => ['email_empty' => empty($email), 'password_empty' => empty($password)]
        ]);
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            error_log("User found: " . $user['Email']);
            error_log("Stored hash: " . $user['Password']);
            
            $password_verified = password_verify($password, $user['Password']);
            error_log("Password verified: " . ($password_verified ? 'YES' : 'NO'));
            
            if ($password_verified) {
                // Login successful
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['Email'];
                $_SESSION['login_time'] = time();
                
                // Set remember me token for mobile app
                if ($remember_me) {
                    $remember_token = bin2hex(random_bytes(32));
                    $expires = time() + 31536000; // 1 year
                    
                    $stmt = $conn->prepare("UPDATE users SET remember_token = :token, token_expires = :expires WHERE id = :id");
                    $stmt->bindParam(':token', $remember_token);
                    $stmt->bindParam(':expires', $expires);
                    $stmt->bindParam(':id', $user['id']);
                    $stmt->execute();
                }

                // Check subscription status
                $subscription_sql = "SELECT status FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
                $subscription_stmt = $conn->prepare($subscription_sql);
                $subscription_stmt->execute([$user['id']]);
                $has_active_subscription = ($subscription_stmt->rowCount() > 0);

                error_log("Login successful for user: " . $user['Email']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => (int)$user['id'],
                        'email' => $user['Email'],
                        'name' => $user['name'] ?? 'User',
                        'role' => $user['role'],
                        'is_trial' => isset($user['is_trial']) ? (bool)$user['is_trial'] : false,
                        'trial_end' => $user['trial_end'] ?? null,
                        'has_active_subscription' => $has_active_subscription,
                    ],
                    'redirect_url' => 'dashboard.php'
                ]);
                
            } else {
                error_log("Password verification failed");
                echo json_encode([
                    'success' => false, 
                    'message' => 'Invalid email or password',
                    'debug' => ['user_found' => true, 'password_match' => false]
                ]);
            }
        } else {
            error_log("User not found with email: $email");
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid email or password',
                'debug' => ['user_found' => false]
            ]);
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Login failed. Please try again.',
            'debug' => ['database_error' => $e->getMessage()]
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method: ' . $_SERVER['REQUEST_METHOD']]);
}
?>
<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Start session with extended lifetime for mobile app
session_start();
ini_set('session.gc_maxlifetime', 31536000); // 1 year
session_set_cookie_params([
    'lifetime' => 31536000,
    'path' => '/',
    'domain' => 'dgcard.online',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
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
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback to form data if JSON parsing fails
    if ($input === null) {
        $input = $_POST;
    }
    
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $input['password'] ?? '';
    $remember_me = isset($input['remember_me']) ? true : false;

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['Password'])) {
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
                
                // Set persistent cookie
                setcookie('remember_token', $remember_token, $expires, '/', 'dgcard.online', isset($_SERVER['HTTPS']), true);
            }

            // Check subscription status
            $subscription_sql = "SELECT status FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
            $subscription_stmt = $conn->prepare($subscription_sql);
            $subscription_stmt->execute([$user['id']]);
            $has_active_subscription = ($subscription_stmt->rowCount() > 0);

            // For mobile app, always redirect to dashboard
            $redirect_url = 'dashboard.php';

            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['Email'],
                    'name' => $user['name'] ?? 'User',
                    'role' => $user['role'],
                    'is_trial' => isset($user['is_trial']) ? (bool)$user['is_trial'] : false,
                    'trial_end' => $user['trial_end'] ?? null,
                    'has_active_subscription' => $has_active_subscription,
                ],
                'redirect_url' => $redirect_url
            ]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
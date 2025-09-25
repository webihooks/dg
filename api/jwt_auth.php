<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Manual JWT includes - ADD BEFORE require_once 'config.php'
require_once 'vendor/firebase/php-jwt/src/BeforeValidException.php';
require_once 'vendor/firebase/php-jwt/src/ExpiredException.php';
require_once 'vendor/firebase/php-jwt/src/JWT.php';
require_once 'vendor/firebase/php-jwt/src/Key.php';

// api/jwt_auth.php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept, charset, boundary, Content-Length');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = htmlspecialchars($input['email']);
    $password = $input['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['Password'])) {
            // Generate JWT token
            $payload = [
                'iss' => 'deegeecard.com',
                'aud' => 'deegeecard.com',
                'iat' => time(),
                'exp' => time() + (60 * 60 * 24), // 24 hours
                'data' => [
                    'user_id' => $user['id'],
                    'email' => $user['Email'],
                    'role' => $user['role']
                ]
            ];

            $jwt = JWT::encode($payload, JWT_SECRET, 'HS256');

            // Check subscription status
            $subscription_sql = "SELECT status FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
            $subscription_stmt = $conn->prepare($subscription_sql);
            $subscription_stmt->bindParam(1, $user['id']);
            $subscription_stmt->execute();
            $has_active_subscription = ($subscription_stmt->rowCount() > 0);
            $subscription_stmt->closeCursor();
            
            $redirectTo = 'dashboard';
            $message = 'Login successful';
            
            if (isset($user['trial_end']) && strtotime($user['trial_end']) < time() && !$has_active_subscription) {
                $redirectTo = 'subscription';
                $message = 'Trial period has ended';
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'redirectTo' => $redirectTo,
                'token' => $jwt,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['Email'],
                    'role' => $user['role'],
                    'trial_end' => $user['trial_end'] ?? null,
                    'has_subscription' => $has_active_subscription
                ]
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
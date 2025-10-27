<?php
// api/jwt_middleware.php
require_once 'config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticateJWT() {
    $headers = apache_request_headers();
    $token = null;

    // Get token from header
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token not provided']);
        exit();
    }

    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        return $decoded->data;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token: ' . $e->getMessage()]);
        exit();
    }
}
?>
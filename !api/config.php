<?php
// api/config.php

// // Your super secure JWT secret key (64 characters recommended)
// define('JWT_SECRET', 'vL9z#8Q@2pX5!kR7$mT3&wS6*yU1%jF4^dG0qW8eR2tY5uI7oP3aS9dF1gH4jK6l');

// Database configuration (optional - you can keep your existing connection code)
define('DB_HOST', 'localhost');
define('DB_NAME', 'doctorie_webihooks_card');
define('DB_USER', 'root');
define('DB_PASS', '');

// JWT expiration time (in seconds)
define('JWT_EXPIRATION', 60 * 60 * 24); // 24 hours

// CORS settings
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Timezone setting
date_default_timezone_set('Asia/Kolkata');
?>
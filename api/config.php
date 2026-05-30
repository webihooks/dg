<?php

// Database configuration (optional - you can keep your existing connection code)
define('DB_HOST', 'localhost');
define('DB_NAME', 'doctorie_webihooks_card');
define('DB_USER', 'doctorie_webihooks');
define('DB_PASS', 'S@g@r4834');

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
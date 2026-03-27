<?php
// CLI-safe database connection - NO HEADERS, NO SESSIONS
// For cron jobs and command line scripts only

// Suppress all header-related warnings for CLI
if (php_sapi_name() === 'cli') {
    // Define a dummy header function to prevent errors
    if (!function_exists('header')) {
        function header($string, $replace = true, $http_response_code = null) {
            // Silently ignore headers in CLI
            return true;
        }
    }
}

// =========================================
// DATABASE CONFIGURATION ONLY
// =========================================
$host = 'localhost';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';
$database = 'doctorie_webihooks_card';

// Create a connection to the database
$conn = new mysqli($host, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// No session functions, no headers
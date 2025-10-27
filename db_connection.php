<?php
// In your server configuration or PHP file
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");


// Database configuration
$host = 'localhost'; // Replace with your database host
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password
$database = 'doctorie_webihooks_card'; // Replace with your database name

// Create a connection to the database
$conn = new mysqli($host, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set charset to utf8mb4 for better compatibility
$conn->set_charset("utf8mb4");

// Function to close the database connection (optional)
function closeConnection($conn) {
    $conn->close();
}
?>
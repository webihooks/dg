<?php
require 'db_connection.php';

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'fcm_tokens'");
if ($result->num_rows > 0) {
    echo "✅ fcm_tokens table exists\n";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE fcm_tokens");
    echo "Table structure:\n";
    while($row = $structure->fetch_assoc()) {
        echo "- " . $row['Field'] . " : " . $row['Type'] . "\n";
    }
} else {
    echo "❌ fcm_tokens table does NOT exist\n";
}

$conn->close();
?>

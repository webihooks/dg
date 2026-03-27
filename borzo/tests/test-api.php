<?php
// borzo/test-api.php
echo "<h2>Borzo API Test</h2>";

// Test 1: Check if files exist
$files = [
    '/borzo/api/create-order.php',
    '/borzo/config/borzo.php',
    '/borzo/classes/BorzoAPI.php',
    '/borzo/classes/DeliveryManager.php',
    '/db_connection.php'
];

echo "<h3>File Existence Check:</h3>";
echo "<ul>";
foreach ($files as $file) {
    $path = $_SERVER['DOCUMENT_ROOT'] . $file;
    $exists = file_exists($path);
    echo "<li>$file: " . ($exists ? '✅ Found' : '❌ Not found') . " at $path</li>";
}
echo "</ul>";

// Test 2: Check database connection
echo "<h3>Database Connection:</h3>";
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connection.php';
    if ($conn) {
        echo "✅ Database connected successfully<br>";
        
        // Test query
        $result = $conn->query("SELECT COUNT(*) as count FROM orders");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✅ Orders table accessible, count: " . $row['count'] . "<br>";
        } else {
            echo "❌ Cannot query orders table: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Check Borzo config
echo "<h3>Borzo Configuration:</h3>";
try {
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/borzo/config/borzo.php';
    if (file_exists($configPath)) {
        $config = require $configPath;
        echo "✅ Config loaded<br>";
        echo "Environment: " . ($config['environment'] ?? 'not set') . "<br>";
        echo "API URL: " . ($config['api']['test']['url'] ?? 'not set') . "<br>";
        echo "Token: " . (substr($config['api']['test']['token'] ?? '', 0, 10) . '...') . "<br>";
    } else {
        echo "❌ Config file not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Error loading config: " . $e->getMessage() . "<br>";
}

// Test 4: Check permissions
echo "<h3>Directory Permissions:</h3>";
$logDir = $_SERVER['DOCUMENT_ROOT'] . '/borzo/logs';
if (is_dir($logDir)) {
    echo "✅ Logs directory exists<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($logDir)), -4) . "<br>";
    echo "Writable: " . (is_writable($logDir) ? '✅ Yes' : '❌ No') . "<br>";
} else {
    echo "❌ Logs directory not found<br>";
}
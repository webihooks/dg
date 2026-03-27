<?php
// borzo/test-config-debug.php
echo "<h2>Borzo Config Debug</h2>";

$configPath = __DIR__ . '/config/borzo.php';
echo "Config path: $configPath<br>";
echo "File exists: " . (file_exists($configPath) ? 'YES' : 'NO') . "<br>";

if (file_exists($configPath)) {
    echo "File is readable: " . (is_readable($configPath) ? 'YES' : 'NO') . "<br>";
    
    $config = require $configPath;
    echo "<pre>";
    print_r($config);
    echo "</pre>";
    
    echo "Config is array: " . (is_array($config) ? 'YES' : 'NO') . "<br>";
    echo "Environment: " . ($config['environment'] ?? 'NOT SET') . "<br>";
    echo "Test token exists: " . (isset($config['api']['test']['token']) ? 'YES' : 'NO') . "<br>";
    if (isset($config['api']['test']['token'])) {
        echo "Token (first 10 chars): " . substr($config['api']['test']['token'], 0, 10) . "...<br>";
    }
}
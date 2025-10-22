<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Environment Check</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "cURL Enabled: " . (function_exists('curl_version') ? 'Yes' : 'No') . "<br>";

// Check if Composer autoload exists
if (file_exists('vendor/autoload.php')) {
    echo "Composer: ✅ Installed<br>";
    
    // Check specific OneSignal classes
    $classes = [
        'OneSignal\Config',
        'OneSignal\OneSignal',
        'OneSignal\Devices',
        'OneSignal\Notifications'
    ];
    
    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "Class $class: ✅ Available<br>";
        } else {
            echo "Class $class: ❌ Missing<br>";
        }
    }
} else {
    echo "Composer: ❌ Not installed<br>";
    echo "Trying manual include...<br>";
    
    // Manual SDK check
    $sdk_files = [
        'src/OneSignal/Config.php',
        'src/OneSignal/OneSignal.php'
    ];
    
    foreach ($sdk_files as $file) {
        if (file_exists($file)) {
            echo "SDK File $file: ✅ Found<br>";
        } else {
            echo "SDK File $file: ❌ Missing<br>";
        }
    }
}

// Check your current onesignal_config.php
echo "<h3>Current Config Check</h3>";
if (file_exists('onesignal_config.php')) {
    $config_content = file_get_contents('onesignal_config.php');
    if (strpos($config_content, '9d512a16-1b7c-4d2c-ae9f-07c36c963086') !== false) {
        echo "OneSignal App ID: ✅ Found in config<br>";
    } else {
        echo "OneSignal App ID: ❌ Missing from config<br>";
    }
} else {
    echo "onesignal_config.php: ❌ File not found<br>";
}
?>
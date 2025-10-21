<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

echo "<h3>Update OneSignal API Key</h3>";

if ($_POST['api_key'] ?? '') {
    $newApiKey = trim($_POST['api_key']);
    
    // Read the current config file
    $configFile = 'onesignal_config.php';
    $content = file_get_contents($configFile);
    
    // Replace the API key
    $newContent = preg_replace(
        "/\\\$this->restApiKey = \"[^\"]*\"/",
        "\$this->restApiKey = \"$newApiKey\"",
        $content
    );
    
    if (file_put_contents($configFile, $newContent)) {
        echo "<p style='color: green;'>✅ API Key updated successfully!</p>";
        
        // Test the new key
        require_once 'onesignal_config.php';
        $oneSignal = new OneSignalNotification();
        $verification = $oneSignal->verifyCredentials();
        
        echo "<h4>Verification Result:</h4>";
        if ($verification['valid']) {
            echo "<p style='color: green;'>✅ " . $verification['message'] . "</p>";
        } else {
            echo "<p style='color: red;'>❌ " . $verification['message'] . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Failed to update API Key</p>";
    }
}

// Show current key (partially masked)
require_once 'onesignal_config.php';
$oneSignal = new OneSignalNotification();
$reflection = new ReflectionClass($oneSignal);
$property = $reflection->getProperty('restApiKey');
$property->setAccessible(true);
$currentKey = $property->getValue($oneSignal);

echo "<h4>Current API Key:</h4>";
if (strlen($currentKey) > 10) {
    echo substr($currentKey, 0, 10) . "..." . substr($currentKey, -10);
} else {
    echo $currentKey;
}

if ($currentKey === 'os_v2_app_tvisufq3prgszlu7a7bwzfrqq3wmhbl53lmem2fmf2cqjrkae2izj4uohbajanp2dnpyxhcmbtru53c5jkczqqovrathaohvyoxhpxq') {
    echo " <span style='color: red;'>(NOT CONFIGURED)</span>";
}
?>

<form method="post" style="margin: 20px 0; padding: 20px; border: 1px solid #ccc;">
    <h4>Enter Your OneSignal REST API Key:</h4>
    <p><strong>How to get it:</strong></p>
    <ol>
        <li>Go to <a href="https://onesignal.com" target="_blank">OneSignal.com</a></li>
        <li>Login and select your app</li>
        <li>Go to Settings → Keys & IDs</li>
        <li>Copy the "REST API Key" (starts with NGEwMGZm...)</li>
    </ol>
    
    <div style="margin: 15px 0;">
        <label><strong>REST API Key:</strong></label><br>
        <input type="text" name="api_key" value="" placeholder="Enter your REST API Key" 
               style="width: 500px; padding: 8px; margin: 5px 0;" required>
    </div>
    
    <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">
        Update API Key
    </button>
</form>

<p>
    <a href="test_notification_working.php">← Back to Test</a> | 
    <a href="notification_status.php">Check Status</a>
</p>
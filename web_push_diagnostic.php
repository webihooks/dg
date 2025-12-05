<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Web Push Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        button { padding: 10px 20px; margin: 10px; font-size: 16px; }
        .log { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; height: 300px; overflow-y: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Web Push Diagnostic Tool</h1>
    
    <div>
        <button onclick="testWebPush()">Test Web Push</button>
        <button onclick="checkSubscriptions()">Check Subscriptions</button>
        <button onclick="clearLog()">Clear Log</button>
    </div>
    
    <div>
        <h3>Status:</h3>
        <div id="status">Ready...</div>
    </div>
    
    <div id="log" class="log"></div>

    <script>
    const log = document.getElementById('log');
    const status = document.getElementById('status');
    
    function addLog(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        log.innerHTML += `<div class="${type}">[${timestamp}] ${message}</div>`;
        log.scrollTop = log.scrollHeight;
        console.log(message);
    }
    
    function updateStatus(message, type = 'info') {
        status.innerHTML = `<span class="${type}">${message}</span>`;
    }
    
    function clearLog() {
        log.innerHTML = '';
        updateStatus('Log cleared');
    }
    
    async function testWebPush() {
        addLog('🧪 Testing Web Push Notification...');
        updateStatus('Testing Web Push...', 'info');
        
        try {
            const response = await fetch('test_web_push.php');
            const result = await response.json();
            
            if (result.success) {
                addLog('✅ Web Push test successful! Check your device for notification.', 'success');
                updateStatus('Web Push test successful!', 'success');
            } else {
                addLog('❌ Web Push test failed: ' + result.message, 'error');
                updateStatus('Web Push test failed', 'error');
            }
        } catch (error) {
            addLog('❌ Web Push test error: ' + error.message, 'error');
            updateStatus('Web Push test error', 'error');
        }
    }
    
    async function checkSubscriptions() {
        addLog('📋 Checking subscriptions...');
        try {
            const response = await fetch('debug_tokens.php');
            const text = await response.text();
            addLog('Subscriptions checked - see browser console for details');
            console.log('Subscriptions:', text);
            updateStatus('Subscriptions checked', 'info');
        } catch (error) {
            addLog('❌ Check Error: ' + error.message, 'error');
        }
    }
    
    addLog('Web Push diagnostic tool ready.');
    addLog('Make sure VAPID private key is configured in web_push_notification.php');
    </script>
</body>
</html>
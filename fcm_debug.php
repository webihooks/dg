<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FCM Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        button { padding: 10px 20px; margin: 10px; font-size: 16px; cursor: pointer; }
        .log { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; height: 300px; overflow-y: auto; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>FCM Push Notification Debug</h1>
    
    <div>
        <button onclick="initFCM()">Initialize FCM</button>
        <button onclick="testPush()">Send Test Push + Ring</button>
        <button onclick="checkTokens()">Check Registered Tokens</button>
        <button onclick="clearLog()">Clear Log</button>
    </div>
    
    <div id="status">
        <h3>Status:</h3>
        <div id="statusContent">Ready...</div>
    </div>
    
    <div>
        <h3>Console Log:</h3>
        <div id="log" class="log"></div>
    </div>

    <script>
    const log = document.getElementById('log');
    const statusContent = document.getElementById('statusContent');
    
    function addLog(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        log.innerHTML += `<div class="${type}">[${timestamp}] ${message}</div>`;
        log.scrollTop = log.scrollHeight;
        console.log(message);
    }
    
    function updateStatus(message, type = 'info') {
        statusContent.innerHTML = `<span class="${type}">${message}</span>`;
    }
    
    function clearLog() {
        log.innerHTML = '';
        updateStatus('Log cleared');
    }
    
    async function initFCM() {
        addLog('🔄 Starting FCM initialization...', 'info');
        updateStatus('Initializing FCM...', 'info');
        
        if (!('serviceWorker' in navigator)) {
            addLog('❌ Service Worker not supported', 'error');
            updateStatus('Service Worker not supported', 'error');
            return;
        }
        
        if (!('PushManager' in window)) {
            addLog('❌ Push Manager not supported', 'error');
            updateStatus('Push Manager not supported', 'error');
            return;
        }
        
        try {
            // Register service worker
            addLog('🔄 Registering service worker...', 'info');
            const registration = await navigator.serviceWorker.register('/fcm-sw.js');
            addLog('✅ Service Worker registered', 'success');
            
            // Check current subscription
            let subscription = await registration.pushManager.getSubscription();
            
            if (subscription) {
                addLog('✅ Already subscribed to push notifications', 'success');
                await saveSubscription(subscription);
                updateStatus('FCM Already Registered', 'success');
                return;
            }
            
            // Request permission
            addLog('🔄 Requesting notification permission...', 'info');
            const permission = await Notification.requestPermission();
            
            if (permission !== 'granted') {
                addLog(`❌ Notification permission denied: ${permission}`, 'error');
                updateStatus('Notification permission denied', 'error');
                return;
            }
            
            addLog('✅ Notification permission granted', 'success');
            
            // Subscribe to push
            addLog('🔄 Subscribing to push notifications...', 'info');
            subscription = await subscribeToPush(registration);
            
            if (subscription) {
                addLog('✅ Push subscription created', 'success');
                await saveSubscription(subscription);
                addLog('🎉 FCM setup completed successfully!', 'success');
                updateStatus('FCM Registered Successfully', 'success');
            } else {
                addLog('❌ Failed to create push subscription', 'error');
                updateStatus('Failed to create subscription', 'error');
            }
            
        } catch (error) {
            addLog(`❌ FCM initialization failed: ${error.message}`, 'error');
            updateStatus(`FCM Failed: ${error.message}`, 'error');
        }
    }
    
    async function subscribeToPush(registration) {
        try {
            const vapidPublicKey = 'BA_40giep4c7wQZcDwmq_u23SFwDrgPwoCFrrPt2MR-aCMBW324yqvAsATjlzowX4cCtSbh1a7fC10rxi_3IY3U';
            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
            });
            
            return subscription;
        } catch (error) {
            addLog(`❌ Subscription error: ${error.message}`, 'error');
            return null;
        }
    }
    
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        
        return outputArray;
    }
    
    async function saveSubscription(subscription) {
        try {
            addLog('🔄 Saving subscription to server...', 'info');
            
            const response = await fetch('save_fcm_token.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    token: JSON.stringify(subscription),
                    user_id: <?php echo $_SESSION['user_id']; ?>
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                addLog(`✅ Token saved successfully (${result.action}, total tokens: ${result.token_count})`, 'success');
            } else {
                addLog(`❌ Failed to save token: ${result.message}`, 'error');
            }
        } catch (error) {
            addLog(`❌ Error saving token: ${error.message}`, 'error');
        }
    }
    
    async function testPush() {
        try {
            addLog('🔄 Sending test push notification with ring sound...', 'info');
            updateStatus('Sending test push...', 'info');
            
            const response = await fetch('test_push.php');
            const result = await response.json();
            
            if (result.success) {
                addLog('✅ Test push sent successfully! Check your device for notification with ring sound.', 'success');
                updateStatus('Test push sent successfully', 'success');
            } else {
                addLog(`❌ Test push failed: ${result.message}`, 'error');
                updateStatus(`Test push failed: ${result.message}`, 'error');
            }
        } catch (error) {
            addLog(`❌ Error sending test: ${error.message}`, 'error');
            updateStatus(`Test failed: ${error.message}`, 'error');
        }
    }
    
    async function checkTokens() {
        try {
            addLog('🔄 Checking registered tokens...', 'info');
            
            const response = await fetch('debug_tokens.php');
            const text = await response.text();
            
            addLog('📋 Token check completed - check browser console for details', 'info');
            console.log('Token check result:', text);
            updateStatus('Token check completed - see console', 'info');
        } catch (error) {
            addLog(`❌ Error checking tokens: ${error.message}`, 'error');
        }
    }
    
    // Auto-init after page load
    setTimeout(() => {
        addLog('Page loaded. Click "Initialize FCM" to start.', 'info');
    }, 1000);
    </script>
</body>
</html>
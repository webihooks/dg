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
    <title>Push + Ring Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        button { padding: 15px 25px; margin: 10px; font-size: 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .push-btn { background: #007bff; color: white; }
        .push-btn:hover { background: #0056b3; }
        .log { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 8px; border: 1px solid #e9ecef; height: 200px; overflow-y: auto; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .status { padding: 15px; margin: 15px 0; border-radius: 8px; font-weight: bold; }
        .instructions { background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Push Notification + Ring Sound Test</h1>
        <p>Test push notifications with built-in ring sound that works when app is closed or in background.</p>
        
        <div class="status" id="status">Ready to test push notifications...</div>
        
        <div>
            <button class="push-btn" onclick="testPushWithRing()">Test Push + Ring Sound</button>
            <button onclick="testMultiple()">Test Multiple Notifications</button>
        </div>
        
        <h3>Test Log:</h3>
        <div id="log" class="log"></div>
        
        <div class="instructions">
            <h4>📱 Testing Instructions:</h4>
            <ol>
                <li>Click "Test Push + Ring Sound" to send notification</li>
                <li><strong>Minimize browser or lock phone</strong> to test background behavior</li>
                <li>Notification should appear with <strong>ring sound</strong> even when app is closed</li>
                <li>Click <strong>"View Order"</strong> in notification to open orders.php</li>
                <li>Ring sound is built into the push notification - no separate audio needed</li>
            </ol>
            
            <h4>🎯 Expected Behavior:</h4>
            <ul>
                <li>✅ Push notification appears with ring sound</li>
                <li>✅ Works when app is closed/in background</li>
                <li>✅ Continuous ring sound plays with notification</li>
                <li>✅ "View Order" opens orders.php page</li>
                <li>✅ Vibration pattern for attention</li>
            </ul>
        </div>
    </div>

    <script>
    const log = document.getElementById('log');
    const status = document.getElementById('status');

    function addLog(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const messageDiv = document.createElement('div');
        messageDiv.className = type;
        messageDiv.innerHTML = `[${timestamp}] ${message}`;
        log.appendChild(messageDiv);
        log.scrollTop = log.scrollHeight;
        console.log(`[${type.toUpperCase()}] ${message}`);
    }

    function updateStatus(message, type = 'info') {
        status.textContent = message;
        status.className = `status ${type}`;
        status.style.backgroundColor = type === 'success' ? '#d4edda' : 
                                     type === 'error' ? '#f8d7da' : 
                                     type === 'warning' ? '#fff3cd' : '#d1ecf1';
        status.style.color = type === 'success' ? '#155724' : 
                           type === 'error' ? '#721c24' : 
                           type === 'warning' ? '#856404' : '#0c5460';
    }

    async function testPushWithRing() {
        addLog('🚀 Testing push notification with ring sound...', 'info');
        updateStatus('Sending push notification with ring...', 'info');
        
        try {
            const response = await fetch('test_web_push_clean.php');
            const result = await response.json();
            
            if (result.success) {
                addLog('✅ Push notification sent with ring sound!', 'success');
                addLog('📱 Check your device for notification with ring', 'success');
                addLog('🎯 Click "View Order" in notification to test navigation', 'info');
                updateStatus('Push sent! Check device for ring notification.', 'success');
                
                // Additional instructions
                setTimeout(() => {
                    addLog('💡 Tip: Minimize browser to test background behavior', 'info');
                }, 2000);
            } else {
                addLog('❌ Push test failed: ' + result.message, 'error');
                updateStatus('Push test failed: ' + result.message, 'error');
            }
        } catch (error) {
            addLog('❌ Push test error: ' + error.message, 'error');
            updateStatus('Push test error: ' + error.message, 'error');
        }
    }

    async function testMultiple() {
        addLog('🔄 Testing multiple push notifications...', 'info');
        
        for (let i = 1; i <= 3; i++) {
            addLog(`Sending notification ${i}/3...`, 'info');
            
            try {
                const response = await fetch('test_web_push_clean.php');
                const result = await response.json();
                
                if (result.success) {
                    addLog(`✅ Notification ${i} sent successfully`, 'success');
                } else {
                    addLog(`❌ Notification ${i} failed`, 'error');
                }
                
                // Wait 2 seconds between notifications
                if (i < 3) {
                    await new Promise(resolve => setTimeout(resolve, 2000));
                }
            } catch (error) {
                addLog(`❌ Notification ${i} error: ` + error.message, 'error');
            }
        }
        
        addLog('🎉 Multiple notification test completed', 'success');
        updateStatus('Multiple notifications test completed', 'success');
    }

    // Register service worker if not already registered
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/fcm-sw.js')
            .then(registration => {
                addLog('✅ Service Worker registered successfully', 'success');
            })
            .catch(error => {
                addLog('❌ Service Worker registration failed: ' + error.message, 'error');
            });
    }

    addLog('Push notification test page loaded.', 'info');
    addLog('Ring sound is built into push notifications.', 'info');
    addLog('Notifications work even when app is closed/in background.', 'info');
    </script>
</body>
</html>
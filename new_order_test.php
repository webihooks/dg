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
    <title>New Order Push Notification Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        button { padding: 15px 30px; margin: 10px; font-size: 16px; border: none; border-radius: 5px; cursor: pointer; }
        .test-btn { background: #28a745; color: white; }
        .test-btn:hover { background: #218838; }
        .info-btn { background: #17a2b8; color: white; }
        .info-btn:hover { background: #138496; }
        .log { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; border: 1px solid #dee2e6; height: 300px; overflow-y: auto; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; background: #e9ecef; }
    </style>
</head>
<body>
    <h1>🔔 New Order Push Notification Test</h1>
    <p>This will simulate a new order and send a push notification with ring sound to your device.</p>
    
    <div class="status" id="status">Ready to test new order notifications...</div>
    
    <div>
        <button class="test-btn" onclick="testNewOrder()">🧪 Test New Order Notification</button>
        <button class="info-btn" onclick="checkSubscriptions()">📋 Check Subscriptions</button>
        <button onclick="clearLog()">🗑️ Clear Log</button>
    </div>
    
    <h3>What to expect in the notification:</h3>
    <ul>
        <li>✅ <strong>Title:</strong> "🔔 New Order Received!"</li>
        <li>✅ <strong>Body:</strong> Customer Name, Address, and Order Amount</li>
        <li>✅ <strong>Ring Sound:</strong> new_order.mp3 will play</li>
        <li>✅ <strong>Actions:</strong> "View Order" and "Dismiss" buttons</li>
        <li>✅ <strong>Click Behavior:</strong> Opens orders.php page in app</li>
        <li>✅ <strong>Works in background:</strong> Even when browser is closed</li>
    </ul>
    
    <h3>Test Log:</h3>
    <div id="log" class="log"></div>

    <script>
    const log = document.getElementById('log');
    const status = document.getElementById('status');
    
    function addLog(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        log.innerHTML += `<div class="${type}">[${timestamp}] ${message}</div>`;
        log.scrollTop = log.scrollHeight;
        console.log(`[${type.toUpperCase()}] ${message}`);
    }
    
    function updateStatus(message, type = 'info') {
        status.textContent = message;
        status.className = `status ${type}`;
        status.style.backgroundColor = type === 'success' ? '#d4edda' : 
                                     type === 'error' ? '#f8d7da' : '#d1ecf1';
        status.style.color = type === 'success' ? '#155724' : 
                           type === 'error' ? '#721c24' : '#0c5460';
    }
    
    function clearLog() {
        log.innerHTML = '';
        updateStatus('Log cleared', 'info');
    }
    
    async function testNewOrder() {
        addLog('🚀 Starting new order simulation...', 'info');
        updateStatus('Simulating new order and sending push notification...', 'info');
        
        try {
            const response = await fetch('test_new_order.php');
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response as text first to handle potential HTML errors
            const responseText = await response.text();
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                addLog('❌ Server returned non-JSON response:', 'error');
                addLog('Response: ' + responseText.substring(0, 200), 'error');
                updateStatus('Server error - check console for details', 'error');
                return;
            }
            
            if (result.success) {
                addLog('✅ New order simulation successful!', 'success');
                addLog('📱 Check your device for the push notification with ring sound', 'success');
                addLog('📋 Order Details:', 'info');
                addLog(`   - Order ID: ${result.order_details.order_id}`, 'info');
                addLog(`   - Customer: ${result.order_details.customer_name}`, 'info');
                addLog(`   - Address: ${result.order_details.address}`, 'info');
                addLog(`   - Amount: ${result.order_details.amount}`, 'info');
                addLog(`   - Type: ${result.order_details.type}`, 'info');
                updateStatus('New order simulation successful! Check your device.', 'success');
                
                // Also trigger a regular browser notification for testing
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('🔔 Test Notification', {
                        body: 'This is a test browser notification',
                        icon: '/assets/images/logo-sm.png',
                        requireInteraction: true
                    });
                }
            } else {
                addLog('❌ New order simulation failed: ' + result.message, 'error');
                updateStatus('New order simulation failed: ' + result.message, 'error');
            }
        } catch (error) {
            addLog('❌ New order simulation error: ' + error.message, 'error');
            updateStatus('New order simulation error: ' + error.message, 'error');
        }
    }
    
    async function checkSubscriptions() {
        addLog('📋 Checking subscriptions...', 'info');
        try {
            const response = await fetch('debug_tokens.php');
            const text = await response.text();
            addLog('Subscriptions checked - see browser console for details', 'info');
            console.log('Subscriptions:', text);
            updateStatus('Subscriptions checked - see console', 'info');
        } catch (error) {
            addLog('❌ Check Error: ' + error.message, 'error');
        }
    }
    
    addLog('New order push notification test page loaded.', 'info');
    addLog('Make sure you have allowed notifications for this site.', 'info');
    addLog('Click "Test New Order Notification" to simulate a real order.', 'info');
    </script>
</body>
</html>
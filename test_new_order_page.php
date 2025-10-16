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
    <title>Test New Order Notification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        button { padding: 15px 30px; margin: 10px; font-size: 16px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #218838; }
        button:disabled { background: #6c757d; cursor: not-allowed; }
        .log { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; border: 1px solid #dee2e6; height: 300px; overflow-y: auto; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        .order-details { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔔 Test New Order Notification</h1>
    <p>This will simulate a new order with ring sound and all order details.</p>
    
    <div>
        <button onclick="testNewOrder()" id="testBtn">Test New Order Notification</button>
        <button onclick="clearLog()">Clear Log</button>
    </div>
    
    <div id="status">Ready to test...</div>
    
    <h3>Test Log:</h3>
    <div id="log" class="log"></div>
    
    <div id="orderDetails" class="order-details" style="display: none;">
        <h4>Test Order Details:</h4>
        <div id="detailsContent"></div>
    </div>

    <script>
    const log = document.getElementById('log');
    const status = document.getElementById('status');
    const testBtn = document.getElementById('testBtn');
    const orderDetails = document.getElementById('orderDetails');
    const detailsContent = document.getElementById('detailsContent');
    
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
        status.className = type;
    }
    
    function clearLog() {
        log.innerHTML = '';
        updateStatus('Log cleared', 'info');
        orderDetails.style.display = 'none';
    }
    
    function showOrderDetails(details) {
        detailsContent.innerHTML = `
            <p><strong>Order ID:</strong> ${details.order_id}</p>
            <p><strong>Customer Name:</strong> ${details.customer_name}</p>
            <p><strong>Customer Address:</strong> ${details.customer_address}</p>
            <p><strong>Total Amount:</strong> ₹${details.total_amount}</p>
            <p><strong>Order Type:</strong> ${details.order_type}</p>
        `;
        orderDetails.style.display = 'block';
    }
    
    async function testNewOrder() {
        testBtn.disabled = true;
        testBtn.textContent = 'Sending...';
        
        addLog('🚀 Testing new order notification...', 'info');
        updateStatus('Sending new order notification...', 'info');
        
        try {
            const response = await fetch('test_new_order.php');
            const result = await response.json();
            
            if (result.success) {
                addLog('✅ New order notification sent successfully!', 'success');
                addLog('📱 Check your device for notification with ring sound', 'success');
                addLog('🔔 Notification should show: Customer name, address, and total amount', 'success');
                updateStatus('New order test successful! Check your device.', 'success');
                
                if (result.order_details) {
                    showOrderDetails(result.order_details);
                }
            } else {
                addLog('❌ New order test failed: ' + result.message, 'error');
                updateStatus('New order test failed: ' + result.message, 'error');
            }
        } catch (error) {
            addLog('❌ New order test error: ' + error.message, 'error');
            updateStatus('New order test error: ' + error.message, 'error');
        } finally {
            testBtn.disabled = false;
            testBtn.textContent = 'Test New Order Notification';
        }
    }
    
    addLog('New order test page loaded successfully.', 'info');
    addLog('Click the button above to test new order notifications.', 'info');
    </script>
</body>
</html>
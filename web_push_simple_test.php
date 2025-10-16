<script>
async function testWebPush() {
    testBtn.disabled = true;
    testBtn.textContent = 'Sending...';
    
    addLog('🚀 Starting web push test...', 'info');
    updateStatus('Sending web push notification...', 'info');
    
    try {
        // Use the clean version that handles headers properly
        const response = await fetch('test_web_push_clean.php');
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to debug
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        // Try to parse as JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error(`Invalid JSON response: ${responseText.substring(0, 100)}`);
        }
        
        if (result.success) {
            addLog('✅ Web push notification sent successfully!', 'success');
            addLog('📱 Check your device for the notification with ring sound', 'success');
            updateStatus('Web push test successful! Check your device.', 'success');
        } else {
            addLog('❌ Web push test failed: ' + result.message, 'error');
            updateStatus('Web push test failed: ' + result.message, 'error');
        }
    } catch (error) {
        addLog('❌ Web push test error: ' + error.message, 'error');
        updateStatus('Web push test error: ' + error.message, 'error');
    } finally {
        testBtn.disabled = false;
        testBtn.textContent = 'Test Web Push Notification';
    }
}
</script>
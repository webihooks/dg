<?php
require 'db_connection.php';
$result = $conn->query("SELECT token FROM fcm_tokens WHERE is_active = 1");
?>
<!DOCTYPE html>
<html>
<head>
    <title>FCM Tokens</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .token { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        textarea { width: 100%; height: 100px; font-family: monospace; }
        .copy-btn { background: #007bff; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>FCM Tokens for Testing</h2>
    
    <?php if ($result->num_rows > 0): ?>
        <p>Found <?php echo $result->num_rows; ?> active token(s). Copy any token below:</p>
        
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="token">
                <textarea id="token-<?php echo $result->num_rows; ?>"><?php echo htmlspecialchars($row['token']); ?></textarea>
                <button class="copy-btn" onclick="copyToken('token-<?php echo $result->num_rows; ?>')">Copy Token</button>
            </div>
        <?php endwhile; ?>
        
        <p><strong>Instructions:</strong></p>
        <ol>
            <li>Click "Copy Token" for any token above</li>
            <li>Go back to Firebase Console</li>
            <li>Paste the token in "Test on device" section</li>
            <li>Send test notification</li>
        </ol>
    <?php else: ?>
        <div style="background: #ffeaa7; padding: 15px; border-radius: 5px;">
            <h3>No FCM Tokens Found</h3>
            <p>To generate an FCM token:</p>
            <ol>
                <li>Open your DGCard Android app</li>
                <li>Login to the dashboard</li>
                <li>Allow notifications when prompted</li>
                <li>Wait 30 seconds</li>
                <li>Refresh this page</li>
            </ol>
            <p>The token will be automatically saved when you allow notifications.</p>
        </div>
    <?php endif; ?>

    <script>
    function copyToken(elementId) {
        var copyText = document.getElementById(elementId);
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        alert("Token copied to clipboard!");
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>

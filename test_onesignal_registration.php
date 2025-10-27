<?php
session_start();
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Manual registration test
if (isset($_POST['test_register'])) {
    $playerId = $_POST['player_id'] ?? 'test-player-' . time();
    $userId = $_POST['user_id'] ?? $_SESSION['user_id'];
    
    $response = manualDeviceRegistration($conn, $playerId, $userId);
    echo "<div class='alert alert-" . ($response['success'] ? 'success' : 'danger') . "'>";
    echo "<strong>Manual Test Result:</strong> " . $response['message'];
    echo "</div>";
}

function manualDeviceRegistration($conn, $playerId, $userId) {
    try {
        // Check if device exists
        $checkStmt = $conn->prepare("SELECT id FROM user_devices WHERE player_id = ? AND user_id = ?");
        $checkStmt->execute([$playerId, $userId]);
        
        if ($checkStmt->fetch()) {
            // Update
            $stmt = $conn->prepare("UPDATE user_devices SET updated_at = NOW() WHERE player_id = ? AND user_id = ?");
            $stmt->execute([$playerId, $userId]);
            return ['success' => true, 'message' => "Device updated successfully"];
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO user_devices (user_id, player_id, device_type, platform, source, is_active, created_at, updated_at) VALUES (?, ?, 'manual_test', 'web', 'test_script', 1, NOW(), NOW())");
            $stmt->execute([$userId, $playerId]);
            return ['success' => true, 'message' => "Device registered successfully. ID: " . $conn->lastInsertId()];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Error: " . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>OneSignal Registration Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h1>OneSignal Registration Debug</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Current Status</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'Not logged in') . "</p>";
                        echo "<p><strong>Session Status:</strong> " . (isset($_SESSION['user_id']) ? 'Active' : 'Inactive') . "</p>";
                        
                        // Check user_devices table structure
                        try {
                            $stmt = $conn->query("DESCRIBE user_devices");
                            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            echo "<p><strong>Table Columns:</strong> " . implode(', ', $columns) . "</p>";
                        } catch (Exception $e) {
                            echo "<p><strong>Table Error:</strong> " . $e->getMessage() . "</p>";
                        }
                        
                        // Count existing devices
                        if (isset($_SESSION['user_id'])) {
                            $stmt = $conn->prepare("SELECT COUNT(*) as device_count FROM user_devices WHERE user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $count = $stmt->fetch(PDO::FETCH_ASSOC);
                            echo "<p><strong>Registered Devices:</strong> " . $count['device_count'] . "</p>";
                            
                            // Show existing devices
                            $stmt = $conn->prepare("SELECT * FROM user_devices WHERE user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if ($devices) {
                                echo "<h6>Existing Devices:</h6>";
                                foreach ($devices as $device) {
                                    echo "<div class='border p-2 mb-2'>";
                                    echo "ID: {$device['id']}, Player: {$device['player_id']}, Type: {$device['device_type']}";
                                    echo "</div>";
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Manual Registration Test</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Player ID</label>
                                <input type="text" name="player_id" class="form-control" value="test-player-<?php echo time(); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">User ID</label>
                                <input type="number" name="user_id" class="form-control" value="<?php echo $_SESSION['user_id'] ?? ''; ?>" required>
                            </div>
                            <button type="submit" name="test_register" class="btn btn-primary">Test Registration</button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>JavaScript Test</h5>
                    </div>
                    <div class="card-body">
                        <button onclick="testJavaScriptRegistration()" class="btn btn-success">Test JS Registration</button>
                        <button onclick="checkWebToNative()" class="btn btn-info">Check WebToNative</button>
                        <div id="js-result" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5>Environment Detection</h5>
            </div>
            <div class="card-body">
                <div id="env-info"></div>
            </div>
        </div>
    </div>

    <script>
    function testJavaScriptRegistration() {
        const resultDiv = document.getElementById('js-result');
        resultDiv.innerHTML = '<div class="alert alert-info">Testing registration...</div>';
        
        const testData = {
            player_id: 'js-test-player-' + Date.now(),
            user_id: <?php echo $_SESSION['user_id'] ?? 'null'; ?>,
            device_type: 'web_browser',
            platform: 'web',
            source: 'test_script'
        };
        
        console.log('Sending test registration:', testData);
        
        fetch('https://dgcard.online/register_device_unified.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(testData)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Registration response:', data);
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success">✅ Registration successful! ' + data.message + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">❌ Registration failed: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Registration error:', error);
            resultDiv.innerHTML = '<div class="alert alert-danger">❌ Request failed: ' + error.message + '</div>';
        });
    }
    
    function checkWebToNative() {
        const envDiv = document.getElementById('env-info');
        let html = '<h6>Environment Check:</h6>';
        
        // Check WebToNative
        if (typeof WTN !== 'undefined') {
            html += '<p>✅ WebToNative SDK loaded</p>';
            if (WTN.OneSignal) {
                html += '<p>✅ WebToNative OneSignal available</p>';
                
                // Try to get player ID
                WTN.OneSignal.getPlayerId().then(playerId => {
                    html += '<p>✅ Player ID: ' + (playerId || 'No ID') + '</p>';
                    envDiv.innerHTML = html;
                }).catch(error => {
                    html += '<p>❌ Player ID error: ' + error + '</p>';
                    envDiv.innerHTML = html;
                });
            } else {
                html += '<p>❌ WebToNative OneSignal NOT available</p>';
            }
        } else {
            html += '<p>❌ WebToNative SDK NOT loaded</p>';
        }
        
        // Check regular OneSignal
        if (typeof OneSignal !== 'undefined') {
            html += '<p>✅ Regular OneSignal available</p>';
        } else {
            html += '<p>❌ Regular OneSignal NOT available</p>';
        }
        
        envDiv.innerHTML = html;
    }
    
    // Auto-check environment
    setTimeout(checkWebToNative, 1000);
    </script>
</body>
</html>
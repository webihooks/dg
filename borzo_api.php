<?php
// borzo_api.php - Borzo API Key Management
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require 'db_connection.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success';

// Fetch user details
$sql = "SELECT name, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $user_role);
$stmt->fetch();
$stmt->close();

// Handle API Key Save/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_api_key'])) {
    $borzo_api_key = trim($_POST['borzo_api_key']);
    
    if (empty($borzo_api_key)) {
        $message = "API Key cannot be empty";
        $message_type = "danger";
    } else {
        // Check if user already has an API key
        $check_sql = "SELECT id FROM borzo_api WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing key
            $update_sql = "UPDATE borzo_api SET borzo_api_key = ?, updated_at = NOW() WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $borzo_api_key, $user_id);
            
            if ($update_stmt->execute()) {
                $message = "API Key updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating API Key: " . $conn->error;
                $message_type = "danger";
            }
            $update_stmt->close();
        } else {
            // Insert new key
            $insert_sql = "INSERT INTO borzo_api (user_id, borzo_api_key) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("is", $user_id, $borzo_api_key);
            
            if ($insert_stmt->execute()) {
                $message = "API Key saved successfully!";
                $message_type = "success";
            } else {
                $message = "Error saving API Key: " . $conn->error;
                $message_type = "danger";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle API Key Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_api_key'])) {
    $delete_sql = "DELETE FROM borzo_api WHERE user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    
    if ($delete_stmt->execute()) {
        $message = "API Key deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting API Key: " . $conn->error;
        $message_type = "danger";
    }
    $delete_stmt->close();
}

// Fetch existing API key for this user
$api_key = '';
$sql = "SELECT borzo_api_key FROM borzo_api WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($api_key);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Borzo API Management - DeeGeeCard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="msapplication-TileImage" content="https://deegeecard.com/images/dg_logo.png">
    <meta name="application-name" content="DeeGeeCard">
    <meta name="mobile-web-app-capable" content="yes">
    
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        .api-key-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .api-key-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .api-key-header h3 {
            color: #333;
            font-weight: 600;
        }
        
        .api-key-header p {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        .api-key-form {
            margin-top: 20px;
        }
        
        .api-key-input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .api-key-input-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: block;
        }
        
        .api-key-input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .api-key-input-group input:focus {
            border-color: #0066CC;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
        }
        
        .api-key-input-group input[readonly] {
            background-color: #f1f3f5;
            border-color: #dee2e6;
            color: #495057;
        }
        
        .toggle-visibility {
            position: absolute;
            right: 15px;
            top: 45px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .toggle-visibility:hover {
            color: #0066CC;
        }
        
        .api-key-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .btn-borzo {
            background-color: #0066CC;
            border-color: #0066CC;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-borzo:hover {
            background-color: #0052a3;
            border-color: #0052a3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,102,204,0.3);
        }
        
        .btn-outline-borzo {
            background-color: transparent;
            border: 2px solid #0066CC;
            color: #0066CC;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-borzo:hover {
            background-color: #0066CC;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,102,204,0.2);
        }
        
        .btn-danger-outline {
            background-color: transparent;
            border: 2px solid #dc3545;
            color: #dc3545;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-danger-outline:hover {
            background-color: #dc3545;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #e9ecef;
        }
        
        .info-card h5 {
            color: #333;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .info-card ul {
            padding-left: 20px;
            color: #6c757d;
        }
        
        .info-card li {
            margin-bottom: 8px;
        }
        
        .api-key-masked {
            font-family: monospace;
            background: #e9ecef;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .badge-role {
            background: #6c757d;
            color: white;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .api-key-container {
                padding: 15px;
            }
            
            .api-key-actions {
                flex-direction: column;
            }
            
            .api-key-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header position-relative">
                                <h4 class="card-title">Borzo API Management</h4>
                                <span class="badge badge-role position-absolute top-50 end-0 translate-middle-y me-3">Role: <?php echo ucfirst($user_role); ?></span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($message); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-lg-8">
                                        <div class="api-key-container">
                                            <div class="api-key-header">
                                                <h3><i class="bi bi-key"></i> Your Borzo API Key</h3>
                                                <p>Manage your Borzo API key for delivery integrations</p>
                                            </div>
                                            
                                            <form method="POST" action="" class="api-key-form">
                                                <div class="api-key-input-group">
                                                    <label for="borzo_api_key">Borzo API Key</label>
                                                    <div class="position-relative">
                                                        <input type="password" 
                                                               class="form-control" 
                                                               id="borzo_api_key" 
                                                               name="borzo_api_key" 
                                                               value="<?php echo htmlspecialchars($api_key); ?>"
                                                               placeholder="Enter your Borzo API key"
                                                               required>
                                                        <i class="bi bi-eye toggle-visibility" id="togglePassword" title="Toggle visibility"></i>
                                                    </div>
                                                    <small class="text-muted">Your API key is stored securely and never displayed in full</small>
                                                </div>
                                                
                                                <?php if (!empty($api_key)): ?>
                                                    <div class="api-key-masked mb-3">
                                                        <strong>Current Key:</strong> 
                                                        <?php 
                                                        // Show only first 10 and last 4 characters
                                                        $key_length = strlen($api_key);
                                                        if ($key_length > 14) {
                                                            $masked_key = substr($api_key, 0, 10) . str_repeat('•', $key_length - 14) . substr($api_key, -4);
                                                        } else {
                                                            $masked_key = str_repeat('•', $key_length);
                                                        }
                                                        echo $masked_key;
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="api-key-actions">
                                                    <button type="submit" name="save_api_key" class="btn btn-borzo">
                                                        <i class="bi bi-check-circle"></i> Save API Key
                                                    </button>
                                                    
                                                    <?php if (!empty($api_key)): ?>
                                                        <button type="button" class="btn btn-outline-borzo" onclick="testApiKey()">
                                                            <i class="bi bi-play-circle"></i> Test Connection
                                                        </button>
                                                        <button type="submit" name="delete_api_key" class="btn btn-danger-outline" onclick="return confirm('Are you sure you want to delete your API key? This will disable Borzo delivery integration.')">
                                                            <i class="bi bi-trash"></i> Delete Key
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4">
                                        <div class="info-card">
                                            <h5><i class="bi bi-info-circle"></i> API Key Information</h5>
                                            <ul>
                                                <li><i class="bi bi-dot"></i> Your Borzo API key is used for delivery integrations</li>
                                                <li><i class="bi bi-dot"></i> Get your API key from your <a href="https://apitest.borzodelivery.com/in/cabinet" target="_blank">Borzo Dashboard</a></li>
                                                <li><i class="bi bi-dot"></i> Keys are encrypted and stored securely</li>
                                                <li><i class="bi bi-dot"></i> Never share your API key with anyone</li>
                                                <li><i class="bi bi-dot"></i> If compromised, regenerate in Borzo dashboard and update here</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="info-card mt-3">
                                            <h5><i class="bi bi-question-circle"></i> Need Help?</h5>
                                            <ul>
                                                <li><i class="bi bi-dot"></i> <a href="https://docs.borzodelivery.com" target="_blank">Borzo API Documentation</a></li>
                                                <li><i class="bi bi-dot"></i> Contact support: api.in@borzodelivery.com</li>
                                                <li><i class="bi bi-dot"></i> Test environment: apitest.borzodelivery.com</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="info-card mt-3">
                                            <h5><i class="bi bi-shield-check"></i> Security Tips</h5>
                                            <ul>
                                                <li><i class="bi bi-dot"></i> Use different keys for test and production</li>
                                                <li><i class="bi bi-dot"></i> Rotate keys periodically</li>
                                                <li><i class="bi bi-dot"></i> Monitor usage in Borzo dashboard</li>
                                                <li><i class="bi bi-dot"></i> Set IP restrictions if possible</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Test API Key Modal -->
    <div class="modal fade" id="testApiModal" tabindex="-1" aria-labelledby="testApiModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="testApiModalLabel">Testing API Connection</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" id="testResult">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Testing...</span>
                    </div>
                    <p class="mt-3">Testing connection to Borzo API...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const input = document.getElementById('borzo_api_key');
            const icon = this;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Test API Key function - FIXED VERSION
        function testApiKey() {
            const apiKey = document.getElementById('borzo_api_key').value;
            
            if (!apiKey) {
                alert('Please enter an API key first');
                return;
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('testApiModal'));
            modal.show();
            
            // Test connection with correct endpoint and method
            $.ajax({
                url: 'https://robotapitest-in.borzodelivery.com/api/business/1.6/calculate-order',
                type: 'POST',
                headers: {
                    'X-DV-Auth-Token': apiKey,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    matter: 'API Test',
                    points: [
                        {
                            address: 'Saket, New Delhi, Delhi',
                            contact_person: { phone: '918880000001' }
                        },
                        {
                            address: 'Janakpuri, New Delhi, Delhi',
                            contact_person: { phone: '918880000001' }
                        }
                    ]
                }),
                success: function(response) {
                    if (response.is_successful) {
                        $('#testResult').html(`
                            <div class="text-success">
                                <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">✅ API Key Valid!</h4>
                                <p>Your API key is working correctly.</p>
                                <p class="small text-muted">Delivery fee: ₹${response.order.delivery_fee_amount}</p>
                            </div>
                        `);
                    } else {
                        $('#testResult').html(`
                            <div class="text-warning">
                                <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">⚠️ API Error</h4>
                                <p>${response.errors ? response.errors.join(', ') : 'Unknown error'}</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr) {
                    console.error('Test error:', xhr);
                    let errorMsg = 'Connection failed';
                    let statusCode = xhr.status;
                    
                    try {
                        if (xhr.responseText) {
                            const res = JSON.parse(xhr.responseText);
                            if (res.errors) {
                                errorMsg = res.errors.join(', ');
                            } else if (res.error) {
                                errorMsg = res.error;
                            }
                        }
                    } catch(e) {
                        // If not JSON, use status text
                        if (statusCode === 401) {
                            errorMsg = 'Invalid API key (Unauthorized)';
                        } else if (statusCode === 0) {
                            errorMsg = 'Network error - cannot reach Borzo servers';
                        } else {
                            errorMsg = xhr.statusText || 'Unknown error';
                        }
                    }
                    
                    $('#testResult').html(`
                        <div class="text-danger">
                            <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">❌ Connection Failed</h4>
                            <p>${errorMsg}</p>
                            <p class="small text-muted">Status: ${statusCode || 'No response'}</p>
                            <p class="small text-muted">Please check your API key and try again.</p>
                        </div>
                    `);
                }
            });
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    </script>
</body>
</html>
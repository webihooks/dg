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
.api-key-container{background:#f8f9fa;border-radius:10px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,.05)}.api-key-header{border-bottom:2px solid #e9ecef;padding-bottom:15px;margin-bottom:20px}.api-key-header h3{color:#333;font-weight:600}.api-key-header p{color:#6c757d;margin-bottom:0}.api-key-form{margin-top:20px}.api-key-input-group{position:relative;margin-bottom:20px}.api-key-input-group label{font-weight:600;color:#495057;margin-bottom:8px;display:block}.api-key-input-group input{width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:8px;font-family:monospace;font-size:14px;transition:.3s}.api-key-input-group input:focus{border-color:#06c;outline:0;box-shadow:0 0 0 3px rgba(0,102,204,.1)}.api-key-input-group input[readonly]{background-color:#f1f3f5;border-color:#dee2e6;color:#495057}.toggle-visibility{position:absolute;right:15px;top:45px;cursor:pointer;color:#6c757d}.toggle-visibility:hover{color:#06c}.api-key-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:30px}.btn-borzo{background-color:#06c;border-color:#06c;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;transition:.3s}.btn-danger-outline,.btn-outline-borzo{background-color:transparent;padding:10px 20px;transition:.3s;font-weight:600}.btn-borzo:hover{background-color:#0052a3;border-color:#0052a3;transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,102,204,.3)}.btn-outline-borzo{border:2px solid #06c;color:#06c;border-radius:8px}.btn-outline-borzo:hover{background-color:#06c;color:#fff;transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,102,204,.2)}.btn-danger-outline{border:2px solid #dc3545;color:#dc3545;border-radius:8px}.btn-danger-outline:hover{background-color:#dc3545;color:#fff;transform:translateY(-2px);box-shadow:0 5px 15px rgba(220,53,69,.3)}.badge-role{background:#6c757d;color:#fff;padding:5px 12px;border-radius:50px;font-size:.8rem;font-weight:600}@media (max-width:768px){.api-key-container{padding:15px}.api-key-actions{flex-direction:column}.api-key-actions .btn{width:100%}}
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
                                    <div class="col-lg-12">
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
                                                        <button type="submit" name="delete_api_key" class="btn btn-danger-outline" onclick="return confirm('Are you sure you want to delete your API key? This will disable Borzo delivery integration.')">
                                                            <i class="bi bi-trash"></i> Delete Key
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
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

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    </script>
</body>
</html>
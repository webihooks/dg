<?php
// Start the session
session_start();

// Include the enhanced session manager
require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();

// Let the session manager handle Android session persistence
$sessionManager->validateAndroidSession();

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$current_profile_url = '';

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// Check if user is a sales person
if ($role !== 'sales_person') {
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['profile_url'])) {
        $profile_url = trim($_POST['profile_url']);
        
        // Basic validation
        if (empty($profile_url)) {
            $error_message = "Profile URL cannot be empty";
        } elseif (!preg_match('/^[a-zA-Z0-9-]+$/', $profile_url)) {
            $error_message = "Profile URL can only contain letters, numbers, and hyphens";
        } else {
            // Check if URL is available
            $check_sql = "SELECT user_id FROM profile_url_details WHERE profile_url = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $profile_url);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_user = $check_result->fetch_assoc();
                if ($existing_user['user_id'] != $user_id) {
                    $error_message = "This profile URL is already taken";
                }
            }
            $check_stmt->close();
            
            // If no errors, save the profile URL
            if (empty($error_message)) {
                // Check if user already has a profile URL
                $existing_sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
                $existing_stmt = $conn->prepare($existing_sql);
                $existing_stmt->bind_param("i", $user_id);
                $existing_stmt->execute();
                $existing_result = $existing_stmt->get_result();
                
                if ($existing_result->num_rows > 0) {
                    // Update existing record
                    $update_sql = "UPDATE profile_url_details SET profile_url = ?, updated_at = NOW() WHERE user_id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("si", $profile_url, $user_id);
                } else {
                    // Insert new record
                    $insert_sql = "INSERT INTO profile_url_details (user_id, profile_url, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("is", $user_id, $profile_url);
                }
                
                if ($stmt->execute()) {
                    $success_message = "Profile URL saved successfully!";
                    $current_profile_url = $profile_url;
                } else {
                    $error_message = "Error saving profile URL: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}


// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Sales Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <style>
        .session-status-android {
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: #28a745;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 10000;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .session-status-android.web {
            background: #17a2b8;
        }
    </style>
</head>

<body>

    <!-- Session Status Indicator -->
    <div class="session-status-android <?php echo $sessionManager->isAndroidApp() ? 'android' : 'web'; ?>" id="sessionStatusIndicator">
        <?php echo $sessionManager->isAndroidApp() ? '📱 Android App - Session Active' : '🌐 Web - Session Active'; ?>
    </div>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php 
        // Include the appropriate menu based on user role
        if ($role === 'admin') {
            include 'admin_menu.php';
        } elseif ($role === 'sales_person') {
            include 'sales_menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        
                        <!-- Session Info Alert for Android -->
                        <?php if ($sessionManager->isAndroidApp()): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-3">
                            <i class="fas fa-mobile-alt me-2"></i>
                            <strong>Android App Session:</strong> Your session is configured to remain active for 365 days. 
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Sales Dashboard</h4>
                                <?php if ($sessionManager->isAndroidApp()): ?>
                                <span class="badge bg-success float-end">Android App</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p>Welcome to the sales dashboard. Manage your sales activities from here.</p>
                                
                                <!-- Sales Quick Stats -->
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body text-center">
                                                <h5>Today's Sales</h5>
                                                <h3>₹0</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body text-center">
                                                <h5>Leads</h5>
                                                <h3>0</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body text-center">
                                                <h5>Conversions</h5>
                                                <h3>0%</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-warning text-dark">
                                            <div class="card-body text-center">
                                                <h5>Target</h5>
                                                <h3>₹0</h3>
                                            </div>
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
    $(document).ready(function() {
        // Session status indicator management
        function updateSessionStatus() {
            const indicator = $('#sessionStatusIndicator');
            if (indicator.length) {
                // Check session status every 30 seconds
                setInterval(() => {
                    $.get('session_health_check.php', function(data) {
                        if (data.status === 'success') {
                            console.log('Sales session active');
                        }
                    }).fail(() => {
                        indicator.removeClass('android web').addClass('warning');
                        indicator.text('⚠️ Connection Issue');
                    });
                }, 30000);
            }
        }

        // Android-specific session maintenance
        function androidSessionMaintenance() {
            // For Android apps, send periodic keep-alive requests
            if (navigator.userAgent.includes('WebToNative') || <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>) {
                setInterval(() => {
                    $.ajax({
                        url: 'heartbeat.php',
                        method: 'GET',
                        xhrFields: {
                            withCredentials: true
                        },
                        success: function(data) {
                            console.log('Sales Android session maintained');
                        }
                    });
                }, 300000); // Every 5 minutes for Android apps
            }
        }

        // Initialize session monitoring
        updateSessionStatus();
        androidSessionMaintenance();
    });
    </script>

</body>
</html>
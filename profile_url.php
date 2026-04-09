<?php
// Start the session
session_start();
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
$trial_notification = '';

// First, check if user has an active subscription
$subscription_sql = "SELECT status FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
$subscription_stmt = $conn->prepare($subscription_sql);
$subscription_stmt->bind_param("i", $user_id);
$subscription_stmt->execute();
$subscription_stmt->store_result();
$has_active_subscription = ($subscription_stmt->num_rows > 0);
$subscription_stmt->close();

// Get user details including trial information
$user_sql = "SELECT name, role, is_trial, trial_end FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $role, $is_trial, $trial_end);
$user_stmt->fetch();
$user_stmt->close();

// Check trial status and prepare notification only if user doesn't have active subscription
if (!$has_active_subscription && $is_trial) {
    $current_date = new DateTime();
    $trial_end_date = new DateTime($trial_end);
    $days_remaining = $current_date->diff($trial_end_date)->days;
    
    if ($current_date > $trial_end_date) {
        $trial_notification = '<div class="alert alert-danger">Your trial period has ended. <a href="subscription.php" class="alert-link">Subscribe now</a> to continue using our services.</div>';
    } else {
        $trial_notification = '<div class="alert alert-info">You have ' . $days_remaining . ' day(s) remaining in your free trial. <a href="subscription.php" class="alert-link">Subscribe now</a> for full access.</div>';
    }
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

// Check for AJAX availability request
if (isset($_GET['check_availability']) && isset($_GET['profile_url'])) {
    $profile_url = trim($_GET['profile_url']);
    $response = ['available' => true];
    
    if (!empty($profile_url)) {
        $check_sql = "SELECT user_id FROM profile_url_details WHERE profile_url = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $profile_url);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_user = $check_result->fetch_assoc();
            $response['available'] = ($existing_user['user_id'] == $user_id);
        }
        $check_stmt->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get current profile URL if exists
$get_sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
$get_stmt = $conn->prepare($get_sql);
$get_stmt->bind_param("i", $user_id);
$get_stmt->execute();
$get_result = $get_stmt->get_result();

if ($get_result->num_rows > 0) {
    $row = $get_result->fetch_assoc();
    $current_profile_url = $row['profile_url'];
}
$get_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Profile URL</title>
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
    <!-- PWA Meta Tags -->
    
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <script src="https://cdn.webtonative.com/scripts/webtonative.min.js"></script>
    <style>
        /* Mobile-first approach */
        @media (max-width: 767.98px) {
            .mb-3 {
                margin-bottom: 1rem !important;
            }
            
            .input-group {
                flex-direction: column;
            }
            
            .input-group-text {
                border-radius: 0.25rem 0.25rem 0 0 !important;
                padding: 0.375rem 0.75rem;
                border-bottom: 0;
                text-align: center;
                width: 100%;
            }
            
            .form-control {
                border-radius: 0 !important;
                width: 100% !important;
            }
            
            #checkAvailability {
                border-radius: 0 0 0.25rem 0.25rem !important;
                width: 100%;
                margin-top: -1px; /* Removes double border */
                padding: 0.5rem;
            }
            
            #availabilityMessage {
                font-size: 0.875rem;
            }
            
            .text-muted {
                font-size: 0.75rem;
            }
        }

        /* Optional: Adjustments for very small screens */
        @media (max-width: 400px) {
            .input-group-text {
                font-size: 0.875rem;
                padding: 0.25rem 0.5rem;
            }
            
            .form-control, #checkAvailability {
                font-size: 0.875rem;
                padding: 0.375rem 0.5rem;
            }
        }
    </style>

    <script>
    // Detect if user is accessing from Android app
    function isAndroidApp() {
        const userAgent = navigator.userAgent || navigator.vendor || window.opera;
        
        // Common patterns for Android WebView
        return /android/i.test(userAgent) && 
               (/wv|webview/i.test(userAgent) || 
                !/chrome|firefox|safari|opera/i.test(userAgent));
    }

    // Apply styles based on detection
    if (isAndroidApp()) {
        document.documentElement.classList.add('android-app');
    }
    </script>

    <style>
        .desktop_view {
            display: block;
        }
        .mob_view {
            display: none;
        }

        /* Styles for Android app */
        .android-app .desktop_view {
            display: none;
        }
        .android-app .mob_view {
            display: block;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        // Updated menu inclusion logic to support vegetable seller role
        if ($role === 'admin') {
            include 'admin_menu.php';
        } elseif ($role === 'room') {
            // Show room management menu for room users
            include 'room_management_menu.php';
        } elseif ($role === 'vegetable_seller') {
            include 'vegetable_seller_menu.php';
        } else {
            // For regular users, check subscription status
            if ($has_active_subscription || ($is_trial && strtotime($trial_end) > time())) {
                include 'menu.php';
            } else {
                include 'unsubscriber_menu.php';
            }
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <!-- Display trial notification if user is on trial -->
                        <?php if (!empty($trial_notification)) echo $trial_notification; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Profile URL</h4>
                            </div>
                            
                            <div class="card-body">
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <form id="profileUrlForm" method="POST" action="">
                                    <div class="mb-3">
                                        <label for="profile_url" class="form-label">Your Profile URL</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo $_SERVER['HTTP_HOST']; ?>/</span>
                                            <input type="text" class="form-control" id="profile_url" name="profile_url" 
                                                   value="<?php echo htmlspecialchars($current_profile_url); ?>" 
                                                   required>
                                            <button type="button" class="btn btn-outline-secondary" id="checkAvailability">Check Availability</button>
                                        </div>
                                        <div id="availabilityMessage" class="mt-2"></div>
                                        <small class="text-muted">Example: yourname or your-business</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Save Profile URL</button>
                                </form>
                                
                                <?php if (!empty($current_profile_url)): ?>
                                <div class="mt-4 android-app desktop_view">
                                    <h5>Your current profile link:</h5>
                                    <a href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/' . $current_profile_url; ?>" target="_blank">
                                        <?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/' . $current_profile_url; ?>
                                    </a>
                                </div>

                                <div class="mt-4 android-app mob_view">
                                    <h5>Your current profile link:</h5>
                                    <?php
                                    $profile_link = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $current_profile_url;
                                    $external_link = $profile_link . '?loadIn=defaultBrowser';
                                    ?>
                                    <a href="<?php echo $external_link; ?>" 
                                       onclick="handleProfileLinkClick('<?php echo $profile_link; ?>'); return false;" 
                                       class="profile-external-link">
                                        <?php echo $profile_link; ?>
                                    </a>
                                </div>
                                <?php endif; ?>

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
            // Check availability button click handler
            $('#checkAvailability').click(function() {
                const profileUrl = $('#profile_url').val().trim();
                const availabilityMessage = $('#availabilityMessage');
                
                if (!profileUrl) {
                    availabilityMessage.html('<span class="text-danger">Please enter a profile URL</span>');
                    return;
                }
                
                // Check if the input matches the allowed pattern
                if (!/^[a-zA-Z0-9-]+$/.test(profileUrl)) {
                    availabilityMessage.html('<span class="text-danger">Only letters, numbers, and hyphens are allowed</span>');
                    return;
                }
                
                // Show loading
                availabilityMessage.html('<span class="text-info">Checking availability...</span>');
                
                // Make AJAX request
                $.get('?check_availability=1&profile_url=' + encodeURIComponent(profileUrl), function(response) {
                    if (response.available) {
                        availabilityMessage.html('<span class="text-success">This URL is available!</span>');
                    } else {
                        availabilityMessage.html('<span class="text-danger">This URL is already taken</span>');
                    }
                }).fail(function() {
                    availabilityMessage.html('<span class="text-danger">Error checking availability</span>');
                });
            });
            
            // Form validation
            $('#profileUrlForm').validate({
                rules: {
                    profile_url: {
                        required: true,
                        // Use string pattern instead of regex literal
                        pattern: "^[a-zA-Z0-9-]+$"
                    }
                },
                messages: {
                    profile_url: {
                        required: "Please enter your profile URL",
                        pattern: "Only letters, numbers, and hyphens are allowed"
                    }
                },
                errorElement: "div",
                errorPlacement: function(error, element) {
                    error.addClass("invalid-feedback");
                    error.insertAfter(element.parent());
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-invalid").removeClass("is-valid");
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-valid").removeClass("is-invalid");
                }
            });
            
            // Trigger check availability when user stops typing (after 1 second)
            let typingTimer;
            $('#profile_url').on('keyup', function() {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    if ($('#profile_url').valid()) {
                        $('#checkAvailability').trigger('click');
                    }
                }, 1000);
            });
            
            // Clear timer on keydown
            $('#profile_url').on('keydown', function() {
                clearTimeout(typingTimer);
            });
        });
    </script>
    
    <script>
        function handleProfileLinkClick(url) {
            // For Android with WTN support
            if (typeof WTN !== 'undefined' && WTN.openUrlInBrowser) {
                WTN.openUrlInBrowser(url);
            } else {
                // For iOS or fallback - use the href with loadIn parameter
                window.location.href = url + '?loadIn=defaultBrowser';
            }
        }
        
        // Ensure all external links work properly
        $(document).ready(function() {
            // Handle profile links with both methods
            $('a.profile-external-link').on('click', function(e) {
                e.preventDefault();
                const url = $(this).attr('href') ? $(this).attr('href').replace('?loadIn=defaultBrowser', '') : $(this).text().trim();
                handleProfileLinkClick(url);
            });
        });
    </script>
</body>
</html>
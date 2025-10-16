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

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

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
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php 
        // Include the appropriate menu based on user role
        if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                    <h4 class="card-title">Dashboard</h4>
                                </div>
                            <div class="card-body">
                                
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
    
    

</body>
</html>
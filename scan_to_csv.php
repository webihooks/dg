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
    <title>Scan Text to CSV | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <style>
/* Flexible height iframe container */
.iframe-container {
    width: 100%;
    height: 70vh; /* 70% of viewport height */
    min-height: 500px; /* Minimum height */
    max-height: 800px; /* Maximum height */
    overflow: hidden;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    background: #f8f9fa;
}

.iframe-container iframe {
    width: 100%;
    height: 100%;
    border: none;
}

@media (max-width: 992px) {
    .iframe-container {
        height: 60vh;
        min-height: 400px;
    }
}

@media (max-width: 768px) {
    .iframe-container {
        height: 65vh;
        min-height: 300px;
        border-radius: 0;
        margin-left: -15px;
        margin-right: -15px;
        width: calc(100% + 30px);
    }
}
    </style>
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
        <h4 class="card-title">Scan Text To CSV</h4>
    </div>
    <div class="card-body">
        <div class="iframe-container">
            <iframe src="https://gemini.google.com/share/dd000976a2ca" allowfullscreen></iframe>
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
    
    

</body>
</html>
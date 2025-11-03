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

// Fetch Users Details
$sql = "SELECT name, email, phone, address FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $phone, $address);
$stmt->fetch();
$stmt->close();

// Display success/error messages if they exist
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Password change messages
$password_success = '';
$password_error = '';
if (isset($_SESSION['password_success'])) {
    $password_success = $_SESSION['password_success'];
    unset($_SESSION['password_success']);
}
if (isset($_SESSION['password_error'])) {
    $password_error = $_SESSION['password_error'];
    unset($_SESSION['password_error']);
}

// Fetch Users Details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";  // Added role to SELECT
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);  // Added role to bind_result
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
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

                    <div class="col-xl-9">
                        <?php if ($success_msg): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success_msg; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($error_msg): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error_msg; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($password_success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $password_success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($password_error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $password_error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Profile</h4>
                            </div>
                            <div class="card-body">
                                <form id="profileForm" method="POST" action="update_profile.php">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input  type="text"  class="form-control"  id="phone"  name="phone"  value="<?php echo htmlspecialchars($phone); ?>"  maxlength="10"  pattern="\d{10}"  title="Please enter exactly 10 digits" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea  class="form-control"  id="address"  name="address"  required onkeydown="if(event.keyCode === 13) { return false; }"><?php echo htmlspecialchars($address); ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success">Update Profile</button>
                                </form>
                                
                                <hr class="my-4">
                                
                                <h5 class="mb-3">Change Password</h5>
                                <form id="passwordForm" method="POST" action="change_password.php">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
$(document).ready(function () {
    $("#profileForm").validate({
        rules: {
            name: {
                required: true,
                minlength: 3
            },
            email: {
                required: true,
                email: true
            },
            phone: {
                required: true,
                digits: true,
                minlength: 10,
                maxlength: 10
            }, // Added missing comma here
            address: {
                required: true,
                minlength: 5,
                noLineBreaks: true  // Custom rule to block line breaks
            }
        },
        messages: {
            name: {
                required: "Please enter your name.",
                minlength: "Your name must be at least 3 characters long."
            },
            email: {
                required: "Please enter your email.",
                email: "Please enter a valid email address."
            },
            phone: {
                required: "Please enter your phone number.",
                digits: "Phone number must contain only digits.",
                minlength: "Phone number must be exactly 10 digits.",
                maxlength: "Phone number must be exactly 10 digits."
            },
            address: {
                required: "Please enter your address.",
                minlength: "Address must be at least 5 characters long.",
                noLineBreaks: "Line breaks are not allowed in the address."
            }
        }
    });

    // Add custom method for exact length validation
    $.validator.addMethod("exactlength", function(value, element, param) {
        return this.optional(element) || value.length == param;
    }, $.validator.format("Please enter exactly {0} characters."));

    // Custom validation method to block line breaks - FIXED this function
    $.validator.addMethod("noLineBreaks", function(value, element) {
        return !/[\n\r]/.test(value); // Returns false if line breaks exist
    }); // Added missing closing brace and parenthesis here
            
    $("#passwordForm").validate({
        rules: {
            current_password: {
                required: true,
                minlength: 6
            },
            new_password: {
                required: true,
                minlength: 6
            },
            confirm_password: {
                required: true,
                minlength: 6,
                equalTo: "#new_password"
            }
        },
        messages: {
            current_password: {
                required: "Please enter your current password.",
                minlength: "Password must be at least 6 characters long."
            },
            new_password: {
                required: "Please enter a new password.",
                minlength: "Password must be at least 6 characters long."
            },
            confirm_password: {
                required: "Please confirm your new password.",
                minlength: "Password must be at least 6 characters long.",
                equalTo: "Passwords do not match."
            }
        }
    });
});
    </script>
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
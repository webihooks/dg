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

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Initialize social media links with empty values
$facebook = $instagram = $whatsapp = $linkedin = $youtube = $telegram = '';

// Fetch social media links if they exist
$sql = "SELECT facebook, instagram, whatsapp, linkedin, youtube, telegram FROM social_link WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($facebook, $instagram, $whatsapp, $linkedin, $youtube, $telegram);
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
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">


                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Social</h4>
                            </div>
                            <div class="card-body">

                                <!-- Display success/error messages -->
                                <?php if (isset($_SESSION['success_message'])): ?>
                                    <div class="alert alert-success">
                                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($_SESSION['error_message'])): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="update_social_links.php" id="socialForm">

    <div class="mb-3">
        <label for="facebook" class="form-label">Facebook</label>
        <input type="url" class="form-control" id="facebook" name="facebook" value="<?php echo htmlspecialchars($facebook); ?>" placeholder="https://facebook.com/username">
        <span style="margin-top: 5px; display:inline-block;">Note: Add URL eg. https://facebook.com/username</span>
    </div>

    <div class="mb-3">
        <label for="instagram" class="form-label">Instagram</label>
        <input type="url" class="form-control" id="instagram" name="instagram" value="<?php echo htmlspecialchars($instagram); ?>" placeholder="https://instagram.com/username">
        <span style="margin-top: 5px; display:inline-block;">Note: Add URL eg. https://instagram.com/username</span>
    </div>

    <div class="mb-3">
        <label for="whatsapp" class="form-label">WhatsApp</label>
        <input type="url" class="form-control" id="whatsapp" name="whatsapp" value="<?php echo htmlspecialchars($whatsapp); ?>" placeholder="https://wa.me/phone_number">
        <span style="margin-top: 5px; display:inline-block;">Note: Add URL eg. https://wa.me/91XXXXXXXXXX</span>
    </div>

    <div class="mb-3">
        <label for="linkedin" class="form-label">LinkedIn</label>
        <input type="url" class="form-control" id="linkedin" name="linkedin" value="<?php echo htmlspecialchars($linkedin); ?>" placeholder="https://linkedin.com/in/username">
        <span style="margin-top: 5px; display:inline-block;">Note: Add URL eg. https://linkedin.com/in/username</span>
    </div>

    <div class="mb-3">
        <label for="youtube" class="form-label">YouTube</label>
        <input type="url" class="form-control" id="youtube" name="youtube" value="<?php echo htmlspecialchars($youtube); ?>" placeholder="https://youtube.com/username">
        <span style="margin-top: 5px; display:inline-block;">Note: Add URL eg. https://youtube.com/username</span>
    </div>

    <div class="mb-3">
        <label for="telegram" class="form-label">Telegram</label>
        <input type="url" class="form-control" id="telegram" name="telegram" value="<?php echo htmlspecialchars($telegram); ?>" placeholder="https://t.me/username">
        <span style="margin-top: 5px; display:inline-block;">Note: Add URL eg. https://t.me/username</span>
    </div>

    <button type="submit" class="btn btn-success">Update Social</button>
</form>

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
            // Form validation
            $("#socialForm").validate({
                rules: {
                    facebook: {
                        url: true
                    },
                    instagram: {
                        url: true
                    },
                    whatsapp: {
                        url: true
                    },
                    linkedin: {
                        url: true
                    },
                    youtube: {
                        url: true
                    },
                    telegram: {
                        url: true
                    }
                },
                messages: {
                    facebook: {
                        url: "Please enter a valid URL (e.g., https://facebook.com/username)"
                    },
                    instagram: {
                        url: "Please enter a valid URL (e.g., https://instagram.com/username)"
                    },
                    whatsapp: {
                        url: "Please enter a valid URL (e.g., https://wa.me/1234567890)"
                    },
                    linkedin: {
                        url: "Please enter a valid URL (e.g., https://linkedin.com/in/username)"
                    },
                    youtube: {
                        url: "Please enter a valid URL (e.g., https://youtube.com/username)"
                    },
                    telegram: {
                        url: "Please enter a valid URL (e.g., https://t.me/username)"
                    }
                }
            });
        });
    </script>

</body>
</html>
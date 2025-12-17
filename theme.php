<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user name and role
$sql_name = "SELECT name, role FROM users WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
if ($stmt_name === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$stmt_name->bind_result($user_name, $user_role);
$stmt_name->fetch();
$stmt_name->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $primary_color = $_POST['primary_color'] ?? '';
    $secondary_color = $_POST['secondary_color'] ?? '';
    
    // Validate colors (simple hex color validation)
    if (!preg_match('/^#[a-f0-9]{6}$/i', $primary_color) || !preg_match('/^#[a-f0-9]{6}$/i', $secondary_color)) {
        $error = "Please enter valid hex color codes (e.g., #FFFFFF)";
    } else {
        // Check if theme exists for user
        $check_sql = "SELECT id FROM theme WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if ($check_stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        $theme_exists = $check_stmt->num_rows > 0;
        $check_stmt->close();
        
        if ($theme_exists) {
            // Update existing theme
            $sql = "UPDATE theme SET primary_color = ?, secondary_color = ? WHERE user_id = ?";
            $message = "Theme updated successfully!";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssi", $primary_color, $secondary_color, $user_id);
        } else {
            // Insert new theme
            $sql = "INSERT INTO theme (user_id, primary_color, secondary_color) VALUES (?, ?, ?)";
            $message = "Theme saved successfully!";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("iss", $user_id, $primary_color, $secondary_color);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = $message;
            $stmt->close();
            header("Location: theme.php");
            exit();
        } else {
            $error = "Error saving theme: " . $conn->error;
            $stmt->close();
        }
    }
}

// Fetch current theme if exists
$current_theme = ['primary_color' => '', 'secondary_color' => ''];
$sql_theme = "SELECT primary_color, secondary_color FROM theme WHERE user_id = ?";
$stmt_theme = $conn->prepare($sql_theme);
if ($stmt_theme === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt_theme->bind_param("i", $user_id);
$stmt_theme->execute();
$result = $stmt_theme->get_result();
if ($result->num_rows > 0) {
    $current_theme = $result->fetch_assoc();
}
$stmt_theme->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Theme</title>
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
    <style>
        .color-preview {
            width: 30px;
            height: 30px;
            display: inline-block;
            border: 1px solid #ddd;
            margin-left: 10px;
            vertical-align: middle;
        }
        .role-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.7rem;
        }
    </style>
</head>


<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        // Show appropriate menu based on user role
        if ($user_role === 'room') {
            include 'room_management_menu.php';
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header position-relative">
                                <h4 class="card-title">Theme Settings</h4>
                                <span class="badge bg-info role-badge">Role: <?php echo ucfirst($user_role); ?></span>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_SESSION['message'])): ?>
                                    <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <form id="themeForm" method="POST" action="theme.php">
                                    <div class="mb-3">
                                        <label for="primary_color" class="form-label">Primary Color</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" id="primary_color" 
                                                   name="primary_color" value="<?php echo htmlspecialchars($current_theme['primary_color'] ?: '#000000'); ?>"
                                                   title="Choose primary color">
                                            <input type="text" class="form-control" id="primary_color_text" 
                                                   value="<?php echo htmlspecialchars($current_theme['primary_color'] ?: '#000000'); ?>" 
                                                   pattern="^#[a-fA-F0-9]{6}$">
                                            <span class="color-preview" id="primary_preview" 
                                                  style="background-color: <?php echo htmlspecialchars($current_theme['primary_color'] ?: '#000000'); ?>"></span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="secondary_color" class="form-label">Secondary Color</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" id="secondary_color" 
                                                   name="secondary_color" value="<?php echo htmlspecialchars($current_theme['secondary_color'] ?: '#ffffff'); ?>"
                                                   title="Choose secondary color">
                                            <input type="text" class="form-control" id="secondary_color_text" 
                                                   value="<?php echo htmlspecialchars($current_theme['secondary_color'] ?: '#ffffff'); ?>" 
                                                   pattern="^#[a-fA-F0-9]{6}$">
                                            <span class="color-preview" id="secondary_preview" 
                                                  style="background-color: <?php echo htmlspecialchars($current_theme['secondary_color'] ?: '#ffffff'); ?>"></span>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Save Theme</button>
                                    
                                    <?php if ($user_role === 'room'): ?>
                                        <a href="room-dashboard.php" class="btn btn-secondary ms-2">
                                            <i class="fas fa-arrow-left me-1"></i>Back to Room Dashboard
                                        </a>
                                    <?php else: ?>
                                        <a href="dashboard.php" class="btn btn-secondary ms-2">
                                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                                        </a>
                                    <?php endif; ?>
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
        // Sync color picker with text input and preview
        $('#primary_color').on('input', function() {
            $('#primary_color_text').val(this.value);
            $('#primary_preview').css('background-color', this.value);
        });
        
        $('#secondary_color').on('input', function() {
            $('#secondary_color_text').val(this.value);
            $('#secondary_preview').css('background-color', this.value);
        });
        
        // Sync text input with color picker and preview
        $('#primary_color_text').on('input', function() {
            if (/^#[a-fA-F0-9]{6}$/.test(this.value)) {
                $('#primary_color').val(this.value);
                $('#primary_preview').css('background-color', this.value);
            }
        });
        
        $('#secondary_color_text').on('input', function() {
            if (/^#[a-fA-F0-9]{6}$/.test(this.value)) {
                $('#secondary_color').val(this.value);
                $('#secondary_preview').css('background-color', this.value);
            }
        });
        
        // Form validation
        $("#themeForm").validate({
            rules: {
                primary_color: {
                    required: true,
                    pattern: "^#[a-fA-F0-9]{6}$"
                },
                secondary_color: {
                    required: true,
                    pattern: "^#[a-fA-F0-9]{6}$"
                }
            },
            messages: {
                primary_color: {
                    required: "Please enter a primary color",
                    pattern: "Please enter a valid hex color (e.g., #000000)"
                },
                secondary_color: {
                    required: "Please enter a secondary color",
                    pattern: "Please enter a valid hex color (e.g., #ffffff)"
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
    });
    </script>
</body>
</html>
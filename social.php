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

// Fetch user name, role, and country
$sql = "SELECT name, role, country FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $user_role, $user_country);
$stmt->fetch();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process WhatsApp input
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    
    // Validate WhatsApp URL if provided
    if (!empty($whatsapp)) {
        // Validate URL format
        if (!filter_var($whatsapp, FILTER_VALIDATE_URL)) {
            $_SESSION['error_message'] = "Invalid WhatsApp URL format. Please enter a valid URL.";
        } 
        // Check if it's a WhatsApp URL
        elseif (strpos($whatsapp, 'wa.me/') === false && strpos($whatsapp, 'whatsapp.com/') === false) {
            $_SESSION['error_message'] = "Please enter a valid WhatsApp URL (should contain wa.me/ or whatsapp.com/)";
        }
        // Extract and validate number based on country
        else {
            $numberPart = '';
            if (strpos($whatsapp, 'wa.me/') !== false) {
                $numberPart = explode('wa.me/', $whatsapp)[1];
            } elseif (strpos($whatsapp, 'whatsapp.com/') !== false) {
                preg_match('/whatsapp\.com\/.*?(\d+)/', $whatsapp, $matches);
                if (isset($matches[1])) {
                    $numberPart = $matches[1];
                }
            }
            
            // Clean the number (remove any non-digit characters)
            $cleanNumber = preg_replace('/\D/', '', $numberPart);
            
            // Validate based on country
            $isValid = true;
            $errorMessage = '';
            
            switch($user_country) {
                case 'UAE':
                    // UAE: Should be 971 followed by 9 digits (without leading 0)
                    if (!preg_match('/^971[0-9]{9}$/', $cleanNumber)) {
                        $isValid = false;
                        $errorMessage = "Invalid UAE WhatsApp format. Should be: https://wa.me/971XXXXXXXX (971 + 9-digit number without leading 0)";
                    }
                    break;
                    
                case 'India':
                    // India: Should be 91 followed by 10 digits starting with 1-9
                    if (!preg_match('/^91[1-9][0-9]{9}$/', $cleanNumber)) {
                        $isValid = false;
                        $errorMessage = "Invalid India WhatsApp format. Should be: https://wa.me/91XXXXXXXXXX (91 + 10-digit number starting with 1-9)";
                    }
                    break;
                    
                case 'USA':
                    // USA: Should be 1 followed by 10 digits
                    if (!preg_match('/^1[0-9]{10}$/', $cleanNumber)) {
                        $isValid = false;
                        $errorMessage = "Invalid USA WhatsApp format. Should be: https://wa.me/11234567890 (1 + 10-digit number)";
                    }
                    break;
                    
                case 'UK':
                    // UK: Should be 44 followed by 10-11 digits
                    if (!preg_match('/^44[0-9]{10,11}$/', $cleanNumber)) {
                        $isValid = false;
                        $errorMessage = "Invalid UK WhatsApp format. Should be: https://wa.me/447XXXXXXXXXX (44 + number without leading 0)";
                    }
                    break;
            }
            
            if (!$isValid) {
                $_SESSION['error_message'] = $errorMessage;
            } else {
                // Process other inputs
                $facebook = trim($_POST['facebook'] ?? '');
                $instagram = trim($_POST['instagram'] ?? '');
                $linkedin = trim($_POST['linkedin'] ?? '');
                $youtube = trim($_POST['youtube'] ?? '');
                $telegram = trim($_POST['telegram'] ?? '');
                
                // Validate other URLs
                $urls = [
                    'Facebook' => $facebook,
                    'Instagram' => $instagram,
                    'LinkedIn' => $linkedin,
                    'YouTube' => $youtube,
                    'Telegram' => $telegram
                ];
                
                $allValid = true;
                foreach ($urls as $platform => $url) {
                    if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                        $_SESSION['error_message'] = "Invalid $platform URL format. Please enter a valid URL.";
                        $allValid = false;
                        break;
                    }
                }
                
                if ($allValid) {
                    // Check if social links already exist for this user
                    $check_sql = "SELECT COUNT(*) FROM social_link WHERE user_id = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("i", $user_id);
                    $check_stmt->execute();
                    $check_stmt->bind_result($count);
                    $check_stmt->fetch();
                    $check_stmt->close();
                    
                    if ($count > 0) {
                        // Update existing record
                        $sql = "UPDATE social_link SET 
                                facebook = ?, 
                                instagram = ?, 
                                whatsapp = ?, 
                                linkedin = ?, 
                                youtube = ?, 
                                telegram = ?, 
                                updated_at = NOW() 
                                WHERE user_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssssi", $facebook, $instagram, $whatsapp, $linkedin, $youtube, $telegram, $user_id);
                    } else {
                        // Insert new record
                        $sql = "INSERT INTO social_link 
                                (user_id, facebook, instagram, whatsapp, linkedin, youtube, telegram, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("issssss", $user_id, $facebook, $instagram, $whatsapp, $linkedin, $youtube, $telegram);
                    }
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Social links updated successfully!";
                    } else {
                        $_SESSION['error_message'] = "Error updating social links: " . $conn->error;
                    }
                    $stmt->close();
                }
            }
        }
        
        // Redirect to refresh the page and show messages
        header("Location: social.php");
        exit();
    } else {
        // WhatsApp is empty, still process other links
        $facebook = trim($_POST['facebook'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $linkedin = trim($_POST['linkedin'] ?? '');
        $youtube = trim($_POST['youtube'] ?? '');
        $telegram = trim($_POST['telegram'] ?? '');
        $whatsapp = ''; // Empty WhatsApp
        
        // Validate other URLs
        $urls = [
            'Facebook' => $facebook,
            'Instagram' => $instagram,
            'LinkedIn' => $linkedin,
            'YouTube' => $youtube,
            'Telegram' => $telegram
        ];
        
        $allValid = true;
        foreach ($urls as $platform => $url) {
            if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                $_SESSION['error_message'] = "Invalid $platform URL format. Please enter a valid URL.";
                $allValid = false;
                break;
            }
        }
        
        if ($allValid) {
            // Check if social links already exist for this user
            $check_sql = "SELECT COUNT(*) FROM social_link WHERE user_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($count > 0) {
                // Update existing record
                $sql = "UPDATE social_link SET 
                        facebook = ?, 
                        instagram = ?, 
                        whatsapp = ?, 
                        linkedin = ?, 
                        youtube = ?, 
                        telegram = ?, 
                        updated_at = NOW() 
                        WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $facebook, $instagram, $whatsapp, $linkedin, $youtube, $telegram, $user_id);
            } else {
                // Insert new record
                $sql = "INSERT INTO social_link 
                        (user_id, facebook, instagram, whatsapp, linkedin, youtube, telegram, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issssss", $user_id, $facebook, $instagram, $whatsapp, $linkedin, $youtube, $telegram);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Social links updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating social links: " . $conn->error;
            }
            $stmt->close();
        }
        
        // Redirect to refresh the page and show messages
        header("Location: social.php");
        exit();
    }
}

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
    <title>Social Media Links</title>
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
        .country-hint {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            display: block;
        }
        .country-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            margin-left: 5px;
        }
        .example-url {
            font-size: 0.85em;
            color: #28a745;
            background-color: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            margin-top: 5px;
            border-left: 3px solid #28a745;
        }
        .example-url i {
            margin-right: 5px;
        }
        .url-validation-message {
            display: none;
            font-size: 0.85em;
            margin-top: 5px;
        }
        .url-validation-message.valid {
            color: #28a745;
        }
        .url-validation-message.invalid {
            color: #dc3545;
        }
        .phone-format-info {
            font-size: 0.8em;
            color: #6c757d;
            margin-top: 3px;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        // Updated menu inclusion to support vegetable seller role
        if ($user_role === 'room') {
            include 'room_management_menu.php';
        } elseif ($user_role === 'vegetable_seller') {
            include 'vegetable_seller_menu.php';
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Social Media Links</h4>
                                <?php if ($user_role === 'room'): ?>
                                    <span class="badge bg-info float-end">Room Management Mode</span>
                                <?php endif; ?>
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

                                <form method="POST" action="" id="socialForm">

                                    <div class="mb-3">
                                        <label for="facebook" class="form-label">Facebook</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-facebook text-primary"></i></span>
                                            <input type="url" class="form-control" id="facebook" name="facebook" value="<?php echo htmlspecialchars($facebook); ?>" placeholder="https://facebook.com/username">
                                        </div>
                                        <div class="form-text">Note: Add URL eg. https://facebook.com/username</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="instagram" class="form-label">Instagram</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-instagram text-danger"></i></span>
                                            <input type="url" class="form-control" id="instagram" name="instagram" value="<?php echo htmlspecialchars($instagram); ?>" placeholder="https://instagram.com/username">
                                        </div>
                                        <div class="form-text">Note: Add URL eg. https://instagram.com/username</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="whatsapp" class="form-label">WhatsApp 
                                            <span class="country-badge"><?php echo htmlspecialchars($user_country); ?></span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-whatsapp text-success"></i></span>
                                            <input type="url" class="form-control" id="whatsapp" name="whatsapp" 
                                                   value="<?php echo htmlspecialchars($whatsapp); ?>" 
                                                   placeholder="<?php 
                                                   if ($user_country === 'UAE') {
                                                       echo 'https://wa.me/9715XXXXXXXX';
                                                   } elseif ($user_country === 'India') {
                                                       echo 'https://wa.me/91XXXXXXXXXX';
                                                   } elseif ($user_country === 'USA') {
                                                       echo 'https://wa.me/11234567890';
                                                   } elseif ($user_country === 'UK') {
                                                       echo 'https://wa.me/447XXXXXXXXXX';
                                                   } else {
                                                       echo 'https://wa.me/XXXXXXXXXX';
                                                   }
                                                   ?>">
                                        </div>
                                        <div class="example-url">
                                            <i class="fas fa-lightbulb"></i>
                                            Example: 
                                            <?php if ($user_country === 'UAE'): ?>
                                                <code>https://wa.me/9715XXXXXXXX</code> (UAE: 971 + 9-digit number without leading 0)
                                            <?php elseif ($user_country === 'India'): ?>
                                                <code>https://wa.me/91XXXXXXXXXX</code> (India: 91 + 10-digit number starting with 1-9)
                                            <?php elseif ($user_country === 'USA'): ?>
                                                <code>https://wa.me/11234567890</code> (USA: 1 + 10-digit number)
                                            <?php elseif ($user_country === 'UK'): ?>
                                                <code>https://wa.me/447XXXXXXXXXX</code> (UK: 44 + number without leading 0)
                                            <?php else: ?>
                                                <code>https://wa.me/XXXXXXXXXX</code>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($user_country === 'India'): ?>
                                            <div class="phone-format-info">
                                                <i class="fas fa-info-circle"></i> Indian mobile numbers: 10 digits starting with 1-9 (not 0)
                                            </div>
                                        <?php endif; ?>
                                        <div id="whatsappValidation" class="url-validation-message"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="linkedin" class="form-label">LinkedIn</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-linkedin text-primary"></i></span>
                                            <input type="url" class="form-control" id="linkedin" name="linkedin" value="<?php echo htmlspecialchars($linkedin); ?>" placeholder="https://linkedin.com/in/username">
                                        </div>
                                        <div class="form-text">Note: Add URL eg. https://linkedin.com/in/username</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="youtube" class="form-label">YouTube</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-youtube text-danger"></i></span>
                                            <input type="url" class="form-control" id="youtube" name="youtube" value="<?php echo htmlspecialchars($youtube); ?>" placeholder="https://youtube.com/username">
                                        </div>
                                        <div class="form-text">Note: Add URL eg. https://youtube.com/username</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="telegram" class="form-label">Telegram</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-telegram text-primary"></i></span>
                                            <input type="url" class="form-control" id="telegram" name="telegram" value="<?php echo htmlspecialchars($telegram); ?>" placeholder="https://t.me/username">
                                        </div>
                                        <div class="form-text">Note: Add URL eg. https://t.me/username</div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i>Update Social Links
                                        </button>
                                        <?php if ($user_role === 'room'): ?>
                                            <a href="room-dashboard.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-arrow-left me-1"></i>Back to Room Dashboard
                                            </a>
                                        <?php else: ?>
                                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>

                            </div>
                        </div>

                        <!-- Help Card -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Social Media Links Help</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Why add social media links?</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>Increase customer engagement</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Build brand presence</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Share updates and promotions</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Connect with your audience</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Best Practices:</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-lightbulb text-warning me-2"></i>Use full URLs with https://</li>
                                            <li><i class="fas fa-lightbulb text-warning me-2"></i>Keep links updated regularly</li>
                                            <li><i class="fas fa-lightbulb text-warning me-2"></i>Use your business username</li>
                                            <li><i class="fas fa-lightbulb text-warning me-2"></i>Test links after saving</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-xl-3">
                        <!-- Current Links Preview -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-eye me-2"></i>Current Links Preview</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if (!empty($facebook)): ?>
                                        <a href="<?php echo htmlspecialchars($facebook); ?>" target="_blank" class="btn btn-outline-primary btn-sm text-start">
                                            <i class="fab fa-facebook me-2"></i>Facebook
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($instagram)): ?>
                                        <a href="<?php echo htmlspecialchars($instagram); ?>" target="_blank" class="btn btn-outline-danger btn-sm text-start">
                                            <i class="fab fa-instagram me-2"></i>Instagram
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($whatsapp)): ?>
                                        <a href="<?php echo htmlspecialchars($whatsapp); ?>" target="_blank" class="btn btn-outline-success btn-sm text-start">
                                            <i class="fab fa-whatsapp me-2"></i>WhatsApp
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($linkedin)): ?>
                                        <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" class="btn btn-outline-primary btn-sm text-start">
                                            <i class="fab fa-linkedin me-2"></i>LinkedIn
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($youtube)): ?>
                                        <a href="<?php echo htmlspecialchars($youtube); ?>" target="_blank" class="btn btn-outline-danger btn-sm text-start">
                                            <i class="fab fa-youtube me-2"></i>YouTube
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($telegram)): ?>
                                        <a href="<?php echo htmlspecialchars($telegram); ?>" target="_blank" class="btn btn-outline-primary btn-sm text-start">
                                            <i class="fab fa-telegram me-2"></i>Telegram
                                        </a>
                                    <?php endif; ?>

                                    <?php if (empty($facebook) && empty($instagram) && empty($whatsapp) && empty($linkedin) && empty($youtube) && empty($telegram)): ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-link fa-2x mb-2"></i>
                                            <p>No social links added yet</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Tips -->
                        <div class="card mt-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-rocket me-2"></i>Quick Tips</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-0">
                                    <small>
                                        <strong>Pro Tip:</strong> Add your most active social media platforms first. 
                                        Customers are more likely to engage with platforms you regularly update.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
        $(document).ready(function() {
            // Get user country from PHP
            const userCountry = "<?php echo $user_country; ?>";
            
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
                        required: false,
                        validateWhatsApp: true
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
                        validateWhatsApp: "Please enter a valid WhatsApp URL for your country"
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
                },
                errorElement: "div",
                errorPlacement: function(error, element) {
                    error.addClass("invalid-feedback");
                    error.insertAfter(element.parent());
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-invalid").removeClass("is-valid");
                    $(element).parent().find('.input-group-text').addClass('border-danger');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-valid").removeClass("is-invalid");
                    $(element).parent().find('.input-group-text').removeClass('border-danger');
                },
                submitHandler: function(form) {
                    // Show loading state
                    const submitBtn = $(form).find('button[type="submit"]');
                    const originalText = submitBtn.html();
                    submitBtn.html('<i class="fas fa-spinner fa-spin me-1"></i>Updating...');
                    submitBtn.prop('disabled', true);
                    
                    // Submit the form
                    form.submit();
                }
            });

            // Custom validation method for WhatsApp URLs
            $.validator.addMethod("validateWhatsApp", function(value, element) {
                // If empty, it's valid (not required)
                if (value === '') {
                    return true;
                }
                
                // Check if it's a valid URL
                if (!isValidUrl(value)) {
                    return false;
                }
                
                // Check if it's a WhatsApp URL
                if (!value.includes('wa.me/') && !value.includes('whatsapp.com/')) {
                    return false;
                }
                
                // Extract the number part from the URL
                let numberPart = '';
                if (value.includes('wa.me/')) {
                    numberPart = value.split('wa.me/')[1];
                } else if (value.includes('whatsapp.com/')) {
                    // Handle whatsapp.com links
                    const match = value.match(/whatsapp\.com\/.*?(\d+)/);
                    if (match) {
                        numberPart = match[1];
                    }
                }
                
                // Remove any non-digit characters
                const cleanNumber = numberPart.replace(/\D/g, '');
                
                // Validate based on country
                switch(userCountry) {
                    case 'UAE':
                        // UAE: Should start with 971 followed by 9 digits (without leading 0)
                        return /^971[0-9]{9}$/.test(cleanNumber);
                        
                    case 'India':
                        // India: Should start with 91 followed by 10 digits starting with 1-9
                        return /^91[1-9][0-9]{9}$/.test(cleanNumber);
                        
                    case 'USA':
                        // USA: Should start with 1 followed by 10 digits
                        return /^1[0-9]{10}$/.test(cleanNumber);
                        
                    case 'UK':
                        // UK: Should start with 44 followed by 10-11 digits
                        return /^44[0-9]{10,11}$/.test(cleanNumber);
                        
                    default:
                        // For other countries, accept any WhatsApp URL with reasonable length
                        return cleanNumber.length >= 8 && cleanNumber.length <= 15;
                }
            });

            // WhatsApp URL real-time validation
            $('#whatsapp').on('input blur', function() {
                const value = $(this).val();
                const validationDiv = $('#whatsappValidation');
                
                if (value === '') {
                    validationDiv.hide();
                    $(this).removeClass('is-valid is-invalid');
                    $(this).parent().find('.input-group-text').removeClass('border-danger border-success');
                    return;
                }
                
                // Check if valid
                if ($(this).valid()) {
                    $(this).addClass('is-valid').removeClass('is-invalid');
                    $(this).parent().find('.input-group-text').addClass('border-success').removeClass('border-danger');
                    
                    // Show success message
                    validationDiv.removeClass('invalid').addClass('valid');
                    validationDiv.html('<i class="fas fa-check-circle"></i> Valid WhatsApp URL for ' + userCountry);
                    validationDiv.show();
                } else {
                    $(this).addClass('is-invalid').removeClass('is-valid');
                    $(this).parent().find('.input-group-text').addClass('border-danger').removeClass('border-success');
                    
                    // Show error message
                    validationDiv.removeClass('valid').addClass('invalid');
                    let errorMsg = 'Invalid WhatsApp URL. ';
                    
                    switch(userCountry) {
                        case 'UAE':
                            errorMsg += 'Format: https://wa.me/9715XXXXXXXX (971 + 9-digit number without leading 0)';
                            break;
                        case 'India':
                            errorMsg += 'Format: https://wa.me/91XXXXXXXXXX (91 + 10-digit number starting with 1-9)';
                            break;
                        case 'USA':
                            errorMsg += 'Format: https://wa.me/11234567890 (1 + 10-digit number)';
                            break;
                        case 'UK':
                            errorMsg += 'Format: https://wa.me/447XXXXXXXXXX (44 + number without leading 0)';
                            break;
                        default:
                            errorMsg += 'Please enter a valid WhatsApp URL';
                    }
                    
                    validationDiv.html('<i class="fas fa-exclamation-circle"></i> ' + errorMsg);
                    validationDiv.show();
                }
            });

            // Auto-add https:// if missing for URL fields
            $('input[type="url"]').on('blur', function() {
                let value = $(this).val();
                if (value && !value.startsWith('http://') && !value.startsWith('https://')) {
                    $(this).val('https://' + value);
                }
            });

            // Helper function to validate URLs
            function isValidUrl(string) {
                try {
                    new URL(string);
                    return true;
                } catch (_) {
                    return false;
                }
            }
        });
    </script>

</body>
</html>
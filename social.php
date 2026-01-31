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
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        // Show room management menu if user role is 'room', otherwise show regular menu
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

                                <form method="POST" action="update_social_links.php" id="socialForm">

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
                                            <input type="text" class="form-control" id="whatsapp" name="whatsapp" 
                                                   value="<?php echo htmlspecialchars($whatsapp); ?>" 
                                                   placeholder="Enter WhatsApp number or URL">
                                        </div>
                                        <div class="form-text">
                                            <?php if ($user_country === 'UAE'): ?>
                                                <span class="country-hint text-info">
                                                    <i class="fas fa-info-circle"></i> For UAE: Enter 9-digit number starting with 0 (e.g., 05XXXXXXXX)
                                                </span>
                                            <?php elseif ($user_country === 'India'): ?>
                                                <span class="country-hint text-info">
                                                    <i class="fas fa-info-circle"></i> For India: Enter 10-digit number (e.g., 9XXXXXXXXX)
                                                </span>
                                            <?php elseif ($user_country === 'USA'): ?>
                                                <span class="country-hint text-info">
                                                    <i class="fas fa-info-circle"></i> For USA: Enter 10-digit number (e.g., 1234567890)
                                                </span>
                                            <?php elseif ($user_country === 'UK'): ?>
                                                <span class="country-hint text-info">
                                                    <i class="fas fa-info-circle"></i> For UK: Enter 11-digit number starting with 07 (e.g., 07XXXXXXXXX)
                                                </span>
                                            <?php endif; ?>
                                            <div>Note: You can also enter full WhatsApp URL (e.g., https://wa.me/971XXXXXXXX)</div>
                                        </div>
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
                                        <a href="<?php echo getWhatsAppUrl($whatsapp, $user_country); ?>" target="_blank" class="btn btn-outline-success btn-sm text-start">
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
                        validateWhatsApp: function() {
                            if (userCountry === 'UAE') {
                                return "For UAE: Enter 9-digit number starting with 0 (e.g., 05XXXXXXXX) or full WhatsApp URL";
                            } else if (userCountry === 'India') {
                                return "For India: Enter 10-digit number (e.g., 9XXXXXXXXX) or full WhatsApp URL";
                            } else if (userCountry === 'USA') {
                                return "For USA: Enter 10-digit number (e.g., 1234567890) or full WhatsApp URL";
                            } else if (userCountry === 'UK') {
                                return "For UK: Enter 11-digit number starting with 07 (e.g., 07XXXXXXXXX) or full WhatsApp URL";
                            } else {
                                return "Please enter a valid WhatsApp number or URL";
                            }
                        }
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
                }
            });

            // Custom validation method for WhatsApp
            $.validator.addMethod("validateWhatsApp", function(value, element) {
                // If empty, it's valid (not required)
                if (value === '') {
                    return true;
                }
                
                // Check if it's a URL
                if (isValidUrl(value)) {
                    return true;
                }
                
                // Remove any non-digit characters
                const cleanValue = value.replace(/\D/g, '');
                
                // Validate based on country
                switch(userCountry) {
                    case 'UAE':
                        // UAE: 9 digits, can start with 0
                        return /^[0-9]{9}$/.test(cleanValue);
                        
                    case 'India':
                        // India: 10 digits, typically starts with 6-9
                        return /^[6-9][0-9]{9}$/.test(cleanValue);
                        
                    case 'USA':
                        // USA: 10 digits
                        return /^[0-9]{10}$/.test(cleanValue);
                        
                    case 'UK':
                        // UK: 11 digits, typically starts with 07
                        return /^07[0-9]{9}$/.test(cleanValue);
                        
                    default:
                        // For other countries, accept any 8-15 digit number
                        return /^[0-9]{8,15}$/.test(cleanValue);
                }
            });

            // Add real-time validation
            $('#whatsapp').on('blur', function() {
                const value = $(this).val();
                if (value && !$(this).valid()) {
                    $(this).addClass('is-invalid');
                    $(this).parent().find('.input-group-text').addClass('border-danger');
                } else if (value) {
                    $(this).addClass('is-valid');
                    $(this).parent().find('.input-group-text').removeClass('border-danger');
                    
                    // Auto-format as WhatsApp URL if it's just a number
                    if (!value.startsWith('http')) {
                        const cleanNumber = value.replace(/\D/g, '');
                        let countryCode = '';
                        
                        // Add appropriate country code
                        switch(userCountry) {
                            case 'UAE':
                                countryCode = '971';
                                // Remove leading 0 if present
                                const uaeNumber = cleanNumber.replace(/^0+/, '');
                                $(this).val(`https://wa.me/${countryCode}${uaeNumber}`);
                                break;
                            case 'India':
                                countryCode = '91';
                                $(this).val(`https://wa.me/${countryCode}${cleanNumber}`);
                                break;
                            case 'USA':
                                countryCode = '1';
                                $(this).val(`https://wa.me/${countryCode}${cleanNumber}`);
                                break;
                            case 'UK':
                                countryCode = '44';
                                // Remove leading 0
                                const ukNumber = cleanNumber.replace(/^0+/, '');
                                $(this).val(`https://wa.me/${countryCode}${ukNumber}`);
                                break;
                        }
                    }
                }
            });

            // Add real-time URL validation for other fields
            $('input[type="url"]').on('blur', function() {
                const url = $(this).val();
                if (url && !isValidUrl(url)) {
                    $(this).addClass('is-invalid');
                    $(this).parent().find('.input-group-text').addClass('border-danger');
                } else if (url) {
                    $(this).addClass('is-valid');
                    $(this).parent().find('.input-group-text').removeClass('border-danger');
                }
            });

            function isValidUrl(string) {
                try {
                    new URL(string);
                    return true;
                } catch (_) {
                    return false;
                }
            }

            // Auto-format URLs on input for other fields
            $('input[type="url"]').on('input', function() {
                let value = $(this).val();
                if (value && !value.startsWith('http://') && !value.startsWith('https://')) {
                    $(this).val('https://' + value);
                }
            });
        });
    </script>

</body>
</html>

<?php
// Helper function to generate proper WhatsApp URL
function getWhatsAppUrl($whatsappValue, $country) {
    // If it's already a URL, return it
    if (filter_var($whatsappValue, FILTER_VALIDATE_URL)) {
        return $whatsappValue;
    }
    
    // Clean the number
    $cleanNumber = preg_replace('/\D/', '', $whatsappValue);
    
    // Generate WhatsApp URL with country code
    switch($country) {
        case 'UAE':
            $countryCode = '971';
            // Remove leading 0 for UAE numbers
            $cleanNumber = ltrim($cleanNumber, '0');
            return "https://wa.me/{$countryCode}{$cleanNumber}";
            
        case 'India':
            $countryCode = '91';
            return "https://wa.me/{$countryCode}{$cleanNumber}";
            
        case 'USA':
            $countryCode = '1';
            return "https://wa.me/{$countryCode}{$cleanNumber}";
            
        case 'UK':
            $countryCode = '44';
            // Remove leading 0 for UK numbers
            $cleanNumber = ltrim($cleanNumber, '0');
            return "https://wa.me/{$countryCode}{$cleanNumber}";
            
        default:
            return "https://wa.me/{$cleanNumber}";
    }
}
?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Kolkata');
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_sql = "SELECT role, name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($role, $user_name);
$user_stmt->fetch();
$user_stmt->close();

if ($role !== 'admin') {
    header("Location: index.php");
    exit();
}

// Define payment screenshots directory
$payment_screenshots_dir = "payment_screenshots/";

// Handle form submission for card assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $assignment_user_id = $_POST['user_id'];
    $quantity = $_POST['quantity'];
    
    // Validate quantity
    if (!is_numeric($quantity) || $quantity <= 0) {
        $_SESSION['error_message'] = "Please enter a valid quantity (must be a positive number).";
        header("Location: create_cards_assignment.php");
        exit();
    }
    
    // Process payment screenshot upload (optional)
    $payment_screenshot_path = null;
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
        $payment_screenshot_path = uploadPaymentScreenshot($assignment_user_id, $_FILES['payment_screenshot'], $payment_screenshots_dir);
    }
    
    // Create card assignment
    if (createCardAssignment($assignment_user_id, $quantity, $payment_screenshot_path)) {
        $_SESSION['success_message'] = "Card assignment created successfully!";
        header("Location: cards_assignment.php"); // Redirect to assignments listing page
        exit();
    }
}

/**
 * Upload payment screenshot file to payment_screenshots folder
 */
function uploadPaymentScreenshot($user_id, $file, $upload_dir) {
    global $conn;
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_type = $file['type'];
    
    // Validate file type (only allow images)
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error_message'] = "Only JPG, PNG, GIF, and WEBP images are allowed for payment screenshot.";
        return false;
    }
    
    // Validate file size (max 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file_size > $max_size) {
        $_SESSION['error_message'] = "Payment screenshot size exceeds maximum limit of 2MB.";
        return false;
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $_SESSION['error_message'] = "Failed to create payment screenshots directory.";
            return false;
        }
        
        // Add .htaccess for security
        $htaccess_content = "Order deny,allow\nDeny from all";
        file_put_contents($upload_dir . '.htaccess', $htaccess_content);
        
        // Add index.html to prevent directory listing
        file_put_contents($upload_dir . 'index.html', '<html><body><h1>Directory access forbidden</h1></body></html>');
    }
    
    // Generate unique filename
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $new_filename = "payment_" . $user_id . "_" . date('Ymd_His') . "_" . uniqid() . "." . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file to destination
    if (move_uploaded_file($file_tmp, $upload_path)) {
        return $upload_path;
    } else {
        $_SESSION['error_message'] = "Error uploading payment screenshot. Please try again.";
        return false;
    }
}

/**
 * Create card assignment record in database
 */
function createCardAssignment($user_id, $quantity, $payment_screenshot_path) {
    global $conn;
    
    // Get user's card designs
    $cards_sql = "SELECT * FROM user_cards WHERE user_id = ?";
    $cards_stmt = $conn->prepare($cards_sql);
    $cards_stmt->bind_param("i", $user_id);
    $cards_stmt->execute();
    $cards_result = $cards_stmt->get_result();
    
    $front_card_path = null;
    $back_card_path = null;
    
    while ($card = $cards_result->fetch_assoc()) {
        if ($card['card_type'] === 'front') {
            $front_card_path = $card['file_path'];
        } elseif ($card['card_type'] === 'back') {
            $back_card_path = $card['file_path'];
        }
    }
    $cards_stmt->close();
    
    // Check if both card designs exist
    if (!$front_card_path || !$back_card_path) {
        $_SESSION['error_message'] = "User must have both front and back card designs before creating assignment.";
        return false;
    }
    
    // Check if cards_assignment table exists, if not create it
    $check_table_sql = "CREATE TABLE IF NOT EXISTS cards_assignment (
        id INT(11) PRIMARY KEY AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        front_card_path VARCHAR(255),
        back_card_path VARCHAR(255),
        quantity INT(11) NOT NULL,
        payment_screenshot_path VARCHAR(255),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        assigned_by INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (assigned_by) REFERENCES users(id)
    )";
    
    if (!$conn->query($check_table_sql)) {
        $_SESSION['error_message'] = "Error creating cards_assignment table: " . $conn->error;
        return false;
    }
    
    // Insert assignment record
    $insert_sql = "INSERT INTO cards_assignment (user_id, front_card_path, back_card_path, quantity, payment_screenshot_path, assigned_by) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("issisi", $user_id, $front_card_path, $back_card_path, $quantity, $payment_screenshot_path, $_SESSION['user_id']);
    
    if ($insert_stmt->execute()) {
        $insert_stmt->close();
        return true;
    } else {
        $_SESSION['error_message'] = "Error creating card assignment: " . $conn->error;
        $insert_stmt->close();
        return false;
    }
}

// Fetch messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch all users with role 'user' and their card designs
$users_sql = "SELECT u.id, u.name, 
              GROUP_CONCAT(CONCAT(uc.card_type, ':', uc.file_path)) as card_designs
              FROM users u
              LEFT JOIN user_cards uc ON u.id = uc.user_id
              WHERE u.role = 'user'
              GROUP BY u.id, u.name
              HAVING COUNT(uc.id) >= 2  -- Only show users with both card designs
              ORDER BY u.name ASC";
$users_result = $conn->query($users_sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Create Card Assignment | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <style>
        .card-preview {
            max-width: 100%;
            max-height: 150px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }
        .design-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .file-info {
            margin-top: 10px;
            font-size: 0.9em;
        }
        .preview-section {
            margin-bottom: 20px;
        }
        .preview-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #495057;
        }
        .user-card {
            border-left: 4px solid #007bff;
        }
        .no-designs-alert {
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Create Card Assignment</h4>
                                <p class="text-muted mb-0">Assign card designs to users with quantity and optional payment screenshot</p>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <form method="post" action="" enctype="multipart/form-data" id="assignmentForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="user_id">Select User <span class="text-danger">*</span></label>
                                                <select class="form-control" id="user_id" name="user_id" required onchange="updateCardDesigns()">
                                                    <option value="">Select User</option>
                                                    <?php 
                                                    if ($users_result->num_rows > 0):
                                                        while ($user = $users_result->fetch_assoc()): 
                                                    ?>
                                                        <option value="<?php echo $user['id']; ?>" 
                                                                data-designs="<?php echo htmlspecialchars($user['card_designs'] ?? ''); ?>">
                                                            <?php echo htmlspecialchars($user['name']); ?> 
                                                            (ID: <?php echo $user['id']; ?>)
                                                        </option>
                                                    <?php 
                                                        endwhile;
                                                    else: 
                                                    ?>
                                                        <option value="" disabled>No users with complete card designs found</option>
                                                    <?php endif; ?>
                                                </select>
                                                <small class="text-muted">Only users with both front and back card designs are shown</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="quantity">Quantity <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                                       min="1" max="10000" required placeholder="Enter number of cards">
                                                <small class="text-muted">Number of cards to assign (1-10000)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card Designs Preview Section -->
                                    <div class="row mt-4" id="cardDesignsSection" style="display: none;">
                                        <div class="col-md-12">
                                            <div class="design-info">
                                                <h5>Available Card Designs</h5>
                                                <div class="row">
                                                    <div class="col-md-6 preview-section">
                                                        <div class="preview-title">Front Card Design</div>
                                                        <div id="frontDesignPreview">
                                                            <p class="text-muted" id="noFrontDesign">No front design available</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 preview-section">
                                                        <div class="preview-title">Back Card Design</div>
                                                        <div id="backDesignPreview">
                                                            <p class="text-muted" id="noBackDesign">No back design available</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Screenshot Section -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="payment_screenshot">Payment Screenshot (Optional)</label>
                                                <input type="file" class="form-control" id="payment_screenshot" name="payment_screenshot" 
                                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                                <small class="text-muted">
                                                    Upload payment confirmation screenshot. 
                                                    Allowed formats: JPG, PNG, GIF, WEBP (Max 2MB). 
                                                    File will be saved in: <code><?php echo $payment_screenshots_dir; ?></code>
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <button type="submit" name="create_assignment" class="btn btn-primary">
                                                <i class="fas fa-plus-circle"></i> Create Assignment
                                            </button>
                                            <a href="cards_assignment.php" class="btn btn-secondary">
                                                <i class="fas fa-list"></i> View All Assignments
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Add jQuery before Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        function updateCardDesigns() {
            const userSelect = document.getElementById('user_id');
            const selectedOption = userSelect.options[userSelect.selectedIndex];
            const designsData = selectedOption.getAttribute('data-designs');
            const designsSection = document.getElementById('cardDesignsSection');
            
            // Reset previews
            document.getElementById('frontDesignPreview').innerHTML = '<p class="text-muted" id="noFrontDesign">No front design available</p>';
            document.getElementById('backDesignPreview').innerHTML = '<p class="text-muted" id="noBackDesign">No back design available</p>';
            
            if (designsData) {
                designsSection.style.display = 'block';
                const designs = designsData.split(',');
                
                let hasFront = false;
                let hasBack = false;
                
                designs.forEach(design => {
                    const [type, path] = design.split(':');
                    if (type && path) {
                        const previewDiv = type === 'front' ? 'frontDesignPreview' : 'backDesignPreview';
                        const noDesignId = type === 'front' ? 'noFrontDesign' : 'noBackDesign';
                        
                        // Remove "no design" message
                        const noDesignElem = document.getElementById(noDesignId);
                        if (noDesignElem) noDesignElem.remove();
                        
                        // Check if file is image or PDF
                        const fileExt = path.split('.').pop().toLowerCase();
                        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt);
                        const isPDF = fileExt === 'pdf';
                        
                        let previewHtml = '';
                        
                        if (isImage) {
                            previewHtml = `
                                <img src="${path}" alt="${type} design" class="card-preview">
                                <div class="file-info">
                                    <strong>File:</strong> ${path.split('/').pop()}
                                </div>
                            `;
                        } else if (isPDF) {
                            previewHtml = `
                                <div class="alert alert-info">
                                    <i class="fas fa-file-pdf"></i> PDF File
                                </div>
                                <div class="file-info">
                                    <strong>File:</strong> ${path.split('/').pop()}
                                </div>
                                <a href="${path}" target="_blank" class="btn btn-sm btn-primary mt-2">View PDF</a>
                            `;
                        } else {
                            previewHtml = `
                                <div class="alert alert-warning">
                                    <i class="fas fa-file"></i> Unknown File Type
                                </div>
                                <div class="file-info">
                                    <strong>File:</strong> ${path.split('/').pop()}
                                </div>
                            `;
                        }
                        
                        document.getElementById(previewDiv).innerHTML = previewHtml;
                        
                        if (type === 'front') hasFront = true;
                        if (type === 'back') hasBack = true;
                    }
                });
                
                // Show warning if missing designs
                if (!hasFront || !hasBack) {
                    const warningHtml = `
                        <div class="alert alert-warning mt-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            User is missing ${!hasFront ? 'front' : ''}${!hasFront && !hasBack ? ' and ' : ''}${!hasBack ? 'back' : ''} card design
                        </div>
                    `;
                    designsSection.innerHTML += warningHtml;
                }
            } else {
                designsSection.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('assignmentForm').addEventListener('submit', function(e) {
            const quantity = document.getElementById('quantity').value;
            const user_id = document.getElementById('user_id').value;
            
            if (!user_id) {
                alert('Please select a user');
                e.preventDefault();
                return;
            }
            
            if (!quantity || quantity < 1) {
                alert('Please enter a valid quantity (minimum 1)');
                e.preventDefault();
                return;
            }
            
            // Check file size client-side
            const fileInput = document.getElementById('payment_screenshot');
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size;
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (fileSize > maxSize) {
                    alert('Payment screenshot must be less than 2MB');
                    e.preventDefault();
                    return;
                }
            }
        });

        // Initialize on page load if user is preselected
        document.addEventListener('DOMContentLoaded', function() {
            const userSelect = document.getElementById('user_id');
            if (userSelect.value) {
                updateCardDesigns();
            }
        });
    </script>
</body>
</html>
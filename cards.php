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

// Handle file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_cards'])) {
        $card_user_id = $_POST['user_id'];
        
        // Process Front Card Design
        if (isset($_FILES['front_card_design']) && $_FILES['front_card_design']['error'] === UPLOAD_ERR_OK) {
            uploadCardDesign($card_user_id, 'front', $_FILES['front_card_design']);
        }
        
        // Process Back Card Design
        if (isset($_FILES['back_card_design']) && $_FILES['back_card_design']['error'] === UPLOAD_ERR_OK) {
            uploadCardDesign($card_user_id, 'back', $_FILES['back_card_design']);
        }
    } 
    // Handle card deletion
    elseif (isset($_POST['delete_card'])) {
        $card_id = $_POST['card_id'];
        deleteCardDesign($card_id);
    }
    
    header("Location: cards.php");
    exit();
}

function uploadCardDesign($user_id, $card_type, $file) {
    global $conn;
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_type = $file['type'];
    
    // Validate file type (only allow images and PDFs)
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error_message'] = "Only JPG, PNG, GIF, and PDF files are allowed for $card_type card.";
        return false;
    }
    
    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file_size > $max_size) {
        $_SESSION['error_message'] = "File size exceeds maximum limit of 5MB for $card_type card.";
        return false;
    }
    
    // Generate unique filename
    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
    $new_filename = "card_" . $user_id . "_" . $card_type . "_" . time() . "." . $file_ext;
    $upload_path = "card_designs/" . $new_filename;
    
    // Move uploaded file to destination
    if (move_uploaded_file($file_tmp, $upload_path)) {
        // Check if record already exists
        $check_sql = "SELECT id, file_path FROM user_cards WHERE user_id = ? AND card_type = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $user_id, $card_type);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_card = $check_result->fetch_assoc();
            // Delete old file first
            if (file_exists($existing_card['file_path'])) {
                unlink($existing_card['file_path']);
            }
            
            // Update existing record
            $update_sql = "UPDATE user_cards SET file_path = ?, uploaded_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $upload_path, $existing_card['id']);
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = ucfirst($card_type) . " card design updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating $card_type card design: " . $conn->error;
            }
            
            $update_stmt->close();
        } else {
            // Insert new record
            $insert_sql = "INSERT INTO user_cards (user_id, card_type, file_path, uploaded_at) VALUES (?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iss", $user_id, $card_type, $upload_path);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = ucfirst($card_type) . " card design uploaded successfully!";
            } else {
                $_SESSION['error_message'] = "Error uploading $card_type card design: " . $conn->error;
            }
            
            $insert_stmt->close();
        }
        
        $check_stmt->close();
        return true;
    } else {
        $_SESSION['error_message'] = "Error uploading $card_type card file. Please try again.";
        return false;
    }
}

function deleteCardDesign($card_id) {
    global $conn;
    
    // Get file path before deleting
    $get_sql = "SELECT file_path FROM user_cards WHERE id = ?";
    $get_stmt = $conn->prepare($get_sql);
    $get_stmt->bind_param("i", $card_id);
    $get_stmt->execute();
    $get_stmt->bind_result($file_path);
    $get_stmt->fetch();
    $get_stmt->close();
    
    if (!empty($file_path)) {
        // Delete the file
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete the database record
        $delete_sql = "DELETE FROM user_cards WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $card_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Card design deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting card design: " . $conn->error;
        }
        
        $delete_stmt->close();
    } else {
        $_SESSION['error_message'] = "Card design not found.";
    }
}

// Fetch messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get search term from GET parameter
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch all users with payment records
$users_sql = "SELECT DISTINCT u.id, u.name 
              FROM users u
              JOIN subscription_payments sp ON u.id = sp.user_id
              WHERE u.role != 'admin'
              ORDER BY u.name ASC";
$users_result = $conn->query($users_sql);

// Build card designs query with search filter
$cards_sql = "SELECT uc.*, u.name as user_name 
              FROM user_cards uc
              JOIN users u ON uc.user_id = u.id";

// Add WHERE clause if search term exists
if (!empty($search_term)) {
    $search_like = "%" . $conn->real_escape_string($search_term) . "%";
    $cards_sql .= " WHERE (u.name LIKE ? OR uc.card_type LIKE ? OR uc.file_path LIKE ?)";
}

$cards_sql .= " ORDER BY uc.uploaded_at DESC, u.name ASC, uc.card_type ASC";

// Prepare and execute the query with search parameters if needed
$cards_stmt = $conn->prepare($cards_sql);
if (!empty($search_term)) {
    $cards_stmt->bind_param("sss", $search_like, $search_like, $search_like);
}
$cards_stmt->execute();
$cards_result = $cards_stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Card Designs | Admin</title>
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
    <style>
        .card-design-container {
            margin-bottom: 30px;
        }
        .card-design-box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .card-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
        }
        .file-info {
            margin-top: 10px;
            font-size: 0.9em;
        }
        .upload-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .card-upload-field {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px dashed #ccc;
            border-radius: 5px;
        }
        .card-upload-field h5 {
            margin-bottom: 15px;
            color: #495057;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            gap: 10px;
        }
        .search-form .form-control {
            flex-grow: 1;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .mr-1 {
            margin-right: 0.25rem !important;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .card-header {
                padding: 15px 10px;
            }
            
            .card-header h4 {
                font-size: 1.2rem;
            }
            
            .upload-section {
                padding: 15px;
            }
            
            .card-upload-field {
                padding: 10px;
                margin-bottom: 15px;
            }
            
            .search-form {
                flex-direction: column;
                gap: 8px;
            }
            
            .search-form .btn {
                width: 100%;
            }
            
            .table-responsive {
                border: 1px solid #dee2e6;
            }
            
            .table thead {
                display: none;
            }
            
            .table tbody tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 10px;
            }
            
            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 5px;
                border: none;
                border-bottom: 1px solid #f8f9fa;
            }
            
            .table tbody td:last-child {
                border-bottom: none;
            }
            
            .table tbody td::before {
                content: attr(data-label);
                font-weight: bold;
                text-align: left;
                margin-right: 10px;
                flex: 0 0 40%;
            }
            
            .table tbody td .btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                justify-content: flex-end;
                flex: 1;
            }
            
            .table tbody td .btn {
                flex: 1;
                min-width: 70px;
                text-align: center;
                font-size: 0.8rem;
                padding: 4px 8px;
            }
            
            .btn-group .btn {
                margin-right: 0 !important;
            }
            
            .mobile-hidden {
                display: none;
            }
            
            .mobile-full-width {
                width: 100% !important;
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card-body {
                padding: 15px 10px;
            }
            
            .upload-section .row > div {
                margin-bottom: 10px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .btn-group {
                width: 100%;
            }
            
            .btn-group .btn {
                flex: 1;
            }
        }
        
        /* Enhanced mobile table styles */
        .mobile-table-cell {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        
        .mobile-action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }
        .scroll-to-top.show {
          bottom: 15px;
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
                                <h4 class="card-title">Upload Card Designs</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <div class="upload-section">
                                    <form method="post" action="" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="user_id">User Name <span class="text-danger">*</span></label>
                                                    <select class="form-control" id="user_id" name="user_id" required>
                                                        <option value="">Select User</option>
                                                        <?php while ($user = $users_result->fetch_assoc()): ?>
                                                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <div class="card-upload-field">
                                                    <h5>Front Card Design</h5>
                                                    <div class="form-group">
                                                        <label for="front_card_design">Upload Front Design</label>
                                                        <input type="file" class="form-control" id="front_card_design" name="front_card_design">
                                                        <small class="text-muted">Allowed formats: JPG, PNG, GIF, PDF (Max 5MB)</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="card-upload-field">
                                                    <h5>Back Card Design</h5>
                                                    <div class="form-group">
                                                        <label for="back_card_design">Upload Back Design</label>
                                                        <input type="file" class="form-control" id="back_card_design" name="back_card_design">
                                                        <small class="text-muted">Allowed formats: JPG, PNG, GIF, PDF (Max 5MB)</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-12">
                                                <button type="submit" name="upload_cards" class="btn btn-primary mobile-full-width">Upload Designs</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Designs Table -->
                <div class="row mt-4">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-column flex-md-row">
                                <h4 class="card-title mb-2 mb-md-0">Card Designs</h4>
                                <div class="search-container w-100 w-md-auto">
                                    <form method="get" action="" class="search-form">
                                        <input type="text" class="form-control" name="search" placeholder="Search by user, type, or filename" value="<?php echo htmlspecialchars($search_term); ?>">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary flex-fill">Search</button>
                                            <?php if (!empty($search_term)): ?>
                                                <a href="cards.php" class="btn btn-secondary flex-fill">Clear</a>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered d-none d-md-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User Name</th>
                                                <th>Card Type</th>
                                                <th>File</th>
                                                <th>Uploaded At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($cards_result->num_rows > 0): ?>
                                                <?php while ($card = $cards_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo $card['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($card['user_name']); ?></td>
                                                        <td><?php echo ucfirst($card['card_type']); ?> Design</td>
                                                        <td>
                                                            <?php 
                                                            $file_name = basename($card['file_path']);
                                                            echo htmlspecialchars($file_name);
                                                            ?>
                                                        </td>
                                                        <td><?php echo date('d M Y H:i', strtotime($card['uploaded_at'])); ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="<?php echo $card['file_path']; ?>" class="btn btn-sm btn-primary mr-1" download>
                                                                    Download
                                                                </a>
                                                                <a href="<?php echo $card['file_path']; ?>" class="btn btn-sm btn-info mr-1" target="_blank">
                                                                    View
                                                                </a>
                                                                <form method="post" style="display:inline;">
                                                                    <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                                                    <button type="submit" name="delete_card" class="btn btn-sm btn-danger" 
                                                                            onclick="return confirm('Are you sure you want to delete this card design? This action cannot be undone.')">
                                                                        Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <?php echo empty($search_term) ? 'No card designs found' : 'No results found for "' . htmlspecialchars($search_term) . '"'; ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>

                                    <!-- Mobile Cards View -->
                                    <div class="d-md-none">
                                        <?php if ($cards_result->num_rows > 0): ?>
                                            <?php 
                                            // Reset pointer for mobile view
                                            $cards_result->data_seek(0); 
                                            ?>
                                            <?php while ($card = $cards_result->fetch_assoc()): ?>
                                                <div class="card mb-3">
                                                    <div class="card-body">
                                                        <div class="mobile-table-cell">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <strong><?php echo htmlspecialchars($card['user_name']); ?></strong>
                                                                <small class="text-muted">ID: <?php echo $card['id']; ?></small>
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <strong>Type:</strong> <?php echo ucfirst($card['card_type']); ?> Design
                                                            </div>
                                                            
                                                            <div class="mb-2 text-truncate">
                                                                <strong>File:</strong> 
                                                                <?php 
                                                                $file_name = basename($card['file_path']);
                                                                echo htmlspecialchars($file_name);
                                                                ?>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <strong>Uploaded:</strong> <?php echo date('d M Y H:i', strtotime($card['uploaded_at'])); ?>
                                                            </div>
                                                            
                                                            <div class="mobile-action-buttons">
                                                                <a href="<?php echo $card['file_path']; ?>" class="btn btn-sm btn-primary flex-fill" download>
                                                                    Download
                                                                </a>
                                                                <a href="<?php echo $card['file_path']; ?>" class="btn btn-sm btn-info flex-fill" target="_blank">
                                                                    View
                                                                </a>
                                                                <form method="post" class="flex-fill">
                                                                    <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                                                    <button type="submit" name="delete_card" class="btn btn-sm btn-danger w-100" 
                                                                            onclick="return confirm('Are you sure you want to delete this card design? This action cannot be undone.')">
                                                                        Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <div class="text-center p-4">
                                                <?php echo empty($search_term) ? 'No card designs found' : 'No results found for "' . htmlspecialchars($search_term) . '"'; ?>
                                            </div>
                                        <?php endif; ?>
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

    <!-- Add jQuery before Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Enhance mobile experience
        $(document).ready(function() {
            // Handle file input styling on mobile
            $('input[type="file"]').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                if (fileName) {
                    $(this).next('small').before('<div class="file-selected text-success mt-1"><i class="fas fa-check-circle"></i> Selected: ' + fileName + '</div>');
                }
            });
            
            // Enhance touch experience for buttons
            $('.btn').on('touchstart', function() {
                $(this).addClass('btn-active');
            }).on('touchend', function() {
                $(this).removeClass('btn-active');
            });
        });
    </script>
</body>
</html>
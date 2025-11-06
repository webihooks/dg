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
$role = $_SESSION['role'] ?? 'user'; // Get user role
$message = '';
$error = '';

// Fetch user name
$sql_name = "SELECT name FROM users WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
if ($stmt_name === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$stmt_name->bind_result($user_name);
$stmt_name->fetch();
$stmt_name->close();

// Handle APK file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['apk_file'])) {
    // Check if user already has an APK
    $check_sql = "SELECT id, file_path FROM user_apks WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $error = "You can only have one APK file. Please delete your existing APK first.";
    } else {
        $uploadDir = 'downloads/' . $user_id . '/';
        
        // Create user directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = basename($_FILES['apk_file']['name']);
        $filePath = $uploadDir . $fileName;
        $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Validate file is APK
        if ($fileType != 'apk') {
            $error = "Only APK files are allowed.";
        } elseif ($_FILES['apk_file']['size'] > 100 * 1024 * 1024) { // 100MB limit
            $error = "File size must be less than 100MB.";
        } elseif (move_uploaded_file($_FILES['apk_file']['tmp_name'], $filePath)) {
            // Save to database
            $sql = "INSERT INTO user_apks (user_id, file_name, file_path, upload_date) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $fileName, $filePath);
            
            if (!$stmt->execute()) {
                $error = "Error saving file info: " . $conn->error;
                // Remove the uploaded file if DB failed
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            } else {
                $message = "APK uploaded successfully!";
            }
            $stmt->close();
        } else {
            $error = "Error uploading file.";
        }
    }
    $check_stmt->close();
}

// Get user's APK (will return only one or none)
$currentApk = null;
$sql_apk = "SELECT file_name, file_path, upload_date FROM user_apks WHERE user_id = ? LIMIT 1";
$stmt_apk = $conn->prepare($sql_apk);
if ($stmt_apk === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt_apk->bind_param("i", $user_id);
$stmt_apk->execute();
$result = $stmt_apk->get_result();
if ($result->num_rows > 0) {
    $currentApk = $result->fetch_assoc();
}
$stmt_apk->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>APK Upload</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>.apk-card{max-width:600px;margin:0 auto}.apk-alert{border-left:4px solid #007bff}.file-info{background:#f8f9fa;border-radius:5px;padding:15px}</style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <!-- Role-based menu inclusion -->
        <?php if ($role === 'room'): ?>
            <?php include 'room_management_menu.php'; ?>
        <?php else: ?>
            <?php include 'menu.php'; ?>
        <?php endif; ?>

        <div class="page-content">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xl-8">
                        <div class="card apk-card">
                            <div class="card-header">
                                <h4 class="card-title">📱 APK Upload</h4>
                                <p class="text-muted mb-0">Upload your Android application package file</p>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show">
                                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($currentApk): ?>
                                    <div class="alert alert-info apk-alert">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                                            <div class="mb-2 mb-md-0">
                                                <h6 class="mb-1">📦 Current APK File</h6>
                                                <div class="file-info">
                                                    <strong>File Name:</strong> <?php echo htmlspecialchars($currentApk['file_name']); ?><br>
                                                    <strong>Uploaded:</strong> <?php echo date('M j, Y g:i A', strtotime($currentApk['upload_date'])); ?>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a href="<?php echo htmlspecialchars($currentApk['file_path']); ?>" 
                                                   class="btn btn-success btn-sm" download>
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                                <a href="delete_apk.php" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Are you sure you want to delete your APK file?')">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center mt-3">
                                        <small class="text-muted">To upload a new APK, please delete the current one first.</small>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="upload_apk.php" enctype="multipart/form-data" id="apkUploadForm">
                                        <div class="mb-4">
                                            <label for="apk_file" class="form-label fw-bold">Select APK File</label>
                                            <input class="form-control form-control-lg" type="file" id="apk_file" name="apk_file" accept=".apk" required>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Maximum file size: 100MB | Only .apk files allowed
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-upload me-2"></i>Upload APK
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <div class="mt-4 p-3 bg-light rounded">
                                        <h6 class="mb-2">📋 Requirements:</h6>
                                        <ul class="list-unstyled mb-0 small">
                                            <li><i class="fas fa-check text-success me-2"></i>File must have .apk extension</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Maximum file size: 100MB</li>
                                            <li><i class="fas fa-check text-success me-2"></i>Only one APK file per user</li>
                                            <li><i class="fas fa-check text-success me-2"></i>File will be available for download</li>
                                        </ul>
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
    // File size validation
    document.getElementById('apk_file')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const maxSize = 100 * 1024 * 1024; // 100MB in bytes
        
        if (file && file.size > maxSize) {
            alert('File size must be less than 100MB');
            e.target.value = '';
        }
        
        // Validate file extension
        const fileName = file.name.toLowerCase();
        if (!fileName.endsWith('.apk')) {
            alert('Only APK files are allowed');
            e.target.value = '';
        }
    });

    // Form submission handling
    document.getElementById('apkUploadForm')?.addEventListener('submit', function(e) {
        const fileInput = document.getElementById('apk_file');
        if (!fileInput.value) {
            e.preventDefault();
            alert('Please select an APK file');
            return false;
        }
    });
    </script>
</body>
</html>
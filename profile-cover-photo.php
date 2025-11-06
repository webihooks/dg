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

// Fetch Users Details including role
$user_sql = "SELECT name, role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $user_role);
$user_stmt->fetch();
$user_stmt->close();

// Fetch profile and cover photos
$photo_sql = "SELECT profile_photo, cover_photo FROM profile_cover_photo WHERE user_id = ?";
$photo_stmt = $conn->prepare($photo_sql);
$photo_stmt->bind_param("i", $user_id);
$photo_stmt->execute();
$photo_stmt->bind_result($profile_photo, $cover_photo);
$photo_stmt->fetch();
$photo_stmt->close();

// Handle profile photo upload
if (isset($_POST['upload_profile'])) {
    $target_dir = "uploads/profile/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $target_file = $target_dir . basename($_FILES["profile_photo"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["profile_photo"]["tmp_name"]);
    if ($check === false) {
        $uploadOk = 0;
    }

    // Check file size (max 5MB)
    if ($_FILES["profile_photo"]["size"] > 5000000) {
        $uploadOk = 0;
    }

    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        // Generate unique filename
        $new_filename = "profile_" . $user_id . "_" . time() . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
            // Check if record exists
            $check_sql = "SELECT id FROM profile_cover_photo WHERE user_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                // Update existing record
                $update_sql = "UPDATE profile_cover_photo SET profile_photo = ? WHERE user_id = ?";
            } else {
                // Insert new record
                $update_sql = "INSERT INTO profile_cover_photo (profile_photo, user_id) VALUES (?, ?)";
            }
            $check_stmt->close();
            
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $new_filename, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Update the $profile_photo variable
            $profile_photo = $new_filename;
        }
    }
}

// Handle cover photo upload
if (isset($_POST['upload_cover'])) {
    $target_dir = "uploads/cover/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $target_file = $target_dir . basename($_FILES["cover_photo"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["cover_photo"]["tmp_name"]);
    if ($check === false) {
        $uploadOk = 0;
    }

    // Check file size (max 5MB)
    if ($_FILES["cover_photo"]["size"] > 5000000) {
        $uploadOk = 0;
    }

    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        // Generate unique filename
        $new_filename = "cover_" . $user_id . "_" . time() . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["cover_photo"]["tmp_name"], $target_file)) {
            // Check if record exists
            $check_sql = "SELECT id FROM profile_cover_photo WHERE user_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                // Update existing record
                $update_sql = "UPDATE profile_cover_photo SET cover_photo = ? WHERE user_id = ?";
            } else {
                // Insert new record
                $update_sql = "INSERT INTO profile_cover_photo (cover_photo, user_id) VALUES (?, ?)";
            }
            $check_stmt->close();
            
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $new_filename, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Update the $cover_photo variable
            $cover_photo = $new_filename;
        }
    }
}

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
        // Show different menu based on user role
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
                                <h4 class="card-title">Profile & Cover Photo</h4>
                                <?php if ($user_role === 'room'): ?>
                                    <span class="badge bg-info" style="float: right;">Room Management User</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <!-- Cover Photo Section -->
                                <div class="cover-photo-section mb-4">
                                    <div class="cover-photo-container" style="height: 300px; overflow: hidden; position: relative; background-color: #f5f5f5;">
                                        <?php if (!empty($cover_photo)): ?>
                                            <img src="uploads/cover/<?php echo htmlspecialchars($cover_photo); ?>" alt="Cover Photo" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background-color: #ddd; display: flex; align-items: center; justify-content: center;">
                                                <span>No cover photo uploaded</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div style="position: absolute; bottom: 20px; right: 20px; z-index: 999;">
                                            <form method="post" enctype="multipart/form-data" class="d-inline">
                                                <label for="cover-upload" class="btn btn-primary btn-sm mb-0" style="cursor: pointer;">
                                                    <i class="mdi mdi-camera"></i> Change Cover
                                                </label>
                                                <input id="cover-upload" type="file" name="cover_photo" style="display: none;" onchange="this.form.submit()">
                                                <input type="hidden" name="upload_cover" value="1">
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- Profile Photo Section -->
                                    <div class="profile-photo-container" style="margin-top: -75px; padding-left: 30px; position: relative; z-index: 1;">
                                        <div style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden; border: 5px solid white; background-color: #f5f5f5;">
                                            <?php if (!empty($profile_photo)): ?>
                                                <img src="uploads/profile/<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 100%; background-color: #ddd; display: flex; align-items: center; justify-content: center;">
                                                    <span>No photo</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form method="post" enctype="multipart/form-data" class="mt-2">
                                            <label for="profile-upload" class="btn btn-primary btn-sm mb-0" style="cursor: pointer;">
                                                <i class="mdi mdi-camera"></i> Update Profile Photo
                                            </label>
                                            <input id="profile-upload" type="file" name="profile_photo" style="display: none;" onchange="this.form.submit()">
                                            <input type="hidden" name="upload_profile" value="1">
                                        </form>
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
    
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    // Enhanced file upload with preview
    document.addEventListener('DOMContentLoaded', function() {
        // Profile photo preview
        const profileUpload = document.getElementById('profile-upload');
        if (profileUpload) {
            profileUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Show preview before upload
                        if (confirm('Update profile photo?')) {
                            profileUpload.form.submit();
                        }
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        // Cover photo preview
        const coverUpload = document.getElementById('cover-upload');
        if (coverUpload) {
            coverUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Show preview before upload
                        if (confirm('Update cover photo?')) {
                            coverUpload.form.submit();
                        }
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        // Add loading state for uploads
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i>Uploading...';
                    submitBtn.disabled = true;
                }
            });
        });
    });

    // Android session protection for room users
    function setupRoomUserSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('🏨 Room User Profile: Setting up Android session protection');
        
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 1000);
        
        setInterval(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 45000);
    }

    <?php if ($user_role === 'room'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setupRoomUserSessionProtection();
    });
    <?php endif; ?>
    </script>
</body>
</html>
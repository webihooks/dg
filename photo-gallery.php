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
$user_sql = "SELECT name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name);
$user_stmt->fetch();
$user_stmt->close();

// Fetch photos from database - updated to include photo_gallery_path
$photos = array();
$photo_sql = "SELECT id, filename, photo_gallery_path, title, description, uploaded_at FROM photo_gallery WHERE user_id = ? ORDER BY uploaded_at DESC";
$photo_stmt = $conn->prepare($photo_sql);
$photo_stmt->bind_param("i", $user_id);
$photo_stmt->execute();
$result = $photo_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $photos[] = $row;
}
$photo_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Photo Gallery</title>
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
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .gallery-item:hover img {
            transform: scale(1.05);
        }
        .gallery-item-info {
            padding: 15px;
            background: #fff;
        }
        .upload-section {
            padding: 20px;
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .photo-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Photo Gallery</h4>
                            </div>
                            <div class="card-body">
                                <!-- Photo Upload Form -->
                                <div class="upload-section">
                                    <h5>Upload New Photo</h5>
                                    <form action="upload-photo.php" method="post" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <input type="file" class="form-control" name="photo" accept="image/*" required>
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="title" placeholder="Title (optional)">
                                        </div>
                                        <div class="mb-3">
                                            <textarea class="form-control" name="description" placeholder="Description (optional)"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Upload Photo</button>
                                    </form>
                                </div>
                                
                                <!-- Photo Gallery -->
                                <h5>Your Photos</h5>
                                <?php if (empty($photos)): ?>
                                    <p>No photos uploaded yet.</p>
                                <?php else: ?>
                                    <div class="gallery">
                                        <?php foreach ($photos as $photo): ?>
                                            <div class="gallery-item">
                                                <img src="<?php echo htmlspecialchars($photo['photo_gallery_path']); ?>" alt="<?php echo htmlspecialchars($photo['title']); ?>">
                                                <div class="gallery-item-info">
                                                    <?php if (!empty($photo['title'])): ?>
                                                        <h6><?php echo htmlspecialchars($photo['title']); ?></h6>
                                                    <?php endif; ?>
                                                    <?php if (!empty($photo['description'])): ?>
                                                        <p><?php echo htmlspecialchars($photo['description']); ?></p>
                                                    <?php endif; ?>
                                                    <small class="text-muted">Uploaded: <?php echo date('M j, Y', strtotime($photo['uploaded_at'])); ?></small>
                                                    <div class="photo-actions">
                                                        <a href="<?php echo htmlspecialchars($photo['photo_gallery_path']); ?>" class="btn btn-sm btn-primary" target="_blank" download="<?php echo htmlspecialchars($photo['filename']); ?>">Download</a>
                                                        <a href="delete-photo.php?id=<?php echo $photo['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this photo?')">Delete</a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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
</body>
</html>
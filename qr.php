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
// Fetch user role and name
$user_sql = "SELECT role, name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($role, $logged_in_name);
$user_stmt->fetch();
$user_stmt->close();

if ($role !== 'admin') {
    header("Location: index.php");
    exit();
}

// QR Code Generation for your current version
require 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;

// Initialize variables
$qrCodeImage = null;
$error = null;
$qrContent = '';

if (isset($_POST['submit_qr'])) {
    $qrContent = $_POST['qr_text'] ?? '';

    if (!empty($qrContent)) {
        try {
            // Create QR code with content
            $qrCode = new QrCode($qrContent);
            
            // Set size
            $qrCode->setSize(800);
            
            // Set margin
            $qrCode->setMargin(20);
            
            // Set colors using Color objects
            $qrCode->setForegroundColor(new Color(0, 0, 0));
            $qrCode->setBackgroundColor(new Color(255, 255, 255));

            $writer = new PngWriter();
            $qrCodeResult = $writer->write($qrCode);
            $qrCodeImage = 'data:image/png;base64,' . base64_encode($qrCodeResult->getString());

            // Add logo to QR code if exists
            if ($qrCodeImage && file_exists('assets/images/logo.png')) {
                try {
                    // Remove the data URL prefix
                    $qrData = base64_decode(str_replace('data:image/png;base64,', '', $qrCodeImage));
                    
                    // Create image resources
                    $qrImage = imagecreatefromstring($qrData);
                    $logoImage = imagecreatefrompng('assets/images/logo.png');
                    
                    if ($qrImage && $logoImage) {
                        // Get dimensions
                        $qrWidth = imagesx($qrImage);
                        $qrHeight = imagesy($qrImage);
                        $logoWidth = imagesx($logoImage);
                        $logoHeight = imagesy($logoImage);
                        
                        // Calculate new logo size (20% of QR code size)
                        $newLogoWidth = intval($qrWidth * 0.2);
                        $newLogoHeight = intval($qrHeight * 0.2);
                        
                        // Calculate position (center)
                        $logoX = intval(($qrWidth - $newLogoWidth) / 2);
                        $logoY = intval(($qrHeight - $newLogoHeight) / 2);
                        
                        // Create a transparent background for the resized logo
                        $resizedLogo = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
                        imagealphablending($resizedLogo, false);
                        imagesavealpha($resizedLogo, true);
                        $transparent = imagecolorallocatealpha($resizedLogo, 0, 0, 0, 127);
                        imagefill($resizedLogo, 0, 0, $transparent);
                        
                        // Resize logo with transparency preservation
                        imagecopyresampled(
                            $resizedLogo, $logoImage,
                            0, 0, 0, 0,
                            $newLogoWidth, $newLogoHeight,
                            $logoWidth, $logoHeight
                        );
                        
                        // Merge logo onto QR code
                        imagecopymerge(
                            $qrImage, $resizedLogo,
                            $logoX, $logoY, 0, 0,
                            $newLogoWidth, $newLogoHeight,
                            100
                        );
                        
                        // Output the final image
                        ob_start();
                        imagepng($qrImage);
                        $finalImage = ob_get_clean();
                        $qrCodeImage = 'data:image/png;base64,' . base64_encode($finalImage);
                        
                        // Clean up
                        imagedestroy($qrImage);
                        imagedestroy($logoImage);
                        imagedestroy($resizedLogo);
                    }
                } catch (Exception $e) {
                    // If logo addition fails, keep the original QR code
                    error_log("Logo addition failed: " . $e->getMessage());
                    // Continue with the original QR code without logo
                }
            }

        } catch (Exception $e) {
            $error = "QR Code generation failed: " . $e->getMessage();
        }
    } else {
        $error = "Please enter some text or URL.";
    }
}

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>QR Code | Admin</title>
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
</head>
<body>
<div class="wrapper">
    <?php include 'toolbar.php'; ?>
    <?php include 'admin_menu.php'; ?>

    <div class="page-content">
        <div class="container">
            <div class="row">
                <div class="col-xl-9">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">QR Code Generator</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>

                            <form method="post">
                                <div class="form-group mb-2">
                                    <label class="form-label" for="qr_text">Enter text or URL</label>
                                    <input type="text" class="form-control" id="qr_text" name="qr_text" 
                                           placeholder="Enter text or URL" value="<?php echo htmlspecialchars($qrContent); ?>" required>
                                </div>
                                <button type="submit" name="submit_qr" class="btn btn-primary">
                                    <i class="ri-qr-code-line me-1"></i> Generate QR Code
                                </button>
                                <a href="qr.php" class="btn btn-secondary">Reset</a>
                            </form>

                            <?php if ($qrCodeImage): ?>
                                <div class="mt-4">
                                    <h5 class="mb-2">Generated QR Code</h5>
                                    <div class="d-flex align-items-center">
                                        <div class="me-4">
                                            <img src="<?php echo $qrCodeImage; ?>" alt="QR Code" class="img-thumbnail" style="max-width: 200px;">
                                        </div>
                                        <div>
                                            <p class="mb-1"><strong>Content:</strong> <?php echo htmlspecialchars($qrContent); ?></p>
                                            <p class="mb-1 text-success">
                                                <i class="ri-checkbox-circle-line me-1"></i> 
                                                <?php echo file_exists('assets/images/logo.png') ? 'Logo included' : 'Logo not found'; ?>
                                            </p>
                                            <div class="mt-2">
                                                <a href="<?php echo $qrCodeImage; ?>" download="deegeecard-qrcode.png" class="btn btn-sm btn-outline-primary">
                                                    <i class="ri-download-line me-1"></i> Download QR Code
                                                </a>
                                            </div>
                                        </div>
                                    </div>
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
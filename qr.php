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

// QR Code Generation for Endroid v6.0.7
require 'vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;

// Initialize variables
$qrCodeImage = null;
$error = null;
$qrContent = '';

if (isset($_POST['submit_qr'])) {
    $qrContent = $_POST['qr_text'] ?? '';

    if (!empty($qrContent)) {
        try {
            $builder = Builder::create()
                ->data($qrContent)
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->size(800)
                ->margin(20)
                ->foregroundColor(new Color(0, 0, 0))
                ->backgroundColor(new Color(255, 255, 255));

            // Add logo if exists
            if (file_exists('assets/images/logo.png')) {
                $builder->logoPath('assets/images/logo.png')
                        ->logoResizeToWidth(200)
                        ->logoResizeToHeight(200);
            }

            $qrCodeResult = $builder->build();
            $qrCodeImage = 'data:image/png;base64,' . base64_encode($qrCodeResult->getString());

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
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
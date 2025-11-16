<?php
// generate_qr.php - Simple version without logo
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: image/png');
header('Access-Control-Allow-Origin: *');

$content = $_GET['content'] ?? '';

if (empty($content)) {
    // Return a transparent 1x1 pixel if no content
    $im = imagecreatetruecolor(1, 1);
    imagesavealpha($im, true);
    $color = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $color);
    imagepng($im);
    imagedestroy($im);
    exit;
}

try {
    // Check if Endroid QR library is available
    if (file_exists('vendor/autoload.php')) {
        require 'vendor/autoload.php';
        
        use Endroid\QrCode\QrCode;
        use Endroid\QrCode\Writer\PngWriter;
        use Endroid\QrCode\Color\Color;

        // Create QR code
        $qrCode = new QrCode($content);
        $qrCode->setSize(300);
        $qrCode->setMargin(10);
        $qrCode->setForegroundColor(new Color(0, 0, 0));
        $qrCode->setBackgroundColor(new Color(255, 255, 255));

        $writer = new PngWriter();
        $qrCodeResult = $writer->write($qrCode);
        
        // Output the QR code
        echo $qrCodeResult->getString();
        
    } else {
        // Fallback to Google Charts API
        $qrUrl = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($content) . '&choe=UTF-8';
        $imageData = file_get_contents($qrUrl);
        if ($imageData !== false) {
            echo $imageData;
        } else {
            throw new Exception("Failed to generate QR code");
        }
    }
    
} catch (Exception $e) {
    // Return error image
    $im = imagecreatetruecolor(300, 300);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $white);
    imagestring($im, 5, 50, 140, 'QR Code', $black);
    imagepng($im);
    imagedestroy($im);
}
?>
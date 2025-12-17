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
$message = '';
$error = '';

// Create card_designs directory if it doesn't exist
$designs_dir = 'card_designs/';
if (!is_dir($designs_dir)) {
    mkdir($designs_dir, 0755, true);
}

// Function to clean WhatsApp URL and show only number
function cleanPhoneNumber($phone) {
    if (!$phone) return '';
    
    $clean = str_replace([
        'https://wa.me/', 'wa.me/', 'http://wa.me/',
        'https://wa.me/91', 'wa.me/91', 'http://wa.me/91'
    ], '', $phone);
    
    $clean = strtok($clean, '?');
    
    if (substr($clean, 0, 2) === '91' && strlen($clean) > 10) {
        $clean = substr($clean, 2);
    }
    
    $clean = preg_replace('/[^\d+]/', '', $clean);
    return $clean;
}

// Safe htmlspecialchars wrapper to handle null values
function safe_htmlspecialchars($value, $default = '') {
    if ($value === null) {
        return $default;
    }
    return htmlspecialchars($value);
}

// Fetch user data with theme information
$sql_user = "SELECT u.name, u.phone, u.role, p.profile_photo, 
                    b.business_name, b.business_address, b.designation, b.website,
                    s.WhatsApp, pu.profile_url, t.primary_color, t.secondary_color
             FROM users u
             LEFT JOIN profile_cover_photo p ON u.id = p.user_id
             LEFT JOIN business_info b ON u.id = b.user_id
             LEFT JOIN social_link s ON u.id = s.user_id
             LEFT JOIN profile_url_details pu ON u.id = pu.user_id
             LEFT JOIN theme t ON u.id = t.user_id
             WHERE u.id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

// Fetch user name and role separately
$sql_name = "SELECT name, role FROM users WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$stmt_name->bind_result($user_name, $user_role);
$stmt_name->fetch();
$stmt_name->close();

// Fetch saved cards
$sql_saved_cards = "SELECT id, card_type, file_path, uploaded_at 
                    FROM user_cards 
                    WHERE user_id = ? AND card_type IN ('front', 'back')
                    ORDER BY uploaded_at DESC";
$stmt_saved = $conn->prepare($sql_saved_cards);
$stmt_saved->bind_param("i", $user_id);
$stmt_saved->execute();
$saved_cards_result = $stmt_saved->get_result();
$saved_cards = [];
while ($row = $saved_cards_result->fetch_assoc()) {
    $saved_cards[] = $row;
}
$stmt_saved->close();

// Build QR content with domain prefix - FIXED NULL CHECK
$qr_content = '';
if (!empty($user_data['profile_url'])) {
    $qr_content = 'www.deegeecard.com/' . ($user_data['profile_url'] ?? '');
} elseif (!empty($user_data['website'])) {
    $qr_content = $user_data['website'] ?? '';
}

// Build website URL for front design - FIXED NULL CHECK
$website_front = '';
if (!empty($user_data['website'])) {
    $website_front = $user_data['website'] ?? '';
} elseif (!empty($user_data['profile_url'])) {
    $website_front = 'www.deegeecard.com/' . ($user_data['profile_url'] ?? '');
} else {
    $website_front = 'www.deegeecard.com';
}

$clean_phone1 = cleanPhoneNumber($user_data['phone'] ?? '');
$clean_phone2 = cleanPhoneNumber($user_data['WhatsApp'] ?? '');

// Handle design save with image generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_design'])) {
    try {
        // Generate unique timestamp for this design
        $timestamp = time();
        
        // Generate front card image
        $front_filename = "card_{$user_id}_front_{$timestamp}.png";
        $front_filepath = $designs_dir . $front_filename;
        
        // Generate back card image
        $back_filename = "card_{$user_id}_back_{$timestamp}.png";
        $back_filepath = $designs_dir . $back_filename;
        
        // For now, we'll save the design data and generate images via JavaScript
        // In a real implementation, you might use a server-side image generation library
        
        $design_data = json_encode([
            'front_design' => [
                'cmyk' => [
                    'c' => $_POST['front_cmyk_c'] ?? 0,
                    'm' => $_POST['front_cmyk_m'] ?? 0,
                    'y' => $_POST['front_cmyk_y'] ?? 0,
                    'k' => $_POST['front_cmyk_k'] ?? 0
                ],
                'secondary_color' => $_POST['secondary_color'] ?? '#fb8933',
                'qr_content' => $_POST['qr_content'] ?? '',
                'scan_text' => $_POST['scan_text'] ?? 'SCAN ME TO ORDER',
                'website_text' => $_POST['website_text'] ?? '',
                'business_name' => $_POST['business_name_front'] ?? ($user_data['business_name'] ?? 'Business Name'),
                'text_colors' => [
                    'business_name' => $_POST['business_name_front_color'] ?? '#ffffff',
                    'website' => $_POST['website_front_color'] ?? '#ffffff'
                ]
            ],
            'back_design' => [
                'cmyk' => [
                    'c' => $_POST['back_cmyk_c'] ?? 0,
                    'm' => $_POST['back_cmyk_m'] ?? 0,
                    'y' => $_POST['back_cmyk_y'] ?? 0,
                    'k' => $_POST['back_cmyk_k'] ?? 0
                ],
                'secondary_color' => $_POST['secondary_color_back'] ?? '#fb8933',
                'address' => $_POST['business_address'] ?? '',
                'phone1' => $_POST['phone1'] ?? '',
                'phone2' => $_POST['phone2'] ?? '',
                'business_name' => $_POST['business_name_back'] ?? ($user_data['business_name'] ?? 'Business Name'),
                'text_colors' => [
                    'business_name' => $_POST['business_name_back_color'] ?? '#ffffff',
                    'address' => $_POST['address_color'] ?? '#ffffff',
                    'contact' => $_POST['contact_color'] ?? '#ffffff'
                ]
            ]
        ]);

        // Save design data
        $sql_design = "INSERT INTO user_cards (user_id, card_type, file_path, uploaded_at) 
                       VALUES (?, 'design_data', ?, NOW()) 
                       ON DUPLICATE KEY UPDATE file_path = ?";
        $stmt_design = $conn->prepare($sql_design);
        $stmt_design->bind_param("iss", $user_id, $design_data, $design_data);
        $stmt_design->execute();
        $stmt_design->close();
        
        $message = "Design data saved successfully! Use the export buttons to generate and save card images.";

        
    } catch (Exception $e) {
        $error = "Failed to save design: " . $e->getMessage();
    }
}

// Handle card image save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_card_image'])) {
    $card_type = $_POST['card_type'];
    $image_data = $_POST['image_data'];
    
    // Remove data URL prefix
    $image_data = str_replace('data:image/png;base64,', '', $image_data);
    $image_data = str_replace(' ', '+', $image_data);
    
    $timestamp = time();
    $filename = "card_{$user_id}_{$card_type}_{$timestamp}.png";
    $filepath = $designs_dir . $filename;
    
    if (file_put_contents($filepath, base64_decode($image_data))) {
        // Save to database
        $sql_save = "INSERT INTO user_cards (user_id, card_type, file_path, uploaded_at) 
                     VALUES (?, ?, ?, NOW())";
        $stmt_save = $conn->prepare($sql_save);
        $stmt_save->bind_param("iss", $user_id, $card_type, $filepath);
        
        if ($stmt_save->execute()) {
            $message = ucfirst($card_type) . " card saved successfully!";
            
            // Auto-refresh after 2 seconds
            echo '<script>
                setTimeout(function() {
                    window.location.href = "card_design.php";
                }, 2000);
            </script>';
        } else {
            $error = "Failed to save card record: " . $stmt_save->error;
        }
        $stmt_save->close();
    } else {
        $error = "Failed to save card image file.";
    }
}

// Handle card deletion
if (isset($_GET['delete_card'])) {
    $card_id = $_GET['delete_card'];
    
    // Get file path before deletion
    $sql_get_file = "SELECT file_path FROM user_cards WHERE id = ? AND user_id = ?";
    $stmt_get = $conn->prepare($sql_get_file);
    $stmt_get->bind_param("ii", $card_id, $user_id);
    $stmt_get->execute();
    $stmt_get->bind_result($file_path);
    $stmt_get->fetch();
    $stmt_get->close();
    
    // Delete from database
    $sql_delete = "DELETE FROM user_cards WHERE id = ? AND user_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("ii", $card_id, $user_id);
    
    if ($stmt_delete->execute()) {
        // Delete physical file
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        $message = "Card deleted successfully!";
    } else {
        $error = "Failed to delete card.";
    }
    $stmt_delete->close();
    
    // Refresh page to show updated list
    header("Location: card_design.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Business Card Designer</title>
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
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
.saved-cards-section{margin-top:30px;padding:20px;background:#f8f9fa;border-radius:10px;border:1px solid #e9ecef}.card-thumbnail{width:100%;max-width:200px;height:120px;object-fit:cover;border:2px solid #dee2e6;border-radius:8px;transition:.3s}.card-thumbnail:hover{border-color:#007bff;transform:scale(1.05)}.card-action-buttons{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}.card-action-buttons .btn{flex:1;min-width:80px;font-size:12px;padding:6px 12px}.empty-state{text-align:center;padding:40px 20px;color:#6c757d}.empty-state i{font-size:48px;margin-bottom:15px;color:#dee2e6}.business-name-back,.business-name-front{text-transform:uppercase;word-wrap:break-word}.designer-container{display:grid;grid-template-columns:350px 1fr;gap:30px;margin-top:20px}.controls-panel{background:#f8f9fa;padding:25px;border-radius:15px;border:1px solid #e9ecef;height:fit-content}.card-preview-container{display:flex;flex-direction:column;align-items:center;gap:30px;width:100%}.business-card{width:630px;height:390px;position:relative;overflow:hidden;transition:.3s;box-shadow:0 8px 30px rgba(0,0,0,.15);background:#000}.business-card:hover{transform:translateY(-5px);box-shadow:0 12px 40px rgba(0,0,0,.2)}.color-controls{margin-bottom:25px;padding:15px;background:#fff;border-radius:10px;border:1px solid #ddd}.cmyk-picker,.form-group{margin-bottom:15px}.cmyk-picker label{display:block;margin-bottom:8px;font-weight:500}.color-preview{width:100%;height:60px;border:2px solid #ddd;margin-top:15px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#333}.tab-content{margin-top:20px}.export-controls{margin-top:25px;display:flex;gap:12px;flex-wrap:wrap}.export-controls .btn{transition:.3s;border-radius:8px;font-weight:500;flex:1;min-width:120px}.export-controls .btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}.card-element{position:absolute;user-select:none;font-family:Arial,sans-serif}.logo-container{position:absolute;overflow:hidden;display:flex;align-items:center;justify-content:center}.logo-container img,.qr-code-container img{width:100%;height:100%;object-fit:contain}.text-controls{margin:20px 0;padding:15px;background:#fff;border-radius:10px;border:1px solid #ddd}.form-group label{font-weight:500;margin-bottom:5px;display:block}.card-preview-wrapper{position:relative;width:100%;display:flex;justify-content:center;min-height:200px}.card-label{position:absolute;top:-30px;left:50%;transform:translateX(-50%);background:#007bff;color:#fff;padding:5px 15px;border-radius:20px;font-size:14px;font-weight:700;z-index:10}.better_view{display:none;background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:8px;margin-bottom:20px;text-align:center;font-weight:500}.better_view i{margin-right:8px;color:#f39c12}.back-card,.front-card{background:#000;color:#fff}.business-name-front{position:absolute;top:23px;left:30px;font-size:20px;font-weight:700;color:#fff}.qr-code-container,.scan-text{position:absolute;left:50%;width:240px;margin-left:-120px}.contact-item i,.scan-text,.website-front i{color:#fb8933}.scan-text{bottom:64px;font-size:22px;text-align:center;z-index:99;letter-spacing:.02em;font-weight:700}.website-front{position:absolute;bottom:25px;right:30px;font-size:16px;opacity:.9;display:flex;align-items:center;gap:8px;color:#fff}.qr-code-container{top:50%;height:260px;background:#fff;border-radius:8px;padding:8px 8px 30px;display:flex;align-items:center;justify-content:center;margin-top:-130px}.address-section,.business-name-back,.contact-info{left:290px;position:absolute;color:#fff}.qr-button-group{display:flex;gap:10px;align-items:flex-start}.color-input-group .form-control,.qr-button-group .form-control{flex:1}.qr-button-group .btn{white-space:nowrap}.business-name-back{top:75px;font-size:20px;font-weight:700;text-align:left;max-width:300px}.address-section{top:135px;font-size:16px;line-height:1.6;max-width:300px;display:flex;align-items:flex-start;gap:10px;text-align:left}.address-section i{margin-top:2px;color:#fb8933;flex-shrink:0}.contact-item,.logo-back{align-items:center;display:flex}.contact-info{bottom:50px;font-size:18px;line-height:1.8;text-align:right}.contact-item{gap:10px;margin-bottom:5px;justify-content:flex-end}.contact-item i{flex-shrink:0}.logo-back{position:absolute;top:0;left:40px;width:220px;height:350px;border-radius:0 0 30px 30px;background-color:#fb8933;justify-content:center;padding:20px}.logo-back img{width:100%;height:auto;object-fit:contain;border-radius:0 0 25px 25px}.nav-tabs .nav-link.active{font-weight:700;border-bottom:3px solid #007bff}.saved-designs{margin-top:20px;padding:15px;background:#fff;border-radius:10px;border:1px solid #ddd}.card_mobile_scanner{background:url(images/card_mobile_scanner.png) center top/100% auto no-repeat;height:310px;position:absolute;right:0;bottom:55px;width:310px}.card_logo{background:url(images/card_logo.png) center top/100% auto no-repeat;width:140px;height:35px;position:absolute;left:30px;bottom:20px}.bottom_line,.top_line{background:#fb8933;height:35px;position:absolute;width:435px}.top_line{left:0;top:20px;border-radius:0 30px 30px 0}.bottom_line{right:0;bottom:20px;border-radius:30px 0 0 30px}.front_logo{width:130px;height:130px;right:30px;top:20px}.front_logo img{width:100%;height:100%}.color-input-group{display:flex;align-items:center;gap:10px;margin-bottom:10px}.color-input-group .form-control-color{width:50px;height:38px;padding:3px}.color-preview-small{width:30px;height:30px;display:inline-block;border:1px solid #ddd;margin-left:10px;vertical-align:middle;border-radius:4px}.mobile-menu-toggle{display:none;background:#007bff;color:#fff;border:none;padding:12px 15px;border-radius:8px;margin-bottom:20px;width:100%;font-weight:500;font-size:16px}.text-color-controls{margin-top:15px;padding:15px;background:#fff;border-radius:10px;border:1px solid #ddd;margin-bottom:15px}.text-color-controls .form-group{margin-bottom:10px}.text-color-controls .color-input-group{margin-bottom:0}@media (max-width:1200px){.designer-container{grid-template-columns:300px 1fr;gap:20px}.business-card{width:500px;height:310px}.qr-code-container{width:180px;height:200px;margin-left:-90px;margin-top:-100px}.logo-back{width:180px;height:280px}.business-name-front{max-width:200px;font-size:18px}.address-section,.business-name-back{max-width:250px;left:250px}.business-name-back{font-size:18px}.contact-info{left:250px}.website-front{left:240px}}@media (max-width:992px){.designer-container{grid-template-columns:1fr;gap:20px}.controls-panel.active,.mobile-menu-toggle{display:block}.controls-panel{display:none;margin-top:15px}.card-preview-container{order:-1;margin-bottom:20px}.business-card{width:100%;max-width:450px;height:280px;margin:0 auto}.qr-code-container{width:150px;height:170px;margin-left:-75px;margin-top:-85px}.logo-back{width:150px;height:240px;left:30px}.business-name-front{max-width:180px;font-size:16px}.business-name-back{max-width:220px;font-size:16px;left:200px;top:60px}.address-section{left:200px;top:110px;max-width:220px;font-size:14px}.contact-info{left:200px;bottom:40px;font-size:16px}.website-front{left:200px;font-size:14px}.scan-text{font-size:18px;bottom:50px}.export-controls{flex-direction:column}.export-controls .btn{width:100%}}@media (max-width:768px){.better_view{display:flex;align-items:center;justify-content:center}.business-card{max-width:400px;height:250px}.qr-code-container{width:120px;height:140px;margin-left:-60px;margin-top:-70px}.logo-back{width:120px;height:200px;left:20px}.business-name-front{max-width:150px;font-size:14px;top:15px;left:20px}.business-name-back{max-width:180px;font-size:14px;left:160px;top:50px}.address-section{left:160px;top:90px;max-width:180px;font-size:12px}.contact-info{left:160px;bottom:30px;font-size:14px}.website-front{left:160px;font-size:12px;bottom:20px}.scan-text{font-size:16px;bottom:40px;width:200px;margin-left:-100px}.card_mobile_scanner{width:180px;height:150px;bottom:45px}.front_logo{width:100px;height:100px;right:20px;top:15px}.bottom_line,.top_line{height:25px}.card_logo{width:120px;height:30px;left:20px;bottom:15px}.text-color-controls{padding:10px}}@media (max-width:576px){.better_view{padding:12px;font-size:14px;margin-bottom:15px}.business-card{max-width:350px;height:220px}.qr-code-container{width:100px;height:120px;margin-left:-50px;margin-top:-60px}.logo-back{width:100px;height:170px;left:15px}.business-name-front{max-width:130px;font-size:12px;top:12px;left:15px}.business-name-back{max-width:150px;font-size:12px;left:130px;top:40px}.address-section{left:130px;top:75px;max-width:150px;font-size:11px}.contact-info{left:130px;bottom:25px;font-size:12px}.website-front{left:130px;font-size:11px;bottom:15px}.scan-text{font-size:14px;bottom:35px;width:180px;margin-left:-90px}.card_mobile_scanner{width:150px;height:120px;bottom:40px}.front_logo{width:80px;height:80px;right:15px;top:12px}.bottom_line,.top_line{height:20px}.top_line{top:15px}.bottom_line{bottom:15px}.card_logo{width:100px;height:25px;left:15px;bottom:12px}.color-input-group{flex-direction:column;align-items:stretch}.color-input-group .form-control-color{width:100%;height:45px}.color-preview-small{margin-left:0;margin-top:5px;width:100%;height:30px}.nav-tabs{flex-direction:column}.nav-tabs .nav-item{width:100%}.nav-tabs .nav-link{text-align:center}.text-color-controls{padding:8px}}@media (max-width:400px){.better_view{padding:10px;font-size:13px;margin-bottom:12px}.business-card{max-width:300px;height:190px}.qr-code-container{width:80px;height:100px;margin-left:-40px;margin-top:-50px}.logo-back{width:80px;height:140px;left:10px}.business-name-front{max-width:110px;font-size:11px}.business-name-back{max-width:130px;font-size:11px;left:110px}.address-section{left:110px;top:65px;max-width:130px;font-size:10px}.contact-info{left:110px;font-size:11px}.website-front{left:110px;font-size:10px}.scan-text{font-size:12px;bottom:30px}.card_mobile_scanner{width:120px;height:100px}}.business-card{min-height:190px}@media print{.better_view,.card-label,.controls-panel,.export-controls,.mobile-menu-toggle{display:none!important}.designer-container{grid-template-columns:1fr!important}.business-card{box-shadow:none!important;break-inside:avoid}}

    .color-preview {
        display: none;
    }
    .text-controls {
        margin: 3px 0;
    }
    .color-controls, .text-color-controls {
        margin-bottom: 3px;
    }
    .text-color-controls {
        margin-top: 3px;
    }
    .cartoon_bg {
        background: url(images/cartoon_bg.png) center top/100% auto no-repeat;
        width: 140px;
        height: 230px;
        position: absolute;
        left: 30px;
        bottom: 80px;
    }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        if ($user_role === 'room') {
            include 'room_management_menu.php';
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header position-relative">
                                <h4 class="card-title">Business Card Designer</h4>
                                <p class="text-muted mb-0">Create professional business cards with front and back designs</p>
                            </div>
                            <div class="card-body">
                                <?php if ($message): ?>
                                    <div class="alert alert-success"><?php echo safe_htmlspecialchars($message); ?></div>
                                <?php endif; ?>
                                
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo safe_htmlspecialchars($error); ?></div>
                                <?php endif; ?>

                                <!-- Better View Message - Shows only on mobile/tablet -->
                                <div class="better_view">
                                    <i class="ri-computer-line"></i>
                                    For the best experience, please view this card design on a desktop or laptop.
                                </div>

                                <!-- Mobile Menu Toggle -->
                                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                                    <i class="ri-settings-3-line"></i> Design Controls
                                </button>

                                <div class="designer-container">
                                    <!-- Controls Panel -->
                                    <div class="controls-panel" id="controlsPanel">
                                        <ul class="nav nav-tabs mb-1">
                                            <li class="nav-item">
                                                <a class="nav-link active" data-bs-toggle="tab" href="#front-controls">Front Design</a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link" data-bs-toggle="tab" href="#back-controls">Back Design</a>
                                            </li>
                                        </ul>

                                        <div class="tab-content">
                                            <!-- Front Design Controls -->
                                            <div class="tab-pane fade show active" id="front-controls">
                                                <h6>Front Card Design</h6>
                                                
                                                <div class="text-controls">
                                                    <div class="form-group">
                                                        <label>Business Name (Front)</label>
                                                        <input type="text" class="form-control" id="business_name_front" 
                                                               value="<?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Business Name'); ?>" 
                                                               placeholder="Enter business name for front side">
                                                    </div>
                                                </div>
                                                

                                                
                                                <div class="color-controls">
                                                    <label class="form-label">Card Background Color</label>
                                                    <div class="color-input-group">
                                                        <input type="color" class="form-control form-control-color" id="front_color_picker" 
                                                               value="#000000" title="Choose front background color">
                                                        <input type="text" class="form-control" id="front_color_text" 
                                                               value="#000000" pattern="^#[a-fA-F0-9]{6}$">
                                                        <span class="color-preview-small" id="front_color_preview" 
                                                              style="background-color: #000000"></span>
                                                    </div>
                                                    <div class="color-sliders" style="display: none;">
                                                        <div class="color-slider">
                                                            <label>
                                                                Cyan: 
                                                                <input type="range" id="front_cmyk_c" min="0" max="100" value="0" class="form-range">
                                                                <span id="front_c_val" class="badge bg-info">0%</span>
                                                            </label>
                                                        </div>
                                                        <div class="color-slider">
                                                            <label>
                                                                Magenta: 
                                                                <input type="range" id="front_cmyk_m" min="0" max="100" value="0" class="form-range">
                                                                <span id="front_m_val" class="badge bg-danger">0%</span>
                                                            </label>
                                                        </div>
                                                        <div class="color-slider">
                                                            <label>
                                                                Yellow: 
                                                                <input type="range" id="front_cmyk_y" min="0" max="100" value="0" class="form-range">
                                                                <span id="front_y_val" class="badge bg-warning">0%</span>
                                                            </label>
                                                        </div>
                                                        <div class="color-slider">
                                                            <label>
                                                                Black: 
                                                                <input type="range" id="front_cmyk_k" min="0" max="100" value="0" class="form-range">
                                                                <span id="front_k_val" class="badge bg-dark">0%</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="color-preview" id="frontColorDisplay">
                                                        Front Background Preview
                                                    </div>
                                                </div>





                                                <div class="color-controls">
                                                    <label class="form-label">Secondary Color (Lines, Text, Icons, Logo Background)</label>
                                                    <div class="color-input-group">
                                                        <input type="color" class="form-control form-control-color" id="secondary_color_picker" 
                                                               value="#fb8933" title="Choose secondary color">
                                                        <input type="text" class="form-control" id="secondary_color_text" 
                                                               value="#fb8933" pattern="^#[a-fA-F0-9]{6}$">
                                                        <span class="color-preview-small" id="secondary_color_preview" 
                                                              style="background-color: #fb8933"></span>
                                                    </div>
                                                    <div class="color-preview" id="secondaryColorDisplay" style="background-color: #fb8933; color: white;">
                                                        Secondary Color: #fb8933
                                                    </div>
                                                </div>



                                                <!-- Text Color Controls for Front -->
                                                <div class="text-color-controls">
                                                    <h6>Text Colors (Front)</h6>
                                                    <div class="form-group">
                                                        <label>Business Name Color</label>
                                                        <div class="color-input-group">
                                                            <input type="color" class="form-control form-control-color" id="business_name_front_color_picker" 
                                                                   value="#ffffff" title="Choose business name color">
                                                            <input type="text" class="form-control" id="business_name_front_color_text" 
                                                                   value="#ffffff" pattern="^#[a-fA-F0-9]{6}$">
                                                            <span class="color-preview-small" id="business_name_front_color_preview" 
                                                                  style="background-color: #ffffff"></span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Website Color</label>
                                                        <div class="color-input-group">
                                                            <input type="color" class="form-control form-control-color" id="website_front_color_picker" 
                                                                   value="#ffffff" title="Choose website color">
                                                            <input type="text" class="form-control" id="website_front_color_text" 
                                                                   value="#ffffff" pattern="^#[a-fA-F0-9]{6}$">
                                                            <span class="color-preview-small" id="website_front_color_preview" 
                                                                  style="background-color: #ffffff"></span>
                                                        </div>
                                                    </div>
                                                </div>



                                                

                                                <div class="text-controls">
                                                    <div class="form-group">
                                                        <label>QR Code Content (URL or Text)</label>
                                                        <div class="mb-1">
                                                            <input type="text" class="form-control" id="qr_content" 
                                                                   value="<?php echo safe_htmlspecialchars($qr_content); ?>" 
                                                                   placeholder="Enter URL or text for QR code">
                                                        </div>
                                                        <div class="mb-3">
                                                            <button style="width:100%;" type="button" class="btn btn-primary" id="generateQRBtn">
                                                                <i class="ri-qr-code-line"></i> Generate QR
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Scan Text</label>
                                                        <input type="text" class="form-control" id="scan_text" 
                                                               value="SCAN ME TO ORDER" placeholder="Enter scan text">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Website URL</label>
                                                        <input type="text" class="form-control" id="website_text" 
                                                               value="<?php echo safe_htmlspecialchars($website_front); ?>" 
                                                               placeholder="Enter website">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Back Design Controls -->
                                            <div class="tab-pane fade" id="back-controls">
                                                <h6>Back Card Design</h6>
                                                
                                                <div class="text-controls">
                                                    <div class="form-group">
                                                        <label>Business Name (Back)</label>
                                                        <input type="text" class="form-control" id="business_name_back" 
                                                               value="<?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Business Name'); ?>" 
                                                               placeholder="Enter business name for back side">
                                                    </div>
                                                </div>
                                                
                                                
                                                
                                                <div class="color-controls">
                                                    <label class="form-label">Card Background Color</label>
                                                    <div class="color-input-group">
                                                        <input type="color" class="form-control form-control-color" id="back_color_picker" 
                                                               value="#000000" title="Choose back background color">
                                                        <input type="text" class="form-control" id="back_color_text" 
                                                               value="#000000" pattern="^#[a-fA-F0-9]{6}$">
                                                        <span class="color-preview-small" id="back_color_preview" 
                                                              style="background-color: #000000"></span>
                                                    </div>
                                                    <div class="color-sliders" style="display: none;">
                                                        <div class="color-slider">
                                                            <label>
                                                                Cyan: 
                                                                <input type="range" id="back_cmyk_c" min="0" max="100" value="0" class="form-range">
                                                                <span id="back_c_val" class="badge bg-info">0%</span>
                                                            </label>
                                                        </div>
                                                        <div class="color-slider">
                                                            <label>
                                                                Magenta: 
                                                                <input type="range" id="back_cmyk_m" min="0" max="100" value="0" class="form-range">
                                                                <span id="back_m_val" class="badge bg-danger">0%</span>
                                                            </label>
                                                        </div>
                                                        <div class="color-slider">
                                                            <label>
                                                                Yellow: 
                                                                <input type="range" id="back_cmyk_y" min="0" max="100" value="0" class="form-range">
                                                                <span id="back_y_val" class="badge bg-warning">0%</span>
                                                            </label>
                                                        </div>
                                                        <div class="color-slider">
                                                            <label>
                                                                Black: 
                                                                <input type="range" id="back_cmyk_k" min="0" max="100" value="0" class="form-range">
                                                                <span id="back_k_val" class="badge bg-dark">0%</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="color-preview" id="backColorDisplay">
                                                        Back Background Preview
                                                    </div>
                                                </div>

                                                <div class="color-controls">
                                                    <label class="form-label">Secondary Color (Icons, Logo Background)</label>
                                                    <div class="color-input-group">
                                                        <input type="color" class="form-control form-control-color" id="secondary_color_picker_back" 
                                                               value="#fb8933" title="Choose secondary color for back">
                                                        <input type="text" class="form-control" id="secondary_color_text_back" 
                                                               value="#fb8933" pattern="^#[a-fA-F0-9]{6}$">
                                                        <span class="color-preview-small" id="secondary_color_preview_back" 
                                                              style="background-color: #fb8933"></span>
                                                    </div>
                                                    <div class="color-preview" id="secondaryColorDisplayBack" style="background-color: #fb8933; color: white;">
                                                        Secondary Color: #fb8933
                                                    </div>
                                                </div>

                                                <!-- Text Color Controls for Back -->
                                                <div class="text-color-controls">
                                                    <h6>Text Colors (Back)</h6>
                                                    <div class="form-group">
                                                        <label>Business Name Color</label>
                                                        <div class="color-input-group">
                                                            <input type="color" class="form-control form-control-color" id="business_name_back_color_picker" 
                                                                   value="#ffffff" title="Choose business name color">
                                                            <input type="text" class="form-control" id="business_name_back_color_text" 
                                                                   value="#ffffff" pattern="^#[a-fA-F0-9]{6}$">
                                                            <span class="color-preview-small" id="business_name_back_color_preview" 
                                                                  style="background-color: #ffffff"></span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Address Color</label>
                                                        <div class="color-input-group">
                                                            <input type="color" class="form-control form-control-color" id="address_color_picker" 
                                                                   value="#ffffff" title="Choose address color">
                                                            <input type="text" class="form-control" id="address_color_text" 
                                                                   value="#ffffff" pattern="^#[a-fA-F0-9]{6}$">
                                                            <span class="color-preview-small" id="address_color_preview" 
                                                                  style="background-color: #ffffff"></span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Contact Info Color</label>
                                                        <div class="color-input-group">
                                                            <input type="color" class="form-control form-control-color" id="contact_color_picker" 
                                                                   value="#ffffff" title="Choose contact info color">
                                                            <input type="text" class="form-control" id="contact_color_text" 
                                                                   value="#ffffff" pattern="^#[a-fA-F0-9]{6}$">
                                                            <span class="color-preview-small" id="contact_color_preview" 
                                                                  style="background-color: #ffffff"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="text-controls">
                                                    <div class="form-group">
                                                        <label>Business Address</label>
                                                        <textarea class="form-control" id="business_address" rows="3" 
                                                                  placeholder="Enter business address"><?php echo safe_htmlspecialchars($user_data['business_address'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Phone Number</label>
                                                        <input type="text" class="form-control" id="phone1" 
                                                               value="<?php echo safe_htmlspecialchars($clean_phone1); ?>" 
                                                               placeholder="Enter primary phone">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>WhatsApp Number</label>
                                                        <input type="text" class="form-control" id="phone2" 
                                                               value="<?php echo safe_htmlspecialchars($clean_phone2); ?>" 
                                                               placeholder="Enter WhatsApp number">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Export Controls -->
                                        <div class="export-controls">
                                            <button onclick="exportFrontCard('png')" class="btn btn-primary">
                                                <i class="ri-download-line"></i> Export Front PNG
                                            </button>
                                            <button onclick="exportBackCard('png')" class="btn btn-success">
                                                <i class="ri-download-line"></i> Export Back PNG
                                            </button>
                                            <button onclick="saveCardImage('front')" class="btn btn-info">
                                                <i class="ri-save-line"></i> Save Front Card
                                            </button>
                                            <button onclick="saveCardImage('back')" class="btn btn-warning">
                                                <i class="ri-save-line"></i> Save Back Card
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Card Previews -->
                                    <div class="card-preview-container">
                                        <!-- Front Card Preview -->
                                        <div id="front_card" class="card-preview-wrapper">
                                            <div class="card-label">FRONT SIDE</div>
                                            <div class="business-card front-card mt-3 mb-3" id="frontBusinessCard">

                                                <span class="card_mobile_scanner"></span>
                                                <span class="cartoon_bg"></span>
                                                <span class="card_logo"></span>
                                                <span class="top_line"></span>
                                                <span class="bottom_line"></span>

                                                <!-- Logo -->
                                                <?php if (!empty($user_data['profile_photo'])): ?>
                                                    <div class="logo-container front_logo">
                                                        <img src="/uploads/profile/<?php echo safe_htmlspecialchars($user_data['profile_photo']); ?>" 
                                                             alt="Logo" id="frontCardLogo">
                                                    </div>
                                                <?php endif; ?>

                                                <!-- QR Code -->
                                                <div class="qr-code-container" id="qrCodeContainer">
                                                    <img src="" alt="QR Code" id="frontCardQR" style="display: none;">
                                                </div>
                                                
                                                <!-- Business Name -->
                                                <div class="business-name-front" id="businessNameFront">
                                                    <?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Business Name'); ?>
                                                </div>
                                                
                                                <!-- Scan Text -->
                                                <div class="scan-text" id="scanText">
                                                    SCAN ME TO ORDER
                                                </div>
                                                
                                                <!-- Website -->
                                                <div class="website-front" id="websiteFront">
                                                    <?php echo safe_htmlspecialchars($website_front); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Back Card Preview -->
                                        <div class="card-preview-wrapper">
                                            <div class="card-label">BACK SIDE</div>
                                            <div class="business-card back-card mt-3" id="backBusinessCard">
                                                <!-- Logo -->
                                                <?php if (!empty($user_data['profile_photo'])): ?>
                                                    <div class="logo-back logo-container" id="backLogoContainer">
                                                        <img src="/uploads/profile/<?php echo safe_htmlspecialchars($user_data['profile_photo']); ?>" 
                                                             alt="Logo" id="backCardLogo">
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Business Name -->
                                                <div class="business-name-back" id="businessNameBack">
                                                    <?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Business Name'); ?>
                                                </div>
                                                
                                                <!-- Address -->
                                                <div class="address-section" id="addressSection">
                                                    <i class="ri-map-pin-line"></i>
                                                    <span><?php echo nl2br(safe_htmlspecialchars($user_data['business_address'] ?? "Shop No 07, Ramdev Ritu Height\nJP North Rd, Mira Bhayandar\nMira Road - East\nMaharashtra 401107")); ?></span>
                                                </div>
                                                
                                                <!-- Contact Info -->
                                                <div class="contact-info" id="contactInfo">
                                                    <?php if (!empty($clean_phone1)): ?>
                                                        <div class="contact-item">
                                                            <i class="ri-phone-line"></i>
                                                            <span><?php echo safe_htmlspecialchars($clean_phone1); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($clean_phone2)): ?>
                                                        <div class="contact-item">
                                                            <i class="ri-whatsapp-line"></i>
                                                            <span><?php echo safe_htmlspecialchars($clean_phone2); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 text-center">
                                            <small class="text-muted">Card Size: 3.54 × 2.17 inches (Standard Business Card) • High Resolution Export</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Saved Cards Section -->
                                <div class="saved-cards-section">
                                    <h5 class="mb-4">
                                        <i class="ri-folder-open-line"></i> Saved Cards
                                        <small class="text-muted">(<?php echo count($saved_cards); ?> cards)</small>
                                    </h5>
                                    
                                    <?php if (empty($saved_cards)): ?>
                                        <div class="empty-state">
                                            <i class="ri-inbox-line"></i>
                                            <h6>No saved cards yet</h6>
                                            <p class="mb-0">Design and save your business cards to see them here.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($saved_cards as $card): ?>
                                                <div class="col-md-6 col-lg-6 mb-4">
                                                    <div class="card h-100">
                                                        <div class="card-body text-center">
                                                            <img src="<?php echo safe_htmlspecialchars($card['file_path']); ?>" 
                                                                 alt="<?php echo ucfirst($card['card_type']); ?> Card" 
                                                                 class="card-thumbnail mb-3"
                                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDIwMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMTIwIiBmaWxsPSIjRjBGMEYwIi8+Cjx0ZXh0IHg9IjEwMCIgeT0iNjAiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OTk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSI+Q2FyZCBJbWFnZTwvdGV4dD4KPC9zdmc+'">
                                                            <h6 class="card-title text-capitalize">
                                                                <?php echo ucfirst($card['card_type']); ?> Card
                                                            </h6>
                                                            <p class="text-muted small mb-2">
                                                                Saved: <?php echo date('M j, Y g:i A', strtotime($card['uploaded_at'])); ?>
                                                            </p>
                                                            <div class="card-action-buttons">
                                                                <a href="<?php echo safe_htmlspecialchars($card['file_path']); ?>" 
                                                                   class="btn btn-primary btn-sm" 
                                                                   target="_blank" 
                                                                   download="<?php echo basename($card['file_path']); ?>">
                                                                    <i class="ri-download-line"></i> Download
                                                                </a>
                                                                <a href="<?php echo safe_htmlspecialchars($card['file_path']); ?>" 
                                                                   class="btn btn-info btn-sm" 
                                                                   target="_blank">
                                                                    <i class="ri-eye-line"></i> View
                                                                </a>
                                                                <a href="?delete_card=<?php echo $card['id']; ?>" 
                                                                   class="btn btn-danger btn-sm" 
                                                                   onclick="return confirm('Are you sure you want to delete this card?')">
                                                                    <i class="ri-delete-bin-line"></i> Delete
                                                                </a>
                                                            </div>
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
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Hidden form for saving design data -->
    <form id="saveDesignForm" method="post" style="display: none;">
        <input type="hidden" name="save_design" value="1">
        <!-- Front Design Data -->
        <input type="hidden" name="front_cmyk_c" id="save_front_cmyk_c" value="0">
        <input type="hidden" name="front_cmyk_m" id="save_front_cmyk_m" value="0">
        <input type="hidden" name="front_cmyk_y" id="save_front_cmyk_y" value="0">
        <input type="hidden" name="front_cmyk_k" id="save_front_cmyk_k" value="0">
        <input type="hidden" name="secondary_color" id="save_secondary_color" value="#fb8933">
        <input type="hidden" name="qr_content" id="save_qr_content" value="<?php echo safe_htmlspecialchars($qr_content); ?>">
        <input type="hidden" name="scan_text" id="save_scan_text" value="SCAN ME TO ORDER">
        <input type="hidden" name="website_text" id="save_website_text" value="<?php echo safe_htmlspecialchars($website_front); ?>">
        <input type="hidden" name="business_name_front" id="save_business_name_front" value="<?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Business Name'); ?>">
        <input type="hidden" name="business_name_front_color" id="save_business_name_front_color" value="#ffffff">
        <input type="hidden" name="website_front_color" id="save_website_front_color" value="#ffffff">
        
        <!-- Back Design Data -->
        <input type="hidden" name="back_cmyk_c" id="save_back_cmyk_c" value="0">
        <input type="hidden" name="back_cmyk_m" id="save_back_cmyk_m" value="0">
        <input type="hidden" name="back_cmyk_y" id="save_back_cmyk_y" value="0">
        <input type="hidden" name="back_cmyk_k" id="save_back_cmyk_k" value="0">
        <input type="hidden" name="secondary_color_back" id="save_secondary_color_back" value="#fb8933">
        <input type="hidden" name="business_address" id="save_business_address" value="<?php echo safe_htmlspecialchars($user_data['business_address'] ?? ''); ?>">
        <input type="hidden" name="phone1" id="save_phone1" value="<?php echo safe_htmlspecialchars($clean_phone1); ?>">
        <input type="hidden" name="phone2" id="save_phone2" value="<?php echo safe_htmlspecialchars($clean_phone2); ?>">
        <input type="hidden" name="business_name_back" id="save_business_name_back" value="<?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Business Name'); ?>">
        <input type="hidden" name="business_name_back_color" id="save_business_name_back_color" value="#ffffff">
        <input type="hidden" name="address_color" id="save_address_color" value="#ffffff">
        <input type="hidden" name="contact_color" id="save_contact_color" value="#ffffff">
    </form>

    <!-- Hidden form for saving card images -->
    <form id="saveCardImageForm" method="post" style="display: none;">
        <input type="hidden" name="save_card_image" value="1">
        <input type="hidden" name="card_type" id="save_card_type">
        <input type="hidden" name="image_data" id="save_image_data">
    </form>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            designer = new BusinessCardDesigner();
            
            // Initialize mobile menu state
            if (window.innerWidth <= 992) {
                document.getElementById('controlsPanel').classList.remove('active');
            } else {
                document.getElementById('controlsPanel').classList.add('active');
            }
        });

        class BusinessCardDesigner {
            constructor() {
                this.frontCard = document.getElementById('frontBusinessCard');
                this.backCard = document.getElementById('backBusinessCard');
                this.qrCodeImg = document.getElementById('frontCardQR');
                this.generateQRBtn = document.getElementById('generateQRBtn');
                this.backLogoContainer = document.getElementById('backLogoContainer');
                this.mobileMenuToggle = document.getElementById('mobileMenuToggle');
                this.controlsPanel = document.getElementById('controlsPanel');
                
                // Text color elements
                this.businessNameFrontElement = document.getElementById('businessNameFront');
                this.websiteFrontElement = document.getElementById('websiteFront');
                this.businessNameBackElement = document.getElementById('businessNameBack');
                this.addressSectionElement = document.getElementById('addressSection');
                this.contactInfoElement = document.getElementById('contactInfo');
                
                this.initEventListeners();
                this.updateFrontColor();
                this.updateBackColor();
                this.updateSecondaryColor();
                this.updateSecondaryColorBack();
                this.updateTextElements();
                this.updateTextColors();
                this.generateQRCode();
            }

            initEventListeners() {
                // Mobile menu toggle
                this.mobileMenuToggle.addEventListener('click', () => {
                    this.controlsPanel.classList.toggle('active');
                });

                // Front color picker sync
                document.getElementById('front_color_picker').addEventListener('input', () => {
                    document.getElementById('front_color_text').value = document.getElementById('front_color_picker').value;
                    document.getElementById('front_color_preview').style.backgroundColor = document.getElementById('front_color_picker').value;
                    this.updateFrontColor();
                });
                
                document.getElementById('front_color_text').addEventListener('input', () => {
                    const value = document.getElementById('front_color_text').value;
                    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
                        document.getElementById('front_color_picker').value = value;
                        document.getElementById('front_color_preview').style.backgroundColor = value;
                        this.updateFrontColor();
                    }
                });

                // Back color picker sync
                document.getElementById('back_color_picker').addEventListener('input', () => {
                    document.getElementById('back_color_text').value = document.getElementById('back_color_picker').value;
                    document.getElementById('back_color_preview').style.backgroundColor = document.getElementById('back_color_picker').value;
                    this.updateBackColor();
                });
                
                document.getElementById('back_color_text').addEventListener('input', () => {
                    const value = document.getElementById('back_color_text').value;
                    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
                        document.getElementById('back_color_picker').value = value;
                        document.getElementById('back_color_preview').style.backgroundColor = value;
                        this.updateBackColor();
                    }
                });

                // Secondary color picker sync
                document.getElementById('secondary_color_picker').addEventListener('input', () => {
                    document.getElementById('secondary_color_text').value = document.getElementById('secondary_color_picker').value;
                    document.getElementById('secondary_color_preview').style.backgroundColor = document.getElementById('secondary_color_picker').value;
                    this.updateSecondaryColor();
                });
                
                document.getElementById('secondary_color_text').addEventListener('input', () => {
                    const value = document.getElementById('secondary_color_text').value;
                    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
                        document.getElementById('secondary_color_picker').value = value;
                        document.getElementById('secondary_color_preview').style.backgroundColor = value;
                        this.updateSecondaryColor();
                    }
                });

                // Secondary color back picker sync
                document.getElementById('secondary_color_picker_back').addEventListener('input', () => {
                    document.getElementById('secondary_color_text_back').value = document.getElementById('secondary_color_picker_back').value;
                    document.getElementById('secondary_color_preview_back').style.backgroundColor = document.getElementById('secondary_color_picker_back').value;
                    this.updateSecondaryColorBack();
                });
                
                document.getElementById('secondary_color_text_back').addEventListener('input', () => {
                    const value = document.getElementById('secondary_color_text_back').value;
                    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
                        document.getElementById('secondary_color_picker_back').value = value;
                        document.getElementById('secondary_color_preview_back').style.backgroundColor = value;
                        this.updateSecondaryColorBack();
                    }
                });

                // Text color pickers for front
                this.setupTextColorPicker('business_name_front_color_picker', 'business_name_front_color_text', 'business_name_front_color_preview');
                this.setupTextColorPicker('website_front_color_picker', 'website_front_color_text', 'website_front_color_preview');
                
                // Text color pickers for back
                this.setupTextColorPicker('business_name_back_color_picker', 'business_name_back_color_text', 'business_name_back_color_preview');
                this.setupTextColorPicker('address_color_picker', 'address_color_text', 'address_color_preview');
                this.setupTextColorPicker('contact_color_picker', 'contact_color_text', 'contact_color_preview');

                const textInputs = ['scan_text', 'website_text', 
                                   'business_address', 'phone1', 'phone2',
                                   'business_name_front', 'business_name_back'];
                textInputs.forEach(inputId => {
                    document.getElementById(inputId).addEventListener('input', () => {
                        this.updateTextElements();
                    });
                });

                // QR content input with debounce
                let qrTimeout;
                document.getElementById('qr_content').addEventListener('input', () => {
                    clearTimeout(qrTimeout);
                    qrTimeout = setTimeout(() => {
                        this.generateQRCode();
                    }, 500);
                });

                // Generate QR button
                this.generateQRBtn.addEventListener('click', () => {
                    this.generateQRCode();
                });

                // Close mobile menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (window.innerWidth <= 992 && 
                        !this.controlsPanel.contains(e.target) && 
                        !this.mobileMenuToggle.contains(e.target) &&
                        this.controlsPanel.classList.contains('active')) {
                        this.controlsPanel.classList.remove('active');
                    }
                });

                // Handle window resize
                window.addEventListener('resize', this.handleResize.bind(this));
            }

            setupTextColorPicker(colorPickerId, textInputId, previewId) {
                const colorPicker = document.getElementById(colorPickerId);
                const textInput = document.getElementById(textInputId);
                const preview = document.getElementById(previewId);

                colorPicker.addEventListener('input', () => {
                    textInput.value = colorPicker.value;
                    preview.style.backgroundColor = colorPicker.value;
                    this.updateTextColors();
                });
                
                textInput.addEventListener('input', () => {
                    const value = textInput.value;
                    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
                        colorPicker.value = value;
                        preview.style.backgroundColor = value;
                        this.updateTextColors();
                    }
                });
            }

            handleResize() {
                // Auto-close mobile menu on larger screens
                if (window.innerWidth > 992) {
                    this.controlsPanel.classList.add('active');
                } else {
                    this.controlsPanel.classList.remove('active');
                }
            }

            updateFrontColor() {
                const backgroundColor = document.getElementById('front_color_picker').value;
                
                // Update color preview display
                document.getElementById('frontColorDisplay').style.backgroundColor = backgroundColor;
                document.getElementById('frontColorDisplay').style.color = this.getContrastColor(backgroundColor);
                document.getElementById('frontColorDisplay').textContent = `Front: ${backgroundColor}`;
                
                // Update card background
                this.frontCard.style.cssText = `background: ${backgroundColor} !important; background-color: ${backgroundColor} !important;`;
            }

            updateBackColor() {
                const backgroundColor = document.getElementById('back_color_picker').value;
                
                // Update color preview display
                document.getElementById('backColorDisplay').style.backgroundColor = backgroundColor;
                document.getElementById('backColorDisplay').style.color = this.getContrastColor(backgroundColor);
                document.getElementById('backColorDisplay').textContent = `Back: ${backgroundColor}`;
                
                // Update card background
                this.backCard.style.cssText = `background: ${backgroundColor} !important; background-color: ${backgroundColor} !important;`;
            }

            updateSecondaryColor() {
                const secondaryColor = document.getElementById('secondary_color_picker').value;
                
                // Update color preview display
                document.getElementById('secondaryColorDisplay').style.backgroundColor = secondaryColor;
                document.getElementById('secondaryColorDisplay').style.color = this.getContrastColor(secondaryColor);
                document.getElementById('secondaryColorDisplay').textContent = `Secondary: ${secondaryColor}`;
                
                // Update front card elements with secondary color
                const topLine = document.querySelector('.top_line');
                const bottomLine = document.querySelector('.bottom_line');
                const scanText = document.querySelector('.scan-text');
                const frontIcons = document.querySelectorAll('.website-front i');
                
                if (topLine) topLine.style.backgroundColor = secondaryColor;
                if (bottomLine) bottomLine.style.backgroundColor = secondaryColor;
                if (scanText) scanText.style.color = secondaryColor;
                frontIcons.forEach(icon => {
                    icon.style.color = secondaryColor;
                });
            }

            updateSecondaryColorBack() {
                const secondaryColor = document.getElementById('secondary_color_picker_back').value;
                
                // Update color preview display
                document.getElementById('secondaryColorDisplayBack').style.backgroundColor = secondaryColor;
                document.getElementById('secondaryColorDisplayBack').style.color = this.getContrastColor(secondaryColor);
                document.getElementById('secondaryColorDisplayBack').textContent = `Secondary: ${secondaryColor}`;
                
                // Update back card icons with secondary color
                const backIcons = document.querySelectorAll('.back-card i');
                backIcons.forEach(icon => {
                    icon.style.color = secondaryColor;
                });
                
                // Update back logo container background color
                if (this.backLogoContainer) {
                    this.backLogoContainer.style.backgroundColor = secondaryColor;
                }
            }

            updateTextColors() {
                // Update front text colors
                if (this.businessNameFrontElement) {
                    const businessNameColor = document.getElementById('business_name_front_color_picker').value;
                    this.businessNameFrontElement.style.color = businessNameColor;
                }
                
                if (this.websiteFrontElement) {
                    const websiteColor = document.getElementById('website_front_color_picker').value;
                    this.websiteFrontElement.style.color = websiteColor;
                }
                
                // Update back text colors
                if (this.businessNameBackElement) {
                    const businessNameBackColor = document.getElementById('business_name_back_color_picker').value;
                    this.businessNameBackElement.style.color = businessNameBackColor;
                }
                
                if (this.addressSectionElement) {
                    const addressColor = document.getElementById('address_color_picker').value;
                    const addressText = this.addressSectionElement.querySelector('span');
                    if (addressText) {
                        addressText.style.color = addressColor;
                    }
                }
                
                if (this.contactInfoElement) {
                    const contactColor = document.getElementById('contact_color_picker').value;
                    const contactTexts = this.contactInfoElement.querySelectorAll('.contact-item span');
                    contactTexts.forEach(span => {
                        span.style.color = contactColor;
                    });
                }
            }

            getContrastColor(hexColor) {
                // Convert hex to RGB
                const r = parseInt(hexColor.slice(1, 3), 16);
                const g = parseInt(hexColor.slice(3, 5), 16);
                const b = parseInt(hexColor.slice(5, 7), 16);
                
                // Calculate luminance
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                
                // Return white for dark colors, black for light colors
                return luminance > 0.5 ? '#000000' : '#ffffff';
            }

            generateQRCode() {
                const qrContent = document.getElementById('qr_content').value.trim();
                
                if (qrContent) {
                    // Show loading state
                    this.generateQRBtn.innerHTML = '<i class="ri-loader-4-line"></i> Generating...';
                    this.generateQRBtn.disabled = true;
                    
                    // Generate QR code using the server-side endpoint
                    const qrUrl = `generate_qr.php?content=${encodeURIComponent(qrContent)}&logo=true&t=${new Date().getTime()}`;
                    
                    // Create new image to handle loading
                    const newImage = new Image();
                    newImage.onload = () => {
                        this.qrCodeImg.src = qrUrl;
                        this.qrCodeImg.style.display = 'block';
                        this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR';
                        this.generateQRBtn.disabled = false;
                    };
                    newImage.onerror = () => {
                        console.error('Failed to load QR code from:', qrUrl);
                        
                        // Fallback: Try without logo
                        const fallbackUrl = `generate_qr.php?content=${encodeURIComponent(qrContent)}&logo=false&t=${new Date().getTime()}`;
                        const fallbackImage = new Image();
                        fallbackImage.onload = () => {
                            this.qrCodeImg.src = fallbackUrl;
                            this.qrCodeImg.style.display = 'block';
                            this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR';
                            this.generateQRBtn.disabled = false;
                        };
                        fallbackImage.onerror = () => {
                            // Ultimate fallback: Use a QR code service
                            this.useExternalQRService(qrContent);
                        };
                        fallbackImage.src = fallbackUrl;
                    };
                    newImage.src = qrUrl;
                    
                } else {
                    this.qrCodeImg.style.display = 'none';
                    this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR';
                    this.generateQRBtn.disabled = false;
                }
            }

            useExternalQRService(content) {
                // Fallback to external QR code service
                const externalQRUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(content)}`;
                
                const externalImage = new Image();
                externalImage.onload = () => {
                    this.qrCodeImg.src = externalQRUrl;
                    this.qrCodeImg.style.display = 'block';
                    this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR';
                    this.generateQRBtn.disabled = false;
                };
                externalImage.onerror = () => {
                    this.qrCodeImg.style.display = 'none';
                    this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR';
                    this.generateQRBtn.disabled = false;
                    console.error('All QR code generation methods failed');
                };
                externalImage.src = externalQRUrl;
            }

            updateTextElements() {
                document.getElementById('scanText').textContent = 
                    document.getElementById('scan_text').value;
                
                const websiteFront = document.getElementById('websiteFront');
                websiteFront.innerHTML = `${document.getElementById('website_text').value}`;

                const addressSection = document.getElementById('addressSection');
                addressSection.innerHTML = `<i class="ri-map-pin-line"></i><span>${document.getElementById('business_address').value.replace(/\n/g, '<br>')}</span>`;
                
                // Update business names
                document.getElementById('businessNameFront').textContent = 
                    document.getElementById('business_name_front').value;
                document.getElementById('businessNameBack').textContent = 
                    document.getElementById('business_name_back').value;
                
                let contactHtml = '';
                const phone1 = this.cleanPhoneNumber(document.getElementById('phone1').value);
                const phone2 = this.cleanPhoneNumber(document.getElementById('phone2').value);
                
                if (phone1) {
                    contactHtml += `<div class="contact-item"><i class="ri-phone-line"></i><span>${phone1}</span></div>`;
                }
                if (phone2) {
                    contactHtml += `<div class="contact-item"><i class="ri-whatsapp-line"></i><span>${phone2}</span></div>`;
                }
                document.getElementById('contactInfo').innerHTML = contactHtml;
                
                // Update text colors after text content changes
                this.updateTextColors();
            }

            cleanPhoneNumber(phone) {
                if (!phone) return '';
                
                let clean = phone.replace(/https:\/\/wa\.me\/|wa\.me\/|http:\/\/wa\.me\/|https:\/\/wa\.me\/91|wa\.me\/91|http:\/\/wa\.me\/91/g, '');
                clean = clean.split('?')[0];
                
                if (clean.startsWith('91') && clean.length > 10) {
                    clean = clean.substring(2);
                }
                
                clean = clean.replace(/[^\d+]/g, '');
                return clean;
            }

            prepareSaveData() {
                document.getElementById('save_front_cmyk_c').value = document.getElementById('front_cmyk_c').value;
                document.getElementById('save_front_cmyk_m').value = document.getElementById('front_cmyk_m').value;
                document.getElementById('save_front_cmyk_y').value = document.getElementById('front_cmyk_y').value;
                document.getElementById('save_front_cmyk_k').value = document.getElementById('front_cmyk_k').value;
                document.getElementById('save_secondary_color').value = document.getElementById('secondary_color_picker').value;
                document.getElementById('save_qr_content').value = document.getElementById('qr_content').value;
                document.getElementById('save_scan_text').value = document.getElementById('scan_text').value;
                document.getElementById('save_website_text').value = document.getElementById('website_text').value;
                document.getElementById('save_business_name_front').value = document.getElementById('business_name_front').value;
                document.getElementById('save_business_name_front_color').value = document.getElementById('business_name_front_color_picker').value;
                document.getElementById('save_website_front_color').value = document.getElementById('website_front_color_picker').value;

                document.getElementById('save_back_cmyk_c').value = document.getElementById('back_cmyk_c').value;
                document.getElementById('save_back_cmyk_m').value = document.getElementById('back_cmyk_m').value;
                document.getElementById('save_back_cmyk_y').value = document.getElementById('back_cmyk_y').value;
                document.getElementById('save_back_cmyk_k').value = document.getElementById('back_cmyk_k').value;
                document.getElementById('save_secondary_color_back').value = document.getElementById('secondary_color_picker_back').value;
                document.getElementById('save_business_address').value = document.getElementById('business_address').value;
                document.getElementById('save_phone1').value = document.getElementById('phone1').value;
                document.getElementById('save_phone2').value = document.getElementById('phone2').value;
                document.getElementById('save_business_name_back').value = document.getElementById('business_name_back').value;
                document.getElementById('save_business_name_back_color').value = document.getElementById('business_name_back_color_picker').value;
                document.getElementById('save_address_color').value = document.getElementById('address_color_picker').value;
                document.getElementById('save_contact_color').value = document.getElementById('contact_color_picker').value;
            }
        }

        function exportFrontCard(format) {
            const card = document.getElementById('frontBusinessCard');
            
            html2canvas(card, {
                scale: 4,
                backgroundColor: null,
                logging: false,
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = `business-card-front.${format}`;
                link.href = canvas.toDataURL(`image/${format}`, 1.0);
                link.click();
            });
        }

        function exportBackCard(format) {
            const card = document.getElementById('backBusinessCard');
            
            html2canvas(card, {
                scale: 4,
                backgroundColor: null,
                logging: false,
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = `business-card-back.${format}`;
                link.href = canvas.toDataURL(`image/${format}`, 1.0);
                link.click();
            });
        }

        function saveCardImage(cardType) {
            const card = cardType === 'front' 
                ? document.getElementById('frontBusinessCard')
                : document.getElementById('backBusinessCard');
            
            // Show loading state
            const originalButton = event.target;
            const originalHTML = originalButton.innerHTML;
            originalButton.innerHTML = '<i class="ri-loader-4-line"></i> Saving...';
            originalButton.disabled = true;
            
            html2canvas(card, {
                scale: 4,
                backgroundColor: null,
                logging: false,
                useCORS: true
            }).then(canvas => {
                const imageData = canvas.toDataURL('image/png');
                
                // Submit the form with image data
                document.getElementById('save_card_type').value = cardType;
                document.getElementById('save_image_data').value = imageData;
                document.getElementById('saveCardImageForm').submit();
                
            }).catch(error => {
                console.error('Error generating card image:', error);
                alert('Error saving card image. Please try again.');
                
                // Restore button state
                originalButton.innerHTML = originalHTML;
                originalButton.disabled = false;
            });
        }

        function saveDesign() {
            designer.prepareSaveData();
            document.getElementById('saveDesignForm').submit();
        }
    </script>
</body>
</html>
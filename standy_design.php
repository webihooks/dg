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

// Create standy_designs directory if it doesn't exist
$designs_dir = 'standy_designs/';
if (!is_dir($designs_dir)) {
    mkdir($designs_dir, 0755, true);
}

// Safe htmlspecialchars wrapper to handle null values
function safe_htmlspecialchars($value, $default = '') {
    if ($value === null) {
        return $default;
    }
    return htmlspecialchars($value);
}

// Fetch user data with business information
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

// Build QR content with domain prefix
$qr_content = '';
if (!empty($user_data['profile_url'])) {
    $qr_content = 'www.deegeecard.com/' . ($user_data['profile_url'] ?? '');
} elseif (!empty($user_data['website'])) {
    $qr_content = $user_data['website'] ?? '';
}

// Build website URL for display
$website_display = '';
if (!empty($user_data['website'])) {
    $website_display = $user_data['website'] ?? '';
} elseif (!empty($user_data['profile_url'])) {
    $website_display = 'www.deegeecard.com/' . ($user_data['profile_url'] ?? '');
}

// Set default table text based on user role
$default_table_text = ($user_role === 'room') ? 'ROOM' : 'TABLE';

// Fetch saved standy designs from user_standy table
$sql_saved_designs = "SELECT id, design_type, file_path, design_data, created_at 
                      FROM user_standy 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC";
$stmt_saved = $conn->prepare($sql_saved_designs);
$stmt_saved->bind_param("i", $user_id);
$stmt_saved->execute();
$saved_designs_result = $stmt_saved->get_result();
$saved_designs = [];
while ($row = $saved_designs_result->fetch_assoc()) {
    $saved_designs[] = $row;
}
$stmt_saved->close();

// Handle design save with image generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_design'])) {
    try {
        // Generate design data
        $design_data = json_encode([
            'business_name' => $_POST['business_name'] ?? ($user_data['business_name'] ?? 'Business Name'),
            'scan_text' => $_POST['scan_text'] ?? 'SCAN TO ORDER',
            'table_text' => $_POST['table_text'] ?? $default_table_text,
            'website_url' => $_POST['website_url'] ?? $website_display,
            'primary_color' => $_POST['primary_color'] ?? '#fb8933',
            'secondary_color' => $_POST['secondary_color'] ?? '#ff0000',
            'text_color' => $_POST['text_color'] ?? '#FFFFFF',
            'qr_content' => $_POST['qr_content'] ?? $qr_content
        ]);

        // Save design data to user_standy table
        $sql_design = "INSERT INTO user_standy (user_id, design_type, file_path, design_data) 
                       VALUES (?, 'design_data', 'design_data', ?)";
        $stmt_design = $conn->prepare($sql_design);
        $stmt_design->bind_param("is", $user_id, $design_data);
        $stmt_design->execute();
        $stmt_design->close();
        
        $message = "Standy design data saved successfully! Use the export buttons to generate and save design images.";

    } catch (Exception $e) {
        $error = "Failed to save design: " . $e->getMessage();
    }
}

// Handle design image save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_design_image'])) {
    $design_type = $_POST['design_type'];
    $image_data = $_POST['image_data'];
    
    // Remove data URL prefix
    $image_data = str_replace('data:image/png;base64,', '', $image_data);
    $image_data = str_replace(' ', '+', $image_data);
    
    $timestamp = time();
    $filename = "standy_{$user_id}_{$timestamp}.png";
    $filepath = $designs_dir . $filename;
    
    if (file_put_contents($filepath, base64_decode($image_data))) {
        // Save to user_standy table
        $sql_save = "INSERT INTO user_standy (user_id, design_type, file_path) 
                     VALUES (?, 'front', ?)";
        $stmt_save = $conn->prepare($sql_save);
        $stmt_save->bind_param("is", $user_id, $filepath);
        
        if ($stmt_save->execute()) {
            $message = "Standy design saved successfully!";
            
            // Auto-refresh after 2 seconds
            echo '<script>
                setTimeout(function() {
                    window.location.href = "standy_design.php";
                }, 2000);
            </script>';
        } else {
            $error = "Failed to save design record: " . $stmt_save->error;
        }
        $stmt_save->close();
    } else {
        $error = "Failed to save design image file.";
    }
}

// Handle design deletion
if (isset($_GET['delete_design'])) {
    $design_id = $_GET['delete_design'];
    
    // Get file path before deletion
    $sql_get_file = "SELECT file_path FROM user_standy WHERE id = ? AND user_id = ?";
    $stmt_get = $conn->prepare($sql_get_file);
    $stmt_get->bind_param("ii", $design_id, $user_id);
    $stmt_get->execute();
    $stmt_get->bind_result($file_path);
    $stmt_get->fetch();
    $stmt_get->close();
    
    // Delete from database
    $sql_delete = "DELETE FROM user_standy WHERE id = ? AND user_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("ii", $design_id, $user_id);
    
    if ($stmt_delete->execute()) {
        // Delete physical file
        if ($file_path && file_exists($file_path) && $file_path !== 'design_data') {
            unlink($file_path);
        }
        $message = "Design deleted successfully!";
    } else {
        $error = "Failed to delete design.";
    }
    $stmt_delete->close();
    
    // Refresh page to show updated list
    header("Location: standy_design.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Standy Designer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        .designer-container{display:grid;grid-template-columns:350px 1fr;gap:30px;margin-top:20px}.controls-panel{background:#f8f9fa;padding:25px;border-radius:15px;border:1px solid #e9ecef;height:fit-content}.design-preview-container{display:flex;flex-direction:column;align-items:center;gap:30px;width:100%}.color-preview,.logo-container{ background: #fff; display:flex;align-items:center}.standy-design{width:400px;height:600px;position:relative;overflow:hidden;transition:.3s;box-shadow:0 8px 30px rgba(0,0,0,.15);background:#fb8933}.standy-design:hover{transform:translateY(-5px);box-shadow:0 12px 40px rgba(0,0,0,.2)}.color-controls{margin-bottom:25px;padding:15px;background:#fff;border-radius:10px;border:1px solid #ddd}.form-group{margin-bottom:15px}.color-preview{width:100%;height:60px;border:2px solid #ddd;margin-top:15px;border-radius:8px;justify-content:center;font-weight:700;color:#333}.export-controls{margin-top:25px;display:flex;gap:12px;flex-wrap:wrap}.export-controls .btn{transition:.3s;border-radius:8px;font-weight:500;flex:1;min-width:120px}.export-controls .btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.15)}.design-element{position:absolute;user-select:none;font-family:Arial,sans-serif}.text-controls{margin:20px 0;padding:15px;background:#fff;border-radius:10px;border:1px solid #ddd}.form-group label{font-weight:500;margin-bottom:5px;display:block}.design-preview-wrapper{position:relative;width:100%;display:flex;justify-content:center;min-height:200px}.design-label{position:absolute;top:-30px;left:50%;transform:translateX(-50%);background:#007bff;color:#fff;padding:5px 15px;border-radius:20px;font-size:14px;font-weight:700;z-index:10}.front-design{color:#fff}.logo-container{position:absolute;top:20px;left:50%;width:120px;height:120px;justify-content:center;overflow:hidden;z-index:3;margin-left:-60px;border-radius:50%;border:5px solid #fff}.business-name-main,.scan-text{left:0;right:0;text-align:center;z-index:3;position:absolute}.qr-code-container,.table-number-box{background:#fff;align-items:center;display:flex}.logo-container img{width:100%;height:100%;object-fit:cover}.business-name-main{top:140px;font-size:28px;font-weight:700;text-transform:uppercase}.scan-text{top:190px;font-size:16px;font-weight:600;letter-spacing:1px}.qr-code-container{position:absolute;top:220px;left:50%;transform:translateX(-50%);width:180px;height:180px;border-radius:8px;padding:10px;justify-content:center;z-index:3}.table-section,.website-url{position:absolute;left:0;right:0;z-index:3;text-align:center}.qr-code-container img{width:100%;height:100%;object-fit:contain}.website-url{top:400px;font-size:18px;font-weight:500}.table-section{top:430px;font-size:24px;font-weight:700;text-transform:uppercase}.card-logo,.half_circle,.table-number-box{position:absolute;left:50%;transform:translateX(-50%)}.table-number-box{top:460px;width:100px;height:60px;border:2px solid #fff;border-radius:5px;justify-content:center;font-size:18px;font-weight:700;color:#000;z-index:3}.card-logo{bottom:20px;width:140px;height:35px;background:url('images/card_logo.png') center/contain no-repeat;z-index:3}.color-input-group{display:flex;align-items:center;gap:10px;margin-bottom:10px}.better_view,.mobile-menu-toggle{margin-bottom:20px;font-weight:500}.color-input-group .form-control-color{width:50px;height:38px;padding:3px}.color-preview-small{width:30px;height:30px;display:inline-block;border:1px solid #ddd;margin-left:10px;vertical-align:middle;border-radius:4px}.mobile-menu-toggle{display:none;background:#007bff;color:#fff;border:none;padding:12px 15px;border-radius:8px;width:100%;font-size:16px}.saved-designs-section{margin-top:30px;padding:20px;background:#f8f9fa;border-radius:10px;border:1px solid #e9ecef}.design-thumbnail{width:100%;max-width:200px;height:300px;object-fit:cover;border:2px solid #dee2e6;border-radius:8px;transition:.3s}.design-thumbnail:hover{border-color:#007bff;transform:scale(1.05)}.design-action-buttons{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}.design-action-buttons .btn{flex:1;min-width:80px;font-size:12px;padding:6px 12px}.empty-state{text-align:center;padding:40px 20px;color:#6c757d}.empty-state i{font-size:48px;margin-bottom:15px;color:#dee2e6}.better_view{display:none;background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:8px;text-align:center}.better_view i{margin-right:8px;color:#f39c12}.half_circle{top:-305px;width:800px;height:800px;border-radius:50%;z-index:1;overflow:hidden;background:red}@media (max-width:1200px){.designer-container{grid-template-columns:320px 1fr;gap:25px}.standy-design{width:350px;height:525px}.half_circle{width:700px;height:700px}}@media (max-width:992px){.designer-container{grid-template-columns:1fr;gap:20px}.controls-panel.active,.mobile-menu-toggle{display:block}.controls-panel{display:none;margin-top:15px}.design-preview-container{order:-1;margin-bottom:20px}.standy-design{width:100%;max-width:400px;height:600px;margin:0 auto}.export-controls{flex-direction:column}.export-controls .btn{width:100%}.half_circle{width:600px;height:600px}}@media (max-width:768px){.better_view{display:flex;align-items:center;justify-content:center}.standy-design{max-width:350px;height:525px}.logo-container{top:25px;width:70px;height:70px;margin-left:-35px}.business-name-main{top:115px;font-size:24px}.scan-text{top:160px;font-size:14px}.qr-code-container{top:195px;width:160px;height:160px}.website-url{top:375px;font-size:13px}.table-section{top:405px;font-size:20px}.table-number-box{top:440px;width:100px;height:50px}.card-logo{bottom:15px}.half_circle{width:500px;height:500px}}@media (max-width:576px){.better_view{padding:12px;font-size:14px;margin-bottom:15px}.standy-design{max-width:300px;height:450px}.logo-container{top:20px;width:60px;height:60px;margin-left:-30px}.business-name-main{top:95px;font-size:20px}.scan-text{top:130px;font-size:13px}.qr-code-container{top:160px;width:140px;height:140px}.website-url{top:315px;font-size:12px}.table-section{top:340px;font-size:18px}.table-number-box{top:370px;width:90px;height:45px;font-size:16px}.card-logo{bottom:10px;width:120px;height:30px}.half_circle{width:400px;height:400px}.color-input-group{flex-direction:column;align-items:stretch}.color-input-group .form-control-color{width:100%;height:45px}.color-preview-small{margin-left:0;margin-top:5px;width:100%;height:30px}}@media print{.better_view,.controls-panel,.design-label,.export-controls,.mobile-menu-toggle{display:none!important}.designer-container{grid-template-columns:1fr!important}.standy-design{box-shadow:none!important;break-inside:avoid}}#standyDesign{position:relative}
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
                                <h4 class="card-title">Standy Designer</h4>
                                <p class="text-muted mb-0">Create professional Standy designs for your <?php echo ($user_role === 'room') ? 'hotel' : 'restaurant'; ?></p>
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
                                    For the best experience, please view this design on a desktop or laptop.
                                </div>

                                <!-- Mobile Menu Toggle -->
                                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                                    <i class="ri-settings-3-line"></i> Design Controls
                                </button>

                                <div class="designer-container">
                                    <!-- Controls Panel -->
                                    <div class="controls-panel" id="controlsPanel">
                                        <h6>Standy Design</h6>
                                        
                                        <div class="text-controls">
                                            <div class="form-group">
                                                <label>Business Name</label>
                                                <input type="text" class="form-control" id="business_name" 
                                                       value="<?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Company Name'); ?>" 
                                                       placeholder="Enter business name">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Scan Text</label>
                                                <input type="text" class="form-control" id="scan_text" 
                                                       value="SCAN TO ORDER" placeholder="Enter scan text">
                                            </div>

                                            <div class="form-group">
                                                <label>Website URL</label>
                                                <input type="text" class="form-control" id="website_url" 
                                                       value="<?php echo safe_htmlspecialchars($website_display); ?>" 
                                                       placeholder="Enter website URL">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label><?php echo ($user_role === 'room') ? 'Room' : 'Table'; ?> Text</label>
                                                <input type="text" class="form-control" id="table_text" 
                                                       value="<?php echo $default_table_text; ?>" 
                                                       placeholder="Enter <?php echo ($user_role === 'room') ? 'room' : 'table'; ?> text">
                                            </div>

                                            <div class="form-group">
                                                <label>QR Code Content</label>
                                                <input type="text" class="form-control" id="qr_content" 
                                                       value="<?php echo safe_htmlspecialchars($qr_content); ?>" 
                                                       placeholder="Enter URL or text for QR code">
                                                <button type="button" class="btn btn-primary mt-2" id="generateQRBtn" style="width:100%;">
                                                    <i class="ri-qr-code-line"></i> Generate QR Code
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="color-controls">
                                            <label class="form-label">Background Color</label>
                                            <div class="color-input-group">
                                                <input type="color" class="form-control form-control-color" id="primary_color_picker" 
                                                       value="#fb8933" title="Choose background color">
                                                <input type="text" class="form-control" id="primary_color_text" 
                                                       value="#fb8933" pattern="^#[a-fA-F0-9]{6}$">
                                                <span class="color-preview-small" id="primary_color_preview" 
                                                      style="background-color: #fb8933"></span>
                                            </div>
                                            <div class="color-preview" id="primaryColorDisplay" style="background-color: #fb8933; color: white;">
                                                Background Color: #fb8933
                                            </div>
                                        </div>

                                        <div class="color-controls">
                                            <label class="form-label">Secondary Color (Half Circle)</label>
                                            <div class="color-input-group">
                                                <input type="color" class="form-control form-control-color" id="secondary_color_picker" 
                                                       value="#ff0000" title="Choose secondary color for half circle">
                                                <input type="text" class="form-control" id="secondary_color_text" 
                                                       value="#ff0000" pattern="^#[a-fA-F0-9]{6}$">
                                                <span class="color-preview-small" id="secondary_color_preview" 
                                                      style="background-color: #ff0000"></span>
                                            </div>
                                            <div class="color-preview" id="secondaryColorDisplay" style="background-color: #ff0000; color: white;">
                                                Secondary Color: #ff0000
                                            </div>
                                        </div>

                                        <div class="color-controls">
                                            <label class="form-label">Text Color</label>
                                            <div class="color-input-group">
                                                <input type="color" class="form-control form-control-color" id="text_color_picker" 
                                                       value="#FFFFFF" title="Choose text color">
                                                <input type="text" class="form-control" id="text_color_text" 
                                                       value="#FFFFFF" pattern="^#[a-fA-F0-9]{6}$">
                                                <span class="color-preview-small" id="text_color_preview" 
                                                      style="background-color: #FFFFFF"></span>
                                            </div>
                                            <div class="color-preview" id="textColorDisplay" style="background-color: #FFFFFF; color: black;">
                                                Text Color: #FFFFFF
                                            </div>
                                        </div>

                                        <!-- Export Controls -->
                                        <div class="export-controls">
                                            <button onclick="exportDesign('png')" class="btn btn-primary">
                                                <i class="ri-download-line"></i> Export PNG
                                            </button>
                                            <button onclick="saveDesignImage()" class="btn btn-success">
                                                <i class="ri-save-line"></i> Save Design
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Design Preview -->
                                    <div class="design-preview-container">
                                        <div class="design-preview-wrapper">
                                            <div class="design-label">STANDY DESIGN</div>
                                            <div class="standy-design front-design mt-3 mb-3" id="standyDesign">
                                                <!-- Half Circle Background -->
                                                <div class="half_circle" id="halfCircle"></div>
                                                
                                                <!-- Logo -->
                                                <?php if (!empty($user_data['profile_photo'])): ?>
                                                    <div class="logo-container">
                                                        <img src="/uploads/profile/<?php echo safe_htmlspecialchars($user_data['profile_photo']); ?>" 
                                                             alt="Logo" id="standyLogo" crossorigin="anonymous">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="logo-container" style="background: #fff; display: flex; align-items: center; justify-content: center;">
                                                        <span style="color: #000; font-size: 12px; font-weight: bold;">LOGO</span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Business Name -->
                                                <div class="business-name-main" id="businessName">
                                                    <?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Company Name'); ?>
                                                </div>
                                                
                                                
                                                <!-- Scan Text -->
                                                <div class="scan-text" id="scanTextElement">
                                                    SCAN TO ORDER
                                                </div>
                                                
                                                <!-- QR Code -->
                                                <div class="qr-code-container" id="qrCodeContainer">
                                                    <img src="" alt="QR Code" id="designQR" style="display: none;" crossorigin="anonymous">
                                                </div>


                                                <!-- Website URL -->
                                                <div class="website-url" id="websiteUrl">
                                                    <?php echo safe_htmlspecialchars($website_display); ?>
                                                </div>
                                                
                                                <!-- Table/Room Text -->
                                                <div class="table-section" id="tableSection">
                                                    <?php echo $default_table_text; ?>
                                                </div>

                                                <!-- Table/Room Number Box -->
                                                <div class="table-number-box" id="tableNumberBox">
                                                    
                                                </div>




                                                <!-- Card Logo -->
                                                <div class="card-logo" id="cardLogo"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 text-center">
                                            <small class="text-muted">Standy Size: 4 × 6 inches • High Resolution Export</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Saved Designs Section -->
                                <div class="saved-designs-section">
                                    <h5 class="mb-4">
                                        <i class="ri-folder-open-line"></i> Saved Standy Designs
                                        <small class="text-muted">(<?php echo count($saved_designs); ?> designs)</small>
                                    </h5>
                                    
                                    <?php if (empty($saved_designs)): ?>
                                        <div class="empty-state">
                                            <i class="ri-inbox-line"></i>
                                            <h6>No saved designs yet</h6>
                                            <p class="mb-0">Design and save your Standy designs to see them here.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($saved_designs as $design): ?>
                                                <div class="col-md-6 col-lg-6 mb-4">
                                                    <div class="card h-100">
                                                        <div class="card-body text-center">
                                                            <?php if ($design['file_path'] !== 'design_data'): ?>
                                                                <img src="<?php echo safe_htmlspecialchars($design['file_path']); ?>" 
                                                                     alt="Standy Design" 
                                                                     class="design-thumbnail mb-3"
                                                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDIwMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMTIwIiBmaWxsPSIjRjBGMEYwIi8+Cjx0ZXh0IHg9IjEwMCIgeT0iNjAiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OTk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSI+U3RhbmR5IERlc2lnbjwvdGV4dD4KPC9zdmc+'">
                                                            <?php else: ?>
                                                                <div class="design-thumbnail mb-3 d-flex align-items-center justify-content-center bg-light">
                                                                    <i class="ri-settings-3-line text-muted" style="font-size: 24px;"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <h6 class="card-title">
                                                                Standy Design
                                                            </h6>
                                                            <p class="text-muted small mb-2">
                                                                Saved: <?php echo date('M j, Y g:i A', strtotime($design['created_at'])); ?>
                                                            </p>
                                                            <div class="design-action-buttons">
                                                                <?php if ($design['file_path'] !== 'design_data'): ?>
                                                                    <a href="<?php echo safe_htmlspecialchars($design['file_path']); ?>" 
                                                                       class="btn btn-primary btn-sm" 
                                                                       target="_blank" 
                                                                       download="<?php echo basename($design['file_path']); ?>">
                                                                        <i class="ri-download-line"></i> Download
                                                                    </a>
                                                                    <a href="<?php echo safe_htmlspecialchars($design['file_path']); ?>" 
                                                                       class="btn btn-info btn-sm" 
                                                                       target="_blank">
                                                                        <i class="ri-eye-line"></i> View
                                                                    </a>
                                                                <?php endif; ?>
                                                                <a href="?delete_design=<?php echo $design['id']; ?>" 
                                                                   class="btn btn-danger btn-sm" 
                                                                   onclick="return confirm('Are you sure you want to delete this design?')">
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
        <input type="hidden" name="business_name" id="save_business_name" value="<?php echo safe_htmlspecialchars($user_data['business_name'] ?? 'Company Name'); ?>">
        <input type="hidden" name="scan_text" id="save_scan_text" value="SCAN TO ORDER">
        <input type="hidden" name="website_url" id="save_website_url" value="<?php echo safe_htmlspecialchars($website_display); ?>">
        <input type="hidden" name="table_text" id="save_table_text" value="<?php echo $default_table_text; ?>">
        <input type="hidden" name="primary_color" id="save_primary_color" value="#fb8933">
        <input type="hidden" name="secondary_color" id="save_secondary_color" value="#ff0000">
        <input type="hidden" name="text_color" id="save_text_color" value="#FFFFFF">
        <input type="hidden" name="qr_content" id="save_qr_content" value="<?php echo safe_htmlspecialchars($qr_content); ?>">
    </form>

    <!-- Hidden form for saving design images -->
    <form id="saveDesignImageForm" method="post" style="display: none;">
        <input type="hidden" name="save_design_image" value="1">
        <input type="hidden" name="design_type" id="save_design_type" value="front">
        <input type="hidden" name="image_data" id="save_image_data">
    </form>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        class StandyDesigner {
            constructor() {
                this.design = document.getElementById('standyDesign');
                this.qrCodeImg = document.getElementById('designQR');
                this.mobileMenuToggle = document.getElementById('mobileMenuToggle');
                this.controlsPanel = document.getElementById('controlsPanel');
                this.generateQRBtn = document.getElementById('generateQRBtn');
                this.halfCircle = document.getElementById('halfCircle');
                this.userRole = '<?php echo $user_role; ?>';
                this.initEventListeners();
                this.updateColors();
                this.updateTextElements();
                this.generateQRCode();
            }

            initEventListeners() {
                // Mobile menu toggle
                this.mobileMenuToggle.addEventListener('click', () => {
                    this.controlsPanel.classList.toggle('active');
                });

                // Color picker sync for primary color
                document.getElementById('primary_color_picker').addEventListener('input', () => {
                    document.getElementById('primary_color_text').value = document.getElementById('primary_color_picker').value;
                    document.getElementById('primary_color_preview').style.backgroundColor = document.getElementById('primary_color_picker').value;
                    this.updateColors();
                });
                
                document.getElementById('primary_color_text').addEventListener('input', () => {
                    const value = document.getElementById('primary_color_text').value;
                    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
                        document.getElementById('primary_color_picker').value = value;
                        document.getElementById('primary_color_preview').style.backgroundColor = value;
                        this.updateColors();
                    }
                });

                // Color picker sync for secondary color
                document.getElementById('secondary_color_picker').addEventListener('input', () => {
                    document.getElementById('secondary_color_text').value = document.getElementById('secondary_color_picker').value;
                    document.getElementById('secondary_color_preview').style.backgroundColor = document.getElementById('secondary_color_picker').value;
                    this.updateColors();
                });
                
                document.getElementById('secondary_color_text').addEventListener('input', () => {
                    const value = document.getElementById('secondary_color_text').value;
                    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
                        document.getElementById('secondary_color_picker').value = value;
                        document.getElementById('secondary_color_preview').style.backgroundColor = value;
                        this.updateColors();
                    }
                });

                // Text color picker sync
                document.getElementById('text_color_picker').addEventListener('input', () => {
                    document.getElementById('text_color_text').value = document.getElementById('text_color_picker').value;
                    document.getElementById('text_color_preview').style.backgroundColor = document.getElementById('text_color_picker').value;
                    this.updateColors();
                });
                
                document.getElementById('text_color_text').addEventListener('input', () => {
                    const value = document.getElementById('text_color_text').value;
                    if (/^#[a-fA-F0-9]{6}$/.test(value)) {
                        document.getElementById('text_color_picker').value = value;
                        document.getElementById('text_color_preview').style.backgroundColor = value;
                        this.updateColors();
                    }
                });

                const textInputs = ['business_name', 'scan_text', 'website_url', 'table_text'];
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

            handleResize() {
                // Auto-close mobile menu on larger screens
                if (window.innerWidth > 992) {
                    this.controlsPanel.classList.add('active');
                } else {
                    this.controlsPanel.classList.remove('active');
                }
            }

            updateColors() {
                const backgroundColor = document.getElementById('primary_color_picker').value;
                const secondaryColor = document.getElementById('secondary_color_picker').value;
                const textColor = document.getElementById('text_color_picker').value;
                
                // Update color preview displays
                document.getElementById('primaryColorDisplay').style.backgroundColor = backgroundColor;
                document.getElementById('primaryColorDisplay').style.color = this.getContrastColor(backgroundColor);
                document.getElementById('primaryColorDisplay').textContent = `Background: ${backgroundColor}`;
                
                document.getElementById('secondaryColorDisplay').style.backgroundColor = secondaryColor;
                document.getElementById('secondaryColorDisplay').style.color = this.getContrastColor(secondaryColor);
                document.getElementById('secondaryColorDisplay').textContent = `Secondary: ${secondaryColor}`;
                
                document.getElementById('textColorDisplay').style.backgroundColor = textColor;
                document.getElementById('textColorDisplay').style.color = this.getContrastColor(textColor);
                document.getElementById('textColorDisplay').textContent = `Text: ${textColor}`;
                
                // Update design background
                this.design.style.backgroundColor = backgroundColor;
                
                // Update half circle with secondary color - REMOVED !important
                this.halfCircle.style.backgroundColor = secondaryColor;
                
                // Update text colors
                const textElements = this.design.querySelectorAll('.business-name-main, .scan-text, .website-url, .table-section');
                textElements.forEach(element => {
                    element.style.color = textColor;
                });
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
                    newImage.crossOrigin = "anonymous";
                    newImage.onload = () => {
                        this.qrCodeImg.src = qrUrl;
                        this.qrCodeImg.style.display = 'block';
                        this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR Code';
                        this.generateQRBtn.disabled = false;
                    };
                    newImage.onerror = () => {
                        console.error('Failed to load QR code from:', qrUrl);
                        
                        // Fallback: Try without logo
                        const fallbackUrl = `generate_qr.php?content=${encodeURIComponent(qrContent)}&logo=false&t=${new Date().getTime()}`;
                        const fallbackImage = new Image();
                        fallbackImage.crossOrigin = "anonymous";
                        fallbackImage.onload = () => {
                            this.qrCodeImg.src = fallbackUrl;
                            this.qrCodeImg.style.display = 'block';
                            this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR Code';
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
                    this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR Code';
                    this.generateQRBtn.disabled = false;
                }
            }

            useExternalQRService(content) {
                // Fallback to external QR code service
                const externalQRUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(content)}`;
                
                const externalImage = new Image();
                externalImage.crossOrigin = "anonymous";
                externalImage.onload = () => {
                    this.qrCodeImg.src = externalQRUrl;
                    this.qrCodeImg.style.display = 'block';
                    this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR Code';
                    this.generateQRBtn.disabled = false;
                };
                externalImage.onerror = () => {
                    this.qrCodeImg.style.display = 'none';
                    this.generateQRBtn.innerHTML = '<i class="ri-qr-code-line"></i> Generate QR Code';
                    this.generateQRBtn.disabled = false;
                    console.error('All QR code generation methods failed');
                };
                externalImage.src = externalQRUrl;
            }

            updateTextElements() {
                document.getElementById('businessName').textContent = 
                    document.getElementById('business_name').value;
                
                document.getElementById('scanTextElement').textContent = 
                    document.getElementById('scan_text').value;

                document.getElementById('websiteUrl').textContent = 
                    document.getElementById('website_url').value;
                
                document.getElementById('tableSection').textContent = 
                    document.getElementById('table_text').value;
            }

            prepareSaveData() {
                document.getElementById('save_business_name').value = document.getElementById('business_name').value;
                document.getElementById('save_scan_text').value = document.getElementById('scan_text').value;
                document.getElementById('save_website_url').value = document.getElementById('website_url').value;
                document.getElementById('save_table_text').value = document.getElementById('table_text').value;
                document.getElementById('save_primary_color').value = document.getElementById('primary_color_picker').value;
                document.getElementById('save_secondary_color').value = document.getElementById('secondary_color_picker').value;
                document.getElementById('save_text_color').value = document.getElementById('text_color_picker').value;
                document.getElementById('save_qr_content').value = document.getElementById('qr_content').value;
            }
        }

        let designer;

        document.addEventListener('DOMContentLoaded', function() {
            designer = new StandyDesigner();
            
            // Initialize mobile menu state
            if (window.innerWidth <= 992) {
                document.getElementById('controlsPanel').classList.remove('active');
            } else {
                document.getElementById('controlsPanel').classList.add('active');
            }
        });

        function exportDesign(format) {
            const design = document.getElementById('standyDesign');
            
            // Show loading state
            const originalButton = event.target;
            const originalHTML = originalButton.innerHTML;
            originalButton.innerHTML = '<i class="ri-loader-4-line"></i> Exporting...';
            originalButton.disabled = true;
            
            html2canvas(design, {
                scale: 4,
                backgroundColor: null,
                logging: false,
                useCORS: true,
                allowTaint: true,
                onclone: function(clonedDoc) {
                    // Ensure all images are loaded in the cloned document
                    const images = clonedDoc.querySelectorAll('img');
                    images.forEach(img => {
                        img.crossOrigin = "anonymous";
                    });
                }
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = `standy-design.${format}`;
                link.href = canvas.toDataURL(`image/${format}`, 1.0);
                link.click();
                
                // Restore button state
                originalButton.innerHTML = originalHTML;
                originalButton.disabled = false;
            }).catch(error => {
                console.error('Error exporting design:', error);
                alert('Error exporting design. Please try again.');
                
                // Restore button state
                originalButton.innerHTML = originalHTML;
                originalButton.disabled = false;
            });
        }

        function saveDesignImage() {
            const design = document.getElementById('standyDesign');
            
            // Show loading state
            const originalButton = event.target;
            const originalHTML = originalButton.innerHTML;
            originalButton.innerHTML = '<i class="ri-loader-4-line"></i> Saving...';
            originalButton.disabled = true;
            
            html2canvas(design, {
                scale: 4,
                backgroundColor: null,
                logging: false,
                useCORS: true,
                allowTaint: true,
                onclone: function(clonedDoc) {
                    // Ensure all images are loaded in the cloned document
                    const images = clonedDoc.querySelectorAll('img');
                    images.forEach(img => {
                        img.crossOrigin = "anonymous";
                    });
                }
            }).then(canvas => {
                const imageData = canvas.toDataURL('image/png');
                
                // Submit the form with image data
                document.getElementById('save_image_data').value = imageData;
                document.getElementById('saveDesignImageForm').submit();
                
            }).catch(error => {
                console.error('Error generating design image:', error);
                alert('Error saving design image. Please try again.');
                
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
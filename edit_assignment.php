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

// Get assignment ID from URL
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assignment_id === 0) {
    $_SESSION['error_message'] = "Invalid assignment ID";
    header("Location: cards_assignment.php");
    exit();
}

// Fetch assignment details
$assignment_sql = "SELECT ca.*, u.name as user_name, admin.name as assigned_by_name,
                   printer.name as printer_name
                   FROM cards_assignment ca
                   JOIN users u ON ca.user_id = u.id
                   JOIN users admin ON ca.assigned_by = admin.id
                   LEFT JOIN users printer ON ca.updated_by_printer = printer.id
                   WHERE ca.id = ?";
$assignment_stmt = $conn->prepare($assignment_sql);
$assignment_stmt->bind_param("i", $assignment_id);
$assignment_stmt->execute();
$assignment_result = $assignment_stmt->get_result();

if ($assignment_result->num_rows === 0) {
    $_SESSION['error_message'] = "Assignment not found";
    header("Location: cards_assignment.php");
    exit();
}

$assignment = $assignment_result->fetch_assoc();
$assignment_stmt->close();

// Fetch all printers for dropdown
$printers_sql = "SELECT id, name FROM users WHERE role = 'printer'";
$printers_result = $conn->query($printers_sql);
$printers = [];
while ($printer = $printers_result->fetch_assoc()) {
    $printers[] = $printer;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = intval($_POST['quantity']);
    $status = $_POST['status'];
    $updated_by_printer = !empty($_POST['updated_by_printer']) ? intval($_POST['updated_by_printer']) : null;
    
    // Handle payment screenshot upload
    $payment_screenshot_path = $assignment['payment_screenshot_path']; // Keep existing if no new upload
    
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/payments/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['payment_screenshot']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $file_extension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types)) {
            // Validate file size (5MB max)
            if ($_FILES['payment_screenshot']['size'] <= 5 * 1024 * 1024) {
                if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $target_file)) {
                    $payment_screenshot_path = $target_file;
                    
                    // Delete old payment screenshot if exists and is different
                    if (!empty($assignment['payment_screenshot_path']) && $assignment['payment_screenshot_path'] !== $target_file) {
                        if (file_exists($assignment['payment_screenshot_path'])) {
                            unlink($assignment['payment_screenshot_path']);
                        }
                    }
                } else {
                    $errors[] = "Error uploading payment screenshot.";
                }
            } else {
                $errors[] = "Payment screenshot must be less than 5MB.";
            }
        } else {
            $errors[] = "Only JPG, JPEG, PNG, GIF, WEBP, and PDF files are allowed for payment screenshot.";
        }
    } elseif (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Error uploading payment screenshot: " . $_FILES['payment_screenshot']['error'];
    }
    
    // Validate inputs
    $errors = $errors ?? [];
    
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0";
    }
    
    if (!in_array($status, ['pending', 'in_process', 'out_for_delivery', 'completed'])) {
        $errors[] = "Invalid status selected";
    }
    
    if (empty($errors)) {
        // Update assignment
        $update_sql = "UPDATE cards_assignment SET 
                        quantity = ?, 
                        status = ?, 
                        updated_by_printer = ?, 
                        payment_screenshot_path = ?,
                        updated_at = NOW()
                       WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("isssi", $quantity, $status, $updated_by_printer, $payment_screenshot_path, $assignment_id);
        
        if ($update_stmt->execute()) {
            // Insert into status history if status changed
            if ($status !== $assignment['status']) {
                $history_sql = "INSERT INTO card_status_history 
                                (card_assignment_id, status, updated_by_printer, created_at) 
                                VALUES (?, ?, ?, NOW())";
                $history_stmt = $conn->prepare($history_sql);
                $history_stmt->bind_param("isi", $assignment_id, $status, $user_id);
                $history_stmt->execute();
                $history_stmt->close();
            }
            
            $_SESSION['success_message'] = "Assignment updated successfully!";
            header("Location: cards_assignment.php");
            exit();
        } else {
            $errors[] = "Error updating assignment: " . $conn->error;
        }
        $update_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Edit Assignment | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <style>
        .file-preview {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        .file-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 4px;
        }
        .file-actions {
            margin-top: 10px;
        }
        .assignment-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 6px 12px;
        }
        .badge-pending { background-color: #6c757d; }
        .badge-in_process { background-color: #ffc107; color: #000; }
        .badge-out_for_delivery { background-color: #17a2b8; }
        .badge-completed { background-color: #28a745; }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #007bff;
            background: #e9ecef;
        }
        .upload-area iconify-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .file-input {
            display: none;
        }
        .file-info {
            margin-top: 10px;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .assignment-info {
                padding: 15px;
            }
            .file-preview {
                padding: 10px;
            }
            .upload-area {
                padding: 15px;
            }
        }
        /* Status badge colors */
        .badge-pending { background-color: #6c757d !important; }
        .badge-in_process { background-color: #ffc107 !important; color: #000 !important; }
        .badge-out_for_delivery { background-color: #17a2b8 !important; }
        .badge-completed { background-color: #28a745 !important; }
        .badge-inprocess {
          --bs-bg-opacity: 1;
          background-color: rgba(var(--bs-warning-rgb), var(--bs-bg-opacity)) !important;
        }
        .badge-outfordelivery {
            --bs-bg-opacity: 1;
            background-color: rgba(var(--bs-info-rgb), var(--bs-bg-opacity)) !important;
        }

        /* Icon spacing */
        iconify-icon {
            margin-right: 0.25rem;
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
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title">
                                    <iconify-icon icon="solar:pen-new-round-bold" class="me-2"></iconify-icon>Edit Assignment #<?php echo $assignment['id']; ?>
                                </h4>
                                <div>
                                    <a href="cards_assignment.php" class="btn btn-secondary">
                                        <iconify-icon icon="solar:arrow-left-bold" class="me-1"></iconify-icon> Back to Assignments
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                                        <strong>Please fix the following errors:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <!-- Assignment Information -->
                                <div class="assignment-info">
                                    <h5 class="mb-3"><iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>Assignment Information</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="info-item">
                                                <span class="info-label">User:</span>
                                                <?php echo htmlspecialchars($assignment['user_name']); ?>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Current Status:</span>
                                                <span class="badge status-badge badge-<?php echo str_replace('_', '', $assignment['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $assignment['status'])); ?>
                                                </span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Assigned By:</span>
                                                <?php echo htmlspecialchars($assignment['assigned_by_name']); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-item">
                                                <span class="info-label">Current Quantity:</span>
                                                <?php echo number_format($assignment['quantity']); ?>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Assigned To:</span>
                                                <?php echo !empty($assignment['printer_name']) ? htmlspecialchars($assignment['printer_name']) : '<span class="text-muted">Not Assigned</span>'; ?>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Created Date:</span>
                                                <?php echo date('d M Y H:i', strtotime($assignment['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Previews -->
                                <div class="row mb-4">
                                    <?php if (!empty($assignment['front_card_path'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="file-preview">
                                                <h6><iconify-icon icon="solar:id-card-bold" class="me-2"></iconify-icon>Front Card Design</h6>
                                                <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $assignment['front_card_path'])): ?>
                                                    <img src="<?php echo $assignment['front_card_path']; ?>" alt="Front Card Design" class="img-fluid mb-2">
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <iconify-icon icon="solar:file-bold" class="me-2"></iconify-icon>File: <?php echo basename($assignment['front_card_path']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="file-actions">
                                                    <a href="<?php echo $assignment['front_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <iconify-icon icon="solar:eye-bold" class="me-1"></iconify-icon>View
                                                    </a>
                                                    <a href="<?php echo $assignment['front_card_path']; ?>" download class="btn btn-sm btn-outline-success">
                                                        <iconify-icon icon="solar:download-bold" class="me-1"></iconify-icon>Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($assignment['back_card_path'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="file-preview">
                                                <h6><iconify-icon icon="solar:id-card-bold" class="me-2"></iconify-icon>Back Card Design</h6>
                                                <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $assignment['back_card_path'])): ?>
                                                    <img src="<?php echo $assignment['back_card_path']; ?>" alt="Back Card Design" class="img-fluid mb-2">
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <iconify-icon icon="solar:file-bold" class="me-2"></iconify-icon>File: <?php echo basename($assignment['back_card_path']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="file-actions">
                                                    <a href="<?php echo $assignment['back_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <iconify-icon icon="solar:eye-bold" class="me-1"></iconify-icon>View
                                                    </a>
                                                    <a href="<?php echo $assignment['back_card_path']; ?>" download class="btn btn-sm btn-outline-success">
                                                        <iconify-icon icon="solar:download-bold" class="me-1"></iconify-icon>Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($assignment['payment_screenshot_path'])): ?>
                                        <div class="col-12 mb-3">
                                            <div class="file-preview">
                                                <h6><iconify-icon icon="solar:card-bold" class="me-2"></iconify-icon>Current Payment Screenshot</h6>
                                                <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $assignment['payment_screenshot_path'])): ?>
                                                    <img src="<?php echo $assignment['payment_screenshot_path']; ?>" alt="Payment Screenshot" class="img-fluid mb-2" style="max-height: 300px;">
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <iconify-icon icon="solar:file-bold" class="me-2"></iconify-icon>File: <?php echo basename($assignment['payment_screenshot_path']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="file-actions">
                                                    <a href="<?php echo $assignment['payment_screenshot_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                        <iconify-icon icon="solar:eye-bold" class="me-1"></iconify-icon>View
                                                    </a>
                                                    <a href="<?php echo $assignment['payment_screenshot_path']; ?>" download class="btn btn-sm btn-outline-success">
                                                        <iconify-icon icon="solar:download-bold" class="me-1"></iconify-icon>Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Edit Form -->
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">
                                                    <iconify-icon icon="solar:box-bold" class="me-1"></iconify-icon>Quantity <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                                       value="<?php echo htmlspecialchars($assignment['quantity']); ?>" 
                                                       min="1" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">
                                                    <iconify-icon icon="solar:list-check-bold" class="me-1"></iconify-icon>Status <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="pending" <?php echo $assignment['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="in_process" <?php echo $assignment['status'] == 'in_process' ? 'selected' : ''; ?>>In Process</option>
                                                    <option value="out_for_delivery" <?php echo $assignment['status'] == 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                                    <option value="completed" <?php echo $assignment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="updated_by_printer" class="form-label">
                                                    <iconify-icon icon="solar:user-hand-up-bold" class="me-1"></iconify-icon>Assign to Printer
                                                </label>
                                                <select class="form-select" id="updated_by_printer" name="updated_by_printer">
                                                    <option value="">-- Select Printer --</option>
                                                    <?php foreach ($printers as $printer): ?>
                                                        <option value="<?php echo $printer['id']; ?>" 
                                                            <?php echo $assignment['updated_by_printer'] == $printer['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($printer['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">                                        
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <iconify-icon icon="solar:upload-bold" class="me-1"></iconify-icon>Upload Payment Screenshot
                                                </label>
                                                <div class="upload-area" onclick="document.getElementById('payment_screenshot').click()">
                                                    <iconify-icon icon="solar:cloud-upload-bold" width="48"></iconify-icon>
                                                    <h6>Click to Upload Payment Screenshot</h6>
                                                    <p class="text-muted mb-2">JPG, PNG, GIF, WEBP, PDF (Max 5MB)</p>
                                                    <div id="file-name" class="file-info text-primary fw-bold"></div>
                                                </div>
                                                <input type="file" class="file-input" id="payment_screenshot" name="payment_screenshot" 
                                                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                                                <div class="form-text">
                                                    Upload a new payment screenshot to replace the current one
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                        <strong>Note:</strong> Updating the status will create a new entry in the status history. 
                                        Changing the assigned printer will notify the printer about this assignment.
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <iconify-icon icon="solar:check-square-bold" class="me-1"></iconify-icon> Update Assignment
                                        </button>
                                        <a href="cards_assignment.php" class="btn btn-secondary">
                                            <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon> Cancel
                                        </a>
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

    <!-- Status History Modal -->
        <!-- Status History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">
                        <iconify-icon icon="solar:history-bold" class="me-2"></iconify-icon>Status History - Assignment #<?php echo $assignment['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    // Use existing connection instead of reconnecting
                    if (isset($conn) && $conn->connect_error) {
                        echo '<div class="alert alert-danger">Database connection unavailable</div>';
                    } else {
                        // Create a new connection for history if needed, but avoid function redeclaration
                        $history_conn = new mysqli($host, $username, $password, $dbname);
                        
                        if ($history_conn->connect_error) {
                            echo '<div class="alert alert-danger">History database connection failed</div>';
                        } else {
                            $history_sql = "SELECT h.*, u.name as updated_by_name 
                                            FROM card_status_history h
                                            LEFT JOIN users u ON h.updated_by_printer = u.id
                                            WHERE h.card_assignment_id = ?
                                            ORDER BY h.created_at DESC";
                            $history_stmt = $history_conn->prepare($history_sql);
                            $history_stmt->bind_param("i", $assignment_id);
                            $history_stmt->execute();
                            $history_result = $history_stmt->get_result();
                            
                            if ($history_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Status</th>
                                                <th>Updated By</th>
                                                <th>Date & Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($history = $history_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge status-badge badge-<?php echo str_replace('_', '', $history['status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $history['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo !empty($history['updated_by_name']) ? htmlspecialchars($history['updated_by_name']) : 'System'; ?></td>
                                                    <td><?php echo date('d M Y H:i', strtotime($history['created_at'])); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <iconify-icon icon="solar:info-circle-bold" class="me-2"></iconify-icon>
                                    No status history found for this assignment.
                                </div>
                            <?php endif;
                            
                            $history_stmt->close();
                            $history_conn->close();
                        }
                    }
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <iconify-icon icon="solar:close-circle-bold" class="me-1"></iconify-icon>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

<script src="assets/js/vendor.js"></script>
<script src="assets/js/app.js"></script>

<script>
$(document).ready(function() {
    // Form validation (keep your existing code)
    $('form').validate({
        rules: {
            quantity: {
                required: true,
                min: 1,
                digits: true
            },
            status: {
                required: true
            }
        },
        messages: {
            quantity: {
                required: "Please enter quantity",
                min: "Quantity must be at least 1",
                digits: "Please enter a valid number"
            },
            status: {
                required: "Please select a status"
            }
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.mb-3').append(error);
        },
        highlight: function (element, errorClass, validClass) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function (element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
        }
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // File upload display
    $('#payment_screenshot').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $('#file-name').text('Selected: ' + fileName);
        } else {
            $('#file-name').text('');
        }
    });
    
    // Status change confirmation
    $('#status').on('change', function() {
        const newStatus = $(this).val();
        const currentStatus = '<?php echo $assignment['status']; ?>';
        
        if (newStatus !== currentStatus) {
            console.log(`Status changing from ${currentStatus} to ${newStatus}`);
        }
    });
    
    // Prevent form submission if file is too large
    $('form').on('submit', function() {
        const fileInput = $('#payment_screenshot')[0];
        if (fileInput.files.length > 0) {
            const fileSize = fileInput.files[0].size;
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (fileSize > maxSize) {
                alert('File size must be less than 5MB');
                return false;
            }
        }
        return true;
    });

    // MINIMAL DROPDOWN FIX - Only if absolutely necessary
    function fixToolbarDropdowns() {
        // Remove any conflicting event listeners from the toolbar dropdown
        $('#page-header-user-dropdown').off('click.manual');
        
        // Ensure Bootstrap handles the dropdown
        const userDropdown = document.getElementById('page-header-user-dropdown');
        if (userDropdown) {
            const dropdown = new bootstrap.Dropdown(userDropdown);
            console.log('✅ Toolbar dropdown properly initialized');
        }
    }
    
    // Fix dropdowns after a short delay to ensure DOM is ready
    setTimeout(fixToolbarDropdowns, 100);
});
</script>
</body>
</html>
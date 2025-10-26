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

// Fetch all printers for dropdown - REMOVED STATUS FILTER
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
    $printer_notes = trim($_POST['printer_notes']);
    
    // Validate inputs
    $errors = [];
    
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
                        printer_notes = ?,
                        updated_at = NOW()
                       WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("issii", $quantity, $status, $updated_by_printer, $printer_notes, $assignment_id);
        
        if ($update_stmt->execute()) {
            // Insert into status history if status changed
            if ($status !== $assignment['status']) {
                $history_sql = "INSERT INTO card_status_history 
                                (card_assignment_id, status, printer_notes, updated_by_printer, created_at) 
                                VALUES (?, ?, ?, ?, NOW())";
                $history_stmt = $conn->prepare($history_sql);
                $history_stmt->bind_param("issi", $assignment_id, $status, $printer_notes, $user_id);
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
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Iconify Icons (Required for toolbar) -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.7/dist/iconify-icon.min.js"></script>

    <!-- JavaScript - Load jQuery first, then Bootstrap Bundle -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Other JS -->
    <script src="assets/js/config.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        @media (max-width: 768px) {
            .assignment-info {
                padding: 15px;
            }
            .file-preview {
                padding: 10px;
            }
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
                                    <i class="fas fa-edit me-2"></i>Edit Assignment #<?php echo $assignment['id']; ?>
                                </h4>
                                <div>
                                    <a href="cards_assignment.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Assignments
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
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
                                    <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Assignment Information</h5>
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
                                                <h6><i class="fas fa-id-card me-2"></i>Front Card Design</h6>
                                                <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $assignment['front_card_path'])): ?>
                                                    <img src="<?php echo $assignment['front_card_path']; ?>" alt="Front Card Design" class="img-fluid mb-2">
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-file me-2"></i>File: <?php echo basename($assignment['front_card_path']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="file-actions">
                                                    <a href="<?php echo $assignment['front_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </a>
                                                    <a href="<?php echo $assignment['front_card_path']; ?>" download class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-download me-1"></i>Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($assignment['back_card_path'])): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="file-preview">
                                                <h6><i class="fas fa-id-card me-2"></i>Back Card Design</h6>
                                                <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $assignment['back_card_path'])): ?>
                                                    <img src="<?php echo $assignment['back_card_path']; ?>" alt="Back Card Design" class="img-fluid mb-2">
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-file me-2"></i>File: <?php echo basename($assignment['back_card_path']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="file-actions">
                                                    <a href="<?php echo $assignment['back_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </a>
                                                    <a href="<?php echo $assignment['back_card_path']; ?>" download class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-download me-1"></i>Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($assignment['payment_screenshot_path'])): ?>
                                        <div class="col-12 mb-3">
                                            <div class="file-preview">
                                                <h6><i class="fas fa-credit-card me-2"></i>Payment Screenshot</h6>
                                                <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $assignment['payment_screenshot_path'])): ?>
                                                    <img src="<?php echo $assignment['payment_screenshot_path']; ?>" alt="Payment Screenshot" class="img-fluid mb-2" style="max-height: 300px;">
                                                <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-file me-2"></i>File: <?php echo basename($assignment['payment_screenshot_path']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="file-actions">
                                                    <a href="<?php echo $assignment['payment_screenshot_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </a>
                                                    <a href="<?php echo $assignment['payment_screenshot_path']; ?>" download class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-download me-1"></i>Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Edit Form -->
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">
                                                    <i class="fas fa-boxes me-1"></i>Quantity <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                                       value="<?php echo htmlspecialchars($assignment['quantity']); ?>" 
                                                       min="1" required>
                                                <div class="form-text">Number of cards to be printed</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">
                                                    <i class="fas fa-tasks me-1"></i>Status <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="pending" <?php echo $assignment['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="in_process" <?php echo $assignment['status'] == 'in_process' ? 'selected' : ''; ?>>In Process</option>
                                                    <option value="out_for_delivery" <?php echo $assignment['status'] == 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                                    <option value="completed" <?php echo $assignment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="updated_by_printer" class="form-label">
                                                    <i class="fas fa-user-tie me-1"></i>Assign to Printer
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
                                                <div class="form-text">Assign this job to a specific printer</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="printer_notes" class="form-label">
                                            <i class="fas fa-sticky-note me-1"></i>Printer Notes
                                        </label>
                                        <textarea class="form-control" id="printer_notes" name="printer_notes" 
                                                  rows="4" placeholder="Add any notes or instructions for the printer..."><?php echo htmlspecialchars($assignment['printer_notes'] ?? ''); ?></textarea>
                                        <div class="form-text">These notes will be visible to the assigned printer</div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Note:</strong> Updating the status will create a new entry in the status history. 
                                        Changing the assigned printer will notify the printer about this assignment.
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Update Assignment
                                        </button>
                                        <a href="cards_assignment.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </a>
                                        <!-- <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#historyModal">
                                            <i class="fas fa-history me-1"></i> View History
                                        </button> -->
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
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">
                        <i class="fas fa-history me-2"></i>Status History - Assignment #<?php echo $assignment['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    // Reconnect to database for history
                    require 'db_connection.php';
                    $history_sql = "SELECT h.*, u.name as updated_by_name 
                                    FROM card_status_history h
                                    LEFT JOIN users u ON h.updated_by_printer = u.id
                                    WHERE h.card_assignment_id = ?
                                    ORDER BY h.created_at DESC";
                    $history_stmt = $conn->prepare($history_sql);
                    $history_stmt->bind_param("i", $assignment_id);
                    $history_stmt->execute();
                    $history_result = $history_stmt->get_result();
                    ?>
                    
                    <?php if ($history_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <th>Printer Notes</th>
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
                                            <td><?php echo $history['printer_notes'] ? htmlspecialchars($history['printer_notes']) : '<span class="text-muted">No notes</span>'; ?></td>
                                            <td><?php echo !empty($history['updated_by_name']) ? htmlspecialchars($history['updated_by_name']) : 'System'; ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($history['created_at'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            No status history found for this assignment.
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    $history_stmt->close();
                    $conn->close();
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Status change confirmation
            $('#status').on('change', function() {
                const newStatus = $(this).val();
                const currentStatus = '<?php echo $assignment['status']; ?>';
                
                if (newStatus !== currentStatus) {
                    console.log(`Status changing from ${currentStatus} to ${newStatus}`);
                }
            });
        });
    </script>
</body>
</html>
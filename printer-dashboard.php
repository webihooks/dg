<?php
// Start the session
session_start();

// Include the enhanced session manager
require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();

// Let the session manager handle Android session persistence
$sessionManager->validateAndroidSession();

// Include the database connection file
require 'db_connection.php';
date_default_timezone_set('Asia/Kolkata');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// Check if user is a printer
if ($role !== 'printer') {
    header("Location: printer-dashboard.php");
    exit();
}

// Handle status update FIRST, before fetching data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $card_id = $_POST['card_id'];
    $status = $_POST['status'];
    $printer_notes = trim($_POST['printer_notes']);
    
    // Debug: Check received data
    error_log("Updating card ID: $card_id, Status: $status, User ID: $user_id");
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update main cards_assignment table
        $update_sql = "UPDATE cards_assignment SET 
                        status = ?, 
                        printer_notes = ?, 
                        updated_by_printer = ?, 
                        updated_at = NOW() 
                       WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssii", $status, $printer_notes, $user_id, $card_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating card status: " . $update_stmt->error);
        }
        $update_stmt->close();
        
        // Insert into status history table
        $history_sql = "INSERT INTO card_status_history 
                        (card_assignment_id, status, printer_notes, updated_by_printer, created_at) 
                        VALUES (?, ?, ?, ?, NOW())";
        $history_stmt = $conn->prepare($history_sql);
        $history_stmt->bind_param("issi", $card_id, $status, $printer_notes, $user_id);
        
        if (!$history_stmt->execute()) {
            throw new Exception("Error saving status history: " . $history_stmt->error);
        }
        $history_stmt->close();
        
        // Commit transaction
        $conn->commit();
        $success_message = "Card status updated successfully!";
        
        // Refresh the page to show updated data
        echo "<script>window.location.href = 'printer-dashboard.php?success=1';</script>";
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Transaction Error: " . $e->getMessage());
    }
}

// Handle card download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_card'])) {
    $card_id = $_POST['card_id'];
    $card_type = $_POST['card_type']; // 'front' or 'back'
    
    // Fetch card details
    $card_sql = "SELECT ca.front_card_path, ca.back_card_path, u.name as customer_name 
                 FROM cards_assignment ca 
                 LEFT JOIN users u ON ca.user_id = u.id 
                 WHERE ca.id = ?";
    $card_stmt = $conn->prepare($card_sql);
    $card_stmt->bind_param("i", $card_id);
    $card_stmt->execute();
    $card_result = $card_stmt->get_result();
    
    if ($card_result->num_rows > 0) {
        $card_data = $card_result->fetch_assoc();
        $file_path = ($card_type === 'front') ? $card_data['front_card_path'] : $card_data['back_card_path'];
        $customer_name = $card_data['customer_name'];
        
        if ($file_path && file_exists($file_path)) {
            // Clean customer name for filename
            $clean_name = preg_replace('/[^A-Za-z0-9_-]/', '_', $customer_name);
            $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
            $new_filename = $clean_name . '_' . ucfirst($card_type) . '_Card.' . $file_extension;
            
            // Set headers for download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $new_filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            $error_message = "Card file not found!";
        }
    } else {
        $error_message = "Card not found!";
    }
    $card_stmt->close();
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Card status updated successfully!";
}

// Fetch assigned cards for this printer
$cards_sql = "SELECT 
                ca.id, 
                ca.user_id,
                u.name as customer_name,
                ca.front_card_path,
                ca.back_card_path,
                ca.quantity,
                ca.payment_screenshot_path,
                ca.status,
                ca.printer_notes,
                ca.updated_by_printer,
                ca.assigned_by,
                ca.created_at,
                ca.updated_at
              FROM cards_assignment ca
              LEFT JOIN users u ON ca.user_id = u.id
              WHERE ca.updated_by_printer = ? OR ca.status IN ('pending', 'in_process', 'out_for_delivery')
              ORDER BY ca.created_at DESC";
$cards_stmt = $conn->prepare($cards_sql);
$cards_stmt->bind_param("i", $user_id);
$cards_stmt->execute();
$cards_result = $cards_stmt->get_result();

$assigned_cards = [];
if ($cards_result->num_rows > 0) {
    while ($row = $cards_result->fetch_assoc()) {
        $assigned_cards[] = $row;
    }
}
$cards_stmt->close();

// Fetch status history for each card
$status_history = [];
foreach ($assigned_cards as $card) {
    $history_sql = "SELECT status, printer_notes, created_at 
                    FROM card_status_history 
                    WHERE card_assignment_id = ? 
                    ORDER BY created_at DESC";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->bind_param("i", $card['id']);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    
    $card_history = [];
    while ($history_row = $history_result->fetch_assoc()) {
        $card_history[] = $history_row;
    }
    $status_history[$card['id']] = $card_history;
    $history_stmt->close();
}

// Fetch statistics for dashboard
$stats_sql = "SELECT 
                COUNT(*) as total_assigned,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'in_process' THEN 1 ELSE 0 END) as in_process,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
              FROM cards_assignment 
              WHERE updated_by_printer = ? OR status IN ('pending', 'in_process', 'out_for_delivery')";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Printer Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- CSS Files -->
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- JavaScript - Load jQuery first, then Bootstrap Bundle -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Other JS -->
    <script src="assets/js/config.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    


    <style>
        /* Mobile Responsive Styles */
        .stat-card-mobile {
            margin-bottom: 15px;
        }
        .table-mobile-view {
            display: none;
        }
        .card-mobile {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .mobile-action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .mobile-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .mobile-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 4px;
        }
        .mobile-value {
            margin-bottom: 8px;
        }
        .mobile-section {
            border-bottom: 1px solid #f0f0f0;
            padding: 8px 0;
        }
        .mobile-section:last-child {
            border-bottom: none;
        }
        
        /* Icon Styles */
        .icon-sm {
            font-size: 0.875rem;
        }
        .icon-md {
            font-size: 1rem;
        }
        .icon-lg {
            font-size: 1.25rem;
        }
        
        @media (max-width: 768px) {
            .table-desktop-view {
                display: none;
            }
            .table-mobile-view {
                display: block;
            }
            .stat-card-mobile .card-body {
                padding: 15px;
            }
            .stat-card-mobile h4 {
                font-size: 1.5rem;
            }
            .mobile-modal .modal-dialog {
                margin: 10px;
            }
            .btn-sm-mobile {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            .mobile-action-buttons {
                flex-direction: column;
            }
            .mobile-action-buttons .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            .stat-card-mobile .card-body {
                padding: 12px;
            }
            .stat-card-mobile {
              margin-bottom: 0;
            }
            .stat-card-mobile .card {
                margin-bottom: 3px;
            }
            .stat-card-mobile {
              width: 50%;
              margin-bottom: 10px;
            }
            .stat-card-mobile:first-child {
              width: 100%;
            }
        }
    </style>
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>

    <!-- Session Status Indicator -->
    <div class="session-status-android <?php echo $sessionManager->isAndroidApp() ? 'android' : 'web'; ?>" id="sessionStatusIndicator">
        <?php echo $sessionManager->isAndroidApp() ? '📱 Android App - Session Active' : '🌐 Web - Session Active'; ?>
    </div>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'printer_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards - Mobile Responsive -->
                <div class="row">
                    <div class="col-xl-4 col-md-6 stat-card-mobile">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                                <h4 class="mb-1"><?php echo $stats['total_assigned']; ?></h4>
                                <p class="text-muted mb-0">Total Assigned</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-6 stat-card-mobile">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h4 class="mb-1 text-success"><?php echo $stats['completed']; ?></h4>
                                <p class="text-muted mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-6 stat-card-mobile">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-cogs fa-2x text-warning mb-2"></i>
                                <h4 class="mb-1 text-warning"><?php echo $stats['in_process']; ?></h4>
                                <p class="text-muted mb-0">In Process</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-6 stat-card-mobile">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-truck fa-2x text-info mb-2"></i>
                                <h4 class="mb-1 text-info"><?php echo $stats['out_for_delivery']; ?></h4>
                                <p class="text-muted mb-0">Out for Delivery</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-6 stat-card-mobile">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x text-danger mb-2"></i>
                                <h4 class="mb-1 text-danger"><?php echo $stats['pending']; ?></h4>
                                <p class="text-muted mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="row table-desktop-view">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><i class="fas fa-list me-2"></i>Assigned Cards</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($assigned_cards)): ?>
                                    <div class="alert alert-info" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No cards assigned to you yet.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Customer</th>
                                                    <th>Front Card</th>
                                                    <th>Back Card</th>
                                                    <th>Quantity</th>
                                                    <th>Payment</th>
                                                    <th>Status</th>
                                                    <th>Created Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($assigned_cards as $card): ?>
                                                    <tr>
                                                        <td><?php echo $card['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($card['customer_name']); ?></td>
                                                        <td>
                                                            <?php if ($card['front_card_path']): ?>
                                                                <div class="d-flex flex-column gap-1">
                                                                    <a href="<?php echo $card['front_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                        <i class="fas fa-eye me-1"></i>View
                                                                    </a>
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                                                        <input type="hidden" name="card_type" value="front">
                                                                        <button type="submit" name="download_card" class="btn btn-sm btn-outline-success w-100">
                                                                            <i class="fas fa-download me-1"></i>Download
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($card['back_card_path']): ?>
                                                                <div class="d-flex flex-column gap-1">
                                                                    <a href="<?php echo $card['back_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                        <i class="fas fa-eye me-1"></i>View
                                                                    </a>
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                                                        <input type="hidden" name="card_type" value="back">
                                                                        <button type="submit" name="download_card" class="btn btn-sm btn-outline-success w-100">
                                                                            <i class="fas fa-download me-1"></i>Download
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo number_format($card['quantity']); ?></td>
                                                        <td>
                                                            <?php if ($card['payment_screenshot_path']): ?>
                                                                <a href="<?php echo $card['payment_screenshot_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                                    <i class="fas fa-credit-card me-1"></i>View
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge 
                                                                <?php 
                                                                switch($card['status']) {
                                                                    case 'completed': echo 'bg-success'; break;
                                                                    case 'in_process': echo 'bg-warning'; break;
                                                                    case 'out_for_delivery': echo 'bg-info'; break;
                                                                    case 'pending': echo 'bg-secondary'; break;
                                                                    default: echo 'bg-secondary';
                                                                }
                                                                ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $card['status'])); ?>
                                                            </span>
                                                            <?php if (isset($status_history[$card['id']]) && count($status_history[$card['id']]) > 0): ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?php 
                                                                    $latest_update = $status_history[$card['id']][0];
                                                                    echo date('M j, Y g:i A', strtotime($latest_update['created_at']));
                                                                    ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            echo date('M j, Y g:i A', strtotime($card['created_at']));
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column gap-1">
                                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $card['id']; ?>">
                                                                    <i class="fas fa-edit me-1"></i>Update
                                                                </button>
                                                                <?php if (isset($status_history[$card['id']]) && count($status_history[$card['id']]) > 0): ?>
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#historyModal<?php echo $card['id']; ?>">
                                                                        <i class="fas fa-history me-1"></i>History
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div class="row table-mobile-view">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><i class="fas fa-list me-2"></i>Assigned Cards</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($assigned_cards)): ?>
                                    <div class="alert alert-info" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No cards assigned to you yet.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($assigned_cards as $card): ?>
                                        <div class="card-mobile">
                                            <div class="mobile-section">
                                                <div class="mobile-label"><i class="fas fa-hashtag me-1"></i>Card ID</div>
                                                <div class="mobile-value">#<?php echo $card['id']; ?></div>
                                            </div>
                                            
                                            <div class="mobile-section">
                                                <div class="mobile-label"><i class="fas fa-user me-1"></i>Customer</div>
                                                <div class="mobile-value"><?php echo htmlspecialchars($card['customer_name']); ?></div>
                                            </div>
                                            
                                            <div class="mobile-section">
                                                <div class="mobile-label"><i class="fas fa-boxes me-1"></i>Quantity</div>
                                                <div class="mobile-value"><?php echo number_format($card['quantity']); ?></div>
                                            </div>
                                            
                                            <div class="mobile-section">
                                                <div class="mobile-label"><i class="fas fa-calendar me-1"></i>Created Date</div>
                                                <div class="mobile-value">
                                                    <i class="fas fa-clock me-1 text-muted"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($card['created_at'])); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mobile-section">
                                                <div class="mobile-label"><i class="fas fa-tasks me-1"></i>Status</div>
                                                <div class="mobile-value">
                                                    <span class="badge mobile-badge
                                                        <?php 
                                                        switch($card['status']) {
                                                            case 'completed': echo 'bg-success'; break;
                                                            case 'in_process': echo 'bg-warning'; break;
                                                            case 'out_for_delivery': echo 'bg-info'; break;
                                                            case 'pending': echo 'bg-secondary'; break;
                                                            default: echo 'bg-secondary';
                                                        }
                                                        ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $card['status'])); ?>
                                                    </span>
                                                    <?php if (isset($status_history[$card['id']]) && count($status_history[$card['id']]) > 0): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-sync me-1"></i>Last Updated: <?php 
                                                            $latest_update = $status_history[$card['id']][0];
                                                            echo date('M j, Y g:i A', strtotime($latest_update['created_at']));
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mobile-section">
                                                <div class="mobile-label"><i class="fas fa-file me-1"></i>Files</div>
                                                <div class="mobile-value">
                                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                                        <?php if ($card['front_card_path']): ?>
                                                            <div class="d-flex flex-column">
                                                                <small class="text-muted">Front Card:</small>
                                                                <div class="d-flex gap-1">
                                                                    <a href="<?php echo $card['front_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary btn-sm-mobile">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                                                        <input type="hidden" name="card_type" value="front">
                                                                        <button type="submit" name="download_card" class="btn btn-sm btn-outline-success btn-sm-mobile">
                                                                            <i class="fas fa-download"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($card['back_card_path']): ?>
                                                            <div class="d-flex flex-column">
                                                                <small class="text-muted">Back Card:</small>
                                                                <div class="d-flex gap-1">
                                                                    <a href="<?php echo $card['back_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary btn-sm-mobile">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                                                        <input type="hidden" name="card_type" value="back">
                                                                        <button type="submit" name="download_card" class="btn btn-sm btn-outline-success btn-sm-mobile">
                                                                            <i class="fas fa-download"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($card['payment_screenshot_path']): ?>
                                                            <div class="d-flex flex-column">
                                                                <small class="text-muted">Payment:</small>
                                                                <a href="<?php echo $card['payment_screenshot_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info btn-sm-mobile">
                                                                    <i class="fas fa-credit-card"></i>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mobile-section">
                                                <div class="mobile-label"><i class="fas fa-cog me-1"></i>Actions</div>
                                                <div class="mobile-value">
                                                    <div class="mobile-action-buttons">
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $card['id']; ?>">
                                                            <i class="fas fa-edit me-1"></i>Update Status
                                                        </button>
                                                        <?php if (isset($status_history[$card['id']]) && count($status_history[$card['id']]) > 0): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#historyModal<?php echo $card['id']; ?>">
                                                                <i class="fas fa-history me-1"></i>History
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- All Modals should be placed here, outside the main content but inside body -->
    <?php foreach ($assigned_cards as $card): ?>
        <!-- Update Status Modal -->
        <div class="modal fade mobile-modal" id="updateModal<?php echo $card['id']; ?>" tabindex="-1" aria-labelledby="updateModalLabel<?php echo $card['id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateModalLabel<?php echo $card['id']; ?>">
                            <i class="fas fa-edit me-2"></i>Update Card #<?php echo $card['id']; ?> Status
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                            
                            <div class="mb-3">
                                <label for="status<?php echo $card['id']; ?>" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status<?php echo $card['id']; ?>" name="status" required>
                                    <option value="pending" <?php echo $card['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_process" <?php echo $card['status'] == 'in_process' ? 'selected' : ''; ?>>In Process</option>
                                    <option value="out_for_delivery" <?php echo $card['status'] == 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                    <option value="completed" <?php echo $card['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="printer_notes<?php echo $card['id']; ?>" class="form-label">Printer Notes</label>
                                <textarea class="form-control" id="printer_notes<?php echo $card['id']; ?>" name="printer_notes" rows="3" placeholder="Add any notes or comments about this printing job..."><?php echo htmlspecialchars($card['printer_notes'] ?? ''); ?></textarea>
                                <div class="form-text">These notes will be visible to administrators and saved in history.</div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                The status will be updated immediately and both the timestamp and notes will be recorded in history.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Status History Modal -->
        <?php if (isset($status_history[$card['id']]) && count($status_history[$card['id']]) > 0): ?>
            <div class="modal fade mobile-modal" id="historyModal<?php echo $card['id']; ?>" tabindex="-1" aria-labelledby="historyModalLabel<?php echo $card['id']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="historyModalLabel<?php echo $card['id']; ?>">
                                <i class="fas fa-history me-2"></i>Status History - Card #<?php echo $card['id']; ?> (<?php echo htmlspecialchars($card['customer_name']); ?>)
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Status</th>
                                            <th>Printer Notes</th>
                                            <th>Date & Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($status_history[$card['id']] as $history): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                        switch($history['status']) {
                                                            case 'completed': echo 'bg-success'; break;
                                                            case 'in_process': echo 'bg-warning'; break;
                                                            case 'out_for_delivery': echo 'bg-info'; break;
                                                            case 'pending': echo 'bg-secondary'; break;
                                                            default: echo 'bg-secondary';
                                                        }
                                                        ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $history['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $history['printer_notes'] ? htmlspecialchars($history['printer_notes']) : '<span class="text-muted"><i class="fas fa-minus me-1"></i>No notes</span>'; ?></td>
                                                <td><i class="fas fa-clock me-1 text-muted"></i><?php echo date('M j, Y g:i A', strtotime($history['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    
    <script>
        // Auto-hide alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Add confirmation for status updates
            $('form').on('submit', function() {
                const status = $(this).find('select[name="status"]').val();
                const cardId = $(this).find('input[name="card_id"]').val();
                
                console.log(`Updating card ${cardId} to status: ${status}`);
                return true; // Allow form submission
            });

            // Handle window resize for better mobile experience
            function handleResize() {
                if (window.innerWidth <= 768) {
                    $('body').addClass('mobile-view');
                } else {
                    $('body').removeClass('mobile-view');
                }
            }

            // Initial check
            handleResize();
            
            // Listen for resize events
            $(window).on('resize', handleResize);
        });
    </script>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

</body>
</html>
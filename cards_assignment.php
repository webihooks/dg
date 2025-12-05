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

// Fetch messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get search term from GET parameter
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build assignments query with search filter
$assignments_sql = "SELECT ca.*, u.name as user_name, admin.name as assigned_by_name, 
                    printer.name as printer_name
                    FROM cards_assignment ca
                    JOIN users u ON ca.user_id = u.id
                    JOIN users admin ON ca.assigned_by = admin.id
                    LEFT JOIN users printer ON ca.updated_by_printer = printer.id";

// Add WHERE clause if search term exists
if (!empty($search_term)) {
    $search_like = "%" . $conn->real_escape_string($search_term) . "%";
    $assignments_sql .= " WHERE (u.name LIKE ? OR admin.name LIKE ? OR ca.status LIKE ? OR printer.name LIKE ?)";
}

$assignments_sql .= " ORDER BY ca.created_at DESC";

// Prepare and execute the query with search parameters if needed
$assignments_stmt = $conn->prepare($assignments_sql);
if (!empty($search_term)) {
    $assignments_stmt->bind_param("ssss", $search_like, $search_like, $search_like, $search_like);
}
$assignments_stmt->execute();
$assignments_result = $assignments_stmt->get_result();

// Fetch status history for each assignment
$status_history = [];
$all_assignments = [];
if ($assignments_result->num_rows > 0) {
    while ($assignment = $assignments_result->fetch_assoc()) {
        $all_assignments[] = $assignment;
        
        // Fetch status history for this assignment
        $history_sql = "SELECT status, printer_notes, created_at 
                        FROM card_status_history 
                        WHERE card_assignment_id = ? 
                        ORDER BY created_at DESC";
        $history_stmt = $conn->prepare($history_sql);
        $history_stmt->bind_param("i", $assignment['id']);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        
        $assignment_history = [];
        while ($history_row = $history_result->fetch_assoc()) {
            $assignment_history[] = $history_row;
        }
        $status_history[$assignment['id']] = $assignment_history;
        $history_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Card Assignments | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <style>
        .search-container {
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            gap: 10px;
        }
        .search-form .form-control {
            flex-grow: 1;
        }
        
        /* Mobile Responsive Styles */
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
        .btn-sm-mobile {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        /* Status badge colors */
        .badge-pending { background-color: #6c757d !important; }
        .badge-in_process { background-color: #ffc107 !important; color: #000 !important; }
        .badge-out_for_delivery { background-color: #17a2b8 !important; }
        .badge-completed { background-color: #28a745 !important; }
        
        /* History table styling */
        .history-table {
            font-size: 0.875rem;
        }
        .history-table th {
            background-color: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .table-desktop-view {
                display: none;
            }
            .table-mobile-view {
                display: block;
            }
            .search-form {
                flex-direction: column;
            }
            .mobile-action-buttons {
                flex-direction: column;
            }
            .mobile-action-buttons .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            .modal-dialog {
                margin: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            .btn-group {
                flex-direction: column;
            }
            .btn-group .btn {
                margin-bottom: 5px;
            }
            .modal.fade .modal-dialog {
                transform: translate(0, 100px) !important
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
                                <h4 class="card-title"><iconify-icon icon="mdi:format-list-bulleted"></iconify-icon>Card Assignments</h4>
                                <div>
                                    <a href="create_cards_assignment.php" class="btn btn-primary">
                                        <iconify-icon icon="mdi:plus-circle"></iconify-icon> Create New Assignment
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <iconify-icon icon="mdi:check-circle"></iconify-icon>
                                        <?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <iconify-icon icon="mdi:alert-circle"></iconify-icon>
                                        <?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <div class="search-container">
                                    <form method="get" action="" class="search-form">
                                        <input type="text" class="form-control" name="search" placeholder="Search by user, assigned by, status, or printer" value="<?php echo htmlspecialchars($search_term); ?>">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <iconify-icon icon="mdi:magnify"></iconify-icon>Search
                                            </button>
                                            <?php if (!empty($search_term)): ?>
                                                <a href="cards_assignment.php" class="btn btn-secondary">
                                                    <iconify-icon icon="mdi:close"></iconify-icon>Clear
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>

                                <!-- Desktop Table View -->
                                <div class="table-desktop-view">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>User</th>
                                                    <th>Quantity</th>
                                                    <th>Status</th>
                                                    <th>Assigned To</th>
                                                    <th>Front Design</th>
                                                    <th>Back Design</th>
                                                    <th>Payment</th>
                                                    <th>Assigned By</th>
                                                    <th>Created Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($all_assignments) > 0): ?>
                                                    <?php foreach ($all_assignments as $assignment): ?>
                                                        <tr>
                                                            <td><?php echo $assignment['id']; ?></td>
                                                            <td><?php echo htmlspecialchars($assignment['user_name']); ?></td>
                                                            <td><?php echo number_format($assignment['quantity']); ?></td>
                                                            <td>
                                                                <span class="badge badge-<?php echo str_replace('_', '', $assignment['status']); ?>">
                                                                    <?php echo ucfirst(str_replace('_', ' ', $assignment['status'])); ?>
                                                                </span>
                                                                <?php if (isset($status_history[$assignment['id']]) && count($status_history[$assignment['id']]) > 0): ?>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?php 
                                                                        $latest_update = $status_history[$assignment['id']][0];
                                                                        echo date('M j, Y g:i A', strtotime($latest_update['created_at']));
                                                                        ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($assignment['printer_name'])): ?>
                                                                    <?php echo htmlspecialchars($assignment['printer_name']); ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Not Assigned</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($assignment['front_card_path'])): ?>
                                                                    <div class="d-flex flex-column gap-1">
                                                                        <a href="<?php echo $assignment['front_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                            <iconify-icon icon="mdi:eye"></iconify-icon>View
                                                                        </a>
                                                                        <a href="<?php echo $assignment['front_card_path']; ?>" download class="btn btn-sm btn-outline-success">
                                                                            <iconify-icon icon="mdi:download"></iconify-icon>Download
                                                                        </a>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">N/A</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($assignment['back_card_path'])): ?>
                                                                    <div class="d-flex flex-column gap-1">
                                                                        <a href="<?php echo $assignment['back_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                            <iconify-icon icon="mdi:eye"></iconify-icon>View
                                                                        </a>
                                                                        <a href="<?php echo $assignment['back_card_path']; ?>" download class="btn btn-sm btn-outline-success">
                                                                            <iconify-icon icon="mdi:download"></iconify-icon>Download
                                                                        </a>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">N/A</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($assignment['payment_screenshot_path'])): ?>
                                                                    <a href="<?php echo $assignment['payment_screenshot_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                                        <iconify-icon icon="mdi:eye"></iconify-icon>View
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Not Provided</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></td>
                                                            <td><?php echo date('d M Y h:i A', strtotime($assignment['created_at'])); ?></td>
                                                            <td>
                                                                <div class="d-flex flex-column gap-1">
                                                                    <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-warning">
                                                                        <iconify-icon icon="mdi:pencil"></iconify-icon>Edit
                                                                    </a>
                                                                    <?php if (isset($status_history[$assignment['id']]) && count($status_history[$assignment['id']]) > 0): ?>
                                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#historyModal<?php echo $assignment['id']; ?>">
                                                                            <iconify-icon icon="mdi:history"></iconify-icon>History
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="11" class="text-center">
                                                            <?php echo empty($search_term) ? 'No card assignments found' : 'No results found for "' . htmlspecialchars($search_term) . '"'; ?>
                                                            <br>
                                                            <a href="create_cards_assignment.php" class="btn btn-primary mt-2">
                                                                <iconify-icon icon="mdi:plus-circle"></iconify-icon>Create First Assignment
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Mobile Card View -->
                                <div class="table-mobile-view">
                                    <?php if (count($all_assignments) > 0): ?>
                                        <?php foreach ($all_assignments as $assignment): ?>
                                            <div class="card-mobile">
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:identifier"></iconify-icon>Assignment ID</div>
                                                    <div class="mobile-value">#<?php echo $assignment['id']; ?></div>
                                                </div>
                                                
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:account"></iconify-icon>User</div>
                                                    <div class="mobile-value"><?php echo htmlspecialchars($assignment['user_name']); ?></div>
                                                </div>
                                                
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:package-variant"></iconify-icon>Quantity</div>
                                                    <div class="mobile-value"><?php echo number_format($assignment['quantity']); ?></div>
                                                </div>
                                                
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:progress-check"></iconify-icon>Status</div>
                                                    <div class="mobile-value">
                                                        <span class="badge mobile-badge badge-<?php echo str_replace('_', '', $assignment['status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $assignment['status'])); ?>
                                                        </span>
                                                        <?php if (isset($status_history[$assignment['id']]) && count($status_history[$assignment['id']]) > 0): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <iconify-icon icon="mdi:clock"></iconify-icon>Last Updated: <?php 
                                                                $latest_update = $status_history[$assignment['id']][0];
                                                                echo date('M j, Y g:i A', strtotime($latest_update['created_at']));
                                                                ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:account-tie"></iconify-icon>Assigned To</div>
                                                    <div class="mobile-value">
                                                        <?php if (!empty($assignment['printer_name'])): ?>
                                                            <?php echo htmlspecialchars($assignment['printer_name']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not Assigned</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:file"></iconify-icon>Files</div>
                                                    <div class="mobile-value">
                                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                                            <?php if ($assignment['front_card_path']): ?>
                                                                <div class="d-flex flex-column">
                                                                    <small class="text-muted">Front Card:</small>
                                                                    <div class="d-flex gap-1">
                                                                        <a href="<?php echo $assignment['front_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary btn-sm-mobile">
                                                                            <iconify-icon icon="mdi:eye"></iconify-icon>
                                                                        </a>
                                                                        <a href="<?php echo $assignment['front_card_path']; ?>" download class="btn btn-sm btn-outline-success btn-sm-mobile">
                                                                            <iconify-icon icon="mdi:download"></iconify-icon>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($assignment['back_card_path']): ?>
                                                                <div class="d-flex flex-column">
                                                                    <small class="text-muted">Back Card:</small>
                                                                    <div class="d-flex gap-1">
                                                                        <a href="<?php echo $assignment['back_card_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary btn-sm-mobile">
                                                                            <iconify-icon icon="mdi:eye"></iconify-icon>
                                                                        </a>
                                                                        <a href="<?php echo $assignment['back_card_path']; ?>" download class="btn btn-sm btn-outline-success btn-sm-mobile">
                                                                            <iconify-icon icon="mdi:download"></iconify-icon>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($assignment['payment_screenshot_path']): ?>
                                                                <div class="d-flex flex-column">
                                                                    <small class="text-muted">Payment:</small>
                                                                    <a href="<?php echo $assignment['payment_screenshot_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info btn-sm-mobile">
                                                                        <iconify-icon icon="mdi:credit-card"></iconify-icon>
                                                                    </a>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:account-supervisor"></iconify-icon>Assigned By</div>
                                                    <div class="mobile-value"><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></div>
                                                </div>
                                                
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:calendar"></iconify-icon>Created Date</div>
                                                    <div class="mobile-value"><?php echo date('d M Y H:i', strtotime($assignment['created_at'])); ?></div>
                                                </div>
                                                
                                                <?php if (!empty($assignment['printer_notes'])): ?>
                                                    <div class="mobile-section">
                                                        <div class="mobile-label"><iconify-icon icon="mdi:note-text"></iconify-icon>Printer Notes</div>
                                                        <div class="mobile-value"><?php echo htmlspecialchars($assignment['printer_notes']); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="mobile-section">
                                                    <div class="mobile-label"><iconify-icon icon="mdi:cog"></iconify-icon>Actions</div>
                                                    <div class="mobile-value">
                                                        <div class="mobile-action-buttons">
                                                            <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-warning">
                                                                <iconify-icon icon="mdi:pencil"></iconify-icon>Edit
                                                            </a>
                                                            <?php if (isset($status_history[$assignment['id']]) && count($status_history[$assignment['id']]) > 0): ?>
                                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#historyModal<?php echo $assignment['id']; ?>">
                                                                    <iconify-icon icon="mdi:history"></iconify-icon>History
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info text-center">
                                            <iconify-icon icon="mdi:information"></iconify-icon>
                                            <?php echo empty($search_term) ? 'No card assignments found' : 'No results found for "' . htmlspecialchars($search_term) . '"'; ?>
                                            <br>
                                            <a href="create_cards_assignment.php" class="btn btn-primary mt-2">
                                                <iconify-icon icon="mdi:plus-circle"></iconify-icon>Create First Assignment
                                            </a>
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

    <!-- Status History Modals -->
    <?php foreach ($all_assignments as $assignment): ?>
        <?php if (isset($status_history[$assignment['id']]) && count($status_history[$assignment['id']]) > 0): ?>
            <div class="modal fade" id="historyModal<?php echo $assignment['id']; ?>" tabindex="-1" aria-labelledby="historyModalLabel<?php echo $assignment['id']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="historyModalLabel<?php echo $assignment['id']; ?>">
                                <iconify-icon icon="mdi:history"></iconify-icon>Status History - Assignment #<?php echo $assignment['id']; ?> (<?php echo htmlspecialchars($assignment['user_name']); ?>)
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped history-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Status</th>
                                            <th>Printer Notes</th>
                                            <th>Date & Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($status_history[$assignment['id']] as $history): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge badge-<?php echo str_replace('_', '', $history['status']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $history['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $history['printer_notes'] ? htmlspecialchars($history['printer_notes']) : '<span class="text-muted"><iconify-icon icon="mdi:minus"></iconify-icon>No notes</span>'; ?></td>
                                                <td><iconify-icon icon="mdi:clock"></iconify-icon><?php echo date('M j, Y g:i A', strtotime($history['created_at'])); ?></td>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        $(document).ready(function() {
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>
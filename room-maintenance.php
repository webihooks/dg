<?php
// room-maintenance.php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
date_default_timezone_set('Asia/Kolkata');

require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();
$sessionManager->validateAndroidSession();

require_once 'session_check.php';
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get user details
$user_sql = "SELECT name, role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $role);
$user_stmt->fetch();
$user_stmt->close();

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Create maintenance_requests table if not exists (MUST BE DONE BEFORE ANY QUERIES)
$create_table_sql = "
    CREATE TABLE IF NOT EXISTS `maintenance_requests_$user_id` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `room_id` INT(11) NOT NULL,
        `maintenance_type` VARCHAR(100) NOT NULL,
        `description` TEXT NOT NULL,
        `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        `estimated_duration` VARCHAR(50) DEFAULT NULL,
        `assigned_staff` VARCHAR(255) DEFAULT NULL,
        `cost_estimate` DECIMAL(10,2) DEFAULT 0.00,
        `actual_cost` DECIMAL(10,2) DEFAULT 0.00,
        `start_date` DATE DEFAULT NULL,
        `completion_date` DATE DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `room_id` (`room_id`),
        KEY `status` (`status`),
        KEY `priority` (`priority`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($create_table_sql);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_maintenance'])) {
        $room_id = $_POST['room_id'];
        $maintenance_type = $_POST['maintenance_type'];
        $description = $_POST['description'];
        $priority = $_POST['priority'];
        $estimated_duration = $_POST['estimated_duration'];
        $assigned_staff = $_POST['assigned_staff'];
        $cost_estimate = $_POST['cost_estimate'];
        
        $insert_sql = "INSERT INTO maintenance_requests_$user_id 
                      (room_id, maintenance_type, description, priority, estimated_duration, assigned_staff, cost_estimate) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("isssssd", $room_id, $maintenance_type, $description, $priority, $estimated_duration, $assigned_staff, $cost_estimate);
        
        if ($stmt->execute()) {
            // Update room status to maintenance
            $update_room_sql = "UPDATE rooms_$user_id SET status = 'maintenance' WHERE id = ?";
            $update_stmt = $conn->prepare($update_room_sql);
            $update_stmt->bind_param("i", $room_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $success_message = "Maintenance request added successfully!";
        } else {
            $error_message = "Error adding maintenance request: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Update maintenance status
    if (isset($_POST['update_status'])) {
        $maintenance_id = $_POST['maintenance_id'];
        $new_status = $_POST['status'];
        $actual_cost = $_POST['actual_cost'] ?? 0;
        
        $update_sql = "UPDATE maintenance_requests_$user_id SET status = ?, actual_cost = ?";
        
        if ($new_status === 'completed') {
            $update_sql .= ", completion_date = CURDATE()";
            
            // Update room status back to available
            $room_update_sql = "UPDATE rooms_$user_id r 
                               JOIN maintenance_requests_$user_id m ON r.id = m.room_id 
                               SET r.status = 'available' 
                               WHERE m.id = ?";
            $room_stmt = $conn->prepare($room_update_sql);
            $room_stmt->bind_param("i", $maintenance_id);
            $room_stmt->execute();
            $room_stmt->close();
        } elseif ($new_status === 'in_progress') {
            $update_sql .= ", start_date = CURDATE()";
        }
        
        $update_sql .= " WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sdi", $new_status, $actual_cost, $maintenance_id);
        
        if ($stmt->execute()) {
            $success_message = "Maintenance status updated successfully!";
        } else {
            $error_message = "Error updating maintenance status: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Delete maintenance request
    if (isset($_POST['delete_maintenance'])) {
        $maintenance_id = $_POST['maintenance_id'];
        
        // Get room_id before deleting
        $get_room_sql = "SELECT room_id FROM maintenance_requests_$user_id WHERE id = ?";
        $get_stmt = $conn->prepare($get_room_sql);
        $get_stmt->bind_param("i", $maintenance_id);
        $get_stmt->execute();
        $get_stmt->bind_result($room_id);
        $get_stmt->fetch();
        $get_stmt->close();
        
        // Delete maintenance request
        $delete_sql = "DELETE FROM maintenance_requests_$user_id WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $maintenance_id);
        
        if ($stmt->execute()) {
            // Update room status back to available if no other maintenance
            $check_maintenance_sql = "SELECT COUNT(*) FROM maintenance_requests_$user_id 
                                    WHERE room_id = ? AND status IN ('pending', 'in_progress')";
            $check_stmt = $conn->prepare($check_maintenance_sql);
            $check_stmt->bind_param("i", $room_id);
            $check_stmt->execute();
            $check_stmt->bind_result($active_maintenance_count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($active_maintenance_count == 0) {
                $update_room_sql = "UPDATE rooms_$user_id SET status = 'available' WHERE id = ?";
                $update_stmt = $conn->prepare($update_room_sql);
                $update_stmt->bind_param("i", $room_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            $success_message = "Maintenance request deleted successfully!";
        } else {
            $error_message = "Error deleting maintenance request: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get available rooms for dropdown
$rooms_sql = "SELECT id, room_number, room_type_id FROM rooms_$user_id 
              WHERE status != 'maintenance' 
              ORDER BY room_number";
$rooms_result = $conn->query($rooms_sql);
$available_rooms = [];
while ($row = $rooms_result->fetch_assoc()) {
    $available_rooms[] = $row;
}

// Get room types for display
$room_types_sql = "SELECT id, name FROM room_types_$user_id";
$room_types_result = $conn->query($room_types_sql);
$room_types = [];
while ($row = $room_types_result->fetch_assoc()) {
    $room_types[$row['id']] = $row['name'];
}

// Get maintenance requests - NOW SAFE TO QUERY SINCE TABLE IS CREATED
$maintenance_sql = "
    SELECT m.*, r.room_number, rt.name as room_type 
    FROM maintenance_requests_$user_id m 
    LEFT JOIN rooms_$user_id r ON m.room_id = r.id 
    LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id 
    ORDER BY 
        CASE m.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        m.created_at DESC
";
$maintenance_result = $conn->query($maintenance_sql);
$maintenance_requests = [];
if ($maintenance_result) {
    while ($row = $maintenance_result->fetch_assoc()) {
        $maintenance_requests[] = $row;
    }
}

// Get maintenance statistics - SAFE TO QUERY NOW
$stats_sql = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_requests,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
        SUM(actual_cost) as total_cost
    FROM maintenance_requests_$user_id
    WHERE MONTH(created_at) = MONTH(CURDATE())
";
$stats_result = $conn->query($stats_sql);
$maintenance_stats = [];
if ($stats_result) {
    $maintenance_stats = $stats_result->fetch_assoc();
} else {
    // Initialize empty stats if table is empty
    $maintenance_stats = [
        'total_requests' => 0,
        'pending_requests' => 0,
        'in_progress_requests' => 0,
        'completed_requests' => 0,
        'total_cost' => 0
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room Maintenance Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .maintenance-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .maintenance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .priority-urgent { border-left-color: #dc3545; background: #fff5f5; }
        .priority-high { border-left-color: #fd7e14; background: #fff9f0; }
        .priority-medium { border-left-color: #ffc107; background: #fffce6; }
        .priority-low { border-left-color: #28a745; background: #f8fff8; }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending { background: #ffc107; color: #000; }
        .status-in_progress { background: #17a2b8; color: #fff; }
        .status-completed { background: #28a745; color: #fff; }
        .status-cancelled { background: #6c757d; color: #fff; }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .maintenance-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .action-buttons .btn {
            margin: 2px;
        }
        
        @media (max-width: 768px) {
            .action-buttons .btn {
                display: block;
                width: 100%;
                margin-bottom: 5px;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">🏗️ Room Maintenance Management</h4>
                            <div class="page-title-right">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                                    <i class="fas fa-plus-circle me-1"></i> Add Maintenance Request
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stat-number"><?php echo $maintenance_stats['total_requests'] ?? 0; ?></div>
                            <div class="stat-label">Total Requests This Month</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                            <div class="stat-number"><?php echo $maintenance_stats['pending_requests'] ?? 0; ?></div>
                            <div class="stat-label">Pending Requests</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);">
                            <div class="stat-number"><?php echo $maintenance_stats['in_progress_requests'] ?? 0; ?></div>
                            <div class="stat-label">In Progress</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #a8e6cf 0%, #56ab2f 100%);">
                            <div class="stat-number">₹<?php echo number_format($maintenance_stats['total_cost'] ?? 0); ?></div>
                            <div class="stat-label">Total Cost This Month</div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Requests Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Maintenance Requests</h4>
                                <p class="text-muted mb-0">Manage room maintenance and repair requests</p>
                            </div>
                            <div class="card-body">
                                <?php if (empty($maintenance_requests)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-tools"></i>
                                        <h4>No Maintenance Requests</h4>
                                        <p class="text-muted mb-4">You haven't created any maintenance requests yet.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                                            <i class="fas fa-plus-circle me-1"></i> Create Your First Request
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover maintenance-table">
                                            <thead>
                                                <tr>
                                                    <th>Room</th>
                                                    <th>Type</th>
                                                    <th>Description</th>
                                                    <th>Priority</th>
                                                    <th>Status</th>
                                                    <th>Assigned Staff</th>
                                                    <th>Cost Estimate</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($maintenance_requests as $request): ?>
                                                    <tr class="priority-<?php echo $request['priority']; ?>">
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($request['room_number']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($request['room_type']); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($request['maintenance_type']); ?></td>
                                                        <td>
                                                            <small><?php echo htmlspecialchars($request['description']); ?></small>
                                                            <?php if ($request['estimated_duration']): ?>
                                                                <br><small class="text-info">Est: <?php echo $request['estimated_duration']; ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $request['priority'] === 'urgent' ? 'danger' : 
                                                                     ($request['priority'] === 'high' ? 'warning' : 
                                                                     ($request['priority'] === 'medium' ? 'info' : 'success')); 
                                                            ?>">
                                                                <?php echo ucfirst($request['priority']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $request['assigned_staff'] ? htmlspecialchars($request['assigned_staff']) : '<span class="text-muted">Not assigned</span>'; ?></td>
                                                        <td>
                                                            ₹<?php echo number_format($request['cost_estimate']); ?>
                                                            <?php if ($request['actual_cost'] > 0): ?>
                                                                <br><small class="text-success">Actual: ₹<?php echo number_format($request['actual_cost']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small><?php echo date('M j, Y', strtotime($request['created_at'])); ?></small>
                                                            <?php if ($request['start_date']): ?>
                                                                <br><small class="text-info">Started: <?php echo date('M j', strtotime($request['start_date'])); ?></small>
                                                            <?php endif; ?>
                                                            <?php if ($request['completion_date']): ?>
                                                                <br><small class="text-success">Completed: <?php echo date('M j', strtotime($request['completion_date'])); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="action-buttons">
                                                            <?php if ($request['status'] === 'pending' || $request['status'] === 'in_progress'): ?>
                                                                <button class="btn btn-sm btn-outline-primary" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#updateStatusModal"
                                                                        data-id="<?php echo $request['id']; ?>"
                                                                        data-current-status="<?php echo $request['status']; ?>"
                                                                        data-cost-estimate="<?php echo $request['cost_estimate']; ?>">
                                                                    Update Status
                                                                </button>
                                                            <?php endif; ?>
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this maintenance request?');">
                                                                <input type="hidden" name="maintenance_id" value="<?php echo $request['id']; ?>">
                                                                <button type="submit" name="delete_maintenance" class="btn btn-sm btn-outline-danger">Delete</button>
                                                            </form>
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
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Maintenance Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Room *</label>
                                    <select class="form-select" name="room_id" required>
                                        <option value="">Select Room</option>
                                        <?php foreach ($available_rooms as $room): ?>
                                            <option value="<?php echo $room['id']; ?>">
                                                <?php echo htmlspecialchars($room['room_number']); ?> 
                                                (<?php echo isset($room_types[$room['room_type_id']]) ? $room_types[$room['room_type_id']] : 'Unknown'; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Maintenance Type *</label>
                                    <select class="form-select" name="maintenance_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Electrical">Electrical</option>
                                        <option value="Plumbing">Plumbing</option>
                                        <option value="HVAC">HVAC</option>
                                        <option value="Carpentry">Carpentry</option>
                                        <option value="Painting">Painting</option>
                                        <option value="Cleaning">Deep Cleaning</option>
                                        <option value="Furniture">Furniture Repair</option>
                                        <option value="Appliance">Appliance Repair</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Describe the maintenance issue..." required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Priority *</label>
                                    <select class="form-select" name="priority" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Estimated Duration</label>
                                    <input type="text" class="form-control" name="estimated_duration" placeholder="e.g., 2 hours, 1 day">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Cost Estimate (₹)</label>
                                    <input type="number" class="form-control" name="cost_estimate" step="0.01" min="0" value="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assigned Staff</label>
                            <input type="text" class="form-control" name="assigned_staff" placeholder="Staff member name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_maintenance" class="btn btn-primary">Add Maintenance Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Maintenance Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="maintenance_id" id="update_maintenance_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" id="status_select" required>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3" id="actual_cost_container" style="display: none;">
                            <label class="form-label">Actual Cost (₹)</label>
                            <input type="number" class="form-control" name="actual_cost" id="actual_cost" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    // Update Status Modal Handler
    document.addEventListener('DOMContentLoaded', function() {
        var updateStatusModal = document.getElementById('updateStatusModal');
        if (updateStatusModal) {
            updateStatusModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var maintenanceId = button.getAttribute('data-id');
                var currentStatus = button.getAttribute('data-current-status');
                var costEstimate = button.getAttribute('data-cost-estimate');
                
                document.getElementById('update_maintenance_id').value = maintenanceId;
                document.getElementById('status_select').value = currentStatus;
                document.getElementById('actual_cost').value = costEstimate;
                
                // Show/hide actual cost field based on status
                toggleActualCostField();
            });
        }
        
        // Toggle actual cost field when status changes
        document.getElementById('status_select').addEventListener('change', toggleActualCostField);
        
        function toggleActualCostField() {
            var status = document.getElementById('status_select').value;
            var costContainer = document.getElementById('actual_cost_container');
            
            if (status === 'completed') {
                costContainer.style.display = 'block';
            } else {
                costContainer.style.display = 'none';
            }
        }
        
        // Android session protection
        if (typeof WTN !== 'undefined') {
            setInterval(() => {
                if (WTN.forceUpdateCookies) {
                    WTN.forceUpdateCookies();
                }
            }, 45000);
        }
    });
    </script>
</body>
</html>
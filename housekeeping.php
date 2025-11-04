<?php
// housekeeping.php
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

// Check if housekeeping table exists, if not create it with correct structure
$check_table_sql = "SHOW TABLES LIKE 'housekeeping_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    // Create housekeeping table with CORRECT column names
    $create_table_sql = "
        CREATE TABLE `housekeeping_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `room_id` INT(11) NOT NULL,
            `room_number` VARCHAR(20) NOT NULL,
            `task_type` ENUM('cleaning', 'maintenance', 'inspection', 'deep_cleaning') DEFAULT 'cleaning',
            `assigned_to` VARCHAR(255) DEFAULT NULL,
            `task_date` DATE NOT NULL,
            `task_time` TIME DEFAULT NULL,
            `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'verified') DEFAULT 'scheduled',
            `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            `notes` TEXT,
            `completed_at` DATETIME DEFAULT NULL,
            `completed_by` VARCHAR(255) DEFAULT NULL,
            `completion_notes` TEXT,
            `verified_by` VARCHAR(255) DEFAULT NULL,
            `verified_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `room_id` (`room_id`),
            KEY `task_date` (`task_date`),
            KEY `status` (`status`),
            KEY `priority` (`priority`),
            UNIQUE KEY `unique_daily_task` (`room_id`, `task_date`, `task_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($create_table_sql) === FALSE) {
        $error_message = "Error creating housekeeping table: " . $conn->error;
    } else {
        $success_message = "Housekeeping table created successfully!";
    }
} else {
    // Check if we need to alter the table structure to match the new schema
    $check_columns_sql = "SHOW COLUMNS FROM housekeeping_$user_id LIKE 'task_date'";
    $column_result = $conn->query($check_columns_sql);
    if ($column_result->num_rows == 0) {
        // Table exists but has old structure - alter it
        $alter_table_sql = "
            ALTER TABLE housekeeping_$user_id 
            CHANGE COLUMN cleaning_date task_date DATE NOT NULL,
            CHANGE COLUMN cleaning_time task_time TIME DEFAULT NULL,
            CHANGE COLUMN cleaning_type task_type ENUM('daily', 'checkout', 'deep', 'special', 'turn_down') DEFAULT 'daily',
            MODIFY COLUMN status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'verified') DEFAULT 'scheduled',
            ADD COLUMN IF NOT EXISTS priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            ADD COLUMN IF NOT EXISTS completion_notes TEXT,
            ADD COLUMN IF NOT EXISTS verified_by VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL
        ";
        
        if ($conn->query($alter_table_sql) === FALSE) {
            $error_message = "Error updating table structure: " . $conn->error;
        } else {
            $success_message = "Housekeeping table structure updated successfully!";
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_task'])) {
        // Add new housekeeping task
        $room_id = $conn->real_escape_string($_POST['room_id']);
        $room_number = $conn->real_escape_string($_POST['room_number']);
        $task_type = $conn->real_escape_string($_POST['task_type']);
        $assigned_to = $conn->real_escape_string($_POST['assigned_to']);
        $task_date = $conn->real_escape_string($_POST['task_date']);
        $task_time = $conn->real_escape_string($_POST['task_time']);
        $priority = $conn->real_escape_string($_POST['priority']);
        $notes = $conn->real_escape_string($_POST['notes']);
        
        $insert_sql = "INSERT INTO housekeeping_$user_id 
                      (room_id, room_number, task_type, assigned_to, task_date, task_time, priority, notes) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("isssssss", $room_id, $room_number, $task_type, $assigned_to, $task_date, $task_time, $priority, $notes);
        
        if ($stmt->execute()) {
            $success_message = "Housekeeping task added successfully!";
        } else {
            $error_message = "Error adding task: " . $stmt->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_status'])) {
        // Update task status
        $task_id = $conn->real_escape_string($_POST['task_id']);
        $status = $conn->real_escape_string($_POST['status']);
        $completion_notes = $conn->real_escape_string($_POST['completion_notes'] ?? '');
        
        if ($status === 'completed') {
            $update_sql = "UPDATE housekeeping_$user_id 
                          SET status = ?, completed_at = NOW(), completion_notes = ?
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssi", $status, $completion_notes, $task_id);
        } else {
            $update_sql = "UPDATE housekeeping_$user_id SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $status, $task_id);
        }
        
        if ($stmt->execute()) {
            $success_message = "Task status updated successfully!";
        } else {
            $error_message = "Error updating task: " . $stmt->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['delete_task'])) {
        // Delete task
        $task_id = $conn->real_escape_string($_POST['task_id']);
        
        $delete_sql = "DELETE FROM housekeeping_$user_id WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $task_id);
        
        if ($stmt->execute()) {
            $success_message = "Task deleted successfully!";
        } else {
            $error_message = "Error deleting task: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get today's date
$today = date('Y-m-d');

// Get housekeeping tasks - UPDATED QUERY with correct column names
$tasks_sql = "SELECT hk.*, r.status as room_status 
              FROM housekeeping_$user_id hk
              LEFT JOIN rooms_$user_id r ON hk.room_id = r.id
              ORDER BY 
                CASE hk.priority 
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                hk.task_date ASC,
                hk.task_time ASC";
$tasks_result = $conn->query($tasks_sql);
$tasks = [];
if ($tasks_result) {
    $tasks = $tasks_result->fetch_all(MYSQLI_ASSOC);
} else {
    $error_message = "Error fetching tasks: " . $conn->error;
}

// Get available rooms for dropdown
$rooms_sql = "SELECT id, room_number, status FROM rooms_$user_id ORDER BY room_number";
$rooms_result = $conn->query($rooms_sql);
$rooms = [];
if ($rooms_result) {
    $rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);
} else {
    // If rooms table doesn't exist, create sample rooms
    $create_sample_rooms = "
        INSERT IGNORE INTO rooms_$user_id (room_number, room_type_id, status, rate_per_night) 
        VALUES 
        ('101', 1, 'available', 2500.00),
        ('102', 1, 'available', 2500.00),
        ('201', 2, 'available', 4000.00),
        ('202', 2, 'occupied', 4000.00)
    ";
    $conn->query($create_sample_rooms);
    $rooms = [
        ['id' => 1, 'room_number' => '101', 'status' => 'available'],
        ['id' => 2, 'room_number' => '102', 'status' => 'available'],
        ['id' => 3, 'room_number' => '201', 'status' => 'available'],
        ['id' => 4, 'room_number' => '202', 'status' => 'occupied']
    ];
}

// Get statistics - UPDATED QUERY with correct column names
$stats_sql = "SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN task_date = '$today' THEN 1 ELSE 0 END) as today_tasks
              FROM housekeeping_$user_id";
$stats_result = $conn->query($stats_sql);
$stats = [];
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
} else {
    $stats = [
        'total_tasks' => 0,
        'pending_tasks' => 0,
        'in_progress_tasks' => 0,
        'completed_tasks' => 0,
        'today_tasks' => 0
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Housekeeping Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .task-priority-urgent { border-left: 4px solid #dc3545; }
        .task-priority-high { border-left: 4px solid #fd7e14; }
        .task-priority-medium { border-left: 4px solid #ffc107; }
        .task-priority-low { border-left: 4px solid #28a745; }
        
        .task-status-scheduled { background-color: #f8f9fa; }
        .task-status-in_progress { background-color: #e7f1ff; }
        .task-status-completed { background-color: #d4edda; }
        .task-status-cancelled { background-color: #f8d7da; }
        .task-status-verified { background-color: #d1ecf1; }
        
        .task-type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .task-type-daily { background-color: #17a2b8; color: white; }
        .task-type-checkout { background-color: #6f42c1; color: white; }
        .task-type-deep { background-color: #20c997; color: white; }
        .task-type-special { background-color: #e83e8c; color: white; }
        .task-type-turn_down { background-color: #fd7e14; color: white; }
        
        .room-status-available { color: #28a745; }
        .room-status-occupied { color: #dc3545; }
        .room-status-maintenance { color: #ffc107; }
        
        .action-buttons .btn {
            margin: 2px;
            font-size: 12px;
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
            font-size: 48px;
            margin-bottom: 15px;
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
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Notifications -->
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stats-card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Total Tasks</h5>
                                        <h2 class="card-text"><?php echo $stats['total_tasks'] ?? 0; ?></h2>
                                        <p class="card-text">All housekeeping tasks</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card bg-warning text-dark">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Scheduled</h5>
                                        <h2 class="card-text"><?php echo $stats['pending_tasks'] ?? 0; ?></h2>
                                        <p class="card-text">Tasks to be done</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">In Progress</h5>
                                        <h2 class="card-text"><?php echo $stats['in_progress_tasks'] ?? 0; ?></h2>
                                        <p class="card-text">Currently working</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Today's Tasks</h5>
                                        <h2 class="card-text"><?php echo $stats['today_tasks'] ?? 0; ?></h2>
                                        <p class="card-text">Scheduled for today</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Add New Task Form -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="card-title mb-0">➕ Add New Task</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="addTaskForm">
                                            <div class="mb-3">
                                                <label class="form-label">Room</label>
                                                <select class="form-select" name="room_id" id="roomSelect" required>
                                                    <option value="">Select Room</option>
                                                    <?php foreach ($rooms as $room): ?>
                                                        <option value="<?php echo $room['id']; ?>" 
                                                                data-room-number="<?php echo $room['room_number']; ?>">
                                                            <?php echo $room['room_number']; ?> 
                                                            (<?php echo ucfirst($room['status']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="room_number" id="roomNumber">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Task Type</label>
                                                <select class="form-select" name="task_type" required>
                                                    <option value="daily">🧹 Daily Cleaning</option>
                                                    <option value="checkout">🚪 Checkout Cleaning</option>
                                                    <option value="deep">✨ Deep Cleaning</option>
                                                    <option value="special">🎯 Special Cleaning</option>
                                                    <option value="turn_down">🛏️ Turn Down Service</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Assigned To</label>
                                                <input type="text" class="form-control" name="assigned_to" 
                                                       placeholder="Staff member name">
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Task Date</label>
                                                        <input type="date" class="form-control" name="task_date" 
                                                               value="<?php echo $today; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Task Time</label>
                                                        <input type="time" class="form-control" name="task_time">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Priority</label>
                                                <select class="form-select" name="priority" required>
                                                    <option value="low">🟢 Low</option>
                                                    <option value="medium" selected>🟡 Medium</option>
                                                    <option value="high">🟠 High</option>
                                                    <option value="urgent">🔴 Urgent</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" name="notes" rows="3" 
                                                          placeholder="Any special instructions..."></textarea>
                                            </div>
                                            
                                            <button type="submit" name="add_task" class="btn btn-primary w-100">
                                                <i class="fas fa-plus-circle me-1"></i>Add Task
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="card mt-4">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="card-title mb-0">⚡ Quick Actions</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="quickAddTask('daily')">
                                                Add Daily Cleaning
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="quickAddTask('checkout')">
                                                Add Checkout Cleaning
                                            </button>
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="quickAddTask('urgent')">
                                                Add Urgent Task
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tasks List -->
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">📋 Housekeeping Tasks</h5>
                                        <div class="btn-group">
                                            <button class="btn btn-outline-light btn-sm" onclick="filterTasks('all')">All</button>
                                            <button class="btn btn-outline-warning btn-sm" onclick="filterTasks('scheduled')">Scheduled</button>
                                            <button class="btn btn-outline-info btn-sm" onclick="filterTasks('in_progress')">In Progress</button>
                                            <button class="btn btn-outline-success btn-sm" onclick="filterTasks('completed')">Completed</button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($tasks)): ?>
                                            <div class="empty-state">
                                                <i class="fas fa-broom"></i>
                                                <h5>No Housekeeping Tasks Found</h5>
                                                <p class="text-muted">Get started by adding your first housekeeping task using the form on the left.</p>
                                                <button type="button" class="btn btn-primary" onclick="document.getElementById('roomSelect').focus()">
                                                    <i class="fas fa-plus me-1"></i>Add First Task
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Room</th>
                                                            <th>Task Type</th>
                                                            <th>Assigned To</th>
                                                            <th>Scheduled</th>
                                                            <th>Priority</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($tasks as $task): ?>
                                                            <tr class="task-status-<?php echo $task['status']; ?> task-priority-<?php echo $task['priority']; ?>">
                                                                <td>
                                                                    <strong><?php echo $task['room_number']; ?></strong>
                                                                    <?php if (isset($task['room_status'])): ?>
                                                                        <br>
                                                                        <small class="room-status-<?php echo $task['room_status']; ?>">
                                                                            (<?php echo ucfirst($task['room_status']); ?>)
                                                                        </small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="task-type-badge task-type-<?php echo $task['task_type']; ?>">
                                                                        <?php 
                                                                        $task_type_labels = [
                                                                            'daily' => 'Daily',
                                                                            'checkout' => 'Checkout', 
                                                                            'deep' => 'Deep Clean',
                                                                            'special' => 'Special',
                                                                            'turn_down' => 'Turn Down'
                                                                        ];
                                                                        echo $task_type_labels[$task['task_type']] ?? ucfirst($task['task_type']);
                                                                        ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo $task['assigned_to'] ?: 'Not assigned'; ?></td>
                                                                <td>
                                                                    <?php echo date('M j, Y', strtotime($task['task_date'])); ?>
                                                                    <?php if ($task['task_time']): ?>
                                                                        <br>
                                                                        <small><?php echo date('g:i A', strtotime($task['task_time'])); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php 
                                                                    $priority_icons = [
                                                                        'urgent' => '🔴',
                                                                        'high' => '🟠', 
                                                                        'medium' => '🟡',
                                                                        'low' => '🟢'
                                                                    ];
                                                                    echo $priority_icons[$task['priority']] . ' ' . ucfirst($task['priority']);
                                                                    ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-<?php 
                                                                        echo $task['status'] === 'completed' ? 'success' : 
                                                                             ($task['status'] === 'in_progress' ? 'info' : 
                                                                             ($task['status'] === 'cancelled' ? 'danger' : 
                                                                             ($task['status'] === 'verified' ? 'primary' : 'warning'))); 
                                                                    ?>">
                                                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                                    </span>
                                                                    <?php if ($task['completed_at']): ?>
                                                                        <br>
                                                                        <small>Completed: <?php echo date('M j, g:i A', strtotime($task['completed_at'])); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="action-buttons">
                                                                    <?php if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled'): ?>
                                                                        <button class="btn btn-success btn-sm" 
                                                                                onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'completed')">
                                                                            ✅ Complete
                                                                        </button>
                                                                        <button class="btn btn-info btn-sm" 
                                                                                onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'in_progress')">
                                                                            🔄 In Progress
                                                                        </button>
                                                                        <button class="btn btn-warning btn-sm" 
                                                                                onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'scheduled')">
                                                                            ⏸️ Scheduled
                                                                        </button>
                                                                    <?php endif; ?>
                                                                    <button class="btn btn-danger btn-sm" 
                                                                            onclick="deleteTask(<?php echo $task['id']; ?>)">
                                                                        🗑️ Delete
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php if ($task['notes'] || $task['completion_notes']): ?>
                                                                <tr>
                                                                    <td colspan="7" style="border-top: none; padding-top: 0;">
                                                                        <div class="bg-light p-2 rounded">
                                                                            <?php if ($task['notes']): ?>
                                                                                <small><strong>Notes:</strong> <?php echo $task['notes']; ?></small><br>
                                                                            <?php endif; ?>
                                                                            <?php if ($task['completion_notes']): ?>
                                                                                <small><strong>Completion Notes:</strong> <?php echo $task['completion_notes']; ?></small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endif; ?>
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
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Task Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="statusForm">
                    <div class="modal-body">
                        <input type="hidden" name="task_id" id="taskId">
                        <input type="hidden" name="status" id="taskStatus">
                        
                        <div class="mb-3">
                            <label class="form-label">Completion Notes (Optional)</label>
                            <textarea class="form-control" name="completion_notes" rows="3" 
                                      placeholder="Add any completion notes..."></textarea>
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
    // Room selection handler
    document.getElementById('roomSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const roomNumber = selectedOption.getAttribute('data-room-number');
        document.getElementById('roomNumber').value = roomNumber;
    });

    // Task status update
    function updateTaskStatus(taskId, status) {
        document.getElementById('taskId').value = taskId;
        document.getElementById('taskStatus').value = status;
        
        if (status === 'completed') {
            document.querySelector('#statusModal .modal-title').textContent = 'Complete Task';
            document.querySelector('textarea[name="completion_notes"]').placeholder = 'Add completion notes...';
        } else {
            document.querySelector('#statusModal .modal-title').textContent = 'Update Task Status';
            document.querySelector('textarea[name="completion_notes"]').placeholder = 'Add any notes...';
        }
        
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    }

    // Task deletion
    function deleteTask(taskId) {
        if (confirm('Are you sure you want to delete this task?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="task_id" value="${taskId}">
                <input type="hidden" name="delete_task" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Task filtering
    function filterTasks(status) {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            if (status === 'all') {
                row.style.display = '';
            } else {
                if (row.classList.contains(`task-status-${status}`)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }

    // Quick add task functions
    function quickAddTask(type) {
        const form = document.getElementById('addTaskForm');
        
        if (type === 'daily') {
            form.querySelector('select[name="task_type"]').value = 'daily';
            form.querySelector('select[name="priority"]').value = 'medium';
        } else if (type === 'checkout') {
            form.querySelector('select[name="task_type"]').value = 'checkout';
            form.querySelector('select[name="priority"]').value = 'high';
        } else if (type === 'urgent') {
            form.querySelector('select[name="task_type"]').value = 'special';
            form.querySelector('select[name="priority"]').value = 'urgent';
        }
        
        form.querySelector('select[name="room_id"]').focus();
    }

    // Auto-refresh tasks every 30 seconds
    setInterval(() => {
        window.location.reload();
    }, 30000);

    // Android session protection
    function setupHousekeepingSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('🏨 Housekeeping: Setting up Android session protection');
        
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 1000);
        
        setInterval(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 45000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupHousekeepingSessionProtection();
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>
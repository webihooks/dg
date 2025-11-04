<?php
// room-rates.php
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
$user_table_prefix = $user_id;

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'room_types_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room_rate'])) {
        // Add new room rate
        $room_type_id = $conn->real_escape_string($_POST['room_type_id']);
        $season_name = $conn->real_escape_string($_POST['season_name']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $rate_per_night = $conn->real_escape_string($_POST['rate_per_night']);
        $extra_adult_charge = $conn->real_escape_string($_POST['extra_adult_charge'] ?? 0);
        $extra_child_charge = $conn->real_escape_string($_POST['extra_child_charge'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Check for overlapping seasonal rates
        $check_overlap_sql = "SELECT id FROM room_rates_$user_id 
                             WHERE room_type_id = ? 
                             AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) 
                             OR (? BETWEEN start_date AND end_date) OR (? BETWEEN start_date AND end_date))
                             AND id != ?";
        $check_stmt = $conn->prepare($check_overlap_sql);
        $check_stmt->bind_param("issssssi", $room_type_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, 0);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Seasonal rate already exists for the selected date range!";
        } else {
            $insert_sql = "INSERT INTO room_rates_$user_id 
                          (room_type_id, season_name, start_date, end_date, rate_per_night, 
                           extra_adult_charge, extra_child_charge, is_active, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("isssdddi", $room_type_id, $season_name, $start_date, $end_date, 
                            $rate_per_night, $extra_adult_charge, $extra_child_charge, $is_active);
            
            if ($stmt->execute()) {
                $success_message = "Room rate added successfully!";
            } else {
                $error_message = "Error adding room rate: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    elseif (isset($_POST['update_room_rate'])) {
        // Update room rate
        $rate_id = $conn->real_escape_string($_POST['rate_id']);
        $room_type_id = $conn->real_escape_string($_POST['room_type_id']);
        $season_name = $conn->real_escape_string($_POST['season_name']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $rate_per_night = $conn->real_escape_string($_POST['rate_per_night']);
        $extra_adult_charge = $conn->real_escape_string($_POST['extra_adult_charge'] ?? 0);
        $extra_child_charge = $conn->real_escape_string($_POST['extra_child_charge'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Check for overlapping seasonal rates (excluding current rate)
        $check_overlap_sql = "SELECT id FROM room_rates_$user_id 
                             WHERE room_type_id = ? 
                             AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) 
                             OR (? BETWEEN start_date AND end_date) OR (? BETWEEN start_date AND end_date))
                             AND id != ?";
        $check_stmt = $conn->prepare($check_overlap_sql);
        $check_stmt->bind_param("issssssi", $room_type_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $rate_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Seasonal rate already exists for the selected date range!";
        } else {
            $update_sql = "UPDATE room_rates_$user_id 
                          SET room_type_id = ?, season_name = ?, start_date = ?, end_date = ?, 
                          rate_per_night = ?, extra_adult_charge = ?, extra_child_charge = ?, 
                          is_active = ?, updated_at = NOW() 
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("isssdddii", $room_type_id, $season_name, $start_date, $end_date, 
                            $rate_per_night, $extra_adult_charge, $extra_child_charge, $is_active, $rate_id);
            
            if ($stmt->execute()) {
                $success_message = "Room rate updated successfully!";
            } else {
                $error_message = "Error updating room rate: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    elseif (isset($_POST['delete_rate'])) {
        // Delete room rate
        $rate_id = $conn->real_escape_string($_POST['rate_id']);
        
        $delete_sql = "DELETE FROM room_rates_$user_id WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $rate_id);
        
        if ($stmt->execute()) {
            $success_message = "Room rate deleted successfully!";
        } else {
            $error_message = "Error deleting room rate: " . $conn->error;
        }
        $stmt->close();
    }
}

// Check if room_rates table exists, if not create it
$check_rates_table = "SHOW TABLES LIKE 'room_rates_$user_id'";
$rates_table_result = $conn->query($check_rates_table);
if ($rates_table_result->num_rows == 0) {
    // Create room_rates table
    $create_rates_table = "CREATE TABLE IF NOT EXISTS `room_rates_$user_id` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `room_type_id` INT(11) NOT NULL,
        `season_name` VARCHAR(100) NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `rate_per_night` DECIMAL(10,2) NOT NULL,
        `extra_adult_charge` DECIMAL(10,2) DEFAULT 0.00,
        `extra_child_charge` DECIMAL(10,2) DEFAULT 0.00,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `room_type_id` (`room_type_id`),
        KEY `season_dates` (`start_date`, `end_date`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($create_rates_table) !== TRUE) {
        $error_message = "Error creating room rates table: " . $conn->error;
    }
}

// Fetch room types for dropdown
$room_types_sql = "SELECT id, name, base_rate FROM room_types_$user_id WHERE is_active = 1 ORDER BY name";
$room_types_result = $conn->query($room_types_sql);

// Fetch room rates with room type information
$room_rates_sql = "SELECT rr.*, rt.name as room_type_name, rt.base_rate as default_rate 
                   FROM room_rates_$user_id rr 
                   LEFT JOIN room_types_$user_id rt ON rr.room_type_id = rt.id 
                   ORDER BY rt.name, rr.start_date";
$room_rates_result = $conn->query($room_rates_sql);

// Check if editing a specific rate
$edit_rate = null;
if (isset($_GET['edit'])) {
    $rate_id = $conn->real_escape_string($_GET['edit']);
    $edit_sql = "SELECT * FROM room_rates_$user_id WHERE id = ?";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $rate_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    $edit_rate = $edit_result->fetch_assoc();
    $edit_stmt->close();
}

// Get statistics for the dashboard cards - USING EXISTING CONNECTION
$active_count_sql = "SELECT COUNT(*) as count FROM room_rates_$user_id WHERE is_active = 1";
$active_result = $conn->query($active_count_sql);
$active_count = $active_result->fetch_assoc()['count'];

$current_count_sql = "SELECT COUNT(*) as count FROM room_rates_$user_id 
                     WHERE is_active = 1 
                     AND start_date <= CURDATE() 
                     AND end_date >= CURDATE()";
$current_result = $conn->query($current_count_sql);
$current_count = $current_result->fetch_assoc()['count'];

$room_types_count_sql = "SELECT COUNT(DISTINCT room_type_id) as count FROM room_rates_$user_id";
$room_types_count_result = $conn->query($room_types_count_sql);
$room_types_count = $room_types_count_result->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room Rates Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .rate-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .rate-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .rate-card.active {
            border-left-color: #28a745;
        }
        .rate-card.inactive {
            border-left-color: #6c757d;
            opacity: 0.7;
        }
        .season-badge {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .rate-comparison {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .rate-difference {
            font-weight: bold;
        }
        .rate-difference.positive {
            color: #28a745;
        }
        .rate-difference.negative {
            color: #dc3545;
        }
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">Room Rates Management</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="room-dashboard.php">Room Management</a></li>
                                    <li class="breadcrumb-item active">Room Rates</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $active_count; ?></h4>
                                        <p class="mb-0">Active Rates</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $current_count; ?></h4>
                                        <p class="mb-0">Current Seasons</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $room_types_count; ?></h4>
                                        <p class="mb-0">Room Types with Rates</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-bed fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Add/Edit Room Rate Form -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <?php echo $edit_rate ? 'Edit Room Rate' : 'Add New Room Rate'; ?>
                                </h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="roomRateForm">
                                    <?php if ($edit_rate): ?>
                                        <input type="hidden" name="rate_id" value="<?php echo $edit_rate['id']; ?>">
                                        <input type="hidden" name="update_room_rate" value="1">
                                    <?php else: ?>
                                        <input type="hidden" name="add_room_rate" value="1">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="room_type_id" class="form-label">Room Type *</label>
                                        <select class="form-select" id="room_type_id" name="room_type_id" required>
                                            <option value="">Select Room Type</option>
                                            <?php 
                                            // Reset pointer and loop through room types again
                                            $room_types_result->data_seek(0);
                                            while($room_type = $room_types_result->fetch_assoc()): 
                                            ?>
                                                <option value="<?php echo $room_type['id']; ?>" 
                                                    <?php echo ($edit_rate && $edit_rate['room_type_id'] == $room_type['id']) ? 'selected' : ''; ?>
                                                    data-base-rate="<?php echo $room_type['base_rate']; ?>">
                                                    <?php echo htmlspecialchars($room_type['name']); ?> 
                                                    (Base: ₹<?php echo number_format($room_type['base_rate']); ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="season_name" class="form-label">Season Name *</label>
                                        <input type="text" class="form-control" id="season_name" name="season_name" 
                                               value="<?php echo $edit_rate ? htmlspecialchars($edit_rate['season_name']) : ''; ?>" 
                                               placeholder="e.g., Peak Season, Off Season, Festival" required>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="start_date" class="form-label">Start Date *</label>
                                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                                       value="<?php echo $edit_rate ? $edit_rate['start_date'] : ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="end_date" class="form-label">End Date *</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                                       value="<?php echo $edit_rate ? $edit_rate['end_date'] : ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="rate_per_night" class="form-label">Rate Per Night (₹) *</label>
                                        <input type="number" class="form-control" id="rate_per_night" name="rate_per_night" 
                                               step="0.01" min="0"
                                               value="<?php echo $edit_rate ? $edit_rate['rate_per_night'] : ''; ?>" 
                                               placeholder="0.00" required>
                                        <div class="form-text" id="rateComparison"></div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="extra_adult_charge" class="form-label">Extra Adult Charge (₹)</label>
                                                <input type="number" class="form-control" id="extra_adult_charge" name="extra_adult_charge" 
                                                       step="0.01" min="0"
                                                       value="<?php echo $edit_rate ? $edit_rate['extra_adult_charge'] : '0'; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="extra_child_charge" class="form-label">Extra Child Charge (₹)</label>
                                                <input type="number" class="form-control" id="extra_child_charge" name="extra_child_charge" 
                                                       step="0.01" min="0"
                                                       value="<?php echo $edit_rate ? $edit_rate['extra_child_charge'] : '0'; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                                   <?php echo (!$edit_rate || $edit_rate['is_active']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_active">Active Rate</label>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <?php echo $edit_rate ? 'Update Rate' : 'Add Rate'; ?>
                                        </button>
                                        <?php if ($edit_rate): ?>
                                            <a href="room-rates.php" class="btn btn-secondary">Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Room Rates List -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Seasonal Room Rates</h4>
                                <p class="text-muted mb-0">Manage seasonal pricing for different room types</p>
                            </div>
                            <div class="card-body">
                                <?php if ($room_rates_result->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Room Type</th>
                                                    <th>Season</th>
                                                    <th>Date Range</th>
                                                    <th>Rate/Night</th>
                                                    <th>Extra Charges</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($rate = $room_rates_result->fetch_assoc()): 
                                                    $is_current = (date('Y-m-d') >= $rate['start_date'] && date('Y-m-d') <= $rate['end_date']);
                                                ?>
                                                    <tr class="<?php echo !$rate['is_active'] ? 'table-secondary' : ($is_current ? 'table-success' : ''); ?>">
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($rate['room_type_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">Base: ₹<?php echo number_format($rate['default_rate']); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-primary season-badge">
                                                                <?php echo htmlspecialchars($rate['season_name']); ?>
                                                            </span>
                                                            <?php if ($is_current): ?>
                                                                <span class="badge bg-success season-badge">Current</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M j, Y', strtotime($rate['start_date'])); ?><br>
                                                            to <?php echo date('M j, Y', strtotime($rate['end_date'])); ?>
                                                        </td>
                                                        <td>
                                                            <strong>₹<?php echo number_format($rate['rate_per_night']); ?></strong>
                                                            <?php 
                                                            $difference = $rate['rate_per_night'] - $rate['default_rate'];
                                                            if ($difference != 0): 
                                                            ?>
                                                                <br>
                                                                <small class="<?php echo $difference > 0 ? 'text-success' : 'text-danger'; ?>">
                                                                    <?php echo $difference > 0 ? '+' : ''; ?>
                                                                    ₹<?php echo number_format(abs($difference)); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            Adult: ₹<?php echo number_format($rate['extra_adult_charge']); ?><br>
                                                            Child: ₹<?php echo number_format($rate['extra_child_charge']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($rate['is_active']): ?>
                                                                <span class="badge bg-success">Active</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Inactive</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="room-rates.php?edit=<?php echo $rate['id']; ?>" 
                                                                   class="btn btn-outline-primary">Edit</a>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="rate_id" value="<?php echo $rate['id']; ?>">
                                                                    <button type="submit" name="delete_rate" 
                                                                            class="btn btn-outline-danger" 
                                                                            onclick="return confirm('Are you sure you want to delete this rate?')">
                                                                        Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-money-bill-wave fa-3x mb-3"></i>
                                            <h5>No Room Rates Found</h5>
                                            <p>Start by adding your first seasonal room rate.</p>
                                            <a href="#roomRateForm" class="btn btn-primary mt-2">Add First Rate</a>
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

    <script>
    $(document).ready(function() {
        // Rate comparison functionality
        function updateRateComparison() {
            const roomTypeSelect = $('#room_type_id');
            const rateInput = $('#rate_per_night');
            const comparisonDiv = $('#rateComparison');
            
            const selectedOption = roomTypeSelect.find('option:selected');
            const baseRate = selectedOption.data('base-rate');
            const currentRate = parseFloat(rateInput.val()) || 0;
            
            if (baseRate && currentRate > 0) {
                const difference = currentRate - baseRate;
                const percentage = ((difference / baseRate) * 100).toFixed(1);
                
                let comparisonText = '';
                if (difference > 0) {
                    comparisonText = `<span class="rate-difference positive">
                        +₹${Math.abs(difference).toLocaleString()} (${percentage}% above base rate)
                    </span>`;
                } else if (difference < 0) {
                    comparisonText = `<span class="rate-difference negative">
                        -₹${Math.abs(difference).toLocaleString()} (${Math.abs(percentage)}% below base rate)
                    </span>`;
                } else {
                    comparisonText = `<span class="rate-difference">Same as base rate</span>`;
                }
                
                comparisonDiv.html(`Base rate: ₹${baseRate.toLocaleString()} | ${comparisonText}`);
            } else {
                comparisonDiv.html('');
            }
        }

        // Event listeners
        $('#room_type_id, #rate_per_night').on('change keyup', updateRateComparison);

        // Form validation
        $('#roomRateForm').on('submit', function(e) {
            const startDate = new Date($('#start_date').val());
            const endDate = new Date($('#end_date').val());
            
            if (startDate >= endDate) {
                e.preventDefault();
                alert('End date must be after start date!');
                return false;
            }
            
            const rate = parseFloat($('#rate_per_night').val());
            if (rate <= 0) {
                e.preventDefault();
                alert('Rate per night must be greater than 0!');
                return false;
            }
        });

        // Initialize date inputs with min date as today
        const today = new Date().toISOString().split('T')[0];
        $('#start_date').attr('min', today);
        $('#end_date').attr('min', today);

        // Update end date min when start date changes
        $('#start_date').on('change', function() {
            $('#end_date').attr('min', $(this).val());
        });

        // Initialize rate comparison
        updateRateComparison();

        // Smooth scroll to form when "Add First Rate" is clicked
        $('a[href="#roomRateForm"]').on('click', function(e) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('#roomRateForm').offset().top - 100
            }, 500);
        });
    });
    </script>
</body>
</html>
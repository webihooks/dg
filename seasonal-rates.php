<?php
// seasonal-rates.php
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
$user_sql = "SELECT name, role, is_trial, trial_end FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $role, $is_trial, $trial_end);
$user_stmt->fetch();
$user_stmt->close();

// Check if seasonal_rates table exists, if not create it
$check_table_sql = "SHOW TABLES LIKE 'seasonal_rates_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    // Create seasonal_rates table
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS `seasonal_rates_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `season_name` VARCHAR(100) NOT NULL,
            `room_type_id` INT(11) NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `rate_multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
            `fixed_rate` DECIMAL(10,2) DEFAULT NULL,
            `min_nights` INT(3) DEFAULT 1,
            `description` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `room_type_id` (`room_type_id`),
            KEY `start_date` (`start_date`),
            KEY `end_date` (`end_date`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($create_table_sql) !== TRUE) {
        $error_message = "Error creating seasonal rates table: " . $conn->error;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_seasonal_rate'])) {
        $season_name = trim($_POST['season_name']);
        $room_type_id = intval($_POST['room_type_id']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $rate_type = $_POST['rate_type'];
        $rate_multiplier = ($rate_type === 'multiplier') ? floatval($_POST['rate_multiplier']) : 1.00;
        $fixed_rate = ($rate_type === 'fixed') ? floatval($_POST['fixed_rate']) : NULL;
        $min_nights = intval($_POST['min_nights']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validate dates
        if ($start_date >= $end_date) {
            $error_message = "End date must be after start date";
        } else {
            // Check for overlapping seasonal rates for the same room type
            $check_overlap_sql = "
                SELECT id FROM seasonal_rates_$user_id 
                WHERE room_type_id = ? 
                AND is_active = 1 
                AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (? BETWEEN start_date AND end_date))
            ";
            $stmt = $conn->prepare($check_overlap_sql);
            $stmt->bind_param("isssss", $room_type_id, $start_date, $end_date, $start_date, $end_date, $start_date);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error_message = "Seasonal rate overlaps with existing rate for this room type";
            } else {
                $insert_sql = "
                    INSERT INTO seasonal_rates_$user_id 
                    (season_name, room_type_id, start_date, end_date, rate_multiplier, fixed_rate, min_nights, description, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("sissdisii", $season_name, $room_type_id, $start_date, $end_date, $rate_multiplier, $fixed_rate, $min_nights, $description, $is_active);
                
                if ($stmt->execute()) {
                    $success_message = "Seasonal rate added successfully!";
                } else {
                    $error_message = "Error adding seasonal rate: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
    
    // Handle delete action
    if (isset($_POST['delete_rate'])) {
        $rate_id = intval($_POST['rate_id']);
        $delete_sql = "DELETE FROM seasonal_rates_$user_id WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $rate_id);
        
        if ($stmt->execute()) {
            $success_message = "Seasonal rate deleted successfully!";
        } else {
            $error_message = "Error deleting seasonal rate: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Handle toggle active status
    if (isset($_POST['toggle_active'])) {
        $rate_id = intval($_POST['rate_id']);
        $is_active = intval($_POST['is_active']);
        $update_sql = "UPDATE seasonal_rates_$user_id SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $is_active, $rate_id);
        
        if ($stmt->execute()) {
            $success_message = "Seasonal rate updated successfully!";
        } else {
            $error_message = "Error updating seasonal rate: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get room types for dropdown
$room_types = [];
$room_types_sql = "SELECT id, name FROM room_types_$user_id WHERE is_active = 1 ORDER BY name";
$room_types_result = $conn->query($room_types_sql);
if ($room_types_result) {
    while ($row = $room_types_result->fetch_assoc()) {
        $room_types[] = $row;
    }
}

// Get seasonal rates with room type information
$seasonal_rates = [];
$rates_sql = "
    SELECT sr.*, rt.name as room_type_name 
    FROM seasonal_rates_$user_id sr 
    LEFT JOIN room_types_$user_id rt ON sr.room_type_id = rt.id 
    ORDER BY sr.start_date DESC, rt.name
";
$rates_result = $conn->query($rates_sql);
if ($rates_result) {
    while ($row = $rates_result->fetch_assoc()) {
        $seasonal_rates[] = $row;
    }
}

// Get current active seasonal rates
$current_seasonal_rates = [];
$current_date = date('Y-m-d');
$current_rates_sql = "
    SELECT sr.*, rt.name as room_type_name, rt.base_rate
    FROM seasonal_rates_$user_id sr 
    LEFT JOIN room_types_$user_id rt ON sr.room_type_id = rt.id 
    WHERE sr.is_active = 1 
    AND ? BETWEEN sr.start_date AND sr.end_date
    ORDER BY rt.name
";
$stmt = $conn->prepare($current_rates_sql);
$stmt->bind_param("s", $current_date);
$stmt->execute();
$current_result = $stmt->get_result();
while ($row = $current_result->fetch_assoc()) {
    $current_seasonal_rates[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Seasonal Rates Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .seasonal-rate-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        .seasonal-rate-card.inactive {
            border-left-color: #6c757d;
            opacity: 0.7;
        }
        .seasonal-rate-card.current {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .rate-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        .season-dates {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .rate-display {
            font-size: 1.1rem;
            font-weight: bold;
        }
        .multiplier-rate {
            color: #28a745;
        }
        .fixed-rate {
            color: #007bff;
        }
        .current-season-indicator {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            if (($is_trial && strtotime($trial_end) > time()) || !$is_trial) {
                include 'room_management_menu.php';
            } else {
                include 'unsubscriber_menu.php';
            }
        }
        ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Notifications -->
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Current Seasonal Rates Indicator -->
                        <?php if (!empty($current_seasonal_rates)): ?>
                            <div class="current-season-indicator">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h4 class="mb-1">🎯 Current Seasonal Rates Active</h4>
                                        <p class="mb-0">You have <?php echo count($current_seasonal_rates); ?> seasonal rate(s) active today</p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <span class="badge bg-light text-dark fs-6"><?php echo date('M j, Y'); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Seasonal Rates Management</h4>
                                <p class="card-title-desc">Manage seasonal pricing for different room types</p>
                            </div>
                            <div class="card-body">
                                <!-- Add Seasonal Rate Form -->
                                <div class="row mb-5">
                                    <div class="col-lg-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">Add New Seasonal Rate</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" id="seasonalRateForm">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Season Name *</label>
                                                                <input type="text" class="form-control" name="season_name" required 
                                                                       placeholder="e.g., Summer Peak, Winter Holiday">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Room Type *</label>
                                                                <select class="form-select" name="room_type_id" required>
                                                                    <option value="">Select Room Type</option>
                                                                    <?php foreach ($room_types as $room_type): ?>
                                                                        <option value="<?php echo $room_type['id']; ?>">
                                                                            <?php echo htmlspecialchars($room_type['name']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <?php if (empty($room_types)): ?>
                                                                    <small class="text-danger">No room types found. <a href="room-types.php">Create room types first</a>.</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Start Date *</label>
                                                                <input type="date" class="form-control" name="start_date" required 
                                                                       min="<?php echo date('Y-m-d'); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">End Date *</label>
                                                                <input type="date" class="form-control" name="end_date" required 
                                                                       min="<?php echo date('Y-m-d'); ?>">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Rate Type *</label>
                                                                <select class="form-select" name="rate_type" id="rateType" required>
                                                                    <option value="multiplier">Percentage Multiplier</option>
                                                                    <option value="fixed">Fixed Rate</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Minimum Nights</label>
                                                                <input type="number" class="form-control" name="min_nights" 
                                                                       value="1" min="1" max="30">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3" id="multiplierField">
                                                                <label class="form-label">Rate Multiplier *</label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control" name="rate_multiplier" 
                                                                           step="0.01" min="0.5" max="5.0" value="1.00">
                                                                    <span class="input-group-text">x Base Rate</span>
                                                                </div>
                                                                <small class="form-text text-muted">1.00 = 100% (normal rate), 1.50 = 150% (50% increase)</small>
                                                            </div>
                                                            <div class="mb-3" id="fixedRateField" style="display: none;">
                                                                <label class="form-label">Fixed Rate *</label>
                                                                <div class="input-group">
                                                                    <span class="input-group-text">₹</span>
                                                                    <input type="number" class="form-control" name="fixed_rate" 
                                                                           step="0.01" min="0" value="0.00">
                                                                </div>
                                                                <small class="form-text text-muted">Fixed price per night (overrides base rate)</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="description" rows="3" 
                                                                          placeholder="Optional description for this seasonal rate"></textarea>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3 form-check">
                                                        <input type="checkbox" class="form-check-input" name="is_active" id="isActive" checked>
                                                        <label class="form-check-label" for="isActive">Active</label>
                                                    </div>

                                                    <button type="submit" name="add_seasonal_rate" class="btn btn-primary">
                                                        <i class="fas fa-plus-circle me-1"></i> Add Seasonal Rate
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">📊 Seasonal Rate Guide</h5>
                                            </div>
                                            <div class="card-body">
                                                <h6>Rate Multiplier Examples:</h6>
                                                <ul class="list-unstyled">
                                                    <li><strong>0.80x</strong> = 20% discount (off-season)</li>
                                                    <li><strong>1.00x</strong> = Standard rate</li>
                                                    <li><strong>1.20x</strong> = 20% premium (peak season)</li>
                                                    <li><strong>1.50x</strong> = 50% premium (holiday season)</li>
                                                </ul>
                                                
                                                <h6 class="mt-3">Best Practices:</h6>
                                                <ul>
                                                    <li>Set seasonal rates for holidays and festivals</li>
                                                    <li>Use multipliers for percentage-based changes</li>
                                                    <li>Use fixed rates for special packages</li>
                                                    <li>Plan seasonal rates 3-6 months in advance</li>
                                                    <li>Set minimum nights for peak seasons</li>
                                                </ul>
                                                
                                                <div class="alert alert-info mt-3">
                                                    <small>
                                                        <strong>💡 Tip:</strong> Seasonal rates automatically apply to new bookings 
                                                        during the specified date range. Existing bookings are not affected.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Seasonal Rates List -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">Manage Seasonal Rates</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($seasonal_rates)): ?>
                                                    <div class="text-center py-4">
                                                        <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                                        <h5>No Seasonal Rates Found</h5>
                                                        <p class="text-muted">Get started by adding your first seasonal rate above.</p>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th>Season Name</th>
                                                                    <th>Room Type</th>
                                                                    <th>Dates</th>
                                                                    <th>Rate</th>
                                                                    <th>Min Nights</th>
                                                                    <th>Status</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($seasonal_rates as $rate): 
                                                                    $is_current = ($current_date >= $rate['start_date'] && $current_date <= $rate['end_date']);
                                                                    $is_past = ($current_date > $rate['end_date']);
                                                                    $is_future = ($current_date < $rate['start_date']);
                                                                ?>
                                                                    <tr class="<?php echo $is_current ? 'table-warning' : ''; ?>">
                                                                        <td>
                                                                            <strong><?php echo htmlspecialchars($rate['season_name']); ?></strong>
                                                                            <?php if ($is_current): ?>
                                                                                <span class="badge bg-warning ms-1">Current</span>
                                                                            <?php elseif ($is_past): ?>
                                                                                <span class="badge bg-secondary ms-1">Past</span>
                                                                            <?php elseif ($is_future): ?>
                                                                                <span class="badge bg-info ms-1">Upcoming</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td><?php echo htmlspecialchars($rate['room_type_name']); ?></td>
                                                                        <td>
                                                                            <small class="season-dates">
                                                                                <?php echo date('M j, Y', strtotime($rate['start_date'])); ?> - 
                                                                                <?php echo date('M j, Y', strtotime($rate['end_date'])); ?>
                                                                            </small>
                                                                        </td>
                                                                        <td>
                                                                            <?php if (!empty($rate['fixed_rate'])): ?>
                                                                                <span class="rate-display fixed-rate">₹<?php echo number_format($rate['fixed_rate']); ?></span>
                                                                                <br><small class="text-muted">Fixed Rate</small>
                                                                            <?php else: ?>
                                                                                <span class="rate-display multiplier-rate"><?php echo $rate['rate_multiplier']; ?>x</span>
                                                                                <br><small class="text-muted">Multiplier</small>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <span class="badge bg-light text-dark"><?php echo $rate['min_nights']; ?> nights</span>
                                                                        </td>
                                                                        <td>
                                                                            <form method="POST" class="d-inline">
                                                                                <input type="hidden" name="rate_id" value="<?php echo $rate['id']; ?>">
                                                                                <input type="hidden" name="is_active" value="<?php echo $rate['is_active'] ? '0' : '1'; ?>">
                                                                                <button type="submit" name="toggle_active" 
                                                                                        class="btn btn-sm <?php echo $rate['is_active'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                                                    <?php echo $rate['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                                </button>
                                                                            </form>
                                                                        </td>
                                                                        <td>
                                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this seasonal rate?');">
                                                                                <input type="hidden" name="rate_id" value="<?php echo $rate['id']; ?>">
                                                                                <button type="submit" name="delete_rate" class="btn btn-sm btn-danger">
                                                                                    <i class="fas fa-trash"></i>
                                                                                </button>
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
        // Toggle between multiplier and fixed rate fields
        $('#rateType').change(function() {
            if ($(this).val() === 'multiplier') {
                $('#multiplierField').show();
                $('#fixedRateField').hide();
                $('[name="rate_multiplier"]').prop('required', true);
                $('[name="fixed_rate"]').prop('required', false);
            } else {
                $('#multiplierField').hide();
                $('#fixedRateField').show();
                $('[name="rate_multiplier"]').prop('required', false);
                $('[name="fixed_rate"]').prop('required', true);
            }
        });

        // Form validation
        $('#seasonalRateForm').validate({
            rules: {
                season_name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100
                },
                room_type_id: {
                    required: true
                },
                start_date: {
                    required: true,
                    date: true
                },
                end_date: {
                    required: true,
                    date: true,
                    greaterThanStart: true
                },
                rate_multiplier: {
                    required: function() {
                        return $('#rateType').val() === 'multiplier';
                    },
                    number: true,
                    min: 0.5,
                    max: 5.0
                },
                fixed_rate: {
                    required: function() {
                        return $('#rateType').val() === 'fixed';
                    },
                    number: true,
                    min: 0
                }
            },
            messages: {
                season_name: {
                    required: "Please enter a season name",
                    minlength: "Season name must be at least 2 characters long"
                },
                room_type_id: {
                    required: "Please select a room type"
                },
                start_date: {
                    required: "Please select a start date"
                },
                end_date: {
                    required: "Please select an end date",
                    greaterThanStart: "End date must be after start date"
                },
                rate_multiplier: {
                    required: "Please enter a rate multiplier",
                    number: "Please enter a valid number",
                    min: "Multiplier must be at least 0.5 (50% of base rate)",
                    max: "Multiplier cannot exceed 5.0 (500% of base rate)"
                },
                fixed_rate: {
                    required: "Please enter a fixed rate",
                    number: "Please enter a valid amount",
                    min: "Rate cannot be negative"
                }
            },
            errorElement: "div",
            errorPlacement: function(error, element) {
                error.addClass("invalid-feedback");
                error.insertAfter(element);
            },
            highlight: function(element, errorClass, validClass) {
                $(element).addClass("is-invalid").removeClass("is-valid");
            },
            unhighlight: function(element, errorClass, validClass) {
                $(element).addClass("is-valid").removeClass("is-invalid");
            }
        });

        // Custom validation for end date
        $.validator.addMethod("greaterThanStart", function(value, element) {
            var startDate = $('[name="start_date"]').val();
            return this.optional(element) || (value > startDate);
        }, "End date must be after start date");

        // Set minimum end date based on start date
        $('[name="start_date"]').change(function() {
            var startDate = $(this).val();
            $('[name="end_date"]').attr('min', startDate);
            
            // If end date is before new start date, clear it
            if ($('[name="end_date"]').val() && $('[name="end_date"]').val() < startDate) {
                $('[name="end_date"]').val('');
            }
        });

        // Initialize date restrictions
        var today = new Date().toISOString().split('T')[0];
        $('[name="start_date"]').attr('min', today);
        $('[name="end_date"]').attr('min', today);
    });

    // Android session protection
    function setupSeasonalRatesSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('🏨 Seasonal Rates: Setting up Android session protection');
        
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 1000);
        
        window.addEventListener('pageshow', function(event) {
            if (event.persisted && WTN.forceUpdateCookies) {
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                }, 500);
            }
        });
        
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
                setTimeout(() => {
                    WTN.forceUpdateCookies();
                }, 300);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupSeasonalRatesSessionProtection();
    });
    </script>
</body>
</html>
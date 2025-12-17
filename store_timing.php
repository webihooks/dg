<?php
// Start the session
session_start();
date_default_timezone_set('Asia/Kolkata');
// Include the database connection file
require 'db_connection.php';


// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Days of the week
$days_of_week = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete existing timings for this user
    $delete_sql = "DELETE FROM store_timing WHERE user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Process each day's timing
    $errors = [];
    $insert_sql = "INSERT INTO store_timing (user_id, day_of_week, open_time, close_time, is_closed) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    
    foreach ($days_of_week as $day_index => $day_name) {
        $is_closed = isset($_POST['closed'][$day_index]) ? 1 : 0;
        $open_time = !empty($_POST['open_time'][$day_index]) ? $_POST['open_time'][$day_index] : null;
        $close_time = !empty($_POST['close_time'][$day_index]) ? $_POST['close_time'][$day_index] : null;
        
        // Validate time inputs if the day is not closed
        if (!$is_closed) {
            if (empty($open_time) || empty($close_time)) {
                $errors[] = "Please set both open and close times for $day_name or mark it as closed.";
                continue;
            }
            
            if (strtotime($close_time) <= strtotime($open_time)) {
                $errors[] = "Close time must be after open time for $day_name.";
                continue;
            }
        }
        
        $insert_stmt->bind_param("iissi", $user_id, $day_index, $open_time, $close_time, $is_closed);
        $insert_stmt->execute();
    }
    
    $insert_stmt->close();
    
    if (empty($errors)) {
        $success_message = "Store timings updated successfully!";
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch existing store timings
$timings = [];
$sql = "SELECT day_of_week, open_time, close_time, is_closed FROM store_timing WHERE user_id = ? ORDER BY day_of_week";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $timings[$row['day_of_week']] = $row;
}

$stmt->close();
$conn->close();

// If no timings exist, set default to 24/7
if (empty($timings)) {
    foreach ($days_of_week as $day_index => $day_name) {
        $timings[$day_index] = [
            'is_closed' => 0,
            'open_time' => '00:00',
            'close_time' => '23:59'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Store Timing Management</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .timing-row {
            padding: 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        .timing-row:last-child {
            border-bottom: none;
        }
        .day-label {
            min-width: 100px;
            font-weight: 500;
        }
        .time-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .closed-checkbox {
            margin-left: 20px;
        }
        @media (max-width: 768px) {
            .timing-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .day-label {
                margin-bottom: 10px;
            }
            .time-inputs {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Store Timing Management</h4>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Set your store opening hours. By default, the store is open 24/7.</p>
                                
                                <form method="POST" action="store_timing.php">
                                    <?php foreach ($days_of_week as $day_index => $day_name): ?>
                                        <div class="timing-row">
                                            <div class="day-label"><?php echo $day_name; ?></div>
                                            <div class="time-inputs">
                                                <div>
                                                    <label>Open:</label>
                                                    <input type="time" name="open_time[<?php echo $day_index; ?>]" 
                                                           value="<?php echo $timings[$day_index]['open_time'] ?? ''; ?>" 
                                                           class="form-control" 
                                                           <?php if ($timings[$day_index]['is_closed'] ?? 0) echo 'disabled'; ?>>
                                                </div>
                                                <div>
                                                    <label>Close:</label>
                                                    <input type="time" name="close_time[<?php echo $day_index; ?>]" 
                                                           value="<?php echo $timings[$day_index]['close_time'] ?? ''; ?>" 
                                                           class="form-control" 
                                                           <?php if ($timings[$day_index]['is_closed'] ?? 0) echo 'disabled'; ?>>
                                                </div>
                                            </div>
                                            <div class="closed-checkbox">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input day-closed" 
                                                           name="closed[<?php echo $day_index; ?>]" 
                                                           value="1" 
                                                           <?php if ($timings[$day_index]['is_closed'] ?? 0) echo 'checked'; ?>>
                                                    <label class="form-check-label">Closed</label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">Save Store Timings</button>
                                        <button type="button" id="set24_7" class="btn btn-secondary">Set to 24/7</button>
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

    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        // Enable/disable time inputs based on closed checkbox
        $('.day-closed').change(function() {
            const timingRow = $(this).closest('.timing-row');
            const timeInputs = timingRow.find('input[type="time"]');
            
            if ($(this).is(':checked')) {
                timeInputs.prop('disabled', true);
            } else {
                timeInputs.prop('disabled', false);
            }
        });
        
        // Set all days to 24/7
        $('#set24_7').click(function() {
            $('input[type="time"][name*="open_time"]').val('00:00').prop('disabled', false);
            $('input[type="time"][name*="close_time"]').val('23:59').prop('disabled', false);
            $('.day-closed').prop('checked', false);
        });
    });
    </script>
</body>
</html>
<?php
// Start the session
session_start();
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
$is_edit_mode = false;
$tag_data = null;

// Fetch user name and role
$sql = "SELECT name, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $user_role);
$stmt->fetch();
$stmt->close();

// Handle form submission for add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag_id = isset($_POST['tag_id']) ? $_POST['tag_id'] : null;
    $tag = trim($_POST['tag']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Timing data
    $time1_start = !empty($_POST['time1_start']) ? $_POST['time1_start'] : null;
    $time1_end = !empty($_POST['time1_end']) ? $_POST['time1_end'] : null;
    $time2_start = !empty($_POST['time2_start']) ? $_POST['time2_start'] : null;
    $time2_end = !empty($_POST['time2_end']) ? $_POST['time2_end'] : null;
    
    // Validate times - ensure end time is after start time
    if ($time1_start && $time1_end && strtotime($time1_end) <= strtotime($time1_start)) {
        $error_message = "Time Slot 1: End time must be after start time";
    } elseif ($time2_start && $time2_end && strtotime($time2_end) <= strtotime($time2_start)) {
        $error_message = "Time Slot 2: End time must be after start time";
    } elseif (empty($tag)) {
        $error_message = "Tag is required.";
    } else {
        if ($tag_id) {
            // Update existing tag
            $sql = "UPDATE tags SET tag = ?, is_active = ?, time1_start = ?, time1_end = ?, time2_start = ?, time2_end = ? WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sissssii", $tag, $is_active, $time1_start, $time1_end, $time2_start, $time2_end, $tag_id, $user_id);
        } else {
            // Add new tag
            $sql = "INSERT INTO tags (user_id, tag, position, is_active, time1_start, time1_end, time2_start, time2_end) VALUES (?, ?, (SELECT IFNULL(MAX(position), 0) + 1 FROM (SELECT * FROM tags) AS temp WHERE user_id = ?), ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isississ", $user_id, $tag, $user_id, $is_active, $time1_start, $time1_end, $time2_start, $time2_end);
        }

        if ($stmt->execute()) {
            $success_message = $tag_id ? "Tag updated successfully!" : "Tag added successfully!";
        } else {
            $error_message = "Error saving tag: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle toggle active status request
if (isset($_GET['toggle'])) {
    $tag_id = $_GET['toggle'];
    
    // Get current status
    $sql = "SELECT is_active FROM tags WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tag_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($current_status);
    $stmt->fetch();
    $stmt->close();
    
    // Toggle status
    $new_status = $current_status ? 0 : 1;
    
    $sql = "UPDATE tags SET is_active = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $new_status, $tag_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Tag status updated successfully!";
    } else {
        $error_message = "Error updating tag status: " . $conn->error;
    }
    $stmt->close();
}

// Handle edit request
if (isset($_GET['edit'])) {
    $tag_id = $_GET['edit'];
    $sql = "SELECT * FROM tags WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tag_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tag_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($tag_data) {
        $is_edit_mode = true;
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $tag_id = $_GET['delete'];
    
    // Delete the tag
    $sql = "DELETE FROM tags WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tag_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Tag deleted successfully!";
        
        // Reorder remaining tags
        $sql_select = "SELECT id FROM tags WHERE user_id = ? ORDER BY position ASC, id ASC";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $user_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        $remaining_tags = $result->fetch_all(MYSQLI_ASSOC);
        $stmt_select->close();
        
        // Update positions sequentially
        $sql_update = "UPDATE tags SET position = ? WHERE id = ? AND user_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        
        $position = 1;
        foreach ($remaining_tags as $tag) {
            $stmt_update->bind_param("iii", $position, $tag['id'], $user_id);
            $stmt_update->execute();
            $position++;
        }
        $stmt_update->close();
        
    } else {
        $error_message = "Error deleting tag: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all tags for the current user
$sql = "SELECT * FROM tags WHERE user_id = ? ORDER BY position ASC, id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tags = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Function to format time for display
function formatTimeForDisplay($time) {
    if (!$time || $time == '00:00:00') return 'Not set';
    return date('g:i A', strtotime($time));
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Tags Management</title>
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
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .drag-handle{cursor:move;touch-action:none;padding:8px 12px;display:inline-block}.dragging{opacity:.8;box-shadow:0 0 10px rgba(0,0,0,.2)}.dragging-active{overflow:hidden!important}.ui-sortable-helper{transform:scale(1.02);box-shadow:0 5px 15px rgba(0,0,0,.1);background-color:#fff}@media (pointer:coarse){.drag-handle{width:40px;height:40px;display:flex;align-items:center;justify-content:center}table tbody tr{padding:8px 0}}.table tbody tr{cursor:move;transition:.2s}@media screen and (max-width:768px){.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}table{width:100%;font-size:13px}td,th{padding:6px 4px}.drag-handle{width:30px;height:30px;padding:5px}.drag-handle i{font-size:14px}.btn{padding:4px 8px;font-size:12px;margin:2px 0;display:inline-block;min-width:60px}.card-body{padding:10px}.form-control{font-size:14px;padding:6px 8px}.form-label{font-size:14px;margin-bottom:5px}.alert{padding:8px 12px;font-size:13px}.ui-sortable-helper{transform:scale(1.01);box-shadow:0 2px 8px rgba(0,0,0,.1)}table tbody tr{-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}td:first-child,td:nth-child(5),th:first-child,th:nth-child(5){display:none}td:nth-child(2),th:nth-child(2){min-width:120px}td:nth-child(3),th:nth-child(3){min-width:180px;font-size:13px}td:nth-child(4),th:nth-child(4){min-width:80px}td:nth-child(6),th:nth-child(6){min-width:150px;white-space:nowrap}.status-toggle{font-size:11px;padding:3px 6px}}.status-toggle{min-width:70px}
        @media screen and (max-width: 768px) {
            .table td:nth-child(3) {
                min-width: 150px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php
        // Include appropriate menu based on user role
        if ($user_role === 'admin') {
            include 'admin_menu.php';
        } elseif ($user_role === 'vegetable_seller') {
            include 'vegetable_seller_menu.php';
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Tags</h4>
                            </div>
                            <div class="card-body">
                                <h4 class="card-title"><?php echo $is_edit_mode ? 'Edit Tag' : 'Add New Tag'; ?></h4>
                                <form id="tagForm" method="POST" action="tags.php">
                                    <input type="hidden" name="tag_id" value="<?php echo $is_edit_mode ? $tag_data['id'] : ''; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="tag" class="form-label">Tag Name *</label>
                                        <input type="text" class="form-control" id="tag" name="tag" required 
                                            value="<?php echo $is_edit_mode ? htmlspecialchars($tag_data['tag']) : ''; ?>">
                                        <small class="text-muted">Enter a descriptive tag to categorize your content</small>
                                    </div>
                                    
                                    <div class="row mb-1">
                                        <div class="col-md-12">
                                            <h5>Serve Time Slots (Optional)</h5>
                                            <p class="text-muted">You can set up to two time slots when this tag/category is available</p>
                                        </div>
                                        
                                        <!-- Time Slot 1 -->
                                        <div class="col-md-6 mb-1">
                                            <div class="card">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0">Time Slot 1</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <label for="time1_start" class="form-label">Start Time</label>
                                                            <input type="time" class="form-control" id="time1_start" name="time1_start" 
                                                                value="<?php echo $is_edit_mode && $tag_data['time1_start'] ? date('H:i', strtotime($tag_data['time1_start'])) : ''; ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="time1_end" class="form-label">End Time</label>
                                                            <input type="time" class="form-control" id="time1_end" name="time1_end" 
                                                                value="<?php echo $is_edit_mode && $tag_data['time1_end'] ? date('H:i', strtotime($tag_data['time1_end'])) : ''; ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Time Slot 2 -->
                                        <div class="col-md-6 mb-1">
                                            <div class="card">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0">Time Slot 2</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <label for="time2_start" class="form-label">Start Time</label>
                                                            <input type="time" class="form-control" id="time2_start" name="time2_start" 
                                                                value="<?php echo $is_edit_mode && $tag_data['time2_start'] ? date('H:i', strtotime($tag_data['time2_start'])) : ''; ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="time2_end" class="form-label">End Time</label>
                                                            <input type="time" class="form-control" id="time2_end" name="time2_end" 
                                                                value="<?php echo $is_edit_mode && $tag_data['time2_end'] ? date('H:i', strtotime($tag_data['time2_end'])) : ''; ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                            <?php echo ($is_edit_mode && $tag_data['is_active'] == 1) || !$is_edit_mode ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Active</label>
                                        <small class="text-muted d-block">Inactive tags won't be available for selection</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $is_edit_mode ? 'Update' : 'Save'; ?> Tag
                                    </button>
                                    <?php if ($is_edit_mode): ?>
                                        <a href="tags.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="card-title">Your Tags</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($tags)): ?>
                                    <p>No tags found. Add your first tag above.</p>
                                <?php else: ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> Drag the <i class="fas fa-arrows-alt"></i> icon to reorder tags. Order saves automatically.
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tag</th>
                                                    <th>Serve Time</th>
                                                    <th>Status</th>
                                                    <th>Position</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tags as $tag): 
                                                    // Format time slots for display
                                                    $time_slots = [];
                                                    if ($tag['time1_start'] && $tag['time1_end']) {
                                                        $time_slots[] = formatTimeForDisplay($tag['time1_start']) . ' - ' . formatTimeForDisplay($tag['time1_end']);
                                                    }
                                                    if ($tag['time2_start'] && $tag['time2_end']) {
                                                        $time_slots[] = formatTimeForDisplay($tag['time2_start']) . ' - ' . formatTimeForDisplay($tag['time2_end']);
                                                    }
                                                    $time_display = $time_slots ? implode('<br>', $time_slots) : 'All day';
                                                ?>
                                                    <tr>
                                                        <td><?php echo $tag['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($tag['tag']); ?></td>
                                                        <td><?php echo $time_display; ?></td>
                                                        <td>
                                                            <a href="tags.php?toggle=<?php echo $tag['id']; ?>" 
                                                               class="btn btn-sm <?php echo $tag['is_active'] ? 'btn-success' : 'btn-secondary'; ?> status-toggle">
                                                                <?php echo $tag['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo $tag['position']; ?></td>
                                                        <td>
                                                            <span class="drag-handle"><i class="fas fa-arrows-alt"></i></span>
                                                            <a href="tags.php?edit=<?php echo $tag['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                            <a href="tags.php?delete=<?php echo $tag['id']; ?>" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this tag?')">Delete</a>
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

    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        // Make table rows sortable with touch support
        $("table tbody").sortable({
            axis: 'y',
            cursor: 'move',
            opacity: 0.7,
            containment: 'parent',
            tolerance: 'pointer',
            delay: 150,
            distance: 5,
            handle: '.drag-handle',
            
            // Touch device support
            touchStart: function(event, ui) {
                $(ui.item).addClass('dragging');
            },
            touchStop: function(event, ui) {
                $(ui.item).removeClass('dragging');
            },
            
            helper: function(e, ui) {
                ui.children().each(function() {
                    $(this).width($(this).width());
                });
                return ui;
            },
            start: function(e, ui) {
                ui.placeholder.height(ui.helper.height());
                $('body').addClass('dragging-active');
            },
            stop: function() {
                $('body').removeClass('dragging-active');
            },
            update: function(event, ui) {
                var tagsOrder = [];
                $("table tbody tr").each(function(index) {
                    tagsOrder.push({
                        id: $(this).find('td:first').text(),
                        position: index + 1
                    });
                });
                
                $.ajax({
                    url: 'update_positions.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ order: tagsOrder }),
                    success: function(response) {
                        if (response.success) {
                            $("table tbody tr").each(function(index) {
                                $(this).find('td:nth-child(5)').text(index + 1);
                            });
                            
                            var $msg = $('<div class="alert alert-success">Tag order saved!</div>');
                            $('.page-content .container .row .col-xl-9').prepend($msg);
                            setTimeout(function() {
                                $msg.fadeOut(500, function() { $(this).remove(); });
                            }, 2000);
                        }
                    },
                    error: function(xhr) {
                        alert('Error updating positions: ' + xhr.responseText);
                    }
                });
            }
        }).disableSelection();
        
        // Form validation
        $("#tagForm").validate({
            rules: {
                tag: {
                    required: true,
                    minlength: 2,
                    maxlength: 150
                },
                time1_start: {
                    timeValidation: true
                },
                time1_end: {
                    timeValidation: true
                },
                time2_start: {
                    timeValidation: true
                },
                time2_end: {
                    timeValidation: true
                }
            },
            messages: {
                tag: {
                    required: "Please enter a tag",
                    minlength: "Tag must be at least 2 characters long",
                    maxlength: "Tag cannot be longer than 150 characters"
                }
            }
        });
        
        // Custom validation for time slots
        $.validator.addMethod("timeValidation", function(value, element) {
            if (!value) return true; // Allow empty
            return /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(value);
        }, "Please enter a valid time (HH:MM format)");
        
        // Time validation before form submission
        $("#tagForm").on('submit', function(e) {
            var time1Start = $('#time1_start').val();
            var time1End = $('#time1_end').val();
            var time2Start = $('#time2_start').val();
            var time2End = $('#time2_end').val();
            
            // Validate time slot 1 if both are filled
            if (time1Start && time1End) {
                if (time1End <= time1Start) {
                    alert('Time Slot 1: End time must be after start time');
                    e.preventDefault();
                    return false;
                }
            }
            
            // Validate time slot 2 if both are filled
            if (time2Start && time2End) {
                if (time2End <= time2Start) {
                    alert('Time Slot 2: End time must be after start time');
                    e.preventDefault();
                    return false;
                }
            }
            
            // Validate that at least one of start/end is filled, not just one
            if ((time1Start && !time1End) || (!time1Start && time1End)) {
                alert('Time Slot 1: Please provide both start and end times, or leave both empty');
                e.preventDefault();
                return false;
            }
            
            if ((time2Start && !time2End) || (!time2Start && time2End)) {
                alert('Time Slot 2: Please provide both start and end times, or leave both empty');
                e.preventDefault();
                return false;
            }
        });
        
        // Clear time field helper
        $('.time-clear').on('click', function() {
            var fieldId = $(this).data('field');
            $('#' + fieldId).val('');
        });
    });
    </script>
</body>
</html>
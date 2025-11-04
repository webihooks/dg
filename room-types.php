<?php
// room-types.php
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

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'room_types_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room_type'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_rate = floatval($_POST['base_rate']);
        $max_occupancy = intval($_POST['max_occupancy']);
        $amenities = trim($_POST['amenities']);
        
        $insert_sql = "INSERT INTO room_types_$user_id (name, description, base_rate, max_occupancy, amenities) 
                      VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssdis", $name, $description, $base_rate, $max_occupancy, $amenities);
        
        if ($stmt->execute()) {
            $success_message = "Room type '$name' added successfully!";
        } else {
            $error_message = "Error adding room type: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all room types
$room_types_sql = "SELECT * FROM room_types_$user_id ORDER BY name";
$room_types_result = $conn->query($room_types_sql);
$room_types = [];
if ($room_types_result) {
    $room_types = $room_types_result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room Types</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="mb-0">Room Types</h4>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Add New Room Type</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Name *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Base Rate (₹) *</label>
                                        <input type="number" class="form-control" name="base_rate" step="0.01" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Max Occupancy</label>
                                        <input type="number" class="form-control" name="max_occupancy" min="1" value="2">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="2"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Amenities</label>
                                        <textarea class="form-control" name="amenities" rows="2" placeholder="AC, TV, WiFi, etc."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary" name="add_room_type">Add Room Type</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">All Room Types</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($room_types)): ?>
                                    <p class="text-muted">No room types added yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Base Rate</th>
                                                    <th>Max Occupancy</th>
                                                    <th>Description</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($room_types as $type): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($type['name']); ?></td>
                                                        <td>₹<?php echo number_format($type['base_rate'], 2); ?></td>
                                                        <td><?php echo $type['max_occupancy']; ?> persons</td>
                                                        <td><?php echo htmlspecialchars($type['description']); ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary">Edit</button>
                                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
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
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
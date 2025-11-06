<?php
// manage-rooms.php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Include required files
require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();
$sessionManager->validateAndroidSession();

require_once 'session_check.php';
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Check if room tables exist, if not redirect to table creation
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room'])) {
        // Add new room
        $room_number = trim($_POST['room_number']);
        $room_type_id = intval($_POST['room_type_id']);
        $floor = trim($_POST['floor']);
        $rate_per_night = floatval($_POST['rate_per_night']);
        $amenities = trim($_POST['amenities']);
        $description = trim($_POST['description']);
        
        // Check if room number already exists
        $check_sql = "SELECT id FROM rooms_$user_id WHERE room_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $room_number);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error_message = "Room number '$room_number' already exists!";
        } else {
            $insert_sql = "INSERT INTO rooms_$user_id 
                          (room_number, room_type_id, floor, rate_per_night, amenities, description, status) 
                          VALUES (?, ?, ?, ?, ?, ?, 'available')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sisdss", $room_number, $room_type_id, $floor, $rate_per_night, $amenities, $description);
            
            if ($insert_stmt->execute()) {
                $success_message = "Room '$room_number' added successfully!";
            } else {
                $error_message = "Error adding room: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
    elseif (isset($_POST['update_room'])) {
        // Update room
        $room_id = intval($_POST['room_id']);
        $room_number = trim($_POST['room_number']);
        $room_type_id = intval($_POST['room_type_id']);
        $floor = trim($_POST['floor']);
        $rate_per_night = floatval($_POST['rate_per_night']);
        $amenities = trim($_POST['amenities']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        $update_sql = "UPDATE rooms_$user_id 
                      SET room_number = ?, room_type_id = ?, floor = ?, rate_per_night = ?, 
                          amenities = ?, description = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                      WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sisdsssi", $room_number, $room_type_id, $floor, $rate_per_night, $amenities, $description, $status, $room_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Room updated successfully!";
        } else {
            $error_message = "Error updating room: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
    elseif (isset($_POST['delete_room'])) {
        // Delete room
        $room_id = intval($_POST['room_id']);
        
        // Check if room has active bookings
        $check_booking_sql = "SELECT id FROM bookings_$user_id WHERE room_id = ? AND status IN ('reserved', 'checked_in')";
        $check_stmt = $conn->prepare($check_booking_sql);
        $check_stmt->bind_param("i", $room_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error_message = "Cannot delete room with active or upcoming bookings!";
        } else {
            $delete_sql = "DELETE FROM rooms_$user_id WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $room_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Room deleted successfully!";
            } else {
                $error_message = "Error deleting room: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get all rooms with room type information
$rooms_sql = "SELECT r.*, rt.name as room_type_name, rt.base_rate as type_base_rate 
              FROM rooms_$user_id r 
              LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id 
              ORDER BY r.floor, r.room_number";
$rooms_result = $conn->query($rooms_sql);
$rooms = [];
if ($rooms_result) {
    $rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);
}

// Get room types for dropdown
$room_types_sql = "SELECT id, name, base_rate FROM room_types_$user_id WHERE is_active = 1 ORDER BY name";
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
    <title>Manage Rooms</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .room-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-available { background-color: #28a745; color: white; }
        .status-occupied { background-color: #dc3545; color: white; }
        .status-maintenance { background-color: #ffc107; color: #000; }
        .status-cleaning { background-color: #17a2b8; color: white; }
        .status-reserved { background-color: #6f42c1; color: white; }
        
        .room-actions .btn {
            margin: 2px;
            font-size: 12px;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .room-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .card-title, .card-text {
            color: #fff;
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
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">Manage Rooms</h4>
                            <div class="page-title-right">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                                    <i class="fas fa-plus-circle me-1"></i> Add New Room
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2 col-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Rooms</h5>
                                <h3 class="card-text"><?php echo count($rooms); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Available</h5>
                                <h3 class="card-text">
                                    <?php echo count(array_filter($rooms, function($room) { return $room['status'] === 'available'; })); ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Occupied</h5>
                                <h3 class="card-text">
                                    <?php echo count(array_filter($rooms, function($room) { return $room['status'] === 'occupied'; })); ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h5 class="card-title">Maintenance</h5>
                                <h3 class="card-text">
                                    <?php echo count(array_filter($rooms, function($room) { return $room['status'] === 'maintenance'; })); ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Cleaning</h5>
                                <h3 class="card-text">
                                    <?php echo count(array_filter($rooms, function($room) { return $room['status'] === 'cleaning'; })); ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Reserved</h5>
                                <h3 class="card-text">
                                    <?php echo count(array_filter($rooms, function($room) { return $room['status'] === 'reserved'; })); ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rooms Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bed me-2"></i>All Rooms
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($rooms)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-bed"></i>
                                        <h4>No Rooms Added Yet</h4>
                                        <p class="text-muted">Get started by adding your first room.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                                            <i class="fas fa-plus-circle me-1"></i> Add First Room
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped" id="roomsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Room No.</th>
                                                    <th>Floor</th>
                                                    <th>Room Type</th>
                                                    <th>Rate/Night</th>
                                                    <th>Status</th>
                                                    <th>Amenities</th>
                                                    <th>Last Updated</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rooms as $room): ?>
                                                    <tr class="room-status-<?php echo $room['status']; ?>">
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($room['room_number']); ?></strong>
                                                            <?php if ($room['description']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($room['description']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($room['floor'] ?: 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($room['room_type_name'] ?: 'Not Set'); ?></td>
                                                        <td>
                                                            <strong>₹<?php echo number_format($room['rate_per_night']); ?></strong>
                                                            <?php if ($room['type_base_rate'] != $room['rate_per_night']): ?>
                                                                <br><small class="text-success">Custom Rate</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge status-<?php echo $room['status']; ?>">
                                                                <?php echo ucfirst($room['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($room['amenities']): ?>
                                                                <small><?php echo htmlspecialchars(substr($room['amenities'], 0, 50)); ?><?php echo strlen($room['amenities']) > 50 ? '...' : ''; ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">No amenities</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small><?php echo date('M j, Y g:i A', strtotime($room['updated_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="room-actions">
                                                                <button class="btn btn-sm btn-outline-primary edit-room" 
                                                                        data-room-id="<?php echo $room['id']; ?>"
                                                                        data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>"
                                                                        data-room-type-id="<?php echo $room['room_type_id']; ?>"
                                                                        data-floor="<?php echo htmlspecialchars($room['floor']); ?>"
                                                                        data-rate="<?php echo $room['rate_per_night']; ?>"
                                                                        data-amenities="<?php echo htmlspecialchars($room['amenities']); ?>"
                                                                        data-description="<?php echo htmlspecialchars($room['description']); ?>"
                                                                        data-status="<?php echo $room['status']; ?>">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger delete-room" 
                                                                        data-room-id="<?php echo $room['id']; ?>"
                                                                        data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
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
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="addRoomForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Room</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Room Number *</label>
                                    <input type="text" class="form-control" name="room_number" required 
                                           placeholder="e.g., 10101A">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Floor</label>
                                    <input type="text" class="form-control" name="floor" 
                                           placeholder="e.g., 1st, Ground">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Room Type *</label>
                                    <select class="form-control" name="room_type_id" required>
                                        <option value="">Select Room Type</option>
                                        <?php foreach ($room_types as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" data-base-rate="<?php echo $type['base_rate']; ?>">
                                                <?php echo htmlspecialchars($type['name']); ?> (₹<?php echo number_format($type['base_rate']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($room_types)): ?>
                                        <small class="text-danger">No room types found. <a href="room-types.php">Create room types first</a>.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Rate Per Night (₹) *</label>
                                    <input type="number" class="form-control" name="rate_per_night" step="0.01" min="0" 
                                           required placeholder="0.00" id="ratePerNight">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Amenities</label>
                            <textarea class="form-control" name="amenities" rows="2" 
                                      placeholder="e.g., AC, TV, WiFi, Mini Bar..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" 
                                      placeholder="Room description, features..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_room">Add Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div class="modal fade" id="editRoomModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editRoomForm">
                    <input type="hidden" name="room_id" id="edit_room_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Room</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Room Number *</label>
                                    <input type="text" class="form-control" name="room_number" id="edit_room_number" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Floor</label>
                                    <input type="text" class="form-control" name="floor" id="edit_floor">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Room Type *</label>
                                    <select class="form-control" name="room_type_id" id="edit_room_type_id" required>
                                        <option value="">Select Room Type</option>
                                        <?php foreach ($room_types as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" data-base-rate="<?php echo $type['base_rate']; ?>">
                                                <?php echo htmlspecialchars($type['name']); ?> (₹<?php echo number_format($type['base_rate']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Rate Per Night (₹) *</label>
                                    <input type="number" class="form-control" name="rate_per_night" id="edit_rate_per_night" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="edit_status" required>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="cleaning">Cleaning</option>
                                <option value="reserved">Reserved</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Amenities</label>
                            <textarea class="form-control" name="amenities" id="edit_amenities" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_room">Update Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteRoomModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteRoomForm">
                    <input type="hidden" name="room_id" id="delete_room_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete room <strong id="delete_room_number"></strong>?</p>
                        <p class="text-danger"><small>This action cannot be undone. Any associated data will be lost.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" name="delete_room">Delete Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        // Auto-fill rate when room type is selected
        $('select[name="room_type_id"]').change(function() {
            const selectedOption = $(this).find('option:selected');
            const baseRate = selectedOption.data('base-rate');
            if (baseRate) {
                $('#ratePerNight').val(baseRate);
            }
        });

        // Edit room functionality
        $('.edit-room').click(function() {
            const roomId = $(this).data('room-id');
            const roomNumber = $(this).data('room-number');
            const roomTypeId = $(this).data('room-type-id');
            const floor = $(this).data('floor');
            const rate = $(this).data('rate');
            const amenities = $(this).data('amenities');
            const description = $(this).data('description');
            const status = $(this).data('status');
            
            $('#edit_room_id').val(roomId);
            $('#edit_room_number').val(roomNumber);
            $('#edit_room_type_id').val(roomTypeId);
            $('#edit_floor').val(floor);
            $('#edit_rate_per_night').val(rate);
            $('#edit_amenities').val(amenities);
            $('#edit_description').val(description);
            $('#edit_status').val(status);
            
            $('#editRoomModal').modal('show');
        });

        // Delete room functionality
        $('.delete-room').click(function() {
            const roomId = $(this).data('room-id');
            const roomNumber = $(this).data('room-number');
            
            $('#delete_room_id').val(roomId);
            $('#delete_room_number').text(roomNumber);
            
            $('#deleteRoomModal').modal('show');
        });

        // Auto-fill rate in edit modal
        $('#edit_room_type_id').change(function() {
            const selectedOption = $(this).find('option:selected');
            const baseRate = selectedOption.data('base-rate');
            if (baseRate && !$('#edit_rate_per_night').val()) {
                $('#edit_rate_per_night').val(baseRate);
            }
        });

        // Form validation
        $('#addRoomForm, #editRoomForm').on('submit', function(e) {
            const rate = $(this).find('input[name="rate_per_night"]').val();
            if (rate <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Rate',
                    text: 'Rate per night must be greater than 0.'
                });
            }
        });

        // Search functionality
        $('#searchRooms').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('#roomsTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Status filter
        $('#statusFilter').change(function() {
            const status = $(this).val();
            $('#roomsTable tbody tr').show();
            if (status) {
                $('#roomsTable tbody tr').not('.room-status-' + status).hide();
            }
        });
    });
    </script>
</body>
</html>
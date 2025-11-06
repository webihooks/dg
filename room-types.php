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
        
        // Check if room type name already exists
        $check_sql = "SELECT id FROM room_types_$user_id WHERE name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error_message = "Room type '$name' already exists!";
        } else {
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
        $check_stmt->close();
    }
    elseif (isset($_POST['update_room_type'])) {
        $type_id = intval($_POST['type_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_rate = floatval($_POST['base_rate']);
        $max_occupancy = intval($_POST['max_occupancy']);
        $amenities = trim($_POST['amenities']);
        
        $update_sql = "UPDATE room_types_$user_id 
                      SET name = ?, description = ?, base_rate = ?, max_occupancy = ?, amenities = ?, updated_at = CURRENT_TIMESTAMP 
                      WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssdisi", $name, $description, $base_rate, $max_occupancy, $amenities, $type_id);
        
        if ($stmt->execute()) {
            $success_message = "Room type updated successfully!";
        } else {
            $error_message = "Error updating room type: " . $stmt->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['delete_room_type'])) {
        $type_id = intval($_POST['type_id']);
        
        // Check if room type is being used by any rooms
        $check_rooms_sql = "SELECT id FROM rooms_$user_id WHERE room_type_id = ?";
        $check_stmt = $conn->prepare($check_rooms_sql);
        $check_stmt->bind_param("i", $type_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error_message = "Cannot delete room type that is being used by existing rooms!";
        } else {
            $delete_sql = "DELETE FROM room_types_$user_id WHERE id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("i", $type_id);
            
            if ($stmt->execute()) {
                $success_message = "Room type deleted successfully!";
            } else {
                $error_message = "Error deleting room type: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .room-type-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .room-type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .action-buttons .btn {
            margin: 2px;
            font-size: 12px;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
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
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">Room Types</h4>
                            <div class="page-title-right">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomTypeModal">
                                    <i class="fas fa-plus-circle me-1"></i> Add New Room Type
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

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Types</h5>
                                <h3 class="card-text"><?php echo count($room_types); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Avg. Rate</h5>
                                <h3 class="card-text">
                                    ₹<?php echo count($room_types) > 0 ? number_format(array_sum(array_column($room_types, 'base_rate')) / count($room_types)) : '0.00'; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Max Occupancy</h5>
                                <h3 class="card-text">
                                    <?php echo count($room_types) > 0 ? max(array_column($room_types, 'max_occupancy')) : '0'; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h5 class="card-title">Min Rate</h5>
                                <h3 class="card-text">
                                    ₹<?php echo count($room_types) > 0 ? number_format(min(array_column($room_types, 'base_rate'))) : '0.00'; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Room Types Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>All Room Types
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($room_types)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-hotel"></i>
                                        <h4>No Room Types Added Yet</h4>
                                        <p class="text-muted">Get started by adding your first room type.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomTypeModal">
                                            <i class="fas fa-plus-circle me-1"></i> Add First Room Type
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped" id="roomTypesTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Base Rate</th>
                                                    <th>Max Occupancy</th>
                                                    <th>Amenities</th>
                                                    <th>Description</th>
                                                    <th>Last Updated</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($room_types as $type): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <strong>₹<?php echo number_format($type['base_rate']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-primary"><?php echo $type['max_occupancy']; ?> persons</span>
                                                        </td>
                                                        <td>
                                                            <?php if ($type['amenities']): ?>
                                                                <small><?php echo htmlspecialchars(substr($type['amenities'], 0, 50)); ?><?php echo strlen($type['amenities']) > 50 ? '...' : ''; ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">No amenities</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($type['description']): ?>
                                                                <small><?php echo htmlspecialchars(substr($type['description'], 0, 80)); ?><?php echo strlen($type['description']) > 80 ? '...' : ''; ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">No description</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small><?php echo date('M j, Y', strtotime($type['updated_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <button class="btn btn-sm btn-outline-primary edit-room-type" 
                                                                        data-type-id="<?php echo $type['id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                                                        data-description="<?php echo htmlspecialchars($type['description']); ?>"
                                                                        data-base-rate="<?php echo $type['base_rate']; ?>"
                                                                        data-max-occupancy="<?php echo $type['max_occupancy']; ?>"
                                                                        data-amenities="<?php echo htmlspecialchars($type['amenities']); ?>">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger delete-room-type" 
                                                                        data-type-id="<?php echo $type['id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($type['name']); ?>">
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

    <!-- Add Room Type Modal -->
    <div class="modal fade" id="addRoomTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="addRoomTypeForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Room Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="e.g., Deluxe Room, Suite, Standard Room">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Rate (₹) *</label>
                            <input type="number" class="form-control" name="base_rate" step="0.01" min="0" required 
                                   placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max Occupancy</label>
                            <input type="number" class="form-control" name="max_occupancy" min="1" value="2">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" 
                                      placeholder="Room type description..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amenities</label>
                            <textarea class="form-control" name="amenities" rows="2" 
                                      placeholder="AC, TV, WiFi, Mini Bar, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_room_type">Add Room Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Room Type Modal -->
    <div class="modal fade" id="editRoomTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editRoomTypeForm">
                    <input type="hidden" name="type_id" id="edit_type_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Room Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Rate (₹) *</label>
                            <input type="number" class="form-control" name="base_rate" id="edit_base_rate" 
                                   step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max Occupancy</label>
                            <input type="number" class="form-control" name="max_occupancy" id="edit_max_occupancy" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amenities</label>
                            <textarea class="form-control" name="amenities" id="edit_amenities" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_room_type">Update Room Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteRoomTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteRoomTypeForm">
                    <input type="hidden" name="type_id" id="delete_type_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete room type "<strong id="delete_type_name"></strong>"?</p>
                        <p class="text-danger"><small>This action cannot be undone. You cannot delete room types that are being used by existing rooms.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" name="delete_room_type">Delete Room Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        // Edit room type functionality
        $('.edit-room-type').click(function() {
            const typeId = $(this).data('type-id');
            const name = $(this).data('name');
            const description = $(this).data('description');
            const baseRate = $(this).data('base-rate');
            const maxOccupancy = $(this).data('max-occupancy');
            const amenities = $(this).data('amenities');
            
            $('#edit_type_id').val(typeId);
            $('#edit_name').val(name);
            $('#edit_description').val(description);
            $('#edit_base_rate').val(baseRate);
            $('#edit_max_occupancy').val(maxOccupancy);
            $('#edit_amenities').val(amenities);
            
            $('#editRoomTypeModal').modal('show');
        });

        // Delete room type functionality
        $('.delete-room-type').click(function() {
            const typeId = $(this).data('type-id');
            const typeName = $(this).data('name');
            
            $('#delete_type_id').val(typeId);
            $('#delete_type_name').text(typeName);
            
            $('#deleteRoomTypeModal').modal('show');
        });

        // Form validation
        $('#addRoomTypeForm, #editRoomTypeForm').on('submit', function(e) {
            const baseRate = $(this).find('input[name="base_rate"]').val();
            if (baseRate <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Rate',
                    text: 'Base rate must be greater than 0.'
                });
            }
            
            const maxOccupancy = $(this).find('input[name="max_occupancy"]').val();
            if (maxOccupancy < 1) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Occupancy',
                    text: 'Max occupancy must be at least 1.'
                });
            }
        });

        // Search functionality
        $('#searchRoomTypes').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('#roomTypesTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
    });
    </script>
</body>
</html>
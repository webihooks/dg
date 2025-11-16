<?php
// bulk_rooms.php
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
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Handle CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_rooms'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        
        if (strtolower($fileExtension) !== 'csv') {
            $error_message = "Please upload a valid CSV file.";
        } else {
            $handle = fopen($file, 'r');
            if ($handle !== FALSE) {
                // Skip header row
                $header = fgetcsv($handle);
                
                $imported = 0;
                $updated = 0;
                $errors = [];
                $rowNumber = 1;
                
                // Start transaction for bulk operations
                $conn->begin_transaction();
                
                try {
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $rowNumber++;
                        
                        // Validate required fields
                        if (count($data) < 4) {
                            $errors[] = "Row $rowNumber: Insufficient data columns";
                            continue;
                        }
                        
                        $room_number = trim($data[0]);
                        $room_type_name = trim($data[1]);
                        $floor = trim($data[2]);
                        $rate_per_night = floatval(trim($data[3]));
                        $status = isset($data[4]) ? trim($data[4]) : 'available';
                        $description = isset($data[5]) ? trim($data[5]) : '';
                        $amenities = isset($data[6]) ? trim($data[6]) : '';
                        
                        // Validate required fields
                        if (empty($room_number)) {
                            $errors[] = "Row $rowNumber: Room number is required";
                            continue;
                        }
                        
                        if (empty($room_type_name)) {
                            $errors[] = "Row $rowNumber: Room type is required";
                            continue;
                        }
                        
                        if ($rate_per_night <= 0) {
                            $errors[] = "Row $rowNumber: Rate per night must be greater than 0";
                            continue;
                        }
                        
                        // Get or create room type
                        $room_type_sql = "SELECT id FROM room_types_$user_id WHERE name = ?";
                        $stmt = $conn->prepare($room_type_sql);
                        $stmt->bind_param("s", $room_type_name);
                        $stmt->execute();
                        $room_type_result = $stmt->get_result();
                        
                        if ($room_type_result->num_rows > 0) {
                            $room_type = $room_type_result->fetch_assoc();
                            $room_type_id = $room_type['id'];
                        } else {
                            // Create new room type
                            $create_type_sql = "INSERT INTO room_types_$user_id (name, base_rate, max_occupancy, is_active) VALUES (?, ?, 2, 1)";
                            $stmt = $conn->prepare($create_type_sql);
                            $stmt->bind_param("sd", $room_type_name, $rate_per_night);
                            $stmt->execute();
                            $room_type_id = $stmt->insert_id;
                        }
                        
                        // Check if room exists
                        $check_room_sql = "SELECT id FROM rooms_$user_id WHERE room_number = ?";
                        $stmt = $conn->prepare($check_room_sql);
                        $stmt->bind_param("s", $room_number);
                        $stmt->execute();
                        $room_result = $stmt->get_result();
                        
                        if ($room_result->num_rows > 0) {
                            // Update existing room
                            $room = $room_result->fetch_assoc();
                            $update_sql = "UPDATE rooms_$user_id SET room_type_id = ?, floor = ?, rate_per_night = ?, 
                                          status = ?, description = ?, amenities = ?, updated_at = CURRENT_TIMESTAMP 
                                          WHERE id = ?";
                            $stmt = $conn->prepare($update_sql);
                            $stmt->bind_param("isdsssi", $room_type_id, $floor, $rate_per_night, $status, $description, $amenities, $room['id']);
                            
                            if ($stmt->execute()) {
                                $updated++;
                            } else {
                                $errors[] = "Row $rowNumber: Failed to update room - " . $conn->error;
                            }
                        } else {
                            // Insert new room
                            $insert_sql = "INSERT INTO rooms_$user_id (room_number, room_type_id, floor, rate_per_night, 
                                          status, description, amenities) VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($insert_sql);
                            $stmt->bind_param("sisdsss", $room_number, $room_type_id, $floor, $rate_per_night, $status, $description, $amenities);
                            
                            if ($stmt->execute()) {
                                $imported++;
                            } else {
                                $errors[] = "Row $rowNumber: Failed to import room - " . $conn->error;
                            }
                        }
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success_message = "Bulk operation completed successfully! ";
                    $success_message .= "Imported: $imported rooms, Updated: $updated rooms";
                    
                    if (!empty($errors)) {
                        $error_message = "Some errors occurred:<br>" . implode("<br>", array_slice($errors, 0, 10));
                        if (count($errors) > 10) {
                            $error_message .= "<br>... and " . (count($errors) - 10) . " more errors";
                        }
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Import failed: " . $e->getMessage();
                }
                
                fclose($handle);
            } else {
                $error_message = "Failed to read CSV file.";
            }
        }
    } else {
        $error_message = "Please select a valid CSV file to upload.";
    }
}

// Handle Bulk Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_status'])) {
    if (isset($_POST['room_ids']) && !empty($_POST['room_ids']) && isset($_POST['bulk_status'])) {
        $room_ids = $_POST['room_ids'];
        $status = $_POST['bulk_status'];
        $placeholders = str_repeat('?,', count($room_ids) - 1) . '?';
        
        $update_sql = "UPDATE rooms_$user_id SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($update_sql);
        
        // Bind parameters
        $types = str_repeat('i', count($room_ids));
        $params = array_merge([$status], $room_ids);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        
        if ($stmt->execute()) {
            $success_message = "Successfully updated status for " . count($room_ids) . " rooms.";
        } else {
            $error_message = "Failed to update room status: " . $conn->error;
        }
    } else {
        $error_message = "Please select rooms and a status to update.";
    }
}

// Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (isset($_POST['room_ids']) && !empty($_POST['room_ids'])) {
        $room_ids = $_POST['room_ids'];
        
        // Check for active bookings
        $placeholders = str_repeat('?,', count($room_ids) - 1) . '?';
        $check_bookings_sql = "SELECT COUNT(*) as active_count FROM bookings_$user_id 
                              WHERE room_id IN ($placeholders) AND status IN ('reserved', 'checked_in')";
        $stmt = $conn->prepare($check_bookings_sql);
        $stmt->bind_param(str_repeat('i', count($room_ids)), ...$room_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $active_count = $result->fetch_assoc()['active_count'];
        
        if ($active_count > 0) {
            $error_message = "Cannot delete $active_count room(s) with active or reserved bookings.";
        } else {
            // Delete room images first
            $delete_images_sql = "DELETE FROM room_images_$user_id WHERE room_id IN ($placeholders)";
            $stmt = $conn->prepare($delete_images_sql);
            $stmt->bind_param(str_repeat('i', count($room_ids)), ...$room_ids);
            $stmt->execute();
            
            // Delete rooms
            $delete_sql = "DELETE FROM rooms_$user_id WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param(str_repeat('i', count($room_ids)), ...$room_ids);
            
            if ($stmt->execute()) {
                $success_message = "Successfully deleted " . count($room_ids) . " rooms.";
            } else {
                $error_message = "Failed to delete rooms: " . $conn->error;
            }
        }
    } else {
        $error_message = "Please select rooms to delete.";
    }
}

// Fetch all rooms for bulk operations
$rooms = [];
$rooms_sql = "
    SELECT r.*, rt.name as room_type_name,
           (SELECT COUNT(*) FROM bookings_$user_id b WHERE b.room_id = r.id AND b.status IN ('reserved', 'checked_in')) as active_bookings
    FROM rooms_$user_id r 
    LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id 
    ORDER BY r.room_number
";
$rooms_result = $conn->query($rooms_sql);
if ($rooms_result) {
    while ($room = $rooms_result->fetch_assoc()) {
        $rooms[] = $room;
    }
}

// Get room counts by status
$status_counts = [
    'available' => 0,
    'occupied' => 0,
    'maintenance' => 0,
    'cleaning' => 0,
    'reserved' => 0
];

foreach ($rooms as $room) {
    if (isset($status_counts[$room['status']])) {
        $status_counts[$room['status']]++;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Bulk Room Management</title>
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
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-available { background-color: #28a745; color: #fff; }
        .status-occupied { background-color: #dc3545; color: #fff; }
        .status-maintenance { background-color: #ffc107; color: #000; }
        .status-cleaning { background-color: #17a2b8; color: #fff; }
        .status-reserved { background-color: #6f42c1; color: #fff; }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #007bff;
            background: #e9ecef;
        }
        .upload-area i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .template-download {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .bulk-actions {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .count-card {
            border-left: 4px solid #007bff;
        }
        .count-card.available { border-color: #28a745; }
        .count-card.occupied { border-color: #dc3545; }
        .count-card.maintenance { border-color: #ffc107; }
        .count-card.cleaning { border-color: #17a2b8; }
        .count-card.reserved { border-color: #6f42c1; }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .select-all-container {
            background: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>
        
        <div class="page-content">
            <div class="container-fluid">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">Bulk Room Management</h4>
                            <div class="page-title-right">
                                <a href="manage-rooms.php" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Rooms
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Room Counts -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card count-card total">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-0"><?php echo count($rooms); ?></h4>
                                        <span class="text-muted">Total Rooms</span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-door-open fa-2x text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card count-card available">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-0"><?php echo $status_counts['available']; ?></h4>
                                        <span class="text-muted">Available</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card count-card occupied">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-0"><?php echo $status_counts['occupied']; ?></h4>
                                        <span class="text-muted">Occupied</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card count-card maintenance">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-0"><?php echo $status_counts['maintenance']; ?></h4>
                                        <span class="text-muted">Maintenance</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card count-card cleaning">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-0"><?php echo $status_counts['cleaning']; ?></h4>
                                        <span class="text-muted">Cleaning</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card count-card reserved">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-0"><?php echo $status_counts['reserved']; ?></h4>
                                        <span class="text-muted">Reserved</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- CSV Import Section -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-file-import me-2"></i>Import Rooms from CSV</h5>
                            </div>
                            <div class="card-body">
                                <div class="template-download">
                                    <h6><i class="fas fa-download me-2"></i>Download Template</h6>
                                    <p class="mb-2">Download our CSV template to ensure proper formatting for import.</p>
                                    <a href="javascript:void(0);" onclick="downloadTemplate()" class="btn btn-success btn-sm">
                                        <i class="fas fa-file-csv me-1"></i>Download CSV Template
                                    </a>
                                </div>
                                
                                <form method="POST" enctype="multipart/form-data" id="importForm">
                                    <div class="mb-3">
                                        <label class="form-label">Upload CSV File</label>
                                        <div class="upload-area" onclick="document.getElementById('csvFile').click()">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p>Click to upload CSV file or drag and drop</p>
                                            <small class="text-muted">CSV files only (Max 10MB)</small>
                                        </div>
                                        <input type="file" id="csvFile" name="csv_file" accept=".csv" 
                                               style="display: none;" onchange="previewFileName(this)">
                                        <div class="mt-2" id="fileName"></div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle me-2"></i>CSV Format Requirements:</h6>
                                        <ul class="mb-0">
                                            <li>Required columns: Room Number, Room Type, Floor, Rate/Night</li>
                                            <li>Optional columns: Status, Description, Amenities</li>
                                            <li>Status values: available, occupied, maintenance, cleaning, reserved</li>
                                            <li>File must have header row</li>
                                        </ul>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100" name="import_rooms">
                                        <i class="fas fa-upload me-2"></i>Import Rooms
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Operations Section -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Bulk Room Operations</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="bulkOperationsForm">
                                    <div class="bulk-actions">
                                        <h6 class="mb-3">Bulk Actions</h6>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Update Status</label>
                                                    <select class="form-control" name="bulk_status" id="bulkStatus">
                                                        <option value="">Select Status</option>
                                                        <option value="available">Available</option>
                                                        <option value="occupied">Occupied</option>
                                                        <option value="maintenance">Maintenance</option>
                                                        <option value="cleaning">Cleaning</option>
                                                        <option value="reserved">Reserved</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">&nbsp;</label>
                                                    <button type="submit" class="btn btn-warning w-100" name="bulk_update_status" id="bulkUpdateBtn" disabled>
                                                        <i class="fas fa-sync me-2"></i>Update Status
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <button type="button" class="btn btn-danger w-100" id="bulkDeleteBtn" disabled data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                                                <i class="fas fa-trash me-2"></i>Delete Selected Rooms
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered mb-0" id="roomsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">
                                                        <input type="checkbox" id="selectAll">
                                                    </th>
                                                    <th>Room Number</th>
                                                    <th>Type</th>
                                                    <th>Floor</th>
                                                    <th>Rate/Night</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($rooms)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">
                                                            <i class="fas fa-door-open fa-2x text-muted mb-3"></i>
                                                            <p class="text-muted">No rooms found. Import rooms using CSV or add them individually.</p>
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($rooms as $room): ?>
                                                        <tr>
                                                            <td>
                                                                <input type="checkbox" class="room-checkbox" name="room_ids[]" value="<?php echo $room['id']; ?>"
                                                                       data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>"
                                                                       data-active-bookings="<?php echo $room['active_bookings']; ?>">
                                                            </td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($room['room_number']); ?></strong>
                                                                <?php if ($room['active_bookings'] > 0): ?>
                                                                    <span class="badge bg-warning ms-1" title="Active Bookings"><?php echo $room['active_bookings']; ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($room['room_type_name'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($room['floor'] ?? 'N/A'); ?></td>
                                                            <td>₹<?php echo number_format($room['rate_per_night'], 2); ?></td>
                                                            <td>
                                                                <span class="status-badge status-<?php echo $room['status']; ?>">
                                                                    <?php echo ucfirst($room['status']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
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

    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Confirm Bulk Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteCount">0</strong> selected rooms?</p>
                    <p class="text-danger" id="activeBookingsWarning" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="activeRoomsCount">0</span> room(s) have active bookings and cannot be deleted.
                    </p>
                    <p class="text-danger"><small>This action cannot be undone. Any associated data will be lost.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" name="bulk_delete" form="bulkOperationsForm">Delete Selected</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
    function downloadTemplate() {
        const csvContent = "Room Number,Room Type,Floor,Rate/Night,Status,Description,Amenities\n101,Standard,1st,2500.00,available,Standard room with basic amenities,WiFi,TV,AC\n102,Deluxe,1st,3500.00,available,Luxury room with premium amenities,WiFi,TV,AC,Minibar\n103,Suite,2nd,5000.00,maintenance,Executive suite under maintenance,WiFi,TV,AC,Minibar,Jacuzzi";
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'room_import_template.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
    
    function previewFileName(input) {
        const fileName = document.getElementById('fileName');
        if (input.files && input.files[0]) {
            fileName.innerHTML = `<div class="alert alert-info p-2"><i class="fas fa-file-csv me-2"></i>${input.files[0].name}</div>`;
        } else {
            fileName.innerHTML = '';
        }
    }
    
    $(document).ready(function() {
        // Select All functionality
        $('#selectAll').change(function() {
            $('.room-checkbox').prop('checked', this.checked);
            updateBulkActions();
        });
        
        $('.room-checkbox').change(function() {
            if (!this.checked) {
                $('#selectAll').prop('checked', false);
            } else {
                const allChecked = $('.room-checkbox:checked').length === $('.room-checkbox').length;
                $('#selectAll').prop('checked', allChecked);
            }
            updateBulkActions();
        });
        
        function updateBulkActions() {
            const selectedCount = $('.room-checkbox:checked').length;
            const hasActiveBookings = $('.room-checkbox:checked').filter(function() {
                return $(this).data('active-bookings') > 0;
            }).length > 0;
            
            // Update bulk delete button
            $('#bulkDeleteBtn').prop('disabled', selectedCount === 0);
            
            // Update bulk update button
            const hasStatus = $('#bulkStatus').val() !== '';
            $('#bulkUpdateBtn').prop('disabled', selectedCount === 0 || !hasStatus);
            
            // Update delete modal content
            $('#deleteCount').text(selectedCount);
            
            if (hasActiveBookings) {
                const activeRoomsCount = $('.room-checkbox:checked').filter(function() {
                    return $(this).data('active-bookings') > 0;
                }).length;
                $('#activeBookingsWarning').show();
                $('#activeRoomsCount').text(activeRoomsCount);
            } else {
                $('#activeBookingsWarning').hide();
            }
        }
        
        $('#bulkStatus').change(function() {
            updateBulkActions();
        });
        
        // Drag and drop for file upload
        $('.upload-area').on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('bg-light');
        });
        
        $('.upload-area').on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('bg-light');
        });
        
        $('.upload-area').on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('bg-light');
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = $(this).next('input[type="file"]');
                fileInput[0].files = files;
                fileInput.trigger('change');
            }
        });
        
        // Form validation
        $('#importForm').on('submit', function(e) {
            const fileInput = document.getElementById('csvFile');
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'No File Selected',
                    text: 'Please select a CSV file to upload.'
                });
            }
        });
        
        $('#bulkOperationsForm').on('submit', function(e) {
            if ($(e.target).attr('name') === 'bulk_update_status') {
                const selectedCount = $('.room-checkbox:checked').length;
                if (selectedCount === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'No Rooms Selected',
                        text: 'Please select at least one room to update.'
                    });
                }
            }
        });
        
        // Initialize bulk actions
        updateBulkActions();
    });
    </script>
</body>
</html>
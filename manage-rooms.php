<?php
// manage-rooms.php
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

$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

$create_images_table_sql = "CREATE TABLE IF NOT EXISTS room_images_$user_id (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_order INT DEFAULT 0,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms_$user_id(id) ON DELETE CASCADE,
    INDEX idx_room_id (room_id),
    INDEX idx_image_order (image_order),
    INDEX idx_is_primary (is_primary)
)";
$conn->query($create_images_table_sql);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rooms_export_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Room Number', 'Room Type', 'Floor', 'Rate/Night', 'Status', 'Amenities', 'Description']);
    
    $export_sql = "
        SELECT r.room_number, rt.name as room_type, r.floor, r.rate_per_night, r.status, r.amenities, r.description
        FROM rooms_$user_id r 
        LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id 
        ORDER BY r.room_number
    ";
    $export_result = $conn->query($export_sql);
    
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['room_number'],
            $row['room_type'] ?? 'N/A',
            $row['floor'] ?? 'N/A',
            $row['rate_per_night'],
            ucfirst($row['status']),
            $row['amenities'] ?? '',
            $row['description'] ?? ''
        ]);
    }
    fclose($output);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room'])) {
        $room_number = trim($_POST['room_number']);
        $room_type_id = intval($_POST['room_type_id']);
        $floor = trim($_POST['floor']);
        $rate_per_night = floatval($_POST['rate_per_night']);
        $description = trim($_POST['description']);
        $amenities = isset($_POST['amenities']) ? implode(', ', $_POST['amenities']) : '';
        
        $check_sql = "SELECT id FROM rooms_$user_id WHERE room_number = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $room_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Room number already exists!";
        } else {
            $insert_sql = "INSERT INTO rooms_$user_id (room_number, room_type_id, floor, rate_per_night, description, amenities, status) 
                          VALUES (?, ?, ?, ?, ?, ?, 'available')";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sisdss", $room_number, $room_type_id, $floor, $rate_per_night, $description, $amenities);
            
            if ($stmt->execute()) {
                $room_id = $stmt->insert_id;
                $success_message = "Room added successfully!";
                
                if (!empty($_FILES['room_images']['name'][0])) {
                    $upload_dir = "uploads/rooms_$user_id/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $image_count = 0;
                    foreach ($_FILES['room_images']['tmp_name'] as $key => $tmp_name) {
                        if ($image_count >= 10) break;
                        
                        if ($_FILES['room_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = time() . '_' . basename($_FILES['room_images']['name'][$key]);
                            $file_path = $upload_dir . $file_name;
                            
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $is_primary = ($image_count === 0) ? 1 : 0;
                                $image_sql = "INSERT INTO room_images_$user_id (room_id, image_path, is_primary) VALUES (?, ?, ?)";
                                $img_stmt = $conn->prepare($image_sql);
                                $img_stmt->bind_param("isi", $room_id, $file_path, $is_primary);
                                $img_stmt->execute();
                                $img_stmt->close();
                                $image_count++;
                            }
                        }
                    }
                }
            } else {
                $error_message = "Error adding room: " . $conn->error;
            }
        }
    }
    
    if (isset($_POST['update_room'])) {
        $room_id = intval($_POST['room_id']);
        $room_number = trim($_POST['room_number']);
        $room_type_id = intval($_POST['room_type_id']);
        $floor = trim($_POST['floor']);
        $rate_per_night = floatval($_POST['rate_per_night']);
        $description = trim($_POST['description']);
        $amenities = isset($_POST['amenities']) ? implode(', ', $_POST['amenities']) : '';
        $status = $_POST['status'];
        
        $check_sql = "SELECT id FROM rooms_$user_id WHERE room_number = ? AND id != ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("si", $room_number, $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Room number already exists!";
        } else {
            $update_sql = "UPDATE rooms_$user_id SET room_number = ?, room_type_id = ?, floor = ?, rate_per_night = ?, 
                          description = ?, amenities = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sisdsssi", $room_number, $room_type_id, $floor, $rate_per_night, $description, $amenities, $status, $room_id);
            
            if ($stmt->execute()) {
                $success_message = "Room updated successfully!";
                
                if (!empty($_FILES['room_images']['name'][0])) {
                    $upload_dir = "uploads/rooms_$user_id/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $count_sql = "SELECT COUNT(*) as count FROM room_images_$user_id WHERE room_id = ?";
                    $count_stmt = $conn->prepare($count_sql);
                    $count_stmt->bind_param("i", $room_id);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    $existing_count = $count_result->fetch_assoc()['count'];
                    
                    $image_count = 0;
                    $max_images = 10 - $existing_count;
                    
                    foreach ($_FILES['room_images']['tmp_name'] as $key => $tmp_name) {
                        if ($image_count >= $max_images) break;
                        
                        if ($_FILES['room_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = time() . '_' . basename($_FILES['room_images']['name'][$key]);
                            $file_path = $upload_dir . $file_name;
                            
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $is_primary = 0;
                                $image_sql = "INSERT INTO room_images_$user_id (room_id, image_path, is_primary) VALUES (?, ?, ?)";
                                $img_stmt = $conn->prepare($image_sql);
                                $img_stmt->bind_param("isi", $room_id, $file_path, $is_primary);
                                $img_stmt->execute();
                                $img_stmt->close();
                                $image_count++;
                            }
                        }
                    }
                }
            } else {
                $error_message = "Error updating room: " . $conn->error;
            }
        }
    }
    
    if (isset($_POST['delete_room'])) {
        $room_id = intval($_POST['room_id']);
        
        $check_bookings_sql = "SELECT id FROM bookings_$user_id WHERE room_id = ? AND status IN ('reserved', 'checked_in')";
        $stmt = $conn->prepare($check_bookings_sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Cannot delete room with active or reserved bookings!";
        } else {
            $delete_images_sql = "DELETE FROM room_images_$user_id WHERE room_id = ?";
            $stmt = $conn->prepare($delete_images_sql);
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            
            $delete_sql = "DELETE FROM rooms_$user_id WHERE id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("i", $room_id);
            
            if ($stmt->execute()) {
                $success_message = "Room deleted successfully!";
            } else {
                $error_message = "Error deleting room: " . $conn->error;
            }
        }
    }
    
    if (isset($_POST['set_primary_image'])) {
        $image_id = intval($_POST['image_id']);
        $room_id = intval($_POST['room_id']);
        
        $reset_sql = "UPDATE room_images_$user_id SET is_primary = 0 WHERE room_id = ?";
        $stmt = $conn->prepare($reset_sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        $set_primary_sql = "UPDATE room_images_$user_id SET is_primary = 1 WHERE id = ?";
        $stmt = $conn->prepare($set_primary_sql);
        $stmt->bind_param("i", $image_id);
        
        if ($stmt->execute()) {
            $success_message = "Primary image updated successfully!";
        } else {
            $error_message = "Error updating primary image!";
        }
    }
    
    if (isset($_POST['delete_image'])) {
        $image_id = intval($_POST['image_id']);
        $room_id = intval($_POST['room_id']);
        
        $check_sql = "SELECT is_primary FROM room_images_$user_id WHERE id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $image_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $image = $result->fetch_assoc();
        
        $delete_sql = "DELETE FROM room_images_$user_id WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $image_id);
        
        if ($stmt->execute()) {
            $success_message = "Image deleted successfully!";
            
            if ($image['is_primary']) {
                $new_primary_sql = "UPDATE room_images_$user_id SET is_primary = 1 WHERE room_id = ? LIMIT 1";
                $stmt = $conn->prepare($new_primary_sql);
                $stmt->bind_param("i", $room_id);
                $stmt->execute();
            }
        } else {
            $error_message = "Error deleting image!";
        }
    }
}

// Fetch room types with counts
$room_types = [];
$room_types_sql = "
    SELECT rt.*, COUNT(r.id) as room_count 
    FROM room_types_$user_id rt 
    LEFT JOIN rooms_$user_id r ON rt.id = r.room_type_id 
    WHERE rt.is_active = 1 
    GROUP BY rt.id 
    ORDER BY rt.name
";
$room_types_result = $conn->query($room_types_sql);
if ($room_types_result) {
    while ($row = $room_types_result->fetch_assoc()) {
        $room_types[] = $row;
    }
}

// Fetch amenities for checkboxes
$amenities = [];
$amenities_sql = "SELECT id, name, icon FROM room_amenities_$user_id WHERE is_active = 1 ORDER BY name";
$amenities_result = $conn->query($amenities_sql);
if ($amenities_result) {
    while ($row = $amenities_result->fetch_assoc()) {
        $amenities[] = $row;
    }
}

// Fetch all rooms with their images and room type info
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
        $images_sql = "SELECT * FROM room_images_$user_id WHERE room_id = ? ORDER BY is_primary DESC, image_order ASC";
        $stmt = $conn->prepare($images_sql);
        $stmt->bind_param("i", $room['id']);
        $stmt->execute();
        $images_result = $stmt->get_result();
        $room['images'] = [];
        while ($image = $images_result->fetch_assoc()) {
            $room['images'][] = $image;
        }
        $stmt->close();
        $rooms[] = $room;
    }
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
    <style>.room-card{transition:all .3s ease;border:1px solid #e9ecef}.room-card:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(0,0,0,.1)}.status-badge{padding:4px 8px;border-radius:12px;font-size:11px;font-weight:bold;text-transform:uppercase}.status-available{background-color:#28a745;color:#fff}.status-occupied{background-color:#dc3545;color:#fff}.status-maintenance{background-color:#ffc107;color:#000}.status-cleaning{background-color:#17a2b8;color:#fff}.status-reserved{background-color:#6f42c1;color:#fff}.room-actions .btn{margin:2px;font-size:12px}.table-responsive{border-radius:10px;overflow:hidden}.room-image{width:60px;height:60px;object-fit:cover;border-radius:8px;cursor:pointer;transition:transform .3s ease}.room-image:hover{transform:scale(1.1)}.image-thumbnail{position:relative;display:inline-block;margin:5px}.image-actions{position:absolute;top:5px;right:5px;opacity:0;transition:opacity .3s ease}.image-thumbnail:hover .image-actions{opacity:1}.primary-badge{position:absolute;top:5px;left:5px;background:#28a745;color:#fff;padding:2px 6px;border-radius:4px;font-size:10px}.empty-state{text-align:center;padding:40px;color:#6c757d}.empty-state i{font-size:48px;margin-bottom:15px;opacity:.5}.export-btn{background-color:#28a745;border-color:#28a745;color:#fff}.export-btn:hover{background-color:#218838;border-color:#1e7e34;color:#fff}.image-preview-container{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}.image-preview{width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #dee2e6}.upload-area{border:2px dashed #dee2e6;border-radius:8px;padding:20px;text-align:center;background:#f8f9fa;cursor:pointer;transition:all .3s ease}.upload-area:hover{border-color:#007bff;background:#e9ecef}.upload-area i{font-size:48px;color:#6c757d;margin-bottom:10px}.amenities-container{max-height:200px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;padding:10px;background:#f8f9fa}.amenity-item{display:flex;align-items:center;padding:8px;border-bottom:1px solid #e9ecef;transition:background-color .2s ease}.amenity-item:last-child{border-bottom:none}.amenity-item:hover{background-color:#e9ecef}.amenity-item input[type=checkbox]{margin-right:10px}.amenity-icon{margin-right:8px;color:#6c757d}.selected-amenities{margin-top:10px;padding:10px;background:#e7f3ff;border-radius:8px;border:1px solid #b3d9ff}.selected-amenity-tag{display:inline-block;background:#007bff;color:#fff;padding:4px 8px;border-radius:12px;font-size:12px;margin:2px}.select-all-btn{margin-bottom:10px}.room-type-counts{margin-bottom:20px}.count-card{border-left:4px solid #007bff}.count-card.available{border-color:#28a745}.count-card.occupied{border-color:#dc3545}.count-card.maintenance{border-color:#ffc107}.count-card.total{border-color:#6f42c1}</style>
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
                            <h4 class="mb-0">Manage Rooms</h4>
                            <div class="page-title-right">
                                <a href="?export=csv" class="btn btn-success export-btn me-2">
                                    <i class="fas fa-download me-2"></i>Export CSV
                                </a>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                                    <i class="fas fa-plus me-2"></i>Add New Room
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Room Type Counts -->
                <div class="row room-type-counts">
                    <div class="col-md-3">
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
                                        <h4 class="mb-0"><?php echo count(array_filter($rooms, fn($room) => $room['status'] === 'available')); ?></h4>
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
                                        <h4 class="mb-0"><?php echo count(array_filter($rooms, fn($room) => $room['status'] === 'occupied')); ?></h4>
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
                                        <h4 class="mb-0"><?php echo count(array_filter($rooms, fn($room) => $room['status'] === 'maintenance')); ?></h4>
                                        <span class="text-muted">Maintenance</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="mb-0"><?php echo count($room_types); ?></h4>
                                        <span class="text-muted">Room Types</span>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-list fa-2x text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <?php if (empty($rooms)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-door-open"></i>
                                        <h4>No Rooms Added Yet</h4>
                                        <p>Start by adding your first room to manage your property.</p>
                                        <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                                            <i class="fas fa-plus me-2"></i>Add Your First Room
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Room</th>
                                                    <th>Type</th>
                                                    <th>Floor</th>
                                                    <th>Rate/Night</th>
                                                    <th>Status</th>
                                                    <th>Images</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rooms as $room): ?>
                                                    <tr>
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
                                                        <td>
                                                            <?php if (!empty($room['images'])): ?>
                                                                <div class="d-flex">
                                                                    <?php foreach (array_slice($room['images'], 0, 3) as $image): ?>
                                                                        <div class="image-thumbnail">
                                                                            <img src="<?php echo $image['image_path']; ?>" 
                                                                                 class="room-image" 
                                                                                 alt="Room Image"
                                                                                 onclick="viewRoomImages(<?php echo $room['id']; ?>)">
                                                                            <?php if ($image['is_primary']): ?>
                                                                                <span class="primary-badge">P</span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                    <?php if (count($room['images']) > 3): ?>
                                                                        <div class="image-thumbnail">
                                                                            <div class="room-image bg-light d-flex align-items-center justify-content-center">
                                                                                <small>+<?php echo count($room['images']) - 3; ?> more</small>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">No images</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="room-actions">
                                                                <button class="btn btn-sm btn-outline-primary edit-room"
                                                                        data-room-id="<?php echo $room['id']; ?>"
                                                                        data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>"
                                                                        data-room-type-id="<?php echo $room['room_type_id']; ?>"
                                                                        data-floor="<?php echo htmlspecialchars($room['floor']); ?>"
                                                                        data-rate="<?php echo $room['rate_per_night']; ?>"
                                                                        data-amenities="<?php echo htmlspecialchars($room['amenities'] ?? ''); ?>"
                                                                        data-description="<?php echo htmlspecialchars($room['description'] ?? ''); ?>"
                                                                        data-status="<?php echo $room['status']; ?>"
                                                                        data-images='<?php echo json_encode($room['images']); ?>'>
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-info view-images"
                                                                        data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>"
                                                                        data-images='<?php echo json_encode($room['images']); ?>'>
                                                                    <i class="fas fa-images"></i>
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
                <form method="POST" id="addRoomForm" enctype="multipart/form-data">
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
                                                <?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['room_count']; ?> rooms) - ₹<?php echo number_format($type['base_rate']); ?>
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
                            <label class="form-label">Room Images (Max 10 images)</label>
                            <div class="upload-area" onclick="document.getElementById('roomImages').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload images or drag and drop</p>
                                <small class="text-muted">JPG, PNG, GIF, WEBP (Max 5MB each)</small>
                            </div>
                            <input type="file" id="roomImages" name="room_images[]" multiple 
                                   accept="image/*" style="display: none;" onchange="previewImages(this, 'addPreview')">
                            <div class="image-preview-container" id="addPreview"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amenities</label>
                            <?php if (!empty($amenities)): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary select-all-btn mb-2" id="selectAllAmenities">
                                    <i class="fas fa-check-double me-1"></i>Select All
                                </button>
                                <div class="amenities-container">
                                    <?php foreach ($amenities as $amenity): ?>
                                        <div class="amenity-item">
                                            <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($amenity['name']); ?>" 
                                                   id="amenity_<?php echo $amenity['id']; ?>" class="amenity-checkbox">
                                            <label for="amenity_<?php echo $amenity['id']; ?>" class="mb-0">
                                                <?php if ($amenity['icon']): ?>
                                                    <i class="fas fa-<?php echo $amenity['icon']; ?> amenity-icon"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($amenity['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="selected-amenities" id="selectedAmenitiesAdd" style="display: none;">
                                    <small class="text-muted">Selected Amenities:</small>
                                    <div id="selectedAmenitiesListAdd"></div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No amenities found. <a href="room-amenities.php">Add amenities first</a>.
                                </div>
                            <?php endif; ?>
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
                <form method="POST" id="editRoomForm" enctype="multipart/form-data">
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
                                                <?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['room_count']; ?> rooms) - ₹<?php echo number_format($type['base_rate']); ?>
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
                            <label class="form-label">Additional Room Images (Max 10 total)</label>
                            <div class="upload-area" onclick="document.getElementById('editRoomImages').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload additional images</p>
                                <small class="text-muted">JPG, PNG, GIF, WEBP (Max 5MB each)</small>
                            </div>
                            <input type="file" id="editRoomImages" name="room_images[]" multiple 
                                   accept="image/*" style="display: none;" onchange="previewImages(this, 'editPreview')">
                            <div class="image-preview-container" id="editPreview"></div>
                            <div id="existingImages" class="mt-3">
                                <h6>Existing Images:</h6>
                                <div class="image-preview-container" id="existingImagesList"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amenities</label>
                            <?php if (!empty($amenities)): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary select-all-btn mb-2" id="selectAllAmenitiesEdit">
                                    <i class="fas fa-check-double me-1"></i>Select All
                                </button>
                                <div class="amenities-container">
                                    <?php foreach ($amenities as $amenity): ?>
                                        <div class="amenity-item">
                                            <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($amenity['name']); ?>" 
                                                   id="edit_amenity_<?php echo $amenity['id']; ?>" class="amenity-checkbox">
                                            <label for="edit_amenity_<?php echo $amenity['id']; ?>" class="mb-0">
                                                <?php if ($amenity['icon']): ?>
                                                    <i class="fas fa-<?php echo $amenity['icon']; ?> amenity-icon"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($amenity['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="selected-amenities" id="selectedAmenitiesEdit" style="display: none;">
                                    <small class="text-muted">Selected Amenities:</small>
                                    <div id="selectedAmenitiesListEdit"></div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No amenities found. <a href="room-amenities.php">Add amenities first</a>.
                                </div>
                            <?php endif; ?>
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

    <!-- View Images Modal -->
    <div class="modal fade" id="viewImagesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Room Images - <span id="viewRoomNumber"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row" id="imagesGallery"></div>
                </div>
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
    function previewImages(input, previewId) {
        const preview = document.getElementById(previewId);
        preview.innerHTML = '';
        if (input.files) {
            const files = Array.from(input.files).slice(0, 10);
            files.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        img.title = file.name;
                        preview.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    }
    
    function displayExistingImages(images, containerId) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        if (images && images.length > 0) {
            images.forEach((image, index) => {
                const imageDiv = document.createElement('div');
                imageDiv.className = 'image-thumbnail';
                imageDiv.innerHTML = `<img src="${image.image_path}" class="room-image" alt="Room Image" style="width: 80px; height: 80px;">${image.is_primary ? '<span class="primary-badge">Primary</span>' : ''}<div class="image-actions"><button type="button" class="btn btn-sm btn-success set-primary-btn" data-image-id="${image.id}" data-room-id="${image.room_id}" title="Set as Primary"><i class="fas fa-star"></i></button><button type="button" class="btn btn-sm btn-danger delete-image-btn" data-image-id="${image.id}" data-room-id="${image.room_id}" title="Delete Image"><i class="fas fa-trash"></i></button></div>`;
                container.appendChild(imageDiv);
            });
        } else {
            container.innerHTML = '<p class="text-muted">No images uploaded yet.</p>';
        }
    }

    function updateSelectedAmenities(containerId, listId) {
        const container = document.getElementById(containerId);
        const list = document.getElementById(listId);
        const checkboxes = document.querySelectorAll('.amenity-checkbox');
        list.innerHTML = '';
        let selectedCount = 0;
        checkboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selectedCount++;
                const tag = document.createElement('span');
                tag.className = 'selected-amenity-tag';
                tag.textContent = checkbox.value;
                list.appendChild(tag);
            }
        });
        if (selectedCount > 0) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }

    function setAmenitiesFromString(amenitiesString, prefix = '') {
        document.querySelectorAll('.amenity-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        if (amenitiesString) {
            const amenitiesArray = amenitiesString.split(', ');
            amenitiesArray.forEach(amenity => {
                const trimmedAmenity = amenity.trim();
                if (trimmedAmenity) {
                    const checkbox = document.querySelector(`.amenity-checkbox[value="${trimmedAmenity}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                }
            });
        }
        updateSelectedAmenities('selectedAmenitiesEdit', 'selectedAmenitiesListEdit');
    }

    function toggleAllAmenities(selectAll) {
        document.querySelectorAll('.amenity-checkbox').forEach(checkbox => {
            checkbox.checked = selectAll;
        });
        updateSelectedAmenities('selectedAmenitiesAdd', 'selectedAmenitiesListAdd');
        updateSelectedAmenities('selectedAmenitiesEdit', 'selectedAmenitiesListEdit');
    }

    $(document).ready(function() {
        $('select[name="room_type_id"]').change(function() {
            const selectedOption = $(this).find('option:selected');
            const baseRate = selectedOption.data('base-rate');
            if (baseRate) {
                $('#ratePerNight').val(baseRate);
            }
        });

        $('.amenity-checkbox').change(function() {
            const isEditModal = $(this).attr('id').startsWith('edit_');
            if (isEditModal) {
                updateSelectedAmenities('selectedAmenitiesEdit', 'selectedAmenitiesListEdit');
            } else {
                updateSelectedAmenities('selectedAmenitiesAdd', 'selectedAmenitiesListAdd');
            }
        });

        $('#selectAllAmenities').click(function() {
            const checkboxes = document.querySelectorAll('#addRoomModal .amenity-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            updateSelectedAmenities('selectedAmenitiesAdd', 'selectedAmenitiesListAdd');
        });

        $('#selectAllAmenitiesEdit').click(function() {
            const checkboxes = document.querySelectorAll('#editRoomModal .amenity-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            updateSelectedAmenities('selectedAmenitiesEdit', 'selectedAmenitiesListEdit');
        });

        $('.edit-room').click(function() {
            const roomId = $(this).data('room-id');
            const roomNumber = $(this).data('room-number');
            const roomTypeId = $(this).data('room-type-id');
            const floor = $(this).data('floor');
            const rate = $(this).data('rate');
            const amenities = $(this).data('amenities');
            const description = $(this).data('description');
            const status = $(this).data('status');
            const images = $(this).data('images');
            $('#edit_room_id').val(roomId);
            $('#edit_room_number').val(roomNumber);
            $('#edit_room_type_id').val(roomTypeId);
            $('#edit_floor').val(floor);
            $('#edit_rate_per_night').val(rate);
            $('#edit_description').val(description);
            $('#edit_status').val(status);
            setAmenitiesFromString(amenities, 'edit_');
            displayExistingImages(images, 'existingImagesList');
            $('#editRoomModal').modal('show');
        });

        $('.view-images').click(function() {
            const roomNumber = $(this).data('room-number');
            const images = $(this).data('images');
            $('#viewRoomNumber').text(roomNumber);
            const gallery = $('#imagesGallery');
            gallery.empty();
            if (images && images.length > 0) {
                images.forEach((image, index) => {
                    const col = $('<div class="col-md-4 mb-3"></div>');
                    const card = $(`<div class="card"><img src="${image.image_path}" class="card-img-top" alt="Room Image" style="height: 200px; object-fit: cover;"><div class="card-body text-center">${image.is_primary ? '<span class="badge bg-success">Primary Image</span>' : ''}<div class="mt-2"><form method="POST" style="display: inline;"><input type="hidden" name="image_id" value="${image.id}"><input type="hidden" name="room_id" value="${image.room_id}">${!image.is_primary ? '<button type="submit" name="set_primary_image" class="btn btn-sm btn-success"><i class="fas fa-star me-1"></i>Set Primary</button>' : ''}<button type="submit" name="delete_image" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this image?\')"><i class="fas fa-trash me-1"></i>Delete</button></form></div></div></div>`);
                    col.append(card);
                    gallery.append(col);
                });
            } else {
                gallery.html('<div class="col-12 text-center"><p class="text-muted">No images available for this room.</p></div>');
            }
            $('#viewImagesModal').modal('show');
        });

        $('.delete-room').click(function() {
            const roomId = $(this).data('room-id');
            const roomNumber = $(this).data('room-number');
            $('#delete_room_id').val(roomId);
            $('#delete_room_number').text(roomNumber);
            $('#deleteRoomModal').modal('show');
        });

        $('#edit_room_type_id').change(function() {
            const selectedOption = $(this).find('option:selected');
            const baseRate = selectedOption.data('base-rate');
            if (baseRate && !$('#edit_rate_per_night').val()) {
                $('#edit_rate_per_night').val(baseRate);
            }
        });

        $(document).on('click', '.set-primary-btn', function() {
            const imageId = $(this).data('image-id');
            const roomId = $(this).data('room-id');
            $.post('', {set_primary_image: true, image_id: imageId, room_id: roomId}, function(response) {
                location.reload();
            });
        });

        $(document).on('click', '.delete-image-btn', function() {
            const imageId = $(this).data('image-id');
            const roomId = $(this).data('room-id');
            if (confirm('Are you sure you want to delete this image?')) {
                $.post('', {delete_image: true, image_id: imageId, room_id: roomId}, function(response) {
                    location.reload();
                });
            }
        });

        $('#addRoomForm, #editRoomForm').on('submit', function(e) {
            const rate = $(this).find('input[name="rate_per_night"]').val();
            if (rate <= 0) {
                e.preventDefault();
                Swal.fire({icon: 'error', title: 'Invalid Rate', text: 'Rate per night must be greater than 0.'});
            }
            const files = $(this).find('input[type="file"]')[0].files;
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > 5 * 1024 * 1024) {
                    e.preventDefault();
                    Swal.fire({icon: 'error', title: 'File Too Large', text: 'Each image must be less than 5MB.'});
                    break;
                }
            }
        });

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
            const fileInput = $(this).next('input[type="file"]');
            fileInput[0].files = files;
            fileInput.trigger('change');
        });

        updateSelectedAmenities('selectedAmenitiesAdd', 'selectedAmenitiesListAdd');
    });
    </script>
</body>
</html>
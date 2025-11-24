<?php
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

// Create room photos table if it doesn't exist
$create_photos_table_sql = "CREATE TABLE IF NOT EXISTS room_photos_$user_id (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_type_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types_$user_id(id) ON DELETE CASCADE
)";
$conn->query($create_photos_table_sql);

// Create amenities table if it doesn't exist
$create_amenities_table_sql = "CREATE TABLE IF NOT EXISTS amenities_$user_id (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT 'fas fa-star',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_amenities_table_sql);

// Create room type amenities junction table
$create_junction_table_sql = "CREATE TABLE IF NOT EXISTS room_type_amenities_$user_id (
    room_type_id INT NOT NULL,
    amenity_id INT NOT NULL,
    PRIMARY KEY (room_type_id, amenity_id),
    FOREIGN KEY (room_type_id) REFERENCES room_types_$user_id(id) ON DELETE CASCADE,
    FOREIGN KEY (amenity_id) REFERENCES amenities_$user_id(id) ON DELETE CASCADE
)";
$conn->query($create_junction_table_sql);

// Create rooms inventory table
$create_rooms_table_sql = "CREATE TABLE IF NOT EXISTS rooms_$user_id (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    room_type_id INT NOT NULL,
    floor VARCHAR(10) DEFAULT '1',
    status ENUM('available', 'occupied', 'maintenance', 'cleaning') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types_$user_id(id) ON DELETE CASCADE
)";
$conn->query($create_rooms_table_sql);

// Default amenities if table is empty
$check_amenities_sql = "SELECT COUNT(*) as count FROM amenities_$user_id";
$amenities_result = $conn->query($check_amenities_sql);
$amenities_count = $amenities_result->fetch_assoc()['count'];

if ($amenities_count == 0) {
    $default_amenities = [
        ['WiFi', 'fas fa-wifi'],
        ['Air Conditioning', 'fas fa-snowflake'],
        ['TV', 'fas fa-tv'],
        ['Mini Bar', 'fas fa-glass-martini-alt'],
        ['Safe', 'fas fa-lock'],
        ['Hair Dryer', 'fas fa-wind'],
        ['Room Service', 'fas fa-concierge-bell'],
        ['Balcony', 'fas fa-door-open'],
        ['Sea View', 'fas fa-water'],
        ['Pool View', 'fas fa-swimming-pool'],
        ['Bathtub', 'fas fa-bath'],
        ['Shower', 'fas fa-shower'],
        ['Desk', 'fas fa-desktop'],
        ['Coffee Maker', 'fas fa-coffee'],
        ['Refrigerator', 'fas fa-thermometer-empty']
    ];
    
    $insert_amenity_stmt = $conn->prepare("INSERT INTO amenities_$user_id (name, icon) VALUES (?, ?)");
    foreach ($default_amenities as $amenity) {
        $insert_amenity_stmt->bind_param("ss", $amenity[0], $amenity[1]);
        $insert_amenity_stmt->execute();
    }
    $insert_amenity_stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room_type'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_rate = floatval($_POST['base_rate']);
        $max_occupancy = intval($_POST['max_occupancy']);
        $amenities = isset($_POST['amenities']) ? $_POST['amenities'] : [];
        $room_count = intval($_POST['room_count']);
        
        // Check if room type name already exists
        $check_sql = "SELECT id FROM room_types_$user_id WHERE name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error_message = "Room type '$name' already exists!";
        } else {
            // Insert room type
            $insert_sql = "INSERT INTO room_types_$user_id (name, description, base_rate, max_occupancy) 
                          VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssdi", $name, $description, $base_rate, $max_occupancy);
            
            if ($stmt->execute()) {
                $room_type_id = $stmt->insert_id;
                
                // Add amenities
                if (!empty($amenities)) {
                    $amenity_sql = "INSERT INTO room_type_amenities_$user_id (room_type_id, amenity_id) VALUES (?, ?)";
                    $amenity_stmt = $conn->prepare($amenity_sql);
                    foreach ($amenities as $amenity_id) {
                        $amenity_stmt->bind_param("ii", $room_type_id, $amenity_id);
                        $amenity_stmt->execute();
                    }
                    $amenity_stmt->close();
                }
                
                // Add rooms inventory
                if ($room_count > 0) {
                    $room_sql = "INSERT INTO rooms_$user_id (room_number, room_type_id, floor) VALUES (?, ?, ?)";
                    $room_stmt = $conn->prepare($room_sql);
                    
                    // Get the highest room number to continue from there
                    $max_room_sql = "SELECT MAX(CAST(SUBSTRING(room_number, 2) AS UNSIGNED)) as max_num FROM rooms_$user_id WHERE room_number LIKE 'R%'";
                    $max_result = $conn->query($max_room_sql);
                    $max_num = 0;
                    if ($max_result && $max_row = $max_result->fetch_assoc()) {
                        $max_num = $max_row['max_num'] ?: 0;
                    }
                    
                    for ($i = 1; $i <= $room_count; $i++) {
                        $room_number = 'R' . ($max_num + $i);
                        $floor = ceil(($max_num + $i) / 10); // Simple floor calculation
                        $room_stmt->bind_param("sis", $room_number, $room_type_id, $floor);
                        $room_stmt->execute();
                    }
                    $room_stmt->close();
                }
                
                // Handle photo uploads
                if (!empty($_FILES['photos']['name'][0])) {
                    $upload_dir = "uploads/room_photos/$user_id/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $photo_count = 0;
                    foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                        if ($photo_count >= 10) break; // Limit to 10 photos
                        
                        if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = time() . '_' . basename($_FILES['photos']['name'][$key]);
                            $file_path = $upload_dir . $file_name;
                            
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $is_primary = ($key == 0) ? 1 : 0; // First photo is primary
                                $photo_sql = "INSERT INTO room_photos_$user_id (room_type_id, photo_path, is_primary) VALUES (?, ?, ?)";
                                $photo_stmt = $conn->prepare($photo_sql);
                                $photo_stmt->bind_param("isi", $room_type_id, $file_path, $is_primary);
                                $photo_stmt->execute();
                                $photo_stmt->close();
                                $photo_count++;
                            }
                        }
                    }
                }
                
                $success_message = "Room type '$name' added successfully with $room_count rooms!";
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
        $amenities = isset($_POST['amenities']) ? $_POST['amenities'] : [];
        
        $update_sql = "UPDATE room_types_$user_id 
                      SET name = ?, description = ?, base_rate = ?, max_occupancy = ?, updated_at = CURRENT_TIMESTAMP 
                      WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssdii", $name, $description, $base_rate, $max_occupancy, $type_id);
        
        if ($stmt->execute()) {
            // Update amenities
            // First delete existing amenities
            $delete_amenities_sql = "DELETE FROM room_type_amenities_$user_id WHERE room_type_id = ?";
            $delete_stmt = $conn->prepare($delete_amenities_sql);
            $delete_stmt->bind_param("i", $type_id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Add new amenities
            if (!empty($amenities)) {
                $amenity_sql = "INSERT INTO room_type_amenities_$user_id (room_type_id, amenity_id) VALUES (?, ?)";
                $amenity_stmt = $conn->prepare($amenity_sql);
                foreach ($amenities as $amenity_id) {
                    $amenity_stmt->bind_param("ii", $type_id, $amenity_id);
                    $amenity_stmt->execute();
                }
                $amenity_stmt->close();
            }
            
            // Handle photo uploads
            if (!empty($_FILES['photos']['name'][0])) {
                $upload_dir = "uploads/room_photos/$user_id/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Check current photo count
                $count_sql = "SELECT COUNT(*) as count FROM room_photos_$user_id WHERE room_type_id = ?";
                $count_stmt = $conn->prepare($count_sql);
                $count_stmt->bind_param("i", $type_id);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $current_count = $count_result->fetch_assoc()['count'];
                $count_stmt->close();
                
                $photo_count = 0;
                $max_photos = 10 - $current_count;
                
                foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                    if ($photo_count >= $max_photos) break;
                    
                    if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = time() . '_' . basename($_FILES['photos']['name'][$key]);
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $is_primary = 0; // New photos are not primary by default
                            $photo_sql = "INSERT INTO room_photos_$user_id (room_type_id, photo_path, is_primary) VALUES (?, ?, ?)";
                            $photo_stmt = $conn->prepare($photo_sql);
                            $photo_stmt->bind_param("isi", $type_id, $file_path, $is_primary);
                            $photo_stmt->execute();
                            $photo_stmt->close();
                            $photo_count++;
                        }
                    }
                }
            }
            
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
    elseif (isset($_POST['delete_photo'])) {
        $photo_id = intval($_POST['photo_id']);
        $room_type_id = intval($_POST['room_type_id']);
        
        // Get photo path
        $photo_sql = "SELECT photo_path FROM room_photos_$user_id WHERE id = ?";
        $photo_stmt = $conn->prepare($photo_sql);
        $photo_stmt->bind_param("i", $photo_id);
        $photo_stmt->execute();
        $photo_result = $photo_stmt->get_result();
        
        if ($photo_row = $photo_result->fetch_assoc()) {
            $photo_path = $photo_row['photo_path'];
            
            // Delete from database
            $delete_sql = "DELETE FROM room_photos_$user_id WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $photo_id);
            
            if ($delete_stmt->execute()) {
                // Delete file from server
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
                
                // If deleted photo was primary, set a new primary
                $check_primary_sql = "SELECT id FROM room_photos_$user_id WHERE room_type_id = ? AND is_primary = 1";
                $check_stmt = $conn->prepare($check_primary_sql);
                $check_stmt->bind_param("i", $room_type_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows == 0) {
                    // Set first photo as primary
                    $set_primary_sql = "UPDATE room_photos_$user_id SET is_primary = 1 WHERE room_type_id = ? ORDER BY id LIMIT 1";
                    $set_stmt = $conn->prepare($set_primary_sql);
                    $set_stmt->bind_param("i", $room_type_id);
                    $set_stmt->execute();
                    $set_stmt->close();
                }
                $check_stmt->close();
                
                $success_message = "Photo deleted successfully!";
            } else {
                $error_message = "Error deleting photo: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        }
        $photo_stmt->close();
    }
    elseif (isset($_POST['set_primary_photo'])) {
        $photo_id = intval($_POST['photo_id']);
        $room_type_id = intval($_POST['room_type_id']);
        
        // First, set all photos for this room type as not primary
        $reset_sql = "UPDATE room_photos_$user_id SET is_primary = 0 WHERE room_type_id = ?";
        $reset_stmt = $conn->prepare($reset_sql);
        $reset_stmt->bind_param("i", $room_type_id);
        $reset_stmt->execute();
        $reset_stmt->close();
        
        // Then set the selected photo as primary
        $set_sql = "UPDATE room_photos_$user_id SET is_primary = 1 WHERE id = ?";
        $set_stmt = $conn->prepare($set_sql);
        $set_stmt->bind_param("i", $photo_id);
        
        if ($set_stmt->execute()) {
            $success_message = "Primary photo updated successfully!";
        } else {
            $error_message = "Error updating primary photo: " . $set_stmt->error;
        }
        $set_stmt->close();
    }
    elseif (isset($_POST['add_rooms'])) {
        $room_type_id = intval($_POST['room_type_id']);
        $room_count = intval($_POST['room_count']);
        
        if ($room_count > 0) {
            $room_sql = "INSERT INTO rooms_$user_id (room_number, room_type_id, floor) VALUES (?, ?, ?)";
            $room_stmt = $conn->prepare($room_sql);
            
            // Get the highest room number to continue from there
            $max_room_sql = "SELECT MAX(CAST(SUBSTRING(room_number, 2) AS UNSIGNED)) as max_num FROM rooms_$user_id WHERE room_number LIKE 'R%'";
            $max_result = $conn->query($max_room_sql);
            $max_num = 0;
            if ($max_result && $max_row = $max_result->fetch_assoc()) {
                $max_num = $max_row['max_num'] ?: 0;
            }
            
            $added_count = 0;
            for ($i = 1; $i <= $room_count; $i++) {
                $room_number = 'R' . ($max_num + $i);
                $floor = ceil(($max_num + $i) / 10); // Simple floor calculation
                $room_stmt->bind_param("sis", $room_number, $room_type_id, $floor);
                if ($room_stmt->execute()) {
                    $added_count++;
                }
            }
            $room_stmt->close();
            
            $success_message = "Successfully added $added_count rooms to inventory!";
        }
    }
    elseif (isset($_POST['upload_more_photos'])) {
        $room_type_id = intval($_POST['room_type_id']);
        
        if (!empty($_FILES['more_photos']['name'][0])) {
            $upload_dir = "uploads/room_photos/$user_id/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Check current photo count
            $count_sql = "SELECT COUNT(*) as count FROM room_photos_$user_id WHERE room_type_id = ?";
            $count_stmt = $conn->prepare($count_sql);
            $count_stmt->bind_param("i", $room_type_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $current_count = $count_result->fetch_assoc()['count'];
            $count_stmt->close();
            
            $photo_count = 0;
            $max_photos = 10 - $current_count;
            
            foreach ($_FILES['more_photos']['tmp_name'] as $key => $tmp_name) {
                if ($photo_count >= $max_photos) break;
                
                if ($_FILES['more_photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . $key . '_' . basename($_FILES['more_photos']['name'][$key]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $is_primary = 0; // New photos are not primary by default
                        $photo_sql = "INSERT INTO room_photos_$user_id (room_type_id, photo_path, is_primary) VALUES (?, ?, ?)";
                        $photo_stmt = $conn->prepare($photo_sql);
                        $photo_stmt->bind_param("isi", $room_type_id, $file_path, $is_primary);
                        $photo_stmt->execute();
                        $photo_stmt->close();
                        $photo_count++;
                    }
                }
            }
            
            if ($photo_count > 0) {
                $success_message = "Successfully added $photo_count new photos!";
            } else {
                $error_message = "No photos were uploaded or reached maximum limit of 10 photos.";
            }
        }
    }
}

// Get all room types with their amenities and photo counts
$room_types_sql = "
    SELECT rt.*, 
           (SELECT COUNT(*) FROM rooms_$user_id r WHERE r.room_type_id = rt.id) as room_count,
           (SELECT COUNT(*) FROM room_photos_$user_id rp WHERE rp.room_type_id = rt.id) as photo_count
    FROM room_types_$user_id rt 
    ORDER BY rt.name
";
$room_types_result = $conn->query($room_types_sql);
$room_types = [];
if ($room_types_result) {
    $room_types = $room_types_result->fetch_all(MYSQLI_ASSOC);
    
    // Get amenities for each room type
    foreach ($room_types as &$type) {
        $amenities_sql = "
            SELECT a.id, a.name, a.icon 
            FROM amenities_$user_id a
            INNER JOIN room_type_amenities_$user_id rta ON a.id = rta.amenity_id
            WHERE rta.room_type_id = ?
        ";
        $amenities_stmt = $conn->prepare($amenities_sql);
        $amenities_stmt->bind_param("i", $type['id']);
        $amenities_stmt->execute();
        $amenities_result = $amenities_stmt->get_result();
        $type['amenities'] = $amenities_result->fetch_all(MYSQLI_ASSOC);
        $amenities_stmt->close();
        
        // Get photos for each room type
        $photos_sql = "SELECT * FROM room_photos_$user_id WHERE room_type_id = ? ORDER BY is_primary DESC, id ASC";
        $photos_stmt = $conn->prepare($photos_sql);
        $photos_stmt->bind_param("i", $type['id']);
        $photos_stmt->execute();
        $photos_result = $photos_stmt->get_result();
        $type['photos'] = $photos_result->fetch_all(MYSQLI_ASSOC);
        $photos_stmt->close();
    }
}

// Get all amenities for forms
$all_amenities_sql = "SELECT * FROM amenities_$user_id ORDER BY name";
$all_amenities_result = $conn->query($all_amenities_sql);
$all_amenities = [];
if ($all_amenities_result) {
    $all_amenities = $all_amenities_result->fetch_all(MYSQLI_ASSOC);
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
        .amenity-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 5px;
            border-radius: 5px;
            transition: background-color 0.2s;
        }
        .amenity-item:hover {
            background-color: #f8f9fa;
        }
        .amenity-item i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        .photo-thumbnail {
            position: relative;
            display: inline-block;
            margin: 5px;
            border-radius: 5px;
            overflow: hidden;
        }
        .photo-thumbnail img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        .photo-actions {
            position: absolute;
            top: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            border-radius: 0 0 0 5px;
            padding: 2px;
        }
        .photo-actions .btn {
            padding: 2px 5px;
            font-size: 10px;
        }
        .primary-badge {
            position: absolute;
            top: 0;
            left: 0;
            background: #28a745;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 0 0 5px 0;
        }
        .photo-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .photo-upload-area:hover {
            border-color: #007bff;
            background: #e9ecef;
        }
        .photo-preview {
            display: flex;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .preview-item {
            position: relative;
            margin: 5px;
        }
        .preview-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        .remove-preview {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
        }
        .inventory-badge {
            font-size: 12px;
        }
        .select-all-btn {
            margin-bottom: 10px;
        }
        .more-photos-container {
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
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
                                <h5 class="card-title">Total Rooms</h5>
                                <h3 class="card-text">
                                    <?php 
                                    $total_rooms = 0;
                                    foreach ($room_types as $type) {
                                        $total_rooms += $type['room_count'];
                                    }
                                    echo $total_rooms;
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Avg. Rate</h5>
                                <h3 class="card-text">
                                    ₹<?php echo count($room_types) > 0 ? number_format(array_sum(array_column($room_types, 'base_rate')) / count($room_types)) : '0.00'; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h5 class="card-title">Max Occupancy</h5>
                                <h3 class="card-text">
                                    <?php echo count($room_types) > 0 ? max(array_column($room_types, 'max_occupancy')) : '0'; ?>
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
                                                    <th>Rooms</th>
                                                    <th>Amenities</th>
                                                    <th>Photos</th>
                                                    <th>Last Updated</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($room_types as $type): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($type['name']); ?></strong>
                                                            <?php if ($type['description']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($type['description'], 0, 50)); ?><?php echo strlen($type['description']) > 50 ? '...' : ''; ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong>₹<?php echo number_format($type['base_rate']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-primary"><?php echo $type['max_occupancy']; ?> persons</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success inventory-badge"><?php echo $type['room_count']; ?> rooms</span>
                                                            <button class="btn btn-sm btn-outline-primary add-rooms-btn" 
                                                                    data-type-id="<?php echo $type['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($type['name']); ?>">
                                                                <i class="fas fa-plus"></i>
                                                            </button>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($type['amenities'])): ?>
                                                                <div class="amenities-preview">
                                                                    <?php 
                                                                    $amenity_count = count($type['amenities']);
                                                                    $display_count = min(3, $amenity_count);
                                                                    for ($i = 0; $i < $display_count; $i++): 
                                                                    ?>
                                                                        <span class="badge bg-light text-dark mb-1">
                                                                            <i class="<?php echo $type['amenities'][$i]['icon']; ?> me-1"></i>
                                                                            <?php echo htmlspecialchars($type['amenities'][$i]['name']); ?>
                                                                        </span>
                                                                        <?php if ($i < $display_count - 1) echo '<br>'; ?>
                                                                    <?php endfor; ?>
                                                                    <?php if ($amenity_count > 3): ?>
                                                                        <span class="badge bg-secondary">+<?php echo $amenity_count - 3; ?> more</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">No amenities</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($type['photo_count'] > 0): ?>
                                                                <div class="photo-thumbnails">
                                                                    <?php 
                                                                    $primary_photo = null;
                                                                    $other_photos = [];
                                                                    foreach ($type['photos'] as $photo) {
                                                                        if ($photo['is_primary']) {
                                                                            $primary_photo = $photo;
                                                                        } else {
                                                                            $other_photos[] = $photo;
                                                                        }
                                                                    }
                                                                    $display_photos = array_slice($other_photos, 0, 2);
                                                                    if ($primary_photo) {
                                                                        array_unshift($display_photos, $primary_photo);
                                                                    }
                                                                    ?>
                                                                    <?php foreach ($display_photos as $photo): ?>
                                                                        <div class="photo-thumbnail">
                                                                            <img src="<?php echo $photo['photo_path']; ?>" alt="Room photo">
                                                                            <?php if ($photo['is_primary']): ?>
                                                                                <span class="primary-badge">Primary</span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                    <?php if ($type['photo_count'] > 3): ?>
                                                                        <div class="photo-thumbnail bg-light d-flex align-items-center justify-content-center">
                                                                            <span class="text-muted">+<?php echo $type['photo_count'] - 3; ?></span>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">No photos</span>
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
                                                                        data-description="<?php echo htmlspecialchars($type['description'] ?? ''); ?>"
                                                                        data-base-rate="<?php echo $type['base_rate']; ?>"
                                                                        data-max-occupancy="<?php echo $type['max_occupancy']; ?>"
                                                                        data-amenities="<?php 
                                                                            $amenity_ids = array_column($type['amenities'], 'id');
                                                                            echo htmlspecialchars(json_encode($amenity_ids));
                                                                        ?>">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-info manage-photos" 
                                                                        data-type-id="<?php echo $type['id']; ?>"
                                                                        data-name="<?php echo htmlspecialchars($type['name']); ?>">
                                                                    <i class="fas fa-images"></i>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="addRoomTypeForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Room Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
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
                                    <label class="form-label">Initial Room Inventory</label>
                                    <input type="number" class="form-control" name="room_count" min="0" value="0">
                                    <small class="text-muted">Number of rooms of this type to add initially</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" 
                                              placeholder="Room type description..."></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Photos (Max 10)</label>
                                    <div class="photo-upload-area" id="photoUploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="mb-1">Click to upload photos</p>
                                        <small class="text-muted">JPEG, PNG, JPG (Max 5MB each)</small>
                                        <input type="file" name="photos[]" id="photos" multiple accept="image/*" style="display: none;">
                                    </div>
                                    <div class="photo-preview" id="photoPreview"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Amenities</label>
                                    <div class="d-flex gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary select-all-btn" id="selectAllAmenities">
                                            <i class="fas fa-check-square me-1"></i> Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary select-all-btn" id="deselectAllAmenities">
                                            <i class="fas fa-times-circle me-1"></i> Deselect All
                                        </button>
                                    </div>
                                    <div class="amenities-list" style="max-height: 200px; overflow-y: auto;">
                                        <div class="row">
                                            <?php foreach ($all_amenities as $amenity): ?>
                                                <div class="col-md-6">
                                                    <div class="amenity-item">
                                                        <input type="checkbox" name="amenities[]" value="<?php echo $amenity['id']; ?>" 
                                                               id="amenity_<?php echo $amenity['id']; ?>" class="amenity-checkbox">
                                                        <label for="amenity_<?php echo $amenity['id']; ?>" class="mb-0 ms-2">
                                                            <i class="<?php echo $amenity['icon']; ?>"></i>
                                                            <?php echo htmlspecialchars($amenity['name']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editRoomTypeForm" enctype="multipart/form-data">
                    <input type="hidden" name="type_id" id="edit_type_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Room Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
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
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Add More Photos (Max 10 total)</label>
                                    <div class="photo-upload-area" id="editPhotoUploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="mb-1">Click to upload additional photos</p>
                                        <small class="text-muted">JPEG, PNG, JPG (Max 5MB each)</small>
                                        <input type="file" name="photos[]" id="edit_photos" multiple accept="image/*" style="display: none;">
                                    </div>
                                    <div class="photo-preview" id="editPhotoPreview"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Amenities</label>
                                    <div class="d-flex gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary select-all-btn" id="editSelectAllAmenities">
                                            <i class="fas fa-check-square me-1"></i> Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary select-all-btn" id="editDeselectAllAmenities">
                                            <i class="fas fa-times-circle me-1"></i> Deselect All
                                        </button>
                                    </div>
                                    <div class="amenities-list" style="max-height: 200px; overflow-y: auto;">
                                        <div class="row">
                                            <?php foreach ($all_amenities as $amenity): ?>
                                                <div class="col-md-6">
                                                    <div class="amenity-item">
                                                        <input type="checkbox" name="amenities[]" value="<?php echo $amenity['id']; ?>" 
                                                               id="edit_amenity_<?php echo $amenity['id']; ?>" class="amenity-checkbox">
                                                        <label for="edit_amenity_<?php echo $amenity['id']; ?>" class="mb-0 ms-2">
                                                            <i class="<?php echo $amenity['icon']; ?>"></i>
                                                            <?php echo htmlspecialchars($amenity['name']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

    <!-- Manage Photos Modal -->
    <div class="modal fade" id="managePhotosModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Photos for <span id="photoRoomTypeName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="photosContainer" class="d-flex flex-wrap gap-3 mb-4">
                        <!-- Photos will be loaded here via AJAX -->
                    </div>
                    
                    <div class="more-photos-container">
                        <h6>Add More Photos</h6>
                        <form method="POST" id="addMorePhotosForm" enctype="multipart/form-data">
                            <input type="hidden" name="room_type_id" id="more_photos_room_type_id">
                            <div class="mb-3">
                                <div class="photo-upload-area" id="managePhotoUploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="mb-1">Click to upload additional photos</p>
                                    <small class="text-muted">JPEG, PNG, JPG (Max 5MB each)</small>
                                    <input type="file" name="more_photos[]" id="manage_photos" multiple accept="image/*" style="display: none;">
                                </div>
                                <div class="photo-preview" id="managePhotoPreview"></div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-success" name="upload_more_photos">
                                    <i class="fas fa-upload me-1"></i> Upload Photos
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Rooms Modal -->
    <div class="modal fade" id="addRoomsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="addRoomsForm">
                    <input type="hidden" name="room_type_id" id="add_rooms_type_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Rooms to <span id="addRoomsTypeName"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Number of Rooms to Add</label>
                            <input type="number" class="form-control" name="room_count" min="1" max="50" value="1" required>
                            <small class="text-muted">Rooms will be automatically numbered (e.g., R101, R102, etc.)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_rooms">Add Rooms</button>
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
        let currentRoomTypeId = null;
        
        // Edit room type functionality
        $(document).on('click', '.edit-room-type', function() {
            console.log('Edit button clicked');
            
            const typeId = $(this).data('type-id');
            const name = $(this).data('name');
            const description = $(this).data('description') || '';
            const baseRate = $(this).data('base-rate');
            const maxOccupancy = $(this).data('max-occupancy');
            const amenitiesData = $(this).data('amenities');
            
            // Set basic form values
            $('#edit_type_id').val(typeId);
            $('#edit_name').val(name);
            $('#edit_description').val(description);
            $('#edit_base_rate').val(baseRate);
            $('#edit_max_occupancy').val(maxOccupancy);
            
            // Reset all checkboxes first
            $('#editRoomTypeModal .amenity-checkbox').prop('checked', false);
            
            // Parse and set amenities
            try {
                let amenities = [];
                if (amenitiesData && amenitiesData !== '[]') {
                    if (typeof amenitiesData === 'string') {
                        amenities = JSON.parse(amenitiesData);
                    } else {
                        amenities = amenitiesData;
                    }
                }
                
                console.log('Parsed amenities:', amenities);
                
                // Check the amenities that this room type has
                amenities.forEach(amenityId => {
                    const checkbox = $(`#edit_amenity_${amenityId}`);
                    if (checkbox.length) {
                        checkbox.prop('checked', true);
                    }
                });
            } catch (error) {
                console.error('Error parsing amenities:', error);
            }
            
            // Clear photo preview
            $('#editPhotoPreview').empty();
            $('#edit_photos').val('');
            
            $('#editRoomTypeModal').modal('show');
        });

        // Delete room type functionality
        $(document).on('click', '.delete-room-type', function() {
            const typeId = $(this).data('type-id');
            const typeName = $(this).data('name');
            
            $('#delete_type_id').val(typeId);
            $('#delete_type_name').text(typeName);
            
            $('#deleteRoomTypeModal').modal('show');
        });

        // Manage photos functionality
        $(document).on('click', '.manage-photos', function() {
            const typeId = $(this).data('type-id');
            const typeName = $(this).data('name');
            
            currentRoomTypeId = typeId;
            $('#photoRoomTypeName').text(typeName);
            $('#more_photos_room_type_id').val(typeId);
            
            // Load photos via AJAX
            loadRoomTypePhotos(typeId);
            
            $('#managePhotosModal').modal('show');
        });

        // Add rooms functionality
        $(document).on('click', '.add-rooms-btn', function() {
            const typeId = $(this).data('type-id');
            const typeName = $(this).data('name');
            
            $('#add_rooms_type_id').val(typeId);
            $('#addRoomsTypeName').text(typeName);
            
            $('#addRoomsModal').modal('show');
        });

        // Photo upload area click handlers
        $('#photoUploadArea').click(function() {
            $('#photos').click();
        });
        
        $('#editPhotoUploadArea').click(function() {
            $('#edit_photos').click();
        });
        
        $('#managePhotoUploadArea').click(function() {
            $('#manage_photos').click();
        });

        // Photo preview functionality - FIXED VERSION
        function handlePhotoPreview(input, previewContainer) {
            const preview = $(previewContainer);
            preview.empty();
            
            if (input.files) {
                const files = Array.from(input.files);
                
                files.forEach((file, index) => {
                    if (file.type.match('image.*')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const previewItem = $('<div class="preview-item">');
                            previewItem.html(`
                                <img src="${e.target.result}" alt="Preview">
                                <div class="remove-preview" data-index="${index}">&times;</div>
                            `);
                            preview.append(previewItem);
                        };
                        
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Remove preview item
            preview.off('click', '.remove-preview').on('click', '.remove-preview', function() {
                const index = $(this).data('index');
                $(this).parent().remove();
                
                // Create a new FileList without the removed file
                const dt = new DataTransfer();
                const files = input.files;
                
                for (let i = 0; i < files.length; i++) {
                    if (i !== index) {
                        dt.items.add(files[i]);
                    }
                }
                
                input.files = dt.files;
            });
        }

        // File input change handlers
        $('#photos').change(function() {
            handlePhotoPreview(this, '#photoPreview');
        });
        
        $('#edit_photos').change(function() {
            handlePhotoPreview(this, '#editPhotoPreview');
        });
        
        $('#manage_photos').change(function() {
            handlePhotoPreview(this, '#managePhotoPreview');
        });

        // Select all amenities functionality
        $(document).on('click', '#selectAllAmenities', function() {
            console.log('Select All clicked in Add modal');
            $('#addRoomTypeModal .amenity-checkbox').prop('checked', true);
        });
        
        $(document).on('click', '#editSelectAllAmenities', function() {
            console.log('Select All clicked in Edit modal');
            $('#editRoomTypeModal .amenity-checkbox').prop('checked', true);
        });

        // Deselect all functionality
        $(document).on('click', '#deselectAllAmenities', function() {
            $('#addRoomTypeModal .amenity-checkbox').prop('checked', false);
        });
        
        $(document).on('click', '#editDeselectAllAmenities', function() {
            $('#editRoomTypeModal .amenity-checkbox').prop('checked', false);
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
                return;
            }
            
            const maxOccupancy = $(this).find('input[name="max_occupancy"]').val();
            if (maxOccupancy < 1) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Occupancy',
                    text: 'Max occupancy must be at least 1.'
                });
                return;
            }
            
            // Check photo count
            const photoInput = $(this).find('input[type="file"]')[0];
            if (photoInput && photoInput.files.length > 10) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Too Many Photos',
                    text: 'You can upload a maximum of 10 photos per room type.'
                });
                return;
            }
        });

        // Load room type photos for management
        function loadRoomTypePhotos(roomTypeId) {
            $.ajax({
                url: 'get_room_photos.php',
                type: 'GET',
                data: { room_type_id: roomTypeId },
                success: function(response) {
                    $('#photosContainer').html(response);
                },
                error: function(xhr, status, error) {
                    console.error('Error loading photos:', error);
                    $('#photosContainer').html('<p class="text-danger">Error loading photos: ' + error + '</p>');
                }
            });
        }

        // Set primary photo
        $(document).on('click', '.set-primary-btn', function() {
            const photoId = $(this).data('photo-id');
            const roomTypeId = currentRoomTypeId;
            
            $.ajax({
                url: 'set_primary_photo.php',
                type: 'POST',
                data: { 
                    photo_id: photoId,
                    room_type_id: roomTypeId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadRoomTypePhotos(roomTypeId);
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'Primary photo updated successfully!'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Error updating primary photo'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error updating primary photo: ' + error
                    });
                }
            });
        });

        // Delete photo
        $(document).on('click', '.delete-photo-btn', function() {
            const photoId = $(this).data('photo-id');
            const roomTypeId = currentRoomTypeId;
            
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'delete_room_photo.php',
                        type: 'POST',
                        data: { 
                            photo_id: photoId,
                            room_type_id: roomTypeId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                loadRoomTypePhotos(roomTypeId);
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: 'Photo has been deleted.'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Error deleting photo'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error deleting photo: ' + error
                            });
                        }
                    });
                }
            });
        });

        // Add More Photos form submission
        $('#addMorePhotosForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Reload the page to show success message and updated photos
                    location.reload();
                },
                error: function(xhr, status, error) {
                    console.error('Error uploading photos:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: 'Error uploading photos: ' + error
                    });
                }
            });
        });

        // Search functionality
        $('#searchRoomTypes').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('#roomTypesTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Clear photo preview when modal is closed
        $('.modal').on('hidden.bs.modal', function() {
            $('#photoPreview').empty();
            $('#editPhotoPreview').empty();
            $('#managePhotoPreview').empty();
        });
    });
    </script>
</body>
</html>
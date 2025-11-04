<?php
// manage-guests.php
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
$user_name = $_SESSION['name'] ?? '';
$success_message = '';
$error_message = '';

// Check if guests table exists, if not create it
$check_table_sql = "SHOW TABLES LIKE 'guests_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    // Create guests table
    $create_table_sql = "
        CREATE TABLE `guests_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `address` TEXT,
            `id_proof_type` VARCHAR(50) DEFAULT NULL,
            `id_proof_number` VARCHAR(100) DEFAULT NULL,
            `id_proof_image` VARCHAR(255) DEFAULT NULL,
            `loyalty_points` INT(11) DEFAULT 0,
            `preferences` TEXT,
            `total_stays` INT(11) DEFAULT 0,
            `total_spent` DECIMAL(12,2) DEFAULT 0.00,
            `last_visit` DATE DEFAULT NULL,
            `is_blacklisted` TINYINT(1) DEFAULT 0,
            `blacklist_reason` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `phone` (`phone`),
            KEY `email` (`email`),
            KEY `name` (`name`),
            KEY `is_blacklisted` (`is_blacklisted`),
            KEY `last_visit` (`last_visit`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($create_table_sql) === FALSE) {
        $error_message = "Error creating guests table: " . $conn->error;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_guest'])) {
        // Add new guest
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $id_proof_type = trim($_POST['id_proof_type']);
        $id_proof_number = trim($_POST['id_proof_number']);
        $preferences = trim($_POST['preferences']);

        // Validate required fields
        if (empty($name) || empty($phone)) {
            $error_message = "Name and Phone are required fields.";
        } else {
            // Check if phone already exists
            $check_sql = "SELECT id FROM guests_$user_id WHERE phone = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $phone);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $error_message = "Guest with this phone number already exists.";
            } else {
                // Insert new guest
                $insert_sql = "INSERT INTO guests_$user_id 
                              (name, phone, email, address, id_proof_type, id_proof_number, preferences) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sssssss", $name, $phone, $email, $address, $id_proof_type, $id_proof_number, $preferences);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Guest added successfully!";
                    // Clear form fields
                    $_POST = array();
                } else {
                    $error_message = "Error adding guest: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['update_guest'])) {
        // Update guest
        $guest_id = $_POST['guest_id'];
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $id_proof_type = trim($_POST['id_proof_type']);
        $id_proof_number = trim($_POST['id_proof_number']);
        $preferences = trim($_POST['preferences']);

        $update_sql = "UPDATE guests_$user_id SET 
                      name = ?, phone = ?, email = ?, address = ?, 
                      id_proof_type = ?, id_proof_number = ?, preferences = ?
                      WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssssssi", $name, $phone, $email, $address, $id_proof_type, $id_proof_number, $preferences, $guest_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Guest updated successfully!";
        } else {
            $error_message = "Error updating guest: " . $update_stmt->error;
        }
        $update_stmt->close();
    } elseif (isset($_POST['blacklist_guest'])) {
        // Blacklist guest
        $guest_id = $_POST['guest_id'];
        $reason = trim($_POST['blacklist_reason']);
        
        $blacklist_sql = "UPDATE guests_$user_id SET is_blacklisted = 1, blacklist_reason = ? WHERE id = ?";
        $blacklist_stmt = $conn->prepare($blacklist_sql);
        $blacklist_stmt->bind_param("si", $reason, $guest_id);
        
        if ($blacklist_stmt->execute()) {
            $success_message = "Guest blacklisted successfully!";
        } else {
            $error_message = "Error blacklisting guest: " . $blacklist_stmt->error;
        }
        $blacklist_stmt->close();
    } elseif (isset($_POST['remove_blacklist'])) {
        // Remove from blacklist
        $guest_id = $_POST['guest_id'];
        
        $remove_sql = "UPDATE guests_$user_id SET is_blacklisted = 0, blacklist_reason = NULL WHERE id = ?";
        $remove_stmt = $conn->prepare($remove_sql);
        $remove_stmt->bind_param("i", $guest_id);
        
        if ($remove_stmt->execute()) {
            $success_message = "Guest removed from blacklist!";
        } else {
            $error_message = "Error removing from blacklist: " . $remove_stmt->error;
        }
        $remove_stmt->close();
    } elseif (isset($_POST['delete_guest'])) {
        // Delete guest
        $guest_id = $_POST['guest_id'];
        
        // Check if guest has any bookings
        $check_bookings_sql = "SELECT COUNT(*) as booking_count FROM bookings_$user_id WHERE guest_id = ?";
        $check_stmt = $conn->prepare($check_bookings_sql);
        $check_stmt->bind_param("i", $guest_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($check_result['booking_count'] > 0) {
            $error_message = "Cannot delete guest with existing bookings. You can blacklist the guest instead.";
        } else {
            $delete_sql = "DELETE FROM guests_$user_id WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $guest_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Guest deleted successfully!";
            } else {
                $error_message = "Error deleting guest: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        }
    }
}

// Get search parameters
$search_name = $_GET['search_name'] ?? '';
$search_phone = $_GET['search_phone'] ?? '';
$blacklisted_only = $_GET['blacklisted'] ?? '';

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search_name)) {
    $where_conditions[] = "name LIKE ?";
    $params[] = "%$search_name%";
    $types .= 's';
}

if (!empty($search_phone)) {
    $where_conditions[] = "phone LIKE ?";
    $params[] = "%$search_phone%";
    $types .= 's';
}

if ($blacklisted_only === '1') {
    $where_conditions[] = "is_blacklisted = 1";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get guests with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM guests_$user_id $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_count / $limit);

// Get guests data
$guests_sql = "SELECT * FROM guests_$user_id $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$guests_stmt = $conn->prepare($guests_sql);

// Add limit and offset to params
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

if (!empty($params)) {
    $guests_stmt->bind_param($types, ...$params);
}
$guests_stmt->execute();
$guests_result = $guests_stmt->get_result();
$guests = $guests_result->fetch_all(MYSQLI_ASSOC);
$guests_stmt->close();

// Get guest statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_guests,
        SUM(is_blacklisted) as blacklisted_guests,
        SUM(total_stays) as total_stays,
        AVG(total_spent) as avg_spent
    FROM guests_$user_id
";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$stats_result->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Manage Guests</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .guest-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .guest-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .guest-card.blacklisted {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .guest-card.vip {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .loyalty-badge {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: #000;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        .blacklist-badge {
            background: #dc3545;
            color: white;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        .action-buttons .btn {
            margin: 2px;
            font-size: 12px;
        }
        .search-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <!-- Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">Manage Guests</h4>
                            <div class="page-title-right">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGuestModal">
                                    <i class="fas fa-plus"></i> Add New Guest
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $stats['total_guests'] ?? 0; ?></h3>
                            <p>Total Guests</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $stats['blacklisted_guests'] ?? 0; ?></h3>
                            <p>Blacklisted</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $stats['total_stays'] ?? 0; ?></h3>
                            <p>Total Stays</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3>₹<?php echo number_format($stats['avg_spent'] ?? 0, 2); ?></h3>
                            <p>Avg. Spending</p>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search Box -->
                <div class="search-box">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search_name" placeholder="Search by name..." value="<?php echo htmlspecialchars($search_name); ?>">
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search_phone" placeholder="Search by phone..." value="<?php echo htmlspecialchars($search_phone); ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-control" name="blacklisted">
                                <option value="">All Guests</option>
                                <option value="1" <?php echo $blacklisted_only === '1' ? 'selected' : ''; ?>>Blacklisted Only</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>
                </div>

                <!-- Guests List -->
                <div class="row">
                    <?php if (empty($guests)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <h5>No guests found</h5>
                                <p>Start by adding your first guest using the "Add New Guest" button.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($guests as $guest): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card guest-card <?php echo $guest['is_blacklisted'] ? 'blacklisted' : ''; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($guest['name']); ?></h5>
                                            <div>
                                                <?php if ($guest['is_blacklisted']): ?>
                                                    <span class="blacklist-badge">Blacklisted</span>
                                                <?php endif; ?>
                                                <?php if ($guest['loyalty_points'] > 100): ?>
                                                    <span class="loyalty-badge">VIP</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="guest-info mb-3">
                                            <p class="mb-1">
                                                <i class="fas fa-phone text-muted me-2"></i>
                                                <?php echo htmlspecialchars($guest['phone']); ?>
                                            </p>
                                            <?php if ($guest['email']): ?>
                                                <p class="mb-1">
                                                    <i class="fas fa-envelope text-muted me-2"></i>
                                                    <?php echo htmlspecialchars($guest['email']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($guest['last_visit']): ?>
                                                <p class="mb-1">
                                                    <i class="fas fa-calendar text-muted me-2"></i>
                                                    Last Visit: <?php echo date('M j, Y', strtotime($guest['last_visit'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($guest['total_stays'] > 0): ?>
                                                <p class="mb-1">
                                                    <i class="fas fa-bed text-muted me-2"></i>
                                                    <?php echo $guest['total_stays']; ?> stays
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($guest['loyalty_points'] > 0): ?>
                                                <p class="mb-1">
                                                    <i class="fas fa-star text-muted me-2"></i>
                                                    <?php echo $guest['loyalty_points']; ?> loyalty points
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary edit-guest" 
                                                    data-guest-id="<?php echo $guest['id']; ?>"
                                                    data-guest-name="<?php echo htmlspecialchars($guest['name']); ?>"
                                                    data-guest-phone="<?php echo htmlspecialchars($guest['phone']); ?>"
                                                    data-guest-email="<?php echo htmlspecialchars($guest['email']); ?>"
                                                    data-guest-address="<?php echo htmlspecialchars($guest['address']); ?>"
                                                    data-guest-id-proof-type="<?php echo htmlspecialchars($guest['id_proof_type']); ?>"
                                                    data-guest-id-proof-number="<?php echo htmlspecialchars($guest['id_proof_number']); ?>"
                                                    data-guest-preferences="<?php echo htmlspecialchars($guest['preferences']); ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            
                                            <?php if ($guest['is_blacklisted']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="guest_id" value="<?php echo $guest['id']; ?>">
                                                    <button type="submit" name="remove_blacklist" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-check"></i> Unblock
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-warning blacklist-guest" 
                                                        data-guest-id="<?php echo $guest['id']; ?>"
                                                        data-guest-name="<?php echo htmlspecialchars($guest['name']); ?>">
                                                    <i class="fas fa-ban"></i> Blacklist
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-outline-danger delete-guest" 
                                                    data-guest-id="<?php echo $guest['id']; ?>"
                                                    data-guest-name="<?php echo htmlspecialchars($guest['name']); ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search_name=<?php echo urlencode($search_name); ?>&search_phone=<?php echo urlencode($search_phone); ?>&blacklisted=<?php echo $blacklisted_only; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Add Guest Modal -->
    <div class="modal fade" id="addGuestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Guest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name *</label>
                                    <input type="text" class="form-control" name="name" value="<?php echo $_POST['name'] ?? ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone *</label>
                                    <input type="text" class="form-control" name="phone" value="<?php echo $_POST['phone'] ?? ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo $_POST['email'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ID Proof Type</label>
                                    <select class="form-control" name="id_proof_type">
                                        <option value="">Select ID Type</option>
                                        <option value="Aadhar Card" <?php echo ($_POST['id_proof_type'] ?? '') === 'Aadhar Card' ? 'selected' : ''; ?>>Aadhar Card</option>
                                        <option value="Passport" <?php echo ($_POST['id_proof_type'] ?? '') === 'Passport' ? 'selected' : ''; ?>>Passport</option>
                                        <option value="Driver License" <?php echo ($_POST['id_proof_type'] ?? '') === 'Driver License' ? 'selected' : ''; ?>>Driver License</option>
                                        <option value="Voter ID" <?php echo ($_POST['id_proof_type'] ?? '') === 'Voter ID' ? 'selected' : ''; ?>>Voter ID</option>
                                        <option value="PAN Card" <?php echo ($_POST['id_proof_type'] ?? '') === 'PAN Card' ? 'selected' : ''; ?>>PAN Card</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ID Proof Number</label>
                                    <input type="text" class="form-control" name="id_proof_number" value="<?php echo $_POST['id_proof_number'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="2"><?php echo $_POST['address'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Preferences/Special Requirements</label>
                                    <textarea class="form-control" name="preferences" rows="2" placeholder="e.g., Non-smoking room, Extra pillows, etc."><?php echo $_POST['preferences'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_guest" class="btn btn-primary">Add Guest</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Guest Modal -->
    <div class="modal fade" id="editGuestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Guest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="guest_id" id="edit_guest_id">
                    <div class="modal-body">
                        <!-- Same form structure as add guest -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name *</label>
                                    <input type="text" class="form-control" name="name" id="edit_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone *</label>
                                    <input type="text" class="form-control" name="phone" id="edit_phone" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="edit_email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ID Proof Type</label>
                                    <select class="form-control" name="id_proof_type" id="edit_id_proof_type">
                                        <option value="">Select ID Type</option>
                                        <option value="Aadhar Card">Aadhar Card</option>
                                        <option value="Passport">Passport</option>
                                        <option value="Driver License">Driver License</option>
                                        <option value="Voter ID">Voter ID</option>
                                        <option value="PAN Card">PAN Card</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ID Proof Number</label>
                                    <input type="text" class="form-control" name="id_proof_number" id="edit_id_proof_number">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Preferences/Special Requirements</label>
                                    <textarea class="form-control" name="preferences" id="edit_preferences" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_guest" class="btn btn-primary">Update Guest</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Blacklist Guest Modal -->
    <div class="modal fade" id="blacklistGuestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Blacklist Guest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="guest_id" id="blacklist_guest_id">
                    <div class="modal-body">
                        <p>Are you sure you want to blacklist <strong id="blacklist_guest_name"></strong>?</p>
                        <div class="mb-3">
                            <label class="form-label">Reason for blacklisting:</label>
                            <textarea class="form-control" name="blacklist_reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="blacklist_guest" class="btn btn-warning">Blacklist Guest</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
    $(document).ready(function() {
        // Edit guest modal
        $('.edit-guest').click(function() {
            const guestId = $(this).data('guest-id');
            const guestName = $(this).data('guest-name');
            const guestPhone = $(this).data('guest-phone');
            const guestEmail = $(this).data('guest-email');
            const guestAddress = $(this).data('guest-address');
            const guestIdProofType = $(this).data('guest-id-proof-type');
            const guestIdProofNumber = $(this).data('guest-id-proof-number');
            const guestPreferences = $(this).data('guest-preferences');

            $('#edit_guest_id').val(guestId);
            $('#edit_name').val(guestName);
            $('#edit_phone').val(guestPhone);
            $('#edit_email').val(guestEmail);
            $('#edit_address').val(guestAddress);
            $('#edit_id_proof_type').val(guestIdProofType);
            $('#edit_id_proof_number').val(guestIdProofNumber);
            $('#edit_preferences').val(guestPreferences);

            $('#editGuestModal').modal('show');
        });

        // Blacklist guest modal
        $('.blacklist-guest').click(function() {
            const guestId = $(this).data('guest-id');
            const guestName = $(this).data('guest-name');

            $('#blacklist_guest_id').val(guestId);
            $('#blacklist_guest_name').text(guestName);

            $('#blacklistGuestModal').modal('show');
        });

        // Delete guest confirmation
        $('.delete-guest').click(function() {
            const guestId = $(this).data('guest-id');
            const guestName = $(this).data('guest-name');

            Swal.fire({
                title: 'Delete Guest?',
                text: `Are you sure you want to delete ${guestName}? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';

                    const guestIdInput = document.createElement('input');
                    guestIdInput.type = 'hidden';
                    guestIdInput.name = 'guest_id';
                    guestIdInput.value = guestId;

                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_guest';

                    form.appendChild(guestIdInput);
                    form.appendChild(deleteInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // Auto-close alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });
    </script>
</body>
</html>
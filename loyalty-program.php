<?php
// loyalty-program.php
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

// Check if loyalty tables exist, if not create them
$check_table_sql = "SHOW TABLES LIKE 'loyalty_programs_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    // Create loyalty tables
    createLoyaltyTables($user_id, $conn);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_program'])) {
        $program_name = trim($_POST['program_name']);
        $points_per_booking = floatval($_POST['points_per_booking']);
        $points_per_amount = floatval($_POST['points_per_amount']);
        $min_redemption_points = intval($_POST['min_redemption_points']);
        $reward_type = $_POST['reward_type'];
        $reward_value = floatval($_POST['reward_value']);
        $status = $_POST['status'];
        
        if (!empty($program_name)) {
            $sql = "INSERT INTO loyalty_programs_$user_id 
                    (program_name, points_per_booking, points_per_amount, min_redemption_points, 
                     reward_type, reward_value, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sddidsi", $program_name, $points_per_booking, $points_per_amount, 
                             $min_redemption_points, $reward_type, $reward_value, $status);
            
            if ($stmt->execute()) {
                $success_message = "Loyalty program created successfully!";
            } else {
                $error_message = "Error creating loyalty program: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Please enter a program name";
        }
    }
    
    if (isset($_POST['update_program'])) {
        $program_id = intval($_POST['program_id']);
        $program_name = trim($_POST['program_name']);
        $points_per_booking = floatval($_POST['points_per_booking']);
        $points_per_amount = floatval($_POST['points_per_amount']);
        $min_redemption_points = intval($_POST['min_redemption_points']);
        $reward_type = $_POST['reward_type'];
        $reward_value = floatval($_POST['reward_value']);
        $status = $_POST['status'];
        
        $sql = "UPDATE loyalty_programs_$user_id SET 
                program_name = ?, points_per_booking = ?, points_per_amount = ?, 
                min_redemption_points = ?, reward_type = ?, reward_value = ?, status = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddidsii", $program_name, $points_per_booking, $points_per_amount,
                         $min_redemption_points, $reward_type, $reward_value, $status, $program_id);
        
        if ($stmt->execute()) {
            $success_message = "Loyalty program updated successfully!";
        } else {
            $error_message = "Error updating loyalty program: " . $conn->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['add_points'])) {
        $guest_phone = trim($_POST['guest_phone']);
        $points = intval($_POST['points']);
        $reason = trim($_POST['reason']);
        
        // Find or create guest
        $guest_id = findOrCreateGuest($guest_phone, $user_id, $conn);
        
        if ($guest_id) {
            // Add points transaction
            $sql = "INSERT INTO loyalty_points_$user_id 
                    (guest_id, points, transaction_type, reason, created_at) 
                    VALUES (?, ?, 'earned', ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $guest_id, $points, $reason);
            
            if ($stmt->execute()) {
                // Update guest's total points
                updateGuestPoints($guest_id, $user_id, $conn);
                $success_message = "Points added successfully!";
            } else {
                $error_message = "Error adding points: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Error finding or creating guest";
        }
    }
    
    if (isset($_POST['redeem_points'])) {
        $guest_phone = trim($_POST['guest_phone']);
        $points = intval($_POST['points']);
        $reason = trim($_POST['reason']);
        
        $guest_id = findGuestByPhone($guest_phone, $user_id, $conn);
        
        if ($guest_id) {
            // Check if guest has enough points
            $guest_points = getGuestPoints($guest_id, $user_id, $conn);
            
            if ($guest_points >= $points) {
                // Add redemption transaction
                $sql = "INSERT INTO loyalty_points_$user_id 
                        (guest_id, points, transaction_type, reason, created_at) 
                        VALUES (?, ?, 'redeemed', ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $guest_id, $points, $reason);
                
                if ($stmt->execute()) {
                    // Update guest's total points
                    updateGuestPoints($guest_id, $user_id, $conn);
                    $success_message = "Points redeemed successfully!";
                } else {
                    $error_message = "Error redeeming points: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Guest doesn't have enough points. Available: $guest_points";
            }
        } else {
            $error_message = "Guest not found";
        }
    }
}

// Fetch loyalty programs
$programs_sql = "SELECT * FROM loyalty_programs_$user_id ORDER BY created_at DESC";
$programs_result = $conn->query($programs_sql);
$loyalty_programs = [];
if ($programs_result) {
    $loyalty_programs = $programs_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch loyalty members with points
$members_sql = "SELECT g.*, lp.total_points 
                FROM guests_$user_id g 
                LEFT JOIN loyalty_points_summary_$user_id lp ON g.id = lp.guest_id 
                WHERE lp.total_points > 0 
                ORDER BY lp.total_points DESC 
                LIMIT 50";
$members_result = $conn->query($members_sql);
$loyalty_members = [];
if ($members_result) {
    $loyalty_members = $members_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch recent point transactions
$transactions_sql = "SELECT lp.*, g.name, g.phone 
                     FROM loyalty_points_$user_id lp 
                     JOIN guests_$user_id g ON lp.guest_id = g.id 
                     ORDER BY lp.created_at DESC 
                     LIMIT 20";
$transactions_result = $conn->query($transactions_sql);
$recent_transactions = [];
if ($transactions_result) {
    $recent_transactions = $transactions_result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

// Helper functions
function createLoyaltyTables($user_id, $conn) {
    $tables = [
        "loyalty_programs_$user_id" => "
            CREATE TABLE IF NOT EXISTS `loyalty_programs_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `program_name` VARCHAR(255) NOT NULL,
                `points_per_booking` DECIMAL(10,2) DEFAULT 0,
                `points_per_amount` DECIMAL(10,2) DEFAULT 0,
                `min_redemption_points` INT(11) DEFAULT 100,
                `reward_type` ENUM('discount', 'free_night', 'upgrade', 'cashback') DEFAULT 'discount',
                `reward_value` DECIMAL(10,2) DEFAULT 0,
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        
        "loyalty_points_$user_id" => "
            CREATE TABLE IF NOT EXISTS `loyalty_points_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `guest_id` INT(11) NOT NULL,
                `points` INT(11) NOT NULL,
                `transaction_type` ENUM('earned', 'redeemed', 'adjusted') DEFAULT 'earned',
                `reason` VARCHAR(500) NOT NULL,
                `booking_id` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `guest_id` (`guest_id`),
                KEY `transaction_type` (`transaction_type`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        
        "loyalty_points_summary_$user_id" => "
            CREATE TABLE IF NOT EXISTS `loyalty_points_summary_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `guest_id` INT(11) NOT NULL,
                `total_points` INT(11) DEFAULT 0,
                `points_earned` INT(11) DEFAULT 0,
                `points_redeemed` INT(11) DEFAULT 0,
                `last_activity` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `guest_id` (`guest_id`),
                KEY `total_points` (`total_points`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        
        "loyalty_rewards_$user_id" => "
            CREATE TABLE IF NOT EXISTS `loyalty_rewards_$user_id` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `reward_name` VARCHAR(255) NOT NULL,
                `points_required` INT(11) NOT NULL,
                `reward_type` ENUM('discount', 'free_night', 'upgrade', 'amenity') DEFAULT 'discount',
                `reward_value` VARCHAR(500) NOT NULL,
                `description` TEXT,
                `status` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        "
    ];
    
    foreach ($tables as $table_name => $query) {
        $conn->query($query);
    }
    
    // Insert default loyalty program
    $default_program = "INSERT INTO loyalty_programs_$user_id 
                       (program_name, points_per_booking, points_per_amount, min_redemption_points, 
                        reward_type, reward_value, status) 
                       VALUES ('Standard Loyalty Program', 10, 1, 100, 'discount', 10, 1)";
    $conn->query($default_program);
}

function findOrCreateGuest($phone, $user_id, $conn) {
    // First try to find existing guest
    $sql = "SELECT id FROM guests_$user_id WHERE phone = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $guest = $result->fetch_assoc();
        $stmt->close();
        return $guest['id'];
    }
    
    $stmt->close();
    
    // Create new guest
    $sql = "INSERT INTO guests_$user_id (name, phone, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $name = "Guest ($phone)";
    $stmt->bind_param("ss", $name, $phone);
    
    if ($stmt->execute()) {
        $guest_id = $stmt->insert_id;
        $stmt->close();
        
        // Initialize points summary
        $sql = "INSERT INTO loyalty_points_summary_$user_id (guest_id, total_points) VALUES (?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $guest_id);
        $stmt->execute();
        $stmt->close();
        
        return $guest_id;
    }
    
    $stmt->close();
    return false;
}

function findGuestByPhone($phone, $user_id, $conn) {
    $sql = "SELECT id FROM guests_$user_id WHERE phone = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $guest = $result->fetch_assoc();
        $stmt->close();
        return $guest['id'];
    }
    
    $stmt->close();
    return false;
}

function getGuestPoints($guest_id, $user_id, $conn) {
    $sql = "SELECT total_points FROM loyalty_points_summary_$user_id WHERE guest_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guest_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $points = $result->fetch_assoc();
        $stmt->close();
        return $points['total_points'];
    }
    
    $stmt->close();
    return 0;
}

function updateGuestPoints($guest_id, $user_id, $conn) {
    // Calculate total points from transactions
    $sql = "SELECT 
            SUM(CASE WHEN transaction_type = 'earned' THEN points ELSE 0 END) as earned,
            SUM(CASE WHEN transaction_type = 'redeemed' THEN points ELSE 0 END) as redeemed
            FROM loyalty_points_$user_id WHERE guest_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guest_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $points = $result->fetch_assoc();
    $stmt->close();
    
    $total_points = ($points['earned'] ?? 0) - ($points['redeemed'] ?? 0);
    
    // Update summary
    $sql = "INSERT INTO loyalty_points_summary_$user_id 
            (guest_id, total_points, points_earned, points_redeemed, last_activity) 
            VALUES (?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            total_points = VALUES(total_points),
            points_earned = VALUES(points_earned),
            points_redeemed = VALUES(points_redeemed),
            last_activity = VALUES(last_activity)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $guest_id, $total_points, $points['earned'], $points['redeemed']);
    $stmt->execute();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Loyalty Program</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .loyalty-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .points-badge {
            background: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .transaction-earned {
            border-left: 4px solid #28a745;
        }
        
        .transaction-redeemed {
            border-left: 4px solid #dc3545;
        }
        
        .member-card {
            transition: transform 0.3s ease;
        }
        
        .member-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
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

                <div class="row">
                    <div class="col-xl-12">
                        <!-- Header -->
                        <div class="card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">
                                            <iconify-icon icon="mdi:crown" class="me-2"></iconify-icon>
                                            Loyalty Program Management
                                        </h4>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProgramModal">
                                            <iconify-icon icon="mdi:plus" class="me-1"></iconify-icon>
                                            Create Program
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stats-card bg-primary text-white">
                                    <div class="stats-number">
                                        <?php echo count($loyalty_programs); ?>
                                    </div>
                                    <div>Active Programs</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card bg-success text-white">
                                    <div class="stats-number">
                                        <?php echo count($loyalty_members); ?>
                                    </div>
                                    <div>Loyalty Members</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card bg-info text-white">
                                    <div class="stats-number">
                                        <?php 
                                        $total_points = 0;
                                        foreach ($loyalty_members as $member) {
                                            $total_points += $member['total_points'];
                                        }
                                        echo $total_points;
                                        ?>
                                    </div>
                                    <div>Total Points Issued</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card bg-warning text-dark">
                                    <div class="stats-number">
                                        <?php echo count($recent_transactions); ?>
                                    </div>
                                    <div>Recent Transactions</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Loyalty Programs -->
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Loyalty Programs</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($loyalty_programs)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Program Name</th>
                                                            <th>Points Rate</th>
                                                            <th>Min. Redemption</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($loyalty_programs as $program): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($program['program_name']); ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        Reward: <?php echo ucfirst($program['reward_type']); ?> 
                                                                        (<?php echo $program['reward_value']; ?>%)
                                                                    </small>
                                                                </td>
                                                                <td>
                                                                    <?php echo $program['points_per_booking']; ?> per booking<br>
                                                                    <?php echo $program['points_per_amount']; ?> per ₹100
                                                                </td>
                                                                <td><?php echo $program['min_redemption_points']; ?> points</td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo $program['status'] ? 'success' : 'danger'; ?>">
                                                                        <?php echo $program['status'] ? 'Active' : 'Inactive'; ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary" 
                                                                            onclick="editProgram(<?php echo htmlspecialchars(json_encode($program)); ?>)">
                                                                        Edit
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <p class="text-muted">No loyalty programs created yet.</p>
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProgramModal">
                                                    Create First Program
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Quick Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <button class="btn btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#addPointsModal">
                                                    <iconify-icon icon="mdi:plus-circle" class="me-1"></iconify-icon>
                                                    Add Points
                                                </button>
                                            </div>
                                            <div class="col-md-6">
                                                <button class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#redeemPointsModal">
                                                    <iconify-icon icon="mdi:gift" class="me-1"></iconify-icon>
                                                    Redeem Points
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Loyalty Members & Recent Activity -->
                            <div class="col-lg-6">
                                <!-- Top Members -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Top Loyalty Members</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($loyalty_members)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Guest</th>
                                                            <th>Phone</th>
                                                            <th>Points</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($loyalty_members as $member): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                                                <td>
                                                                    <span class="points-badge"><?php echo $member['total_points']; ?> pts</span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-info" 
                                                                            onclick="viewMemberHistory('<?php echo $member['phone']; ?>')">
                                                                        History
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted text-center py-3">No loyalty members yet.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Recent Transactions -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Recent Point Transactions</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($recent_transactions)): ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($recent_transactions as $transaction): ?>
                                                    <div class="list-group-item transaction-<?php echo $transaction['transaction_type']; ?>">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($transaction['name']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo $transaction['reason']; ?></small>
                                                            </div>
                                                            <div class="text-end">
                                                                <span class="badge bg-<?php echo $transaction['transaction_type'] === 'earned' ? 'success' : 'danger'; ?>">
                                                                    <?php echo $transaction['transaction_type'] === 'earned' ? '+' : '-'; ?>
                                                                    <?php echo $transaction['points']; ?> pts
                                                                </span>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?php echo date('M j, g:i A', strtotime($transaction['created_at'])); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted text-center py-3">No recent transactions.</p>
                                        <?php endif; ?>
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

    <!-- Modals -->
    <!-- Create Program Modal -->
    <div class="modal fade" id="createProgramModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Loyalty Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Program Name</label>
                            <input type="text" class="form-control" name="program_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Points per Booking</label>
                                    <input type="number" class="form-control" name="points_per_booking" value="10" min="0" step="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Points per ₹100</label>
                                    <input type="number" class="form-control" name="points_per_amount" value="1" min="0" step="0.1">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Minimum Redemption Points</label>
                            <input type="number" class="form-control" name="min_redemption_points" value="100" min="1">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Reward Type</label>
                                    <select class="form-control" name="reward_type">
                                        <option value="discount">Discount</option>
                                        <option value="free_night">Free Night</option>
                                        <option value="upgrade">Room Upgrade</option>
                                        <option value="cashback">Cashback</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Reward Value (%)</label>
                                    <input type="number" class="form-control" name="reward_value" value="10" min="1" max="100">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_program" class="btn btn-primary">Create Program</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Program Modal -->
    <div class="modal fade" id="editProgramModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Loyalty Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="program_id" id="edit_program_id">
                        <div class="mb-3">
                            <label class="form-label">Program Name</label>
                            <input type="text" class="form-control" name="program_name" id="edit_program_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Points per Booking</label>
                                    <input type="number" class="form-control" name="points_per_booking" id="edit_points_per_booking" min="0" step="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Points per ₹100</label>
                                    <input type="number" class="form-control" name="points_per_amount" id="edit_points_per_amount" min="0" step="0.1">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Minimum Redemption Points</label>
                            <input type="number" class="form-control" name="min_redemption_points" id="edit_min_redemption_points" min="1">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Reward Type</label>
                                    <select class="form-control" name="reward_type" id="edit_reward_type">
                                        <option value="discount">Discount</option>
                                        <option value="free_night">Free Night</option>
                                        <option value="upgrade">Room Upgrade</option>
                                        <option value="cashback">Cashback</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Reward Value (%)</label>
                                    <input type="number" class="form-control" name="reward_value" id="edit_reward_value" min="1" max="100">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="edit_status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_program" class="btn btn-primary">Update Program</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Points Modal -->
    <div class="modal fade" id="addPointsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Loyalty Points</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Guest Phone Number</label>
                            <input type="text" class="form-control" name="guest_phone" required 
                                   placeholder="Enter guest's phone number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Points to Add</label>
                            <input type="number" class="form-control" name="points" required min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <input type="text" class="form-control" name="reason" required 
                                   placeholder="e.g., Booking bonus, Manual adjustment">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_points" class="btn btn-success">Add Points</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Redeem Points Modal -->
    <div class="modal fade" id="redeemPointsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Redeem Loyalty Points</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Guest Phone Number</label>
                            <input type="text" class="form-control" name="guest_phone" required 
                                   placeholder="Enter guest's phone number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Points to Redeem</label>
                            <input type="number" class="form-control" name="points" required min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <input type="text" class="form-control" name="reason" required 
                                   placeholder="e.g., Discount redemption, Reward claim">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="redeem_points" class="btn btn-warning">Redeem Points</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
    function editProgram(program) {
        document.getElementById('edit_program_id').value = program.id;
        document.getElementById('edit_program_name').value = program.program_name;
        document.getElementById('edit_points_per_booking').value = program.points_per_booking;
        document.getElementById('edit_points_per_amount').value = program.points_per_amount;
        document.getElementById('edit_min_redemption_points').value = program.min_redemption_points;
        document.getElementById('edit_reward_type').value = program.reward_type;
        document.getElementById('edit_reward_value').value = program.reward_value;
        document.getElementById('edit_status').value = program.status;
        
        var editModal = new bootstrap.Modal(document.getElementById('editProgramModal'));
        editModal.show();
    }
    
    function viewMemberHistory(phone) {
        // You can implement this to show member's point history
        alert('Viewing history for: ' + phone + '\nThis would show detailed point transactions.');
    }
    
    // Auto-close alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    </script>
</body>
</html>
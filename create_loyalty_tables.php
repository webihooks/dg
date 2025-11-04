<?php
// create_loyalty_tables.php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$user_id = $_SESSION['user_id'];

function createLoyaltyTablesForUser($user_id, $conn) {
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
    
    $errors = [];
    $success = [];
    
    foreach ($tables as $table_name => $query) {
        try {
            if ($conn->query($query) === TRUE) {
                $success[] = "Table '$table_name' created successfully";
            } else {
                $errors[] = "Error creating table '$table_name': " . $conn->error;
            }
        } catch (Exception $e) {
            $errors[] = "Exception creating table '$table_name': " . $e->getMessage();
        }
    }
    
    // Insert default loyalty program
    $default_program = "INSERT INTO loyalty_programs_$user_id 
                       (program_name, points_per_booking, points_per_amount, min_redemption_points, 
                        reward_type, reward_value, status) 
                       VALUES ('Standard Loyalty Program', 10, 1, 100, 'discount', 10, 1)";
    $conn->query($default_program);
    
    return ['success' => $success, 'errors' => $errors];
}

// Create tables for current user
$result = createLoyaltyTablesForUser($user_id, $conn);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Loyalty Tables</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3>Loyalty Program Tables Setup</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($result['success'])): ?>
                            <div class="alert alert-success">
                                <h5>Successfully Created Tables:</h5>
                                <ul>
                                    <?php foreach ($result['success'] as $msg): ?>
                                        <li><?php echo $msg; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['errors'])): ?>
                            <div class="alert alert-danger">
                                <h5>Errors:</h5>
                                <ul>
                                    <?php foreach ($result['errors'] as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="loyalty-program.php" class="btn btn-primary">Go to Loyalty Program</a>
                            <a href="room-dashboard.php" class="btn btn-secondary">Back to Room Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// tax-settings.php
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

// Check if tax settings table exists and has correct structure
$tax_table_name = "tax_settings_$user_id";
$check_table_sql = "SHOW TABLES LIKE '$tax_table_name'";
$table_result = $conn->query($check_table_sql);

if ($table_result->num_rows == 0) {
    // Create tax settings table with new structure
    $create_table_sql = "
        CREATE TABLE `$tax_table_name` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tax_name` VARCHAR(100) NOT NULL,
            `tax_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
            `tax_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `tax_code` VARCHAR(50) DEFAULT NULL,
            `applicable_on` ENUM('room_charges', 'food_charges', 'all_services', 'specific_services') DEFAULT 'all_services',
            `is_active` TINYINT(1) DEFAULT 1,
            `is_compound` TINYINT(1) DEFAULT 0,
            `priority` INT(3) DEFAULT 0,
            `description` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tax_name` (`tax_name`),
            KEY `is_active` (`is_active`),
            KEY `tax_type` (`tax_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($create_table_sql) === TRUE) {
        $success_message = "Tax settings table created successfully!";
        
        // Insert default tax settings
        $default_taxes = [
            ['GST', 'percentage', 18.00, 'GST', 'all_services', 1, 0, 1, 'Goods and Services Tax'],
            ['Service Charge', 'percentage', 5.00, 'SERV', 'all_services', 1, 0, 2, 'Service charge for hospitality services'],
            ['Luxury Tax', 'percentage', 12.00, 'LUX', 'room_charges', 0, 0, 3, 'Luxury tax applicable on room charges above ₹7500']
        ];
        
        $insert_sql = "INSERT INTO `$tax_table_name` (tax_name, tax_type, tax_rate, tax_code, applicable_on, is_active, is_compound, priority, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        
        foreach ($default_taxes as $tax) {
            $stmt->bind_param("ssdssiiis", $tax[0], $tax[1], $tax[2], $tax[3], $tax[4], $tax[5], $tax[6], $tax[7], $tax[8]);
            $stmt->execute();
        }
        $stmt->close();
        
    } else {
        $error_message = "Error creating tax settings table: " . $conn->error;
    }
} else {
    // Table exists, check and add missing columns one by one
    $check_columns_sql = "SHOW COLUMNS FROM `$tax_table_name`";
    $columns_result = $conn->query($check_columns_sql);
    $existing_columns = [];
    while ($column = $columns_result->fetch_assoc()) {
        $existing_columns[] = $column['Field'];
    }
    
    // Define columns to check and add
    $columns_to_add = [
        'priority' => "ADD COLUMN `priority` INT(3) DEFAULT 0",
        'is_compound' => "ADD COLUMN `is_compound` TINYINT(1) DEFAULT 0", 
        'applicable_on' => "ADD COLUMN `applicable_on` ENUM('room_charges', 'food_charges', 'all_services', 'specific_services') DEFAULT 'all_services'"
    ];
    
    $columns_added = [];
    
    foreach ($columns_to_add as $column_name => $alter_sql) {
        if (!in_array($column_name, $existing_columns)) {
            $alter_table_sql = "ALTER TABLE `$tax_table_name` $alter_sql";
            if ($conn->query($alter_table_sql) === TRUE) {
                $columns_added[] = $column_name;
                $existing_columns[] = $column_name; // Add to existing columns for subsequent checks
            } else {
                $error_message = "Error adding column '$column_name': " . $conn->error;
            }
        }
    }
    
    if (!empty($columns_added)) {
        $success_message = "Added missing columns: " . implode(', ', $columns_added);
        
        // Set default values for existing records for newly added columns
        $update_queries = [];
        if (in_array('priority', $columns_added)) {
            $update_queries[] = "UPDATE `$tax_table_name` SET priority = 0 WHERE priority IS NULL";
        }
        if (in_array('is_compound', $columns_added)) {
            $update_queries[] = "UPDATE `$tax_table_name` SET is_compound = 0 WHERE is_compound IS NULL";
        }
        if (in_array('applicable_on', $columns_added)) {
            $update_queries[] = "UPDATE `$tax_table_name` SET applicable_on = 'all_services' WHERE applicable_on IS NULL";
        }
        
        foreach ($update_queries as $update_sql) {
            $conn->query($update_sql);
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_tax'])) {
        // Add new tax
        $tax_name = trim($_POST['tax_name']);
        $tax_type = $_POST['tax_type'];
        $tax_rate = floatval($_POST['tax_rate']);
        $tax_code = trim($_POST['tax_code']);
        $applicable_on = $_POST['applicable_on'] ?? 'all_services';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_compound = isset($_POST['is_compound']) ? 1 : 0;
        $priority = intval($_POST['priority'] ?? 0);
        $description = trim($_POST['description']);
        
        // Check if tax name already exists
        $check_sql = "SELECT id FROM `$tax_table_name` WHERE tax_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $tax_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Tax with name '$tax_name' already exists!";
        } else {
            // Build dynamic insert query based on available columns
            $insert_columns = ['tax_name', 'tax_type', 'tax_rate'];
            $insert_placeholders = ['?', '?', '?'];
            $insert_values = [$tax_name, $tax_type, $tax_rate];
            $param_types = "ssd";
            
            // Add optional columns if they exist in the table
            $optional_columns = [
                'tax_code' => ['value' => $tax_code, 'type' => 's'],
                'applicable_on' => ['value' => $applicable_on, 'type' => 's'],
                'is_active' => ['value' => $is_active, 'type' => 'i'],
                'is_compound' => ['value' => $is_compound, 'type' => 'i'],
                'priority' => ['value' => $priority, 'type' => 'i'],
                'description' => ['value' => $description, 'type' => 's']
            ];
            
            foreach ($optional_columns as $column => $data) {
                if (in_array($column, $existing_columns)) {
                    $insert_columns[] = $column;
                    $insert_placeholders[] = '?';
                    $insert_values[] = $data['value'];
                    $param_types .= $data['type'];
                }
            }
            
            $columns_string = implode(', ', $insert_columns);
            $placeholders_string = implode(', ', $insert_placeholders);
            
            $insert_sql = "INSERT INTO `$tax_table_name` ($columns_string) VALUES ($placeholders_string)";
            $stmt = $conn->prepare($insert_sql);
            
            if ($stmt) {
                $stmt->bind_param($param_types, ...$insert_values);
                
                if ($stmt->execute()) {
                    $success_message = "Tax setting added successfully!";
                } else {
                    $error_message = "Error adding tax setting: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing insert statement: " . $conn->error;
            }
        }
        $check_stmt->close();
        
    } elseif (isset($_POST['update_tax'])) {
        // Update existing tax
        $tax_id = intval($_POST['tax_id']);
        $tax_name = trim($_POST['tax_name']);
        $tax_type = $_POST['tax_type'];
        $tax_rate = floatval($_POST['tax_rate']);
        $tax_code = trim($_POST['tax_code']);
        $applicable_on = $_POST['applicable_on'] ?? 'all_services';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_compound = isset($_POST['is_compound']) ? 1 : 0;
        $priority = intval($_POST['priority'] ?? 0);
        $description = trim($_POST['description']);
        
        // Check if tax name already exists (excluding current tax)
        $check_sql = "SELECT id FROM `$tax_table_name` WHERE tax_name = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $tax_name, $tax_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Another tax with name '$tax_name' already exists!";
        } else {
            // Build dynamic update query based on available columns
            $update_fields = ['tax_name = ?', 'tax_type = ?', 'tax_rate = ?'];
            $update_values = [$tax_name, $tax_type, $tax_rate];
            $param_types = "ssd";
            
            // Add optional columns if they exist in the table
            $optional_columns = [
                'tax_code' => ['value' => $tax_code, 'type' => 's'],
                'applicable_on' => ['value' => $applicable_on, 'type' => 's'],
                'is_active' => ['value' => $is_active, 'type' => 'i'],
                'is_compound' => ['value' => $is_compound, 'type' => 'i'],
                'priority' => ['value' => $priority, 'type' => 'i'],
                'description' => ['value' => $description, 'type' => 's']
            ];
            
            foreach ($optional_columns as $column => $data) {
                if (in_array($column, $existing_columns)) {
                    $update_fields[] = "$column = ?";
                    $update_values[] = $data['value'];
                    $param_types .= $data['type'];
                }
            }
            
            $update_fields_string = implode(', ', $update_fields);
            $update_values[] = $tax_id; // Add ID for WHERE clause
            $param_types .= "i";
            
            $update_sql = "UPDATE `$tax_table_name` SET $update_fields_string WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            
            if ($stmt) {
                $stmt->bind_param($param_types, ...$update_values);
                
                if ($stmt->execute()) {
                    $success_message = "Tax setting updated successfully!";
                } else {
                    $error_message = "Error updating tax setting: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing update statement: " . $conn->error;
            }
        }
        $check_stmt->close();
        
    } elseif (isset($_POST['delete_tax'])) {
        // Delete tax
        $tax_id = intval($_POST['tax_id']);
        
        $delete_sql = "DELETE FROM `$tax_table_name` WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $tax_id);
        
        if ($stmt->execute()) {
            $success_message = "Tax setting deleted successfully!";
        } else {
            $error_message = "Error deleting tax setting: " . $stmt->error;
        }
        $stmt->close();
        
    } elseif (isset($_POST['toggle_status'])) {
        // Toggle tax status
        $tax_id = intval($_POST['tax_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        
        $update_sql = "UPDATE `$tax_table_name` SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $new_status, $tax_id);
        
        if ($stmt->execute()) {
            $success_message = "Tax status updated successfully!";
        } else {
            $error_message = "Error updating tax status: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all tax settings - handle potential missing columns gracefully
try {
    // First, check what columns actually exist
    $check_columns_sql = "SHOW COLUMNS FROM `$tax_table_name`";
    $columns_result = $conn->query($check_columns_sql);
    $existing_columns = [];
    while ($column = $columns_result->fetch_assoc()) {
        $existing_columns[] = $column['Field'];
    }
    
    // Build query based on existing columns
    $select_columns = ['id', 'tax_name', 'tax_type', 'tax_rate'];
    
    // Add optional columns if they exist
    $optional_columns = ['tax_code', 'applicable_on', 'is_active', 'is_compound', 'priority', 'description'];
    foreach ($optional_columns as $column) {
        if (in_array($column, $existing_columns)) {
            $select_columns[] = $column;
        }
    }
    
    $columns_string = implode(', ', $select_columns);
    
    // Build ORDER BY clause based on available columns
    $order_by = 'tax_name ASC';
    if (in_array('priority', $existing_columns)) {
        $order_by = 'priority ASC, tax_name ASC';
    } elseif (in_array('is_active', $existing_columns)) {
        $order_by = 'is_active DESC, tax_name ASC';
    }
    
    $tax_settings_sql = "SELECT $columns_string FROM `$tax_table_name` ORDER BY $order_by";
    $tax_settings_result = $conn->query($tax_settings_sql);
    $tax_settings = [];
    if ($tax_settings_result) {
        $tax_settings = $tax_settings_result->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Error fetching tax settings: " . $e->getMessage();
    $tax_settings = [];
}

// Calculate total tax impact for preview
$total_tax_rate = 0;
$active_taxes = array_filter($tax_settings, function($tax) {
    return isset($tax['is_active']) ? $tax['is_active'] == 1 : true;
});

foreach ($active_taxes as $tax) {
    if ($tax['tax_type'] === 'percentage') {
        $total_tax_rate += $tax['tax_rate'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Tax Settings - Room Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <style>
        .tax-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .tax-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .tax-card.inactive {
            border-left-color: #6c757d;
            opacity: 0.7;
        }
        .tax-rate-badge {
            font-size: 1.1em;
            font-weight: bold;
        }
        .tax-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .applicable-on-badge {
            font-size: 0.8em;
            margin-right: 5px;
        }
        .column-missing {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
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
                    <div class="col-xl-12">
                        <!-- Page Title -->
                        <div class="page-title-box">
                            <h4 class="page-title">Tax Settings</h4>
                            <p class="text-muted mb-4">Manage tax rates and configurations for your room management system</p>
                        </div>

                        <!-- Notifications -->
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Database Structure Warning -->
                        <?php 
                        $required_columns = ['priority', 'is_compound', 'applicable_on'];
                        $missing_columns = array_diff($required_columns, $existing_columns);
                        if (!empty($missing_columns)): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Database Update Required</h5>
                                <p class="mb-2">Your tax settings table is missing some columns. The system will automatically update the table structure when needed.</p>
                                <p class="mb-0"><strong>Missing columns:</strong> <?php echo implode(', ', $missing_columns); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Tax Impact Preview -->
                        <div class="tax-preview">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="text-white">Current Tax Configuration</h5>
                                    <p class="mb-1">Total Active Taxes: <?php echo count($active_taxes); ?></p>
                                    <p class="mb-1">Total Tax Rate: <strong><?php echo $total_tax_rate; ?>%</strong></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <h3 class="text-white mb-0"><?php echo $total_tax_rate; ?>%</h3>
                                    <p class="mb-0">Overall Tax Impact</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Add Tax Form -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-plus-circle me-2"></i>
                                            <?php echo isset($_GET['edit']) ? 'Edit Tax Setting' : 'Add New Tax'; ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $edit_tax = null;
                                        if (isset($_GET['edit'])) {
                                            $edit_id = intval($_GET['edit']);
                                            foreach ($tax_settings as $tax) {
                                                if ($tax['id'] == $edit_id) {
                                                    $edit_tax = $tax;
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        
                                        <form method="POST" id="taxForm">
                                            <?php if ($edit_tax): ?>
                                                <input type="hidden" name="tax_id" value="<?php echo $edit_tax['id']; ?>">
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="tax_name" class="form-label">Tax Name *</label>
                                                <input type="text" class="form-control" id="tax_name" name="tax_name" 
                                                       value="<?php echo $edit_tax ? htmlspecialchars($edit_tax['tax_name']) : ''; ?>" 
                                                       required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="tax_type" class="form-label">Tax Type *</label>
                                                <select class="form-select" id="tax_type" name="tax_type" required>
                                                    <option value="percentage" <?php echo ($edit_tax && $edit_tax['tax_type'] == 'percentage') ? 'selected' : ''; ?>>Percentage (%)</option>
                                                    <option value="fixed" <?php echo ($edit_tax && $edit_tax['tax_type'] == 'fixed') ? 'selected' : ''; ?>>Fixed Amount (₹)</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="tax_rate" class="form-label">Tax Rate *</label>
                                                <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                                       step="0.01" min="0" 
                                                       value="<?php echo $edit_tax ? $edit_tax['tax_rate'] : ''; ?>" 
                                                       required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="tax_code" class="form-label">Tax Code</label>
                                                <input type="text" class="form-control" id="tax_code" name="tax_code" 
                                                       value="<?php echo $edit_tax ? htmlspecialchars($edit_tax['tax_code'] ?? '') : ''; ?>">
                                                <div class="form-text">Short code for reporting (e.g., GST, VAT)</div>
                                            </div>
                                            
                                            <?php if (in_array('applicable_on', $existing_columns)): ?>
                                            <div class="mb-3">
                                                <label for="applicable_on" class="form-label">Applicable On</label>
                                                <select class="form-select" id="applicable_on" name="applicable_on">
                                                    <option value="all_services" <?php echo ($edit_tax && ($edit_tax['applicable_on'] ?? '') == 'all_services') ? 'selected' : ''; ?>>All Services</option>
                                                    <option value="room_charges" <?php echo ($edit_tax && ($edit_tax['applicable_on'] ?? '') == 'room_charges') ? 'selected' : ''; ?>>Room Charges Only</option>
                                                    <option value="food_charges" <?php echo ($edit_tax && ($edit_tax['applicable_on'] ?? '') == 'food_charges') ? 'selected' : ''; ?>>Food Charges Only</option>
                                                    <option value="specific_services" <?php echo ($edit_tax && ($edit_tax['applicable_on'] ?? '') == 'specific_services') ? 'selected' : ''; ?>>Specific Services</option>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array('priority', $existing_columns)): ?>
                                            <div class="mb-3">
                                                <label for="priority" class="form-label">Priority</label>
                                                <input type="number" class="form-control" id="priority" name="priority" 
                                                       min="0" max="100" 
                                                       value="<?php echo $edit_tax ? ($edit_tax['priority'] ?? 0) : '0'; ?>">
                                                <div class="form-text">Lower numbers are applied first</div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $edit_tax ? htmlspecialchars($edit_tax['description'] ?? '') : ''; ?></textarea>
                                            </div>
                                            
                                            <?php if (in_array('is_active', $existing_columns)): ?>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                                           <?php echo (($edit_tax && ($edit_tax['is_active'] ?? 1) == 1) || !$edit_tax) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="is_active">Active</label>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array('is_compound', $existing_columns)): ?>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="is_compound" name="is_compound"
                                                           <?php echo ($edit_tax && ($edit_tax['is_compound'] ?? 0) == 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="is_compound">Compound Tax (applied on top of other taxes)</label>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-grid gap-2">
                                                <?php if ($edit_tax): ?>
                                                    <button type="submit" name="update_tax" class="btn btn-primary">Update Tax Setting</button>
                                                    <a href="tax-settings.php" class="btn btn-secondary">Cancel</a>
                                                <?php else: ?>
                                                    <button type="submit" name="add_tax" class="btn btn-success">Add Tax Setting</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Tax Settings List -->
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-cog me-2"></i>
                                            Configured Taxes (<?php echo count($tax_settings); ?>)
                                        </h5>
                                        <div>
                                            <small class="text-muted">
                                                <?php if (!empty($missing_columns)): ?>
                                                    <span class="badge bg-warning">Legacy Structure</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Updated Structure</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($tax_settings)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-cog fa-3x text-muted mb-3"></i>
                                                <h5>No Tax Settings Found</h5>
                                                <p class="text-muted">Add your first tax setting to get started.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Tax Name</th>
                                                            <th>Type</th>
                                                            <th>Rate</th>
                                                            <?php if (in_array('applicable_on', $existing_columns)): ?>
                                                                <th>Applicable On</th>
                                                            <?php endif; ?>
                                                            <?php if (in_array('is_active', $existing_columns)): ?>
                                                                <th>Status</th>
                                                            <?php endif; ?>
                                                            <?php if (in_array('priority', $existing_columns)): ?>
                                                                <th>Priority</th>
                                                            <?php endif; ?>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($tax_settings as $tax): ?>
                                                            <tr class="<?php echo (isset($tax['is_active']) && $tax['is_active'] == 0) ? 'table-secondary' : ''; ?>">
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($tax['tax_name']); ?></strong>
                                                                    <?php if (isset($tax['tax_code']) && $tax['tax_code']): ?>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($tax['tax_code']); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo $tax['tax_type'] == 'percentage' ? 'info' : 'warning'; ?>">
                                                                        <?php echo ucfirst($tax['tax_type']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="tax-rate-badge">
                                                                        <?php echo $tax['tax_rate']; ?>
                                                                        <?php echo $tax['tax_type'] == 'percentage' ? '%' : '₹'; ?>
                                                                    </span>
                                                                </td>
                                                                <?php if (in_array('applicable_on', $existing_columns)): ?>
                                                                <td>
                                                                    <span class="badge bg-light text-dark applicable-on-badge">
                                                                        <?php echo isset($tax['applicable_on']) ? str_replace('_', ' ', ucfirst($tax['applicable_on'])) : 'All Services'; ?>
                                                                    </span>
                                                                </td>
                                                                <?php endif; ?>
                                                                <?php if (in_array('is_active', $existing_columns)): ?>
                                                                <td>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="tax_id" value="<?php echo $tax['id']; ?>">
                                                                        <input type="hidden" name="current_status" value="<?php echo $tax['is_active']; ?>">
                                                                        <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $tax['is_active'] ? 'success' : 'secondary'; ?>">
                                                                            <?php echo $tax['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                        </button>
                                                                    </form>
                                                                </td>
                                                                <?php endif; ?>
                                                                <?php if (in_array('priority', $existing_columns)): ?>
                                                                <td>
                                                                    <span class="badge bg-dark"><?php echo $tax['priority'] ?? 0; ?></span>
                                                                </td>
                                                                <?php endif; ?>
                                                                <td>
                                                                    <div class="btn-group btn-group-sm">
                                                                        <a href="tax-settings.php?edit=<?php echo $tax['id']; ?>" class="btn btn-primary">
                                                                            <i class="fas fa-edit"></i>
                                                                        </a>
                                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this tax setting?');">
                                                                            <input type="hidden" name="tax_id" value="<?php echo $tax['id']; ?>">
                                                                            <button type="submit" name="delete_tax" class="btn btn-danger">
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                        </form>
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

                                <!-- Tax Calculation Example -->
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-calculator me-2"></i>
                                            Tax Calculation Example
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="example_amount" class="form-label">Enter Amount (₹)</label>
                                                <input type="number" class="form-control" id="example_amount" value="1000" min="0" step="0.01">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Total Tax</label>
                                                <div id="tax_calculation_result" class="h4 text-primary">₹0.00</div>
                                            </div>
                                        </div>
                                        <div class="mt-3" id="tax_breakdown">
                                            <!-- Tax breakdown will be populated by JavaScript -->
                                        </div>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
    $(document).ready(function() {
        // Form validation
        $('#taxForm').validate({
            rules: {
                tax_name: {
                    required: true,
                    minlength: 2
                },
                tax_rate: {
                    required: true,
                    min: 0
                }
            },
            messages: {
                tax_name: {
                    required: "Please enter tax name",
                    minlength: "Tax name must be at least 2 characters long"
                },
                tax_rate: {
                    required: "Please enter tax rate",
                    min: "Tax rate cannot be negative"
                }
            }
        });

        // Tax calculation example
        function calculateTax() {
            const amount = parseFloat($('#example_amount').val()) || 0;
            const activeTaxes = <?php echo json_encode($active_taxes); ?>;
            
            let totalTax = 0;
            let breakdownHtml = '<h6 class="mb-3">Tax Breakdown:</h6>';
            
            // Sort taxes by priority if available, otherwise by name
            const sortedTaxes = [...activeTaxes].sort((a, b) => {
                if (a.priority !== undefined && b.priority !== undefined) {
                    return a.priority - b.priority;
                }
                return a.tax_name.localeCompare(b.tax_name);
            });
            
            let currentAmount = amount;
            
            sortedTaxes.forEach(tax => {
                let taxAmount = 0;
                
                if (tax.tax_type === 'percentage') {
                    taxAmount = currentAmount * (tax.tax_rate / 100);
                } else {
                    taxAmount = parseFloat(tax.tax_rate);
                }
                
                totalTax += taxAmount;
                
                breakdownHtml += `
                    <div class="d-flex justify-content-between mb-2">
                        <span>${tax.tax_name} (${tax.tax_rate}${tax.tax_type === 'percentage' ? '%' : '₹'})</span>
                        <span>₹${taxAmount.toFixed(2)}</span>
                    </div>
                `;
                
                // If compound tax, add to current amount for next calculation
                if (tax.is_compound) {
                    currentAmount += taxAmount;
                }
            });
            
            breakdownHtml += `
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total Tax:</span>
                    <span>₹${totalTax.toFixed(2)}</span>
                </div>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total Amount:</span>
                    <span>₹${(amount + totalTax).toFixed(2)}</span>
                </div>
            `;
            
            $('#tax_calculation_result').text('₹' + totalTax.toFixed(2));
            $('#tax_breakdown').html(breakdownHtml);
        }

        // Calculate tax on input change
        $('#example_amount').on('input', calculateTax);
        
        // Initial calculation
        calculateTax();

        // Show/hide fields based on tax type
        $('#tax_type').change(function() {
            const taxType = $(this).val();
            if (taxType === 'percentage') {
                $('#tax_rate').attr('step', '0.01');
            } else {
                $('#tax_rate').attr('step', '1');
            }
        });
    });
    </script>
</body>
</html>
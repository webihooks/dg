<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$is_edit_mode = false;
$toppings_group_data = null;
$topping_data = null;

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Create toppings group table if it doesn't exist
$toppings_group_table = "toppings_group_" . $user_id;
$create_toppings_group_sql = "CREATE TABLE IF NOT EXISTS $toppings_group_table (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    toppings_group_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($create_toppings_group_sql)) {
    $error_message = "Error creating toppings group table: " . $conn->error;
}

// Create toppings table if it doesn't exist
$toppings_table = "toppings_" . $user_id;
$create_toppings_sql = "CREATE TABLE IF NOT EXISTS $toppings_table (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    toppings_group_id INT(11) NOT NULL,
    toppings_name VARCHAR(255) NOT NULL,
    toppings_price DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (toppings_group_id) REFERENCES $toppings_group_table(id) ON DELETE CASCADE
)";

if (!$conn->query($create_toppings_sql)) {
    $error_message = "Error creating toppings table: " . $conn->error;
}

// Handle form submission for add/update toppings group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toppings_group_name'])) {
    $toppings_group_id = isset($_POST['toppings_group_id']) ? $_POST['toppings_group_id'] : null;
    $toppings_group_name = trim($_POST['toppings_group_name']);

    // Validate inputs
    if (empty($toppings_group_name)) {
        $error_message = "Toppings Group Name is required.";
    } else {
        if ($toppings_group_id) {
            // Update existing toppings group
            $sql = "UPDATE $toppings_group_table SET toppings_group_name = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $toppings_group_name, $toppings_group_id);
        } else {
            // Add new toppings group
            $sql = "INSERT INTO $toppings_group_table (toppings_group_name) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $toppings_group_name);
        }

        if ($stmt->execute()) {
            $success_message = $toppings_group_id ? "Toppings group updated successfully!" : "Toppings group added successfully!";
        } else {
            $error_message = "Error saving toppings group: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle form submission for add/update topping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toppings_name'])) {
    $topping_id = isset($_POST['topping_id']) ? $_POST['topping_id'] : null;
    $toppings_group_id = $_POST['toppings_group_id'];
    $toppings_name = trim($_POST['toppings_name']);
    $toppings_price = trim($_POST['toppings_price']);

    // Validate inputs
    if (empty($toppings_name) || empty($toppings_group_id)) {
        $error_message = "Topping Name and Group are required fields.";
    } else {
        if ($topping_id) {
            // Update existing topping
            $sql = "UPDATE $toppings_table SET toppings_group_id = ?, toppings_name = ?, toppings_price = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isdi", $toppings_group_id, $toppings_name, $toppings_price, $topping_id);
        } else {
            // Add new topping
            $sql = "INSERT INTO $toppings_table (toppings_group_id, toppings_name, toppings_price) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isd", $toppings_group_id, $toppings_name, $toppings_price);
        }

        if ($stmt->execute()) {
            $success_message = $topping_id ? "Topping updated successfully!" : "Topping added successfully!";
        } else {
            $error_message = "Error saving topping: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle edit toppings group request
if (isset($_GET['edit_group'])) {
    $toppings_group_id = $_GET['edit_group'];
    $sql = "SELECT * FROM $toppings_group_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $toppings_group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $toppings_group_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($toppings_group_data) {
        $is_edit_mode = true;
    }
}

// Handle edit topping request
if (isset($_GET['edit_topping'])) {
    $topping_id = $_GET['edit_topping'];
    $sql = "SELECT * FROM $toppings_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $topping_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $topping_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($topping_data) {
        $is_edit_mode = true;
    }
}

// Handle delete toppings group request
if (isset($_GET['delete_group'])) {
    $toppings_group_id = $_GET['delete_group'];
    
    // Delete the toppings group from user's table
    $sql = "DELETE FROM $toppings_group_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $toppings_group_id);
    
    if ($stmt->execute()) {
        $success_message = "Toppings group deleted successfully!";
    } else {
        $error_message = "Error deleting toppings group: " . $conn->error;
    }
    $stmt->close();
}

// Handle delete topping request
if (isset($_GET['delete_topping'])) {
    $topping_id = $_GET['delete_topping'];
    
    // Delete the topping from user's table
    $sql = "DELETE FROM $toppings_table WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $topping_id);
    
    if ($stmt->execute()) {
        $success_message = "Topping deleted successfully!";
    } else {
        $error_message = "Error deleting topping: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all toppings groups for the current user
$sql = "SELECT * FROM $toppings_group_table ORDER BY toppings_group_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$toppings_groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all toppings for the current user with group names
$sql = "SELECT t.*, tg.toppings_group_name 
        FROM $toppings_table t
        JOIN $toppings_group_table tg ON t.toppings_group_id = tg.id
        ORDER BY tg.toppings_group_name, t.toppings_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$toppings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Toppings Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table th{background-color:#f8f9fa;font-weight:600}.toppings-table{width:100%;border-collapse:collapse}.toppings-table td,.toppings-table th{padding:12px;border:1px solid #dee2e6;vertical-align:middle}.action-buttons{display:flex;gap:8px}.btn-sm{padding:5px 10px;font-size:12px}.no-results{text-align:center;padding:20px;font-style:italic;color:#6c757d}@media (max-width:768px){.form-row{flex-direction:column}.col-md-6{width:100%;margin-bottom:15px}.btn-group-responsive{display:flex;flex-direction:column;gap:10px}.btn-group-responsive .btn{width:100%;margin-bottom:5px}.action-buttons{flex-direction:row;flex-wrap:wrap;justify-content:center}}@media (max-width:576px){.container{padding-left:10px;padding-right:10px}.card-body{padding:15px}.toppings-table td,.toppings-table th{padding:8px}.btn{padding:8px 12px;font-size:14px}.action-buttons{flex-direction:row;flex-wrap:nowrap;justify-content:center}.action-buttons .btn{padding:6px 10px;font-size:12px}.modal-dialog{margin:10px}}.mobile-toppings-card{display:none;border:1px solid #dee2e6;border-radius:5px;padding:15px;margin-bottom:15px;background:#fff}.mobile-toppings-field{display:flex;justify-content:space-between;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #f1f1f1}.mobile-toppings-field:last-child{border-bottom:none}.mobile-field-label{font-weight:700;color:#495057;min-width:100px}.mobile-field-value{flex:1;text-align:right}@media (max-width:992px){.toppings-table{display:none}.mobile-toppings-card{display:block}}.mobile-actions{display:flex;justify-content:center;gap:8px;margin-top:15px;flex-wrap:wrap}.mobile-actions .btn{flex:1;min-width:80px;max-width:120px}.nav-tabs .nav-link.active{font-weight:600}
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Toppings Management</h4>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-tabs" id="toppingsTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups" type="button" role="tab" aria-controls="groups" aria-selected="true">Toppings Groups</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="toppings-tab" data-bs-toggle="tab" data-bs-target="#toppings" type="button" role="tab" aria-controls="toppings" aria-selected="false">Toppings</button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content mt-3" id="toppingsTabsContent">
                                    <!-- Toppings Groups Tab -->
                                    <div class="tab-pane fade show active" id="groups" role="tabpanel" aria-labelledby="groups-tab">
                                        <h4 class="card-title"><?php echo isset($toppings_group_data) ? 'Edit Toppings Group' : 'Add New Toppings Group'; ?></h4>
                                        <form id="toppingsGroupForm" method="POST" action="toppings.php">
                                            <input type="hidden" name="toppings_group_id" value="<?php echo isset($toppings_group_data) ? $toppings_group_data['id'] : ''; ?>">
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="toppings_group_name" class="form-label">Toppings Group Name *</label>
                                                    <input type="text" class="form-control" id="toppings_group_name" name="toppings_group_name" required 
                                                        value="<?php echo isset($toppings_group_data) ? htmlspecialchars($toppings_group_data['toppings_group_name']) : ''; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="btn-group-responsive">
                                                <button type="submit" class="btn btn-primary">
                                                    <?php echo isset($toppings_group_data) ? 'Update' : 'Save'; ?> Toppings Group
                                                </button>
                                                <?php if (isset($toppings_group_data)): ?>
                                                    <a href="toppings.php" class="btn btn-secondary">Cancel</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                        
                                        <div class="mt-4">
                                            <h4 class="card-title">Your Toppings Groups</h4>
                                            
                                            <?php if (empty($toppings_groups)): ?>
                                                <div class="no-results">
                                                    <p>No toppings groups found. Add your first toppings group above.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-striped toppings-table">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Group Name</th>
                                                                <th>Created At</th>
                                                                <th>Updated At</th>
                                                                <th width="120">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($toppings_groups as $group): ?>
                                                                <tr>
                                                                    <td><?php echo $group['id']; ?></td>
                                                                    <td><?php echo htmlspecialchars($group['toppings_group_name']); ?></td>
                                                                    <td><?php echo $group['created_at']; ?></td>
                                                                    <td><?php echo $group['updated_at']; ?></td>
                                                                    <td>
                                                                        <div class="action-buttons">
                                                                            <a href="toppings.php?edit_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-primary">
                                                                                <i class="fas fa-edit"></i>
                                                                            </a>
                                                                            <a href="toppings.php?delete_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-danger" 
                                                                                onclick="return confirm('Are you sure you want to delete this toppings group? This will also delete all toppings in this group.')">
                                                                                <i class="fas fa-times"></i>
                                                                            </a>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                
                                                <!-- Mobile toppings groups cards (hidden on larger screens) -->
                                                <div class="mobile-toppings-list">
                                                    <?php foreach ($toppings_groups as $group): ?>
                                                        <div class="mobile-toppings-card">
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">ID</span>
                                                                <span class="mobile-field-value"><?php echo $group['id']; ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Group Name</span>
                                                                <span class="mobile-field-value"><?php echo htmlspecialchars($group['toppings_group_name']); ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Created At</span>
                                                                <span class="mobile-field-value"><?php echo $group['created_at']; ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Updated At</span>
                                                                <span class="mobile-field-value"><?php echo $group['updated_at']; ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Actions</span>
                                                                <span class="mobile-field-value">
                                                                    <div class="action-buttons">
                                                                        <a href="toppings.php?edit_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-primary">
                                                                            <i class="fas fa-edit"></i>
                                                                        </a>
                                                                        <a href="toppings.php?delete_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-danger" 
                                                                            onclick="return confirm('Are you sure you want to delete this toppings group? This will also delete all toppings in this group.')">
                                                                            <i class="fas fa-times"></i>
                                                                        </a>
                                                                    </div>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Toppings Tab -->
                                    <div class="tab-pane fade" id="toppings" role="tabpanel" aria-labelledby="toppings-tab">
                                        <h4 class="card-title"><?php echo isset($topping_data) ? 'Edit Topping' : 'Add New Topping'; ?></h4>
                                        <form id="toppingForm" method="POST" action="toppings.php">
                                            <input type="hidden" name="topping_id" value="<?php echo isset($topping_data) ? $topping_data['id'] : ''; ?>">
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <label for="toppings_group_id" class="form-label">Toppings Group *</label>
                                                    <select class="form-select" id="toppings_group_id" name="toppings_group_id" required>
                                                        <option value="">-- Select Toppings Group --</option>
                                                        <?php foreach ($toppings_groups as $group): ?>
                                                            <option value="<?php echo $group['id']; ?>" 
                                                                <?php echo (isset($topping_data) && $topping_data['toppings_group_id'] == $group['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($group['toppings_group_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="toppings_name" class="form-label">Topping Name *</label>
                                                    <input type="text" class="form-control" id="toppings_name" name="toppings_name" required 
                                                        value="<?php echo isset($topping_data) ? htmlspecialchars($topping_data['toppings_name']) : ''; ?>">
                                                </div>

                                                <div class="col-md-4">
                                                    <label for="toppings_price" class="form-label">Topping Price</label>
                                                    <input type="number" step="0.01" class="form-control" id="toppings_price" name="toppings_price" 
                                                        value="<?php echo isset($topping_data) ? $topping_data['toppings_price'] : '0'; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="btn-group-responsive">
                                                <button type="submit" class="btn btn-primary">
                                                    <?php echo isset($topping_data) ? 'Update' : 'Save'; ?> Topping
                                                </button>
                                                <?php if (isset($topping_data)): ?>
                                                    <a href="toppings.php" class="btn btn-secondary">Cancel</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                        
                                        <div class="mt-4">
                                            <h4 class="card-title">Your Toppings</h4>
                                            
                                            <?php if (empty($toppings)): ?>
                                                <div class="no-results">
                                                    <p>No toppings found. Add your first topping above.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-striped toppings-table">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Group Name</th>
                                                                <th>Topping Name</th>
                                                                <th>Price</th>
                                                                <th>Created At</th>
                                                                <th>Updated At</th>
                                                                <th width="120">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($toppings as $topping): ?>
                                                                <tr>
                                                                    <td><?php echo $topping['id']; ?></td>
                                                                    <td><?php echo htmlspecialchars($topping['toppings_group_name']); ?></td>
                                                                    <td><?php echo htmlspecialchars($topping['toppings_name']); ?></td>
                                                                    <td>₹<?php echo number_format($topping['toppings_price'], 2); ?></td>
                                                                    <td><?php echo $topping['created_at']; ?></td>
                                                                    <td><?php echo $topping['updated_at']; ?></td>
                                                                    <td>
                                                                        <div class="action-buttons">
                                                                            <a href="toppings.php?edit_topping=<?php echo $topping['id']; ?>" class="btn btn-sm btn-primary">
                                                                                <i class="fas fa-edit"></i>
                                                                            </a>
                                                                            <a href="toppings.php?delete_topping=<?php echo $topping['id']; ?>" class="btn btn-sm btn-danger" 
                                                                                onclick="return confirm('Are you sure you want to delete this topping?')">
                                                                                <i class="fas fa-times"></i>
                                                                            </a>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                
                                                <!-- Mobile toppings cards (hidden on larger screens) -->
                                                <div class="mobile-toppings-list">
                                                    <?php foreach ($toppings as $topping): ?>
                                                        <div class="mobile-toppings-card">
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">ID</span>
                                                                <span class="mobile-field-value"><?php echo $topping['id']; ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Group Name</span>
                                                                <span class="mobile-field-value"><?php echo htmlspecialchars($topping['toppings_group_name']); ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Topping Name</span>
                                                                <span class="mobile-field-value"><?php echo htmlspecialchars($topping['toppings_name']); ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Price</span>
                                                                <span class="mobile-field-value">₹<?php echo number_format($topping['toppings_price'], 2); ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Created At</span>
                                                                <span class="mobile-field-value"><?php echo $topping['created_at']; ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Updated At</span>
                                                                <span class="mobile-field-value"><?php echo $topping['updated_at']; ?></span>
                                                            </div>
                                                            <div class="mobile-toppings-field">
                                                                <span class="mobile-field-label">Actions</span>
                                                                <span class="mobile-field-value">
                                                                    <div class="action-buttons">
                                                                        <a href="toppings.php?edit_topping=<?php echo $topping['id']; ?>" class="btn btn-sm btn-primary">
                                                                            <i class="fas fa-edit"></i>
                                                                        </a>
                                                                        <a href="toppings.php?delete_topping=<?php echo $topping['id']; ?>" class="btn btn-sm btn-danger" 
                                                                            onclick="return confirm('Are you sure you want to delete this topping?')">
                                                                            <i class="fas fa-times"></i>
                                                                        </a>
                                                                    </div>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="configurable_products.php" class="btn btn-outline-secondary">Back to Products</a>
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
            // Form validation for toppings group
            $("#toppingsGroupForm").validate({
                rules: {
                    toppings_group_name: "required"
                },
                messages: {
                    toppings_group_name: "Please enter toppings group name"
                }
            });
            
            // Form validation for topping
            $("#toppingForm").validate({
                rules: {
                    toppings_group_id: "required",
                    toppings_name: "required"
                },
                messages: {
                    toppings_group_id: "Please select a toppings group",
                    toppings_name: "Please enter topping name"
                }
            });
            
            // Toggle between table and card view based on screen size
            function checkScreenSize() {
                if ($(window).width() < 992) {
                    $('.toppings-table').hide();
                    $('.mobile-toppings-list').show();
                } else {
                    $('.toppings-table').show();
                    $('.mobile-toppings-list').hide();
                }
            }
            
            // Check on load and resize
            checkScreenSize();
            $(window).resize(checkScreenSize);
            
            // Activate the appropriate tab based on URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit_topping') || urlParams.has('delete_topping')) {
                $('#toppings-tab').tab('show');
            }
        });
    </script>
</body>
</html>
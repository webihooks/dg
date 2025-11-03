<?php
// Start the session
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
$bank_data = null;

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_name = $_POST['account_name'] ?? '';
    $bank_name = $_POST['bank_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
    $account_type = $_POST['account_type'] ?? '';
    $ifsc_code = $_POST['ifsc_code'] ?? '';
    $bank_id = $_POST['bank_id'] ?? null;

    // Validate inputs
    if (empty($account_name) || empty($bank_name) || empty($account_number) || empty($account_type) || empty($ifsc_code)) {
        $error_message = "All fields are required!";
    } else {
        if ($bank_id) {
            // Update existing record
            $sql = "UPDATE bank_details SET account_name=?, bank_name=?, account_number=?, account_type=?, ifsc_code=? WHERE id=? AND user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssii", $account_name, $bank_name, $account_number, $account_type, $ifsc_code, $bank_id, $user_id);
        } else {
            // Insert new record
            $sql = "INSERT INTO bank_details (user_id, account_name, bank_name, account_number, account_type, ifsc_code) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $user_id, $account_name, $bank_name, $account_number, $account_type, $ifsc_code);
        }

        if ($stmt->execute()) {
            $success_message = $bank_id ? "Bank details updated successfully!" : "Bank details added successfully!";
        } else {
            $error_message = "Error saving bank details: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $bank_id = $_GET['edit'];
    $sql = "SELECT * FROM bank_details WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $bank_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bank_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($bank_data) {
        $is_edit_mode = true;
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $bank_id = $_GET['delete'];
    $sql = "DELETE FROM bank_details WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $bank_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Bank details deleted successfully!";
    } else {
        $error_message = "Error deleting bank details: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all bank details for the user
$sql = "SELECT * FROM bank_details WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bank_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Bank Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
</head>


<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Bank Details</h4>
                            </div>
                            <div class="card-body">
                                
                                <?php if ($success_message): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <form id="bankForm" method="POST" action="bank-details.php">
                                    <input type="hidden" name="bank_id" value="<?php echo $is_edit_mode ? $bank_data['id'] : ''; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="account_name" class="form-label">Account Holder Name</label>
                                        <input type="text" class="form-control" id="account_name" name="account_name" 
                                            value="<?php echo $is_edit_mode ? htmlspecialchars($bank_data['account_name']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="bank_name" class="form-label">Bank Name</label>
                                        <input style="text-transform: uppercase;" type="text" class="form-control" id="bank_name" name="bank_name" 
                                            value="<?php echo $is_edit_mode ? htmlspecialchars($bank_data['bank_name']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" id="account_number" name="account_number" 
                                            value="<?php echo $is_edit_mode ? htmlspecialchars($bank_data['account_number']) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="account_type" class="form-label">Account Type</label>
                                        <select class="form-select" id="account_type" name="account_type" required>
                                            <option value="">Select Account Type</option>
                                            <option value="Savings" <?php echo ($is_edit_mode && $bank_data['account_type'] == 'Savings') ? 'selected' : ''; ?>>Savings</option>
                                            <option value="Current" <?php echo ($is_edit_mode && $bank_data['account_type'] == 'Current') ? 'selected' : ''; ?>>Current</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="ifsc_code" class="form-label">IFSC Code</label>
                                        <input style="text-transform: uppercase;" type="text" class="form-control" id="ifsc_code" name="ifsc_code" 
                                            value="<?php echo $is_edit_mode ? htmlspecialchars($bank_data['ifsc_code']) : ''; ?>" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $is_edit_mode ? 'Update' : 'Add'; ?> Bank Details
                                    </button>
                                    
                                    <?php if ($is_edit_mode): ?>
                                        <a href="bank-details.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="card-title">Your Bank Account</h4>
                            </div>
                            <div class="card-body">
                                
                                <?php if (empty($bank_details)): ?>
                                    <p>No bank accounts added yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Account Holder</th>
                                                    <th>Bank Name</th>
                                                    <th>Account Number</th>
                                                    <th>Account Type</th>
                                                    <th>IFSC Code</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bank_details as $bank): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($bank['account_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($bank['bank_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($bank['account_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($bank['account_type']); ?></td>
                                                        <td><?php echo htmlspecialchars($bank['ifsc_code']); ?></td>
                                                        <td>
                                                            <a href="bank-details.php?edit=<?php echo $bank['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                            <a href="bank-details.php?delete=<?php echo $bank['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this bank account?')">Delete</a>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        $(document).ready(function() {
            // Form validation
            $("#bankForm").validate({
                rules: {
                    account_name: "required",
                    bank_name: "required",
                    account_number: {
                        required: true,
                        minlength: 9,
                        maxlength: 18
                    },
                    account_type: "required",
                    ifsc_code: {
                        required: true,
                        minlength: 11,
                        maxlength: 11
                    }
                },
                messages: {
                    account_name: "Please enter account holder name",
                    bank_name: "Please enter bank name",
                    account_number: {
                        required: "Please enter account number",
                        minlength: "Account number must be at least 9 characters",
                        maxlength: "Account number cannot exceed 18 characters"
                    },
                    account_type: "Please select account type",
                    ifsc_code: {
                        required: "Please enter IFSC code",
                        minlength: "IFSC code must be 11 characters",
                        maxlength: "IFSC code must be 11 characters"
                    }
                }
            });
        });
    </script>

</body>
</html>
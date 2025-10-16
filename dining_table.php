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
$message = '';
$message_type = 'success'; // default message type

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Fetch current table count and check if record exists
$stmt = $conn->prepare("SELECT id, table_count FROM dining_tables WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($record_id, $table_count);
$record_exists = $stmt->fetch();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_table_count = filter_var($_POST['table_count'], FILTER_SANITIZE_NUMBER_INT);

    if ($new_table_count < 1 || $new_table_count > 50) {
        $message = "Table count must be between 1 and 50.";
        $message_type = 'danger';
    } else {
        if ($record_exists) {
            // Update existing record
            $update = $conn->prepare("UPDATE dining_tables SET table_count = ?, updated_at = NOW() WHERE user_id = ?");
            $update->bind_param("ii", $new_table_count, $user_id);
            $update->execute();
            $update->close();
            $message = "Table count updated successfully.";
        } else {
            // Insert new record
            $insert = $conn->prepare("INSERT INTO dining_tables (user_id, table_count, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $insert->bind_param("ii", $user_id, $new_table_count);
            $insert->execute();
            $insert->close();
            $message = "Table count added successfully.";
        }

        $table_count = $new_table_count;
        $message_type = 'success';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Dining Table</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php echo ($role === 'admin') ? include 'admin_menu.php' : include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Dining Table</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" id="diningTableForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label for="table_count" class="form-label">Number of Tables</label>
                                            <input type="number" name="table_count" id="table_count" class="form-control"
                                                value="<?php echo htmlspecialchars($table_count); ?>" min="1" max="50" required />
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button type="submit" class="btn btn-primary">Save Table Count</button>
                                        </div>
                                    </div>
                                </form>
                            </div> <!-- card-body -->
                        </div> <!-- card -->
                    </div> <!-- col -->
                </div> <!-- row -->
            </div> <!-- container -->
            <?php include 'footer.php'; ?>
        </div> <!-- page-content -->
    </div> <!-- wrapper -->

    <script>
        $(document).ready(function () {
            $("#diningTableForm").validate({
                rules: {
                    table_count: {
                        required: true,
                        number: true,
                        min: 1,
                        max: 50
                    }
                },
                messages: {
                    table_count: {
                        required: "Please enter number of tables.",
                        number: "Please enter a valid number.",
                        min: "Minimum 1 table required.",
                        max: "Maximum 50 tables allowed."
                    }
                }
            });
        });
    </script>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>

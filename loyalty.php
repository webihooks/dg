<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Fetch user details - Don't remove this
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

if (isset($_GET['updated'])) {
    $message = "Loyalty card updated successfully.";
    $message_type = "success";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_loyalty'])) {
    $card_number = trim($_POST['card_number']);
    $full_name = trim($_POST['full_name']);
    $mobile = trim($_POST['mobile']);
    $address = trim($_POST['address']);
    $errors = [];

    if (!preg_match('/^[0-9]{6,20}$/', $card_number)) {
        $errors[] = "Card number must be 6–20 digits.";
    }
    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors[] = "Mobile must be 10 digits.";
    }
    if (strlen($full_name) < 3) {
        $errors[] = "Full name must be at least 3 characters.";
    }
    if (strlen($address) < 10) {
        $errors[] = "Address must be at least 10 characters.";
    }

    $check_sql = "SELECT id FROM loyalty_cards WHERE (card_number = ? OR mobile = ?) AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ssi", $card_number, $mobile, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $errors[] = "Card number or mobile already registered.";
    }
    $check_stmt->close();

    if (empty($errors)) {
        $insert_sql = "INSERT INTO loyalty_cards (user_id, card_number, full_name, mobile, address, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("issss", $user_id, $card_number, $full_name, $mobile, $address);
        if ($stmt->execute()) {
            header("Location: loyalty.php");
            exit();
        } else {
            $message = "Insert failed: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = implode("<br>", $errors);
        $message_type = "danger";
    }
}

// Fetch all loyalty cards for user
$sql = "SELECT id, card_number, full_name, mobile, address, created_at FROM loyalty_cards WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Loyalty Card Registration</title>
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
    <?php include 'menu.php'; ?>
    <div class="page-content">
        <div class="container">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Loyalty Card Registration</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($message)): ?>
                                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                            <?php endif; ?>

                            <form method="POST" id="loyaltyForm">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label>Card Number *</label>
                                        <input type="text" class="form-control" name="card_number" required pattern="[0-9]{6,20}">
                                    </div>
                                    <div class="col-md-4">
                                        <label>Full Name *</label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label>Mobile Number *</label>
                                        <input type="text" class="form-control" name="mobile" required pattern="[0-9]{10}">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label>Address *</label>
                                        <textarea class="form-control" name="address" required></textarea>
                                    </div>
                                </div>
                                <button type="submit" name="register_loyalty" class="btn btn-primary">Register</button>
                                <a href="export_loyalty_excel.php" class="btn btn-success">Export to Excel</a>
                            </form>

                            <hr>
                            <h5 class="mt-4">Registered Loyalty Cards</h5>

                            <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search by name, card or mobile">

                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Card Number</th>
                                        <th>Name</th>
                                        <th>Mobile</th>
                                        <th>Address</th>
                                        <th>Registered At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="loyaltyTable">
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['card_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                            <td><?php echo htmlspecialchars($row['address']); ?></td>
                                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                            <td>
                                                <a href="edit_loyalty.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $row['id']; ?>">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'footer.php'; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Ajax delete
    $('.delete-btn').click(function() {
        if (confirm('Are you sure you want to delete this card?')) {
            const id = $(this).data('id');
            $.ajax({
                url: 'delete_loyalty.php',
                type: 'POST',
                data: { id: id },
                success: function(response) {
                    location.reload();
                },
                error: function() {
                    alert('Failed to delete card.');
                }
            });
        }
    });

    // Search filter
    $('#searchInput').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('#loyaltyTable tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
</script>

<script src="assets/js/vendor.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>

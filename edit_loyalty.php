<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo "Invalid ID.";
    exit();
}

$message = '';
$message_type = '';

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Fetch card details
$sql = "SELECT card_number, full_name, mobile, address FROM loyalty_cards WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Card not found or unauthorized access.";
    exit();
}
$data = $result->fetch_assoc();
$stmt->close();

// Update logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Check for duplicate card number or mobile (excluding current record)
    $check_sql = "SELECT id FROM loyalty_cards WHERE (card_number = ? OR mobile = ?) AND id != ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ssii", $card_number, $mobile, $id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $errors[] = "Card number or mobile already in use.";
    }
    $check_stmt->close();

    if (empty($errors)) {
        $update_sql = "UPDATE loyalty_cards SET card_number = ?, full_name = ?, mobile = ?, address = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssii", $card_number, $full_name, $mobile, $address, $id, $user_id);
        if ($update_stmt->execute()) {
            header("Location: loyalty.php?updated=1");
            exit();
        } else {
            $message = "Update failed. Try again.";
            $message_type = "danger";
        }
        $update_stmt->close();
    } else {
        $message = implode("<br>", $errors);
        $message_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Edit Loyalty Card</title>
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
                            <h4 class="card-title">Edit Loyalty Card</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($message)): ?>
                                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="card_number">Card Number *</label>
                                    <input type="text" class="form-control" name="card_number" required pattern="[0-9]{6,20}" value="<?php echo htmlspecialchars($data['card_number']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required value="<?php echo htmlspecialchars($data['full_name']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="mobile">Mobile Number *</label>
                                    <input type="text" class="form-control" name="mobile" required pattern="[0-9]{10}" value="<?php echo htmlspecialchars($data['mobile']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="address">Address *</label>
                                    <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($data['address']); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Update</button>
                                <a href="loyalty.php" class="btn btn-secondary">Back</a>
                            </form>
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
</body>
</html>

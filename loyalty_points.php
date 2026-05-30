<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current settings
$sql = "SELECT * FROM loyalty_settings WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();
$stmt->close();

// If no settings exist, create default record
if (!$settings) {
    $insert = "INSERT INTO loyalty_settings (user_id) VALUES (?)";
    $stmt = $conn->prepare($insert);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    // Refresh to get new record
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $redemption_points = intval($_POST['redemption_points']);
    $redemption_currency_amount = floatval($_POST['redemption_currency_amount']);
    $earn_points_per_currency = intval($_POST['earn_points_per_currency']);
    $welcome_points = intval($_POST['welcome_points']);

    if ($redemption_points <= 0 || $redemption_currency_amount <= 0 || $earn_points_per_currency <= 0 || $welcome_points <= 0) {
        $error = "All values must be positive numbers.";
    } else {
        $update = "UPDATE loyalty_settings 
                   SET redemption_points = ?, 
                       redemption_currency_amount = ?, 
                       earn_points_per_currency = ?,
                       welcome_points = ?
                   WHERE user_id = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("idiid", $redemption_points, $redemption_currency_amount, $earn_points_per_currency, $welcome_points, $user_id);
        if ($stmt->execute()) {
            $message = "Loyalty settings updated successfully!";
            // Refresh settings
            $settings['redemption_points'] = $redemption_points;
            $settings['redemption_currency_amount'] = $redemption_currency_amount;
            $settings['earn_points_per_currency'] = $earn_points_per_currency;
            $settings['welcome_points'] = $welcome_points;
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get currency symbol for display (from user's country)
$user_sql = "SELECT country FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();
$country = $user_data['country'] ?? 'India';

function getCurrencySymbol($country) {
    switch ($country) {
        case 'UAE': return 'AED ';
        case 'UK': return '£';
        case 'USA': return '$';
        default: return '₹';
    }
}
$currencySymbol = getCurrencySymbol($country);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Loyalty Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/app.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="assets/js/config.js"></script>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>
        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Loyalty Program Settings</h4>
                                <p class="text-muted">Define how customers earn and redeem loyalty points.</p>
                            </div>
                            <div class="card-body">
                                <?php if ($message): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Redemption Points</label>
                                            <input type="number" class="form-control" name="redemption_points" 
                                                   value="<?php echo $settings['redemption_points']; ?>" required min="1">
                                            <small class="text-muted">Number of points needed to get the discount below.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Redemption Discount Amount (<?php echo $currencySymbol; ?>)</label>
                                            <input type="number" step="0.01" class="form-control" name="redemption_currency_amount" 
                                                   value="<?php echo $settings['redemption_currency_amount']; ?>" required min="0.01">
                                            <small class="text-muted">Discount value when customer redeems the above points.</small>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Points Earned per <?php echo $currencySymbol; ?>1 spent</label>
                                            <input type="number" class="form-control" name="earn_points_per_currency" 
                                                   value="<?php echo $settings['earn_points_per_currency']; ?>" required min="1">
                                            <small class="text-muted">Example: 1 point per rupee, or 10 points per rupee.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Welcome Bonus Points (new customers)</label>
                                            <input type="number" class="form-control" name="welcome_points" 
                                                   value="<?php echo $settings['welcome_points']; ?>" required min="0">
                                            <small class="text-muted">Points awarded when a customer logs in for the first time.</small>
                                        </div>
                                    </div>

                                    <div class="alert alert-info mt-3">
                                        <strong>How it works:</strong><br>
                                        • Customer earns <?php echo $settings['earn_points_per_currency']; ?> point(s) for every <?php echo $currencySymbol; ?>1 spent on the final total.<br>
                                        • Customer can redeem <?php echo number_format($settings['redemption_points']); ?> points to get a discount of <?php echo $currencySymbol; ?><?php echo number_format($settings['redemption_currency_amount'], 2); ?>.<br>
                                        • New customers receive <?php echo number_format($settings['welcome_points']); ?> bonus points on their first login.
                                    </div>

                                    <button type="submit" name="save_settings" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Settings
                                    </button>
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
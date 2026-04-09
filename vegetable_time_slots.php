<?php
// vegetable_time_slots.php - Delivery Time Slots Management for Vegetable Seller
session_start();
require_once 'db_connection.php';
require_once 'vegetable_helper.php'; // Contains ensureVegetableSellerTables()

// Check if user is logged in and is a vegetable seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vegetable_seller') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Ensure all required tables exist for this user
ensureVegetableSellerTables($user_id, $conn);

$slots_table = "vegetable_time_slots_{$user_id}";
$settings_table = "vegetable_settings_{$user_id}";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle slot active/inactive
    if (isset($_POST['toggle_slot'])) {
        $slot_id = (int)$_POST['slot_id'];
        $is_active = (int)$_POST['is_active'];
        $stmt = $conn->prepare("UPDATE `$slots_table` SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $slot_id);
        if ($stmt->execute()) {
            $message = "Slot updated successfully.";
        } else {
            $error = "Failed to update slot.";
        }
        $stmt->close();
    }
    // Add new slot
    elseif (isset($_POST['add_slot'])) {
        $slot_name = trim($_POST['slot_name']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $display_order = (int)$_POST['display_order'];
        
        if (empty($slot_name) || empty($start_time) || empty($end_time)) {
            $error = "Please fill all fields.";
        } elseif (strtotime($end_time) <= strtotime($start_time)) {
            $error = "End time must be after start time.";
        } else {
            $stmt = $conn->prepare("INSERT INTO `$slots_table` (slot_name, start_time, end_time, display_order, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("sssi", $slot_name, $start_time, $end_time, $display_order);
            if ($stmt->execute()) {
                $message = "Slot added successfully.";
            } else {
                $error = "Failed to add slot.";
            }
            $stmt->close();
        }
    }
    // Delete slot
    elseif (isset($_POST['delete_slot'])) {
        $slot_id = (int)$_POST['slot_id'];
        $stmt = $conn->prepare("DELETE FROM `$slots_table` WHERE id = ?");
        $stmt->bind_param("i", $slot_id);
        if ($stmt->execute()) {
            $message = "Slot deleted successfully.";
        } else {
            $error = "Failed to delete slot.";
        }
        $stmt->close();
    }
    // Save general settings (instant delivery, delivery charge, tax rate only)
    elseif (isset($_POST['save_settings'])) {
        $instant_enabled = isset($_POST['instant_enabled']) ? 1 : 0;
        $instant_charge = (float)$_POST['instant_charge'];
        $delivery_charge = (float)$_POST['delivery_charge'];
        $tax_rate = (float)$_POST['tax_rate'];
        
        $stmt = $conn->prepare("UPDATE `$settings_table` SET 
            instant_delivery_enabled = ?, 
            instant_delivery_charge = ?, 
            delivery_charge = ?, 
            tax_rate = ? 
            WHERE id = (SELECT id FROM (SELECT id FROM `$settings_table` LIMIT 1) AS tmp)");
        // If no row exists, insert instead
        $check = $conn->query("SELECT COUNT(*) as cnt FROM `$settings_table`");
        $row = $check->fetch_assoc();
        if ($row['cnt'] == 0) {
            $stmt = $conn->prepare("INSERT INTO `$settings_table` 
                (instant_delivery_enabled, instant_delivery_charge, delivery_charge, tax_rate) 
                VALUES (?, ?, ?, ?)");
        }
        $stmt->bind_param("iddd", $instant_enabled, $instant_charge, $delivery_charge, $tax_rate);
        if ($stmt->execute()) {
            $message = "Settings saved successfully.";
        } else {
            $error = "Failed to save settings: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all time slots
$slots = $conn->query("SELECT * FROM `$slots_table` ORDER BY display_order ASC, start_time ASC");

// Fetch settings (if any)
$settings = $conn->query("SELECT * FROM `$settings_table` LIMIT 1")->fetch_assoc();
if (!$settings) {
    // Default settings if none exist
    $settings = [
        'instant_delivery_enabled' => 0,
        'instant_delivery_charge' => 0,
        'delivery_charge' => 0,
        'tax_rate' => 0
    ];
}

// Helper to get currency symbol (from user's country)
function getCurrencySymbol($country) {
    $symbols = ['India'=>'₹', 'UAE'=>'AED', 'UK'=>'£', 'USA'=>'$'];
    return $symbols[$country] ?? '₹';
}
// Fetch user country from session or database
$user_country = 'India'; // default
$stmt = $conn->prepare("SELECT country FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_country);
$stmt->fetch();
$stmt->close();
$currencySymbol = getCurrencySymbol($user_country);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Delivery Time Slots - Vegetable Seller</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="msapplication-TileImage" content="https://deegeecard.com/images/dg_logo.png">
    <meta name="application-name" content="DeeGeeCard">
    <meta name="mobile-web-app-capable" content="yes">

    <link href="assets/css/vendor.min.css" rel="stylesheet">
    <link href="assets/css/icons.min.css" rel="stylesheet">
    <link href="assets/css/app.min.css" rel="stylesheet">
    <script src="assets/js/config.js"></script>
    <style>
        .slot-list { max-height: 400px; overflow-y: auto; }
        .slot-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #eee; }
        .slot-name { font-weight: 500; }
        .slot-actions button { margin-left: 5px; }
        .card-header { background: #f8f9fa; }
        @media (max-width: 768px) {
            .slot-item { flex-direction: column; align-items: flex-start; gap: 8px; }
            .slot-actions { width: 100%; display: flex; justify-content: flex-end; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'vegetable_seller_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Time Slots Management Card -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Delivery & Takeaway Time Slots</h4>
                                <p class="text-muted">Manage available time slots for scheduled orders.</p>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Existing Slots</h5>
                                        <div class="slot-list">
                                            <?php if ($slots && $slots->num_rows > 0): ?>
                                                <?php while($slot = $slots->fetch_assoc()): ?>
                                                <div class="slot-item">
                                                    <div class="slot-name">
                                                        <?php echo htmlspecialchars($slot['slot_name']); ?>
                                                        <small class="text-muted">(<?php echo date('g:i A', strtotime($slot['start_time'])); ?> - <?php echo date('g:i A', strtotime($slot['end_time'])); ?>)</small>
                                                    </div>
                                                    <div class="slot-actions">
                                                        <form method="POST" style="display:inline-block">
                                                            <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                                            <input type="hidden" name="is_active" value="<?php echo $slot['is_active'] ? 0 : 1; ?>">
                                                            <button type="submit" name="toggle_slot" class="btn btn-sm <?php echo $slot['is_active'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                                <?php echo $slot['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display:inline-block" onsubmit="return confirm('Delete this slot?')">
                                                            <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                                            <button type="submit" name="delete_slot" class="btn btn-sm btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <div class="alert alert-info">No time slots defined. Add your first slot below.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Add New Slot</h5>
                                        <form method="POST">
                                            <div class="mb-2">
                                                <label>Slot Name (e.g., 9AM-10AM)</label>
                                                <input type="text" name="slot_name" class="form-control" required>
                                            </div>
                                            <div class="row">
                                                <div class="col">
                                                    <label>Start Time</label>
                                                    <input type="time" name="start_time" class="form-control" required>
                                                </div>
                                                <div class="col">
                                                    <label>End Time</label>
                                                    <input type="time" name="end_time" class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="mb-2 mt-2">
                                                <label>Display Order (lower number appears first)</label>
                                                <input type="number" name="display_order" class="form-control" value="0">
                                            </div>
                                            <button type="submit" name="add_slot" class="btn btn-primary">Add Slot</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delivery Settings Card (without business fields) -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h4 class="card-title">Delivery & Instant Order Settings</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="instant_enabled" id="instant_enabled" value="1" <?php echo $settings['instant_delivery_enabled'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="instant_enabled">Enable Instant Delivery (Chargeable)</label>
                                                </div>
                                                <small class="text-muted">Instant delivery allows customers to get their order immediately for an extra fee.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label>Instant Delivery Charge (<?php echo $currencySymbol; ?>)</label>
                                                <input type="number" step="0.01" name="instant_charge" class="form-control" value="<?php echo htmlspecialchars($settings['instant_delivery_charge']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label>Standard Delivery Charge (<?php echo $currencySymbol; ?>)</label>
                                                <input type="number" step="0.01" name="delivery_charge" class="form-control" value="<?php echo htmlspecialchars($settings['delivery_charge']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label>Tax Rate (%)</label>
                                                <input type="number" step="0.01" name="tax_rate" class="form-control" value="<?php echo htmlspecialchars($settings['tax_rate']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" name="save_settings" class="btn btn-success">Save Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'footer.php'; ?>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
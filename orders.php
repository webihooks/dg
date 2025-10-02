<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Kolkata');

require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success';

// Date range handling
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = date('Y-m-d');
}
if ($to_date < $from_date) {
    $to_date = $from_date;
}

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Fetch business information
$business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $user_id);
$business_stmt->execute();
$business_stmt->bind_result($business_name, $business_address);
$business_stmt->fetch();
$business_stmt->close();

if (empty($business_name)) {
    $business_name = "Your Restaurant";
    $business_address = "123 Restaurant Street, City";
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $allowed_statuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        $message = "Invalid status selected";
        $message_type = "danger";
    } else {
        $check_sql = "SELECT user_id FROM orders WHERE order_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $order_id);
        $check_stmt->execute();
        $check_stmt->bind_result($order_user_id);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($order_user_id == $user_id) {
            $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_status, $order_id);
            
            if ($update_stmt->execute()) {
                $message = "Order status updated successfully!";
            } else {
                $message = "Error updating order status: " . $conn->error;
                $message_type = "danger";
            }
            $update_stmt->close();
        } else {
            $message = "You don't have permission to update this order.";
            $message_type = "danger";
        }
    }
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    
    $check_sql = "SELECT user_id, status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_user_id, $current_status);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_user_id == $user_id) {
        if (in_array($current_status, ['Pending', 'Confirmed', 'Preparing'])) {
            $update_sql = "UPDATE orders SET status = 'Cancelled' WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $order_id);
            
            if ($update_stmt->execute()) {
                $message = "Order cancelled successfully!";
            } else {
                $message = "Error cancelling order: " . $conn->error;
                $message_type = "danger";
            }
            $update_stmt->close();
        } else {
            $message = "Order cannot be cancelled at this stage.";
            $message_type = "danger";
        }
    } else {
        $message = "You don't have permission to cancel this order.";
        $message_type = "danger";
    }
}

// Handle AJAX status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_status'])) {
    header('Content-Type: application/json');
    
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $allowed_statuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    $check_sql = "SELECT user_id FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_user_id);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_user_id == $user_id) {
        $update_sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating order status']);
        }
        $update_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
    }
    exit();
}

// Handle AJAX order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cancel_order'])) {
    header('Content-Type: application/json');
    
    $order_id = $_POST['order_id'];
    
    $check_sql = "SELECT user_id, status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_user_id, $current_status);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_user_id == $user_id) {
        if (in_array($current_status, ['Pending', 'Confirmed', 'Preparing'])) {
            $update_sql = "UPDATE orders SET status = 'Cancelled', updated_at = NOW() WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $order_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Order cancelled successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error cancelling order']);
            }
            $update_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled at this stage']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
    }
    exit();
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 200;
$offset = ($page - 1) * $per_page;

// Get total count of orders
$count_sql = "SELECT COUNT(*) FROM orders WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("iss", $user_id, $from_date, $to_date);
$count_stmt->execute();
$count_stmt->bind_result($total_orders);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_orders / $per_page);

// Fetch orders with items
$orders_sql = "SELECT 
    o.order_id, 
    o.customer_name, 
    o.customer_phone, 
    o.order_type, 
    o.delivery_address, 
    o.table_number, 
    o.status, 
    o.subtotal, 
    o.discount_amount, 
    o.discount_type, 
    o.gst_amount, 
    o.delivery_charge, 
    o.total_amount, 
    o.created_at,
    o.order_notes,
    o.updated_at,
    COUNT(oi.item_id) as item_count
FROM orders o
LEFT JOIN order_items oi ON o.order_id = oi.order_id
WHERE o.user_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
GROUP BY o.order_id
ORDER BY o.created_at DESC
LIMIT ? OFFSET ?";

$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("issii", $user_id, $from_date, $to_date, $per_page, $offset);
$orders_stmt->execute();
$result = $orders_stmt->get_result();
$orders = [];

while ($order = $result->fetch_assoc()) {
    $items_sql = "SELECT product_name, price, quantity FROM order_items WHERE order_id = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $order['order_id']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $order['items'] = $items_result->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();
    
    // Calculate timer information
    $order_created = strtotime($order['created_at']);
    $current_time = time();
    $time_elapsed = $current_time - $order_created;
    $time_limit = 30 * 60; // 30 minutes in seconds
    $time_remaining = $time_limit - $time_elapsed;
    
    $order['timer_remaining'] = max(0, $time_remaining);
    $order['is_delayed'] = $time_elapsed > $time_limit;
    $order['is_completed_on_time'] = ($order['status'] === 'Completed' && !$order['is_delayed']);
    
    $orders[] = $order;
}
$orders_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Order Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css?<?php echo time(); ?>" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">

<style>
    .table tbody tr:last-child td {
        border-bottom: 1px solid #dee2e6 !important;
    }
    .btn-info.update-status-btn,.btn-info.update-status-btn.wave-pulse,.btn-success.update-status-btn{position:relative;overflow:hidden}.bg-warning{background:red!important}.status-badge{padding:5px 10px;border-radius:20px;font-weight:700;font-size:.8em}.status-Pending{background-color:#ffc107;color:#000}.status-Confirmed{background-color:#17a2b8;color:#fff}.status-Preparing{background-color:#fd7e14;color:#fff}.status-Ready{background-color:#28a745;color:#fff}.status-Completed{background-color:orange;color:#fff}.status-Cancelled{background-color:#dc3545;color:#fff}.bi-arrow-repeat.spin{animation:1s linear infinite spin}@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}.timer{display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:6px;background-color:#000;font-weight:700;color:#fff}.timer.warning{background-color:orange;color:#000}.timer.danger{background-color:red;color:#fff;animation:1s infinite blink}@keyframes blink{0%,50%{opacity:1}100%,51%{opacity:.5}}.timer-column{min-width:120px}.table.order th:last-child{width:310px}.btn-info,.btn-info:hover{background:orange;border-color:orange}.custom-toast{position:fixed;top:20px;right:20px;z-index:9999;min-width:300px}.btn-success.update-status-btn{border:2px solid #198754}.btn-success.update-status-btn.pulse-border{animation:2s infinite borderPulse}@keyframes borderPulse{0%{box-shadow:0 0 0 0 rgba(25,135,84,.7);border-color:#198754}70%{box-shadow:0 0 0 10px rgba(25,135,84,0);border-color:#20c997}100%{box-shadow:0 0 0 0 rgba(25,135,84,0);border-color:#198754}}.btn-success.update-status-btn.glow-border{animation:1.5s ease-in-out infinite alternate borderGlow}@keyframes borderGlow{from{box-shadow:0 0 5px #198754,0 0 10px #198754,0 0 15px #198754;border-color:#198754}to{box-shadow:0 0 10px #20c997,0 0 20px #20c997,0 0 30px #20c997;border-color:#20c997}}.btn-info.update-status-btn.ring-pulse,.btn-success.update-status-btn.ring-pulse{position:relative}.btn-success.update-status-btn.ring-pulse::before{content:'';position:absolute;top:-4px;left:-4px;right:-4px;bottom:-4px;border:2px solid #198754;border-radius:.375rem;animation:2s linear infinite ringPulse;opacity:0}@keyframes ringPulse{0%{transform:scale(1);opacity:1}100%{transform:scale(1.1);opacity:0}}.btn-success.update-status-btn.double-pulse{animation:2s infinite doublePulse}@keyframes doublePulse{0%{box-shadow:0 0 0 0 rgba(25,135,84,.4),0 0 0 0 rgba(32,201,151,.4)}50%{box-shadow:0 0 0 8px rgba(25,135,84,.2),0 0 0 16px rgba(32,201,151,.1)}100%{box-shadow:0 0 0 0 rgba(25,135,84,0),0 0 0 0 rgba(32,201,151,0)}}.btn-success.update-status-btn.heartbeat{animation:1.5s ease-in-out infinite both heartbeat}@keyframes heartbeat{from{transform:scale(1);box-shadow:0 0 0 0 rgba(25,135,84,.7)}50%{transform:scale(1.03);box-shadow:0 0 0 8px rgba(25,135,84,0)}to{transform:scale(1);box-shadow:0 0 0 0 rgba(25,135,84,0)}}.btn-info.update-status-btn{border:2px solid #ff6c2f}.btn-info.update-status-btn.pulse-border{animation:2s infinite borderPulseOrange}@keyframes borderPulseOrange{0%{box-shadow:0 0 0 0 rgba(255,108,47,.7);border-color:#ff6c2f}70%{box-shadow:0 0 0 10px rgba(255,108,47,0);border-color:#ff8c5a}100%{box-shadow:0 0 0 0 rgba(255,108,47,0);border-color:#ff6c2f}}.btn-info.update-status-btn.glow-border{animation:1.5s ease-in-out infinite alternate borderGlowOrange}@keyframes borderGlowOrange{from{box-shadow:0 0 5px #ff6c2f,0 0 10px #ff6c2f,0 0 15px #ff6c2f;border-color:#ff6c2f}to{box-shadow:0 0 10px #ff8c5a,0 0 20px #ff8c5a,0 0 30px #ff8c5a;border-color:#ff8c5a}}.btn-info.update-status-btn.ring-pulse::before{content:'';position:absolute;top:-4px;left:-4px;right:-4px;bottom:-4px;border:2px solid #ff6c2f;border-radius:.375rem;animation:2s linear infinite ringPulseOrange;opacity:0}@keyframes ringPulseOrange{0%{transform:scale(1);opacity:1}100%{transform:scale(1.1);opacity:0}}.btn-info.update-status-btn.double-pulse{animation:2s infinite doublePulseOrange}@keyframes doublePulseOrange{0%{box-shadow:0 0 0 0 rgba(255,108,47,.4),0 0 0 0 rgba(255,140,90,.4)}50%{box-shadow:0 0 0 8px rgba(255,108,47,.2),0 0 0 16px rgba(255,140,90,.1)}100%{box-shadow:0 0 0 0 rgba(255,108,47,0),0 0 0 0 rgba(255,140,90,0)}}.btn-info.update-status-btn.heartbeat{animation:1.5s ease-in-out infinite both heartbeatOrange}@keyframes heartbeatOrange{from{transform:scale(1);box-shadow:0 0 0 0 rgba(255,108,47,.7)}50%{transform:scale(1.03);box-shadow:0 0 0 8px rgba(255,108,47,0)}to{transform:scale(1);box-shadow:0 0 0 0 rgba(255,108,47,0)}}.btn-info.update-status-btn.fire-pulse{animation:2s ease-in-out infinite firePulse}@keyframes firePulse{0%,100%{box-shadow:0 0 5px #ff6c2f,0 0 10px #ff6c2f;border-color:#ff6c2f}50%{box-shadow:0 0 15px #ff8c5a,0 0 25px #ff8c5a,0 0 35px #ff8c5a;border-color:#ff8c5a}}.btn-info.update-status-btn.wave-pulse::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);animation:2s infinite wavePulse}@keyframes wavePulse{0%{left:-100%}100%{left:100%}}@media (max-width:768px){.mobile_table .update-status-btn[data-new-status=Completed],.mobile_table .update-status-btn[data-new-status=Ready]{width:100%;margin:5px 0;display:block;padding:10px 20px;font-size:15px;text-align:left}.mobile_table td[data-label=Actions]{text-align:center}.timer-column{min-width:100px}.mobile_table tr{position:relative}.mobile_table .table td.timer-column:before{display:none}.mobile_table .table td.timer-column{border-bottom:0}.clountdown_group{position:absolute;top:76px;z-index:99;right:28px}}.whatsapp-fallback-link{background:#25d366;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;font-weight:700;margin:5px;display:inline-block;animation:2s infinite pulse-green}@keyframes pulse-green{0%{transform:scale(1);box-shadow:0 0 0 0 rgba(37,211,102,.7)}70%{transform:scale(1.05);box-shadow:0 0 0 10px rgba(37,211,102,0)}100%{transform:scale(1);box-shadow:0 0 0 0 rgba(37,211,102,0)}}

@media (max-width: 768px) {
  .mobile_table td[data-label="Actions"] {
    min-height: 70px;
  }
}
</style>

</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include ($role === 'admin') ? 'admin_menu.php' : 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Order Management
                                    <div class="float-end order_section">
                                        <form method="GET" class="d-inline-flex align-items-center">
                                            <div class="me-2">
                                                <label class="form-label small mb-0">From</label>
                                                <input type="date" name="from_date" class="form-control" 
                                                       value="<?php echo htmlspecialchars($from_date); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            <div class="me-2">
                                                <label class="form-label small mb-0">To</label>
                                                <input type="date" name="to_date" class="form-control" 
                                                       value="<?php echo htmlspecialchars($to_date); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            <button type="submit" class="btn btn-primary align-self-end">View Orders</button>  
                                        </form>
                                    </div>
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <h5 class="mb-3">
                                    <?php 
                                    $today = date('Y-m-d');
                                    if ($from_date == $today && $to_date == $today) {
                                        echo "Today's Orders (" . date('F j, Y', strtotime($from_date)) . ")";
                                    } else {
                                        echo "Orders from " . date('F j, Y', strtotime($from_date)) . " to " . date('F j, Y', strtotime($to_date));
                                    }
                                    ?>
                                </h5>

                                <?php if (empty($orders)): ?>
                                    <div class="alert alert-info">
                                        <?php 
                                        if ($from_date == $today && $to_date == $today) {
                                            echo "No orders found for today (" . date('F j, Y', strtotime($from_date)) . ")";
                                        } else {
                                            echo "No orders found from " . date('F j, Y', strtotime($from_date)) . " to " . date('F j, Y', strtotime($to_date));
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive mobile_table">
                                        <table class="table order table-hover mb-0">
    <thead>
        <tr>
            <th>Sr. No.</th>
            <th>Order ID</th>
            <th>Date & Time</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Timer</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="ordersTableBody">
        <?php foreach ($orders as $index => $order): ?>
            <tr id="order-<?php echo $order['order_id']; ?>" data-order-id="<?php echo $order['order_id']; ?>">
                <td data-label="Sr. No."><?php echo $index + 1 + $offset; ?></td>
                <td data-label="Order ID">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                <td data-label="Date & Time">
                    <?php echo date('d/m/Y h:i A', strtotime($order['created_at'])); ?>
                </td>
                <td data-label="Customer"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                <td data-label="Type">
                    <?php 
                    if ($order['order_type'] === 'dining') {
                        echo 'Dining - Table ' . htmlspecialchars($order['table_number']);
                    } else {
                        echo ucfirst(htmlspecialchars($order['order_type']));
                    }
                    ?>
                </td>
                <td data-label="Items"><?php echo htmlspecialchars($order['item_count']); ?></td>
                <td data-label="Total">₹<?php echo number_format($order['total_amount']); ?></td>
                <td data-label="Status">
                    <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                        <?php echo htmlspecialchars($order['status']); ?>
                    </span>
                </td>
                <td data-label="Timer" class="timer-column">
                    <div class="clountdown_group">
                        <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing', 'Ready'])): ?>
                            <div class="timer" 
                                 data-created-at="<?php echo $order['created_at']; ?>"
                                 data-order-id="<?php echo $order['order_id']; ?>">
                                <i class="bi bi-clock"></i>
                                <span class="timer-display">
                                    <?php
                                    $minutes = floor($order['timer_remaining'] / 60);
                                    $seconds = $order['timer_remaining'] % 60;
                                    echo sprintf('%02d:%02d', $minutes, $seconds);
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td data-label="Actions">
                    <button class="btn btn-sm btn-primary view-order" 
                            data-order-id="<?php echo $order['order_id']; ?>"
                            data-bs-toggle="modal" 
                            data-bs-target="#orderModal">
                        <i class="bi bi-eye"></i> View
                    </button>

                    <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing'])): ?>
                        <button class="btn btn-sm btn-danger cancel-order" 
                                data-order-id="<?php echo $order['order_id']; ?>">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['Pending', 'Confirmed', 'Preparing'])): ?>
                        <button class="btn btn-sm btn-success update-status-btn" 
                                data-order-id="<?php echo $order['order_id']; ?>"
                                data-new-status="Ready">
                            <i class="bi bi-check-circle"></i> Ready
                        </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['Ready'])): ?>
                        <button class="btn btn-sm btn-info update-status-btn" 
                                data-order-id="<?php echo $order['order_id']; ?>"
                                data-new-status="Completed">
                            <i class="bi bi-check-all"></i> Complete
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
                                    </div>

                                    <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-center mt-3">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                                            <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
<div class="modal fade order-modal" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderModalLabel">Order Details #<span id="modalOrderId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Customer Information</h6>
                        
                        <!-- Customer Name with Copy Button -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <p class="mb-0"><strong>Name:</strong> <span id="modalCustomerName"></span></p>
                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="modalCustomerName">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        
                        <!-- Customer Phone with Copy Button -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <p class="mb-0"><strong>Phone:</strong> <span id="modalCustomerPhone"></span></p>
                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="modalCustomerPhone">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        
                        <!-- Delivery Address with Copy Button -->
                        <div class="d-flex justify-content-between align-items-center mb-2" id="modalDeliveryAddress">
                            <p class="mb-0"><strong>Address:</strong> <span id="modalAddressText"></span></p>
                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="modalAddressText">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        
                        <!-- Table Number -->
                        <p id="modalTableNumber" class="mb-2"><strong>Table Number:</strong> <span id="modalTableText"></span></p>
                        
                        <!-- Order Notes -->
                        <div id="modalOrderNotesContainer">
                            <h6>Order Notes</h6>
                            <p id="modalOrderNotes"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Order Summary</h6>
                        <p><strong>Order Type:</strong> <span id="modalOrderType"></span></p>
                        <p><strong>Order Date:</strong> <span id="modalOrderDate"></span></p>
                        <p><strong>Status:</strong> <span id="modalOrderStatus" class="status-badge"></span></p>
                    </div>
                </div>
                
                <h6>Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm order-items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="modalOrderItems">
                            <!-- Items will be inserted here by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6 offset-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Subtotal:</strong></td>
                                <td>₹<span id="modalSubtotal"></span></td>
                            </tr>
                            <tr id="modalDiscountRow">
                                <td><strong>Discount:</strong></td>
                                <td>-₹<span id="modalDiscountAmount"></span> (<span id="modalDiscountType"></span>)</td>
                            </tr>
                            <tr id="modalGstRow">
                                <td><strong>GST:</strong></td>
                                <td>₹<span id="modalGstAmount"></span></td>
                            </tr>
                            <tr id="modalDeliveryRow">
                                <td><strong>Delivery Charge:</strong></td>
                                <td>₹<span id="modalDeliveryCharge"></span></td>
                            </tr>
                            <tr class="table-active">
                                <td><strong>Total Amount:</strong></td>
                                <td><strong>₹<span id="modalTotalAmount"></span></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" action="orders.php" class="d-inline" id="statusUpdateForm">
                    <input type="hidden" name="order_id" id="modalFormOrderId">
                    <div class="input-group">
                        <select class="form-select" name="new_status" id="modalStatusSelect">
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Ready">Ready</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>

                <form method="POST" action="orders.php" class="d-inline ms-2" id="cancelOrderForm">
                    <input type="hidden" name="order_id" id="modalCancelOrderId">
                    <button type="submit" name="cancel_order" class="btn btn-danger" style="display:none;">
                        <i class="bi bi-x-circle"></i> Cancel Order
                    </button>
                </form>
                
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
<script>
/**
 * Apply border pulse animation to Complete buttons
 * Adds visual emphasis to the action button
 */
function applyCompleteButtonAnimations() {
    // Apply pulse animation to all Complete buttons
    $('.update-status-btn[data-new-status="Completed"]').addClass('pulse-border');
    
    // Optional: Apply different animations based on conditions
    $('.update-status-btn[data-new-status="Completed"]').each(function() {
        const $btn = $(this);
        const orderId = $btn.data('order-id');
        
        // Example: Apply different animations based on order age
        const $createdAt = $btn.closest('tr').find('td[data-label="Date & Time"]').text();
        
        // You can add custom logic here based on your requirements
        if (orderId % 2 === 0) { // Example condition
            $btn.addClass('fire-pulse');
        } else {
            $btn.addClass('pulse-border');
        }
    });
}

// Call this function after page load and after any DOM updates
$(document).ready(function() {
    applyCompleteButtonAnimations();
});

// Re-apply animations after status updates
function reapplyCompleteAnimations() {
    $('.update-status-btn[data-new-status="Completed"]').removeClass('pulse-border glow-border ring-pulse double-pulse heartbeat fire-pulse wave-pulse');
    applyCompleteButtonAnimations();
}



/**
 * Apply border pulse animation to Ready buttons
 * Adds visual emphasis to the action button
 */
function applyButtonAnimations() {
    // Apply pulse animation to all Ready buttons
    $('.update-status-btn[data-new-status="Ready"]').addClass('pulse-border');
    
    // Optional: Apply different animations based on order status or other conditions
    $('.update-status-btn[data-new-status="Ready"]').each(function() {
        const $btn = $(this);
        const orderId = $btn.data('order-id');
        
        // Example: Apply different animations based on timer status
        const $timer = $btn.closest('tr').find('.timer');
        if ($timer.hasClass('danger')) {
            // If timer is in danger state, use heartbeat animation
            $btn.addClass('heartbeat');
        } else if ($timer.hasClass('warning')) {
            // If timer is in warning state, use double pulse
            $btn.addClass('double-pulse');
        } else {
            // Default pulse animation
            $btn.addClass('pulse-border');
        }
    });
}

// Call this function after page load and after any DOM updates
$(document).ready(function() {
    applyButtonAnimations();
});

// Re-apply animations after status updates
function reapplyAnimations() {
    $('.update-status-btn').removeClass('pulse-border glow-border ring-pulse double-pulse heartbeat');
    applyButtonAnimations();
}

$(document).ready(function() {
    /**
     * Timer countdown functionality
     * Updates all visible timers every second
     * Shows warning/danger states based on time remaining
     * Completely hides timer for completed orders
     */
    function updateTimers() {
        $('.timer').each(function() {
            const $timer = $(this);
            const $display = $timer.find('.timer-display');
            const createdAt = $timer.data('created-at');
            const orderId = $timer.data('order-id');
            
            // Get the order status from multiple possible sources
            let orderStatus = '';
            
            // Try to get status from status badge
            const $statusBadge = $timer.closest('tr').find('.status-badge');
            if ($statusBadge.length) {
                orderStatus = $statusBadge.text();
            }
            
            // If status badge not found, try to get from global ordersData
            if (!orderStatus && window.ordersData) {
                const order = window.ordersData.find(o => o.order_id == orderId);
                if (order) {
                    orderStatus = order.status;
                }
            }
            
            // Remove timer completely for completed orders
            if (orderStatus === 'Completed') {
                $timer.closest('.clountdown_group').html('');
                return;
            }
            
            // Also remove timer for cancelled orders if needed
            if (orderStatus === 'Cancelled') {
                $timer.closest('.clountdown_group').html('');
                return;
            }
            
            const createdTime = new Date(createdAt).getTime();
            const currentTime = new Date().getTime();
            
            // Check if createdTime is valid
            if (isNaN(createdTime)) {
                console.warn('Invalid created time for order:', orderId);
                $display.text('00:00');
                return;
            }
            
            const timeElapsed = Math.floor((currentTime - createdTime) / 1000);
            const timeLimit = 30 * 60;
            const timeRemaining = timeLimit - timeElapsed;
            
            if (timeRemaining <= 0) {
                $display.text('00:00');
                $timer.removeClass('warning').addClass('danger');
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            $display.text(`${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
            
            $timer.removeClass('warning danger');
            if (timeRemaining <= 10 * 60) {
                $timer.addClass('danger');
            } else if (timeRemaining <= 15 * 60) {
                $timer.addClass('warning');
            }
        });
    }
    
    // Update timers every second
    setInterval(updateTimers, 1000);
    
    // Initial timer update
    updateTimers();

    // Initialize Data - store orders data globally for fallback use
    window.ordersData = <?php echo json_encode($orders); ?>;

    // Initialize all handlers
    initializeAllHandlers();

    /**
     * Bind order handlers for view and cancel buttons
     * Uses event delegation to handle dynamically created elements
     */
    function bindOrderHandlers() {
        $('.view-order').off('click').on('click', viewOrderHandler);
        $('.cancel-order').off('click').on('click', cancelOrderHandler);
    }

    /**
     * Handle status update buttons (Ready, Complete)
     * Binds click events to status update buttons
     */
    function handleStatusUpdateButtons() {
        $('.update-status-btn').off('click').on('click', function(e) {
            e.preventDefault();
            
            const orderId = $(this).data('order-id');
            const newStatus = $(this).data('new-status');
            
            updateOrderStatusDirect(orderId, newStatus, $(this));
        });
    }

    /**
     * View order handler - opens modal with order details
     * Always fetches fresh data from server to ensure accuracy
     * @param {Event} e - Click event
     */
    function viewOrderHandler() {
        const orderId = $(this).data('order-id');
        console.log('🔍 Opening modal for order:', orderId);
        
        // Always fetch fresh data from server for modal to ensure accuracy
        fetchOrderDetailsForModal(orderId);
    }

    /**
     * Cancel order handler - confirms and cancels order via AJAX
     * @param {Event} e - Click event
     */
    function cancelOrderHandler(e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        
        if (confirm('Are you sure you want to cancel this order?')) {
            const button = $(this);
            const originalText = button.html();
            button.html('<i class="bi bi-arrow-repeat spin"></i>').prop('disabled', true);
            
            $.ajax({
                url: 'orders.php',
                type: 'POST',
                data: {
                    ajax_cancel_order: true,
                    order_id: orderId
                },
                success: function(response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        if (result.success) {
                            showToast(result.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            throw new Error(result.message || 'Cancellation failed');
                        }
                    } catch (e) {
                        showToast(e.message, 'danger');
                        button.html(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Cancellation error:', error);
                    showToast('Error cancelling order. Please try again.', 'danger');
                    button.html(originalText).prop('disabled', false);
                }
            });
        }
    }

    /**
     * Update order status directly via AJAX
     * Handles status updates for Ready and Complete buttons
     * @param {number} orderId - ID of the order to update
     * @param {string} newStatus - New status to set
     * @param {jQuery} button - Button element that triggered the update
     */
    function updateOrderStatusDirect(orderId, newStatus, button) {
        const originalText = button.html();
        button.html('<i class="bi bi-arrow-repeat spin"></i>').prop('disabled', true);
        
        $.ajax({
            url: 'orders.php',
            type: 'POST',
            data: {
                ajax_update_status: true,
                order_id: orderId,
                new_status: newStatus
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        showToast(result.message || `Order marked as ${newStatus}!`, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        throw new Error(result.message || 'Update failed');
                    }
                } catch (e) {
                    showToast(e.message || 'Error updating order status', 'danger');
                    button.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Status update error:', error);
                showToast('Error updating order status. Please try again.', 'danger');
                button.html(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Show toast notification
     * Displays temporary notification messages to user
     * @param {string} message - Message to display
     * @param {string} type - Bootstrap alert type (success, danger, warning, info)
     */
    function showToast(message, type) {
        $('.custom-toast').remove();
        
        const toast = $(`
            <div class="alert alert-${type} alert-dismissible fade show custom-toast" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(toast);
        
        setTimeout(() => {
            toast.alert('close');
        }, 5000);
    }

    /**
     * Fetch fresh order details from server for modal
     * Always gets latest data from server to ensure accuracy
     * @param {number} orderId - ID of the order to fetch details for
     */
    function fetchOrderDetailsForModal(orderId) {
        console.log('🔄 Fetching fresh order details for modal - order #' + orderId);
        
        // Show loading state
        $('#modalOrderId').text('Loading...');
        $('#modalCustomerName').text('Loading...');
        $('#modalCustomerPhone').text('Loading...');
        
        fetch(`get_restaurant_order_details.php?order_id=${orderId}&t=${Date.now()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.order) {
                    console.log('✅ Fresh order data loaded for order #' + orderId, data.order);
                    updateOrderModal(data.order);
                } else {
                    throw new Error(data.error || 'Failed to load order details');
                }
            })
            .catch(error => {
                console.error('Error fetching order details:', error);
                showToast('Error loading order details. Please try again.', 'danger');
                
                // Fallback: Try to use existing data if server fetch fails
                const order = window.ordersData?.find(o => o.order_id == orderId);
                if (order) {
                    console.log('🔄 Using fallback data for order #' + orderId);
                    updateOrderModal(order);
                } else {
                    showToast('Could not load order details.', 'danger');
                }
            });
    }

    /**
     * Update order modal with order data
     * Populates all modal fields with order information
     * @param {Object} order - Order data object
     */
    function updateOrderModal(order) {
        console.log('📋 Updating modal with order data for order #' + order.order_id, order);
        
        // Reset modal first to avoid stale data
        $('#modalOrderId').text('');
        $('#modalCustomerName').text('');
        $('#modalCustomerPhone').text('');
        $('#modalOrderItems').empty();
        
        // Basic info
        $('#modalOrderId').text(order.order_id || 'N/A');
        $('#modalCustomerName').text(order.customer_name || 'Not specified');
        $('#modalCustomerPhone').text(order.customer_phone || 'Not specified');
        
        // Order type specifics
        if (order.order_type === 'delivery') {
            $('#modalDeliveryAddress').show().find('#modalAddressText').text(order.delivery_address || 'Not specified');
            $('#modalTableNumber').hide();
        } else if (order.order_type === 'dining') {
            $('#modalDeliveryAddress').hide();
            $('#modalTableNumber').show().find('#modalTableText').text(order.table_number || 'Not specified');
        } else {
            $('#modalDeliveryAddress').hide();
            $('#modalTableNumber').hide();
        }
        
        // Order summary
        $('#modalOrderType').text(formatOrderType(order));
        $('#modalOrderDate').text(new Date(order.created_at).toLocaleString());
        
        // Status
        const statusBadge = $('#modalOrderStatus');
        statusBadge.text(order.status || 'Unknown')
            .removeClass().addClass('status-badge status-' + (order.status || 'Pending'));
        
        // Items
        renderOrderItems(order.items || []);
        
        // Order notes
        const $notesContainer = $('#modalOrderNotesContainer');
        const $notesText = $('#modalOrderNotes');
        
        if (order.order_notes) {
            $notesContainer.show();
            $notesText.text(order.order_notes);
        } else {
            $notesContainer.hide();
        }
        
        // Financials
        updateFinancials(order);
        
        // Form fields
        $('#modalFormOrderId').val(order.order_id);
        $('#modalCancelOrderId').val(order.order_id);
        $('#modalStatusSelect').val(order.status);
        
        // Action buttons
        const showActions = ['Pending', 'Confirmed', 'Preparing'].includes(order.status);
        $('#statusUpdateForm, #cancelOrderForm').toggle(showActions);
    }

    /**
     * Render order items in modal table
     * Creates table rows for each order item
     * @param {Array} items - Array of order items
     */
    function renderOrderItems(items) {
        const $container = $('#modalOrderItems').empty();
        
        if (!items || items.length === 0) {
            $container.append('<tr><td colspan="4" class="text-center">No items found</td></tr>');
            return;
        }
        
        items.forEach(item => {
            const total = (parseFloat(item.price || 0) * parseInt(item.quantity || 0));
            $container.append(`
                <tr>
                    <td>${item.product_name || 'Unnamed Item'}</td>
                    <td>₹${parseFloat(item.price || 0).toFixed(2)}</td>
                    <td>${item.quantity || 1}</td>
                    <td>₹${total.toFixed(2)}</td>
                </tr>
            `);
        });
    }

    /**
     * Update financial information in modal
     * Displays subtotal, discounts, GST, delivery charges, and total
     * @param {Object} order - Order data object
     */
    function updateFinancials(order) {
        $('#modalSubtotal').text(parseFloat(order.subtotal || 0).toFixed(2));
        
        // Toggle and set discount
        const discountAmount = parseFloat(order.discount_amount || 0);
        $('#modalDiscountRow').toggle(discountAmount > 0);
        if (discountAmount > 0) {
            $('#modalDiscountAmount').text(discountAmount.toFixed(2));
            $('#modalDiscountType').text(order.discount_type || 'Discount');
        }
        
        // Toggle and set GST
        const gstAmount = parseFloat(order.gst_amount || 0);
        $('#modalGstRow').toggle(gstAmount > 0);
        if (gstAmount > 0) $('#modalGstAmount').text(gstAmount.toFixed(2));
        
        // Toggle and set delivery
        const deliveryCharge = parseFloat(order.delivery_charge || 0);
        $('#modalDeliveryRow').toggle(deliveryCharge > 0);
        if (deliveryCharge > 0) $('#modalDeliveryCharge').text(deliveryCharge.toFixed(2));
        
        // Total
        $('#modalTotalAmount').text(parseFloat(order.total_amount || 0).toFixed(2));
    }

    /**
     * Cancel order form submission handler
     * Prevents default form submission and triggers cancel order via button click
     */
    $('#cancelOrderForm').submit(function(e) {
        e.preventDefault();
        const orderId = $('#modalCancelOrderId').val();
        $(`.cancel-order[data-order-id="${orderId}"]`).click();
    });

    /**
     * Status update form submission handler
     * Prevents default form submission and triggers status update
     */
    $('#statusUpdateForm').submit(function(e) {
        e.preventDefault();
        const orderId = $('#modalFormOrderId').val();
        const newStatus = $('#modalStatusSelect').val();
        
        updateOrderStatusDirect(orderId, newStatus, $(this).find('button[type="submit"]'));
    });

    /**
     * Format order type for display
     * Converts order type to user-friendly display text
     * @param {Object} order - Order data object
     * @returns {string} Formatted order type string
     */
    function formatOrderType(order) {
        if (!order.order_type) return 'Unknown type';
        return order.order_type === 'dining' 
            ? `Dining (Table ${order.table_number || 'N/A'})` 
            : order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1);
    }

    /**
     * Initialize all event handlers
     * Sets up click handlers for view, cancel, and status update buttons
     */
    function initializeAllHandlers() {
        bindOrderHandlers();
        handleStatusUpdateButtons();
    }

    /**
     * Copy functionality for text elements
     * Allows copying of customer name, phone, and address to clipboard
     */
    $(document).on('click', '.copy-btn', function() {
        const targetId = $(this).data('target');
        const textToCopy = $(`#${targetId}`).text().trim();
        
        // Use the modern Clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            // Use the Clipboard API for secure contexts (HTTPS)
            navigator.clipboard.writeText(textToCopy).then(() => {
                showCopyFeedback($(this));
            }).catch(err => {
                console.error('Failed to copy text: ', err);
                fallbackCopyText(textToCopy, $(this));
            });
        } else {
            // Fallback for non-secure contexts (HTTP)
            fallbackCopyText(textToCopy, $(this));
        }
    });

    /**
     * Fallback copy text method for older browsers
     * Uses deprecated execCommand for clipboard operations
     * @param {string} text - Text to copy
     * @param {jQuery} button - Button element that triggered copy
     */
    function fallbackCopyText(text, button) {
        // Create a temporary textarea for fallback method
        const tempTextArea = document.createElement('textarea');
        tempTextArea.value = text;
        tempTextArea.style.position = 'fixed';
        tempTextArea.style.left = '-999999px';
        tempTextArea.style.top = '-999999px';
        document.body.appendChild(tempTextArea);
        tempTextArea.focus();
        tempTextArea.select();
        
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopyFeedback(button);
            } else {
                throw new Error('Fallback copy failed');
            }
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            // Last resort - show text for manual copy
            alert('Please copy manually: ' + text);
        } finally {
            document.body.removeChild(tempTextArea);
        }
    }

    /**
     * Show copy feedback on button
     * Temporarily changes button text to indicate successful copy
     * @param {jQuery} button - Button element to show feedback on
     */
    function showCopyFeedback(button) {
        const originalHtml = button.html();
        button.html('<i class="bi bi-check"></i> Copied!').prop('disabled', true);
        
        // Revert button text after 2 seconds
        setTimeout(() => {
            button.html(originalHtml).prop('disabled', false);
        }, 2000);
    }
});

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Order management system initialized');
});
</script>

</body>
</html>
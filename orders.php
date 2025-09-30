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
    .bg-warning {
        background: red !important;
    }
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.8em;
    }
    .status-Pending {
        background-color: #ffc107;
        color: #000;
    }
    .status-Confirmed {
        background-color: #17a2b8;
        color: #fff;
    }
    .status-Preparing {
        background-color: #fd7e14;
        color: #fff;
    }
    .status-Ready {
        background-color: #28a745;
        color: #fff;
    }
    .status-Completed {
        background-color: orange;
        color: #fff;
    }
    .status-Cancelled {
        background-color: #dc3545;
        color: #fff;
    }
    
    .bi-arrow-repeat.spin {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Timer styles */
    .timer {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 6px;
        background-color: #000;
        font-weight: bold;
        color: #fff;
    }
    
    .timer.warning {
        background-color: orange;
        color: #000;
    }
    
    .timer.danger {
        background-color: red;
        color: #fff;
        animation: blink 1s infinite;
    }
    
    @keyframes blink {
        0%, 50% { opacity: 1; }
        51%, 100% { opacity: 0.5; }
    }
    
    .timer-column {
        min-width: 120px;
    }

    .table.order th:last-child {
        width: 310px;
    }

    .btn-primary {
        background: #606060;
        border-color: #606060;
    }
   
    .btn-info {
        background: #ff6c2f;
        border-color: #ff6c2f;
    }

    /* Toast notifications */
    .custom-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    }

    @media (max-width: 768px) {
        .mobile_table .update-status-btn[data-new-status="Ready"],
        .mobile_table .update-status-btn[data-new-status="Completed"] {
            width: 100%;    
            margin: 5px 0;
            display: block;
            padding: 10px 20px;
            font-size: 15px;
            text-align: left;
        }
        
        .mobile_table td[data-label="Actions"] {
            text-align: center;
        }
        
        .timer-column {
            min-width: 100px;
        }

        .mobile_table tr {
            position: relative;
        }

        .mobile_table .table td.timer-column:before {
            display: none;
        }

        .mobile_table .table td.timer-column {
            border-bottom:0;
        }

        .clountdown_group {
            position: absolute;
            top: 72px;
            z-index: 99;
            right: 28px;
        }
    }

    /* WhatsApp notification styles */
    .whatsapp-fallback-link {
        background: #25D366;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: bold;
        margin: 5px;
        display: inline-block;
        animation: pulse-green 2s infinite;
    }

    @keyframes pulse-green {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
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
$(document).ready(function() {
    // Timer countdown functionality
    function updateTimers() {
        $('.timer').each(function() {
            const $timer = $(this);
            const $display = $timer.find('.timer-display');
            const createdAt = $timer.data('created-at');
            const orderId = $timer.data('order-id');
            
            const createdTime = new Date(createdAt).getTime();
            const currentTime = new Date().getTime();
            const timeElapsed = Math.floor((currentTime - createdTime) / 1000); // in seconds
            const timeLimit = 30 * 60; // 30 minutes in seconds
            const timeRemaining = timeLimit - timeElapsed;
            
            if (timeRemaining <= 0) {
                // Timer expired - show 00:00 in red
                $display.text('00:00');
                $timer.removeClass('warning').addClass('danger');
                return;
            }
            
            // Update timer display
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            $display.text(`${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
            
            // Update styling based on time remaining
            $timer.removeClass('warning danger');
            if (timeRemaining <= 10 * 60) { // 10 minutes or less
                $timer.addClass('danger');
            } else if (timeRemaining <= 15 * 60) { // 15 minutes or less
                $timer.addClass('warning');
            }
        });
    }
    
    // Update timers every second
    setInterval(updateTimers, 1000);
    
    // Initial timer update
    updateTimers();

    // Initialize Data
    let ordersData = <?php echo json_encode($orders); ?>;

    // Initialize all handlers
    initializeAllHandlers();

    // Order Management Functions
    function bindOrderHandlers() {
        $('.view-order').off('click').on('click', viewOrderHandler);
        $('.cancel-order').off('click').on('click', cancelOrderHandler);
    }

    // Handle direct status update buttons
    function handleStatusUpdateButtons() {
        $('.update-status-btn').off('click').on('click', function(e) {
            e.preventDefault();
            
            const orderId = $(this).data('order-id');
            const newStatus = $(this).data('new-status');
            
            // Remove confirmation and directly update status
            updateOrderStatusDirect(orderId, newStatus, $(this));
        });
    }

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

    function showToast(message, type) {
        // Remove existing toasts
        $('.custom-toast').remove();
        
        // Create toast element
        const toast = $(`
            <div class="alert alert-${type} alert-dismissible fade show custom-toast" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.alert('close');
        }, 5000);
    }

    function viewOrderHandler() {
        const orderId = $(this).data('order-id');
        const order = ordersData.find(o => o.order_id == orderId);
        
        if (!order) {
            console.error('Order not found:', orderId);
            showToast('Order not loaded. Please refresh the page.', 'danger');
            return;
        }
        
        updateOrderModal(order);
    }

    function updateOrderModal(order) {
        // Basic info
        $('#modalOrderId').text(order.order_id);
        $('#modalCustomerName').text(order.customer_name || 'Not specified');
        $('#modalCustomerPhone').text(order.customer_phone || 'Not specified');
        
        // Order type specifics
        if (order.order_type === 'delivery') {
            $('#modalDeliveryAddress').show().find('#modalAddressText').text(order.delivery_address || 'Not specified');
            $('#modalTableNumber').hide();
        } else {
            $('#modalDeliveryAddress').hide();
            $('#modalTableNumber').show().find('#modalTableText').text(order.table_number || 'Not specified');
        }
        
        // Order summary
        $('#modalOrderType').text(formatOrderType(order));
        $('#modalOrderDate').text(new Date(order.created_at).toLocaleString());
        
        // Status
        const statusBadge = $('#modalOrderStatus');
        statusBadge.text(order.status)
            .removeClass().addClass('status-badge status-' + order.status);
        
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

    function renderOrderItems(items) {
        const $container = $('#modalOrderItems').empty();
        
        if (items.length === 0) {
            $container.append('<tr><td colspan="4" class="text-center">No items found</td></tr>');
            return;
        }
        
        items.forEach(item => {
            const total = (parseFloat(item.price || 0) * parseInt(item.quantity || 0));
            $container.append(`
                <tr>
                    <td>${item.product_name || 'Unnamed'}</td>
                    <td>₹${parseFloat(item.price || 0)}</td>
                    <td>${item.quantity}</td>
                    <td>₹${total}</td>
                </tr>
            `);
        });
    }

    function updateFinancials(order) {
        $('#modalSubtotal').text(parseFloat(order.subtotal || 0));
        
        // Toggle and set discount
        const discountAmount = parseFloat(order.discount_amount || 0);
        $('#modalDiscountRow').toggle(discountAmount > 0);
        if (discountAmount > 0) {
            $('#modalDiscountAmount').text(discountAmount);
            $('#modalDiscountType').text(order.discount_type || 'Discount');
        }
        
        // Toggle and set GST
        const gstAmount = parseFloat(order.gst_amount || 0);
        $('#modalGstRow').toggle(gstAmount > 0);
        if (gstAmount > 0) $('#modalGstAmount').text(gstAmount);
        
        // Toggle and set delivery
        const deliveryCharge = parseFloat(order.delivery_charge || 0);
        $('#modalDeliveryRow').toggle(deliveryCharge > 0);
        if (deliveryCharge > 0) $('#modalDeliveryCharge').text(deliveryCharge);
        
        // Total
        $('#modalTotalAmount').text(parseFloat(order.total_amount || 0));
    }

    // Order Actions
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

    $('#cancelOrderForm').submit(function(e) {
        e.preventDefault();
        const orderId = $('#modalCancelOrderId').val();
        $(`.cancel-order[data-order-id="${orderId}"]`).click();
    });

    $('#statusUpdateForm').submit(function(e) {
        e.preventDefault();
        const orderId = $('#modalFormOrderId').val();
        const newStatus = $('#modalStatusSelect').val();
        
        updateOrderStatusDirect(orderId, newStatus, $(this).find('button[type="submit"]'));
    });

    function updateOrderStatusUI(orderId, newStatus) {
        const $badge = $(`tr:has(button[data-order-id="${orderId}"]) .status-badge`);
        
        $badge.text(newStatus)
            .removeClass()
            .addClass(`status-badge status-${newStatus}`);
        
        $(`.cancel-order[data-order-id="${orderId}"]`)
            .toggle(['Pending', 'Confirmed', 'Preparing'].includes(newStatus));
    }

    // UI Helpers
    function formatOrderType(order) {
        if (!order.order_type) return 'Unknown type';
        return order.order_type === 'dining' 
            ? `Dining (Table ${order.table_number || 'N/A'})` 
            : order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1);
    }

    // Initialize all handlers
    function initializeAllHandlers() {
        bindOrderHandlers();
        handleStatusUpdateButtons();
    }
});

// Copy functionality
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

function showCopyFeedback(button) {
    const originalHtml = button.html();
    button.html('<i class="bi bi-check"></i> Copied!').prop('disabled', true);
    
    // Revert button text after 2 seconds
    setTimeout(() => {
        button.html(originalHtml).prop('disabled', false);
    }, 2000);
}
</script>

</body>
</html>
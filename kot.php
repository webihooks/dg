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
$message_type = '';
$grouped_orders = [];

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $stmt->bind_result($user_name, $email, $phone, $address, $role);
        $stmt->fetch();
    }
    $stmt->close();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $sql = "UPDATE orders SET status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("si", $new_status, $order_id);
        if ($stmt->execute()) {
            $message = "Order status updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating order status: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Set filter to today's date only
$filter_date = date('Y-m-d');

// Determine status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';
$status_condition = "";
if ($status_filter === 'active') {
    $status_condition = "AND (o.status = 'Confirmed' OR o.status = 'Preparing')";
} elseif ($status_filter === 'confirmed') {
    $status_condition = "AND o.status = 'Confirmed'";
} elseif ($status_filter === 'preparing') {
    $status_condition = "AND o.status = 'Preparing'";
} elseif ($status_filter === 'ready') {
    $status_condition = "AND o.status = 'Ready'";
} elseif ($status_filter === 'all') {
    $status_condition = "";
}

// Fetch orders based on filter
$sql = "SELECT o.order_id, o.customer_name, o.order_notes, o.created_at, 
               o.order_type, o.table_number, o.status,
               GROUP_CONCAT(oi.product_name SEPARATOR '|') AS products,
               GROUP_CONCAT(oi.quantity SEPARATOR '|') AS quantities,
               GROUP_CONCAT(oi.price SEPARATOR '|') AS prices,
               SUM(oi.price * oi.quantity) AS total_amount
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.user_id = ? 
          AND DATE(o.created_at) = ?
          $status_condition
        GROUP BY o.order_id
        ORDER BY 
            CASE 
                WHEN o.status = 'Confirmed' THEN 1
                WHEN o.status = 'Preparing' THEN 2
                WHEN o.status = 'Ready' THEN 3
                ELSE 4
            END,
            o.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("is", $user_id, $filter_date);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $grouped_orders = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        
        foreach ($grouped_orders as &$order) {
            $products = explode('|', $order['products']);
            $quantities = explode('|', $order['quantities']);
            $prices = explode('|', $order['prices']);
            
            $order['items'] = [];
            for ($i = 0; $i < count($products); $i++) {
                $order['items'][] = [
                    'product_name' => $products[$i],
                    'quantity' => $quantities[$i],
                    'price' => $prices[$i]
                ];
            }
            
            $order['additional_notes'] = [];
        }
        unset($order);
        
        $order_ids = array_column($grouped_orders, 'order_id');
        if (!empty($order_ids)) {
            $table_check = $conn->query("SHOW TABLES LIKE 'order_notes'");
            if ($table_check && $table_check->num_rows > 0) {
                $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                $sql = "SELECT order_id, note, created_at FROM order_notes WHERE order_id IN ($placeholders) ORDER BY created_at DESC";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $types = str_repeat('i', count($order_ids));
                    $stmt->bind_param($types, ...$order_ids);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $all_notes = $result->fetch_all(MYSQLI_ASSOC);
                        
                        $notes_by_order = [];
                        foreach ($all_notes as $note) {
                            $notes_by_order[$note['order_id']][] = $note;
                        }
                        
                        foreach ($grouped_orders as &$order) {
                            if (isset($notes_by_order[$order['order_id']])) {
                                $order['additional_notes'] = $notes_by_order[$order['order_id']];
                            }
                        }
                        unset($order);
                    }
                    $stmt->close();
                }
            }
        }
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Kitchen Orders (KOT)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .status-Confirmed { background-color: #17a2b8; color: #fff; }
        .status-Preparing { background-color: #fd7e14; color: #fff; }
        .status-Ready { background-color: #28a745; color: #fff; }
        .status-Pending { background-color: #ffc107; color: #000; }
        .kot_qty { color: #6c757d; font-size: 0.9em; }
        .order-details { padding: 0; margin: 0; }
        .order-details li { list-style-type: none; padding: 5px 0; border-bottom: 1px solid #eee; }
        .order-details li:last-child { border-bottom: none; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-weight: bold; font-size: 0.8em; }
        .status-filter { margin-bottom: 20px; }
        .action-buttons .btn { margin-right: 5px; margin-bottom: 5px; }
        .dropdown-item.active { background-color: #e9ecef; color: #495057; font-weight: bold; }
        
        /* Responsive table styles */
        @media (max-width: 992px) {
            .table-responsive table {
                width: 100%;
            }
            
            .table-responsive thead {
                display: none;
            }
            
            .table-responsive tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #dee2e6;
                border-radius: 0.25rem;
            }
            
            .table-responsive td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem;
                border-bottom: 1px solid #dee2e6;
                position: relative;
                padding-left: 50%;
            }
            
            .table-responsive td:before {
                content: attr(data-label);
                position: absolute;
                left: 0.75rem;
                width: 45%;
                padding-right: 1rem;
                font-weight: bold;
                text-align: left;
            }
            
            .table-responsive td:last-child {
                border-bottom: 0;
            }
            
            .action-buttons {
                justify-content: flex-end !important;
            }
            
            /* Adjust order details for mobile */
            .order-details li {
                padding: 3px 0;
            }
            
            .kot_qty {
                display: block;
                margin-left: 0;
                margin-top: 2px;
            }
        }
        
        /* Mobile card view styles */
        @media (max-width: 768px) {
            .order-card {
                border: 1px solid #dee2e6;
                border-radius: 0.25rem;
                margin-bottom: 1rem;
                padding: 1rem;
            }
            
            .order-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                border-bottom: 1px solid #eee;
                padding-bottom: 0.5rem;
            }
            
            .order-body {
                margin-bottom: 0.5rem;
            }
            
            .order-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .mobile-order-item {
                display: flex;
                justify-content: space-between;
                padding: 0.25rem 0;
            }
            
            .mobile-order-notes {
                font-size: 0.9em;
                color: #6c757d;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include ($role === 'admin') ? 'admin_menu.php' : 'menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Kitchen Orders (KOT) - <?php echo date('F j, Y'); ?></h4>
                                <div class="float-end">
                                    <span class="badge bg-primary">Total: <?php echo count($grouped_orders); ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                                <?php endif; ?>
                                
                                <div class="status-filter mb-3">
                                    <div class="dropdown">
                                        <button class="btn btn-secondary dropdown-toggle" type="button" id="statusFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <?php 
                                            $filter_labels = [
                                                'active' => 'Active Orders',
                                                'confirmed' => 'Confirmed',
                                                'preparing' => 'Preparing',
                                                'ready' => 'Ready',
                                                'all' => 'All Orders'
                                            ];
                                            echo $filter_labels[$status_filter] ?? 'Filter Orders';
                                            ?>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="statusFilterDropdown">
                                            <li><a class="dropdown-item <?php echo $status_filter === 'active' ? 'active' : ''; ?>" href="?status=active">Active Orders</a></li>
                                            <li><a class="dropdown-item <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>" href="?status=confirmed">Confirmed</a></li>
                                            <li><a class="dropdown-item <?php echo $status_filter === 'preparing' ? 'active' : ''; ?>" href="?status=preparing">Preparing</a></li>
                                            <li><a class="dropdown-item <?php echo $status_filter === 'ready' ? 'active' : ''; ?>" href="?status=ready">Ready</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item <?php echo $status_filter === 'all' ? 'active' : ''; ?>" href="?status=all">All Orders</a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <?php if (empty($grouped_orders)): ?>
                                    <div class="alert alert-info">No orders found for the selected filter.</div>
                                <?php else: ?>
                                    <!-- Desktop View -->
                                    <div class="d-none d-lg-block">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th width="30">Order ID</th>
                                                        <th width="30">Customer</th>
                                                        <th width="30">Type/Table</th>
                                                        <th width="30">Status</th>
                                                        <th>Items</th>
                                                        <th>Notes</th>
                                                        <th width="30">Time</th>
                                                        <th width="30">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($grouped_orders as $order): ?>
                                                        <tr>
                                                            <td data-label="Order ID"><?php echo htmlspecialchars($order['order_id']); ?></td>
                                                            <td data-label="Customer"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                            <td data-label="Type/Table">
                                                                <?php if ($order['order_type'] === 'dining' && !empty($order['table_number'])): ?>
                                                                    Table <?php echo htmlspecialchars($order['table_number']); ?>
                                                                <?php else: ?>
                                                                    <?php echo ucfirst($order['order_type']); ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td data-label="Status">
                                                                <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                                                    <?php echo htmlspecialchars($order['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td data-label="Items">
                                                                <ul class="order-details">
                                                                    <?php foreach ($order['items'] as $item): ?>
                                                                        <li>
                                                                            <?php echo htmlspecialchars($item['product_name']); ?> 
                                                                            - ₹<?php echo number_format($item['price']); ?>
                                                                            <span class="kot_qty">(Qty: <?php echo htmlspecialchars($item['quantity']); ?>)</span>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </td>
                                                            <td data-label="Notes">
                                                                <?php if (!empty($order['order_notes'])): ?>
                                                                    <div><?php echo htmlspecialchars($order['order_notes']); ?></div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($order['additional_notes'])): ?>
                                                                    <div><small>Additional Notes:</small>
                                                                        <ul>
                                                                            <?php foreach ($order['additional_notes'] as $note): ?>
                                                                                <li><small><?php echo htmlspecialchars($note['note']); ?> (<?php echo date('h:i A', strtotime($note['created_at'])); ?>)</small></li>
                                                                            <?php endforeach; ?>
                                                                        </ul>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td data-label="Time"><?php echo date('h:i A', strtotime($order['created_at'])); ?></td>
                                                            <td data-label="Actions" class="action-buttons">
                                                                <?php if ($order['status'] === 'Confirmed'): ?>
                                                                    <form method="post" style="display: inline;">
                                                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                        <input type="hidden" name="new_status" value="Preparing">
                                                                        <button type="submit" name="update_status" class="btn btn-sm btn-warning">Preparing</button>
                                                                    </form>
                                                                <?php elseif ($order['status'] === 'Preparing'): ?>
                                                                    <form method="post" style="display: inline;">
                                                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                        <input type="hidden" name="new_status" value="Ready">
                                                                        <button type="submit" name="update_status" class="btn btn-sm btn-success">Ready</button>
                                                                    </form>
                                                                <?php elseif ($order['status'] === 'Ready'): ?>
                                                                    <span class="text-success">Completed</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Mobile View -->
                                    <div class="d-lg-none">
                                        <?php foreach ($grouped_orders as $order): ?>
                                            <div class="order-card mb-3">
                                                <div class="order-header">
                                                    <div>
                                                        <strong>Order #<?php echo $order['order_id']; ?></strong>
                                                        <div class="text-muted small"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
                                                    </div>
                                                    <div>
                                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                                            <?php echo $order['status']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="order-body">
                                                    <div class="mb-2">
                                                        <strong>Customer:</strong> <?php echo $order['customer_name']; ?>
                                                        <?php if ($order['order_type'] === 'dining' && !empty($order['table_number'])): ?>
                                                            <span class="text-muted">(Table <?php echo $order['table_number']; ?>)</span>
                                                        <?php else: ?>
                                                            <span class="text-muted">(<?php echo ucfirst($order['order_type']); ?>)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <strong>Items:</strong>
                                                        <ul class="list-unstyled">
                                                            <?php foreach ($order['items'] as $item): ?>
                                                                <li class="mobile-order-item">
                                                                    <span>
                                                                        <?php echo $item['product_name']; ?> 
                                                                        <span class="text-muted">x<?php echo $item['quantity']; ?></span>
                                                                    </span>
                                                                    <span>₹<?php echo number_format($item['price']); ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                    
                                                    <?php if (!empty($order['order_notes']) || !empty($order['additional_notes'])): ?>
                                                        <div class="mb-2">
                                                            <strong>Notes:</strong>
                                                            <?php if (!empty($order['order_notes'])): ?>
                                                                <div class="mobile-order-notes"><?php echo $order['order_notes']; ?></div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($order['additional_notes'])): ?>
                                                                <div class="small text-muted">
                                                                    Additional Notes:
                                                                    <ul>
                                                                        <?php foreach ($order['additional_notes'] as $note): ?>
                                                                            <li><?php echo $note['note']; ?> (<?php echo date('h:i A', strtotime($note['created_at'])); ?>)</li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="order-footer">
                                                    <div class="action-buttons">
                                                        <?php if ($order['status'] === 'Confirmed'): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                <input type="hidden" name="new_status" value="Preparing">
                                                                <button type="submit" name="update_status" class="btn btn-sm btn-warning">Preparing</button>
                                                            </form>
                                                        <?php elseif ($order['status'] === 'Preparing'): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                <input type="hidden" name="new_status" value="Ready">
                                                                <button type="submit" name="update_status" class="btn btn-sm btn-success">Ready</button>
                                                            </form>
                                                        <?php elseif ($order['status'] === 'Ready'): ?>
                                                            <span class="text-success">Completed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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
        // Auto-refresh every 30 seconds
        setTimeout(() => location.reload(), 30000);
        
        // Make status badges clickable to filter
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('click', function() {
                const status = this.textContent.trim().toLowerCase();
                window.location.href = `?status=${status}`;
            });
        });
    </script>
</body>
</html>
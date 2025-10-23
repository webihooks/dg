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

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: all_orders.php");
    exit();
}

$order_id = $_GET['id'];

// Fetch order details with user information
$order_sql = "SELECT o.*, 
                     u.id as user_id, 
                     u.name as user_name, 
                     u.email as user_email,
                     u.phone as user_phone,
                     u.address as user_address,
                     p.profile_url
              FROM orders o 
              JOIN users u ON o.user_id = u.id 
              LEFT JOIN profile_url_details p ON u.id = p.user_id
              WHERE o.order_id = ?";
$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("s", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    header("Location: all_orders.php");
    exit();
}

$order = $order_result->fetch_assoc();
$order_stmt->close();

// Fetch order items
$items_sql = "SELECT * FROM order_items WHERE order_id = ? ORDER BY item_id";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("s", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// Calculate item count and total quantity
$item_count = 0;
$total_quantity = 0;
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
    $item_count++;
    $total_quantity += $item['quantity'];
}

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Order Details | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .order-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }
        .order-details-card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .order-items-table {
            width: 100%;
        }
        .order-items-table th {
            background-color: #f8f9fa;
        }
        .profile-url {
            color: #3b5de7;
            text-decoration: none;
        }
        .profile-url:hover {
            text-decoration: underline;
        }
        .amount-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .amount-row.total {
            font-weight: bold;
            font-size: 1.1em;
            border-bottom: none;
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
                    <div class="col-xl-12">
                        <div class="card order-details-card mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="card-title mb-0">Order #<?php echo htmlspecialchars($order['order_id']); ?></h4>
                                    <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h5>Customer Details</h5>
                                        <div class="mb-3">
                                            <strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </div>
                                        <?php if ($order['order_type'] === 'delivery' && !empty($order['delivery_address'])): ?>
                                        <div class="mb-3">
                                            <strong>Delivery Address:</strong> 
                                            <?php echo htmlspecialchars($order['delivery_address']); ?>
                                        </div>
                                        <?php elseif ($order['order_type'] === 'dine-in' && !empty($order['table_number'])): ?>
                                        <div class="mb-3">
                                            <strong>Table Number:</strong> 
                                            <?php echo htmlspecialchars($order['table_number']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($order['order_notes'])): ?>
                                        <div class="mb-3">
                                            <strong>Order Notes:</strong> 
                                            <?php echo htmlspecialchars($order['order_notes']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5>User Details</h5>
                                        <div class="mb-3">
                                            <strong>User ID:</strong> <?php echo htmlspecialchars($order['user_id']); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Name:</strong> <?php echo htmlspecialchars($order['user_name']); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($order['user_email']); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($order['user_phone']); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Profile URL:</strong> 
                                            <?php if (!empty($order['profile_url'])): ?>
                                                <a href="https://deegeecard.com/<?php echo htmlspecialchars($order['profile_url']); ?>" 
                                                   class="profile-url" 
                                                   target="_blank">
                                                   <?php echo htmlspecialchars($order['profile_url']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5 class="mb-3">Order Items (<?php echo $item_count; ?> items, <?php echo $total_quantity; ?> total quantity)</h5>
                                        <div class="table-responsive">
                                            <table class="table order-items-table">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Product</th>
                                                        <th>Price</th>
                                                        <th>Qty</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($items as $index => $item): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                        <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                                        <td><?php echo $item['quantity']; ?></td>
                                                        <td>₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <h5 class="mb-3">Order Summary</h5>
                                        <div class="amount-details">
                                            <div class="amount-row">
                                                <span>Subtotal:</span>
                                                <span>₹<?php echo number_format($order['subtotal'], 2); ?></span>
                                            </div>
                                            
                                            <?php if ($order['discount_amount'] > 0): ?>
                                            <div class="amount-row">
                                                <span>Discount (<?php echo htmlspecialchars($order['discount_type']); ?>):</span>
                                                <span>-₹<?php echo number_format($order['discount_amount'], 2); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="amount-row">
                                                <span>GST:</span>
                                                <span>₹<?php echo number_format($order['gst_amount'], 2); ?></span>
                                            </div>
                                            
                                            <?php if ($order['order_type'] === 'delivery' && $order['delivery_charge'] > 0): ?>
                                            <div class="amount-row">
                                                <span>Delivery Charge:</span>
                                                <span>₹<?php echo number_format($order['delivery_charge'], 2); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="amount-row total">
                                                <span>Total Amount:</span>
                                                <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <h5>Order Timeline</h5>
                                            <div class="mb-2">
                                                <strong>Placed On:</strong> 
                                                <?php echo date('d M Y h:i A', strtotime($order['created_at'])); ?>
                                            </div>
                                            <?php if (!empty($order['updated_at']) && $order['created_at'] != $order['updated_at']): ?>
                                            <div class="mb-2">
                                                <strong>Last Updated:</strong> 
                                                <?php echo date('d M Y h:i A', strtotime($order['updated_at'])); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
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
    
</body>
</html>
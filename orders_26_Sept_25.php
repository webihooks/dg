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
            background-color: #6c757d;
            color: #fff;
        }
        .status-Cancelled {
            background-color: #dc3545;
            color: #fff;
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
                                        <table class="table table-hover mb-0">
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
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($orders as $index => $order): ?>
                                                    <tr>
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
                                                        <td data-label="Total">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td data-label="Status">
                                                            <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                                                <?php echo htmlspecialchars($order['status']); ?>
                                                            </span>
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
                            <p><strong>Name:</strong> <span id="modalCustomerName"></span></p>
                            <p><strong>Phone:</strong> <span id="modalCustomerPhone"></span></p>
                            <p id="modalDeliveryAddress"><strong>Address:</strong> <span id="modalAddressText"></span></p>
                            <p id="modalTableNumber"><strong>Table Number:</strong> <span id="modalTableText"></span></p>
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
        // Initialize Data
        let ordersData = <?php echo json_encode($orders); ?>;

        // Order Management Functions
        function bindOrderHandlers() {
            $('.view-order').off('click').on('click', viewOrderHandler);
            $('.cancel-order').off('click').on('click', cancelOrderHandler);
        }

        function viewOrderHandler() {
            const orderId = $(this).data('order-id');
            const order = ordersData.find(o => o.order_id == orderId);
            
            if (!order) {
                console.error('Order not found:', orderId);
                alert('Order not loaded. Please refresh the page.');
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
                const total = (parseFloat(item.price || 0) * parseInt(item.quantity || 0)).toFixed(2);
                $container.append(`
                    <tr>
                        <td>${item.product_name || 'Unnamed'}</td>
                        <td>₹${parseFloat(item.price || 0).toFixed(2)}</td>
                        <td>${item.quantity}</td>
                        <td>₹${total}</td>
                    </tr>
                `);
            });
        }

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

        // Order Actions
        function cancelOrderHandler(e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            
            if (confirm('Are you sure you want to cancel this order?')) {
                processOrderAction({
                    action: 'cancel_order',
                    order_id: orderId,
                    button: $(this),
                    success: () => updateOrderStatusUI(orderId, 'Cancelled')
                });
            }
        }

        $('#cancelOrderForm').submit(function(e) {
            e.preventDefault();
            cancelOrderHandler(e);
        });

        $('#statusUpdateForm').submit(function(e) {
            e.preventDefault();
            processOrderAction({
                action: 'update_status',
                order_id: $('#modalFormOrderId').val(),
                new_status: $('#modalStatusSelect').val(),
                button: $(this).find('button[type="submit"]'),
                success: () => updateOrderStatusUI($('#modalFormOrderId').val(), $('#modalStatusSelect').val())
            });
        });

        function processOrderAction({action, order_id, new_status, button, success}) {
            const originalText = button.html();
            button.html('<i class="bi bi-arrow-repeat spin"></i> Processing...').prop('disabled', true);
            
            $.ajax({
                url: 'orders.php',
                type: 'POST',
                data: { 
                    [action]: true,
                    order_id,
                    ...(new_status && {new_status})
                },
                success: function() {
                    success();
                    $('#orderModal').modal('hide');
                    // showToast(`Order ${action.replace('_', ' ')} successful!`, 'success');
                    setTimeout(() => location.reload(), 1000);
                },
                error: function(xhr) {
                    alert(`Action failed: ${xhr.responseText || 'Unknown error'}`);
                    button.html(originalText).prop('disabled', false);
                }
            });
        }

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

        // function showToast(message, type) {
        //     // Implement your toast notification system here
        //     alert(`${type.toUpperCase()}: ${message}`);
        // }

        // Initialize
        bindOrderHandlers();
    });
    </script>
</body>
</html>
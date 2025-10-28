<?php
// Start the session
session_start();

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch admin user name
$sql_name = "SELECT name FROM users WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$stmt_name->bind_result($user_name);
$stmt_name->fetch();
$stmt_name->close();

// Fetch all addon orders with user details
$addon_orders = [];
$sql = "SELECT 
            ua.*, 
            a.name as addon_name, 
            a.image as addon_image,
            u.name as customer_name,
            u.email as customer_email
        FROM user_addons ua
        JOIN addons a ON ua.addon_id = a.id
        JOIN users u ON ua.user_id = u.id
        ORDER BY ua.purchase_date DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $addon_orders[] = $row;
}
$stmt->close();

// Handle order filtering if requested
$filter_user = $_GET['user_id'] ?? null;
if ($filter_user && is_numeric($filter_user)) {
    $addon_orders = array_filter($addon_orders, function($order) use ($filter_user) {
        return $order['user_id'] == $filter_user;
    });
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Admin - Addon Orders</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <style>
        .order-highlight {
            background-color: #f8f9fa;
        }
        .badge-special {
            background-color: #ffc107;
            color: #212529;
        }
        .customer-info {
            min-width: 200px;
        }
        .table tbody tr:last-child td {
            border-bottom: 1px solid #ddd;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .card-header .btn {
                margin-top: 10px;
                align-self: flex-end;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table {
                min-width: 800px; /* Ensure table has enough width for horizontal scrolling */
            }
            
            .table th, .table td {
                white-space: nowrap;
                padding: 8px 12px;
                font-size: 0.875rem;
            }
            
            .customer-info {
                min-width: 150px;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .modal-content {
                border-radius: 0.5rem;
            }
            
            .img-thumbnail {
                max-height: 30px !important;
            }
            
            .badge {
                font-size: 0.7rem;
            }
            
            .small {
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .table th, .table td {
                padding: 6px 8px;
                font-size: 0.8rem;
            }
            
            .card-title {
                font-size: 1.25rem;
            }
            
            .modal-dialog {
                margin: 0.25rem;
            }
            
            .modal-header, .modal-footer {
                padding: 0.75rem;
            }
            
            .modal-body {
                padding: 1rem;
            }
        }
        
        /* Touch-friendly styles */
        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .view-details {
            min-width: 80px;
        }
        
        /* Improve table row spacing for touch */
        .table tbody tr {
            height: 60px;
        }
        
        /* Ensure modal is properly sized on mobile */
        .modal-lg {
            max-width: 95%;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title">Addon Orders Management</h4>
                                <div>
                                    <a href="addon_orders.php" class="btn btn-sm btn-outline-secondary">Reset Filters</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($addon_orders)): ?>
                                    <div class="alert alert-info">No addon orders found.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table id="ordersTable" class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Order ID</th>
                                                    <th>Customer</th>
                                                    <th>Addon</th>
                                                    <th>Price</th>
                                                    <th>Payment</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($addon_orders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <code><?php echo htmlspecialchars(substr($order['order_id'], 0, 8)); ?>...</code>
                                                        </td>
                                                        <td class="customer-info">
                                                            <div><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                                            <div class="small">
                                                                <a href="addon_orders.php?user_id=<?php echo $order['user_id']; ?>" 
                                                                   class="text-primary">View all orders</a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($order['addon_image'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($order['addon_image']); ?>" 
                                                                         alt="<?php echo htmlspecialchars($order['addon_name']); ?>" 
                                                                         class="img-thumbnail me-2" style="max-height: 40px;">
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($order['addon_name']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-bold">₹<?php echo number_format($order['amount'], 2); ?></span>
                                                                <?php if ($order['price_applied'] === 'special'): ?>
                                                                    <span class="small text-muted">
                                                                        <s>₹<?php echo number_format($order['original_price'], 2); ?></s>
                                                                        <span class="badge badge-special ms-1">SAVED ₹<?php 
                                                                            echo number_format($order['original_price'] - $order['amount'], 2);
                                                                        ?></span>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success">Paid</span>
                                                            <div class="small text-muted mt-1">
                                                                <?php echo htmlspecialchars(substr($order['payment_id'], 0, 8)); ?>...
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M d, Y', strtotime($order['purchase_date'])); ?>
                                                            <div class="small text-muted">
                                                                <?php echo date('h:i A', strtotime($order['purchase_date'])); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary view-details" 
                                                                    data-order-id="<?php echo $order['id']; ?>">
                                                                Details
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize DataTable with responsive options
        $('#ordersTable').DataTable({
            order: [[5, 'desc']],
            responsive: true,
            // Disable some features on mobile for better performance
            pageLength: 10,
            lengthChange: false,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search orders..."
            },
            // Custom responsive rendering
            drawCallback: function(settings) {
                // Add touch-friendly class to buttons after table is drawn
                $('.view-details').addClass('touch-friendly');
            }
        });

        // Handle order details click
        $('body').on('click', '.view-details', function() {
            var orderId = $(this).data('order-id');
            $.ajax({
                url: 'get_order_details.php',
                method: 'GET',
                data: { order_id: orderId },
                success: function(data) {
                    $('#orderDetailsContent').html(data);
                    var modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                    modal.show();
                },
                error: function() {
                    alert('Error loading order details');
                }
            });
        });
        
        // Improve touch experience
        if ('ontouchstart' in document.documentElement) {
            $('body').addClass('touch-device');
            // Increase tap target sizes for touch devices
            $('.btn, .view-details').css({
                'min-height': '44px',
                'min-width': '44px',
                'padding': '12px 16px'
            });
        }
    });
    </script>
</body>
</html>
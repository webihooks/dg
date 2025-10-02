<?php
session_start();
require 'db_connection.php';

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied');
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id || !is_numeric($order_id)) {
    die('Invalid order ID');
}

// Fetch complete order details
$sql = "SELECT 
            ua.*, 
            a.name as addon_name, 
            a.description as addon_description,
            a.image as addon_image,
            u.name as customer_name,
            u.email as customer_email,
            u.phone as customer_phone
        FROM user_addons ua
        JOIN addons a ON ua.addon_id = a.id
        JOIN users u ON ua.user_id = u.id
        WHERE ua.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    die('Order not found');
}
?>

<div class="row">
    <div class="col-md-6">
        <h5>Order Information</h5>
        <table class="table table-sm">
            <tr>
                <th>Order ID:</th>
                <td><code><?php echo htmlspecialchars($order['order_id']); ?></code></td>
            </tr>
            <tr>
                <th>Payment ID:</th>
                <td><code><?php echo htmlspecialchars($order['payment_id']); ?></code></td>
            </tr>
            <tr>
                <th>Purchase Date:</th>
                <td><?php echo date('M d, Y h:i A', strtotime($order['purchase_date'])); ?></td>
            </tr>
            <tr>
                <th>Price Applied:</th>
                <td>
                    <span class="badge <?php echo $order['price_applied'] === 'special' ? 'bg-warning' : 'bg-primary'; ?>">
                        <?php echo ucfirst($order['price_applied']); ?> Price
                    </span>
                </td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h5>Customer Information</h5>
        <table class="table table-sm">
            <tr>
                <th>Name:</th>
                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
            </tr>
            <tr>
                <th>Email:</th>
                <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
            </tr>
            <tr>
                <th>Phone:</th>
                <td><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>User ID:</th>
                <td><?php echo $order['user_id']; ?></td>
            </tr>
        </table>
    </div>
</div>


<div class="row mt-4">
    <div class="col-md-12">
        <h5>Addon Details</h5>
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <?php if (!empty($order['addon_image'])): ?>
                            <img src="<?php echo htmlspecialchars($order['addon_image']); ?>" 
                                 class="img-fluid rounded" 
                                 alt="<?php echo htmlspecialchars($order['addon_name']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <h4><?php echo htmlspecialchars($order['addon_name']); ?></h4>
                        <p><?php echo nl2br(htmlspecialchars($order['addon_description'])); ?></p>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Pricing</h6>
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <th>Original Price:</th>
                                                <td>₹<?php echo number_format($order['original_price'], 2); ?></td>
                                            </tr>
                                            <?php if ($order['price_applied'] === 'special'): ?>
                                            <tr>
                                                <th>Special Price:</th>
                                                <td>₹<?php echo number_format($order['special_price'], 2); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr class="table-active">
                                                <th>Amount Paid:</th>
                                                <td class="fw-bold">₹<?php echo number_format($order['amount'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
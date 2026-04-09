<?php
// Check if cart exists in session, if not create it
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Function to calculate cart totals
function calculateCartTotals($cart, $products, $delivery_charge, $gst_percent, $discounts) {
    $subtotal = 0;
    foreach ($cart as $item) {
        foreach ($products as $product) {
            if ($product['id'] == $item['id']) {
                $subtotal += $product['price'] * $item['quantity'];
                break;
            }
        }
    }
    
    // Calculate discount
    $discount_amount = 0;
    $applied_discount = null;
    foreach ($discounts as $discount) {
        if ($subtotal >= $discount['min_cart_value']) {
            if ($discount['discount_in_percent'] && $discount['discount_in_percent'] > 0) {
                $discount_amount = ($subtotal * $discount['discount_in_percent'] / 100);
            } else if ($discount['discount_in_flat'] && $discount['discount_in_flat'] > 0) {
                $discount_amount = $discount['discount_in_flat'];
            }
            $applied_discount = $discount;
        }
    }
    
    // Calculate GST
    $tax_amount = (($subtotal - $discount_amount) * $gst_percent / 100);
    
    // Delivery charge (free if subtotal after discount meets threshold)
    $final_delivery_charge = $delivery_charge;
    if (isset($delivery_charges['free_delivery_minimum']) && $delivery_charges['free_delivery_minimum'] > 0) {
        if (($subtotal - $discount_amount) >= $delivery_charges['free_delivery_minimum']) {
            $final_delivery_charge = 0;
        }
    }
    
    $total = $subtotal - $discount_amount + $tax_amount + $final_delivery_charge;
    
    return [
        'subtotal' => $subtotal,
        'discount_amount' => $discount_amount,
        'tax_amount' => $tax_amount,
        'delivery_charge' => $final_delivery_charge,
        'total' => $total,
        'applied_discount' => $applied_discount
    ];
}

// Handle AJAX requests for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $product_id = $_POST['product_id'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 1);
    
    $response = ['success' => false, 'message' => '', 'cart_count' => 0, 'cart_total' => 0];
    
    switch ($action) {
        case 'add_to_cart':
            // Check if product exists and has stock
            $product_found = false;
            foreach ($products as $product) {
                if ($product['id'] == $product_id) {
                    if ($product['quantity'] >= $quantity) {
                        $product_found = true;
                    } else {
                        $response['message'] = 'Insufficient stock available';
                        echo json_encode($response);
                        exit();
                    }
                    break;
                }
            }
            
            if ($product_found) {
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'id' => $product_id,
                        'quantity' => $quantity
                    ];
                }
                $response['success'] = true;
                $response['message'] = 'Product added to cart';
            } else {
                $response['message'] = 'Product not found';
            }
            break;
            
        case 'update_cart':
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$product_id]);
            } else {
                // Check stock availability
                foreach ($products as $product) {
                    if ($product['id'] == $product_id) {
                        if ($product['quantity'] >= $quantity) {
                            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        } else {
                            $response['message'] = 'Insufficient stock';
                            echo json_encode($response);
                            exit();
                        }
                        break;
                    }
                }
            }
            $response['success'] = true;
            $response['message'] = 'Cart updated';
            break;
            
        case 'remove_from_cart':
            unset($_SESSION['cart'][$product_id]);
            $response['success'] = true;
            $response['message'] = 'Product removed from cart';
            break;
            
        case 'clear_cart':
            $_SESSION['cart'] = [];
            $response['success'] = true;
            $response['message'] = 'Cart cleared';
            break;
    }
    
    // Calculate cart count and total for response
    $cart_count = 0;
    $cart_subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
        foreach ($products as $product) {
            if ($product['id'] == $item['id']) {
                $cart_subtotal += $product['price'] * $item['quantity'];
                break;
            }
        }
    }
    
    $response['cart_count'] = $cart_count;
    $response['cart_total'] = $cart_subtotal;
    
    echo json_encode($response);
    exit();
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $order_type = $_POST['order_type'] ?? 'delivery';
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $time_slot_id = $_POST['time_slot_id'] ?? null;
    $is_instant = isset($_POST['is_instant']) ? 1 : 0;
    $order_notes = trim($_POST['order_notes'] ?? '');
    $scheduled_date = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
    $scheduled_time_slot = !empty($_POST['scheduled_time_slot']) ? $_POST['scheduled_time_slot'] : null;
    
    $errors = [];
    
    if (empty($customer_name)) {
        $errors[] = "Name is required";
    }
    if (empty($customer_phone)) {
        $errors[] = "Phone number is required";
    }
    if ($order_type === 'delivery' && empty($delivery_address)) {
        $errors[] = "Delivery address is required";
    }
    if (empty($_SESSION['cart'])) {
        $errors[] = "Cart is empty";
    }
    
    // Calculate cart totals
    $cart_totals = calculateCartTotals($_SESSION['cart'], $products, $delivery_charge, $gst_percent, $discounts);
    
    // Prepare order items JSON
    $order_items = [];
    foreach ($_SESSION['cart'] as $item) {
        foreach ($products as $product) {
            if ($product['id'] == $item['id']) {
                $order_items[] = [
                    'id' => $product['id'],
                    'name' => $product['product_name_en'],
                    'price' => $product['price'],
                    'quantity' => $item['quantity'],
                    'total' => $product['price'] * $item['quantity']
                ];
                break;
            }
        }
    }
    
    if (empty($errors)) {
        $order_table = "vegetable_orders_" . $user_id;
        
        // Check if order table exists
        $check_order_table = $conn->prepare("SHOW TABLES LIKE ?");
        $check_order_table->execute([$order_table]);
        
        if (!$check_order_table->fetch(PDO::FETCH_ASSOC)) {
            // Create order table if not exists (simplified version)
            $create_table_sql = "CREATE TABLE IF NOT EXISTS `$order_table` (
                `order_id` int NOT NULL AUTO_INCREMENT,
                `customer_name` varchar(100) NOT NULL,
                `customer_phone` varchar(20) NOT NULL,
                `order_type` enum('delivery','takeaway') NOT NULL DEFAULT 'delivery',
                `delivery_address` text,
                `time_slot_id` int DEFAULT NULL,
                `is_instant` tinyint(1) DEFAULT '0',
                `instant_charge` decimal(10,2) DEFAULT '0.00',
                `order_date` date NOT NULL,
                `order_time` time NOT NULL,
                `scheduled_date` date DEFAULT NULL,
                `scheduled_time_slot` varchar(50) DEFAULT NULL,
                `items` json NOT NULL,
                `subtotal` decimal(10,2) NOT NULL,
                `tax_amount` decimal(10,2) DEFAULT '0.00',
                `total_amount` decimal(10,2) NOT NULL,
                `status` enum('pending','confirmed','preparing','ready','completed','cancelled') DEFAULT 'pending',
                `notes` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";
            $conn->exec($create_table_sql);
        }
        
        $instant_charge = ($is_instant && $instant_delivery_enabled) ? $instant_delivery_charge : 0;
        
        $sql = "INSERT INTO `$order_table` (
            customer_name, customer_phone, order_type, delivery_address, time_slot_id,
            is_instant, instant_charge, order_date, order_time, scheduled_date,
            scheduled_time_slot, items, subtotal, tax_amount, total_amount, notes, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?, 'pending'
        )";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $items_json = json_encode($order_items);
            $stmt->execute([
                $customer_name, $customer_phone, $order_type, $delivery_address, $time_slot_id,
                $is_instant, $instant_charge, $scheduled_date, $scheduled_time_slot,
                $items_json, $cart_totals['subtotal'], $cart_totals['tax_amount'],
                $cart_totals['total'] + $instant_charge, $order_notes
            ]);
            
            // Clear cart after successful order
            $_SESSION['cart'] = [];
            
            echo "<script>
                alert('Order placed successfully!');
                window.location.href = '?profile_url=" . urlencode($profile_url) . "';
            </script>";
            exit();
        } else {
            $errors[] = "Failed to place order. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        echo "<script>alert('" . implode("\\n", $errors) . "');</script>";
    }
}
?>

<style>
    /* Product Card Styles */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        padding: 1rem 0;
    }
    
    .product-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    }
    
    .product-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: #f5f5f5;
    }
    
    .product-info {
        padding: 1rem;
    }
    
    .product-name {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 0.5rem 0;
        color: #333;
    }
    
    .product-name-hi {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.5rem;
    }
    
    .product-price {
        font-size: 1.25rem;
        font-weight: 700;
        color: <?php echo $primary_color; ?>;
        margin-bottom: 0.5rem;
    }
    
    .product-unit {
        font-size: 0.8rem;
        color: #888;
    }
    
    .product-stock {
        font-size: 0.8rem;
        margin-bottom: 0.75rem;
    }
    
    .stock-available {
        color: #28a745;
    }
    
    .stock-low {
        color: #ffc107;
    }
    
    .stock-out {
        color: #dc3545;
    }
    
    .quantity-control {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }
    
    .quantity-btn {
        width: 32px;
        height: 32px;
        border: 1px solid #ddd;
        background: #f8f9fa;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1.2rem;
        font-weight: bold;
        transition: all 0.2s;
    }
    
    .quantity-btn:hover {
        background: <?php echo $primary_color; ?>;
        color: #fff;
        border-color: <?php echo $primary_color; ?>;
    }
    
    .quantity-input {
        width: 50px;
        text-align: center;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 0.25rem;
        font-size: 1rem;
    }
    
    .add-to-cart-btn {
        width: 100%;
        padding: 0.5rem;
        background: <?php echo $primary_color; ?>;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        transition: opacity 0.2s;
    }
    
    .add-to-cart-btn:hover {
        opacity: 0.9;
    }
    
    .add-to-cart-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    
    /* Cart Sidebar Styles */
    .cart-sidebar {
        position: fixed;
        right: -400px;
        top: 0;
        width: 380px;
        height: 100vh;
        background: #fff;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        z-index: 1000;
        transition: right 0.3s ease;
        display: flex;
        flex-direction: column;
    }
    
    .cart-sidebar.open {
        right: 0;
    }
    
    .cart-header {
        padding: 1rem;
        background: <?php echo $primary_color; ?>;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .cart-header h3 {
        margin: 0;
        font-size: 1.2rem;
    }
    
    .close-cart {
        background: none;
        border: none;
        color: #fff;
        font-size: 1.5rem;
        cursor: pointer;
    }
    
    .cart-items {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    
    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #eee;
    }
    
    .cart-item-info {
        flex: 1;
    }
    
    .cart-item-name {
        font-weight: 500;
        margin-bottom: 0.25rem;
    }
    
    .cart-item-price {
        font-size: 0.85rem;
        color: #666;
    }
    
    .cart-item-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .cart-qty-btn {
        width: 28px;
        height: 28px;
        border: 1px solid #ddd;
        background: #f8f9fa;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .cart-item-qty {
        width: 35px;
        text-align: center;
    }
    
    .cart-item-total {
        font-weight: 600;
        min-width: 60px;
        text-align: right;
    }
    
    .remove-item {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0 0.25rem;
    }
    
    .cart-footer {
        padding: 1rem;
        border-top: 1px solid #eee;
        background: #f9f9f9;
    }
    
    .cart-total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    
    .cart-grand-total {
        font-size: 1.1rem;
        font-weight: 700;
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 2px solid #ddd;
    }
    
    .checkout-btn {
        width: 100%;
        padding: 0.75rem;
        background: <?php echo $primary_color; ?>;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        margin-top: 1rem;
    }
    
    .cart-icon {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        background: <?php echo $primary_color; ?>;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 999;
        transition: transform 0.2s;
    }
    
    .cart-icon:hover {
        transform: scale(1.05);
    }
    
    .cart-icon i {
        font-size: 1.5rem;
        color: #fff;
    }
    
    .cart-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4444;
        color: #fff;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    /* Order Form Modal */
    .order-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1001;
        align-items: center;
        justify-content: center;
    }
    
    .order-modal.active {
        display: flex;
    }
    
    .order-modal-content {
        background: #fff;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 85vh;
        overflow-y: auto;
        padding: 1.5rem;
    }
    
    .order-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #eee;
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.25rem;
        font-weight: 500;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 0.9rem;
    }
    
    .form-row {
        display: flex;
        gap: 1rem;
    }
    
    .form-row .form-group {
        flex: 1;
    }
    
    .order-summary {
        background: #f5f5f5;
        padding: 0.75rem;
        border-radius: 6px;
        margin: 1rem 0;
    }
    
    .order-summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
    }
    
    .instant-delivery-option {
        background: #fff3cd;
        padding: 0.75rem;
        border-radius: 6px;
        margin-bottom: 1rem;
    }
    
    .section-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid <?php echo $primary_color; ?>;
        display: inline-block;
    }
    
    .empty-cart-message {
        text-align: center;
        padding: 2rem;
        color: #888;
    }
    
    @media (max-width: 768px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1rem;
        }
        
        .cart-sidebar {
            width: 100%;
            right: -100%;
        }
    }
</style>

<div class="container mt-4">
    <!-- Products Section -->
    <div class="products-section">
        <h2 class="section-title">
            <i class="fas fa-leaf"></i> Our Fresh Products
        </h2>
        
        <?php if (empty($products)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> No products available at the moment. Please check back later.
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): 
                    $stock_status = '';
                    $stock_text = '';
                    if ($product['quantity'] <= 0) {
                        $stock_status = 'stock-out';
                        $stock_text = 'Out of Stock';
                        $disabled = true;
                    } elseif ($product['quantity'] <= 5) {
                        $stock_status = 'stock-low';
                        $stock_text = 'Only ' . $product['quantity'] . ' left';
                        $disabled = false;
                    } else {
                        $stock_status = 'stock-available';
                        $stock_text = 'In Stock';
                        $disabled = false;
                    }
                ?>
                    <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                        <img src="<?php echo !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : (!empty($product['master_image']) ? htmlspecialchars($product['master_image']) : 'assets/images/default-product.png'); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name_en']); ?>" 
                             class="product-image"
                             onerror="this.src='assets/images/default-product.png'">
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['product_name_en']); ?></h3>
                            <?php if (!empty($product['product_name_hi'])): ?>
                                <div class="product-name-hi"><?php echo htmlspecialchars($product['product_name_hi']); ?></div>
                            <?php endif; ?>
                            <div class="product-price">
                                <?php echo $currency_symbol; ?> <?php echo number_format($product['price'], 2); ?>
                                <span class="product-unit">/ <?php echo htmlspecialchars($product['unit']); ?></span>
                            </div>
                            <div class="product-stock <?php echo $stock_status; ?>">
                                <i class="fas <?php echo $stock_status == 'stock-out' ? 'fa-times-circle' : ($stock_status == 'stock-low' ? 'fa-exclamation-triangle' : 'fa-check-circle'); ?>"></i>
                                <?php echo $stock_text; ?>
                            </div>
                            
                            <?php if (!$disabled): ?>
                                <div class="quantity-control">
                                    <button class="quantity-btn" data-action="decrease">-</button>
                                    <input type="number" class="quantity-input" value="1" min="1" max="<?php echo $product['quantity']; ?>">
                                    <button class="quantity-btn" data-action="increase">+</button>
                                </div>
                                <button class="add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button class="add-to-cart-btn" disabled>
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cart Icon -->
<div class="cart-icon" id="cartIcon">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-badge" id="cartBadge">0</span>
</div>

<!-- Cart Sidebar -->
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
        <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
        <button class="close-cart" id="closeCart">&times;</button>
    </div>
    <div class="cart-items" id="cartItems">
        <div class="empty-cart-message">
            <i class="fas fa-shopping-basket"></i>
            <p>Your cart is empty</p>
        </div>
    </div>
    <div class="cart-footer" id="cartFooter" style="display: none;">
        <div class="cart-total-row">
            <span>Subtotal:</span>
            <span id="cartSubtotal"><?php echo $currency_symbol; ?> 0.00</span>
        </div>
        <div class="cart-total-row" id="discountRow" style="display: none;">
            <span>Discount:</span>
            <span id="cartDiscount" class="text-success">- <?php echo $currency_symbol; ?> 0.00</span>
        </div>
        <div class="cart-total-row">
            <span>Delivery Charge:</span>
            <span id="cartDeliveryCharge"><?php echo $currency_symbol; ?> <?php echo number_format($delivery_charge, 2); ?></span>
        </div>
        <div class="cart-total-row">
            <span>Tax (<?php echo $gst_percent; ?>%):</span>
            <span id="cartTax"><?php echo $currency_symbol; ?> 0.00</span>
        </div>
        <div class="cart-total-row cart-grand-total">
            <span>Total:</span>
            <span id="cartTotal"><?php echo $currency_symbol; ?> 0.00</span>
        </div>
        <button class="checkout-btn" id="checkoutBtn">
            <i class="fas fa-credit-card"></i> Proceed to Checkout
        </button>
    </div>
</div>

<!-- Order Modal -->
<div class="order-modal" id="orderModal">
    <div class="order-modal-content">
        <div class="order-modal-header">
            <h3><i class="fas fa-clipboard-list"></i> Place Order</h3>
            <button class="close-modal" id="closeModal">&times;</button>
        </div>
        
        <form method="POST" id="orderForm">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="customer_name" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="tel" name="customer_phone" required>
            </div>
            
            <div class="form-group">
                <label>Order Type *</label>
                <select name="order_type" id="orderTypeSelect" required>
                    <option value="delivery">Delivery</option>
                    <option value="takeaway">Takeaway</option>
                </select>
            </div>
            
            <div class="form-group" id="deliveryAddressGroup">
                <label>Delivery Address *</label>
                <textarea name="delivery_address" rows="3"></textarea>
            </div>
            
            <?php if ($instant_delivery_enabled): ?>
                <div class="instant-delivery-option">
                    <label>
                        <input type="checkbox" name="is_instant" id="instantDeliveryCheckbox">
                        <i class="fas fa-bolt"></i> Instant Delivery (Extra <?php echo $currency_symbol; ?> <?php echo number_format($instant_delivery_charge, 2); ?>)
                    </label>
                </div>
            <?php endif; ?>
            
            <div class="form-group" id="scheduledDateTimeGroup" style="display: none;">
                <label>Scheduled Date</label>
                <input type="date" name="scheduled_date" id="scheduledDate">
            </div>
            
            <?php if (!empty($time_slots)): ?>
                <div class="form-group" id="timeSlotGroup">
                    <label>Time Slot</label>
                    <select name="time_slot_id">
                        <option value="">Select time slot</option>
                        <?php foreach ($time_slots as $slot): ?>
                            <option value="<?php echo $slot['id']; ?>">
                                <?php echo htmlspecialchars($slot['slot_name']); ?> 
                                (<?php echo date('g:i A', strtotime($slot['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($slot['end_time'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Notes (Optional)</label>
                <textarea name="order_notes" rows="2"></textarea>
            </div>
            
            <div class="order-summary" id="orderSummary">
                <div class="order-summary-row">
                    <span>Items:</span>
                    <span id="orderItemsCount">0</span>
                </div>
                <div class="order-summary-row">
                    <span>Subtotal:</span>
                    <span id="orderSubtotal"><?php echo $currency_symbol; ?> 0.00</span>
                </div>
                <div class="order-summary-row" id="orderDiscountRow" style="display: none;">
                    <span>Discount:</span>
                    <span id="orderDiscount" class="text-success">- <?php echo $currency_symbol; ?> 0.00</span>
                </div>
                <div class="order-summary-row">
                    <span>Delivery:</span>
                    <span id="orderDelivery"><?php echo $currency_symbol; ?> <?php echo number_format($delivery_charge, 2); ?></span>
                </div>
                <div class="order-summary-row">
                    <span>Tax (<?php echo $gst_percent; ?>%):</span>
                    <span id="orderTax"><?php echo $currency_symbol; ?> 0.00</span>
                </div>
                <div class="order-summary-row" style="font-weight: 700; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #ddd;">
                    <span>Total:</span>
                    <span id="orderTotal"><?php echo $currency_symbol; ?> 0.00</span>
                </div>
            </div>
            
            <button type="submit" name="place_order" class="checkout-btn" style="margin-top: 1rem;">
                <i class="fas fa-check-circle"></i> Place Order
            </button>
        </form>
    </div>
</div>

<script>
// Product data for JavaScript
const productsData = <?php echo json_encode($products); ?>;
const deliveryCharge = <?php echo $delivery_charge; ?>;
const instantDeliveryCharge = <?php echo $instant_delivery_charge; ?>;
const instantDeliveryEnabled = <?php echo $instant_delivery_enabled ? 'true' : 'false'; ?>;
const gstPercent = <?php echo $gst_percent; ?>;
const discounts = <?php echo json_encode($discounts); ?>;
const currencySymbol = '<?php echo $currency_symbol; ?>';

// Cart state
let cart = {};

// Load cart from localStorage
function loadCart() {
    const savedCart = localStorage.getItem('vegetableCart_' + <?php echo $user_id; ?>);
    if (savedCart) {
        cart = JSON.parse(savedCart);
    }
    updateCartUI();
}

// Save cart to localStorage
function saveCart() {
    localStorage.setItem('vegetableCart_' + <?php echo $user_id; ?>, JSON.stringify(cart));
}

// Get product details by ID
function getProductById(productId) {
    return productsData.find(p => p.id == productId);
}

// Calculate cart totals
function calculateTotals() {
    let subtotal = 0;
    let itemCount = 0;
    
    for (const [id, item] of Object.entries(cart)) {
        const product = getProductById(id);
        if (product) {
            subtotal += product.price * item.quantity;
            itemCount += item.quantity;
        }
    }
    
    // Calculate discount
    let discountAmount = 0;
    let appliedDiscount = null;
    
    for (const discount of discounts) {
        if (subtotal >= discount.min_cart_value) {
            if (discount.discount_in_percent && discount.discount_in_percent > 0) {
                discountAmount = subtotal * discount.discount_in_percent / 100;
            } else if (discount.discount_in_flat && discount.discount_in_flat > 0) {
                discountAmount = discount.discount_in_flat;
            }
            appliedDiscount = discount;
        }
    }
    
    // Calculate tax
    const taxAmount = (subtotal - discountAmount) * gstPercent / 100;
    
    // Delivery charge (free if subtotal after discount >= 500 - adjust as needed)
    let finalDeliveryCharge = deliveryCharge;
    // You can add free delivery threshold logic here
    
    const total = subtotal - discountAmount + taxAmount + finalDeliveryCharge;
    
    return {
        subtotal,
        discountAmount,
        taxAmount,
        deliveryCharge: finalDeliveryCharge,
        total,
        itemCount,
        appliedDiscount
    };
}

// Update cart UI
function updateCartUI() {
    const totals = calculateTotals();
    
    // Update cart badge
    document.getElementById('cartBadge').textContent = totals.itemCount;
    
    // Update cart items display
    const cartItemsContainer = document.getElementById('cartItems');
    const cartFooter = document.getElementById('cartFooter');
    
    if (totals.itemCount === 0) {
        cartItemsContainer.innerHTML = `
            <div class="empty-cart-message">
                <i class="fas fa-shopping-basket"></i>
                <p>Your cart is empty</p>
            </div>
        `;
        cartFooter.style.display = 'none';
        return;
    }
    
    cartFooter.style.display = 'block';
    
    let itemsHtml = '';
    for (const [id, item] of Object.entries(cart)) {
        const product = getProductById(id);
        if (product) {
            itemsHtml += `
                <div class="cart-item" data-product-id="${id}">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${escapeHtml(product.product_name_en)}</div>
                        <div class="cart-item-price">${currencySymbol} ${product.price.toFixed(2)} / ${product.unit}</div>
                    </div>
                    <div class="cart-item-actions">
                        <button class="cart-qty-btn" data-action="decrease" data-id="${id}">-</button>
                        <span class="cart-item-qty">${item.quantity}</span>
                        <button class="cart-qty-btn" data-action="increase" data-id="${id}">+</button>
                        <button class="remove-item" data-id="${id}">&times;</button>
                    </div>
                    <div class="cart-item-total">${currencySymbol} ${(product.price * item.quantity).toFixed(2)}</div>
                </div>
            `;
        }
    }
    cartItemsContainer.innerHTML = itemsHtml;
    
    // Update totals
    document.getElementById('cartSubtotal').textContent = `${currencySymbol} ${totals.subtotal.toFixed(2)}`;
    document.getElementById('cartTax').textContent = `${currencySymbol} ${totals.taxAmount.toFixed(2)}`;
    document.getElementById('cartDeliveryCharge').textContent = `${currencySymbol} ${totals.deliveryCharge.toFixed(2)}`;
    document.getElementById('cartTotal').textContent = `${currencySymbol} ${totals.total.toFixed(2)}`;
    
    const discountRow = document.getElementById('discountRow');
    const cartDiscount = document.getElementById('cartDiscount');
    if (totals.discountAmount > 0) {
        discountRow.style.display = 'flex';
        cartDiscount.textContent = `- ${currencySymbol} ${totals.discountAmount.toFixed(2)}`;
    } else {
        discountRow.style.display = 'none';
    }
    
    saveCart();
}

// Add to cart
function addToCart(productId, quantity) {
    const product = getProductById(productId);
    if (!product) return false;
    
    if (product.quantity < quantity) {
        alert('Insufficient stock available');
        return false;
    }
    
    if (cart[productId]) {
        const newQuantity = cart[productId].quantity + quantity;
        if (product.quantity < newQuantity) {
            alert('Insufficient stock available');
            return false;
        }
        cart[productId].quantity = newQuantity;
    } else {
        cart[productId] = {
            id: productId,
            quantity: quantity
        };
    }
    
    updateCartUI();
    return true;
}

// Update cart item quantity
function updateCartItem(productId, quantity) {
    const product = getProductById(productId);
    if (!product) return;
    
    if (quantity <= 0) {
        delete cart[productId];
    } else {
        if (product.quantity < quantity) {
            alert('Insufficient stock available');
            return;
        }
        cart[productId].quantity = quantity;
    }
    
    updateCartUI();
}

// Remove from cart
function removeFromCart(productId) {
    delete cart[productId];
    updateCartUI();
}

// Clear cart
function clearCart() {
    cart = {};
    updateCartUI();
}

// Escape HTML to prevent XSS
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Update order summary in modal
function updateOrderSummary() {
    const totals = calculateTotals();
    const orderType = document.getElementById('orderTypeSelect').value;
    const isInstant = document.getElementById('instantDeliveryCheckbox')?.checked || false;
    
    let deliveryFee = deliveryCharge;
    if (orderType === 'takeaway') {
        deliveryFee = 0;
    } else if (isInstant && instantDeliveryEnabled) {
        deliveryFee = instantDeliveryCharge;
    }
    
    // Recalculate total with selected delivery fee
    const finalTotal = totals.subtotal - totals.discountAmount + totals.taxAmount + deliveryFee;
    
    document.getElementById('orderItemsCount').textContent = totals.itemCount;
    document.getElementById('orderSubtotal').textContent = `${currencySymbol} ${totals.subtotal.toFixed(2)}`;
    document.getElementById('orderDelivery').textContent = `${currencySymbol} ${deliveryFee.toFixed(2)}`;
    document.getElementById('orderTax').textContent = `${currencySymbol} ${totals.taxAmount.toFixed(2)}`;
    document.getElementById('orderTotal').textContent = `${currencySymbol} ${finalTotal.toFixed(2)}`;
    
    const orderDiscountRow = document.getElementById('orderDiscountRow');
    const orderDiscount = document.getElementById('orderDiscount');
    if (totals.discountAmount > 0) {
        orderDiscountRow.style.display = 'flex';
        orderDiscount.textContent = `- ${currencySymbol} ${totals.discountAmount.toFixed(2)}`;
    } else {
        orderDiscountRow.style.display = 'none';
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    loadCart();
    
    // Cart icon click
    document.getElementById('cartIcon').addEventListener('click', function() {
        document.getElementById('cartSidebar').classList.add('open');
    });
    
    // Close cart
    document.getElementById('closeCart').addEventListener('click', function() {
        document.getElementById('cartSidebar').classList.remove('open');
    });
    
    // Close modal
    document.getElementById('closeModal').addEventListener('click', function() {
        document.getElementById('orderModal').classList.remove('active');
    });
    
    // Checkout button
    document.getElementById('checkoutBtn').addEventListener('click', function() {
        const totals = calculateTotals();
        if (totals.itemCount === 0) {
            alert('Your cart is empty');
            return;
        }
        updateOrderSummary();
        document.getElementById('orderModal').classList.add('active');
    });
    
    // Order type change
    document.getElementById('orderTypeSelect').addEventListener('change', function() {
        const deliveryAddressGroup = document.getElementById('deliveryAddressGroup');
        const scheduledDateTimeGroup = document.getElementById('scheduledDateTimeGroup');
        
        if (this.value === 'delivery') {
            deliveryAddressGroup.style.display = 'block';
            document.querySelector('textarea[name="delivery_address"]').required = true;
        } else {
            deliveryAddressGroup.style.display = 'none';
            document.querySelector('textarea[name="delivery_address"]').required = false;
        }
        
        updateOrderSummary();
    });
    
    // Instant delivery checkbox
    const instantCheckbox = document.getElementById('instantDeliveryCheckbox');
    if (instantCheckbox) {
        instantCheckbox.addEventListener('change', function() {
            const scheduledDateTimeGroup = document.getElementById('scheduledDateTimeGroup');
            if (this.checked) {
                scheduledDateTimeGroup.style.display = 'none';
                document.getElementById('scheduledDate').required = false;
            } else {
                scheduledDateTimeGroup.style.display = 'block';
            }
            updateOrderSummary();
        });
    }
    
    // Set minimum date for scheduled date
    const today = new Date().toISOString().split('T')[0];
    const scheduledDateInput = document.getElementById('scheduledDate');
    if (scheduledDateInput) {
        scheduledDateInput.min = today;
    }
    
    // Product card quantity controls
    document.querySelectorAll('.product-card').forEach(card => {
        const productId = card.dataset.productId;
        const decreaseBtn = card.querySelector('[data-action="decrease"]');
        const increaseBtn = card.querySelector('[data-action="increase"]');
        const quantityInput = card.querySelector('.quantity-input');
        const addToCartBtn = card.querySelector('.add-to-cart-btn');
        
        if (decreaseBtn) {
            decreaseBtn.addEventListener('click', function() {
                let currentVal = parseInt(quantityInput.value);
                if (currentVal > 1) {
                    quantityInput.value = currentVal - 1;
                }
            });
        }
        
        if (increaseBtn) {
            increaseBtn.addEventListener('click', function() {
                let currentVal = parseInt(quantityInput.value);
                const maxStock = parseInt(quantityInput.max);
                if (currentVal < maxStock) {
                    quantityInput.value = currentVal + 1;
                }
            });
        }
        
        if (addToCartBtn) {
            addToCartBtn.addEventListener('click', function() {
                const quantity = parseInt(quantityInput.value);
                addToCart(productId, quantity);
                
                // Show feedback
                const originalText = addToCartBtn.innerHTML;
                addToCartBtn.innerHTML = '<i class="fas fa-check"></i> Added!';
                setTimeout(() => {
                    addToCartBtn.innerHTML = originalText;
                }, 1500);
            });
        }
    });
    
    // Cart item actions (delegation)
    document.getElementById('cartItems').addEventListener('click', function(e) {
        const target = e.target;
        const productId = target.dataset.id;
        
        if (target.classList.contains('cart-qty-btn')) {
            const action = target.dataset.action;
            const cartItem = cart[productId];
            if (cartItem) {
                let newQuantity = cartItem.quantity;
                if (action === 'increase') {
                    newQuantity++;
                } else if (action === 'decrease') {
                    newQuantity--;
                }
                updateCartItem(productId, newQuantity);
            }
        } else if (target.classList.contains('remove-item')) {
            removeFromCart(productId);
        }
    });
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('cartSidebar');
        const cartIcon = document.getElementById('cartIcon');
        
        if (sidebar.classList.contains('open') && 
            !sidebar.contains(e.target) && 
            !cartIcon.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
});
</script>
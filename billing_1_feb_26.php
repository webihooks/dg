<?php
// billing.php - Restaurant Billing System
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
require 'db_connection.php';
$user_id = $_SESSION['user_id'];
$message = $error = '';

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['order_id'])) {
    $message = "Bill created successfully! Bill #" . htmlspecialchars($_GET['order_id']);
}

// Fetch business information for bill header
$business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $user_id);
$business_stmt->execute();
$business_stmt->bind_result($business_name, $business_address);
$business_stmt->fetch();
$business_stmt->close();

// Set default business info if not available
if (empty($business_name)) {
    $business_name = "Your Restaurant";
    $business_address = "123 Restaurant Street, City";
}

// Get user's country for currency and tax label
$user_sql = "SELECT country FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_country);
$user_stmt->fetch();
$user_stmt->close();

// Set tax label based on country (GST for India, VAT for UAE)
$taxLabel = ($user_country == 'UAE') ? 'VAT' : 'GST';

// Get currency symbol based on country
function getCurrencySymbol($country) {
    $currencySymbols = [
        'India' => '₹',
        'UAE' => 'AED',
        'UK' => '£',
        'USA' => '$'
    ];
    
    return isset($currencySymbols[$country]) ? $currencySymbols[$country] : '₹';
}

$currencySymbol = getCurrencySymbol($user_country);

// ==================== FETCH BUSINESS CONFIG ====================
// GST Rate
$gst_rate = 0;
$gst_sql = "SELECT gst_percent FROM gst_charge WHERE user_id = ? LIMIT 1";
if ($stmt = $conn->prepare($gst_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($gst_rate);
    $stmt->fetch();
    $stmt->close();
}

// Delivery Charges
$delivery_charge = $free_delivery_minimum = 0;
$delivery_sql = "SELECT delivery_charge, free_delivery_minimum FROM delivery_charges WHERE user_id = ? LIMIT 1";
if ($stmt = $conn->prepare($delivery_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($delivery_charge, $free_delivery_minimum);
    $stmt->fetch();
    $stmt->close();
}

// Table Count
$table_count = 20;
$table_sql = "SELECT table_count FROM dining_tables WHERE user_id = ? LIMIT 1";
if ($stmt = $conn->prepare($table_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($table_count_result);
    if ($stmt->fetch()) {
        $table_count = $table_count_result;
    }
    $stmt->close();
}

// ==================== HANDLE FORM SUBMISSION ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_bill'])) {
    // Collect form data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $order_type = $_POST['order_type'] ?? 'dining';
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $table_number = $_POST['table_number'] ?? '';
    $order_notes = trim($_POST['order_notes'] ?? '');
    $order_items = $_POST['order_items'] ?? [];

    // Validation
    $errors = [];
    if (empty($order_items)) {
        $errors[] = "Please add at least one item to the cart";
    }

    if ($order_type === 'dining' && empty($table_number)) {
        $errors[] = "Table number is required for dining orders";
    }

    if (($order_type === 'delivery' || $order_type === 'takeaway') && empty($customer_name)) {
        $errors[] = "Customer name is required";
    }

    if (($order_type === 'delivery' || $order_type === 'takeaway') && empty($customer_phone)) {
        $errors[] = "Phone number is required";
    }

    if ($order_type === 'delivery' && empty($delivery_address)) {
        $errors[] = "Delivery address is required";
    }

    // If validation errors, return error response
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode("<br>", $errors)]);
        exit;
    }

    // Calculate subtotal
    $subtotal = 0;
    foreach ($order_items as $item) {
        $subtotal += ((float)$item['price'] * (int)$item['quantity']);
    }
    
    // Calculate GST
    $gst_amount = ($subtotal * $gst_rate) / 100;
    
    // Calculate delivery charge
    $final_delivery_charge = 0;
    if ($order_type === 'delivery') {
        if ($free_delivery_minimum > 0 && $subtotal >= $free_delivery_minimum) {
            $final_delivery_charge = 0;
        } else {
            $final_delivery_charge = $delivery_charge;
        }
    }
    
    $total_amount = $subtotal + $gst_amount + $final_delivery_charge;

    // Start transaction
    $conn->begin_transaction();
    try {
        // Create new order with status "Confirmed"
        $order_sql = "INSERT INTO orders (user_id, customer_name, customer_phone, order_type, 
                      delivery_address, table_number, order_notes, subtotal, gst_amount, 
                      delivery_charge, total_amount, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed')";
        
        $stmt = $conn->prepare($order_sql);
        $stmt->bind_param("issssssdddd", 
            $user_id, 
            $customer_name, 
            $customer_phone, 
            $order_type,
            $delivery_address, 
            $table_number, 
            $order_notes, 
            $subtotal,
            $gst_amount, 
            $final_delivery_charge, 
            $total_amount
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create order: " . $stmt->error);
        }
        $order_id = $conn->insert_id;
        $stmt->close();

        // Add order items
        $item_sql = "INSERT INTO order_items (order_id, product_name, price, quantity) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($item_sql);
        foreach ($order_items as $item) {
            $stmt->bind_param("isdi", $order_id, $item['product_name'], $item['price'], $item['quantity']);
            $stmt->execute();
        }
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'order_id' => $order_id]);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ==================== FETCH PRODUCTS ====================
$products = [];
$products_table = "products_" . $user_id;

// Get user's base URL for images
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

// Function to get image URL
function getImageUrl($image_path, $base_url) {
    if (empty($image_path)) return null;
    
    if (filter_var($image_path, FILTER_VALIDATE_URL)) {
        return $image_path;
    }
    
    $clean_path = str_replace('../', '', $image_path);
    
    if (strpos($clean_path, '/') !== 0) {
        $clean_path = ltrim($clean_path, '/');
        return $base_url . '/' . $clean_path;
    }
    
    if (file_exists($clean_path)) {
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        if (strpos($clean_path, $doc_root) === 0) {
            $relative_path = str_replace($doc_root, '', $clean_path);
            return $base_url . $relative_path;
        }
        
        if (filesize($clean_path) < 500000) {
            $mime_type = mime_content_type($clean_path);
            $image_data = base64_encode(file_get_contents($clean_path));
            return 'data:' . $mime_type . ';base64,' . $image_data;
        }
    }
    
    return null;
}

// Function to format price without .00
function formatPriceWithoutZero($price) {
    $price = floatval($price);
    if (is_numeric($price) && floor($price) == $price) {
        return number_format($price, 0);
    } else {
        return number_format($price, 2);
    }
}

// Check if products table exists
$table_check = $conn->query("SHOW TABLES LIKE '$products_table'");
if ($table_check && $table_check->num_rows > 0) {
    $products_sql = "SELECT id, product_name, price, image_path FROM $products_table WHERE is_active = 1 ORDER BY product_name";
    $result = $conn->query($products_sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['price'] = (float)$row['price'];
            $row['image_url'] = getImageUrl($row['image_path'], $base_url);
            $row['formatted_price'] = formatPriceWithoutZero($row['price']);
            $products[] = $row;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Restaurant Billing System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Restaurant POS">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="application-name" content="Restaurant POS">
    <meta name="mobile-web-app-capable" content="yes">
    
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root{--primary-color:#fb5b29;--secondary-color:#28a745;--light-bg:#f8f9fa;--border-color:#e0e0e0}
        .fullscreen-active{position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:white;overflow:auto}
        .fullscreen-active .container-fluid{max-width:100%;padding:20px}
        .bill-container{background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);margin-bottom:20px}
        .bill-header{background:linear-gradient(135deg,var(--primary-color),#ff7b54);color:white;padding:15px 20px;border-radius:10px 10px 0 0}
        .order-type-btn{padding:8px 15px;border:2px solid var(--primary-color);background:white;color:var(--primary-color);border-radius:5px;transition:all 0.3s;cursor:pointer;margin-right:5px;font-weight:500}
        .order-type-btn:hover{background:#fff5f0}.order-type-btn.active{background:var(--primary-color);color:white}
        .customer-info-card{background:var(--light-bg);border-radius:8px;padding:15px}
        .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;max-height:calc(100vh - 300px);overflow-y:auto;padding-right:5px}
        .product-card{background:white;border:1px solid var(--border-color);border-radius:8px;padding:10px;text-align:center;cursor:pointer;transition:all 0.3s;display:flex;flex-direction:column;justify-content:space-between;min-height:150px}
        .product-card:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,0.1);border-color:var(--primary-color)}
        .product-image-container{height:80px;width:100%;display:flex;align-items:center;justify-content:center;margin-bottom:8px;overflow:hidden;border-radius:5px;background:#f8f9fa}
        .product-image{object-fit:cover;width:100%;height:100%}
        .product-name{font-size:13px;line-height:1.3;margin-bottom:5px;font-weight:500;height:35px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
        .product-price{color:var(--secondary-color);font-weight:bold;font-size:14px}
        .cart-item{display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid var(--border-color)}
        .cart-item:last-child{border-bottom:none}
        .cart-item-controls{display:flex;align-items:center;gap:8px}
        .quantity-btn{width:28px;height:28px;border-radius:50%;border:1px solid var(--primary-color);background:white;color:var(--primary-color);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.3s;font-size:14px}
        .quantity-btn:hover{background:var(--primary-color);color:white}
        .quantity-input{width:40px;text-align:center;border:1px solid var(--border-color);border-radius:5px;padding:4px;font-size:14px}
        .bill-summary-card{background:var(--light-bg);border-radius:8px;padding:15px}
        .summary-row{display:flex;justify-content:space-between;padding:5px 0}
        .total-row{border-top:2px solid var(--primary-color);margin-top:10px;padding-top:10px;font-size:1.1em;font-weight:bold}
        .table-number-box{display:flex;flex-wrap:wrap;gap:5px;max-height:150px;overflow-y:auto;padding:5px;}
        .table-box{width:45px;height:45px;display:flex;align-items:center;justify-content:center;background:white;border:2px solid var(--border-color);border-radius:5px;cursor:pointer;font-weight:bold;transition:all 0.3s}
        .table-box:hover{border-color:var(--primary-color);background:#fff5f0}
        .table-box.selected{background:var(--primary-color);color:white;border-color:var(--primary-color)}
        .field-error{border-color:#dc3545!important}
        .error-message{color:#dc3545;font-size:12px;margin-top:5px;display:none}
        .required-field::after{content:" *";color:#dc3545}
        .cart-scroll{max-height:250px;overflow-y:auto;border:1px solid var(--border-color);border-radius:8px;padding:10px;background:white}
        .empty-cart-message{text-align:center;padding:40px 20px;color:#6c757d}
        .toast-notification{position:fixed;top:20px;right:20px;z-index:1050;min-width:250px;opacity:0;transform:translateY(-20px);transition:all 0.3s ease}
        .toast-notification.show{opacity:1;transform:translateY(0)}
        .delivery-info{display:none}
        .fullscreen-toggle-btn{position:fixed;bottom:20px;right:20px;z-index:1000;width:50px;height:50px;border-radius:50%;background:var(--primary-color);color:white;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 10px rgba(0,0,0,0.2);transition:all 0.3s}
        .fullscreen-toggle-btn:hover{transform:scale(1.1)}
        .auto-hide-alert{position:relative;}
        .auto-hide-alert .btn-close{position:absolute;right:10px;top:10px;}
        @media (max-width:768px){.products-grid{grid-template-columns:repeat(auto-fill,minmax(120px,1fr))}.table-box{width:40px;height:40px}.table-number-box{max-height:120px}}
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                
                <!-- Toast Notification -->
                <div class="toast-notification alert alert-info" id="cartToast" style="display: none;">
                    <i class="fas fa-shopping-cart me-2"></i>
                    <span id="toastMessage"></span>
                </div>
                
                
                <!-- Main Billing Interface -->
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-body">
                                <!-- Header -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0"><i class="fas fa-receipt me-2"></i> Create New Bill</h5>
                                    <div class="d-flex">
                                        <button type="button" class="order-type-btn active" data-type="dining">
                                            <i class="fas fa-utensils me-2"></i> Dining
                                        </button>
                                        <button type="button" class="order-type-btn" data-type="delivery">
                                            <i class="fas fa-motorcycle me-2"></i> Delivery
                                        </button>
                                        <button type="button" class="order-type-btn" data-type="takeaway">
                                            <i class="fas fa-shopping-bag me-2"></i> Takeaway
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Left Column: Customer Details -->
                                    <div class="col-md-3 mb-2">
                                        <!-- Customer Information -->
                                        <div class="customer-info-card mb-2">
                                            <h6 class="mb-2"><i class="fas fa-user me-2"></i> Customer Details</h6>
                                            
                                            <!-- Table Selection -->
                                            <div class="mb-2 dining-info" id="tableField">
                                                <label class="form-label required-field">Table Number</label>
                                                <div class="table-number-box" id="tableNumberBox">
                                                    <?php for($i = 1; $i <= $table_count; $i++): ?>
                                                    <div class="table-box" data-table="<?php echo $i; ?>"><?php echo $i; ?></div>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="error-message" id="tableNumberError">Please select a table number</div>
                                            </div>
                                            
                                            <!-- Customer Name -->
                                            <div class="mb-2">
                                                <label class="form-label" id="customerNameLabel">Customer Name</label>
                                                <input type="text" class="form-control" id="customer_name" placeholder="Enter customer name">
                                                <div class="error-message" id="customerNameError"></div>
                                            </div>
                                            
                                            <!-- Phone Number -->
                                            <div class="mb-2">
                                                <label class="form-label" id="customerPhoneLabel">Phone Number</label>
                                                <input type="text" class="form-control" id="customer_phone" placeholder="Enter phone number" maxlength="10">
                                                <div class="error-message" id="customerPhoneError"></div>
                                            </div>
                                            
                                            <!-- Delivery Address -->
                                            <div class="mb-2 delivery-info" id="deliveryAddressField">
                                                <label class="form-label required-field">Delivery Address</label>
                                                <textarea class="form-control" id="delivery_address" rows="2" placeholder="Enter delivery address"></textarea>
                                                <div class="error-message" id="deliveryAddressError">Delivery address is required</div>
                                            </div>
                                            
                                            <!-- Special Instructions -->
                                            <div class="mb-2">
                                                <label class="form-label">Special Instructions</label>
                                                <textarea class="form-control" id="order_notes" rows="2" placeholder="Any special requests or notes"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Middle Column: Menu Items -->
                                    <div class="col-md-5 mb-2">
                                        <!-- Search Bar -->
                                        <div class="mb-2">
                                            <input type="text" class="form-control" id="productSearch" placeholder="Search products...">
                                        </div>
                                        
                                        <!-- Products Grid -->
                                        <div>
                                            <?php if (!empty($products)): ?>
                                            <div class="products-grid" id="productsContainer">
                                                <?php foreach ($products as $product): ?>
                                                <div class="product-card" 
                                                     data-product-id="<?php echo $product['id']; ?>" 
                                                     data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                                     data-product-price="<?php echo $product['price']; ?>">
                                                    <div class="product-image-container">
                                                        <?php if (!empty($product['image_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                             class="product-image" 
                                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                                             loading="lazy">
                                                        <?php else: ?>
                                                        <div class="text-muted">
                                                            <i class="fas fa-image fa-2x"></i>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                                    <div class="product-price"><?php echo $currencySymbol; ?><?php echo $product['formatted_price']; ?></div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div id="noProductsFound" class="alert alert-warning mt-2" style="display: none;">
                                                No products found matching your search.
                                            </div>
                                            <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                No products found. Please add products first.
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                        
                                    <!-- Right Column: Order Cart & Summary -->
                                    <div class="col-md-4 mb-2">
                                        <!-- Order Cart -->
                                        <div class="mb-2">
                                            <h6 class="mb-2"><i class="fas fa-shopping-cart me-2"></i> Order Items</h6>
                                            <div class="cart-scroll" id="orderCart">
                                                <div class="empty-cart-message" id="emptyCart">
                                                    <i class="fas fa-shopping-cart fa-3x mb-2"></i>
                                                    <p>No items added to cart. Select items from the menu.</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bill Summary -->
                                        <div class="bill-summary-card">
                                            <h6 class="mb-2"><i class="fas fa-file-invoice-dollar me-2"></i> Bill Summary</h6>
                                            <div class="summary-row">
                                                <span>Subtotal:</span>
                                                <span><?php echo $currencySymbol; ?><span id="subtotal">0</span></span>
                                            </div>
                                            <div class="summary-row">
                                                <span><?php echo $taxLabel; ?> (<?php echo $gst_rate; ?>%):</span>
                                                <span><?php echo $currencySymbol; ?><span id="gstAmount">0</span></span>
                                            </div>
                                            <div class="summary-row" id="deliveryChargeRow" style="display: none;">
                                                <span>Delivery Charge:</span>
                                                <span><?php echo $currencySymbol; ?><span id="deliveryChargeAmount">0</span></span>
                                            </div>
                                            <div class="summary-row total-row">
                                                <span>Total Amount:</span>
                                                <span><?php echo $currencySymbol; ?><span id="totalAmount">0</span></span>
                                            </div>
                                            
                                            <!-- Bill Form -->
                                            <form id="billForm" method="POST">
                                                <input type="hidden" name="create_bill" value="1">
                                                <input type="hidden" id="order_type_input" name="order_type" value="dining">
                                                <input type="hidden" id="customer_name_input" name="customer_name">
                                                <input type="hidden" id="customer_phone_input" name="customer_phone">
                                                <input type="hidden" id="table_number_input" name="table_number">
                                                <input type="hidden" id="delivery_address_input" name="delivery_address">
                                                <input type="hidden" id="order_notes_input" name="order_notes">
                                                <div id="orderItemsInputs"></div>
                                                
                                                <!-- Action Buttons -->
                                                <div class="d-flex gap-2 mt-3">
                                                    <button type="button" class="btn btn-secondary flex-grow-1" id="clearCart">
                                                        <i class="fas fa-trash me-2"></i> Clear
                                                    </button>
                                                    <button type="submit" class="btn btn-primary flex-grow-1" id="saveBillBtn">
                                                        <i class="fas fa-save me-2"></i> Save Bill
                                                    </button>
                                                </div>
                                            </form>
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
    
    <script>
    $(document).ready(function() {
        console.log('Billing page loaded');
        
        // Auto-hide success alert after 5 seconds
        const successAlert = $('#successAlert');
        if (successAlert.length && successAlert.data('auto-hide')) {
            const hideTime = successAlert.data('auto-hide');
            setTimeout(function() {
                successAlert.alert('close');
            }, hideTime);
        }
        
        // Configuration
        const config = {
            gstRate: <?php echo $gst_rate; ?>,
            deliveryCharge: <?php echo $delivery_charge; ?>,
            freeDeliveryMinimum: <?php echo $free_delivery_minimum; ?>,
            currencySymbol: '<?php echo $currencySymbol; ?>',
            taxLabel: '<?php echo $taxLabel; ?>',
            businessName: '<?php echo addslashes($business_name); ?>',
            businessAddress: '<?php echo addslashes($business_address); ?>'
        };
        
        let cartItems = [];
        let isFullscreen = false;
        let lastCreatedOrderId = null;
        
        // Helper Functions
        function formatPrice(price) {
            price = parseFloat(price);
            if (price % 1 === 0) {
                return price.toString();
            } else {
                return price.toFixed(2);
            }
        }
        
        function showToast(message, type = 'info') {
            const toast = $('#cartToast');
            const toastMessage = $('#toastMessage');
            
            toastMessage.text(message);
            toast.removeClass('alert-info alert-success alert-warning alert-danger')
                 .addClass('alert-' + type)
                 .fadeIn(300)
                 .addClass('show');
            
            setTimeout(() => toast.fadeOut(300).removeClass('show'), 3000);
        }
        
        
        // Field Management
        function updateFieldVisibility(orderType) {
            if (orderType === 'dining') {
                $('.dining-info').show();
                $('.delivery-info').hide();
                $('#deliveryChargeRow').hide();
                
                $('#customerNameLabel').html('Customer Name <small class="text-muted">(optional)</small>');
                $('#customerPhoneLabel').html('Phone Number <small class="text-muted">(optional)</small>');
                
            } else if (orderType === 'delivery') {
                $('.dining-info').hide();
                $('.delivery-info').show();
                $('#deliveryChargeRow').show();
                
                $('#customerNameLabel').html('Customer Name <span class="text-danger">*</span>');
                $('#customerPhoneLabel').html('Phone Number <span class="text-danger">*</span>');
                
            } else if (orderType === 'takeaway') {
                $('.dining-info').hide();
                $('.delivery-info').hide();
                $('#deliveryChargeRow').hide();
                
                $('#customerNameLabel').html('Customer Name <span class="text-danger">*</span>');
                $('#customerPhoneLabel').html('Phone Number <span class="text-danger">*</span>');
            }
            
            if (cartItems.length > 0) {
                calculateTotals();
            }
        }
        
        // Product Management
        function initializeProducts() {
            $(document).on('click', '.product-card', function() {
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const productPrice = parseFloat($(this).data('product-price'));
                
                const existingItem = cartItems.find(item => item.id === productId);
                
                if (existingItem) {
                    existingItem.quantity++;
                    showToast(`${productName} quantity increased to ${existingItem.quantity}`, 'success');
                } else {
                    cartItems.push({ 
                        id: productId, 
                        name: productName, 
                        price: productPrice, 
                        quantity: 1 
                    });
                    showToast(`${productName} added to cart`, 'success');
                }
                
                updateCart();
            });
        }
        
        // Cart Management
        function updateCart() {
            const orderCart = $('#orderCart');
            const emptyCart = $('#emptyCart');
            
            if (cartItems.length === 0) {
                orderCart.html(emptyCart.clone().show());
                resetTotals();
                return;
            }
            
            let cartHtml = '';
            
            cartItems.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                
                cartHtml += `
                    <div class="cart-item">
                        <div>
                            <strong>${item.name}</strong><br>
                            <small>${config.currencySymbol}${formatPrice(item.price)} × ${item.quantity}</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="fw-bold">${config.currencySymbol}${formatPrice(itemTotal)}</div>
                            <div class="cart-item-controls">
                                <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                                <span class="quantity-input">${item.quantity}</span>
                                <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
                                <button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="removeItem(${index})" title="Remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            orderCart.html(cartHtml);
            calculateTotals();
        }
        
        window.updateQuantity = function(index, change) {
            if (cartItems[index]) {
                cartItems[index].quantity += change;
                if (cartItems[index].quantity < 1) cartItems[index].quantity = 1;
                
                updateCart();
            }
        };
        
        window.removeItem = function(index) {
            if (cartItems[index]) {
                const itemName = cartItems[index].name;
                cartItems.splice(index, 1);
                showToast(`${itemName} removed from cart`, 'warning');
                updateCart();
            }
        };
        
        $('#clearCart').click(function() {
            if (cartItems.length === 0) {
                showToast('Cart is already empty!', 'info');
                return;
            }
            
            if (confirm('Clear all items from cart?')) {
                cartItems = [];
                showToast('Cart cleared', 'warning');
                updateCart();
            }
        });
        
        // Totals Calculation
        function resetTotals() {
            $('#subtotal').text('0');
            $('#gstAmount').text('0');
            $('#totalAmount').text('0');
            $('#deliveryChargeAmount').text('0');
        }
        
        function calculateTotals() {
            let subtotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const gstAmount = subtotal * (config.gstRate / 100);
            const orderType = $('#order_type_input').val();
            
            let delivery = 0;
            let showDeliveryRow = (orderType === 'delivery');
            
            if (showDeliveryRow) {
                if (config.freeDeliveryMinimum > 0 && subtotal >= config.freeDeliveryMinimum) {
                    delivery = 0;
                } else {
                    delivery = config.deliveryCharge;
                }
            }
            
            const totalAmount = subtotal + gstAmount + delivery;
            
            $('#subtotal').text(formatPrice(subtotal));
            $('#gstAmount').text(formatPrice(gstAmount));
            $('#totalAmount').text(formatPrice(totalAmount));
            
            if (showDeliveryRow) {
                $('#deliveryChargeRow').show();
                $('#deliveryChargeAmount').text(formatPrice(delivery));
            } else {
                $('#deliveryChargeRow').hide();
            }
        }
        
        // Form Validation
        function validateForm() {
            const orderType = $('#order_type_input').val();
            let isValid = true;
            
            $('.error-message').hide();
            $('.form-control').removeClass('field-error');
            $('.table-box').removeClass('field-error');
            
            if (cartItems.length === 0) {
                showToast('Please add at least one item to the cart.', 'warning');
                return false;
            }
            
            if (orderType === 'dining') {
                if (!$('#table_number_input').val()) {
                    $('#tableNumberError').show();
                    $('.table-box').addClass('field-error');
                    isValid = false;
                }
            } else {
                const customerName = $('#customer_name').val().trim();
                if (!customerName) {
                    $('#customerNameError').text('Customer name is required').show();
                    $('#customer_name').addClass('field-error');
                    isValid = false;
                }
                
                const phone = $('#customer_phone').val().trim();
                if (!phone) {
                    $('#customerPhoneError').text('Phone number is required').show();
                    $('#customer_phone').addClass('field-error');
                    isValid = false;
                } else if (phone.length !== 10 || !/^\d+$/.test(phone)) {
                    $('#customerPhoneError').text('Phone must be 10 digits').show();
                    $('#customer_phone').addClass('field-error');
                    isValid = false;
                }
                
                if (orderType === 'delivery') {
                    const address = $('#delivery_address').val().trim();
                    if (!address) {
                        $('#deliveryAddressError').show();
                        $('#delivery_address').addClass('field-error');
                        isValid = false;
                    }
                }
            }
            
            if (!isValid) {
                showToast('Please fill in all required fields', 'warning');
            }
            
            return isValid;
        }
        
        // Update hidden form fields
        function updateHiddenFields() {
            $('#customer_name_input').val($('#customer_name').val().trim());
            $('#customer_phone_input').val($('#customer_phone').val().trim());
            $('#delivery_address_input').val($('#delivery_address').val().trim());
            $('#order_notes_input').val($('#order_notes').val().trim());
            
            $('#orderItemsInputs').empty();
            cartItems.forEach((item, index) => {
                $('#orderItemsInputs').append(`
                    <input type="hidden" name="order_items[${index}][product_name]" value="${item.name.replace(/"/g, '&quot;')}">
                    <input type="hidden" name="order_items[${index}][price]" value="${item.price}">
                    <input type="hidden" name="order_items[${index}][quantity]" value="${item.quantity}">
                `);
            });
        }
        
        // Event Handlers
        $('.order-type-btn').click(function() {
            const orderType = $(this).data('type');
            
            $('.order-type-btn').removeClass('active');
            $(this).addClass('active');
            $('#order_type_input').val(orderType);
            updateFieldVisibility(orderType);
            
            $('.error-message').hide();
            $('.form-control').removeClass('field-error');
        });
        
        $('.table-box').click(function() {
            const tableNumber = $(this).data('table');
            
            $('.table-box').removeClass('selected');
            $(this).addClass('selected');
            $('#table_number_input').val(tableNumber);
            $('#tableNumberError').hide();
            $('.table-box').removeClass('field-error');
        });
        
        $('#productSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            let foundCount = 0;
            
            $('.product-card').each(function() {
                const productName = $(this).data('product-name').toLowerCase();
                if (productName.includes(searchTerm)) {
                    $(this).show();
                    foundCount++;
                } else {
                    $(this).hide();
                }
            });
            
            if (foundCount === 0 && searchTerm.length > 0) {
                $('#noProductsFound').show();
            } else {
                $('#noProductsFound').hide();
            }
        });
        
        // Form Submission
        $('#billForm').submit(function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return false;
            }
            
            updateHiddenFields();
            
            const saveBtn = $('#saveBillBtn');
            const originalHtml = saveBtn.html();
            saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');
            
            const formData = new FormData(this);
            
            $.ajax({
                url: 'billing.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            lastCreatedOrderId = data.order_id;
                            showToast(`Bill created successfully! Bill #${data.order_id}`, 'success');
                            cartItems = [];
                            updateCart();
                            $('#customer_name').val('');
                            $('#customer_phone').val('');
                            $('#delivery_address').val('');
                            $('#order_notes').val('');
                            $('.table-box').removeClass('selected');
                            $('#table_number_input').val('');
                            
                            // Redirect to show success message
                            setTimeout(() => {
                                window.location.href = 'billing.php?success=1&order_id=' + data.order_id;
                            }, 1500);
                        } else {
                            showToast(data.error, 'danger');
                            saveBtn.prop('disabled', false).html(originalHtml);
                        }
                    } catch (e) {
                        showToast('Error processing response', 'danger');
                        saveBtn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    showToast('Error saving bill. Please try again.', 'danger');
                    saveBtn.prop('disabled', false).html(originalHtml);
                }
            });
        });
        
        // Initialize
        initializeProducts();
        updateFieldVisibility('dining');
        
        console.log('Billing page initialized successfully');
    });
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showToast(message, type = 'info') {
        // Remove existing toasts
        $('.custom-toast').remove();
        
        const toast = $('<div class="alert alert-' + type + ' alert-dismissible fade show custom-toast" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 1050; min-width: 250px;">' + 
                       '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'danger' ? 'exclamation-circle' : 'info-circle') + ' me-2"></i>' +
                       message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        
        $('body').append(toast);
        
        setTimeout(() => {
            toast.alert('close');
        }, 5000);
    }
    </script>
</body>
</html>
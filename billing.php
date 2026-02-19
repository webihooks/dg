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
$existing_order_id = null;
$existing_order_data = null;

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['order_id'])) {
    $message = "Bill created successfully! Bill #" . htmlspecialchars($_GET['order_id']);
}

// Check if we're loading an existing order for update
if (isset($_GET['edit_order']) && is_numeric($_GET['edit_order'])) {
    $existing_order_id = intval($_GET['edit_order']);
    
    // Verify the order belongs to the current user and is active (not paid/cancelled)
    $order_check_sql = "SELECT order_id, customer_name, customer_phone, order_type, delivery_address, table_number, order_notes, subtotal, gst_amount, delivery_charge, total_amount, status FROM orders WHERE order_id = ? AND user_id = ? AND status IN ('Confirmed', 'In Progress')";
    $order_check_stmt = $conn->prepare($order_check_sql);
    $order_check_stmt->bind_param("ii", $existing_order_id, $user_id);
    $order_check_stmt->execute();
    $order_check_result = $order_check_stmt->get_result();
    
    if ($order_check_result->num_rows > 0) {
        $existing_order_data = $order_check_result->fetch_assoc();
    } else {
        $existing_order_id = null;
        $existing_order_data = null;
    }
    $order_check_stmt->close();
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
// First check if send_whatsapp_on_bill column exists
$column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'send_whatsapp_on_bill'");
$send_whatsapp_on_bill = 1; // Default to ON

if ($column_check && $column_check->num_rows > 0) {
    // Column exists, use the actual query
    $user_sql = "SELECT country, send_whatsapp_on_bill FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_stmt->bind_result($user_country, $send_whatsapp_on_bill);
    $user_stmt->fetch();
    $user_stmt->close();
} else {
    // Column doesn't exist, just get country
    $user_sql = "SELECT country FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_stmt->bind_result($user_country);
    $user_stmt->fetch();
    $user_stmt->close();
}

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

// Set default for send_whatsapp_on_bill if not set
if (!isset($send_whatsapp_on_bill)) {
    $send_whatsapp_on_bill = 1; // Default to ON
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_bill'])) {
        // Collect form data
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $order_type = $_POST['order_type'] ?? 'dining';
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $table_number = $_POST['table_number'] ?? '';
        $order_notes = trim($_POST['order_notes'] ?? '');
        $order_items = $_POST['order_items'] ?? [];
        $existing_order_id = isset($_POST['existing_order_id']) ? intval($_POST['existing_order_id']) : null;

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
            if ($existing_order_id) {
                // UPDATE EXISTING ORDER
                // Get existing order totals
                $existing_sql = "SELECT subtotal, gst_amount, delivery_charge, total_amount FROM orders WHERE order_id = ? AND user_id = ?";
                $existing_stmt = $conn->prepare($existing_sql);
                $existing_stmt->bind_param("ii", $existing_order_id, $user_id);
                $existing_stmt->execute();
                $existing_stmt->bind_result($existing_subtotal, $existing_gst, $existing_delivery, $existing_total);
                $existing_stmt->fetch();
                $existing_stmt->close();
                
                // Calculate new totals by adding to existing
                $new_subtotal = $existing_subtotal + $subtotal;
                $new_gst_amount = $existing_gst + $gst_amount;
                
                // Recalculate delivery charge if needed (only for delivery orders)
                $new_delivery_charge = $existing_delivery;
                if ($order_type === 'delivery') {
                    if ($free_delivery_minimum > 0 && $new_subtotal >= $free_delivery_minimum) {
                        $new_delivery_charge = 0;
                    } else {
                        $new_delivery_charge = $delivery_charge;
                    }
                }
                
                $new_total_amount = $new_subtotal + $new_gst_amount + $new_delivery_charge;
                
                // Update the order with new totals
                $update_order_sql = "UPDATE orders SET 
                                    subtotal = ?, 
                                    gst_amount = ?, 
                                    delivery_charge = ?, 
                                    total_amount = ?,
                                    updated_at = NOW()
                                    WHERE order_id = ? AND user_id = ?";
                
                $update_stmt = $conn->prepare($update_order_sql);
                $update_stmt->bind_param("dddiii", 
                    $new_subtotal,
                    $new_gst_amount,
                    $new_delivery_charge,
                    $new_total_amount,
                    $existing_order_id,
                    $user_id
                );
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update order: " . $update_stmt->error);
                }
                $update_stmt->close();
                
                // Add new order items (only the new items, not existing ones)
                $item_sql = "INSERT INTO order_items (order_id, product_name, price, quantity) VALUES (?, ?, ?, ?)";
                $item_stmt = $conn->prepare($item_sql);
                foreach ($order_items as $item) {
                    $item_stmt->bind_param("isdi", $existing_order_id, $item['product_name'], $item['price'], $item['quantity']);
                    $item_stmt->execute();
                }
                $item_stmt->close();
                
                $order_id = $existing_order_id;
                $action = 'updated';
                
            } else {
                // CREATE NEW ORDER
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
                $item_stmt = $conn->prepare($item_sql);
                foreach ($order_items as $item) {
                    $item_stmt->bind_param("isdi", $order_id, $item['product_name'], $item['price'], $item['quantity']);
                    $item_stmt->execute();
                }
                $item_stmt->close();
                
                $action = 'created';
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'order_id' => $order_id, 
                'action' => $action,
                'send_whatsapp' => $send_whatsapp_on_bill,
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'order_type' => $order_type
            ]);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// ==================== CHECK TABLE STATUS ====================
// Function to check if a table has active orders (excluding the current order being edited)
function getActiveTableOrders($conn, $user_id, $exclude_order_id = null) {
    $table_orders = [];
    
    $sql = "SELECT table_number, order_id, total_amount, status FROM orders 
            WHERE user_id = ? AND table_number IS NOT NULL AND table_number != '' 
            AND status IN ('Confirmed', 'In Progress')";
    
    if ($exclude_order_id) {
        $sql .= " AND order_id != ?";
    }
    
    $sql .= " ORDER BY table_number";
    
    $stmt = $conn->prepare($sql);
    
    if ($exclude_order_id) {
        $stmt->bind_param("ii", $user_id, $exclude_order_id);
    } else {
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $table_orders[$row['table_number']] = $row;
    }
    
    $stmt->close();
    return $table_orders;
}

// Get active table orders (exclude the current order if editing)
$active_table_orders = getActiveTableOrders($conn, $user_id, $existing_order_id);

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
        .fullscreen-active{position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:9999;background:white;overflow:auto;margin:0;padding:0}
        .fullscreen-active .container-fluid{max-width:100%;padding:15px;height:calc(100vh - 60px);overflow-y:auto}
        .fullscreen-active .row{height:100%;margin:0}
        .fullscreen-active .col-md-3,.fullscreen-active .col-md-5,.fullscreen-active .col-md-4{height:100%;display:flex;flex-direction:column}
        .fullscreen-active .products-grid{max-height:calc(100vh - 400px);flex-grow:1}
        .fullscreen-active .cart-scroll{max-height:calc(50vh - 150px);flex-grow:1}
        .fullscreen-active .bill-summary-card{margin-top:auto}
        .bill-container{background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);margin-bottom:20px}
        .bill-header{background:linear-gradient(135deg,var(--primary-color),#ff7b54);color:white;padding:15px 20px;border-radius:10px 10px 0 0}
        .order-type-btn{padding:8px 15px;border:2px solid var(--primary-color);background:white;color:var(--primary-color);border-radius:5px;transition:all 0.3s;cursor:pointer;margin-right:5px;font-weight:500}
        .order-type-btn:hover{background:#fff5f0}.order-type-btn.active{background:var(--primary-color);color:white}
        .customer-info-card{background:var(--light-bg);border-radius:8px;padding:15px}
        .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;max-height:calc(100vh - 300px);overflow-y:auto;padding-right:5px}
        .product-card{background:white;border:1px solid var(--border-color);border-radius:8px;padding:10px;text-align:center;cursor:pointer;transition:all 0.3s;display:flex;flex-direction:column;justify-content:space-between;min-height:150px}
        .product-card:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,0.1);border-color:var(--primary-color)}


        .product-card {
            position: relative;
        }
        .product-card .text-muted {
            position: absolute;
          right: 0;
          top: 0px;
          background: red;
          color: #fff !important;
          z-index: 9;
          padding: 3px 6px;
          border-radius: 5px;
        }

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
        .table-number-box{display:flex;flex-wrap:wrap;gap:5px;max-height:165px;overflow-y:auto;padding:5px;}
        .table-box{width:45px;height:45px;display:flex;align-items:center;justify-content:center;background:white;border:2px solid var(--border-color);border-radius:5px;cursor:pointer;font-weight:bold;transition:all 0.3s}
        .table-box:hover{border-color:var(--primary-color);background:#fff5f0}
        .table-box.selected{background:var(--primary-color);color:white;border-color:var(--primary-color)}
        .table-box.occupied{background:#ffebee;color:#c62828;border-color:#c62828}
        .table-box.occupied:hover{background:#ffcdd2}
        .table-box.occupied.selected{background:#c62828;color:white;border-color:#c62828}
        .table-box.editing{background:#e3f2fd;color:#1565c0;border-color:#1565c0}
        .table-box.editing.selected{background:#1565c0;color:white;border-color:#1565c0}
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
        .modal-header.bg-warning{background-color:#ffc107!important;color:#212529;}
        .table-order-info{font-size:12px;color:#666;margin-top:5px;}
        .info-message{background:#e3f2fd;border-left:4px solid #2196f3;padding:10px;margin-bottom:15px;border-radius:4px;}
        .whatsapp-toggle-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #25d366;
        }
        .whatsapp-toggle-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            font-weight: 500;
        }
        .whatsapp-toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        .whatsapp-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .whatsapp-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .whatsapp-toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        .whatsapp-toggle-switch input:checked + .whatsapp-toggle-slider {
            background-color: #25d366;
        }
        .whatsapp-toggle-switch input:checked + .whatsapp-toggle-slider:before {
            transform: translateX(24px);
        }
        .whatsapp-toggle-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        @media (max-width:768px){.products-grid{grid-template-columns:repeat(auto-fill,minmax(120px,1fr))}.table-box{width:40px;height:40px}.table-number-box{max-height:120px}}
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                
                <!-- Success Message -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-success auto-hide-alert alert-dismissible fade show" role="alert" id="successAlert" data-auto-hide="5000">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Information Message for Editing -->
                <?php if ($existing_order_id): ?>
                <div class="info-message">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Updating Order #<?php echo $existing_order_id; ?></strong> - You are adding new items to this existing order. The cart below is empty - add only the NEW items you want to add to this order.
                </div>
                <?php endif; ?>
                
                <!-- Table Conflict Modal -->
                <div class="modal fade" id="tableConflictModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Table Occupied</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Table <span id="conflictTableNumber" class="fw-bold"></span> already has an active order.</p>
                                <p>What would you like to do?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" id="createNewOrderBtn" data-bs-dismiss="modal">
                                    <i class="fas fa-plus-circle me-2"></i> Create New Bill
                                </button>
                                <button type="button" class="btn btn-primary" id="updateExistingOrderBtn">
                                    <i class="fas fa-edit me-2"></i> Update Existing Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Billing Interface -->
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-body">
                                <!-- Header -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-receipt me-2"></i> 
                                        <?php echo $existing_order_id ? 'Add Items to Order #' . $existing_order_id : 'Create New Bill'; ?>
                                    </h5>
                                    <div class="d-flex">
                                        <?php
                                        $dining_active = 'active';
                                        $delivery_active = '';
                                        $takeaway_active = '';
                                        
                                        if ($existing_order_data) {
                                            $dining_active = ($existing_order_data['order_type'] == 'dining') ? 'active' : '';
                                            $delivery_active = ($existing_order_data['order_type'] == 'delivery') ? 'active' : '';
                                            $takeaway_active = ($existing_order_data['order_type'] == 'takeaway') ? 'active' : '';
                                        }
                                        ?>
                                        <button type="button" class="order-type-btn <?php echo $dining_active; ?>" data-type="dining">
                                            <i class="fas fa-utensils me-2"></i> Dining
                                        </button>
                                        <button type="button" class="order-type-btn <?php echo $delivery_active; ?>" data-type="delivery">
                                            <i class="fas fa-motorcycle me-2"></i> Delivery
                                        </button>
                                        <button type="button" class="order-type-btn <?php echo $takeaway_active; ?>" data-type="takeaway">
                                            <i class="fas fa-shopping-bag me-2"></i> Takeaway
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Left Column: Customer Details -->
                                    <div class="col-md-3 mb-2">
                                        <!-- WhatsApp Confirmation Toggle -->
                                        <div class="whatsapp-toggle-container">
                                            <label class="whatsapp-toggle-label">
                                                <span>
                                                    <i class="fab fa-whatsapp me-2" style="color: #25d366;"></i>
                                                    WhatsApp Confirmation
                                                </span>
                                                <div class="whatsapp-toggle-switch">
                                                    <input type="checkbox" id="send_whatsapp_toggle" <?php echo $send_whatsapp_on_bill ? 'checked' : ''; ?>>
                                                    <span class="whatsapp-toggle-slider"></span>
                                                </div>
                                            </label>
                                            <div class="whatsapp-toggle-info">
                                                <i class="fas fa-info-circle"></i>
                                                <span id="whatsappStatusText">
                                                    <?php echo $send_whatsapp_on_bill ? 'ON: Customer will receive WhatsApp confirmation' : 'OFF: No WhatsApp will be sent'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Customer Information -->
                                        <div class="customer-info-card mb-2">
                                            <h6 class="mb-2"><i class="fas fa-user me-2"></i> Customer Details</h6>
                                            
                                            <!-- Table Selection -->
                                            <div class="mb-2 dining-info" id="tableField">
                                                <label class="form-label required-field">Table Number</label>
                                                <div class="table-number-box" id="tableNumberBox">
                                                    <?php 
                                                    for($i = 1; $i <= $table_count; $i++): 
                                                        $isOccupied = isset($active_table_orders[$i]);
                                                        $isCurrentEditingTable = ($existing_order_data && $existing_order_data['table_number'] == $i);
                                                        
                                                        $tableClass = '';
                                                        if ($isCurrentEditingTable) {
                                                            $tableClass = 'editing selected';
                                                        } elseif ($isOccupied) {
                                                            $tableClass = 'occupied';
                                                        }
                                                        
                                                        $orderInfo = $isOccupied ? $active_table_orders[$i] : null;
                                                    ?>
                                                    <div class="table-box <?php echo $tableClass; ?>" 
                                                         data-table="<?php echo $i; ?>" 
                                                         data-order-id="<?php echo $isOccupied ? $orderInfo['order_id'] : ''; ?>"
                                                         title="<?php 
                                                            if ($isCurrentEditingTable) {
                                                                echo 'Currently Editing Order #' . $existing_order_id;
                                                            } elseif ($isOccupied) {
                                                                echo 'Order #' . $orderInfo['order_id'] . ' - ' . $orderInfo['status'];
                                                            } else {
                                                                echo 'Available';
                                                            }
                                                         ?>">
                                                        <?php echo $i; ?>
                                                    </div>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="error-message" id="tableNumberError">Please select a table number</div>
                                                <div id="tableOrderInfo" class="table-order-info" style="<?php echo $existing_order_data && $existing_order_data['table_number'] ? '' : 'display: none;'; ?>">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <span id="tableOrderText">
                                                        <?php if ($existing_order_data && $existing_order_data['table_number']): ?>
                                                        Currently editing order #<?php echo $existing_order_id; ?> for table <?php echo $existing_order_data['table_number']; ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Customer Name -->
                                            <div class="mb-2">
                                                <label class="form-label" id="customerNameLabel">Customer Name</label>
                                                <input type="text" class="form-control" id="customer_name" placeholder="Enter customer name" value="<?php echo $existing_order_data ? htmlspecialchars($existing_order_data['customer_name']) : ''; ?>">
                                                <div class="error-message" id="customerNameError"></div>
                                            </div>
                                            
                                            <!-- Phone Number -->
                                            <div class="mb-2">
                                                <label class="form-label" id="customerPhoneLabel">Phone Number</label>
                                                <input type="text" class="form-control" id="customer_phone" placeholder="Enter phone number" maxlength="10" value="<?php echo $existing_order_data ? htmlspecialchars($existing_order_data['customer_phone']) : ''; ?>">
                                                <div class="error-message" id="customerPhoneError"></div>
                                            </div>
                                            
                                            <!-- Delivery Address -->
                                            <div class="mb-2 delivery-info" id="deliveryAddressField">
                                                <label class="form-label required-field">Delivery Address</label>
                                                <textarea class="form-control" id="delivery_address" rows="2" placeholder="Enter delivery address"><?php echo $existing_order_data ? htmlspecialchars($existing_order_data['delivery_address']) : ''; ?></textarea>
                                                <div class="error-message" id="deliveryAddressError">Delivery address is required</div>
                                            </div>
                                            
                                            <!-- Special Instructions -->
                                            <div class="mb-2">
                                                <label class="form-label">Special Instructions</label>
                                                <textarea class="form-control" id="order_notes" rows="2" placeholder="Any special requests or notes"><?php echo $existing_order_data ? htmlspecialchars($existing_order_data['order_notes']) : ''; ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Middle Column: Menu Items -->
                                    <div class="col-md-5 mb-2">
                                        <!-- Search Bar -->
                                        <div class="mb-2">
                                            <input type="text" class="form-control" id="productSearch" placeholder="Search products by name, ID, or first letters (e.g., 'a b k r')...">
                                        </div>
                                        
                                        <!-- Products Grid -->
                                        <div>
                                            <?php if (!empty($products)): ?>
                                            <div class="products-grid" id="productsContainer">
                                                <?php foreach ($products as $product): ?>
                                                <div class="product-card" 
                                                     data-product-id="<?php echo $product['id']; ?>" 
                                                     data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                                     data-product-price="<?php echo $product['price']; ?>"
                                                     data-product-first-letters="<?php 
                                                        // Generate first letters of each word
                                                        $words = explode(' ', $product['product_name']);
                                                        $firstLetters = '';
                                                        foreach ($words as $word) {
                                                            if (!empty($word)) {
                                                                $firstLetters .= strtoupper($word[0]);
                                                            }
                                                        }
                                                        echo htmlspecialchars($firstLetters);
                                                     ?>">
                                                    <div class="product-image-container">
                                                        <?php if (!empty($product['image_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                             class="product-image" 
                                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                                             loading="lazy">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                                    <div class="product-price"><?php echo $currencySymbol; ?><?php echo $product['formatted_price']; ?></div>
                                                    <small class="text-muted"><?php echo $product['id']; ?></small>
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
                                            <h6 class="mb-2"><i class="fas fa-shopping-cart me-2"></i> New Items to Add</h6>
                                            <div class="cart-scroll" id="orderCart">
                                                <div class="empty-cart-message" id="emptyCart">
                                                    <i class="fas fa-shopping-cart fa-3x mb-2"></i>
                                                    <p>No items added to cart. Select items from the menu.</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bill Summary -->
                                        <div class="bill-summary-card">
                                            <h6 class="mb-2"><i class="fas fa-file-invoice-dollar me-2"></i> Additional Amount</h6>
                                            <div class="summary-row">
                                                <span>Additional Subtotal:</span>
                                                <span><?php echo $currencySymbol; ?><span id="subtotal">0</span></span>
                                            </div>
                                            <div class="summary-row">
                                                <span>Additional <?php echo $taxLabel; ?> (<?php echo $gst_rate; ?>%):</span>
                                                <span><?php echo $currencySymbol; ?><span id="gstAmount">0</span></span>
                                            </div>
                                            <div class="summary-row" id="deliveryChargeRow" style="display: none;">
                                                <span>Delivery Charge:</span>
                                                <span><?php echo $currencySymbol; ?><span id="deliveryChargeAmount">0</span></span>
                                            </div>
                                            <div class="summary-row total-row">
                                                <span>Additional Total:</span>
                                                <span><?php echo $currencySymbol; ?><span id="totalAmount">0</span></span>
                                            </div>
                                            
                                            <!-- Bill Form -->
                                            <form id="billForm" method="POST">
                                                <input type="hidden" name="create_bill" value="1">
                                                <input type="hidden" id="existing_order_id" name="existing_order_id" value="<?php echo $existing_order_id ? $existing_order_id : ''; ?>">
                                                <input type="hidden" id="order_type_input" name="order_type" value="<?php echo $existing_order_data ? $existing_order_data['order_type'] : 'dining'; ?>">
                                                <input type="hidden" id="customer_name_input" name="customer_name" value="<?php echo $existing_order_data ? htmlspecialchars($existing_order_data['customer_name']) : ''; ?>">
                                                <input type="hidden" id="customer_phone_input" name="customer_phone" value="<?php echo $existing_order_data ? htmlspecialchars($existing_order_data['customer_phone']) : ''; ?>">
                                                <input type="hidden" id="table_number_input" name="table_number" value="<?php echo $existing_order_data ? $existing_order_data['table_number'] : ''; ?>">
                                                <input type="hidden" id="delivery_address_input" name="delivery_address" value="<?php echo $existing_order_data ? htmlspecialchars($existing_order_data['delivery_address']) : ''; ?>">
                                                <input type="hidden" id="order_notes_input" name="order_notes" value="<?php echo $existing_order_data ? htmlspecialchars($existing_order_data['order_notes']) : ''; ?>">
                                                <div id="orderItemsInputs"></div>
                                                
                                                <!-- Action Buttons -->
                                                <div class="d-flex gap-2 mt-3">
                                                    <button type="button" class="btn btn-secondary flex-grow-1" id="clearCart">
                                                        <i class="fas fa-trash me-2"></i> Clear
                                                    </button>
                                                    <button type="submit" class="btn btn-primary flex-grow-1" id="saveBillBtn">
                                                        <i class="fas fa-save me-2"></i> 
                                                        <?php echo $existing_order_id ? 'Add Items to Order' : 'Save Bill'; ?>
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
        
        // Set global variables from PHP
        window.userCountry = '<?php echo $user_country; ?>';
        window.currencySymbol = '<?php echo $currencySymbol; ?>';
        window.taxLabel = '<?php echo $taxLabel; ?>';
        
        console.log('User country:', window.userCountry);
        console.log('Currency symbol:', window.currencySymbol);
        console.log('Tax label:', window.taxLabel);
        
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
        
        console.log('Delivery charge config:', {
            deliveryCharge: config.deliveryCharge,
            freeDeliveryMinimum: config.freeDeliveryMinimum
        });
        
        let cartItems = [];
        let isFullscreen = false;
        let lastCreatedOrderId = null;
        let currentTableOrderId = null;
        
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
        
        // WhatsApp toggle functionality
        $('#send_whatsapp_toggle').change(function() {
            const isChecked = $(this).is(':checked');
            const statusText = isChecked ? 'ON: Customer will receive WhatsApp confirmation' : 'OFF: No WhatsApp will be sent';
            $('#whatsappStatusText').text(statusText);
            
            // Update setting via AJAX
            $.ajax({
                url: 'update_whatsapp_setting.php',
                type: 'POST',
                data: {
                    send_whatsapp_on_bill: isChecked ? 1 : 0
                },
                success: function(response) {
                    console.log('WhatsApp setting updated:', response);
                },
                error: function() {
                    console.error('Failed to update WhatsApp setting');
                }
            });
        });
        
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
                            <strong>${escapeHtml(item.name)}</strong><br>
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
                // Check if we're updating an existing order
                const isUpdatingOrder = $('#existing_order_id').val() ? true : false;
                
                if (isUpdatingOrder) {
                    // For updating existing orders, delivery charge should already be included
                    // in the original order, so we don't add it to the new items
                    delivery = 0;
                    console.log('Updating existing order - delivery charge not added to new items');
                } else {
                    // For new delivery orders, calculate delivery charge
                    if (config.freeDeliveryMinimum > 0 && subtotal >= config.freeDeliveryMinimum) {
                        delivery = 0;
                        console.log('New order qualifies for free delivery');
                    } else {
                        delivery = config.deliveryCharge;
                        console.log('New order delivery charge:', delivery);
                    }
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
            
            console.log('Totals calculated:', { subtotal, gstAmount, delivery, totalAmount, orderType });
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
                const tableNumber = $('#table_number_input').val();
                if (!tableNumber) {
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
                    <input type="hidden" name="order_items[${index}][product_name]" value="${escapeHtml(item.name)}">
                    <input type="hidden" name="order_items[${index}][price]" value="${item.price}">
                    <input type="hidden" name="order_items[${index}][quantity]" value="${item.quantity}">
                `);
            });
        }
        
        // Check if table has existing order
        function checkTableOrder(tableNumber) {
            $.ajax({
                url: 'check_table_order.php',
                type: 'POST',
                data: {
                    table_number: tableNumber,
                    user_id: <?php echo $user_id; ?>,
                    exclude_order_id: $('#existing_order_id').val() || 0
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.exists) {
                            // Show conflict modal
                            $('#conflictTableNumber').text(tableNumber);
                            currentTableOrderId = data.order_id;
                            
                            // Update table order info display
                            $('#tableOrderInfo').show();
                            $('#tableOrderText').html('Active order #' + data.order_id + ' - ' + data.status + ' - Total: ' + config.currencySymbol + data.total_amount);
                            
                            $('#tableConflictModal').modal('show');
                        } else {
                            // No existing order, proceed normally
                            selectTable(tableNumber);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                },
                error: function() {
                    showToast('Error checking table status', 'danger');
                }
            });
        }
        
        function selectTable(tableNumber) {
            // Remove selected class from all tables
            $('.table-box').removeClass('selected');
            
            // Add selected class to the clicked table
            $(`.table-box[data-table="${tableNumber}"]`).addClass('selected');
            $('#table_number_input').val(tableNumber);
            $('#tableNumberError').hide();
            $('.table-box').removeClass('field-error');
            
            // Clear any existing order ID unless we're editing
            if (!$('#existing_order_id').val()) {
                $('#existing_order_id').val('');
            }
        }
        
        // Enhanced Search Functionality
        function performSearch(searchTerm) {
            let foundCount = 0;
            
            $('.product-card').each(function() {
                const productId = $(this).data('product-id').toString();
                const productName = $(this).data('product-name').toLowerCase();
                const firstLetters = $(this).data('product-first-letters').toLowerCase();
                
                // Remove spaces from search term for first letter matching
                const searchWithoutSpaces = searchTerm.replace(/\s+/g, '').toLowerCase();
                
                // Check multiple search criteria
                const matchesId = productId.includes(searchTerm);
                const matchesName = productName.includes(searchTerm.toLowerCase());
                const matchesFirstLetters = firstLetters.includes(searchWithoutSpaces);
                
                // Special case: search by first letters with spaces (e.g., "a b k r")
                let matchesFirstLettersWithSpaces = false;
                if (searchTerm.includes(' ')) {
                    // Extract first letters from product name
                    const productWords = productName.split(' ');
                    let productFirstLetters = '';
                    productWords.forEach(word => {
                        if (word.length > 0) {
                            productFirstLetters += word[0];
                        }
                    });
                    
                    // Get search letters without spaces
                    const searchLetters = searchTerm.toLowerCase().replace(/\s+/g, '');
                    
                    // Check if product first letters contain the search letters
                    matchesFirstLettersWithSpaces = productFirstLetters.includes(searchLetters);
                }
                
                // Also check if the search term matches the beginning of first letters
                const matchesFirstLettersStart = firstLetters.startsWith(searchWithoutSpaces);
                
                if (matchesId || matchesName || matchesFirstLetters || matchesFirstLettersWithSpaces || matchesFirstLettersStart) {
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
            const orderId = $(this).data('order-id');
            
            // Don't allow changing table if we're editing an existing order
            if ($('#existing_order_id').val()) {
                showToast('Cannot change table number when updating an existing order.', 'warning');
                return;
            }
            
            // If table is occupied and we're not already editing that order
            if ($(this).hasClass('occupied') && orderId != $('#existing_order_id').val()) {
                checkTableOrder(tableNumber);
            } else {
                selectTable(tableNumber);
                currentTableOrderId = null;
                
                // Clear table order info
                $('#tableOrderInfo').hide();
            }
        });
        
        $('#updateExistingOrderBtn').click(function() {
            if (currentTableOrderId) {
                // Redirect to edit mode with the existing order ID
                window.location.href = 'billing.php?edit_order=' + currentTableOrderId;
            }
            $('#tableConflictModal').modal('hide');
        });
        
        $('#createNewOrderBtn').click(function() {
            const tableNumber = $('#conflictTableNumber').text();
            selectTable(tableNumber);
            $('#tableConflictModal').modal('hide');
        });
        
        // Enhanced Search with Debounce
        let searchTimeout;
        $('#productSearch').on('input', function() {
            const searchTerm = $(this).val().trim();
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Debounce search to improve performance
            searchTimeout = setTimeout(() => {
                performSearch(searchTerm);
            }, 300);
        });
        
        // Clear search on Escape key
        $('#productSearch').on('keydown', function(e) {
            if (e.key === 'Escape') {
                $(this).val('');
                performSearch('');
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
            const isUpdating = $('#existing_order_id').val() ? true : false;
            saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> ' + (isUpdating ? 'Adding Items...' : 'Saving...'));
            
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
                            const actionText = data.action === 'updated' ? 'updated' : 'created';
                            showToast(`Bill ${actionText} successfully! Order #${data.order_id}`, 'success');
                            
                            // Check if WhatsApp confirmation should be sent
                            const sendWhatsApp = $('#send_whatsapp_toggle').is(':checked');
                            const customerPhone = $('#customer_phone').val().trim();
                            const customerName = $('#customer_name').val().trim();
                            const orderType = $('#order_type_input').val();
                            
                            console.log('WhatsApp settings:', {
                                sendWhatsApp,
                                customerPhone,
                                customerName,
                                orderType,
                                hasPhone: !!customerPhone
                            });
                            
                            // Send WhatsApp confirmation if enabled and customer has phone
                            if (sendWhatsApp && customerPhone && customerPhone.length >= 10) {
                                console.log('Attempting to send WhatsApp confirmation...');
                                
                                // Fetch business data first
                                fetchBusinessData().then(businessData => {
                                    console.log('Business data fetched:', businessData);
                                    
                                    // Send WhatsApp confirmation
                                    const whatsappSent = sendOrderConfirmation(
                                        data.order_id,
                                        customerPhone,
                                        customerName || 'Customer',
                                        orderType,
                                        businessData.businessInfo,
                                        businessData.userPhone,
                                        businessData.profileUrl
                                    );
                                    
                                    if (whatsappSent) {
                                        showToast('WhatsApp confirmation sent to customer!', 'success');
                                    }
                                }).catch(error => {
                                    console.error('Error sending WhatsApp:', error);
                                });
                            } else if (sendWhatsApp && (!customerPhone || customerPhone.length < 10)) {
                                console.log('WhatsApp not sent: No valid phone number provided');
                            } else if (!sendWhatsApp) {
                                console.log('WhatsApp not sent: Feature is disabled');
                            }
                            
                            // Reset form
                            cartItems = [];
                            updateCart();
                            
                            // For editing, don't reset customer details
                            if (data.action === 'created') {
                                $('#customer_name').val('');
                                $('#customer_phone').val('');
                                $('#delivery_address').val('');
                                $('#order_notes').val('');
                                $('.table-box').removeClass('selected');
                                $('#table_number_input').val('');
                                $('#existing_order_id').val('');
                                $('#tableOrderInfo').hide();
                                $('#saveBillBtn').html('<i class="fas fa-save me-2"></i> Save Bill');
                            } else {
                                // For updates, just clear the cart
                                cartItems = [];
                                updateCart();
                            }
                            
                            // Redirect to orders.php page after delay
                            setTimeout(() => {
                                window.location.href = 'orders.php?success=1&order_id=' + data.order_id + '&action=' + data.action;
                            }, 2000);
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
        
        // Set initial order type based on existing order or default
        const initialOrderType = $('#order_type_input').val();
        updateFieldVisibility(initialOrderType);
        
        // When editing an existing order, disable table selection
        if ($('#existing_order_id').val()) {
            $('.table-box').css('cursor', 'not-allowed').off('click');
            showToast('You are adding items to an existing order. Table number cannot be changed.', 'info');
            
            // Update header text to show we're adding items
            $('#saveBillBtn').html('<i class="fas fa-plus-circle me-2"></i> Add Items to Order');
            
            // Update cart title
            $('h6:contains("Order Items")').html('<i class="fas fa-plus-circle me-2"></i> New Items to Add');
        }
        
        console.log('Billing page initialized successfully');
    });
    
    // Functions from menu.php that are needed
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Function to adjust time for UAE users
    function adjustTimeForCountry(dateTimeString) {
        if (!dateTimeString) return new Date();
        
        const date = new Date(dateTimeString);
        
        if (window.userCountry === 'UAE') {
            // Subtract 1 hour 30 minutes for UAE
            date.setMinutes(date.getMinutes() - 90);
        }
        
        return date;
    }
    
    // Function to format date for display with country adjustment
    function formatDateTimeForCountry(dateTimeString) {
        const date = adjustTimeForCountry(dateTimeString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }
    
    // Function to fetch business data
    async function fetchBusinessData() {
        try {
            const response = await fetch('get_business_data.php');
            const data = await response.json();
            
            if (data.success) {
                return {
                    businessInfo: data.business_info,
                    userPhone: data.user_phone,
                    profileUrl: data.profile_url
                };
            } else {
                throw new Error('Failed to fetch business data');
            }
        } catch (error) {
            console.error('Error fetching business data:', error);
            return {
                businessInfo: { business_name: 'Our Restaurant' },
                userPhone: '',
                profileUrl: ''
            };
        }
    }
    
    // WhatsApp confirmation function (from menu.php)
    function sendOrderConfirmation(orderId, customerPhone, customerName, orderType, businessInfo, businessPhone, profileUrl) {
        try {
            // Validate inputs
            if (!customerPhone || customerPhone.length < 9) {
                console.warn(`Invalid phone number for order ${orderId}: ${customerPhone}`);
                return false;
            }

            // Business details
            const businessName = businessInfo?.business_name || 'Our Restaurant';
            const businessAddress = businessInfo?.business_address || '';
            const phone = businessPhone || '';

            // Format customer phone based on country
            let formattedCustomerPhone = customerPhone.replace(/\D/g, '');
            
            if (window.userCountry === 'UAE') {
                // For UAE: 9 digits, add 971
                if (formattedCustomerPhone.length === 9) {
                    formattedCustomerPhone = '971' + formattedCustomerPhone;
                }
            } else {
                // For other countries: 10 digits, add 91 (India default)
                if (formattedCustomerPhone.length === 10) {
                    formattedCustomerPhone = '91' + formattedCustomerPhone;
                }
            }

            // URLs
            const orderStatusUrl = profileUrl 
                ? `https://deegeecard.com/order_status.php?order_id=${orderId}&profile_url=${encodeURIComponent(profileUrl)}`
                : `https://deegeecard.com/order_status.php?order_id=${orderId}`;
                
            const profileOrderUrl = profileUrl 
                ? `https://deegeecard.com/${profileUrl}`
                : 'https://deegeecard.com';

            // Create confirmation message exactly as per sample
            let message = `🚀 *Next time, order faster!*\n`;
            message += `Place your order easily here:\n`;
            message += `🔗 ${profileOrderUrl}\n\n`;
            
            message += `🍽 *${businessName.toUpperCase()}*\n`;
            message += `✅ Order Confirmed #${orderId}\n\n`;
            
            message += `👋 Dear ${customerName},\n`;
            message += `Your order has been confirmed and is now being processed!\n\n`;
            
            message += `📋 *Order Details:*\n`;
            message += `•⁠  ⁠Order Type: ${orderType === 'delivery' ? '🚚 Delivery' : orderType === 'dining' ? '🍽️ Dining' : orderType}\n`;
            message += `•⁠  ⁠Order ID: #${orderId}\n\n`;
            
            message += `🔎 *Track Your Order:*\n`;
            message += `${orderStatusUrl}\n\n`;

            message += `⚠ *To activate the tracking link above, please reply with 'OK' or save our number.*\n\n`;
            
            message += `❤️ *Thank you for choosing ${businessName}!*\n`;
            message += `We truly appreciate your business.`;

            // Create WhatsApp URL
            const whatsappUrl = `https://wa.me/${formattedCustomerPhone}?text=${encodeURIComponent(message)}`;
            
            // Open WhatsApp in new tab
            const newWindow = window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
            
            console.log('WhatsApp confirmation sent to:', customerPhone);
            return true;
            
        } catch (error) {
            console.error('Error sending WhatsApp confirmation:', error);
            return false;
        }
    }
    </script>
</body>
</html>
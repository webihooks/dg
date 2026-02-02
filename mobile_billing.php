<?php
// mobile_billing.php - Restaurant Billing System
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
            
            echo json_encode(['success' => true, 'order_id' => $order_id, 'action' => $action]);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
        :root{--primary-color:#fb5b29;--secondary-color:#28a745;--light-bg:#f8f9fa;--border-color:#e0e0e0;--safe-area-top:env(safe-area-inset-top);--safe-area-bottom:env(safe-area-inset-bottom)}.wrapper{padding-top:var(--safe-area-top);padding-bottom:var(--safe-area-bottom)}.page-content{padding:0;min-height:calc(100vh - var(--safe-area-top) - var(--safe-area-bottom))}.container-fluid{padding:0;height:100%}.mobile-app-layout{display:flex;flex-direction:column;height:100vh;overflow:hidden;background:white}.main-content-area{flex:1;overflow:hidden;display:flex;flex-direction:column}.top-action-bar{display:flex;justify-content:space-between;align-items:center;padding:12px 15px;background:white;border-bottom:1px solid var(--border-color);position:sticky;top:0;z-index:100;flex-shrink:0}.page-title{font-size:18px;font-weight:600;margin:0;color:#333}.action-buttons{display:flex;gap:10px}.action-btn{width:40px;height:40px;border-radius:10px;border:1px solid var(--border-color);background:white;display:flex;align-items:center;justify-content:center;color:var(--primary-color);font-size:16px;transition:all .2s}.action-btn:active{background:#f0f0f0;transform:scale(.95)}.tab-navigation{display:flex;background:white;border-bottom:1px solid var(--border-color);padding:0 15px;flex-shrink:0}.tab-btn{flex:1;padding:12px 0;text-align:center;background:none;border:none;border-bottom:3px solid transparent;font-size:14px;font-weight:500;color:#666;transition:all .2s}.tab-btn.active{color:var(--primary-color);border-bottom-color:var(--primary-color)}.scrollable-content{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:15px;padding-bottom:180px}.mobile-section{margin-bottom:20px}.section-title{font-size:14px;font-weight:600;color:#555;margin-bottom:12px;display:flex;align-items:center;gap:8px}.section-title i{color:var(--primary-color)}.customer-info-mobile{background:white;border-radius:12px;padding:15px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:15px}.customer-field{margin-bottom:12px}.customer-field:last-child{margin-bottom:0}.customer-field label{font-size:12px;color:#666;margin-bottom:5px;display:block}.form-input-mobile{width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:14px;transition:border-color .2s}.form-input-mobile:focus{border-color:var(--primary-color);outline:none}.tables-grid-mobile{display:grid;grid-template-columns:repeat(auto-fill,minmax(70px,1fr));gap:8px;max-height:200px;overflow-y:auto;padding:5px}.table-box-mobile{aspect-ratio:1;display:flex;align-items:center;justify-content:center;background:white;border:2px solid var(--border-color);border-radius:10px;font-weight:600;font-size:16px;transition:all .2s;cursor:pointer}.table-box-mobile:active{transform:scale(.95)}.table-box-mobile.selected{background:var(--primary-color);color:white;border-color:var(--primary-color)}.table-box-mobile.occupied{background:#ffebee;color:#c62828;border-color:#c62828}.table-box-mobile.editing{background:#e3f2fd;color:#1565c0;border-color:#1565c0}.products-grid-mobile{display:grid;grid-template-columns:repeat(auto-fill,minmax(45vw,1fr));gap:10px}@media (min-width:768px){.products-grid-mobile{grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}}.product-card-mobile{background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 5px rgba(0,0,0,.08);transition:all .2s;cursor:pointer;border:1px solid var(--border-color)}.product-card-mobile:active{transform:scale(.98);box-shadow:0 1px 3px rgba(0,0,0,.1)}.product-image-mobile{width:100%;height:100px;object-fit:cover;background:var(--light-bg)}.product-info-mobile{padding:10px}.product-name-mobile{font-size:13px;font-weight:500;margin-bottom:5px;line-height:1.3;height:34px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.product-price-mobile{font-size:14px;font-weight:600;color:var(--secondary-color)}.search-container-mobile{position:sticky;top:0;z-index:50;background:white;padding:10px 0;margin-bottom:15px}.search-input-mobile{width:100%;padding:10px 40px 10px 15px;border:1px solid var(--border-color);border-radius:25px;font-size:14px;background:var(--light-bg)}.search-input-mobile:focus{border-color:var(--primary-color);outline:none}.bottom-cart-mobile{position:fixed;bottom:0;left:0;right:0;background:white;border-top:1px solid var(--border-color);box-shadow:0 -2px 10px rgba(0,0,0,.1);z-index:1000;padding:15px;padding-bottom:max(15px,var(--safe-area-bottom));transform:translateY(0);transition:transform .3s ease}.bottom-cart-mobile.collapsed{transform:translateY(100%)}.cart-header-mobile{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;cursor:pointer}.cart-title-mobile{font-size:16px;font-weight:600;color:#333;display:flex;align-items:center;gap:8px}.cart-items-count{background:var(--primary-color);color:white;width:24px;height:24px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600}.cart-toggle-btn{background:none;border:none;color:#666;font-size:20px;padding:5px}.cart-items-mobile{max-height:200px;overflow-y:auto;margin-bottom:15px}.cart-item-mobile{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-color)}.cart-item-mobile:last-child{border-bottom:none}.cart-item-info{flex:1}.cart-item-name{font-size:14px;font-weight:500;margin-bottom:3px}.cart-item-price{font-size:12px;color:#666}.cart-item-controls-mobile{display:flex;align-items:center;gap:10px}.quantity-btn-mobile{width:30px;height:30px;border-radius:15px;border:1px solid var(--primary-color);background:white;color:var(--primary-color);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:bold}.quantity-btn-mobile:active{background:var(--primary-color);color:white}.quantity-display-mobile{width:40px;text-align:center;font-weight:500}.cart-summary-mobile{background:var(--light-bg);border-radius:10px;padding:15px;margin-bottom:15px}.summary-row-mobile{display:flex;justify-content:space-between;padding:5px 0;font-size:14px}.summary-total-mobile{border-top:2px solid var(--primary-color);margin-top:10px;padding-top:10px;font-weight:600;font-size:16px}.cart-actions-mobile{display:flex;gap:10px}.cart-action-btn{flex:1;padding:12px;border-radius:10px;border:none;font-weight:600;font-size:14px;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s}.cart-action-btn:active{transform:scale(.98)}.btn-clear{background:#f8f9fa;color:#666;border:1px solid var(--border-color)}.btn-save{background:var(--primary-color);color:white}.empty-state{text-align:center;padding:40px 20px;color:#999}.empty-state i{font-size:48px;margin-bottom:15px;opacity:.5}.modal-dialog{margin:10px}.toast-mobile{position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;min-width:80%;max-width:400px;opacity:0;transition:opacity .3s}.toast-mobile.show{opacity:1}@media (min-width:992px){.mobile-app-layout{max-width:1200px;margin:0 auto;border-left:1px solid var(--border-color);border-right:1px solid var(--border-color)}.scrollable-content{padding-bottom:20px}.bottom-cart-mobile{position:relative;border-top:none;box-shadow:none;transform:none!important;padding-bottom:15px}.products-grid-mobile{grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}}.field-error{border-color:#dc3545!important}.error-message{color:#dc3545;font-size:11px;margin-top:3px;display:none}@media (max-width:767px){.desktop-only{display:none!important}.container-fluid{padding-left:0;padding-right:0}.card-body{padding:0}.wrapper{background:white}.page-content{background:white}}.mobile-only{display:block}@media (min-width:768px){.mobile-only{display:none!important}}.info-message-mobile{background:#e3f2fd;border-left:4px solid #2196f3;padding:12px;margin-bottom:15px;border-radius:8px;font-size:13px}.alert-success-mobile{position:sticky;top:0;z-index:1000;margin:0;border-radius:0;border:none;text-align:center}.table-order-info-mobile{font-size:11px;color:#666;margin-top:5px;padding:5px 10px;background:var(--light-bg);border-radius:6px}
    </style>
</head>

<body>

    <!-- Mobile App Layout -->
    <div class="mobile-app-layout">
        
        <!-- Top Action Bar -->
        <div class="top-action-bar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="action-btn" onclick="history.back()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h1 class="page-title">
                    <?php echo $existing_order_id ? 'Add Items #' . $existing_order_id : 'New Bill'; ?>
                </h1>
            </div>
            <div class="action-buttons">
                <button type="button" class="action-btn" id="mobileSearchToggle">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        
        <!-- Success Message -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-success-mobile alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Information Message for Editing -->
        <?php if ($existing_order_id): ?>
        <div class="info-message-mobile">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Adding to Order #<?php echo $existing_order_id; ?></strong> - Only add new items here.
        </div>
        <?php endif; ?>
        
        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button type="button" class="tab-btn <?php echo ($existing_order_data ? $existing_order_data['order_type'] : 'dining') == 'dining' ? 'active' : ''; ?>" data-type="dining">
                <i class="fas fa-utensils me-1"></i> Dining
            </button>
            <button type="button" class="tab-btn <?php echo ($existing_order_data ? $existing_order_data['order_type'] : '') == 'delivery' ? 'active' : ''; ?>" data-type="delivery">
                <i class="fas fa-motorcycle me-1"></i> Delivery
            </button>
            <button type="button" class="tab-btn <?php echo ($existing_order_data ? $existing_order_data['order_type'] : '') == 'takeaway' ? 'active' : ''; ?>" data-type="takeaway">
                <i class="fas fa-shopping-bag me-1"></i> Takeaway
            </button>
        </div>
        
        <!-- Search Bar -->
        <div class="search-container-mobile" id="mobileSearchBar" style="display: none;">
            <input type="text" class="search-input-mobile" id="mobileProductSearch" placeholder="Search products...">
        </div>
        
        <!-- Scrollable Content -->
        <div class="scrollable-content" id="mainScrollContent">
            
            <!-- Customer Information Section -->
            <div class="mobile-section">
                <div class="section-title">
                    <i class="fas fa-user"></i>
                    <span>Customer Details</span>
                </div>
                
                <div class="customer-info-mobile">
                    
                    <!-- Table Selection (Dining) -->
                    <div class="customer-field dining-info" id="tableFieldMobile">
                        <label>Table Number</label>
                        <div class="tables-grid-mobile" id="tableNumberBoxMobile">
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
                            <div class="table-box-mobile <?php echo $tableClass; ?>" 
                                 data-table="<?php echo $i; ?>" 
                                 data-order-id="<?php echo $isOccupied ? $orderInfo['order_id'] : ''; ?>"
                                 title="<?php 
                                    if ($isCurrentEditingTable) {
                                        echo 'Editing Order #' . $existing_order_id;
                                    } elseif ($isOccupied) {
                                        echo 'Order #' . $orderInfo['order_id'];
                                    } else {
                                        echo 'Table ' . $i;
                                    }
                                 ?>">
                                <?php echo $i; ?>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <div class="error-message" id="tableNumberErrorMobile">Please select a table</div>
                        <?php if ($existing_order_data && $existing_order_data['table_number']): ?>
                        <div class="table-order-info-mobile">
                            <i class="fas fa-info-circle me-1"></i>
                            Editing order #<?php echo $existing_order_id; ?> for table <?php echo $existing_order_data['table_number']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Customer Name -->
                    <div class="customer-field">
                        <label id="customerNameLabelMobile">Customer Name</label>
                        <input type="text" class="form-input-mobile" id="customer_name_mobile" 
                               placeholder="Enter name" 
                               value="<?php echo $existing_order_data ? htmlspecialchars($existing_order_data['customer_name']) : ''; ?>">
                        <div class="error-message" id="customerNameErrorMobile"></div>
                    </div>
                    
                    <!-- Phone Number -->
                    <div class="customer-field">
                        <label id="customerPhoneLabelMobile">Phone Number</label>
                        <input type="tel" class="form-input-mobile" id="customer_phone_mobile" 
                               placeholder="10-digit number" 
                               maxlength="10"
                               value="<?php echo $existing_order_data ? htmlspecialchars($existing_order_data['customer_phone']) : ''; ?>">
                        <div class="error-message" id="customerPhoneErrorMobile"></div>
                    </div>
                    
                    <!-- Delivery Address -->
                    <div class="customer-field delivery-info" id="deliveryAddressFieldMobile" style="display: none;">
                        <label>Delivery Address</label>
                        <textarea class="form-input-mobile" id="delivery_address_mobile" 
                                  rows="2" 
                                  placeholder="Enter delivery address"><?php echo $existing_order_data ? htmlspecialchars($existing_order_data['delivery_address']) : ''; ?></textarea>
                        <div class="error-message" id="deliveryAddressErrorMobile">Address required</div>
                    </div>
                    
                    <!-- Special Instructions -->
                    <div class="customer-field">
                        <label>Special Instructions</label>
                        <textarea class="form-input-mobile" id="order_notes_mobile" 
                                  rows="2" 
                                  placeholder="Any special requests"><?php echo $existing_order_data ? htmlspecialchars($existing_order_data['order_notes']) : ''; ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Products Section -->
            <div class="mobile-section">
                <div class="section-title">
                    <i class="fas fa-utensils"></i>
                    <span>Menu Items</span>
                </div>
                
                <?php if (!empty($products)): ?>
                <div class="products-grid-mobile" id="productsContainerMobile">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card-mobile" 
                         data-product-id="<?php echo $product['id']; ?>" 
                         data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>" 
                         data-product-price="<?php echo $product['price']; ?>">
                        <?php if (!empty($product['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             class="product-image-mobile" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        <?php else: ?>
                        <div class="product-image-mobile" style="display: flex; align-items: center; justify-content: center; color: #999;">
                            <i class="fas fa-utensils fa-2x"></i>
                        </div>
                        <?php endif; ?>
                        <div class="product-info-mobile">
                            <div class="product-name-mobile"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-price-mobile"><?php echo $currencySymbol; ?><?php echo $product['formatted_price']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="noProductsFoundMobile" class="empty-state" style="display: none;">
                    <i class="fas fa-search"></i>
                    <p>No products found</p>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <p>No products available</p>
                </div>
                <?php endif; ?>
            </div>
            
        </div> <!-- End Scrollable Content -->
        
        <!-- Bottom Cart (Mobile) -->
        <div class="bottom-cart-mobile" id="bottomCartMobile">
            <div class="cart-header-mobile" onclick="toggleCart()">
                <div class="cart-title-mobile">
                    <span>Cart</span>
                    <span class="cart-items-count" id="cartCountMobile">0</span>
                </div>
                <button type="button" class="cart-toggle-btn" onclick="toggleCart(); event.stopPropagation();">
                    <i class="fas fa-chevron-up" id="cartToggleIcon"></i>
                </button>
            </div>
            
            <div class="cart-items-mobile" id="cartItemsMobile">
                <div class="empty-state" id="emptyCartMobile" style="display:none;">
                    <i class="fas fa-shopping-cart"></i>
                    <p>No items added</p>
                </div>
            </div>
            
            <div class="cart-summary-mobile" id="cartSummaryMobile" style="display: none;">
                <div class="summary-row-mobile">
                    <span>Additional Subtotal:</span>
                    <span><?php echo $currencySymbol; ?><span id="subtotalMobile">0</span></span>
                </div>
                <div class="summary-row-mobile">
                    <span><?php echo $taxLabel; ?> (<?php echo $gst_rate; ?>%):</span>
                    <span><?php echo $currencySymbol; ?><span id="gstAmountMobile">0</span></span>
                </div>
                <div class="summary-row-mobile" id="deliveryChargeRowMobile" style="display: none;">
                    <span>Delivery Charge:</span>
                    <span><?php echo $currencySymbol; ?><span id="deliveryChargeAmountMobile">0</span></span>
                </div>
                <div class="summary-row-mobile summary-total-mobile">
                    <span>Additional Total:</span>
                    <span><?php echo $currencySymbol; ?><span id="totalAmountMobile">0</span></span>
                </div>
            </div>
            
            <div class="cart-actions-mobile">
                <button type="button" class="cart-action-btn btn-clear" id="clearCartMobile">
                    <i class="fas fa-trash"></i> Clear
                </button>
                <button type="button" class="cart-action-btn btn-save" id="saveBillMobile">
                    <i class="fas fa-save"></i> 
                    <?php echo $existing_order_id ? 'Add Items' : 'Save Bill'; ?>
                </button>
            </div>
        </div>
        
    </div> <!-- End Mobile App Layout -->

    <!-- Table Conflict Modal -->
    <div class="modal fade" id="tableConflictModalMobile" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Table Occupied</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Table <span id="conflictTableNumberMobile" class="fw-bold"></span> has an active order.</p>
                    <p>What would you like to do?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="createNewOrderBtnMobile" data-bs-dismiss="modal">
                        New Bill
                    </button>
                    <button type="button" class="btn btn-primary" id="updateExistingOrderBtnMobile">
                        Update Order
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        console.log('Mobile billing page loaded');
        
        // Configuration
        const config = {
            gstRate: <?php echo $gst_rate; ?>,
            deliveryCharge: <?php echo $delivery_charge; ?>,
            freeDeliveryMinimum: <?php echo $free_delivery_minimum; ?>,
            currencySymbol: '<?php echo $currencySymbol; ?>',
            taxLabel: '<?php echo $taxLabel; ?>'
        };
        
        let cartItems = [];
        let currentOrderType = '<?php echo $existing_order_data ? $existing_order_data['order_type'] : 'dining'; ?>';
        let currentTableOrderId = null;
        let isCartExpanded = true;
        
        // Initialize
        initializeMobile();
        
        function initializeMobile() {
            // Set initial order type
            updateFieldVisibilityMobile(currentOrderType);
            
            // Initialize product clicks
            $(document).on('click', '.product-card-mobile', function(e) {
                e.preventDefault();
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const productPrice = parseFloat($(this).data('product-price'));
                
                addToCart(productId, productName, productPrice);
                
                // Add visual feedback
                $(this).css('transform', 'scale(0.95)');
                setTimeout(() => {
                    $(this).css('transform', '');
                }, 200);
            });
            
            // Tab navigation
            $('.tab-btn').on('click', function() {
                const orderType = $(this).data('type');
                currentOrderType = orderType;
                
                // Update tabs
                $('.tab-btn').removeClass('active');
                $(this).addClass('active');
                
                // Update field visibility
                updateFieldVisibilityMobile(orderType);
                
                // Calculate totals
                if (cartItems.length > 0) {
                    calculateTotalsMobile();
                }
            });
            
            // Table selection
            $('.table-box-mobile').on('click', function() {
                if ($('#existing_order_id_mobile').val()) {
                    showToast('Cannot change table when updating order', 'warning');
                    return;
                }
                
                const tableNumber = $(this).data('table');
                const orderId = $(this).data('order-id');
                
                if ($(this).hasClass('occupied') && orderId) {
                    // Show conflict modal
                    $('#conflictTableNumberMobile').text(tableNumber);
                    currentTableOrderId = orderId;
                    $('#tableConflictModalMobile').modal('show');
                } else {
                    selectTableMobile(tableNumber);
                }
            });
            
            // Search functionality
            $('#mobileSearchToggle').on('click', function() {
                $('#mobileSearchBar').slideToggle();
                $('#mobileProductSearch').focus();
            });
            
            $('#mobileProductSearch').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                let foundCount = 0;
                
                $('.product-card-mobile').each(function() {
                    const productName = $(this).data('product-name').toLowerCase();
                    if (productName.includes(searchTerm)) {
                        $(this).show();
                        foundCount++;
                    } else {
                        $(this).hide();
                    }
                });
                
                if (foundCount === 0 && searchTerm.length > 0) {
                    $('#noProductsFoundMobile').show();
                } else {
                    $('#noProductsFoundMobile').hide();
                }
            });
            
            // Clear cart
            $('#clearCartMobile').on('click', function() {
                if (cartItems.length === 0) {
                    showToast('Cart is empty', 'info');
                    return;
                }
                
                if (confirm('Clear all items from cart?')) {
                    cartItems = [];
                    updateCartMobile();
                    showToast('Cart cleared', 'warning');
                }
            });
            
            // Save bill
            $('#saveBillMobile').on('click', function() {
                saveBillMobile();
            });
            
            // Modal buttons
            $('#updateExistingOrderBtnMobile').on('click', function() {
                if (currentTableOrderId) {
                    window.location.href = 'mobile_billing.php?edit_order=' + currentTableOrderId;
                }
                $('#tableConflictModalMobile').modal('hide');
            });
            
            $('#createNewOrderBtnMobile').on('click', function() {
                const tableNumber = $('#conflictTableNumberMobile').text();
                selectTableMobile(tableNumber);
                $('#tableConflictModalMobile').modal('hide');
            });
            
            // Auto-hide success alert
            setTimeout(() => {
                $('.alert-success-mobile').alert('close');
            }, 3000);
            
            console.log('Mobile billing initialized');
        }
        
        function updateFieldVisibilityMobile(orderType) {
            // Update form labels
            if (orderType === 'dining') {
                $('.dining-info').show();
                $('#deliveryAddressFieldMobile').hide();
                $('#deliveryChargeRowMobile').hide();
                
                $('#customerNameLabelMobile').html('Customer Name <small class="text-muted">(optional)</small>');
                $('#customerPhoneLabelMobile').html('Phone Number <small class="text-muted">(optional)</small>');
                
            } else if (orderType === 'delivery') {
                $('.dining-info').hide();
                $('#deliveryAddressFieldMobile').show();
                $('#deliveryChargeRowMobile').show();
                
                $('#customerNameLabelMobile').html('Customer Name <span class="text-danger">*</span>');
                $('#customerPhoneLabelMobile').html('Phone Number <span class="text-danger">*</span>');
                
            } else if (orderType === 'takeaway') {
                $('.dining-info').hide();
                $('#deliveryAddressFieldMobile').hide();
                $('#deliveryChargeRowMobile').hide();
                
                $('#customerNameLabelMobile').html('Customer Name <span class="text-danger">*</span>');
                $('#customerPhoneLabelMobile').html('Phone Number <span class="text-danger">*</span>');
            }
        }
        
        function addToCart(productId, productName, productPrice) {
            const existingItem = cartItems.find(item => item.id === productId);
            
            if (existingItem) {
                existingItem.quantity++;
                showToast(`${productName} (${existingItem.quantity})`, 'success');
            } else {
                cartItems.push({ 
                    id: productId, 
                    name: productName, 
                    price: productPrice, 
                    quantity: 1 
                });
                showToast(`${productName} added`, 'success');
            }
            
            updateCartMobile();
            
            // Ensure cart is expanded when adding items
            if (!isCartExpanded) {
                toggleCart();
            }
        }
        
        function updateCartMobile() {
            const cartCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);
            $('#cartCountMobile').text(cartCount);
            
            if (cartItems.length === 0) {
                $('#emptyCartMobile').show();
                $('#cartItemsMobile').html('');
                $('#cartSummaryMobile').hide();
                return;
            }
            
            $('#emptyCartMobile').hide();
            $('#cartSummaryMobile').show();
            
            let cartHtml = '';
            cartItems.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                
                cartHtml += `
                    <div class="cart-item-mobile">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${escapeHtml(item.name)}</div>
                            <div class="cart-item-price">${config.currencySymbol}${formatPrice(item.price)} each</div>
                        </div>
                        <div class="cart-item-controls-mobile">
                            <button type="button" class="quantity-btn-mobile" onclick="updateQuantityMobile(${index}, -1)">-</button>
                            <span class="quantity-display-mobile">${item.quantity}</span>
                            <button type="button" class="quantity-btn-mobile" onclick="updateQuantityMobile(${index}, 1)">+</button>
                        </div>
                    </div>
                `;
            });
            
            $('#cartItemsMobile').html(cartHtml);
            calculateTotalsMobile();
        }
        
        window.updateQuantityMobile = function(index, change) {
            if (cartItems[index]) {
                cartItems[index].quantity += change;
                if (cartItems[index].quantity < 1) {
                    cartItems.splice(index, 1);
                    showToast('Item removed', 'warning');
                }
                updateCartMobile();
            }
        };
        
        function calculateTotalsMobile() {
            let subtotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const gstAmount = subtotal * (config.gstRate / 100);
            
            let delivery = 0;
            if (currentOrderType === 'delivery') {
                if (config.freeDeliveryMinimum > 0 && subtotal >= config.freeDeliveryMinimum) {
                    delivery = 0;
                } else {
                    delivery = config.deliveryCharge;
                }
                $('#deliveryChargeRowMobile').show();
                $('#deliveryChargeAmountMobile').text(formatPrice(delivery));
            } else {
                $('#deliveryChargeRowMobile').hide();
            }
            
            const totalAmount = subtotal + gstAmount + delivery;
            
            $('#subtotalMobile').text(formatPrice(subtotal));
            $('#gstAmountMobile').text(formatPrice(gstAmount));
            $('#totalAmountMobile').text(formatPrice(totalAmount));
        }
        
        function selectTableMobile(tableNumber) {
            $('.table-box-mobile').removeClass('selected');
            $(`.table-box-mobile[data-table="${tableNumber}"]`).addClass('selected');
        }
        
        function validateFormMobile() {
            let isValid = true;
            
            // Clear previous errors
            $('.error-message').hide();
            $('.form-input-mobile').removeClass('field-error');
            
            // Check cart
            if (cartItems.length === 0) {
                showToast('Add items to cart first', 'warning');
                return false;
            }
            
            // Validate based on order type
            if (currentOrderType === 'dining') {
                const tableSelected = $('.table-box-mobile.selected').length > 0;
                if (!tableSelected) {
                    $('#tableNumberErrorMobile').show();
                    isValid = false;
                }
            } else {
                // Validate customer name
                const customerName = $('#customer_name_mobile').val().trim();
                if (!customerName) {
                    $('#customerNameErrorMobile').text('Required').show();
                    $('#customer_name_mobile').addClass('field-error');
                    isValid = false;
                }
                
                // Validate phone
                const phone = $('#customer_phone_mobile').val().trim();
                if (!phone) {
                    $('#customerPhoneErrorMobile').text('Required').show();
                    $('#customer_phone_mobile').addClass('field-error');
                    isValid = false;
                } else if (!/^\d{10}$/.test(phone)) {
                    $('#customerPhoneErrorMobile').text('10 digits required').show();
                    $('#customer_phone_mobile').addClass('field-error');
                    isValid = false;
                }
                
                // Validate delivery address for delivery orders
                if (currentOrderType === 'delivery') {
                    const address = $('#delivery_address_mobile').val().trim();
                    if (!address) {
                        $('#deliveryAddressErrorMobile').show();
                        $('#delivery_address_mobile').addClass('field-error');
                        isValid = false;
                    }
                }
            }
            
            return isValid;
        }
        
        function saveBillMobile() {
            if (!validateFormMobile()) {
                return;
            }
            
            // Collect form data
            const formData = new FormData();
            formData.append('create_bill', '1');
            formData.append('order_type', currentOrderType);
            formData.append('customer_name', $('#customer_name_mobile').val().trim());
            formData.append('customer_phone', $('#customer_phone_mobile').val().trim());
            formData.append('delivery_address', $('#delivery_address_mobile').val().trim());
            formData.append('order_notes', $('#order_notes_mobile').val().trim());
            formData.append('existing_order_id', '<?php echo $existing_order_id ? $existing_order_id : ''; ?>');
            
            // Add table number if dining
            if (currentOrderType === 'dining') {
                const tableNumber = $('.table-box-mobile.selected').data('table');
                formData.append('table_number', tableNumber || '');
            }
            
            // Add order items
            cartItems.forEach((item, index) => {
                formData.append(`order_items[${index}][product_name]`, item.name);
                formData.append(`order_items[${index}][price]`, item.price);
                formData.append(`order_items[${index}][quantity]`, item.quantity);
            });
            
            // Show loading
            const saveBtn = $('#saveBillMobile');
            const originalHtml = saveBtn.html();
            saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            
            // Submit via AJAX
            $.ajax({
                url: 'mobile_billing.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            showToast(`Bill ${data.action} successfully!`, 'success');
                            
                            // Reset for new order
                            if (!<?php echo $existing_order_id ? 'true' : 'false'; ?>) {
                                setTimeout(() => {
                                    window.location.href = 'orders.php?success=1&order_id=' + data.order_id;
                                }, 1500);
                            } else {
                                // For updates, go back to orders
                                setTimeout(() => {
                                    window.location.href = 'orders.php';
                                }, 1500);
                            }
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
                    showToast('Network error. Please try again.', 'danger');
                    saveBtn.prop('disabled', false).html(originalHtml);
                }
            });
        }
        
        function toggleCart() {
            isCartExpanded = !isCartExpanded;
            const cart = $('#bottomCartMobile');
            const icon = $('#cartToggleIcon');
            
            if (isCartExpanded) {
                cart.removeClass('collapsed');
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            } else {
                cart.addClass('collapsed');
                icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            }
        }
        
        function showToast(message, type = 'info') {
            // Remove existing toast
            $('.toast-mobile').remove();
            
            const icon = type === 'success' ? 'check-circle' : 
                        type === 'warning' ? 'exclamation-triangle' : 
                        type === 'danger' ? 'exclamation-circle' : 'info-circle';
            
            const toast = $(`
                <div class="alert alert-${type} toast-mobile show" role="alert">
                    <i class="fas fa-${icon} me-2"></i> ${message}
                </div>
            `);
            
            $('body').append(toast);
            
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function formatPrice(price) {
            price = parseFloat(price);
            if (price % 1 === 0) {
                return price.toString();
            } else {
                return price.toFixed(2);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Prevent vertical scrolling on body when cart is expanded
        $('body').on('touchmove', function(e) {
            const cart = $('#bottomCartMobile');
            if (isCartExpanded && cart.has(e.target).length > 0) {
                e.stopPropagation();
            }
        });
        
        // Handle back button
        window.addEventListener('popstate', function(e) {
            if (isCartExpanded) {
                toggleCart();
                e.preventDefault();
            }
        });
        
        // Prevent accidental refresh
        window.onbeforeunload = function() {
            if (cartItems.length > 0) {
                return 'You have unsaved items in cart. Are you sure you want to leave?';
            }
        };
    });
    </script>
</body>
</html>
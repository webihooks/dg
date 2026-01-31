<?php
// billing.php - Restaurant Billing System
// Start: Main PHP Logic

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user name and role
$sql_name = "SELECT name, role FROM users WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
if ($stmt_name === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$stmt_name->bind_result($user_name, $user_role);
$stmt_name->fetch();
$stmt_name->close();

// Assign to $role for menu inclusion
$role = $user_role;

// Get GST rate
$gst_rate = 0;
$gst_sql = "SELECT gst_percent FROM gst_charge WHERE user_id = ? LIMIT 1";
$gst_stmt = $conn->prepare($gst_sql);
$gst_stmt->bind_param("i", $user_id);
$gst_stmt->execute();
$gst_stmt->bind_result($gst_rate);
$gst_stmt->fetch();
$gst_stmt->close();

// Get delivery charge and free delivery minimum
$delivery_charge = 0;
$free_delivery_minimum = 0;
$delivery_sql = "SELECT delivery_charge, free_delivery_minimum FROM delivery_charges WHERE user_id = ? LIMIT 1";
$delivery_stmt = $conn->prepare($delivery_sql);
$delivery_stmt->bind_param("i", $user_id);
$delivery_stmt->execute();
$delivery_stmt->bind_result($delivery_charge_result, $free_delivery_minimum_result);
if ($delivery_stmt->fetch()) {
    $delivery_charge = $delivery_charge_result ? (float)$delivery_charge_result : 0;
    $free_delivery_minimum = $free_delivery_minimum_result ? (float)$free_delivery_minimum_result : 0;
}
$delivery_stmt->close();

// Get table count from dining_tables table
$table_count = 20; // Default value if not found
$table_sql = "SELECT table_count FROM dining_tables WHERE user_id = ? LIMIT 1";
$table_stmt = $conn->prepare($table_sql);
if ($table_stmt) {
    $table_stmt->bind_param("i", $user_id);
    $table_stmt->execute();
    $table_stmt->bind_result($table_count_result);
    if ($table_stmt->fetch()) {
        $table_count = $table_count_result;
    }
    $table_stmt->close();
}

// Handle form submission for new bill
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_bill'])) {
        $customer_name = $_POST['customer_name'] ?? '';
        $customer_phone = $_POST['customer_phone'] ?? '';
        $order_type = $_POST['order_type'] ?? 'dining';
        $delivery_address = $_POST['delivery_address'] ?? '';
        $table_number = $_POST['table_number'] ?? '';
        $order_notes = $_POST['order_notes'] ?? '';
        $order_items = $_POST['order_items'] ?? [];
        $club_with_existing = isset($_POST['club_with_existing']) ? $_POST['club_with_existing'] : false;
        $subtotal = 0;
        
        // Calculate subtotal from items
        if (!empty($order_items)) {
            foreach ($order_items as $item) {
                if (isset($item['price']) && isset($item['quantity'])) {
                    $subtotal += ($item['price'] * $item['quantity']);
                }
            }
        }
        
        // Check if we should club with existing order (only for dining)
        $existing_order_id = null;
        $club_order = false;
        
        if ($order_type === 'dining' && !empty($table_number) && $club_with_existing) {
            // Check if there's an existing order for this table that's not completed
            $check_sql = "SELECT order_id, subtotal, gst_amount, total_amount 
                         FROM orders 
                         WHERE user_id = ? 
                         AND table_number = ? 
                         AND order_type = 'dining' 
                         AND status IN ('Pending', 'Confirmed', 'Preparing', 'Ready')
                         ORDER BY created_at DESC 
                         LIMIT 1";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $user_id, $table_number);
            $check_stmt->execute();
            $check_stmt->bind_result($existing_order_id, $existing_subtotal, $existing_gst_amount, $existing_total_amount);
            
            if ($check_stmt->fetch()) {
                $club_order = true;
            }
            $check_stmt->close();
        }
        
        // Calculate GST amount
        $gst_amount = ($subtotal * $gst_rate) / 100;
        
        // Set delivery charge if delivery order
        $final_delivery_charge = 0;
        if ($order_type === 'delivery') {
            if ($free_delivery_minimum > 0 && $subtotal >= $free_delivery_minimum) {
                $final_delivery_charge = 0; // Free delivery
            } else {
                $final_delivery_charge = $delivery_charge;
            }
        }
        
        $total_amount = $subtotal + $gst_amount + $final_delivery_charge;
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            if ($club_order && $existing_order_id) {
                // Club with existing order
                // Update customer info if provided
                if (!empty($customer_name) || !empty($customer_phone)) {
                    $update_customer_sql = "UPDATE orders SET 
                                           customer_name = COALESCE(?, customer_name),
                                           customer_phone = COALESCE(?, customer_phone),
                                           order_notes = CONCAT(COALESCE(order_notes, ''), ' | ', ?)
                                           WHERE order_id = ?";
                    $update_customer_stmt = $conn->prepare($update_customer_sql);
                    $update_customer_stmt->bind_param("sssi", 
                        $customer_name ?: null,
                        $customer_phone ?: null,
                        $order_notes,
                        $existing_order_id
                    );
                    $update_customer_stmt->execute();
                    $update_customer_stmt->close();
                }
                
                // Insert order items to existing order
                if (!empty($order_items)) {
                    $item_sql = "INSERT INTO order_items (order_id, product_name, price, quantity) VALUES (?, ?, ?, ?)";
                    $item_stmt = $conn->prepare($item_sql);
                    
                    foreach ($order_items as $item) {
                        if (!empty($item['product_name']) && $item['quantity'] > 0) {
                            $item_stmt->bind_param("isdi", $existing_order_id, $item['product_name'], $item['price'], $item['quantity']);
                            if (!$item_stmt->execute()) {
                                throw new Exception("Failed to add order item: " . $conn->error);
                            }
                        }
                    }
                    $item_stmt->close();
                }
                
                // Update order totals
                $update_total_sql = "UPDATE orders 
                                    SET subtotal = subtotal + ?,
                                        gst_amount = gst_amount + ?,
                                        total_amount = total_amount + ?
                                    WHERE order_id = ?";
                $update_total_stmt = $conn->prepare($update_total_sql);
                $update_total_stmt->bind_param("dddi", $subtotal, $gst_amount, $total_amount, $existing_order_id);
                
                if (!$update_total_stmt->execute()) {
                    throw new Exception("Failed to update order totals: " . $conn->error);
                }
                $update_total_stmt->close();
                
                $order_id = $existing_order_id;
                $message = "Items added to existing bill #" . $order_id . " for Table " . $table_number;
                
            } else {
                // Create new order with status "Confirmed" (CHANGED from 'Pending')
                $order_sql = "INSERT INTO orders (user_id, customer_name, customer_phone, order_type, delivery_address, 
                               table_number, order_notes, subtotal, gst_amount, delivery_charge, total_amount, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed')"; // CHANGED
                $order_stmt = $conn->prepare($order_sql);
                $order_stmt->bind_param("issssssdddd", $user_id, $customer_name, $customer_phone, $order_type, 
                                       $delivery_address, $table_number, $order_notes, $subtotal, $gst_amount, 
                                       $final_delivery_charge, $total_amount);
                
                if (!$order_stmt->execute()) {
                    throw new Exception("Failed to create order: " . $conn->error);
                }
                
                $order_id = $conn->insert_id;
                
                // Insert order items
                if (!empty($order_items)) {
                    $item_sql = "INSERT INTO order_items (order_id, product_name, price, quantity) VALUES (?, ?, ?, ?)";
                    $item_stmt = $conn->prepare($item_sql);
                    
                    foreach ($order_items as $item) {
                        if (!empty($item['product_name']) && $item['quantity'] > 0) {
                            $item_stmt->bind_param("isdi", $order_id, $item['product_name'], $item['price'], $item['quantity']);
                            if (!$item_stmt->execute()) {
                                throw new Exception("Failed to add order item: " . $conn->error);
                            }
                        }
                    }
                    $item_stmt->close();
                }
                
                $message = "Bill created successfully! Bill #" . $order_id;
            }
            
            // Commit transaction
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error creating bill: " . $e->getMessage();
        }
        
        if (isset($order_stmt)) $order_stmt->close();
    }
}

// Function to get image URL from path
function getImageUrl($image_path, $base_url = null) {
    if (empty($image_path)) {
        return null;
    }
    
    // If it's already a full URL, return as is
    if (filter_var($image_path, FILTER_VALIDATE_URL)) {
        return $image_path;
    }
    
    // Remove any "../" from the path for security
    $clean_path = str_replace('../', '', $image_path);
    
    // Check if it's a relative path
    if (strpos($clean_path, '/') !== 0) {
        // It's a relative path
        if (!$base_url) {
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        }
        
        // Remove any leading slash from the path
        $clean_path = ltrim($clean_path, '/');
        
        // Return full URL
        return $base_url . '/' . $clean_path;
    }
    
    // It's an absolute path, check if file exists
    if (file_exists($clean_path)) {
        // Convert to URL if it's within document root
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        if (strpos($clean_path, $doc_root) === 0) {
            $relative_path = str_replace($doc_root, '', $clean_path);
            if (!$base_url) {
                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            }
            return $base_url . $relative_path;
        }
        
        // If outside document root, use base64 encoding (for small images only)
        if (filesize($clean_path) < 500000) { // 500KB limit
            $mime_type = mime_content_type($clean_path);
            $image_data = base64_encode(file_get_contents($clean_path));
            return 'data:' . $mime_type . ';base64,' . $image_data;
        }
    }
    
    return null;
}

// Fetch products with images
$products = [];
$products_table = "products_" . $user_id;
$check_table_sql = "SHOW TABLES LIKE '$products_table'";
$table_exists = $conn->query($check_table_sql)->num_rows > 0;

if ($table_exists) {
    // Load all product data at once
    $products_sql = "SELECT id, product_name, price, image_path FROM $products_table WHERE is_active = 1 ORDER BY product_name";
    $products_result = $conn->query($products_sql);
    
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    
    while ($row = $products_result->fetch_assoc()) {
        $row['price'] = (float)$row['price'];
        
        // Get image URL
        $image_url = getImageUrl($row['image_path'], $base_url);
        $row['image_url'] = $image_url;
        
        $products[] = $row;
    }
}

// Fetch recent bills
$recent_bills_sql = "SELECT o.order_id, o.customer_name, o.customer_phone, o.order_type, 
                     o.table_number, o.total_amount, o.status, o.created_at 
                     FROM orders o 
                     WHERE o.user_id = ? 
                     ORDER BY o.created_at DESC 
                     LIMIT 10";
$recent_bills_stmt = $conn->prepare($recent_bills_sql);
$recent_bills_stmt->bind_param("i", $user_id);
$recent_bills_stmt->execute();
$recent_bills_result = $recent_bills_stmt->get_result();

$conn->close();

// Helper function to format price without .00
function formatPrice($price) {
    $price = (float)$price;
    if ($price == floor($price)) {
        return (int)$price;
    }
    return number_format($price, 2, '.', '');
}
// End: Main PHP Logic
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
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="msapplication-TileImage" content="https://deegeecard.com/images/dg_logo.png">
    <meta name="application-name" content="DeeGeeCard">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- PWA Meta Tags -->
    
    <!-- CSS Libraries -->
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- JavaScript Libraries -->
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    
    <style>
        :root {
            --primary-color: #fb5b29;
            --secondary-color: #28a745;
            --light-bg: #f8f9fa;
            --border-color: #e0e0e0;
        }
        
        /* Layout Styles */
        .bill-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .bill-header {
            background: linear-gradient(135deg, var(--primary-color), #ff7b54);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        
        .bill-body {
            padding: 20px;
        }
        
        /* Order Type Buttons */
        .order-type-btn {
            padding: 10px;
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            border-radius: 5px;
            transition: all 0.3s;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .order-type-btn:hover {
            background: #fff5f0;
        }
        
        .order-type-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        /* Customer Info Card */
        .customer-info-card {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        /* Products Grid */
        .products-grid-container {
            max-height: 500px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            padding-right: 5px;
        }
        
        .product-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        
        .product-card.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .product-image-container {
            height: 60px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            overflow: hidden;
            border-radius: 5px;
            background: #f8f9fa;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .product-image.error {
            display: none;
        }
        
        .product-name {
            font-size: 12px;
            line-height: 1.2;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            color: var(--secondary-color);
            font-weight: bold;
            font-size: 13px;
        }
        
        /* Cart Items */
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .quantity-btn:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .quantity-input {
            width: 50px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            padding: 5px;
        }
        
        /* Bill Summary */
        .bill-summary-card {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        
        .total-row {
            border-top: 2px solid var(--primary-color);
            margin-top: 10px;
            padding-top: 10px;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        /* Search */
        .search-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-input {
            padding-right: 40px;
        }
        
        .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .no-products-found {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .receipt-print {
                display: block !important;
                font-size: 12px;
            }
        }
        
        .receipt-print {
            display: none;
        }
        
        /* Cart Scroll */
        .cart-scroll {
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
            background: white;
        }
        
        .empty-cart-message {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        /* Table Number Box */
        .table-number-box {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .table-box {
            width: 47px;
            height: 47px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .table-box:hover {
            border-color: var(--primary-color);
            background: #fff5f0;
        }
        
        .table-box.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* Remove Item Button */
        .remove-item-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
            font-size: 14px;
        }
        
        .remove-item-btn:hover {
            color: #bd2130;
        }
        
        /* Validation Styles */
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .field-error {
            border-color: #dc3545 !important;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Image Placeholder */
        .image-placeholder {
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
        }
        
        .image-placeholder i {
            color: #adb5bd;
            font-size: 24px;
        }
        
        /* Field ordering for different order types */
        .customer-field {
            margin-bottom: 15px;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 250px;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }
        
        .toast-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Free Delivery Info */
        .free-delivery-info {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 5px;
            padding: 8px 12px;
            margin-top: 5px;
            font-size: 12px;
            color: #2e7d32;
        }
        
        .free-delivery-info i {
            margin-right: 5px;
        }
        
        /* Phone Validation Message */
        .phone-validation-message {
            font-size: 11px;
            color: #6c757d;
            margin-top: 2px;
        }
        
        .phone-validation-message.error {
            color: #dc3545;
        }
        
        .phone-validation-message.success {
            color: #28a745;
        }
        
        /* Delivery Charge Info */
        .delivery-charge-info {
            font-size: 11px;
            color: #6c757d;
            margin-left: 5px;
            font-style: italic;
        }
        
        .free-delivery-achieved {
            color: #28a745 !important;
            font-weight: bold;
        }
        
        .delivery-charge-row {
            display: none;
        }
        
        /* Club Order Option */
        .club-order-option {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .club-order-option .form-check-label {
            font-weight: 500;
            color: #2e7d32;
        }
        
        .club-order-option small {
            color: #6c757d;
            font-size: 11px;
            display: block;
            margin-top: 5px;
        }

        /* Bill & KOT Preview Modal Styling */
        .bill-container-preview {
            width: 65mm;
            max-width: 65mm;
            font-family: 'Arial';
            font-size: 12px;
            line-height: 1.2;
            background: white;
            padding: 0;
            margin: 0 auto;
            color: #000 !important;
        }
        
        .bill-header-preview {
            text-align: center;
            margin-bottom: 5px;
            color: #000 !important;
        }
        
        .bill-header-preview .business-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 2px;
            color: #000 !important;
        }
        
        .bill-header-preview .business-address {
            font-size: 10px;
            margin-bottom: 2px;
            color: #000 !important;
        }
        
        .bill-header-preview .business-phone {
            font-size: 10px;
            margin-bottom: 3px;
            color: #000 !important;
        }
        
        .bill-divider-preview {
            border-bottom: 1px solid #000;
            margin: 3px 0;
        }
        
        .bill-double-divider-preview {
            border-bottom: 2px solid #000;
            margin: 3px 0;
        }
        
        .bill-row-preview {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
            color: #000 !important;
        }
        
        .bill-item-name-preview {
            flex: 2;
            text-align: left;
            font-size: 11px;
            color: #000 !important;
        }
        
        .bill-item-qty-preview {
            flex: 1;
            text-align: center;
            color: #000 !important;
        }
        
        .bill-item-price-preview {
            flex: 1;
            text-align: right;
            color: #000 !important;
        }
        
        .bill-item-total-preview {
            flex: 1;
            text-align: right;
            color: #000 !important;
        }
        
        .bill-summary-row-preview {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
            color: #000 !important;
        }
        
        .bill-summary-label-preview {
            flex: 2;
            text-align: left;
            color: #000 !important;
        }
        
        .bill-summary-value-preview {
            flex: 1;
            text-align: right;
            color: #000 !important;
        }
        
        .bill-footer-preview {
            margin-top: 5px;
            font-size: 10px;
            text-align: center;
            color: #000 !important;
        }
        
        /* KOT Preview Styling */
        .kot-container-preview {
            width: 65mm;
            max-width: 65mm;
            font-family: 'Arial';
            font-size: 12px;
            line-height: 1.2;
            background: white;
            padding: 0;
            margin: 0 auto;
            color: #000 !important;
        }
        
        .kot-header-preview {
            text-align: center;
            margin-bottom: 5px;
            color: #000 !important;
        }
        
        .kot-header-preview .business-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 2px;
            color: #000 !important;
        }
        
        .kot-header-preview .kot-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 3px;
            color: #000 !important;
            text-transform: uppercase;
        }
        
        .kot-divider-preview {
            border-bottom: 1px solid #000;
            margin: 3px 0;
        }
        
        .kot-double-divider-preview {
            border-bottom: 2px solid #000;
            margin: 3px 0;
        }
        
        .kot-row-preview {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
            color: #000 !important;
        }
        
        .kot-item-name-preview {
            flex: 2;
            text-align: left;
            font-size: 11px;
            color: #000 !important;
        }
        
        .kot-item-qty-preview {
            flex: 1;
            text-align: center;
            color: #000 !important;
            font-weight: bold;
        }
        
        .kot-item-special-preview {
            flex: 3;
            text-align: left;
            font-size: 10px;
            font-style: italic;
            color: #000 !important;
            margin-top: -2px;
        }
        
        .kot-footer-preview {
            margin-top: 5px;
            font-size: 10px;
            text-align: center;
            color: #000 !important;
        }

        /* Print button specific styles */
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #e0a800;
            color: #000;
        }
        
        .btn-dark {
            background-color: #343a40;
            border-color: #343a40;
            color: #fff;
        }
        
        .btn-dark:hover {
            background-color: #23272b;
            border-color: #23272b;
            color: #fff;
        }

        /* Print specific styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .bill-container-preview, .bill-container-preview *,
            .kot-container-preview, .kot-container-preview * {
                visibility: visible;
                color: #000 !important;
            }
            .bill-container-preview,
            .kot-container-preview {
                position: absolute;
                left: 0;
                top: 0;
                width: 65mm;
                max-width: 65mm;
                color: #000 !important;
            }
            .modal-footer, .modal-header {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <!-- Start: Page Wrapper -->
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php include ($role === 'admin') ? 'admin_menu.php' : 'menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <!-- Messages -->
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Toast Notification for cart actions -->
                <div class="toast-notification alert alert-info" id="cartToast" style="display: none;">
                    <i class="fas fa-shopping-cart me-2"></i>
                    <span id="toastMessage"></span>
                </div>
                
                <!-- Main Billing Interface -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="bill-container mb-4">
                            <div class="bill-header">
                                <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Create New Bill</h5>
                            </div>
                            
                            <div class="bill-body">
                                <div class="row">
                                    <!-- Left Column: Customer Details -->
                                    <div class="col-md-3">
                                        <!-- Order Type Selection -->
                                        <div class="mb-4">
                                            <h5 class="mb-3">Order Type</h5>
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
                                        
                                        <!-- Customer Information Card -->
                                        <div class="customer-info-card">
                                            <h5 class="mb-3"><i class="fas fa-user me-2"></i> Customer Details</h5>
                                            <div class="row">
                                                <!-- Dining Order Fields -->
                                                <div class="col-md-12 mb-3 dining-info">
                                                    <label class="form-label required-field">Table Number</label>
                                                    <div class="table-number-box" id="tableNumberBox">
                                                        <?php for($i = 1; $i <= $table_count; $i++): ?>
                                                            <div class="table-box" data-table="<?php echo $i; ?>"><?php echo $i; ?></div>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <input type="hidden" id="table_number" name="table_number">
                                                    <div class="error-message" id="tableNumberError">Please select a table number</div>
                                                </div>
                                                
                                                <!-- Club Order Option -->
                                                <div class="col-md-12 club-order-option" id="clubOrderOption" style="display: none;">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="club_with_existing" name="club_with_existing">
                                                        <label class="form-check-label" for="club_with_existing">
                                                            <i class="fas fa-plus-circle me-1"></i> Add items to existing bill for this table
                                                        </label>
                                                    </div>
                                                    <small class="text-muted">If checked, items will be added to the existing pending bill for this table.</small>
                                                </div>
                                                
                                                <!-- Customer Name - Order changes based on order type -->
                                                <div class="col-md-12 customer-field" id="customerNameField">
                                                    <label class="form-label" id="customerNameLabel">Customer Name</label>
                                                    <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Enter customer name">
                                                    <div class="error-message" id="customerNameError"></div>
                                                </div>
                                                
                                                <!-- Phone Number - Order changes based on order type -->
                                                <div class="col-md-12 customer-field" id="customerPhoneField">
                                                    <label class="form-label" id="customerPhoneLabel">Phone Number</label>
                                                    <input type="text" class="form-control" id="customer_phone" name="customer_phone" 
                                                           placeholder="Enter 10-digit phone number" 
                                                           maxlength="10"
                                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);">
                                                    <div class="error-message" id="customerPhoneError"></div>
                                                    <div class="phone-validation-message" id="phoneValidationMessage">
                                                        Enter 10-digit number (should not start with 0)
                                                    </div>
                                                </div>
                                                
                                                <!-- Delivery Address (Only for delivery orders) -->
                                                <div class="col-md-12 customer-field delivery-info" id="deliveryAddressField" style="display: none;">
                                                    <label class="form-label required-field">Delivery Address</label>
                                                    <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" placeholder="Enter complete delivery address" required></textarea>
                                                    <div class="error-message" id="deliveryAddressError">Delivery address is required</div>
                                                    <?php if ($free_delivery_minimum > 0): ?>
                                                    <div class="free-delivery-info">
                                                        <i class="fas fa-truck"></i>
                                                        Free delivery on orders above ₹<?php echo formatPrice($free_delivery_minimum); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Special Instructions (Common for all order types) -->
                                                <div class="col-md-12 customer-field" id="specialInstructionsField">
                                                    <label class="form-label">Special Instructions</label>
                                                    <textarea class="form-control" id="order_notes" name="order_notes" rows="2" placeholder="Any special requests or notes"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Middle Column: Menu Items -->
                                    <div class="col-md-5">
                                        <!-- Search Bar -->
                                        <div class="mb-3">
                                            <h5 class="mb-3"><i class="fas fa-pizza-slice me-2"></i> Menu Items</h5>
                                            <div class="search-container">
                                                <input type="text" class="form-control search-input" id="productSearch" placeholder="Search products...">
                                                <i class="fas fa-search search-icon"></i>
                                            </div>
                                        </div>
                                        
                                        <!-- Products Grid -->
                                        <div class="mb-4">
                                            <?php if (!empty($products)): ?>
                                            <div class="products-grid-container">
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
                                                                 loading="lazy"
                                                                 onerror="this.classList.add('error'); this.nextElementSibling.style.display='flex';">
                                                            <div class="image-placeholder" style="display: none;">
                                                                <i class="fas fa-image"></i>
                                                            </div>
                                                            <?php else: ?>
                                                            <div class="image-placeholder">
                                                                <i class="fas fa-image"></i>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                                        <div class="product-price">₹<?php echo formatPrice($product['price']); ?></div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div id="noProductsFound" class="no-products-found" style="display: none;">
                                                <i class="fas fa-search fa-2x mb-3"></i>
                                                <p>No products found matching your search.</p>
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
                                    <div class="col-md-4">
                                        <!-- Order Cart -->
                                        <div class="mb-4">
                                            <h5 class="mb-3"><i class="fas fa-shopping-cart me-2"></i> Order Items</h5>
                                            <div class="cart-scroll" id="orderCart">
                                                <div class="empty-cart-message" id="emptyCart">
                                                    <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                                    <p>No items added to cart. Select items from the menu.</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bill Summary -->
                                        <div class="bill-summary-card">
                                            <h5 class="mb-3"><i class="fas fa-file-invoice-dollar me-2"></i> Bill Summary</h5>
                                            <div class="summary-row">
                                                <span>Subtotal:</span>
                                                <span>₹<span id="subtotal">0</span></span>
                                            </div>
                                            <div class="summary-row">
                                                <span>GST (<?php echo $gst_rate; ?>%):</span>
                                                <span>₹<span id="gstAmount">0</span></span>
                                            </div>
                                            <div class="summary-row delivery-charge-row" id="deliveryChargeRow">
                                                <span>Delivery Charge:</span>
                                                <span id="deliveryChargeDisplay">
                                                    ₹<span id="deliveryChargeAmount">0</span>
                                                    <span id="freeDeliveryIndicator" style="display: none; color: #28a745; margin-left: 5px;">(FREE)</span>
                                                </span>
                                            </div>
                                            <div class="summary-row total-row">
                                                <span>Total Amount:</span>
                                                <span>₹<span id="totalAmount">0</span></span>
                                            </div>
                                            
                                            <!-- Free Delivery Progress (Optional) -->
                                            <?php if ($free_delivery_minimum > 0): ?>
                                            <div class="mt-3" id="freeDeliveryProgress" style="display: none;">
                                                <small class="text-muted d-block mb-1">
                                                    Add <span id="amountNeeded">₹0</span> more for FREE delivery
                                                </small>
                                                <div class="progress" style="height: 6px;">
                                                    <div id="deliveryProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Bill Form -->
                                            <form id="billForm" method="POST" action="">
                                                <input type="hidden" id="order_type_input" name="order_type" value="dining">
                                                <input type="hidden" id="customer_name_input" name="customer_name">
                                                <input type="hidden" id="customer_phone_input" name="customer_phone">
                                                <input type="hidden" id="table_number_input" name="table_number">
                                                <input type="hidden" id="delivery_address_input" name="delivery_address">
                                                <input type="hidden" id="order_notes_input" name="order_notes">
                                                <input type="hidden" id="club_with_existing_input" name="club_with_existing" value="0">
                                                <div id="orderItemsInputs"></div>
                                                
                                                <!-- Action Buttons -->
                                                <div class="d-flex gap-1 mt-4">
                                                    <button type="button" class="btn btn-secondary flex-grow-1 no-print" id="clearCart">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <button type="submit" name="create_bill" class="btn btn-primary flex-grow-1 no-print" id="saveBillBtn">
                                                        <i class="fas fa-save me-2"></i> Save Bill
                                                    </button>
                                                    <button type="button" class="btn btn-warning flex-grow-1 no-print" id="previewKOT">
                                                        <i class="fas fa-print me-2"></i> Print KOT
                                                    </button>
                                                    <button type="button" class="btn btn-warning flex-grow-1 no-print" id="previewBill">
                                                        <i class="fas fa-print me-2"></i> Print Bill
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

            <!-- KOT Preview Modal -->
            <div class="modal fade" id="kotPreviewModal" tabindex="-1" aria-labelledby="kotPreviewModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="kotPreviewModalLabel">KOT Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-2">
                            <!-- KOT Content will be loaded here -->
                            <div id="kotContent" class="kot-container-preview">
                                <!-- KOT content will be dynamically loaded -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-success" onclick="printKOT()">
                                <i class="fas fa-print me-2"></i> Print KOT
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bill Preview Modal -->
            <div class="modal fade" id="billPreviewModal" tabindex="-1" aria-labelledby="billPreviewModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="billPreviewModalLabel">Bill Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-2">
                            <!-- Bill Content will be loaded here -->
                            <div id="billContent" class="bill-container-preview">
                                <!-- Bill content will be dynamically loaded -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="printBill()">
                                <i class="fas fa-print me-2"></i> Print Bill
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>
    <!-- End: Page Wrapper -->

    <!-- JavaScript Libraries -->
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <!-- Main JavaScript -->
    <script>
    // Start: Main JavaScript Logic
    $(document).ready(function() {
        const gstRate = <?php echo $gst_rate; ?>;
        const deliveryCharge = <?php echo $delivery_charge; ?>;
        const freeDeliveryMinimum = <?php echo $free_delivery_minimum; ?>;
        let cartItems = [];
        
        // Helper function to format price without .00
        function formatPrice(price) {
            price = parseFloat(price);
            if (price % 1 === 0) {
                return price.toFixed(0);
            }
            return price.toFixed(2);
        }
        
        // Validate phone number
        function validatePhoneNumber(phone) {
            if (!phone) return { isValid: false, message: 'Phone number is required' };
            
            // Check if it's exactly 10 digits
            if (phone.length !== 10) {
                return { isValid: false, message: 'Phone number must be exactly 10 digits' };
            }
            
            // Check if it starts with 0
            if (phone.charAt(0) === '0') {
                return { isValid: false, message: 'Phone number should not start with 0' };
            }
            
            // Check if all characters are digits
            if (!/^\d+$/.test(phone)) {
                return { isValid: false, message: 'Phone number should contain only digits' };
            }
            
            return { isValid: true, message: '' };
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const toast = $('#cartToast');
            const toastMessage = $('#toastMessage');
            
            toastMessage.text(message);
            toast.removeClass('alert-info alert-success alert-warning alert-danger')
                 .addClass('alert-' + (type === 'info' ? 'info' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'danger' ? 'danger' : 'info'))
                 .fadeIn(300)
                 .addClass('show');
            
            setTimeout(function() {
                toast.fadeOut(300).removeClass('show');
            }, 3000);
        }
        
        // Function to reorder customer fields based on order type
        function reorderCustomerFields(orderType) {
            const customerCard = $('.customer-info-card .row');
            const customerNameField = $('#customerNameField');
            const customerPhoneField = $('#customerPhoneField');
            const deliveryAddressField = $('#deliveryAddressField');
            const specialInstructionsField = $('#specialInstructionsField');
            const tableInfoField = $('.dining-info');
            const clubOrderOption = $('#clubOrderOption');
            
            // Remove all fields first
            customerNameField.detach();
            customerPhoneField.detach();
            deliveryAddressField.detach();
            specialInstructionsField.detach();
            clubOrderOption.detach();
            
            if (orderType === 'dining') {
                // Order for Dining: Table → Club Option → Customer Name (optional) → Phone (optional) → Special Instructions
                tableInfoField.show();
                customerCard.append(tableInfoField);
                
                // Show club option if table is selected
                if ($('#table_number').val()) {
                    clubOrderOption.show();
                    customerCard.append(clubOrderOption);
                }
                
                customerCard.append(customerNameField);
                customerCard.append(customerPhoneField);
                customerCard.append(specialInstructionsField);
                
                // Update labels and requirements
                $('#customerNameLabel').html('Customer Name <small class="text-muted">(optional)</small>');
                $('#customerPhoneLabel').html('Phone Number <small class="text-muted">(optional)</small>');
                $('#customer_name').removeClass('required-field');
                $('#customer_phone').removeClass('required-field');
                $('#phoneValidationMessage').hide();
                
            } else if (orderType === 'delivery') {
                // Order for Delivery: Customer Name (required) → Phone (required) → Delivery Address (required) → Special Instructions
                tableInfoField.hide();
                clubOrderOption.hide();
                customerCard.append(customerNameField);
                customerCard.append(customerPhoneField);
                customerCard.append(deliveryAddressField);
                customerCard.append(specialInstructionsField);
                
                // Show delivery fields
                deliveryAddressField.show();
                
                // Update labels and requirements
                $('#customerNameLabel').html('Customer Name <span class="text-danger">*</span>');
                $('#customerPhoneLabel').html('Phone Number <span class="text-danger">*</span>');
                $('#customer_name').addClass('required-field');
                $('#customer_phone').addClass('required-field');
                $('#phoneValidationMessage').show();
                
            } else if (orderType === 'takeaway') {
                // Order for Takeaway: Customer Name (required) → Phone (required) → Special Instructions
                tableInfoField.hide();
                clubOrderOption.hide();
                customerCard.append(customerNameField);
                customerCard.append(customerPhoneField);
                customerCard.append(specialInstructionsField);
                
                // Hide delivery fields
                deliveryAddressField.hide();
                
                // Update labels and requirements
                $('#customerNameLabel').html('Customer Name <span class="text-danger">*</span>');
                $('#customerPhoneLabel').html('Phone Number <span class="text-danger">*</span>');
                $('#customer_name').addClass('required-field');
                $('#customer_phone').addClass('required-field');
                $('#phoneValidationMessage').show();
            }
        }
        
        // Real-time phone number validation
        $('#customer_phone').on('input', function() {
            const phone = $(this).val().trim();
            const validation = validatePhoneNumber(phone);
            const validationMessage = $('#phoneValidationMessage');
            
            if (phone.length > 0) {
                if (validation.isValid) {
                    validationMessage.removeClass('error').addClass('success').text('✓ Valid phone number');
                } else {
                    validationMessage.removeClass('success').addClass('error').text(validation.message);
                }
            } else {
                validationMessage.removeClass('error success').text('Enter 10-digit number (should not start with 0)');
            }
        });
        
        // Initialize product cards
        function initializeProductCards() {
            // Product selection
            $(document).on('click', '.product-card', function() {
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const productPrice = $(this).data('product-price');
                
                // Check if product already in cart
                const existingItem = cartItems.find(item => item.id === productId);
                
                if (existingItem) {
                    existingItem.quantity++;
                    $(this).find('.product-price').text('✓ Added');
                    $(this).addClass('selected');
                    setTimeout(() => {
                        $(this).find('.product-price').text('₹' + formatPrice(productPrice));
                        $(this).removeClass('selected');
                    }, 1000);
                    
                    showToast(`${productName} quantity increased to ${existingItem.quantity}`, 'success');
                } else {
                    cartItems.push({
                        id: productId,
                        name: productName,
                        price: productPrice,
                        quantity: 1
                    });
                    
                    $(this).find('.product-price').text('✓ Added');
                    $(this).addClass('selected');
                    setTimeout(() => {
                        $(this).find('.product-price').text('₹' + formatPrice(productPrice));
                        $(this).removeClass('selected');
                    }, 1000);
                    
                    showToast(`${productName} added to cart`, 'success');
                }
                
                updateCart();
            });
        }
        
        // Initialize
        initializeProductCards();
        
        // Order type selection - FIXED BILL SUMMARY BUGS
        $('.order-type-btn').click(function() {
            const orderType = $(this).data('type');
            $('.order-type-btn').removeClass('active');
            $(this).addClass('active');
            $('#order_type_input').val(orderType);
            
            // Reorder customer fields based on order type
            reorderCustomerFields(orderType);
            
            // Show/hide relevant sections based on order type - FIXED
            if (orderType === 'dining') {
                $('.dining-info').show();
                $('.delivery-info').hide();
                $('#deliveryChargeRow').hide();
                $('#freeDeliveryProgress').hide();
            } else if (orderType === 'delivery') {
                $('.dining-info').hide();
                $('.delivery-info').show();
                // Always show delivery charge row for delivery, but recalculate if cart has items
                $('#deliveryChargeRow').show();
                if (cartItems.length > 0) {
                    calculateTotals();
                } else {
                    $('#deliveryChargeAmount').text('0');
                    $('#freeDeliveryIndicator').hide();
                    $('#freeDeliveryProgress').hide();
                }
            } else { // takeaway
                $('.dining-info').hide();
                $('.delivery-info').hide();
                $('#deliveryChargeRow').hide();
                $('#freeDeliveryProgress').hide();
            }
            
            // Clear any previous validation errors
            clearValidationErrors();
        });
        
        // Table number selection
        $('.table-box').click(function() {
            const tableNumber = $(this).data('table');
            $('.table-box').removeClass('selected');
            $(this).addClass('selected');
            $('#table_number').val(tableNumber);
            $('#table_number_input').val(tableNumber);
            
            // Check if there's an existing order for this table
            checkExistingOrder(tableNumber);
            
            // Clear table number error
            $('#tableNumberError').hide();
            $('.table-box').removeClass('field-error');
            
            // Show club order option
            $('#clubOrderOption').show();
            reorderCustomerFields($('#order_type_input').val());
        });
        
        // Check existing order for table
        function checkExistingOrder(tableNumber) {
            $.ajax({
                url: 'check_existing_order.php',
                type: 'POST',
                data: {
                    table_number: tableNumber,
                    user_id: <?php echo $user_id; ?>
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.exists) {
                            $('#club_with_existing').prop('checked', true);
                            $('#club_with_existing_input').val(data.order_id);
                            showToast(`Existing bill #${data.order_id} found for Table ${tableNumber}. Items will be added to this bill.`, 'info');
                        } else {
                            $('#club_with_existing').prop('checked', false);
                            $('#club_with_existing_input').val('0');
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking existing order:', error);
                }
            });
        }
        
        // Product search functionality
        $('#productSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const productCards = $('.product-card');
            const productsContainer = $('#productsContainer');
            const noProductsFound = $('#noProductsFound');
            
            let foundCount = 0;
            
            productCards.each(function() {
                const productName = $(this).data('product-name').toLowerCase();
                if (productName.includes(searchTerm)) {
                    $(this).show();
                    foundCount++;
                } else {
                    $(this).hide();
                }
            });
            
            if (foundCount === 0 && searchTerm.length > 0) {
                productsContainer.hide();
                noProductsFound.show();
            } else {
                productsContainer.show();
                noProductsFound.hide();
            }
        });
        
        // Update cart display
        function updateCart() {
            const orderCart = $('#orderCart');
            const emptyCart = $('#emptyCart');
            const orderItemsInputs = $('#orderItemsInputs');
            
            if (cartItems.length === 0) {
                orderCart.html(emptyCart.clone().show());
                // Reset totals to 0 when cart is empty
                $('#subtotal').text('0');
                $('#gstAmount').text('0');
                $('#totalAmount').text('0');
                $('#deliveryChargeAmount').text('0');
                $('#freeDeliveryIndicator').hide();
                $('#freeDeliveryProgress').hide();
                return;
            }
            
            let cartHtml = '';
            let itemsInputs = '';
            
            cartItems.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                
                cartHtml += `
                    <div class="cart-item">
                        <div>
                            <strong>${item.name}</strong><br>
                            <small>₹${formatPrice(item.price)} × ${item.quantity}</small>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="fw-bold">₹${formatPrice(itemTotal)}</div>
                            <div class="cart-item-controls">
                                <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                                <span class="quantity-input">${item.quantity}</span>
                                <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
                                <button type="button" class="remove-item-btn" onclick="removeItem(${index})" title="Remove item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                itemsInputs += `
                    <input type="hidden" name="order_items[${index}][product_name]" value="${item.name}">
                    <input type="hidden" name="order_items[${index}][price]" value="${item.price}">
                    <input type="hidden" name="order_items[${index}][quantity]" value="${item.quantity}">
                `;
            });
            
            orderCart.html(cartHtml);
            orderItemsInputs.html(itemsInputs);
            
            calculateTotals();
        }
        
        // Update item quantity
        window.updateQuantity = function(index, change) {
            if (cartItems[index]) {
                const oldQuantity = cartItems[index].quantity;
                cartItems[index].quantity += change;
                
                if (cartItems[index].quantity < 1) {
                    cartItems[index].quantity = 1;
                    return;
                }
                
                const productName = cartItems[index].name;
                const newQuantity = cartItems[index].quantity;
                
                updateCart();
                
                if (change > 0) {
                    showToast(`${productName} quantity increased to ${newQuantity}`, 'success');
                } else {
                    showToast(`${productName} quantity decreased to ${newQuantity}`, 'warning');
                }
            }
        };
        
        // Remove item from cart
        window.removeItem = function(index) {
            if (cartItems[index]) {
                const removedItem = cartItems[index];
                cartItems.splice(index, 1);
                updateCart(); // This will recalculate totals
                
                showToast(`${removedItem.name} removed from cart`, 'warning');
                
                // Show empty cart message if cart is now empty
                if (cartItems.length === 0) {
                    showToast('All items removed from cart', 'info');
                }
            }
        };
        
        // Clear cart
        $('#clearCart').click(function() {
            if (cartItems.length === 0) {
                showToast('Cart is already empty!', 'info');
                return;
            }
            
            if (confirm('Are you sure you want to clear all items from cart?')) {
                const itemCount = cartItems.length;
                cartItems = [];
                updateCart(); // This will reset totals to 0
                showToast(`All ${itemCount} items removed from cart`, 'warning');
            }
        });
        
        // Calculate totals with free delivery logic - COMPLETELY FIXED VERSION
        function calculateTotals() {
            let subtotal = 0;
            
            cartItems.forEach(item => {
                subtotal += item.price * item.quantity;
            });
            
            const gstAmount = subtotal * (gstRate / 100);
            const orderType = $('#order_type_input').val();
            
            // Calculate delivery charge with free delivery logic
            let delivery = 0;
            let isFreeDelivery = false;
            let showDeliveryRow = false;
            
            if (orderType === 'delivery') {
                showDeliveryRow = true;
                if (freeDeliveryMinimum > 0 && subtotal >= freeDeliveryMinimum) {
                    // Free delivery
                    delivery = 0;
                    isFreeDelivery = true;
                } else {
                    // Regular delivery charge
                    delivery = deliveryCharge;
                    isFreeDelivery = false;
                }
            }
            
            const totalAmount = subtotal + gstAmount + delivery;
            
            // Update display
            $('#subtotal').text(formatPrice(subtotal));
            $('#gstAmount').text(formatPrice(gstAmount));
            $('#totalAmount').text(formatPrice(totalAmount));
            
            // Update delivery charge display - FIXED LOGIC
            if (showDeliveryRow) {
                $('#deliveryChargeRow').show();
                $('#deliveryChargeAmount').text(formatPrice(delivery));
                
                if (isFreeDelivery) {
                    $('#freeDeliveryIndicator').show().text('(FREE)');
                } else {
                    $('#freeDeliveryIndicator').hide();
                }
                
                // Update free delivery progress if applicable
                if (freeDeliveryMinimum > 0) {
                    const progressContainer = $('#freeDeliveryProgress');
                    const progressBar = $('#deliveryProgressBar');
                    const amountNeeded = $('#amountNeeded');
                    
                    if (subtotal < freeDeliveryMinimum) {
                        const needed = freeDeliveryMinimum - subtotal;
                        const progressPercent = Math.min((subtotal / freeDeliveryMinimum) * 100, 100);
                        
                        amountNeeded.text('₹' + formatPrice(needed));
                        progressBar.css('width', progressPercent + '%');
                        progressContainer.show();
                    } else {
                        progressContainer.hide();
                    }
                } else {
                    $('#freeDeliveryProgress').hide();
                }
            } else {
                $('#deliveryChargeRow').hide();
                $('#freeDeliveryProgress').hide();
            }
        }
        
        // Clear validation errors
        function clearValidationErrors() {
            $('.error-message').hide();
            $('.form-control').removeClass('field-error');
            $('#phoneValidationMessage').removeClass('error success');
        }
        
        // Validate form based on order type
        function validateForm() {
            let isValid = true;
            const orderType = $('#order_type_input').val();
            
            clearValidationErrors();
            
            // Common validation - cart must have items
            if (cartItems.length === 0) {
                showToast('Please add at least one item to the cart.', 'warning');
                return false;
            }
            
            // Order type specific validations
            if (orderType === 'dining') {
                // Table number is required for dining
                const tableNumber = $('#table_number').val();
                if (!tableNumber) {
                    $('#tableNumberError').show();
                    $('.table-box').addClass('field-error');
                    isValid = false;
                }
                
            } else if (orderType === 'delivery') {
                // Customer name is required for delivery
                const customerName = $('#customer_name').val().trim();
                if (!customerName) {
                    $('#customerNameError').text('Customer name is required for delivery').show();
                    $('#customer_name').addClass('field-error');
                    isValid = false;
                }
                
                // Phone number validation for delivery
                const phone = $('#customer_phone').val().trim();
                const phoneValidation = validatePhoneNumber(phone);
                if (!phoneValidation.isValid) {
                    $('#customerPhoneError').text(phoneValidation.message).show();
                    $('#customer_phone').addClass('field-error');
                    $('#phoneValidationMessage').addClass('error').text(phoneValidation.message);
                    isValid = false;
                }
                
                // Delivery address is required for delivery
                const address = $('#delivery_address').val().trim();
                if (!address) {
                    $('#deliveryAddressError').show();
                    $('#delivery_address').addClass('field-error');
                    isValid = false;
                }
                
            } else if (orderType === 'takeaway') {
                // Customer name is required for takeaway
                const customerName = $('#customer_name').val().trim();
                if (!customerName) {
                    $('#customerNameError').text('Customer name is required for takeaway').show();
                    $('#customer_name').addClass('field-error');
                    isValid = false;
                }
                
                // Phone number validation for takeaway
                const phone = $('#customer_phone').val().trim();
                const phoneValidation = validatePhoneNumber(phone);
                if (!phoneValidation.isValid) {
                    $('#customerPhoneError').text(phoneValidation.message).show();
                    $('#customer_phone').addClass('field-error');
                    $('#phoneValidationMessage').addClass('error').text(phoneValidation.message);
                    isValid = false;
                }
            }
            
            if (!isValid) {
                showToast('Please fill in all required fields correctly', 'warning');
            }
            
            return isValid;
        }
        
        // Generate KOT HTML for thermal printer (65mm)
        function generateKOTHTML() {
            const customerName = $('#customer_name').val() || 'Walk-in Customer';
            const customerPhone = $('#customer_phone').val() || 'N/A';
            const orderType = $('#order_type_input').val();
            const tableNumber = $('#table_number').val();
            const orderNotes = $('#order_notes').val() || '';
            
            // Format order type
            const orderTypeDisplay = orderType === 'delivery' ? 'HOME DELIVERY' : 
                                   orderType === 'dining' ? `DINE-IN (TABLE ${tableNumber})` : 'TAKEAWAY';
            
            // Format date and time
            const now = new Date();
            const orderDate = now.toLocaleDateString('en-IN');
            const orderTime = now.toLocaleTimeString('en-IN', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            
            let kotHtml = `
                <div class="kot-container-preview">
                    <div class="kot-header-preview">
                        <div class="business-name"><?php echo htmlspecialchars($user_name); ?> Restaurant</div>
                        <div class="kot-title">KITCHEN ORDER TICKET</div>
                    </div>
                    
                    <div class="kot-double-divider-preview"></div>
                    
                    <!-- Order Information -->
                    <div class="kot-row-preview">
                        <div class="kot-item-name-preview">Date</div>
                        <div class="kot-item-qty-preview">${orderDate}</div>
                    </div>
                    <div class="kot-row-preview">
                        <div class="kot-item-name-preview">Time</div>
                        <div class="kot-item-qty-preview">${orderTime}</div>
                    </div>
                    <div class="kot-row-preview">
                        <div class="kot-item-name-preview">Type</div>
                        <div class="kot-item-qty-preview">${orderTypeDisplay}</div>
                    </div>
                    
                    <div class="kot-divider-preview"></div>
                    
                    <!-- Customer Information -->
                    <div class="kot-row-preview">
                        <div class="kot-item-name-preview">Customer:</div>
                        <div class="kot-item-qty-preview">${escapeHtml(customerName)}</div>
                    </div>
            `;
            
            if (orderType === 'dining') {
                kotHtml += `
                    <div class="kot-row-preview">
                        <div class="kot-item-name-preview">Table No:</div>
                        <div class="kot-item-qty-preview">${tableNumber}</div>
                    </div>
                `;
            }
            
            kotHtml += `
                    <div class="kot-double-divider-preview"></div>
                    
                    <!-- Order Items -->
                    <div class="kot-row-preview" style="font-weight: bold;">
                        <div class="kot-item-name-preview">ITEMS</div>
                        <div class="kot-item-qty-preview">QTY</div>
                    </div>
                    <div class="kot-divider-preview"></div>
            `;
            
            // Add order items
            if (cartItems.length > 0) {
                cartItems.forEach(item => {
                    kotHtml += `
                    <div class="kot-row-preview">
                        <div class="kot-item-name-preview">${escapeHtml(item.name)}</div>
                        <div class="kot-item-qty-preview">${item.quantity}x</div>
                    </div>
                    `;
                    
                    // Check if there are special instructions for this item
                    if (orderNotes && orderNotes.toLowerCase().includes(item.name.toLowerCase())) {
                        kotHtml += `
                        <div class="kot-row-preview">
                            <div class="kot-item-special-preview">* Special: ${escapeHtml(orderNotes)}</div>
                        </div>
                        `;
                    }
                });
            }
            
            // Add general order notes if available
            if (orderNotes && !cartItems.some(item => 
                orderNotes.toLowerCase().includes(item.name.toLowerCase()))) {
                kotHtml += `
                    <div class="kot-double-divider-preview"></div>
                    <div class="kot-row-preview">
                        <div class="kot-item-special-preview" style="text-align: center; font-weight: bold;">
                            SPECIAL INSTRUCTIONS:
                        </div>
                    </div>
                    <div class="kot-row-preview">
                        <div class="kot-item-special-preview" style="text-align: center;">
                            ${escapeHtml(orderNotes)}
                        </div>
                    </div>
                `;
            }
            
            kotHtml += `
                    <div class="kot-double-divider-preview"></div>
                    
                    <!-- Footer -->
                    <div class="kot-footer-preview">
                        <div>*** KITCHEN COPY ***</div>
                        <div>Order Time: ${orderTime}</div>
                        <div style="margin-top: 3px;">
                            ${now.toLocaleTimeString('en-IN', { 
                                hour: '2-digit', 
                                minute: '2-digit',
                                hour12: true 
                            })}
                        </div>
                    </div>
                </div>
            `;
            
            return kotHtml;
        }
        
        // Generate Bill HTML for thermal printer (65mm)
        function generateBillHTML() {
            const customerName = $('#customer_name').val() || 'Walk-in Customer';
            const customerPhone = $('#customer_phone').val() || 'N/A';
            const orderType = $('#order_type_input').val();
            const tableNumber = $('#table_number').val();
            const deliveryAddress = $('#delivery_address').val() || '';
            const orderNotes = $('#order_notes').val() || '';
            
            // Calculate totals
            let subtotal = 0;
            cartItems.forEach(item => {
                subtotal += item.price * item.quantity;
            });
            
            const gstAmount = subtotal * (gstRate / 100);
            let delivery = 0;
            if (orderType === 'delivery') {
                if (freeDeliveryMinimum > 0 && subtotal >= freeDeliveryMinimum) {
                    delivery = 0;
                } else {
                    delivery = deliveryCharge;
                }
            }
            const totalAmount = subtotal + gstAmount + delivery;
            
            // Format order type
            const orderTypeDisplay = orderType === 'delivery' ? 'Home Delivery' : 
                                   orderType === 'dining' ? `Dine-In (Table ${tableNumber})` : 'Takeaway';
            
            // Format date and time
            const now = new Date();
            const orderDate = now.toLocaleDateString('en-IN');
            const orderTime = now.toLocaleTimeString('en-IN', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            
            // Helper function to format currency - remove .00 if whole number
            const formatCurrency = (amount) => {
                const num = parseFloat(amount || 0);
                if (num % 1 === 0) {
                    return num.toString();
                } else {
                    return num.toFixed(2);
                }
            };
            
            let billHtml = `
                <div class="bill-container-preview">
                    <div class="bill-header-preview">
                        <div class="business-name"><?php echo htmlspecialchars($user_name); ?> Restaurant</div>
                        <div class="business-address">Restaurant Address</div>
                        <?php if (!empty($user_phone)): ?>
                        <div class="business-phone">Ph: <?php echo htmlspecialchars($user_phone); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bill-double-divider-preview"></div>
                    
                    <!-- Order Information -->
                    <div class="bill-row-preview">
                        <div class="bill-item-name-preview">Date</div>
                        <div class="bill-item-total-preview">${orderDate}</div>
                    </div>
                    <div class="bill-row-preview">
                        <div class="bill-item-name-preview">Time</div>
                        <div class="bill-item-total-preview">${orderTime}</div>
                    </div>
                    <div class="bill-row-preview">
                        <div class="bill-item-name-preview">Type</div>
                        <div class="bill-item-total-preview">${orderTypeDisplay}</div>
                    </div>
                    
                    <div class="bill-divider-preview"></div>
                    
                    <!-- Customer Information -->
                    <div class="bill-row-preview" style="font-weight: bold;">Customer Details</div>
                    <div class="bill-row-preview">
                        <div class="bill-item-name-preview">Name:</div>
                        <div class="bill-item-total-preview">${escapeHtml(customerName)}</div>
                    </div>
            `;
            
            if (customerPhone && customerPhone !== 'N/A') {
                billHtml += `
                    <div class="bill-row-preview">
                        <div class="bill-item-name-preview">Phone:</div>
                        <div class="bill-item-total-preview">${escapeHtml(customerPhone)}</div>
                    </div>
                `;
            }
            
            if (orderType === 'delivery' && deliveryAddress) {
                billHtml += `
                    <div class="bill-row-preview">
                        <div style="flex: 3; text-align: left; font-size: 10px;">
                            Address: ${escapeHtml(deliveryAddress)}
                        </div>
                    </div>
                `;
            }
            
            if (orderNotes) {
                billHtml += `
                    <div class="bill-row-preview">
                        <div style="flex: 3; text-align: left; font-size: 10px;">
                            Notes: ${escapeHtml(orderNotes)}
                        </div>
                    </div>
                `;
            }
            
            billHtml += `
                    <div class="bill-divider-preview"></div>
                    
                    <!-- Order Items -->
                    <div class="bill-row-preview" style="font-weight: bold; text-align: center;">ORDER ITEMS</div>
                    <div class="bill-double-divider-preview"></div>
                    
                    <!-- Header for items -->
                    <div class="bill-row-preview" style="font-weight: bold;">
                        <div class="bill-item-name-preview">Item</div>
                        <div class="bill-item-qty-preview">Qty</div>
                        <div class="bill-item-price-preview">Price</div>
                        <div class="bill-item-total-preview">Total</div>
                    </div>
                    <div class="bill-divider-preview"></div>
            `;
            
            // Add order items
            if (cartItems.length > 0) {
                cartItems.forEach(item => {
                    const itemTotal = (parseFloat(item.price) * parseInt(item.quantity));
                    billHtml += `
                    <div class="bill-row-preview">
                        <div class="bill-item-name-preview">${escapeHtml(item.name)}</div>
                        <div class="bill-item-qty-preview">${item.quantity}</div>
                        <div class="bill-item-price-preview">${formatCurrency(item.price)}</div>
                        <div class="bill-item-total-preview">${formatCurrency(itemTotal)}</div>
                    </div>
                    `;
                });
            }
            
            billHtml += `
                    <div class="bill-double-divider-preview"></div>
                    
                    <!-- Bill Summary -->
                    <div class="bill-row-preview" style="font-weight: bold; text-align: center;">BILL SUMMARY</div>
                    
                    <div class="bill-summary-row-preview">
                        <div class="bill-summary-label-preview">Subtotal:</div>
                        <div class="bill-summary-value-preview">₹${formatCurrency(subtotal)}</div>
                    </div>
            `;
            
            if (parseFloat(gstAmount) > 0) {
                billHtml += `
                    <div class="bill-summary-row-preview">
                        <div class="bill-summary-label-preview">GST (${gstRate}%):</div>
                        <div class="bill-summary-value-preview">₹${formatCurrency(gstAmount)}</div>
                    </div>
                `;
            }
            
            if (parseFloat(delivery) > 0) {
                billHtml += `
                    <div class="bill-summary-row-preview">
                        <div class="bill-summary-label-preview">Delivery Charge:</div>
                        <div class="bill-summary-value-preview">₹${formatCurrency(delivery)}</div>
                    </div>
                `;
            }
            
            billHtml += `
                    <div class="bill-double-divider-preview"></div>
                    
                    <div class="bill-summary-row-preview" style="font-weight: bold;">
                        <div class="bill-summary-label-preview">GRAND TOTAL:</div>
                        <div class="bill-summary-value-preview">₹${formatCurrency(totalAmount)}</div>
                    </div>
                    
                    <div class="bill-double-divider-preview"></div>
                    
                    <!-- Footer -->
                    <div class="bill-footer-preview">
                        <div>Thank you for your order!</div>
                        <div>Visit again</div>
                        <div style="margin-top: 3px;">
                            ${now.toLocaleDateString('en-IN')} ${now.toLocaleTimeString('en-IN', { 
                                hour: '2-digit', 
                                minute: '2-digit',
                                hour12: true 
                            })}
                        </div>
                    </div>
                </div>
            `;
            
            return billHtml;
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Show KOT preview modal
        $('#previewKOT').click(function() {
            if (cartItems.length === 0) {
                showToast('Please add items to the cart before printing KOT.', 'warning');
                return;
            }
            
            // Generate KOT HTML
            const kotHTML = generateKOTHTML();
            $('#kotContent').html(kotHTML);
            
            // Show the modal
            const kotModal = new bootstrap.Modal(document.getElementById('kotPreviewModal'));
            kotModal.show();
        });
        
        // Show Bill preview modal
        $('#previewBill').click(function() {
            if (cartItems.length === 0) {
                showToast('Please add items to the cart before printing Bill.', 'warning');
                return;
            }
            
            // Generate Bill HTML
            const billHTML = generateBillHTML();
            $('#billContent').html(billHTML);
            
            // Show the modal
            const billModal = new bootstrap.Modal(document.getElementById('billPreviewModal'));
            billModal.show();
        });
        
        // Print KOT
        window.printKOT = function() {
            const kotContent = document.getElementById('kotContent');
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank', 'width=65mm,height=600,scrollbars=no,toolbar=no,location=no');
            
            if (!printWindow) {
                showToast('Please allow popups for printing', 'warning');
                return;
            }
            
            // Write the KOT content to the new window with proper thermal printer styling
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>KOT</title>
                    <style>
                        @page {
                            margin: 0;
                            padding: 0;
                            size: 65mm auto;
                        }
                        body {
                            margin: 0;
                            padding: 5px;
                            font-family: 'Arial';
                            font-size: 12px;
                            line-height: 1.2;
                            width: 65mm;
                            background: white;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                            color: #000 !important;
                        }
                        .kot-container-preview {
                            width: 65mm;
                            max-width: 65mm;
                            font-family: 'Arial';
                            font-size: 12px;
                            line-height: 1.2;
                            background: white;
                            padding: 0;
                            margin: 0 auto;
                            color: #000 !important;
                        }
                        .kot-header-preview {
                            text-align: center;
                            margin-bottom: 5px;
                            color: #000 !important;
                        }
                        .kot-header-preview .business-name {
                            font-weight: bold;
                            font-size: 14px;
                            margin-bottom: 2px;
                            color: #000 !important;
                        }
                        .kot-header-preview .kot-title {
                            font-weight: bold;
                            font-size: 16px;
                            margin-bottom: 3px;
                            color: #000 !important;
                            text-transform: uppercase;
                        }
                        .kot-divider-preview {
                            border-bottom: 1px solid #000;
                            margin: 3px 0;
                        }
                        .kot-double-divider-preview {
                            border-bottom: 2px solid #000;
                            margin: 3px 0;
                        }
                        .kot-row-preview {
                            display: flex;
                            justify-content: space-between;
                            margin: 1px 0;
                            color: #000 !important;
                        }
                        .kot-item-name-preview {
                            flex: 2;
                            text-align: left;
                            font-size: 11px;
                            color: #000 !important;
                        }
                        .kot-item-qty-preview {
                            flex: 1;
                            text-align: center;
                            color: #000 !important;
                            font-weight: bold;
                        }
                        .kot-item-special-preview {
                            flex: 3;
                            text-align: left;
                            font-size: 10px;
                            font-style: italic;
                            color: #000 !important;
                            margin-top: -2px;
                        }
                        .kot-footer-preview {
                            margin-top: 5px;
                            font-size: 10px;
                            text-align: center;
                            color: #000 !important;
                        }
                        @media print {
                            body {
                                margin: 0;
                                padding: 5px;
                                width: 65mm;
                                color: #000 !important;
                            }
                            * {
                                color: #000 !important;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${kotContent.innerHTML}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // Wait for content to load then trigger print
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.print();
                }, 500);
            };
            
            // Fallback: if onload doesn't fire, try printing after a delay
            setTimeout(() => {
                if (!printWindow.closed) {
                    printWindow.print();
                }
            }, 1000);
            
            showToast('KOT sent to kitchen printer!', 'success');
        };
        
        // Print Bill
        window.printBill = function() {
            const billContent = document.getElementById('billContent');
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank', 'width=65mm,height=600,scrollbars=no,toolbar=no,location=no');
            
            if (!printWindow) {
                showToast('Please allow popups for printing', 'warning');
                return;
            }
            
            // Write the bill content to the new window with proper thermal printer styling
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Bill</title>
                    <style>
                        @page {
                            margin: 0;
                            padding: 0;
                            size: 65mm auto;
                        }
                        body {
                            margin: 0;
                            padding: 5px;
                            font-family: 'Arial';
                            font-size: 12px;
                            line-height: 1.2;
                            width: 65mm;
                            background: white;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        .bill-container-preview {
                            width: 65mm;
                            max-width: 65mm;
                            font-family: 'Arial';
                            font-size: 12px;
                            line-height: 1.2;
                            background: white;
                            padding: 0;
                            margin: 0 auto;
                        }
                        .bill-header-preview {
                            text-align: center;
                            margin-bottom: 5px;
                        }
                        .bill-header-preview .business-name {
                            font-weight: bold;
                            font-size: 14px;
                            margin-bottom: 2px;
                        }
                        .bill-header-preview .business-address {
                            font-size: 10px;
                            margin-bottom: 2px;
                        }
                        .bill-header-preview .business-phone {
                            font-size: 10px;
                            margin-bottom: 3px;
                        }
                        .bill-divider-preview {
                            border-bottom: 1px solid #000;
                            margin: 3px 0;
                        }
                        .bill-double-divider-preview {
                            border-bottom: 2px solid #000;
                            margin: 3px 0;
                        }
                        .bill-row-preview {
                            display: flex;
                            justify-content: space-between;
                            margin: 1px 0;
                        }
                        .bill-item-name-preview {
                            flex: 2;
                            text-align: left;
                            font-size: 11px;
                        }
                        .bill-item-qty-preview {
                            flex: 1;
                            text-align: center;
                        }
                        .bill-item-price-preview {
                            flex: 1;
                            text-align: right;
                        }
                        .bill-item-total-preview {
                            flex: 1;
                            text-align: right;
                        }
                        .bill-summary-row-preview {
                            display: flex;
                            justify-content: space-between;
                            margin: 1px 0;
                        }
                        .bill-summary-label-preview {
                            flex: 2;
                            text-align: left;
                        }
                        .bill-summary-value-preview {
                            flex: 1;
                            text-align: right;
                        }
                        .bill-footer-preview {
                            margin-top: 5px;
                            font-size: 10px;
                            text-align: center;
                        }
                        @media print {
                            body {
                                margin: 0;
                                padding: 5px;
                                width: 65mm;
                            }
                        }
                    </style>
                </head>
                <body>
                    ${billContent.innerHTML}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // Wait for content to load then trigger print
            printWindow.onload = function() {
                setTimeout(() => {
                    printWindow.print();
                }, 500);
            };
            
            // Fallback: if onload doesn't fire, try printing after a delay
            setTimeout(() => {
                if (!printWindow.closed) {
                    printWindow.print();
                }
            }, 1000);
            
            showToast('Bill sent to printer!', 'success');
        };
        
        // Update form inputs before submission
        $('#billForm').submit(function(e) {
            e.preventDefault();
            
            // Validate form
            if (!validateForm()) {
                return false;
            }
            
            // Update hidden form fields
            $('#customer_name_input').val($('#customer_name').val().trim());
            $('#customer_phone_input').val($('#customer_phone').val().trim());
            $('#table_number_input').val($('#table_number').val());
            $('#delivery_address_input').val($('#delivery_address').val().trim());
            $('#order_notes_input').val($('#order_notes').val().trim());
            
            // Update club with existing value
            if ($('#club_with_existing').is(':checked')) {
                $('#club_with_existing_input').val('1');
            } else {
                $('#club_with_existing_input').val('0');
            }
            
            // Submit form programmatically
            this.submit();
        });
        
        // Initialize with dining order type
        reorderCustomerFields('dining');
        
        // Hide delivery charge row initially
        $('#deliveryChargeRow').hide();
        
        // Initialize Bootstrap modals
        const kotPreviewModal = new bootstrap.Modal(document.getElementById('kotPreviewModal'), {});
        const billPreviewModal = new bootstrap.Modal(document.getElementById('billPreviewModal'), {});
    });
    // End: Main JavaScript Logic
    </script>
</body>
</html>
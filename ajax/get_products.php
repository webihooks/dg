<?php
// ajax/get_products.php
session_start();
require '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Function to get correct image URL from relative path
function getImageUrl($image_path) {
    if (empty($image_path)) {
        return null;
    }
    
    // If it's already a full URL, return as is
    if (filter_var($image_path, FILTER_VALIDATE_URL)) {
        return $image_path;
    }
    
    // Check if it's a relative path
    if (strpos($image_path, '/') !== 0) {
        // It's a relative path, prepend with base URL
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        
        // Remove any "../" from the path for security
        $clean_path = str_replace('../', '', $image_path);
        
        // If path doesn't start with uploads/, add it
        if (strpos($clean_path, 'uploads/') !== 0) {
            $clean_path = 'uploads/' . $clean_path;
        }
        
        // Return full URL
        return $base_url . '/' . ltrim($clean_path, '/');
    }
    
    // It's an absolute path, check if file exists
    if (file_exists($image_path)) {
        // Convert to URL if it's within document root
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        if (strpos($image_path, $doc_root) === 0) {
            $relative_path = str_replace($doc_root, '', $image_path);
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            return $base_url . $relative_path;
        }
        
        // If outside document root, use base64 encoding
        $mime_type = mime_content_type($image_path);
        $image_data = base64_encode(file_get_contents($image_path));
        return 'data:' . $mime_type . ';base64,' . $image_data;
    }
    
    return null;
}

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'get_products_list') {
        // Get product list with images
        $products_table = "products_" . $user_id;
        
        // Check if table exists
        $check_table = $conn->query("SHOW TABLES LIKE '$products_table'");
        
        if ($check_table->num_rows > 0) {
            $sql = "SELECT id, product_name, price, image_path FROM $products_table WHERE is_active = 1 ORDER BY product_name";
            $result = $conn->query($sql);
            
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $image_url = getImageUrl($row['image_path']);
                
                $products[] = [
                    'id' => (int)$row['id'],
                    'product_name' => $row['product_name'],
                    'price' => (float)$row['price'],
                    'image_url' => $image_url
                ];
            }
            
            echo json_encode(['success' => true, 'products' => $products]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Products table not found']);
        }
    }
    elseif ($_POST['action'] === 'get_product_image' && isset($_POST['product_id'])) {
        // Get image for a specific product
        $product_id = (int)$_POST['product_id'];
        $products_table = "products_" . $user_id;
        
        $sql = "SELECT image_path FROM $products_table WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->bind_result($image_path);
        $stmt->fetch();
        $stmt->close();
        
        $image_url = getImageUrl($image_path);
        
        if ($image_url) {
            echo json_encode(['success' => true, 'image_url' => $image_url]);
        } else {
            echo json_encode(['success' => false, 'image_url' => null]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>
<?php
// save_customer_data.php - Save customer data to database

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

$response = ['success' => false, 'message' => ''];

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'User not logged in';
        echo json_encode($response);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get POST data
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_phone = $_POST['customer_phone'] ?? '';
    
    // Validate input
    if (empty($customer_name) || empty($customer_phone)) {
        $response['message'] = 'Customer name and phone are required';
        echo json_encode($response);
        exit;
    }
    
    // Validate phone number (10 digits)
    if (!preg_match('/^\d{10}$/', $customer_phone)) {
        $response['message'] = 'Phone number must be 10 digits';
        echo json_encode($response);
        exit;
    }
    
    // Check if customer already exists for this user
    $check_stmt = $conn->prepare("SELECT id FROM customer_data WHERE user_id = :user_id AND customer_phone = :phone");
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->bindParam(':phone', $customer_phone);
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        // Update existing customer
        $update_stmt = $conn->prepare("UPDATE customer_data SET customer_name = :name, updated_at = NOW() WHERE user_id = :user_id AND customer_phone = :phone");
        $update_stmt->bindParam(':name', $customer_name);
        $update_stmt->bindParam(':user_id', $user_id);
        $update_stmt->bindParam(':phone', $customer_phone);
        
        if ($update_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Customer data updated successfully';
        } else {
            $response['message'] = 'Failed to update customer data';
        }
    } else {
        // Insert new customer
        $insert_stmt = $conn->prepare("INSERT INTO customer_data (user_id, customer_name, customer_phone, created_at, updated_at) 
                                      VALUES (:user_id, :name, :phone, NOW(), NOW())");
        $insert_stmt->bindParam(':user_id', $user_id);
        $insert_stmt->bindParam(':name', $customer_name);
        $insert_stmt->bindParam(':phone', $customer_phone);
        
        if ($insert_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Customer data saved successfully';
            $response['customer_id'] = $conn->lastInsertId();
        } else {
            $response['message'] = 'Failed to save customer data';
        }
    }
    
} catch (PDOException $e) {
    error_log("Save Customer Data Error: " . $e->getMessage());
    $response['message'] = 'Database error occurred';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
<?php
// test_db.php
require 'db_connection.php';

echo "<h2>Database Connection Test</h2>";
echo "Connection: " . ($conn->connect_error ? "FAILED: " . $conn->connect_error : "SUCCESS") . "<br><br>";

// Check if orders table exists
$result = $conn->query("SHOW TABLES LIKE 'orders'");
echo "Orders table exists: " . ($result->num_rows > 0 ? "YES" : "NO") . "<br>";

// Check orders table structure
if ($result->num_rows > 0) {
    echo "<h3>Orders Table Structure:</h3>";
    $structure = $conn->query("DESCRIBE orders");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show recent orders
    echo "<h3>Recent Orders (last 5):</h3>";
    $orders = $conn->query("SELECT * FROM orders ORDER BY order_id DESC LIMIT 5");
    if ($orders->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Customer</th><th>Type</th><th>Table</th><th>Total</th><th>Status</th><th>Created</th></tr>";
        while ($order = $orders->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $order['order_id'] . "</td>";
            echo "<td>" . $order['customer_name'] . "</td>";
            echo "<td>" . $order['order_type'] . "</td>";
            echo "<td>" . $order['table_number'] . "</td>";
            echo "<td>" . $order['total_amount'] . "</td>";
            echo "<td>" . $order['status'] . "</td>";
            echo "<td>" . $order['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No orders found in the table.<br>";
    }
}

// Check order_items table
$result = $conn->query("SHOW TABLES LIKE 'order_items'");
echo "<br>Order_items table exists: " . ($result->num_rows > 0 ? "YES" : "NO") . "<br>";

// Test insert
echo "<h3>Test Insert:</h3>";
$test_sql = "INSERT INTO orders (user_id, customer_name, customer_phone, order_type, table_number, subtotal, total_amount, status) 
             VALUES (1, 'Test Customer', '9876543210', 'dining', '1', 100.00, 118.00, 'Pending')";
if ($conn->query($test_sql)) {
    echo "Test insert SUCCESSFUL. Insert ID: " . $conn->insert_id . "<br>";
} else {
    echo "Test insert FAILED: " . $conn->error . "<br>";
}

$conn->close();
?>
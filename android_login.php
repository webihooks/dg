<?php
// Start the session
session_start();

//require("db_connection.php");
// Database connection details
$host = 'localhost'; // Replace with your database host
$dbname = 'doctorie_webihooks_card'; // Replace with your database name
$username = 'doctorie_webihooks'; // Replace with your database username
$password = 'S@g@r4834'; // Replace with your database password

// Connect to the database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $email = htmlspecialchars($_GET['email']);
    $password = $_GET['password'];


        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['Password'])) {
            // Login successful
            // Store user data in the session
            $_OUTPUT['user_id'] = $user['id'];
            $_OUTPUT['role'] = $user['role']; 
            $_OUTPUT['message'] = "";
            $_OUTPUT['page'] = "";
            $_OUTPUT['status'] = "";
            
            // Check if trial has ended
            if (isset($user['trial_end']) && strtotime($user['trial_end']) < time()) {
                // Trial has ended, redirect to subscription page
                $_OUTPUT['message'] = "Trial has ended";
                $_OUTPUT['page'] = "subscription.php";
                $_OUTPUT['status'] = "Success";
            }
            
            // Redirect based on user role
            if ($user['role'] === 'admin') {
                $_OUTPUT['Status'] = "Success";
                $_OUTPUT['page'] = "admin-dashboard.php";
                $_OUTPUT['message'] = "Login Successful!";
            } elseif ($user['role'] === 'sales_person') {
                $_OUTPUT['Status'] = "Success";
                $_OUTPUT['page'] = "sales-dashboard.php";
                $_OUTPUT['message'] = "Login Successful!";
                header("Location: sales-dashboard.php");
            } else {
                $_OUTPUT['Status'] = "Success";
                $_OUTPUT['page'] = "subscription.php";
                $_OUTPUT['message'] = "Please Subscribe!";
            }
        } else {
                $_OUTPUT['Status'] = "Fail";
                $_OUTPUT['page'] = "subscription.php";
                $_OUTPUT['message'] = "Invalid email or password.";
        }
   
   echo json_encode($_OUTPUT);
   
}
?>
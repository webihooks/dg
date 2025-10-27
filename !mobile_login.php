<?php
// Start the session
session_start();

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'];

    // Fetch user from the database
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['Password'])) {
            // Login successful
            // Store user data in the session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role']; // Assuming you have a 'role' column in your users table
            $_SESSION['android_logout_button'] = '1';
            // Check if trial has ended
            if (isset($user['trial_end']) && strtotime($user['trial_end']) < time()) {
                // Trial has ended, redirect to subscription page
                header("Location: subscription.php");
                exit();
            }
            
            // Redirect based on user role
            if ($user['role'] === 'admin') {
                header("Location: admin-dashboard.php");
            } elseif ($user['role'] === 'sales_person') {
                header("Location: sales-dashboard.php");
            } else {
                header("Location: subscription.php");
            }
            exit();
        } else {
            // Login failed
            echo "<script>alert('Invalid email or password.');</script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
     <!-- Title Meta -->
     <meta charset="utf-8" />
     <title>Login</title>
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <meta name="description" content="A fully responsive premium admin dashboard template" />
     <meta name="author" content="Techzaa" />
     <meta http-equiv="X-UA-Compatible" content="IE=edge" />

     <!-- App favicon -->
     <link rel="shortcut icon" href="assets/images/favicon.ico">

     <!-- Vendor css (Require in all Page) -->
     <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />

     <!-- Icons css (Require in all Page) -->
     <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />

     <!-- App css (Require in all Page) -->
     <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

     <!-- Theme Config js (Require in all Page) -->
     <script src="assets/js/config.js"></script>


<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-81W5S4MMGY"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-81W5S4MMGY');
</script>
     
</head>

<body class="h-100">
    <!-- Loading overlay -->
<div id="loading-screen" style="
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background-color: rgba(255, 255, 255);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Auto logging in, please wait...</p>
</div>

     <div class="d-flex flex-column h-100 p-3">
          <div class="d-flex flex-column flex-grow-1">
               <div class="row h-100">
                    <div class="col-xxl-7">
                         <div class="row justify-content-center h-100">
                              <div class="col-lg-6 py-lg-5">
                                   <div class="d-flex flex-column h-100 justify-content-center">
                                        <div class="auth-logo mb-4">
                                             <a href="index.php" class="logo-dark">
                                                  <img src="assets/images/logo-dark.png" height="60" alt="logo dark">
                                             </a>

                                             <a href="index.php" class="logo-light">
                                                  <img src="assets/images/logo-light.png" height="60" alt="logo light">
                                             </a>
                                        </div>

                                        <h2 class="fw-bold fs-24">Sign In</h2>

                                        <p class="text-muted mt-1 mb-4">Enter your email address and password to access admin panel.</p>

                                        <div class="mb-5">
                                             <form action="" method="POST" class="authentication-form">
                                                  <div class="mb-3">
                                                       <label class="form-label" for="example-email">Email</label>
                                                       <input type="email" id="example-email" name="email" class="form-control" placeholder="Enter your email" required>
                                                  </div>
                                                  <div class="mb-3">
                                                       <a href="forgot-password.php" class="float-end text-muted text-unline-dashed ms-1">Reset password</a>
                                                       <label class="form-label" for="example-password">Password</label>
                                                       <input type="password" id="example-password" name="password" class="form-control" placeholder="Enter your password" required>
                                                  </div>
                                                  <div class="mb-3">
                                                       <div class="form-check">
                                                            <input type="checkbox" class="form-check-input" id="checkbox-signin">
                                                            <label class="form-check-label" for="checkbox-signin">Remember me</label>
                                                       </div>
                                                  </div>

                                                  <div class="mb-1 text-center d-grid">
                                                       <button class="btn btn-soft-primary" type="submit">Sign In</button>
                                                  </div>
                                             </form>
                                        </div>

                                        <p class="text-danger text-center">Don't have an account? <a href="register.php" class="text-dark fw-bold ms-1">Sign Up</a></p>
                                   </div>
                              </div>
                         </div>
                    </div>

                    <div class="col-xxl-5 d-none d-xxl-flex">
                         <div class="card h-100 mb-0 overflow-hidden">
                              <div class="d-flex flex-column h-100">
                                   <img src="assets/images/small/img-10.jpg" alt="" class="w-100 h-100">
                              </div>
                         </div>
                    </div>
               </div>
          </div>
     </div>

     <!-- Vendor Javascript (Require in all Page) -->
     <script src="assets/js/vendor.js"></script>

     <!-- App Javascript (Require in all Page) -->
     <script src="assets/js/app.js"></script>
        <script>
        // Read URL params
        const urlParams = new URLSearchParams(window.location.search);
        const email = urlParams.get('email');
        const password = urlParams.get('password');
        
        // Fill form fields if values exist
        if (email && password) {
            document.getElementById('example-email').value = email;
            document.getElementById('example-password').value = password;
        
            // Wait 5 seconds, then submit
            setTimeout(() => {
                document.querySelector('.authentication-form').submit();
            }, 2000);
        }
        </script>

</body>

</html>
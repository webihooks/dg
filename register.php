<?php
// Database connection details
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'root';
$password = '';

// Connect to the database
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = htmlspecialchars($_POST['phone']);
    $address = htmlspecialchars($_POST['address']);
    $role = 'user';
    
    // Calculate trial dates
    $trialStart = date('Y-m-d H:i:s');
    $trialEnd = date('Y-m-d H:i:s', strtotime('+7 days'));
    $isTrial = 1; // 1 for true, user is in trial

    // Begin transaction for atomic operations
    $conn->beginTransaction();
    
    try {
        // Insert user data into the users table
        $stmt = $conn->prepare("INSERT INTO users (Name, Email, Password, Phone, Address, Role, is_trial, trial_start, trial_end) 
                               VALUES (:name, :email, :password, :phone, :address, :role, :is_trial, :trial_start, :trial_end)");
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':is_trial', $isTrial);
        $stmt->bindParam(':trial_start', $trialStart);
        $stmt->bindParam(':trial_end', $trialEnd);
        
        $stmt->execute();
        
        // Get the last inserted user ID
        $userId = $conn->lastInsertId();
        
        // Insert trial record into trial_subscriptions table
        $stmtTrial = $conn->prepare("INSERT INTO trial_subscriptions 
                                    (user_id, start_date, end_date, is_active) 
                                    VALUES (:user_id, :start_date, :end_date, 1)");
        
        $stmtTrial->bindParam(':user_id', $userId);
        $stmtTrial->bindParam(':start_date', $trialStart);
        $stmtTrial->bindParam(':end_date', $trialEnd);
        $stmtTrial->execute();
        
        // Commit the transaction
        $conn->commit();
        
        echo "<script>alert('Registration successful! You have a 7-day free trial.'); window.location.href='login.php';</script>";
    } catch (PDOException $e) {
        // Roll back the transaction if something failed
        $conn->rollBack();
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
     <!-- Title Meta -->
     <meta charset="utf-8" />
     <title>Register</title>
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

<!-- Google tag (gtag.js) event -->
<script>
  gtag('event', 'conversion_event_signup', {
    // <event_parameters>
  });
</script>


</head>

<body class="h-100">
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

                                        <h2 class="fw-bold fs-24">Sign Up</h2>

                                        <p class="text-muted mt-1 mb-1">New to our platform? Sign up now! It only takes a minute</p>
                                        <div class="alert alert-info mb-2">
                                            <strong>7-Day Free Trial!</strong> Enjoy full access for 7 days with no commitment.
                                        </div>

                                        <div>
                                             <form action="" method="POST" class="authentication-form">
                                                  <div class="mb-1">
                                                       <label class="form-label" for="example-name">Name</label>
                                                       <input type="text" id="example-name" name="name" class="form-control" placeholder="Enter your name" required>
                                                  </div>
                                                  <div class="mb-1">
                                                       <label class="form-label" for="example-email">Email</label>
                                                       <input type="email" id="example-email" name="email" class="form-control" placeholder="Enter your email" required>
                                                  </div>
                                                  <div class="mb-1">                                                      
                                                       <label class="form-label" for="example-password">Password</label>
                                                       <input type="password" id="example-password" name="password" class="form-control" placeholder="Enter your password" required>
                                                  </div>
                                                  <div class="mb-1">
                                                       <label class="form-label" for="example-phone">Phone Number</label>
                                                       <input type="tel" id="example-phone" name="phone" class="form-control" placeholder="Enter your phone number" required>
                                                  </div>
                                                  <div class="mb-1">
                                                       <label class="form-label" for="example-address">Address</label>
                                                       <textarea id="example-address" name="address" class="form-control" placeholder="Enter your address" required></textarea>
                                                  </div>
                                                  <div class="mb-3">
                                                       <div class="form-check">
                                                            <input type="checkbox" class="form-check-input" id="checkbox-signin" required>
                                                            <label class="form-check-label" for="checkbox-signin">I accept Terms and Conditions and agree to the 7-day trial</label>
                                                       </div>
                                                  </div>

                                                  <div class="mb-1 text-center d-grid">
                                                       <button class="btn btn-soft-primary" type="submit">Start Free Trial</button>
                                                  </div>
                                             </form>
                                        </div>

                                        <p class="mt-auto text-danger text-center">I already have an account  <a href="login.php" class="text-dark fw-bold ms-1">Sign In</a></p>
                                   </div>
                              </div>
                         </div>
                    </div>

                    <div class="col-xxl-5 d-none d-xxl-flex">
                         <div class="card h-100 mb-0 overflow-hidden">
                              <div class="d-flex flex-column h-100">
                                   <img src="assets/images/small/img-10.jpg" alt="" class="w-100 h-100">
                              </div>
                         </div> <!-- end card -->
                    </div>
               </div>
          </div>
     </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

</body>

</html>
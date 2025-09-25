<?php
// reset-password.php
session_start();

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

$message = '';
$error = '';
$validToken = false;

// Check if token is provided
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token
    try {
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = :token AND expires > NOW()");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resetRequest) {
            $validToken = true;
            $email = $resetRequest['email'];
            
            // Handle password reset form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'];
                $confirmPassword = $_POST['confirm_password'];
                
                if ($password === $confirmPassword) {
                    // Hash the new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update user's password
                    $stmt = $conn->prepare("UPDATE users SET Password = :password WHERE Email = :email");
                    $stmt->bindParam(':password', $hashedPassword);
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();
                    
                    // Delete the used token
                    $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = :token");
                    $stmt->bindParam(':token', $token);
                    $stmt->execute();
                    
                    $message = "Password reset successfully. You can now <a href='login.php'>login</a> with your new password.";
                    $validToken = false; // Token has been used
                } else {
                    $error = "Passwords do not match.";
                }
            }
        } else {
            $error = "Invalid or expired reset token.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
} else {
    $error = "No reset token provided.";
}
?>

<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
     <!-- Title Meta -->
     <meta charset="utf-8" />
     <title>Reset Password</title>
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

                                        <h2 class="fw-bold fs-24">Reset Password</h2>

                                        <?php if ($message): ?>
                                            <div class="alert alert-success"><?php echo $message; ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($error): ?>
                                            <div class="alert alert-danger"><?php echo $error; ?></div>
                                        <?php endif; ?>

                                        <?php if ($validToken): ?>
                                        <p class="text-muted mt-1 mb-4">Please enter your new password.</p>
                                        <div>
                                             <form action="" method="POST" class="authentication-form">
                                                  <div class="mb-3">
                                                       <label class="form-label" for="password">New Password</label>
                                                       <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password" required>
                                                  </div>
                                                  <div class="mb-3">
                                                       <label class="form-label" for="confirm_password">Confirm Password</label>
                                                       <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                                                  </div>
                                                  <div class="mb-1 text-center d-grid">
                                                       <button class="btn btn-primary" type="submit">Reset Password</button>
                                                  </div>
                                             </form>
                                        </div>
                                        <?php endif; ?>

                                        <p class="mt-5 text-danger text-center">Back to <a href="login.php" class="text-dark fw-bold ms-1">Sign In</a></p>
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

     <!-- Vendor Javascript (Require in all Page) -->
     <script src="assets/js/vendor.js"></script>

     <!-- App Javascript (Require in all Page) -->
     <script src="assets/js/app.js"></script>

</body>

</html>
<?php
// In your server configuration or PHP file
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
// Start the session with extended lifetime
session_start();

// Set session to expire in 1 year (365 days)
ini_set('session.gc_maxlifetime', 31536000); // 365 days in seconds

// Set session cookie parameters for 1 year
session_set_cookie_params([
    'lifetime' => 31536000,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']), // Auto-detect HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

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

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin-dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'sales_person') {
        header("Location: sales-dashboard.php");
        exit();
    } else {
        header("Location: subscription.php");
        exit();
    }
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
            // Login successful - Regenerate session ID for security
            session_regenerate_id(true);
            
            // Store user data in the session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['login_time'] = time();
            
            // Set a persistent cookie to help maintain session
            setcookie('remember_me', $user['id'], time() + 31536000, '/', $_SERVER['HTTP_HOST'], isset($_SERVER['HTTPS']), true);
            
            // Check if "Remember me" was checked
            if (isset($_POST['remember_me'])) {
                // Create remember token for persistent login
                $remember_token = bin2hex(random_bytes(32));
                $expires = time() + 31536000; // 1 year
                
                // Store token in database
                $stmt = $conn->prepare("UPDATE users SET remember_token = :token, token_expires = :expires WHERE id = :id");
                $stmt->bindParam(':token', $remember_token);
                $stmt->bindParam(':expires', $expires);
                $stmt->bindParam(':id', $user['id']);
                $stmt->execute();
                
                // Set persistent cookie
                setcookie('remember_token', $remember_token, $expires, '/', $_SERVER['HTTP_HOST'], isset($_SERVER['HTTPS']), true);
            }
            
            // Check if trial has ended
            if (isset($user['trial_end']) && strtotime($user['trial_end']) < time()) {
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
     <meta name="description" content="Deegeecard Login Page" />
     <meta name="author" content="" />
     <meta http-equiv="X-UA-Compatible" content="IE=edge" />


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
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">


<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-81W5S4MMGY"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-81W5S4MMGY');
</script>

<!-- ADD SOLUTION 2 SCRIPT HERE -->
<script>
// Enhanced session management for Chrome desktop app
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in a Chrome desktop app environment
    const isChromeDesktopApp = /Chrome/.test(navigator.userAgent) && !/Edge/.test(navigator.userAgent);
    
    if (isChromeDesktopApp) {
        console.log('Chrome desktop app detected - enabling enhanced session management');
        
        // Periodically ping server to keep session alive
        setInterval(() => {
            fetch('session-keepalive.php', {
                method: 'HEAD',
                credentials: 'include' // Important: include cookies
            }).then(response => {
                console.log('Session keep-alive ping successful');
            }).catch(err => {
                console.log('Keep-alive request failed:', err);
            });
        }, 300000); // Ping every 5 minutes
        
        // Store session info in localStorage as backup
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('lastActivity', Date.now());
            console.log('Session backup stored in localStorage');
        }
    }
});

// Handle beforeunload to preserve session
window.addEventListener('beforeunload', function() {
    if (typeof(Storage) !== "undefined") {
        localStorage.setItem('sessionPreserved', 'true');
        localStorage.setItem('preserveTime', Date.now());
    }
});
</script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-81W5S4MMGY');
</script>

<style>
.bi-android2 {
     font-size: 25px; margin-right: 10px;
}
.download_btn {
     line-height: 22px;
}
</style>
     
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

                                        <h2 class="fw-bold fs-24">Sign In</h2>

                                        <p class="text-muted mt-1 mb-2">Enter your email address and password to access admin panel.</p>

                                        <div class="mb-2">
                                             <form action="" method="POST" class="authentication-form">
                                                  <div class="mb-2">
                                                       <label class="form-label" for="example-email">Email</label>
                                                       <input type="email" id="example-email" name="email" class="form-control" placeholder="Enter your email" required>
                                                  </div>
                                                  <div class="mb-2">
                                                       <!-- <a href="forgot-password.php" class="float-end text-muted text-unline-dashed ms-1">Reset password</a> -->
                                                       <label class="form-label" for="example-password">Password</label>
                                                       <input type="password" id="example-password" name="password" class="form-control" placeholder="Enter your password" required>
                                                  </div>
                                                  <div class="mb-2">
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


                                        <!-- Download Our Partner Android App Section -->
                                        <div class="mt-1 border-top pt-4 mb-5">
                                            <h5 class="text-center mb-3">Download Our Partner App</h5>
                                            <div class="row g-2">

                                               <div class="col-6">
                                                    <a href="downloads/Deegeecard-Partner-App.apk" class="download_btn btn btn-outline-success w-100" download>
                                                        <i class="bi bi-android2"></i> DeeGeeCard Partner App
                                                    </a>
                                                </div>

                                                <div class="col-6">
                                                    <a href="downloads/Deegeecard-Partner-Dining-App.apk" class="download_btn btn btn-outline-primary w-100" download>
                                                        <i class="bi bi-android2"></i> DeeGeeCard Partner App - Dining
                                                    </a>
                                                </div>
                                                
                                            </div>
                                        </div>
                                        <!-- Download Our Partner Android App Section -->

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
// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(error) {
                console.log('ServiceWorker registration failed: ', error);
            });
    });
}

// Handle Add to Home Screen prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show install button (optional)
    showInstallPrompt();
});

function showInstallPrompt() {
    // Your custom install button logic
    console.log('App can be installed');
}
</script>

</body>

</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ROBUST UNIVERSAL SESSION CONFIGURATION - 365 DAYS
session_set_cookie_params([
    'lifetime' => 31536000, // 1 year
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'None' // Essential for Android apps
]);

// Server-side session configuration
ini_set('session.gc_maxlifetime', 31536000); // 1 year
ini_set('session.cookie_lifetime', 31536000); // 1 year
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 1 : 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'None');

// Prevent session ID regeneration on every request
ini_set('session.use_strict_mode', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ANDROID SESSION MANAGER
class AndroidSessionManager {
    private $conn;
    
    public function __construct() {
        $this->initDB();
    }
    
    private function initDB() {
        $host = 'localhost';
        $dbname = 'doctorie_webihooks_card';
        $username = 'doctorie_webihooks';
        $password = 'S@g@r4834';
        
        try {
            $this->conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("AndroidSessionManager DB Error: " . $e->getMessage());
        }
    }
    
    public function isAndroidApp() {
        return strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false || 
               isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'com.webtonative.app' ||
               isset($_SESSION['is_android_app']);
    }
    
    public function maintainAndroidSession($userId) {
        if (!$this->isAndroidApp()) return false;
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['is_android_app'] = true;
        $_SESSION['android_last_activity'] = time();
        $_SESSION['session_expires'] = time() + 31536000;
        $_SESSION['android_session_created'] = time();
        
        // Update session cookie
        setcookie(session_name(), session_id(), [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
        
        error_log("✅ Android session maintained for user: $userId");
        return true;
    }
    
    public function validateAndroidSession() {
        if (!$this->isAndroidApp()) return true;
        
        // For Android apps, always maintain session if user_id exists
        if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
            $this->maintainAndroidSession($_SESSION['user_id']);
            return true;
        }
        
        return false;
    }
    
    public function getDebugInfo() {
        return [
            'is_android_app' => $this->isAndroidApp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Not set',
            'session_user_id' => $_SESSION['user_id'] ?? 'Not set',
            'session_android' => $_SESSION['is_android_app'] ?? 'Not set',
            'cookies_received' => isset($_COOKIE[session_name()]) ? 'Yes' : 'No',
            'session_id' => session_id(),
            'timestamp' => time()
        ];
    }
}

// Initialize Android Session Manager
$androidSessionManager = new AndroidSessionManager();

// ANDROID APP DETECTION AND DEBUGGING
$isAndroidApp = $androidSessionManager->isAndroidApp();
$androidDebugInfo = $androidSessionManager->getDebugInfo();

// Store Android-specific session data
if ($isAndroidApp) {
    $_SESSION['is_android_app'] = true;
    $_SESSION['app_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $_SESSION['android_debug_info'] = $androidDebugInfo;
    $_SESSION['last_android_access'] = time();
}

// PREVENT INFINITE REDIRECT - Check if this is a redirect from dashboard
$isRedirectFromDashboard = isset($_GET['redirect']) && $_GET['redirect'] === 'true';
$forceLoginPage = isset($_GET['force_login']) && $_GET['force_login'] === 'true';

// UNIVERSAL SESSION VALIDATION WITH 365-DAY PERSISTENCE
if (isset($_SESSION['user_id']) && !$forceLoginPage) {
    // Update session lifetime
    $_SESSION['last_activity'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $_SESSION['session_expires'] = time() + 31536000; // 1 year from now
    
    // Android-specific session maintenance
    if ($isAndroidApp) {
        $androidSessionManager->maintainAndroidSession($_SESSION['user_id']);
    }
    
    // Update session cookie with extended lifetime
    setcookie(session_name(), session_id(), [
        'expires' => time() + 31536000,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'None'
    ]);
    
    // Redirect based on user role - ONLY if not already on login page
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page === 'login.php') {
        $role = $_SESSION['role'] ?? '';
        switch ($role) {
            case 'admin':
                header("Location: admin-dashboard.php");
                exit();
            case 'sales_person':
                header("Location: sales-dashboard.php");
                exit();
            case 'printer':
                header("Location: printer-dashboard.php");
                exit();
            case 'rider':
                header("Location: rider-dashboard.php");
                exit();
            default:
                header("Location: dashboard.php");
                exit();
        }
    }
    // If already on correct dashboard page, don't redirect
}

// ENHANCED REMEMBER ME TOKEN AUTO-LOGIN WITH 365-DAY PERSISTENCE
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token']) && !$forceLoginPage) {
    $remember_token = $_COOKIE['remember_token'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = :token AND token_expires > :now");
        $stmt->bindParam(':token', $remember_token);
        $stmt->bindValue(':now', time());
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Auto-login user with fresh session
            session_regenerate_id(true);
            
            // Set comprehensive session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $_SESSION['auto_logged_in'] = true;
            $_SESSION['session_expires'] = time() + 31536000; // 1 year from now
            
            // Android app specific data
            if ($isAndroidApp) {
                $androidSessionManager->maintainAndroidSession($user['id']);
                $_SESSION['android_auto_login'] = true;
                $_SESSION['android_login_time'] = time();
            }
            
            // Update session cookie with extended lifetime
            setcookie(session_name(), session_id(), [
                'expires' => time() + 31536000,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'None'
            ]);
            
            error_log("✅ Auto-Login Success: User {$user['id']} via token");
            
            // Redirect based on user role
            $role = $user['role'];
            switch ($role) {
                case 'admin':
                    header("Location: admin-dashboard.php");
                    break;
                case 'sales_person':
                    header("Location: sales-dashboard.php");
                    break;
                case 'printer':
                    header("Location: printer-dashboard.php");
                    break;
                case 'rider':
                    header("Location: rider-dashboard.php");
                    break;
                default:
                    header("Location: dashboard.php");
            }
            exit();
        } else {
            // Clear invalid remember token
            setcookie('remember_token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
            error_log("❌ Auto-Login Failed: Invalid token");
        }
    } catch (PDOException $e) {
        error_log("🚨 Auto-Login Error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['Password'])) {
            // Login successful
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['needs_device_registration'] = true; // Flag for device registration
            
            // Android app specific session data
            if ($isAndroidApp) {
                $androidSessionManager->maintainAndroidSession($user['id']);
                $_SESSION['android_manual_login'] = true;
                $_SESSION['android_login_time'] = time();
                
                // Log Android login
                error_log("Android Manual Login Success: User {$user['id']}");
            }
            
            // ENHANCED REMEMBER ME FOR 365-DAY PERSISTENCE
            if (isset($_POST['remember_me'])) {
                $remember_token = bin2hex(random_bytes(32));
                $expires = time() + 31536000; // 1 year
                
                // Store token in database
                $stmt = $conn->prepare("UPDATE users SET remember_token = :token, token_expires = :expires WHERE id = :id");
                $stmt->bindParam(':token', $remember_token);
                $stmt->bindParam(':expires', $expires);
                $stmt->bindParam(':id', $user['id']);
                $stmt->execute();
                
                // Set cookie with Android-compatible parameters
                setcookie('remember_token', $remember_token, [
                    'expires' => $expires,
                    'path' => '/',
                    'domain' => $_SERVER['HTTP_HOST'],
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'None' // Essential for Android WebView
                ]);
                
                if ($isAndroidApp) {
                    $_SESSION['android_remember_me_set'] = true;
                    error_log("Android Remember Me Token Set: User {$user['id']}");
                }
            } else {
                // Clear remember token
                $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = :id");
                $stmt->bindParam(':id', $user['id']);
                $stmt->execute();
                
                setcookie('remember_token', '', time() - 3600, '/', $_SERVER['HTTP_HOST'], true, true);
            }
            
            // Update session cookie with extended lifetime
            setcookie(session_name(), session_id(), [
                'expires' => time() + 31536000,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'None'
            ]);
            
            // Check trial
            if (isset($user['trial_end']) && strtotime($user['trial_end']) < time()) {
                header("Location: subscription.php");
                exit();
            }
            
            // Redirect based on role
            $role = $user['role'];
            switch ($role) {
                case 'admin':
                    header("Location: admin-dashboard.php");
                    break;
                case 'sales_person':
                    header("Location: sales-dashboard.php");
                    break;
                case 'printer':
                    header("Location: printer-dashboard.php");
                    break;
                case 'rider':
                    header("Location: rider-dashboard.php");
                    break;
                default:
                    header("Location: dashboard.php");
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

<!-- Enhanced Universal Session Management - 365 Days -->
<script>
// Enhanced Universal Session Management - 365 Days
class UniversalSessionManager {
    constructor() {
        this.keepAliveInterval = 300000; // 5 minutes
        this.isAndroidApp = typeof WTN !== 'undefined';
        this.init();
    }

    init() {
        this.startKeepAlive();
        this.setupVisibilityHandler();
        this.setupActivityHandlers();
        this.initializeSession();
    }

    initializeSession() {
        // Set session persistence in localStorage
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('sessionInitialized', Date.now());
            localStorage.setItem('userAgent', navigator.userAgent);
            localStorage.setItem('sessionStart', new Date().toISOString());
        }
    }

    startKeepAlive() {
        // Immediate keep-alive on load
        this.keepSessionAlive();
        
        // Periodic keep-alive every 5 minutes
        this.keepAliveTimer = setInterval(() => {
            this.keepSessionAlive();
        }, this.keepAliveInterval);
    }

    async keepSessionAlive() {
        try {
            const response = await fetch('session-keepalive.php', {
                method: 'GET',
                credentials: 'include', // Essential for cookies
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('✅ Session kept alive:', new Date().toLocaleTimeString());
                
                // Update session info in storage
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastKeepAlive', Date.now());
                    localStorage.setItem('lastActivity', new Date().toISOString());
                }
            } else {
                console.warn('⚠️ Session keep-alive failed');
            }
        } catch (error) {
            console.error('❌ Keep-alive request failed:', error);
        }
    }

    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Page became visible - refresh session immediately
                console.log('🔄 Page visible - refreshing session');
                this.keepSessionAlive();
                
                // Additional session validation
                this.validateSessionState();
            }
        });
    }

    setupActivityHandlers() {
        // Refresh session on user activity
        const activities = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        activities.forEach(activity => {
            document.addEventListener(activity, () => {
                this.keepSessionAlive();
            }, { passive: true });
        });
    }

    validateSessionState() {
        // Check if session is still valid
        if (typeof(Storage) !== "undefined") {
            const lastKeepAlive = localStorage.getItem('lastKeepAlive');
            if (lastKeepAlive && (Date.now() - parseInt(lastKeepAlive)) > 600000) { // 10 minutes
                console.log('🔄 Session state validation triggered');
                this.keepSessionAlive();
            }
        }
    }

    // Cleanup method
    destroy() {
        if (this.keepAliveTimer) {
            clearInterval(this.keepAliveTimer);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.sessionManager = new UniversalSessionManager();
    
    // Store session initialization
    console.log('🚀 Universal Session Manager Initialized');
    console.log('📱 Android App:', typeof WTN !== 'undefined');
    console.log('🍪 Cookies enabled:', navigator.cookieEnabled);
    console.log('📅 Session designed for 365-day persistence');
    
    // Debug info for Android
    <?php if ($isAndroidApp): ?>
    console.log('🔧 Android Debug Info:', <?php echo json_encode($androidDebugInfo); ?>);
    <?php endif; ?>
});

// Handle page unload for session preservation
window.addEventListener('beforeunload', function() {
    if (typeof(Storage) !== "undefined") {
        localStorage.setItem('sessionPreserved', 'true');
        localStorage.setItem('lastUnload', Date.now());
    }
});
</script>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-81W5S4MMGY"></script>
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

/* Session status indicator */
.session-status {
    position: fixed;
    bottom: 10px;
    right: 10px;
    background: #28a745;
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    z-index: 10000;
    display: none;
}

.android-debug {
    position: fixed;
    bottom: 10px;
    left: 10px;
    background: #17a2b8;
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    z-index: 10000;
    display: none;
}
</style>
     
</head>

<body class="h-100">
     <!-- Session Status Indicator -->
     <!-- <div class="session-status" id="sessionStatus">Session Active (365 Days)</div> -->
     
     <!-- Android Debug Indicator -->
     <?php if ($isAndroidApp): ?>
     <!-- <div class="android-debug" id="androidDebug">Android App Detected</div> -->
     <?php endif; ?>

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
                                             <form action="" method="POST" class="authentication-form" id="loginForm">
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
                                                            <input type="checkbox" class="form-check-input" id="checkbox-signin" name="remember_me" checked>
                                                            <label class="form-check-label" for="checkbox-signin">
                                                                Remember Me (Recommended - 365 Days)
                                                            </label>
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

// Show session status on form interaction
document.getElementById('loginForm').addEventListener('submit', function() {
    const status = document.getElementById('sessionStatus');
    if (status) {
        status.style.display = 'block';
        status.textContent = 'Setting up 365-day session...';
        status.style.background = '#17a2b8';
    }
    
    const androidDebug = document.getElementById('androidDebug');
    if (androidDebug) {
        androidDebug.style.display = 'block';
        androidDebug.textContent = 'Android: Setting up persistent session...';
    }
});

// Show session info on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const status = document.getElementById('sessionStatus');
        if (status) {
            status.style.display = 'block';
            setTimeout(() => {
                status.style.display = 'none';
            }, 3000);
        }
        
        const androidDebug = document.getElementById('androidDebug');
        if (androidDebug) {
            androidDebug.style.display = 'block';
            setTimeout(() => {
                androidDebug.style.display = 'none';
            }, 5000);
        }
    }, 1000);
});

// Enhanced Android session debugging
<?php if ($isAndroidApp): ?>
console.log('📱 Android App Session Debug:');
console.log('User ID:', <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>);
console.log('Session ID:', '<?php echo session_id(); ?>');
console.log('Android Flag:', <?php echo isset($_SESSION['is_android_app']) ? 'true' : 'false'; ?>);
<?php endif; ?>
</script>

<!-- ========================================= -->
<!-- GUARANTEED OneSignal Device Registration -->
<!-- ========================================= -->

<!-- SIMPLIFIED OneSignal Registration -->
<script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
<script>
// Enhanced Android-Only OneSignal Registration
class AndroidOneSignalRegister {
    constructor() {
        this.userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        console.log('🚀 Android Register - User ID:', this.userId);
        
        // Only register if user is logged in
        if (this.userId && this.userId !== 'null') {
            this.startAndroidRegistration();
        } else {
            console.log('⏳ User not logged in - OneSignal registration deferred');
        }
    }
    
    startAndroidRegistration() {
        console.log('🔄 Starting Android-only registration...');
        
        // ONLY attempt registration for Android WebToNative
        if (typeof WTN !== 'undefined' && WTN.OneSignal) {
            console.log('📱 Android WebToNative detected - registering...');
            this.registerViaWebToNative();
        } else {
            console.log('🌐 Web browser detected - skipping device registration');
        }
    }
    
    registerViaWebToNative() {
        WTN.OneSignal.getPlayerId().then(playerId => {
            if (playerId) {
                console.log('✅ Got Android Player ID:', playerId);
                this.sendRegistration(playerId, 'android_webtonative', 'android');
            } else {
                console.log('❌ No Player ID from WebToNative');
            }
        }).catch(error => {
            console.error('❌ WebToNative error:', error);
        });
    }
    
    sendRegistration(playerId, deviceType, platform) {
        const payload = {
            player_id: playerId,
            device_type: deviceType,
            platform: platform,
            user_id: this.userId,
            source: 'android_only_script'
        };
        
        console.log('📨 Sending Android registration:', payload);
        
        fetch('register_device_unified.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            console.log('✅ Registration response:', data);
            if (data.success) {
                if (data.skipped) {
                    console.log('ℹ️ Registration skipped:', data.reason);
                } else {
                    console.log('🎉 ANDROID DEVICE REGISTERED SUCCESSFULLY!');
                    localStorage.setItem('android_device_registered', 'true');
                    localStorage.setItem('player_id', playerId);
                }
            } else {
                console.error('❌ Registration failed:', data.message);
            }
        })
        .catch(error => {
            console.error('❌ Request failed:', error);
        });
    }
}

// Start Android-only registration when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if user is logged in
    <?php if (isset($_SESSION['user_id'])): ?>
    new AndroidOneSignalRegister();
    <?php else: ?>
    console.log('⏳ OneSignal registration waiting for login...');
    <?php endif; ?>
});

// Re-initialize OneSignal after successful login
function initOneSignalAfterLogin(userId) {
    console.log('🔄 Initializing OneSignal after login for user:', userId);
    setTimeout(() => {
        new AndroidOneSignalRegister();
    }, 2000);
}
</script>

<!-- Listen for successful login form submission -->
<script>
document.getElementById('loginForm').addEventListener('submit', function() {
    // Set a flag to indicate login is in progress
    localStorage.setItem('login_in_progress', 'true');
    
    // OneSignal will be re-initialized after page redirect
    console.log('🔐 Login submitted - OneSignal will initialize after redirect');
});
</script>
<!-- ========================================= -->
<!-- Enhanced OneSignal Integration for Login -->
<!-- ========================================= -->
</body>
</html>
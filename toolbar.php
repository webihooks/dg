<?php
// toolbar.php - Enhanced with 365-day session persistence

// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    // Start session with extended configuration only if not already started
    session_set_cookie_params([
        'lifetime' => 31536000, // 1 year
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'None'
    ]);

    ini_set('session.gc_maxlifetime', 31536000);
    session_start();
} else {
    // Session already started, just update the activity
    error_log("Session already active, using existing session");
}

// Update session activity and extend cookie if user is logged in
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    $_SESSION['session_expires'] = time() + 31536000;
    
    // Only update cookie if we have write access to headers
    if (!headers_sent()) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
    }
}

// Database connection for user data
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Silent fail - don't break the page if DB connection fails
    error_log("Toolbar DB Connection Error: " . $e->getMessage());
    $conn = null;
}

// Get user data if logged in
$user_name = "Guest";
$user_id = "N/A";

if (isset($_SESSION['user_id']) && $conn) {
    try {
        $stmt = $conn->prepare("SELECT name, id FROM users WHERE id = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user_name = htmlspecialchars($user['name'] ?? 'User');
            $user_id = $user['id'];
        }
    } catch (PDOException $e) {
        error_log("Toolbar User Query Error: " . $e->getMessage());
    }
}

// Check if we're in Android app context
$isAndroidApp = isset($_SESSION['is_android_app']) && $_SESSION['is_android_app'] === true;

// Set Android-specific session data
if ($isAndroidApp && isset($_SESSION['user_id'])) {
    $_SESSION['android_last_activity'] = time();
    $_SESSION['toolbar_accessed'] = true;
}
?>

<!-- ========================================= -->
<!-- Enhanced Session Management - 365 Days -->
<!-- ========================================= -->
<script>
// Enhanced Universal Session Management - 365 Days
class UniversalSessionManager {
    constructor() {
        this.keepAliveInterval = 300000; // 5 minutes
        this.isAndroidApp = <?php echo $isAndroidApp ? 'true' : 'false'; ?>;
        this.isTWA = this.detectTWA();
        this.init();
    }

    detectTWA() {
        return window.navigator.standalone || 
               document.referrer.includes('android-app://') ||
               /Chrome/.test(navigator.userAgent) && !/Edge/.test(navigator.userAgent);
    }

    init() {
        console.log('🚀 Toolbar Session Manager Initialized');
        console.log('📱 Android App:', this.isAndroidApp);
        console.log('🖥️ TWA Environment:', this.isTWA);
        
        this.startKeepAlive();
        this.setupVisibilityHandler();
        this.setupActivityHandlers();
        this.initializeSession();
        
        if (this.isTWA || this.isAndroidApp) {
            this.setupTWAFeatures();
        }
    }

    initializeSession() {
        // Set session persistence in localStorage
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('sessionInitialized', Date.now());
            localStorage.setItem('userAgent', navigator.userAgent);
            localStorage.setItem('sessionStart', new Date().toISOString());
            localStorage.setItem('lastToolbarAccess', Date.now());
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
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Toolbar-Request': 'true'
                }
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('✅ Toolbar Session kept alive:', new Date().toLocaleTimeString());
                
                // Update session info in storage
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastKeepAlive', Date.now());
                    localStorage.setItem('lastActivity', new Date().toISOString());
                }
                
                // Update session status indicator
                this.updateSessionStatus('active');
            } else {
                console.warn('⚠️ Toolbar Session keep-alive failed');
                this.updateSessionStatus('warning');
            }
        } catch (error) {
            console.error('❌ Toolbar Keep-alive request failed:', error);
            this.updateSessionStatus('error');
        }
    }

    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Page became visible - refresh session immediately
                console.log('🔄 Toolbar Page visible - refreshing session');
                this.keepSessionAlive();
                
                // Additional session validation
                this.validateSessionState();
                
                // Check for pending orders if function exists
                if (typeof window.checkExistingPendingOrders === 'function') {
                    setTimeout(() => {
                        window.checkExistingPendingOrders();
                    }, 1000);
                }
            } else {
                // Page hidden - prepare for background
                this.prepareForBackground();
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

    setupTWAFeatures() {
        // Enhanced TWA/Android app features
        console.log('🔧 Setting up TWA/Android features');
        
        // Store session state before unload
        this.setupBeforeUnload();
        
        // Restore session state on load
        this.restoreSessionState();
    }

    setupBeforeUnload() {
        window.addEventListener('beforeunload', () => {
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem('twaSessionPreserved', 'true');
                localStorage.setItem('twaLastActive', Date.now().toString());
                localStorage.setItem('lastUrl', window.location.href);
            }
        });
    }

    restoreSessionState() {
        if (typeof(Storage) !== "undefined") {
            const sessionPreserved = localStorage.getItem('twaSessionPreserved');
            const lastActive = localStorage.getItem('twaLastActive');
            
            if (sessionPreserved === 'true' && lastActive) {
                const timeSinceLastActive = Date.now() - parseInt(lastActive);
                if (timeSinceLastActive < 300000) { // 5 minutes
                    console.log('🔄 TWA session restored from background');
                    this.updateSessionStatus('restored');
                    
                    // Trigger pending orders check
                    setTimeout(() => {
                        if (typeof window.checkExistingPendingOrders === 'function') {
                            window.checkExistingPendingOrders();
                        }
                    }, 1500);
                }
            }
            
            // Clean up
            localStorage.removeItem('twaSessionPreserved');
            localStorage.removeItem('twaLastActive');
        }
    }

    prepareForBackground() {
        console.log('📱 Preparing for background/switch');
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('lastBackgroundTime', Date.now());
        }
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

    updateSessionStatus(status) {
        // Update visual indicator if exists
        const statusElement = document.getElementById('sessionStatusIndicator');
        if (statusElement) {
            // const statusConfig = {
            //     'active': { text: 'Session Active (365 Days)', color: '#28a745' },
            //     'warning': { text: 'Session Warning', color: '#ffc107' },
            //     'error': { text: 'Session Error', color: '#dc3545' },
            //     'restored': { text: 'Session Restored', color: '#17a2b8' }
            // };
            
            const config = statusConfig[status] || statusConfig['active'];
            statusElement.textContent = config.text;
            statusElement.style.backgroundColor = config.color;
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
    // Only initialize if user is logged in
    <?php if (isset($_SESSION['user_id'])): ?>
    window.toolbarSessionManager = new UniversalSessionManager();
    
    // Store session initialization
    console.log('🚀 Toolbar Session Manager Active');
    console.log('📅 Session designed for 365-day persistence');
    console.log('👤 User ID: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not logged in'; ?>');
    
    // Make functions globally available for TWA
    if (typeof window.checkExistingPendingOrders !== 'function') {
        window.checkExistingPendingOrders = function() {
            console.log('🔄 checkExistingPendingOrders called from toolbar');
            // This function can be overridden by specific pages
        };
    }
    <?php else: ?>
    console.log('👤 User not logged in - Toolbar Session manager not started');
    <?php endif; ?>
});

// Handle page unload for session preservation
window.addEventListener('beforeunload', function() {
    if (typeof(Storage) !== "undefined") {
        localStorage.setItem('sessionPreserved', 'true');
        localStorage.setItem('lastUnload', Date.now());
        localStorage.setItem('toolbarLastAccess', Date.now());
    }
});

// Enhanced activity monitoring
let lastActivityTime = Date.now();
document.addEventListener('mousemove', function() {
    lastActivityTime = Date.now();
});
document.addEventListener('keypress', function() {
    lastActivityTime = Date.now();
});
</script>

<!-- Session Status Indicator -->
<!-- <div id="sessionStatusIndicator" style="
    position: fixed; 
    top: 10px; 
    right: 10px; 
    background: #28a745; 
    color: white; 
    padding: 5px 10px; 
    border-radius: 15px; 
    font-size: 10px; 
    z-index: 10000; 
    display: none;
    font-weight: bold;
">Session Active</div> -->

<style>
/*.userid_class {
    font-size: 11px;
    opacity: 0.7;
    display: block;
    margin-top: 2px;
}*/

.session-info {
    font-size: 11px;
    opacity: 0.8;
    display: block;
    margin-top: 2px;
}

/* Android app specific styles */
<?php if ($isAndroidApp): ?>
.topbar {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(10px);
}
<?php endif; ?>
</style>

<header class="topbar">
     <div class="container-fluid">
          <div class="navbar-header">
               <div class="d-flex align-items-center">
                    <!-- Menu Toggle Button -->
                    <div class="topbar-item">
                         <button type="button" class="button-toggle-menu me-2">
                              <iconify-icon icon="solar:hamburger-menu-broken" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div>

                    <!-- Welcome Message -->
                    <div class="topbar-item">
                         <h4 class="fw-bold topbar-button pe-none text-uppercase mb-0">
                         <?php echo $user_name; ?>
                    </h4>
                    </div>
               </div>

               <div class="d-flex align-items-center gap-1">

                    <!-- Theme Color (Light/Dark) -->
                    <div class="topbar-item">
                         <button type="button" class="topbar-button" id="light-dark-mode">
                              <iconify-icon icon="solar:moon-bold-duotone" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div>

                    <!-- Session Status Button -->
                    <!-- <div class="topbar-item">
                         <button type="button" class="topbar-button" id="session-status-btn" title="Session Status">
                              <iconify-icon icon="solar:shield-check-bold" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div> -->

                    <!-- User -->
                    <div class="dropdown topbar-item">
                         <a type="button" class="topbar-button" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <span class="d-flex align-items-center">
                                   <img class="rounded-circle" width="32" src="assets/images/users/dummy-avatar.jpg" alt="avatar-3">
                                   <?php if ($isAndroidApp): ?>
                                   <span class="position-absolute top-0 start-100 translate-middle p-1 bg-success border border-light rounded-circle">
                                        <span class="visually-hidden">Android App</span>
                                   </span>
                                   <?php endif; ?>
                              </span>
                         </a>
                         <div class="dropdown-menu dropdown-menu-end">
                              <!-- User Info -->
                              <h6 class="dropdown-header">
                                  Welcome, <?php echo $user_name; ?>! 
                                  <span class="userid_class">ID: <?php echo $user_id; ?></span>
                              </h6>

                              <div class="dropdown-divider my-1"></div>
                              
                              <!-- Session Info -->
                              <div class="px-3 py-2 small">
                                  <div class="text-muted">
                                      <strong>Session Status:</strong> Active<br>
                                      <strong>Duration:</strong> 365 Days<br>
                                      <strong>Last Activity:</strong> 
                                      <?php echo isset($_SESSION['last_activity']) ? 
                                          date('M j, g:i A', $_SESSION['last_activity']) : 'Just now'; ?>
                                  </div>
                              </div>

                              <div class="dropdown-divider my-1"></div>
                                
                              <!-- Logout Option -->
                              <?php
                              if (!isset($_SESSION['android_logout_button'])) {
                                  echo '<a class="dropdown-item text-danger" href="logout.php" id="logoutButton">
                                   <i class="bx bx-log-out fs-18 align-middle me-1"></i>
                                   <span class="align-middle">Logout</span>
                              </a>';
                              } ?>
                              
                         </div>
                    </div>

               </div>
          </div>
     </div>
</header>

<script>
// Enhanced toolbar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Session status button functionality
    const sessionStatusBtn = document.getElementById('session-status-btn');
    const sessionStatusIndicator = document.getElementById('sessionStatusIndicator');
    
    if (sessionStatusBtn) {
        sessionStatusBtn.addEventListener('click', function() {
            if (sessionStatusIndicator) {
                // Toggle visibility
                if (sessionStatusIndicator.style.display === 'none') {
                    sessionStatusIndicator.style.display = 'block';
                    setTimeout(() => {
                        sessionStatusIndicator.style.display = 'none';
                    }, 3000);
                } else {
                    sessionStatusIndicator.style.display = 'block';
                }
            }
            
            // Trigger immediate keep-alive
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.keepSessionAlive();
            }
        });
    }
    
    // Enhanced logout confirmation
    const logoutButton = document.getElementById('logoutButton');
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            // Clean up session manager
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.destroy();
            }
            
            // Clear session storage
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem('sessionPreserved');
                localStorage.removeItem('lastKeepAlive');
                localStorage.removeItem('sessionInitialized');
            }
            
            // Allow the default logout behavior to proceed
            // No confirmation dialog - user will be logged out immediately
        });
    }
    
    // Show session status on first load
    setTimeout(() => {
        if (sessionStatusIndicator) {
            sessionStatusIndicator.style.display = 'block';
            setTimeout(() => {
                sessionStatusIndicator.style.display = 'none';
            }, 3000);
        }
    }, 1000);
});

// Make checkExistingPendingOrders available globally
function checkExistingPendingOrders() {
    console.log('🔄 Default pending orders check - override this in specific pages');
    // This will be overridden by specific dashboard pages
}
window.checkExistingPendingOrders = checkExistingPendingOrders;
</script>
<?php
// toolbar.php - Enhanced with safe session management for 365-day persistence

// First, check if we need to start session management
if (session_status() === PHP_SESSION_NONE) {
    // No session active - use our session manager
    require_once 'android_session_manager.php';
    $sessionManager = new AndroidSessionManager();
} else {
    // Session already active - create manager without starting new session
    require_once 'android_session_manager.php';
    $sessionManager = new AndroidSessionManager();
    
    // Just validate the existing session
    $sessionManager->validateAndroidSession();
}

// Update session activity and extend cookie if user is logged in
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    $_SESSION['session_expires'] = time() + 31536000;
    
    // Only update cookie if we have write access to headers AND session wasn't already active
    if (!headers_sent() && method_exists($sessionManager, 'wasSessionStartedByManager') && $sessionManager->wasSessionStartedByManager()) {
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
$isAndroidApp = $sessionManager->isAndroidApp();

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
        this.healthCheckInterval = 120000; // 2 minutes for health checks
        this.heartbeatInterval = this.isAndroidApp ? 300000 : 600000; // 5 min Android, 10 min Web
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
        console.log('❤️ Heartbeat Interval:', this.heartbeatInterval / 1000 + 's');
        
        this.startKeepAlive();
        this.startHealthChecks();
        this.startHeartbeat();
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
            localStorage.setItem('platform', this.isAndroidApp ? 'android' : 'web');
        }
    }

    startKeepAlive() {
        // Immediate keep-alive on load
        this.keepSessionAlive();
        
        // Periodic keep-alive
        this.keepAliveTimer = setInterval(() => {
            this.keepSessionAlive();
        }, this.keepAliveInterval);
    }

    startHealthChecks() {
        // Health check every 2 minutes
        this.healthCheckTimer = setInterval(() => {
            this.performHealthCheck();
        }, this.healthCheckInterval);
    }

    startHeartbeat() {
        // Heartbeat for session maintenance (more frequent for Android)
        this.heartbeatTimer = setInterval(() => {
            this.sendHeartbeat();
        }, this.heartbeatInterval);
    }

    async performHealthCheck() {
        try {
            const response = await fetch('session_health_check.php', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.session_active) {
                console.log('✅ Session Health Check Passed');
                
                // Update session info in storage
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastHealthCheck', Date.now());
                    localStorage.setItem('sessionHealth', 'healthy');
                }
                
                this.updateSessionStatus('active');
            } else {
                console.warn('⚠️ Session Health Check Failed:', data.issues);
                this.updateSessionStatus('warning');
                
                // Try to recover session
                this.recoverSession();
            }
        } catch (error) {
            console.error('❌ Health Check Request Failed:', error);
            this.updateSessionStatus('error');
        }
    }

    async sendHeartbeat() {
        try {
            const response = await fetch('heartbeat.php', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('❤️ Heartbeat maintained - Count:', data.heartbeat_count);
                
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastHeartbeat', Date.now());
                    localStorage.setItem('heartbeatCount', data.heartbeat_count);
                }
            } else {
                console.warn('💔 Heartbeat failed:', data.error);
            }
        } catch (error) {
            console.error('💔 Heartbeat request failed:', error);
        }
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

    async recoverSession() {
        console.log('🔄 Attempting session recovery...');
        
        try {
            // Try to refresh the page first
            const response = await fetch(window.location.href, {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'X-Session-Recovery': 'true'
                }
            });
            
            if (response.ok) {
                console.log('✅ Session recovery successful');
                this.updateSessionStatus('recovered');
            } else {
                throw new Error('Page refresh failed');
            }
        } catch (error) {
            console.error('❌ Session recovery failed:', error);
            this.showSessionRecoveryAlert();
        }
    }

    showSessionRecoveryAlert() {
        // Create a non-intrusive recovery notification
        const recoveryDiv = document.createElement('div');
        recoveryDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            z-index: 10001;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        `;
        recoveryDiv.innerHTML = `
            <strong>⚠️ Session Issue Detected</strong>
            <p style="margin: 8px 0; font-size: 14px;">Your session may have issues. <a href="javascript:location.reload()" style="color: #856404; text-decoration: underline;">Refresh page</a></p>
            <button onclick="this.parentNode.remove()" style="background: none; border: none; color: #856404; float: right; cursor: pointer;">×</button>
        `;
        document.body.appendChild(recoveryDiv);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (recoveryDiv.parentNode) {
                recoveryDiv.parentNode.removeChild(recoveryDiv);
            }
        }, 10000);
    }

    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Page became visible - refresh session immediately
                console.log('🔄 Toolbar Page visible - refreshing session');
                this.keepSessionAlive();
                this.performHealthCheck();
                
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
                localStorage.setItem('sessionPreserved', 'true');
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
            localStorage.setItem('wasInBackground', 'true');
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
            const statusConfig = {
                'active': { text: 'Session Active (365 Days)', color: '#28a745', icon: '✅' },
                'warning': { text: 'Session Warning', color: '#ffc107', icon: '⚠️' },
                'error': { text: 'Session Error', color: '#dc3545', icon: '❌' },
                'restored': { text: 'Session Restored', color: '#17a2b8', icon: '🔄' }
            };
            
            const config = statusConfig[status] || statusConfig['active'];
            statusElement.innerHTML = `${config.icon} ${config.text}`;
            statusElement.style.backgroundColor = config.color;
        }
    }

    // Get session statistics for debugging
    getSessionStats() {
        if (typeof(Storage) === "undefined") return null;
        
        return {
            platform: localStorage.getItem('platform') || 'unknown',
            sessionStart: localStorage.getItem('sessionStart'),
            lastHealthCheck: localStorage.getItem('lastHealthCheck'),
            lastHeartbeat: localStorage.getItem('lastHeartbeat'),
            lastKeepAlive: localStorage.getItem('lastKeepAlive'),
            heartbeatCount: localStorage.getItem('heartbeatCount'),
            userAgent: localStorage.getItem('userAgent')
        };
    }

    // Cleanup method
    destroy() {
        if (this.keepAliveTimer) {
            clearInterval(this.keepAliveTimer);
        }
        if (this.healthCheckTimer) {
            clearInterval(this.healthCheckTimer);
        }
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }
        
        console.log('🧹 Toolbar Session Manager Cleaned Up');
    }
}








// Enhanced Android-Only OneSignal Registration with Retry Logic
class AndroidOneSignalRegister {
    constructor() {
        this.userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        this.registrationAttempts = 0;
        this.maxAttempts = 3;
        console.log('🚀 Android Register - User ID:', this.userId);
        
        if (this.userId) {
            this.startAndroidRegistration();
        }
    }
    
    startAndroidRegistration() {
        console.log('🔄 Starting Android registration...');
        
        // Check if we need to force registration (after login)
        const needsRegistration = <?php echo isset($_SESSION['needs_device_registration']) ? 'true' : 'false'; ?>;
        
        if (needsRegistration) {
            console.log('🔔 Force registration required after login');
        }
        
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
                this.retryRegistration();
            }
        }).catch(error => {
            console.error('❌ WebToNative error:', error);
            this.retryRegistration();
        });
    }
    
    sendRegistration(playerId, deviceType, platform) {
        const payload = {
            player_id: playerId,
            device_type: deviceType,
            platform: platform,
            user_id: this.userId,
            source: 'android_login_reactivation',
            force_reactivate: true
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
                    console.log('🎉 ANDROID DEVICE REGISTERED/REACTIVATED SUCCESSFULLY!');
                    
                    // Clear the needs registration flag
                    if (typeof(Storage) !== "undefined") {
                        localStorage.setItem('device_registered', 'true');
                        localStorage.setItem('registration_time', Date.now());
                    }
                    
                    // Show success message for reactivation
                    if (data.was_reactivated) {
                        this.showMessage('✅ Device reactivated - You will receive push notifications', 'success');
                    }
                }
            } else {
                console.error('❌ Registration failed:', data.message);
                this.retryRegistration();
            }
        })
        .catch(error => {
            console.error('❌ Request failed:', error);
            this.retryRegistration();
        });
    }
    
    retryRegistration() {
        this.registrationAttempts++;
        
        if (this.registrationAttempts < this.maxAttempts) {
            console.log(`🔄 Retrying registration (attempt ${this.registrationAttempts + 1}/${this.maxAttempts})`);
            setTimeout(() => {
                this.startAndroidRegistration();
            }, 2000 * this.registrationAttempts); // Exponential backoff
        } else {
            console.error('❌ Max registration attempts reached');
            this.showMessage('⚠️ Device registration failed. Notifications may not work.', 'warning');
        }
    }
    
    showMessage(message, type) {
        // Create a visible notification
        const div = document.createElement('div');
        div.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            background: ${type === 'success' ? '#d4edda' : 
                        type === 'warning' ? '#fff3cd' : '#f8d7da'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : 
                              type === 'warning' ? '#ffeaa7' : '#f5c6cb'};
            border-radius: 5px;
            z-index: 10000;
            color: ${type === 'success' ? '#155724' : 
                    type === 'warning' ? '#856404' : '#721c24'};
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        `;
        div.textContent = message;
        document.body.appendChild(div);
        
        setTimeout(() => {
            if (div.parentNode) {
                div.parentNode.removeChild(div);
            }
        }, 5000);
    }
}

// Start Android registration when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if we just logged in and need device registration
    const justLoggedIn = <?php echo isset($_SESSION['needs_device_registration']) ? 'true' : 'false'; ?>;
    
    if (justLoggedIn) {
        console.log('🔔 New login detected - forcing device registration');
        // Clear the flag
        <?php unset($_SESSION['needs_device_registration']); ?>
    }
    
    new AndroidOneSignalRegister();
});









// Handle page unload for session preservation
window.addEventListener('beforeunload', function() {
    if (typeof(Storage) !== "undefined") {
        localStorage.setItem('sessionPreserved', 'true');
        localStorage.setItem('lastUnload', Date.now());
        localStorage.setItem('toolbarLastAccess', Date.now());
    }
    
    // Clean up session manager
    if (window.toolbarSessionManager) {
        window.toolbarSessionManager.destroy();
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
<div id="sessionStatusIndicator" style="
    position: fixed; 
    top: 10px; 
    right: 10px; 
    background: #28a745; 
    color: white; 
    padding: 8px 12px; 
    border-radius: 20px; 
    font-size: 12px; 
    z-index: 10000; 
    display: none;
    font-weight: bold;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
">✅ Session Active (365 Days)</div>

<style>
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

/* Session status badge in user dropdown */
.session-badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
}
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
                    <div class="topbar-item">
                         <button type="button" class="topbar-button" id="session-status-btn" title="Session Status">
                              <iconify-icon icon="solar:shield-check-bold" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div>

                    <!-- User -->
                    <div class="dropdown topbar-item">
                        <a type="button" class="topbar-button" id="page-header-user-dropdown" 
                           data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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
                              
                              <!-- Enhanced Session Info -->
                              <div class="px-3 py-2 small">
                                  <div class="text-muted">
                                      <strong>Session Status:</strong> 
                                      <span class="badge session-badge bg-success">Active</span><br>
                                      <strong>Platform:</strong> 
                                      <?php echo $isAndroidApp ? '📱 Android App' : '🌐 Web Browser'; ?><br>
                                      <strong>Duration:</strong> 365 Days<br>
                                      <strong>Last Activity:</strong> 
                                      <?php echo isset($_SESSION['last_activity']) ? 
                                          date('M j, g:i A', $_SESSION['last_activity']) : 'Just now'; ?>
                                  </div>
                              </div>

                              <div class="dropdown-divider my-1"></div>

                              <!-- Device Management Link -->
                              <a class="dropdown-item" href="device_management.php">
                                  <i class="fas fa-mobile-alt fs-18 align-middle me-1"></i>
                                  <span class="align-middle">Manage Devices</span>
                              </a>
                                
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
                    }, 5000);
                } else {
                    sessionStatusIndicator.style.display = 'block';
                }
            }
            
            // Trigger immediate health check
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.performHealthCheck();
                window.toolbarSessionManager.keepSessionAlive();
                
                // Show debug info in console
                const debugInfo = window.getSessionDebugInfo();
                console.log('🔍 Manual Session Check:', debugInfo);
            }
        });
    }
    
    // Enhanced logout confirmation
    const logoutButton = document.getElementById('logoutButton');
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout from this device? Push notifications will stop only on this device.')) {
                e.preventDefault();
                return;
            }
            
            // Clean up session manager
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.destroy();
            }
            
            // Clear device-specific session storage
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem('current_player_id');
                localStorage.removeItem('lastKeepAlive');
                localStorage.removeItem('sessionInitialized');
            }
            
            // Allow the default logout behavior to proceed
        });
    }
    
    // Show session status on first load
    setTimeout(() => {
        if (sessionStatusIndicator) {
            sessionStatusIndicator.style.display = 'block';
            setTimeout(() => {
                sessionStatusIndicator.style.display = 'none';
            }, 5000);
        }
    }, 2000);
    
    // Auto-hide session status after 5 seconds
    setInterval(() => {
        if (sessionStatusIndicator && sessionStatusIndicator.style.display === 'block') {
            // Only hide if it's been visible for more than 5 seconds
            setTimeout(() => {
                sessionStatusIndicator.style.display = 'none';
            }, 5000);
        }
    }, 10000);
});

// Make checkExistingPendingOrders available globally
function checkExistingPendingOrders() {
    console.log('🔄 Default pending orders check - override this in specific pages');
    // This will be overridden by specific dashboard pages
}
window.checkExistingPendingOrders = checkExistingPendingOrders;

// Session debug function
function debugSession() {
    if (window.toolbarSessionManager) {
        const stats = window.toolbarSessionManager.getSessionStats();
        const sessionInfo = {
            phpSession: {
                userId: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>,
                isAndroid: <?php echo $isAndroidApp ? 'true' : 'false'; ?>,
                lastActivity: <?php echo isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 'null'; ?>
            },
            javascript: stats
        };
        console.log('🔍 Full Session Debug:', sessionInfo);
        return sessionInfo;
    }
    return null;
}
</script>
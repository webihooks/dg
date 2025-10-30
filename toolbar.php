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
// Enhanced Universal Session Management - 365 Days with WebToNative
class UniversalSessionManager {
    constructor() {
        this.keepAliveInterval = 300000; // 5 minutes
        this.isAndroidApp = <?php echo $isAndroidApp ? 'true' : 'false'; ?>;
        this.isWebToNative = typeof WTN !== 'undefined';
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
        console.log('🔧 WebToNative:', this.isWebToNative);
        console.log('🖥️ TWA Environment:', this.isTWA);
        console.log('❤️ Heartbeat Interval:', this.heartbeatInterval / 1000 + 's');
        
        this.startKeepAlive();
        this.startHealthChecks();
        this.startHeartbeat();
        this.setupVisibilityHandler();
        this.setupActivityHandlers();
        this.initializeSession();
        this.setupWebToNativeFeatures();
        
        if (this.isTWA || this.isAndroidApp) {
            this.setupTWAFeatures();
        }
    }

    setupWebToNativeFeatures() {
        if (this.isWebToNative && typeof WTN !== 'undefined') {
            console.log('🔧 Setting up WebToNative features');
            
            // Force cookie update immediately
            this.forceCookieUpdate();
            
            // Set up periodic cookie updates for WebToNative
            this.cookieUpdateInterval = setInterval(() => {
                this.forceCookieUpdate();
            }, 60000); // Every minute for WebToNative
            
            // Listen for WebToNative events
            this.setupWebToNativeEventListeners();
        }
    }

    forceCookieUpdate() {
        if (this.isWebToNative && typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
            console.log('🔧 WebToNative: Forcing cookie update');
            try {
                WTN.forceUpdateCookies();
                
                // Log successful cookie update
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastCookieUpdate', Date.now());
                    localStorage.setItem('webtonative_update_count', 
                        parseInt(localStorage.getItem('webtonative_update_count') || '0') + 1);
                }
                
                console.log('✅ WebToNative: Cookies updated successfully');
            } catch (error) {
                console.error('❌ WebToNative: Cookie update failed:', error);
            }
        }
    }

    setupWebToNativeEventListeners() {
        // Listen for app state changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isWebToNative) {
                // App came to foreground - force cookie update
                setTimeout(() => {
                    this.forceCookieUpdate();
                    this.keepSessionAlive();
                }, 500);
            }
        });

        // Listen for any user interaction to trigger cookie updates
        const interactiveEvents = ['touchstart', 'click', 'scroll', 'keydown'];
        interactiveEvents.forEach(event => {
            document.addEventListener(event, () => {
                if (this.isWebToNative) {
                    // Debounced cookie update on user interaction
                    clearTimeout(this.cookieUpdateTimeout);
                    this.cookieUpdateTimeout = setTimeout(() => {
                        this.forceCookieUpdate();
                    }, 1000);
                }
            }, { passive: true });
        });
    }

    initializeSession() {
        // Set session persistence in localStorage
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('sessionInitialized', Date.now());
            localStorage.setItem('userAgent', navigator.userAgent);
            localStorage.setItem('sessionStart', new Date().toISOString());
            localStorage.setItem('lastToolbarAccess', Date.now());
            localStorage.setItem('platform', this.isAndroidApp ? 'android' : 'web');
            localStorage.setItem('webtonative', this.isWebToNative ? 'true' : 'false');
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
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-WebToNative': this.isWebToNative ? 'true' : 'false'
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
                
                // Force cookie update for WebToNative after health check
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
                }
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
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-WebToNative': this.isWebToNative ? 'true' : 'false'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('❤️ Heartbeat maintained - Count:', data.heartbeat_count);
                
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastHeartbeat', Date.now());
                    localStorage.setItem('heartbeatCount', data.heartbeat_count);
                }
                
                // Force cookie update for WebToNative after heartbeat
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
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
                    'X-Toolbar-Request': 'true',
                    'X-WebToNative': this.isWebToNative ? 'true' : 'false'
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
                
                // Force cookie update for WebToNative after keep-alive
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
                }
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
                    'X-Session-Recovery': 'true',
                    'X-WebToNative': this.isWebToNative ? 'true' : 'false'
                }
            });
            
            if (response.ok) {
                console.log('✅ Session recovery successful');
                this.updateSessionStatus('recovered');
                
                // Force cookie update after recovery
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
                }
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
                
                // Force cookie update for WebToNative
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
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
                
                // Force cookie update for WebToNative on user activity
                if (this.isWebToNative) {
                    clearTimeout(this.activityCookieTimeout);
                    this.activityCookieTimeout = setTimeout(() => {
                        this.forceCookieUpdate();
                    }, 2000);
                }
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
                
                // Force final cookie update for WebToNative
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
                }
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
                    
                    // Force cookie update after restore
                    if (this.isWebToNative) {
                        this.forceCookieUpdate();
                    }
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
            
            // Force cookie update before going to background
            if (this.isWebToNative) {
                this.forceCookieUpdate();
            }
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
                'active': { 
                    text: this.isWebToNative ? 'WebToNative - Session Active' : 'Session Active (365 Days)', 
                    color: '#28a745', 
                    icon: '✅' 
                },
                'warning': { 
                    text: 'Session Warning', 
                    color: '#ffc107', 
                    icon: '⚠️' 
                },
                'error': { 
                    text: 'Session Error', 
                    color: '#dc3545', 
                    icon: '❌' 
                },
                'restored': { 
                    text: 'Session Restored', 
                    color: '#17a2b8', 
                    icon: '🔄' 
                }
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
            webtonative: localStorage.getItem('webtonative') || 'false',
            sessionStart: localStorage.getItem('sessionStart'),
            lastHealthCheck: localStorage.getItem('lastHealthCheck'),
            lastHeartbeat: localStorage.getItem('lastHeartbeat'),
            lastKeepAlive: localStorage.getItem('lastKeepAlive'),
            lastCookieUpdate: localStorage.getItem('lastCookieUpdate'),
            heartbeatCount: localStorage.getItem('heartbeatCount'),
            cookieUpdateCount: localStorage.getItem('webtonative_update_count'),
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
        if (this.cookieUpdateInterval) {
            clearInterval(this.cookieUpdateInterval);
        }
        if (this.cookieUpdateTimeout) {
            clearTimeout(this.cookieUpdateTimeout);
        }
        if (this.activityCookieTimeout) {
            clearTimeout(this.activityCookieTimeout);
        }
        
        console.log('🧹 Toolbar Session Manager Cleaned Up');
    }
}

// Enhanced WebToNative Session Management
class WebToNativeSessionManager {
    constructor() {
        this.isWebToNative = typeof WTN !== 'undefined';
        this.userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        this.init();
    }

    init() {
        if (this.isWebToNative) {
            console.log('🔧 WebToNative Session Manager Initialized');
            this.setupWebToNativeSessionHandling();
            this.startCookieMaintenance();
            this.setupAppStateHandlers();
            
            // Log WebToNative environment info
            this.logWebToNativeInfo();
        }
    }

    logWebToNativeInfo() {
        const info = {
            wtnAvailable: typeof WTN !== 'undefined',
            forceUpdateAvailable: typeof WTN !== 'undefined' && typeof WTN.forceUpdateCookies === 'function',
            userId: this.userId,
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };
        console.log('🔧 WebToNative Environment:', info);
    }

    setupWebToNativeSessionHandling() {
        // Force initial cookie update
        this.forceCookieUpdate();
        
        // Set up periodic session validation
        setInterval(() => {
            this.validateWebToNativeSession();
        }, 30000); // Every 30 seconds
    }

    startCookieMaintenance() {
        // Update cookies every minute for WebToNative
        this.cookieMaintenanceInterval = setInterval(() => {
            this.forceCookieUpdate();
        }, 60000);
        
        // Also update cookies on user activity
        this.setupActivityBasedUpdates();
    }

    setupActivityBasedUpdates() {
        const activities = ['touchstart', 'click', 'scroll', 'keydown', 'mousemove'];
        activities.forEach(activity => {
            document.addEventListener(activity, () => {
                if (this.isWebToNative) {
                    clearTimeout(this.activityUpdateTimeout);
                    this.activityUpdateTimeout = setTimeout(() => {
                        this.forceCookieUpdate();
                    }, 2000);
                }
            }, { passive: true });
        });
    }

    setupAppStateHandlers() {
        // Handle app background/foreground transitions
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // App came to foreground - ensure session is fresh
                console.log('📱 WebToNative: App foreground - refreshing session');
                this.forceCookieUpdate();
                this.validateWebToNativeSession();
            }
        });

        // Handle page load for WebToNative
        window.addEventListener('load', () => {
            if (this.isWebToNative) {
                console.log('📱 WebToNative: Page loaded - initializing session');
                setTimeout(() => {
                    this.forceCookieUpdate();
                }, 1000);
            }
        });
    }

    forceCookieUpdate() {
        if (this.isWebToNative && typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
            try {
                WTN.forceUpdateCookies();
                console.log('✅ WebToNative: Cookies force updated');
                
                // Log the update
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('webtonative_last_cookie_update', Date.now());
                    localStorage.setItem('webtonative_update_count', 
                        parseInt(localStorage.getItem('webtonative_update_count') || '0') + 1);
                }
            } catch (error) {
                console.error('❌ WebToNative: Cookie update error:', error);
            }
        }
    }

    async validateWebToNativeSession() {
        try {
            const response = await fetch('session-keepalive.php', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'X-WebToNative-Validate': 'true',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('✅ WebToNative: Session validated');
                this.forceCookieUpdate(); // Update cookies after validation
            } else {
                console.warn('⚠️ WebToNative: Session validation failed');
            }
        } catch (error) {
            console.error('❌ WebToNative: Session validation request failed:', error);
        }
    }

    // Debug method for WebToNative
    getWebToNativeDebugInfo() {
        return {
            isWebToNative: this.isWebToNative,
            userId: this.userId,
            lastCookieUpdate: localStorage.getItem('webtonative_last_cookie_update'),
            updateCount: localStorage.getItem('webtonative_update_count'),
            wtnAvailable: typeof WTN !== 'undefined',
            forceUpdateAvailable: typeof WTN !== 'undefined' && typeof WTN.forceUpdateCookies === 'function',
            sessionActive: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>
        };
    }

    destroy() {
        if (this.cookieMaintenanceInterval) {
            clearInterval(this.cookieMaintenanceInterval);
        }
        if (this.activityUpdateTimeout) {
            clearTimeout(this.activityUpdateTimeout);
        }
    }
}

// Enhanced Android-only debug console
class AndroidDebugConsole {
    constructor() {
        this.isWebToNative = typeof WTN !== 'undefined';
        this.debugEnabled = this.isWebToNative; // Only enable for Android WebToNative
        this.init();
    }

    init() {
        if (this.debugEnabled) {
            console.log('🔧 Android Debug Console Initialized - WebToNative Detected');
            this.setupDebugPanel();
            this.startSessionMonitoring();
        }
    }

    setupDebugPanel() {
        // Create debug panel that only shows for WebToNative Android
        const debugPanel = document.createElement('div');
        debugPanel.id = 'androidDebugPanel';
        debugPanel.style.cssText = `
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.9);
            color: #00ff00;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            z-index: 9999;
            max-width: 300px;
            display: ${this.isWebToNative ? 'block' : 'none'};
            border: 1px solid #00ff00;
        `;

        debugPanel.innerHTML = `
            <div style="margin-bottom: 5px;"><strong>📱 WebToNative Android Debug</strong></div>
            <div id="debugContent"></div>
            <button onclick="androidDebug.togglePanel()" style="background: #333; color: #00ff00; border: 1px solid #00ff00; padding: 2px 5px; margin-top: 5px; font-size: 10px;">Toggle</button>
        `;

        document.body.appendChild(debugPanel);
        this.updateDebugInfo();
    }

    updateDebugInfo() {
        if (!this.debugEnabled) return;

        const debugContent = document.getElementById('debugContent');
        if (debugContent) {
            const sessionInfo = {
                userId: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>,
                sessionId: '<?php echo session_id(); ?>',
                isAndroid: <?php echo isset($_SESSION['is_android_app']) ? 'true' : 'false'; ?>,
                webToNative: this.isWebToNative,
                lastActivity: <?php echo isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 'null'; ?>,
                cookieUpdates: localStorage.getItem('webtonative_update_count') || '0',
                lastCookieUpdate: localStorage.getItem('webtonative_last_cookie_update') || 'Never',
                sessionAge: <?php echo isset($_SESSION['login_time']) ? time() - $_SESSION['login_time'] : '0'; ?>
            };

            debugContent.innerHTML = `
                <div>User ID: ${sessionInfo.userId}</div>
                <div>Session: ${sessionInfo.sessionId.substring(0, 10)}...</div>
                <div>Android: ${sessionInfo.isAndroid ? '✅' : '❌'}</div>
                <div>WebToNative: ${sessionInfo.webToNative ? '✅' : '❌'}</div>
                <div>Cookie Updates: ${sessionInfo.cookieUpdates}</div>
                <div>Last Update: ${sessionInfo.lastCookieUpdate !== 'Never' ? new Date(parseInt(sessionInfo.lastCookieUpdate)).toLocaleTimeString() : 'Never'}</div>
                <div>Last Activity: ${sessionInfo.lastActivity ? new Date(sessionInfo.lastActivity * 1000).toLocaleTimeString() : 'Never'}</div>
                <div>Session Age: ${Math.round(sessionInfo.sessionAge / 60)} minutes</div>
            `;
        }

        // Update every 5 seconds
        setTimeout(() => this.updateDebugInfo(), 5000);
    }

    togglePanel() {
        const panel = document.getElementById('androidDebugPanel');
        if (panel) {
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
    }

    startSessionMonitoring() {
        // Monitor session health
        setInterval(() => {
            this.logSessionHealth();
        }, 30000);
    }

    logSessionHealth() {
        const healthInfo = {
            timestamp: new Date().toISOString(),
            sessionActive: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>,
            cookiesEnabled: navigator.cookieEnabled,
            webToNativeActive: this.isWebToNative,
            localStorage: typeof(Storage) !== "undefined",
            cookieUpdateCount: localStorage.getItem('webtonative_update_count') || '0'
        };

        console.log('📱 WebToNative Session Health:', healthInfo);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main session manager
    window.toolbarSessionManager = new UniversalSessionManager();
    
    // Initialize WebToNative session manager
    window.webToNativeSessionManager = new WebToNativeSessionManager();
    
    // Initialize Android debug console (only for WebToNative)
    window.androidDebug = new AndroidDebugConsole();
    
    // Store session initialization
    console.log('🚀 Enhanced Toolbar Session Management Initialized');
    
    // Debug info for WebToNative
    if (typeof WTN !== 'undefined') {
        console.log('🔧 WebToNative Full Debug Info:', window.webToNativeSessionManager.getWebToNativeDebugInfo());
    }
});

// Handle page unload for session preservation
window.addEventListener('beforeunload', function() {
    if (typeof(Storage) !== "undefined") {
        localStorage.setItem('sessionPreserved', 'true');
        localStorage.setItem('lastUnload', Date.now());
        localStorage.setItem('toolbarLastAccess', Date.now());
    }
    
    // Clean up session managers
    if (window.toolbarSessionManager) {
        window.toolbarSessionManager.destroy();
    }
    if (window.webToNativeSessionManager) {
        window.webToNativeSessionManager.destroy();
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

/* WebToNative specific styles */
<?php if ($isAndroidApp): ?>
#sessionStatusIndicator {
    border: 2px solid #28a745;
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
                         <?php if ($isAndroidApp): ?>
                         <span class="badge bg-success ms-2" style="font-size: 10px;">Android App</span>
                         <?php endif; ?>
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

                    <!-- WebToNative Debug Button -->
                    <?php if ($isAndroidApp): ?>
                    <div class="topbar-item">
                         <button type="button" class="topbar-button" id="webtonative-debug-btn" title="WebToNative Debug">
                              <iconify-icon icon="solar:bug-bold-duotone" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div>
                    <?php endif; ?>

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
                                      <?php if ($isAndroidApp): ?>
                                      <strong>WebToNative:</strong> 
                                      <span class="badge session-badge bg-info">Enabled</span><br>
                                      <?php endif; ?>
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

                              <!-- WebToNative Debug Link -->
                              <?php if ($isAndroidApp): ?>
                              <a class="dropdown-item" href="javascript:void(0)" onclick="androidDebug.togglePanel()">
                                  <i class="fas fa-bug fs-18 align-middle me-1"></i>
                                  <span class="align-middle">WebToNative Debug</span>
                              </a>
                              <?php endif; ?>
                                
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

    // WebToNative debug button functionality
    const webtonativeDebugBtn = document.getElementById('webtonative-debug-btn');
    if (webtonativeDebugBtn) {
        webtonativeDebugBtn.addEventListener('click', function() {
            if (window.androidDebug) {
                window.androidDebug.togglePanel();
            }
            
            // Force cookie update
            if (window.webToNativeSessionManager) {
                window.webToNativeSessionManager.forceCookieUpdate();
            }
            
            // Show WebToNative debug info
            if (window.webToNativeSessionManager) {
                const debugInfo = window.webToNativeSessionManager.getWebToNativeDebugInfo();
                console.log('🔧 WebToNative Debug Info:', debugInfo);
                alert('WebToNative Debug Info:\n' + JSON.stringify(debugInfo, null, 2));
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
            
            // Clean up session managers
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.destroy();
            }
            if (window.webToNativeSessionManager) {
                window.webToNativeSessionManager.destroy();
            }
            
            // Clear device-specific session storage
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem('current_player_id');
                localStorage.removeItem('lastKeepAlive');
                localStorage.removeItem('sessionInitialized');
                localStorage.removeItem('webtonative_update_count');
                localStorage.removeItem('webtonative_last_cookie_update');
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
                lastActivity: <?php echo isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 'null'; ?>,
                sessionId: '<?php echo session_id(); ?>'
            },
            javascript: stats,
            webtonative: window.webToNativeSessionManager ? window.webToNativeSessionManager.getWebToNativeDebugInfo() : null
        };
        console.log('🔍 Full Session Debug:', sessionInfo);
        return sessionInfo;
    }
    return null;
}
window.getSessionDebugInfo = debugSession;

// Force WebToNative cookie update (can be called from anywhere)
function forceWebToNativeCookieUpdate() {
    if (window.webToNativeSessionManager) {
        window.webToNativeSessionManager.forceCookieUpdate();
        return true;
    }
    return false;
}
window.forceWebToNativeCookieUpdate = forceWebToNativeCookieUpdate;
</script>
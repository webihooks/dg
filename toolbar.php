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
// Android Session Recovery System
class AndroidSessionRecovery {
    constructor() {
        this.isAndroidApp = <?php echo (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false) ? 'true' : 'false'; ?>;
        this.recoveryInterval = null;
        this.forceRecoveryInterval = null;
        this.init();
    }

    init() {
        if (!this.isAndroidApp) return;
        
        console.log('🔧 Starting Android Session Recovery System');
        
        this.startRecoveryMonitoring();
        this.startForceRecovery();
        this.setupRecoveryEventListeners();
        this.attemptImmediateRecovery();
    }

    startRecoveryMonitoring() {
        // Check session every 20 seconds
        this.recoveryInterval = setInterval(() => {
            this.checkAndRecoverSession();
        }, 20000);
    }

    startForceRecovery() {
        // Force recovery every 2 minutes as backup
        this.forceRecoveryInterval = setInterval(() => {
            this.forceSessionRecovery();
        }, 120000);
    }

    async checkAndRecoverSession() {
        try {
            const response = await fetch('session-keepalive.php?android_check=true&t=' + Date.now(), {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.status !== 'success') {
                console.warn('⚠️ Session check failed, attempting recovery');
                await this.forceSessionRecovery();
            } else {
                console.log('✅ Session check passed');
            }
        } catch (error) {
            console.error('❌ Session check error, forcing recovery:', error);
            await this.forceSessionRecovery();
        }
    }

    async forceSessionRecovery() {
        console.log('🔄 Forcing Android session recovery...');
        
        try {
            const response = await fetch('android_session_recovery.php?force=true&t=' + Date.now(), {
                credentials: 'include',
                headers: {
                    'X-Android-Force-Recovery': 'true'
                }
            });
            
            const data = await response.json();
            
            if (data.success && data.recovered) {
                console.log('✅ Android session recovery successful');
                
                // Force WebToNative cookie update
                this.forceCookieUpdate();
                
                // Update recovery stats
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastAndroidRecovery', Date.now());
                    const recoveryCount = parseInt(localStorage.getItem('androidRecoveryCount') || '0') + 1;
                    localStorage.setItem('androidRecoveryCount', recoveryCount);
                }
            } else {
                throw new Error('Recovery failed: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('❌ Android session recovery failed:', error);
            this.showRecoveryAlert();
        }
    }

    setupRecoveryEventListeners() {
        // Recover on any visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                setTimeout(() => {
                    this.forceSessionRecovery();
                    this.forceCookieUpdate();
                }, 500);
            }
        });

        // Recover on page load
        window.addEventListener('load', () => {
            setTimeout(() => {
                this.forceSessionRecovery();
            }, 2000);
        });

        // Recover on any user interaction
        const recoveryEvents = ['click', 'touchstart', 'keydown', 'scroll'];
        recoveryEvents.forEach(event => {
            document.addEventListener(event, () => {
                this.forceCookieUpdate();
            }, { passive: true });
        });
    }

    attemptImmediateRecovery() {
        // Immediate recovery attempts
        setTimeout(() => {
            this.forceSessionRecovery();
        }, 1000);
        
        setTimeout(() => {
            this.forceSessionRecovery();
        }, 5000);
        
        setTimeout(() => {
            this.forceSessionRecovery();
        }, 10000);
    }

    forceCookieUpdate() {
        if (typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
            try {
                WTN.forceUpdateCookies();
                console.log('✅ WebToNative Cookies Updated - ' + new Date().toLocaleTimeString());
            } catch (error) {
                console.error('❌ WebToNative Cookie Update Failed:', error);
            }
        }
    }

    showRecoveryAlert() {
        const alertDiv = document.createElement('div');
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 15px;
            border-radius: 8px;
            z-index: 10001;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: Arial, sans-serif;
        `;
        alertDiv.innerHTML = `
            <strong>🚨 Session Recovery Needed</strong>
            <p style="margin: 8px 0; font-size: 14px;">Please refresh the app to restore your session</p>
            <button onclick="location.reload()" style="
                background: white; 
                color: #dc3545; 
                border: none; 
                padding: 8px 15px; 
                border-radius: 4px; 
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
            ">Refresh Now</button>
        `;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 10000);
    }

    destroy() {
        if (this.recoveryInterval) {
            clearInterval(this.recoveryInterval);
        }
        if (this.forceRecoveryInterval) {
            clearInterval(this.forceRecoveryInterval);
        }
    }
}

// Initialize Android Session Recovery
document.addEventListener('DOMContentLoaded', function() {
    if (<?php echo (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false) ? 'true' : 'false'; ?>) {
        window.androidSessionRecovery = new AndroidSessionRecovery();
    }
});

















// Enhanced Universal Session Management - 365 Days with WebToNative
class UniversalSessionManager {
    constructor() {
        this.keepAliveInterval = 300000; // 5 minutes
        this.isAndroidApp = <?php echo json_encode($isAndroidApp); ?>;
        this.isWebToNative = typeof WTN !== 'undefined';
        this.healthCheckInterval = 120000; // 2 minutes for health checks
        this.heartbeatInterval = this.isAndroidApp ? 300000 : 600000; // 5 min Android, 10 min Web
        this.androidMonitorInterval = 30000; // 30 seconds for Android
        this.init();
    }

    init() {
        console.log('🚀 Universal Session Manager Initialized');
        console.log('📱 Android App:', this.isAndroidApp);
        console.log('🔧 WebToNative:', this.isWebToNative);
        console.log('❤️ Heartbeat Interval:', this.heartbeatInterval / 1000 + 's');
        
        this.startKeepAlive();
        this.startHealthChecks();
        this.startHeartbeat();
        this.setupVisibilityHandler();
        this.setupActivityHandlers();
        this.initializeSession();
        
        // Only setup WebToNative features if in WebToNative environment
        if (this.isWebToNative) {
            this.setupWebToNativeFeatures();
        }
        
        // Aggressive monitoring for Android apps
        if (this.isAndroidApp) {
            this.startAndroidAggressiveMonitoring();
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

    startAndroidAggressiveMonitoring() {
        console.log('📱 Starting aggressive Android session monitoring');
        
        // Monitor every 30 seconds for Android
        this.androidMonitorInterval = setInterval(() => {
            this.androidSessionHealthCheck();
        }, this.androidMonitorInterval);
        
        // Immediate health check
        setTimeout(() => {
            this.androidSessionHealthCheck();
        }, 5000);
    }

    async androidSessionHealthCheck() {
        if (!this.isAndroidApp) return;
        
        try {
            const response = await fetch('session-keepalive.php?android_health_check=true&t=' + Date.now(), {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'X-Android-Health-Check': 'true',
                    'Cache-Control': 'no-cache'
                }
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('✅ Android Session Health Check Passed');
                
                // Force cookie update after health check
                this.forceCookieUpdate();
                
                // Update session info in storage
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastAndroidHealthCheck', Date.now());
                }
            } else {
                console.warn('⚠️ Android Session Health Check Failed');
                this.attemptAndroidSessionRecovery();
            }
        } catch (error) {
            console.error('❌ Android Health Check Request Failed:', error);
            this.attemptAndroidSessionRecovery();
        }
    }

    async attemptAndroidSessionRecovery() {
        if (!this.isAndroidApp) return;
        
        console.log('🔄 Attempting Android session recovery...');
        
        try {
            // Try multiple recovery methods
            const recoveryPromises = [
                fetch('session-keepalive.php?android_recovery=true&t=' + Date.now(), {
                    credentials: 'include'
                }),
                fetch('heartbeat.php?android_recovery=true&t=' + Date.now(), {
                    credentials: 'include'
                })
            ];
            
            const results = await Promise.allSettled(recoveryPromises);
            
            let recoverySuccessful = false;
            for (const result of results) {
                if (result.status === 'fulfilled' && result.value.ok) {
                    const data = await result.value.json();
                    if (data.success || data.status === 'success') {
                        recoverySuccessful = true;
                        break;
                    }
                }
            }
            
            if (recoverySuccessful) {
                console.log('✅ Android session recovery successful');
                this.forceCookieUpdate();
                this.updateSessionStatus('recovered');
            } else {
                throw new Error('All recovery methods failed');
            }
            
        } catch (error) {
            console.error('❌ Android session recovery failed:', error);
            this.showAndroidRecoveryAlert();
        }
    }

    showAndroidRecoveryAlert() {
        // Create Android-specific recovery notification
        const recoveryDiv = document.createElement('div');
        recoveryDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff3cd;
            border: 2px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            z-index: 10001;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: Arial, sans-serif;
        `;
        recoveryDiv.innerHTML = `
            <strong>⚠️ Android Session Issue</strong>
            <p style="margin: 8px 0; font-size: 14px;">Your session needs refresh</p>
            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button onclick="location.reload()" style="
                    background: #856404; 
                    color: white; 
                    border: none; 
                    padding: 8px 15px; 
                    border-radius: 4px; 
                    cursor: pointer;
                    font-size: 14px;
                ">Refresh App</button>
                <button onclick="this.parentNode.parentNode.remove()" style="
                    background: none; 
                    border: 1px solid #856404; 
                    color: #856404; 
                    padding: 8px 15px; 
                    border-radius: 4px; 
                    cursor: pointer;
                    font-size: 14px;
                ">Dismiss</button>
            </div>
        `;
        document.body.appendChild(recoveryDiv);
        
        // Auto-remove after 15 seconds
        setTimeout(() => {
            if (recoveryDiv.parentNode) {
                recoveryDiv.parentNode.removeChild(recoveryDiv);
            }
        }, 15000);
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
                console.log('📱 WebToNative: App foreground - refreshing session');
                setTimeout(() => {
                    this.forceCookieUpdate();
                    this.keepSessionAlive();
                    this.androidSessionHealthCheck();
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
            localStorage.setItem('isWebToNative', this.isWebToNative.toString());
            localStorage.setItem('isAndroidApp', this.isAndroidApp.toString());
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
            const response = await fetch('session-keepalive.php?health_check=true&t=' + Date.now(), {
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
            
            if (data.status === 'success') {
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
            const response = await fetch('heartbeat.php?t=' + Date.now(), {
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
            const response = await fetch('/session-keepalive.php?keep_alive=true&t=' + Date.now(), {
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

    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Page became visible - refresh session immediately
                console.log('🔄 Toolbar Page visible - refreshing session');
                this.keepSessionAlive();
                this.performHealthCheck();
                
                // Additional session validation
                this.validateSessionState();
                
                // Android-specific: Aggressive session check
                if (this.isAndroidApp) {
                    this.androidSessionHealthCheck();
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

    // In setupActivityHandlers(), add error suppression:
    setupActivityHandlers() {
        const activities = ['scroll', 'touchstart', 'click'];
        activities.forEach(activity => {
            document.addEventListener(activity, () => {
                // Use debouncing to prevent rapid consecutive calls
                clearTimeout(this.activityTimeout);
                this.activityTimeout = setTimeout(() => {
                    this.keepSessionAlive().catch(() => {
                        // Silent fail - don't log network errors to console
                    });
                }, 1000); // 1 second debounce
            }, { passive: true });
        });
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
                    text: this.isWebToNative ? '📱 Android - Session Active (365 Days)' : '🌐 Web - Session Active (365 Days)', 
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
                'recovered': { 
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
            androidApp: localStorage.getItem('isAndroidApp') || 'false',
            sessionStart: localStorage.getItem('sessionStart'),
            lastHealthCheck: localStorage.getItem('lastHealthCheck'),
            lastAndroidHealthCheck: localStorage.getItem('lastAndroidHealthCheck'),
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
        if (this.androidMonitorInterval) {
            clearInterval(this.androidMonitorInterval);
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

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main session manager
    window.toolbarSessionManager = new UniversalSessionManager();
    
    // Store session initialization
    console.log('🚀 Enhanced Toolbar Session Management Initialized');
    
    // Show session status on first load
    setTimeout(() => {
        const statusElement = document.getElementById('sessionStatusIndicator');
        if (statusElement) {
            statusElement.style.display = 'block';
            setTimeout(() => {
                statusElement.style.display = 'none';
            }, 5000);
        }
    }, 2000);
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
});

// Enhanced activity monitoring
let lastActivityTime = Date.now();
document.addEventListener('mousemove', function() {
    lastActivityTime = Date.now();
});
document.addEventListener('keypress', function() {
    lastActivityTime = Date.now();
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
                userId: <?php echo isset($_SESSION['user_id']) ? json_encode($_SESSION['user_id']) : 'null'; ?>,
                isAndroid: <?php echo json_encode($isAndroidApp); ?>,
                lastActivity: <?php echo isset($_SESSION['last_activity']) ? json_encode($_SESSION['last_activity']) : 'null'; ?>,
                sessionId: <?php echo json_encode(session_id()); ?>
            },
            javascript: stats,
            timestamp: new Date().toISOString()
        };
        console.log('🔍 Full Session Debug:', sessionInfo);
        return sessionInfo;
    }
    return null;
}
window.getSessionDebugInfo = debugSession;

// Force WebToNative cookie update (can be called from anywhere)
function forceWebToNativeCookieUpdate() {
    if (window.toolbarSessionManager) {
        window.toolbarSessionManager.forceCookieUpdate();
        return true;
    }
    return false;
}
window.forceWebToNativeCookieUpdate = forceWebToNativeCookieUpdate;
</script>

<style>
.session-info {
    font-size: 11px;
    opacity: 0.8;
    display: block;
    margin-top: 2px;
}

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
                              </span>
                         </a>
                         <div class="dropdown-menu dropdown-menu-end">
                              <!-- User Info -->
                              <h6 class="dropdown-header">
                                  Welcome, <?php echo $user_name; ?>! 
                                  <span class="userid_class">ID: <?php echo $user_id; ?></span>
                              </h6>

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
            if (typeof androidDebug !== 'undefined') {
                androidDebug.togglePanel();
            }
            
            // Force cookie update
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.forceCookieUpdate();
            }
            
            // Show WebToNative debug info
            const debugInfo = window.getSessionDebugInfo();
            console.log('🔧 WebToNative Debug Info:', debugInfo);
            alert('WebToNative Debug Info:\n' + JSON.stringify(debugInfo, null, 2));
        });
    }
    
    // Enhanced logout - NO CONFIRMATION, device remains active
    const logoutButton = document.getElementById('logoutButton');
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            // NO CONFIRMATION DIALOG - proceed directly to logout
            
            // Clean up session managers
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.destroy();
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
            // Device remains active in database for push notifications
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
</script>
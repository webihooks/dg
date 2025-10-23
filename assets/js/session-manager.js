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
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('sessionInitialized', Date.now());
            localStorage.setItem('userAgent', navigator.userAgent);
            localStorage.setItem('sessionStart', new Date().toISOString());
        }
    }

    startKeepAlive() {
        this.keepSessionAlive();
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
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('✅ Session kept alive:', new Date().toLocaleTimeString());
                
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
                console.log('🔄 Page visible - refreshing session');
                this.keepSessionAlive();
                this.validateSessionState();
            }
        });
    }

    setupActivityHandlers() {
        const activities = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        activities.forEach(activity => {
            document.addEventListener(activity, () => {
                this.keepSessionAlive();
            }, { passive: true });
        });
    }

    validateSessionState() {
        if (typeof(Storage) !== "undefined") {
            const lastKeepAlive = localStorage.getItem('lastKeepAlive');
            if (lastKeepAlive && (Date.now() - parseInt(lastKeepAlive)) > 600000) {
                console.log('🔄 Session state validation triggered');
                this.keepSessionAlive();
            }
        }
    }

    destroy() {
        if (this.keepAliveTimer) {
            clearInterval(this.keepAliveTimer);
        }
    }
}

// Auto-initialize for logged-in users
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a protected page (user should be logged in)
    const protectedPages = ['admin-dashboard', 'sales-dashboard', 'subscription'];
    const currentPage = window.location.pathname.split('/').pop();
    
    if (protectedPages.some(page => currentPage.includes(page))) {
        window.sessionManager = new UniversalSessionManager();
        console.log('🚀 Universal Session Manager Initialized');
    }
});
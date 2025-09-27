// socket-manager.js - Aggressive PWA WebSocket Manager for Background Operation
class AggressivePWASocketManager {
    constructor() {
        this.socket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10; // Increased attempts
        this.reconnectInterval = 2000; // Faster reconnection
        this.keepAliveInterval = 15000; // More frequent pings (15 seconds)
        this.isBackground = false;
        this.pendingMessages = [];
        this.backgroundCheckInterval = 10000; // Check every 10 seconds in background
        this.forceReconnectTimer = null;
        
        // Aggressive background flags
        this.aggressiveMode = false;
        this.lastActivity = Date.now();
        
        this.init();
    }
    
    init() {
        this.setupAggressiveVisibilityHandling();
        this.setupServiceWorkerCommunication();
        this.setupBackgroundHeartbeat();
        this.connect();
        
        // Pre-cache critical resources
        this.preloadCriticalAssets();
    }
    
    connect() {
        try {
            // Multiple fallback endpoints for reliability
            const endpoints = [
                `wss://deegeecard.com/ws?token=${this.getAuthToken()}&aggressive=1`,
                `wss://ws1.deegeecard.com/ws?token=${this.getAuthToken()}`,
                `wss://ws2.deegeecard.com/ws?token=${this.getAuthToken()}`
            ];
            
            const wsUrl = endpoints[0]; // Primary endpoint
            this.socket = new WebSocket(wsUrl);
            
            // Aggressive timeout handling
            this.connectionTimeout = setTimeout(() => {
                if (this.socket && this.socket.readyState === WebSocket.CONNECTING) {
                    this.socket.close();
                    this.tryNextEndpoint(endpoints);
                }
            }, 5000);
            
            this.socket.onopen = () => {
                clearTimeout(this.connectionTimeout);
                console.log('🔄 WebSocket connected aggressively');
                this.reconnectAttempts = 0;
                this.startAggressiveKeepAlive();
                this.flushPendingMessages();
                this.enableAggressiveMode();
                
                // Notify service worker aggressively
                this.notifyServiceWorker('SOCKET_CONNECTED_AGGRESSIVE');
                
                // Force periodic reconnection to prevent stale connections
                this.scheduleForceReconnect();
            };
            
            this.socket.onmessage = (event) => {
                this.lastActivity = Date.now();
                this.handleMessage(JSON.parse(event.data));
            };
            
            this.socket.onclose = (event) => {
                clearTimeout(this.connectionTimeout);
                console.log('WebSocket disconnected, reconnecting aggressively...', event);
                this.handleAggressiveDisconnection();
            };
            
            this.socket.onerror = (error) => {
                clearTimeout(this.connectionTimeout);
                console.error('WebSocket error:', error);
            };
            
        } catch (error) {
            console.error('WebSocket connection failed:', error);
            this.scheduleImmediateReconnect();
        }
    }
    
    tryNextEndpoint(endpoints) {
        const currentIndex = endpoints.indexOf(this.socket?.url || '');
        const nextIndex = (currentIndex + 1) % endpoints.length;
        setTimeout(() => {
            this.connect();
        }, 1000);
    }
    
    handleMessage(data) {
        // Update last activity timestamp
        this.lastActivity = Date.now();
        
        switch (data.type) {
            case 'NEW_ORDER':
                this.handleNewOrderAggressive(data.order);
                break;
            case 'PONG':
                // Update connection health
                this.connectionHealthy = true;
                break;
            case 'ORDER_UPDATE':
                this.handleOrderUpdate(data.update);
                break;
            case 'SERVER_RESTART':
                // Server indicates restart - reconnect immediately
                this.immediateReconnect();
                break;
        }
    }
    
    handleNewOrderAggressive(order) {
        // Triple notification strategy
        this.updateOrderDisplayImmediate(order);
        this.playMultipleNotificationSounds();
        this.showPersistentBackgroundNotification(order);
        
        // Vibrate if available
        this.vibrateDevice();
        
        // Send receipt acknowledgement
        this.send({ type: 'ORDER_RECEIVED', orderId: order.id });
    }
    
    startAggressiveKeepAlive() {
        // Clear existing timers
        if (this.keepAliveTimer) clearInterval(this.keepAliveTimer);
        if (this.healthCheckTimer) clearInterval(this.healthCheckTimer);
        
        // Frequent pings
        this.keepAliveTimer = setInterval(() => {
            if (this.socket && this.socket.readyState === WebSocket.OPEN) {
                this.send({ 
                    type: 'PING', 
                    timestamp: Date.now(),
                    aggressive: true 
                });
            }
        }, this.keepAliveInterval);
        
        // Health check - more frequent in background
        this.healthCheckTimer = setInterval(() => {
            this.checkConnectionHealth();
        }, 10000); // Every 10 seconds
    }
    
    checkConnectionHealth() {
        const timeSinceActivity = Date.now() - this.lastActivity;
        
        if (timeSinceActivity > 30000) { // 30 seconds no activity
            console.warn('Connection seems stale, forcing reconnect');
            this.immediateReconnect();
            return;
        }
        
        if (this.isBackground && timeSinceActivity > 20000) {
            // Send health check message
            this.send({ type: 'HEALTH_CHECK', background: true });
        }
    }
    
    send(message) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            try {
                this.socket.send(JSON.stringify(message));
                this.lastActivity = Date.now();
            } catch (error) {
                this.pendingMessages.push(message);
                this.scheduleImmediateReconnect();
            }
        } else {
            this.pendingMessages.push(message);
            if (!this.isBackground) {
                this.scheduleImmediateReconnect();
            }
        }
    }
    
    flushPendingMessages() {
        const messagesToSend = [...this.pendingMessages];
        this.pendingMessages = [];
        
        messagesToSend.forEach(message => {
            this.send(message);
        });
    }
    
    handleAggressiveDisconnection() {
        clearInterval(this.keepAliveTimer);
        clearInterval(this.healthCheckTimer);
        clearTimeout(this.forceReconnectTimer);
        
        // Immediate reconnection attempt
        if (!this.isBackground) {
            this.scheduleImmediateReconnect();
        } else {
            // In background, use exponential backoff but more aggressive
            this.scheduleBackgroundReconnect();
        }
    }
    
    scheduleImmediateReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = Math.min(1000 * this.reconnectAttempts, 10000); // Max 10 second delay
            
            console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
            
            setTimeout(() => {
                this.connect();
            }, delay);
        } else {
            // Fallback to polling
            this.startAggressivePolling();
        }
    }
    
    scheduleBackgroundReconnect() {
        // More frequent reconnection attempts in background
        const delay = Math.min(2000 * (this.reconnectAttempts + 1), 15000);
        
        setTimeout(() => {
            this.connect();
        }, delay);
    }
    
    immediateReconnect() {
        if (this.socket) {
            this.socket.close();
        }
        setTimeout(() => this.connect(), 500);
    }
    
    setupAggressiveVisibilityHandling() {
        // Page visibility API
        document.addEventListener('visibilitychange', () => {
            this.isBackground = document.hidden;
            
            if (!this.isBackground) {
                // App came to foreground - aggressive reconnect
                console.log('🔔 App in foreground - aggressive mode');
                this.enableAggressiveMode();
                this.immediateReconnect();
            } else {
                // App going to background - enable super aggressive background mode
                console.log('🔔 App in background - super aggressive mode');
                this.enableBackgroundAggressiveMode();
                this.registerMultipleBackgroundSyncs();
            }
        });
        
        // Window focus/blur events as backup
        window.addEventListener('focus', () => {
            this.isBackground = false;
            this.enableAggressiveMode();
        });
        
        window.addEventListener('blur', () => {
            this.isBackground = true;
            this.enableBackgroundAggressiveMode();
        });
        
        // Network status monitoring
        window.addEventListener('online', () => {
            console.log('🌐 Network online - aggressive reconnect');
            this.immediateReconnect();
        });
        
        // Prevent sleep when important
        this.setupWakeLock();
    }
    
    setupWakeLock() {
        if ('wakeLock' in navigator) {
            try {
                navigator.wakeLock.request('screen').then(wakeLock => {
                    this.wakeLock = wakeLock;
                });
            } catch (err) {
                console.log('Wake Lock not supported');
            }
        }
    }
    
    setupServiceWorkerCommunication() {
        if ('serviceWorker' in navigator) {
            // Listen for messages from service worker
            navigator.serviceWorker.addEventListener('message', (event) => {
                const { type, data } = event.data;
                
                if (type === 'SOCKET_DATA') {
                    this.handleMessage(data);
                } else if (type === 'FORCE_RECONNECT') {
                    this.immediateReconnect();
                } else if (type === 'BACKGROUND_NEW_ORDER') {
                    this.handleNewOrderAggressive(data.order);
                }
            });
            
            // Send regular heartbeats to service worker
            setInterval(() => {
                this.notifyServiceWorker('HEARTBEAT', {
                    timestamp: Date.now(),
                    background: this.isBackground
                });
            }, 30000);
        }
    }
    
    setupBackgroundHeartbeat() {
        // Send regular heartbeats to prevent service worker termination
        this.backgroundHeartbeat = setInterval(() => {
            if (this.isBackground) {
                this.send({ type: 'BACKGROUND_HEARTBEAT' });
                this.notifyServiceWorker('BACKGROUND_ALIVE');
            }
        }, 45000); // Every 45 seconds
    }
    
    registerMultipleBackgroundSyncs() {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            navigator.serviceWorker.ready.then(registration => {
                // Register multiple sync tags for redundancy
                const syncTags = ['background-websocket', 'order-check', 'heartbeat'];
                
                syncTags.forEach(tag => {
                    registration.sync.register(tag)
                        .then(() => console.log(`Background sync registered: ${tag}`))
                        .catch(err => console.log(`Sync ${tag} failed:`, err));
                });
            });
        }
        
        // Fallback: Periodic background sync via setInterval
        this.backgroundSyncInterval = setInterval(() => {
            if (this.isBackground) {
                this.backgroundOrderCheck();
            }
        }, 60000); // Every minute
    }
    
    backgroundOrderCheck() {
        // Direct API call as fallback
        fetch('orders.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.getAuthToken()}`,
                'Background-Check': 'true'
            },
            keepalive: true // Important for background requests
        })
        .then(response => response.json())
        .then(orders => {
            if (orders && orders.length > 0) {
                orders.forEach(order => {
                    this.handleNewOrderAggressive(order);
                });
            }
        })
        .catch(error => console.log('Background check failed:', error));
    }
    
    enableAggressiveMode() {
        this.aggressiveMode = true;
        // Increase keep-alive frequency
        this.keepAliveInterval = 10000; // 10 seconds in foreground
        this.startAggressiveKeepAlive();
    }
    
    enableBackgroundAggressiveMode() {
        this.aggressiveMode = true;
        // Slightly less frequent in background to save battery but still aggressive
        this.keepAliveInterval = 20000; // 20 seconds in background
        this.startAggressiveKeepAlive();
    }
    
    scheduleForceReconnect() {
        // Force reconnect every 2 hours to prevent memory leaks/stale connections
        this.forceReconnectTimer = setTimeout(() => {
            console.log('🔄 Scheduled force reconnect');
            this.immediateReconnect();
        }, 2 * 60 * 60 * 1000); // 2 hours
    }
    
    notifyServiceWorker(type, data = {}) {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type,
                data: { 
                    ...data, 
                    timestamp: Date.now(),
                    aggressive: true 
                }
            });
        }
    }
    
    showPersistentBackgroundNotification(order) {
        if ('Notification' in window && Notification.permission === 'granted') {
            // Persistent notification that requires interaction
            const notification = new Notification('🚨 NEW ORDER - ACTION REQUIRED!', {
                body: `Order #${order.id} - ${order.items.length} items - Tap to view`,
                icon: '/images/dg_logo.png',
                badge: '/images/badge.png',
                tag: `order-${order.id}`,
                requireInteraction: true, // Stays until user acts
                vibrate: [200, 100, 200, 100, 200, 100, 200],
                actions: [
                    {
                        action: 'accept',
                        title: '✅ Accept Order',
                        icon: '/images/accept.png'
                    },
                    {
                        action: 'view',
                        title: '👀 View Details',
                        icon: '/images/view.png'
                    }
                ],
                data: { orderId: order.id }
            });
            
            notification.onclick = () => {
                window.focus();
                this.focusOrder(order.id);
            };
        }
    }
    
    playMultipleNotificationSounds() {
        // Play sound multiple times for important notifications
        const playSound = () => {
            const audio = new Audio('/assets/sounds/new_order.mp3');
            audio.play().catch(e => console.log('Sound play failed:', e));
        };
        
        playSound();
        // Second sound after delay if still in background
        if (this.isBackground) {
            setTimeout(playSound, 3000);
        }
    }
    
    vibrateDevice() {
        if (navigator.vibrate) {
            // Aggressive vibration pattern
            navigator.vibrate([500, 200, 500, 200, 500]);
        }
    }
    
    updateOrderDisplayImmediate(order) {
        // Immediate UI update with visual emphasis
        const event = new CustomEvent('newOrderAggressive', { 
            detail: { 
                order,
                priority: 'high',
                timestamp: Date.now()
            }
        });
        document.dispatchEvent(event);
        
        // Also update title/flash tab for attention
        this.flashTitle(`🚨 New Order #${order.id}`);
    }
    
    flashTitle(message) {
        const originalTitle = document.title;
        let flashCount = 0;
        const flashInterval = setInterval(() => {
            document.title = flashCount % 2 === 0 ? message : originalTitle;
            flashCount++;
            if (flashCount > 6) { // Flash 3 times
                clearInterval(flashInterval);
                document.title = originalTitle;
            }
        }, 500);
    }
    
    preloadCriticalAssets() {
        // Pre-load sounds and images for instant notification
        const criticalAssets = [
            '/assets/sounds/new_order.mp3',
            '/images/dg_logo.png',
            'orders.php'
        ];
        
        criticalAssets.forEach(asset => {
            fetch(asset, { cache: 'force-cache' })
                .catch(() => {}); // Silent fail
        });
    }
    
    getAuthToken() {
        // Multiple token storage for redundancy
        return localStorage.getItem('authToken') || 
               sessionStorage.getItem('authToken') || 
               '';
    }
}

// Ultra-aggressive initialization
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Starting aggressive PWA socket manager');
    window.socketManager = new AggressivePWASocketManager();
    
    // Additional aggressive optimizations
    if ('serviceWorker' in navigator) {
        // Force service worker update
        navigator.serviceWorker.ready.then(registration => {
            registration.update();
        });
    }
    
    // Prevent any potential sleep
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            // Request wake lock when visible
            if ('wakeLock' in navigator) {
                navigator.wakeLock.request('screen');
            }
        }
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AggressivePWASocketManager;
}
// sw.js - Aggressive Background Service Worker
const CACHE_NAME = 'deegeecard-aggressive-v4';
const BACKGROUND_SYNC_TAG = 'aggressive-order-check';
const PUSH_TIMEOUT = 30000; // 30 seconds push timeout

// Aggressive background intervals
const BACKGROUND_INTERVALS = {
    ORDER_CHECK: 1 * 60 * 1000, // 1 minute
    HEALTH_CHECK: 2 * 60 * 1000, // 2 minutes
    API_POLL: 30 * 1000, // 30 seconds when important
};

let isAggressiveMode = true;
let backgroundTimers = new Map();

// Install with aggressive caching
self.addEventListener('install', (event) => {
    console.log('🚀 Installing aggressive background service worker');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll([
                '/',
                '/login.php',
                '/dashboard.php',
                '/assets/sounds/new_order.mp3',
                'orders.php'
            ]))
            .then(() => self.skipWaiting()) // Activate immediately
    );
});

// Activate aggressively
self.addEventListener('activate', (event) => {
    console.log('🔛 Activating aggressive background mode');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            self.clients.claim(); // Take control immediately
            startBackgroundAggression(); // Start background tasks immediately
        })
    );
});

// Start aggressive background tasks
function startBackgroundAggression() {
    console.log('🔄 Starting aggressive background tasks');
    
    // Clear any existing timers
    clearAllBackgroundTimers();
    
    // Order checking every minute (even when app closed)
    startPeriodicOrderChecking();
    
    // Health checks
    startHealthMonitoring();
    
    // Network state monitoring
    startNetworkMonitoring();
}

function startPeriodicOrderChecking() {
    const timer = setInterval(() => {
        checkForNewOrdersAggressive();
    }, BACKGROUND_INTERVALS.ORDER_CHECK);
    
    backgroundTimers.set('order-check', timer);
    console.log('📡 Started aggressive order checking every minute');
}

function startHealthMonitoring() {
    const timer = setInterval(() => {
        performHealthCheck();
    }, BACKGROUND_INTERVALS.HEALTH_CHECK);
    
    backgroundTimers.set('health-check', timer);
}

function startNetworkMonitoring() {
    // Check network status frequently
    const timer = setInterval(() => {
        checkNetworkStatus();
    }, 30000);
    
    backgroundTimers.set('network-monitor', timer);
}

// Aggressive order checking
async function checkForNewOrdersAggressive() {
    try {
        console.log('🔍 Aggressive background order check');
        
        const response = await fetch('orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Background-Check': 'true',
                'Authorization': `Bearer ${await getStoredToken()}`
            },
            cache: 'no-cache'
        });
        
        if (response.ok) {
            const result = await response.json();
            if (result.success && result.orders && result.orders.length > 0) {
                console.log(`🎯 Found ${result.orders.length} new orders in background`);
                
                // Process each order aggressively
                result.orders.forEach(order => {
                    processNewOrderAggressive(order);
                });
                
                // Notify all clients (if any are open)
                notifyAllClients({
                    type: 'BACKGROUND_ORDERS_FOUND',
                    orders: result.orders,
                    timestamp: Date.now()
                });
            }
        }
    } catch (error) {
        console.log('Background order check failed, will retry:', error);
        // Auto-retry is handled by the interval
    }
}

// Process new orders with maximum aggression
function processNewOrderAggressive(order) {
    console.log('🚨 Processing new order aggressively:', order.id);
    
    // Show persistent notification
    showAggressiveNotification(order);
    
    // Play sound multiple times
    playAggressiveNotificationSound();
    
    // Store order for when app opens
    storePendingOrder(order);
    
    // Send to all open clients
    notifyAllClients({
        type: 'NEW_ORDER_AGGRESSIVE',
        order: order,
        background: true
    });
    
    // If no clients are open, schedule additional reminders
    checkIfClientsActive().then(hasClients => {
        if (!hasClients) {
            scheduleFollowUpNotification(order);
        }
    });
}

// Show ultra-aggressive notification
function showAggressiveNotification(order) {
    const options = {
        body: `URGENT: Order #${order.id} - ${order.items || 1} items waiting!`,
        icon: '/images/dg_logo.png',
        badge: '/images/badge.png',
        tag: `order-${order.id}-${Date.now()}`, // Unique tag to prevent grouping
        requireInteraction: true, // Must be dismissed manually
        vibrate: [500, 250, 500, 250, 500, 250, 1000],
        actions: [
            {
                action: 'accept',
                title: '✅ Accept Now',
                icon: '/images/accept.png'
            },
            {
                action: 'view',
                title: '👀 View Details',
                icon: '/images/view.png'
            },
            {
                action: 'snooze',
                title: '⏰ Remind in 2 min',
                icon: '/images/snooze.png'
            }
        ],
        data: {
            order: order,
            timestamp: Date.now(),
            priority: 'urgent'
        },
        silent: false // Ensure sound plays
    };
    
    // Add sound if supported
    if (self.registration.showNotification.length > 2) {
        options.sound = '/assets/sounds/new_order.mp3';
    }
    
    self.registration.showNotification('🚨 NEW ORDER - ACTION REQUIRED!', options)
        .then(() => console.log('Aggressive notification shown'))
        .catch(err => console.log('Notification failed:', err));
}

// Play sound aggressively
function playAggressiveNotificationSound() {
    // This will play through the notification system
    // Additional sound handling can be done when clients are active
}

// Background Sync with aggressive retry
self.addEventListener('sync', (event) => {
    console.log('🔄 Background sync triggered:', event.tag);
    
    if (event.tag === BACKGROUND_SYNC_TAG) {
        event.waitUntil(
            checkForNewOrdersAggressive().catch(error => {
                console.log('Background sync failed, retrying aggressively');
                // Retry sync registration for immediate retry
                return self.registration.sync.register(BACKGROUND_SYNC_TAG);
            })
        );
    }
});

// Periodic Sync (for browsers that support it)
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'aggressive-order-check') {
        event.waitUntil(checkForNewOrdersAggressive());
    }
});

// Push notifications with aggressive handling
self.addEventListener('push', (event) => {
    console.log('📢 Push notification received aggressively');
    
    if (!event.data) {
        // If no data, still check for orders
        event.waitUntil(checkForNewOrdersAggressive());
        return;
    }
    
    let data;
    try {
        data = event.data.json();
    } catch (e) {
        data = { title: 'New Order!', body: 'Check your orders' };
    }
    
    // Show notification immediately
    event.waitUntil(
        self.registration.showNotification(data.title || 'DEEGEECARD Alert', {
            body: data.body || 'New activity requires your attention',
            icon: '/images/dg_logo.png',
            badge: '/images/badge.png',
            tag: 'push-notification',
            requireInteraction: true,
            data: data
        }).then(() => {
            // After showing push, check for orders anyway
            return checkForNewOrdersAggressive();
        })
    );
});

// Notification click handling
self.addEventListener('notificationclick', (event) => {
    console.log('🔔 Notification clicked aggressively');
    event.notification.close();
    
    const action = event.action;
    const notificationData = event.notification.data;
    
    // Handle different actions
    if (action === 'accept' || action === 'view') {
        event.waitUntil(
            clients.openWindow('/dashboard.php?page=orders&order=' + 
                (notificationData.order ? notificationData.order.id : ''))
        );
    } else if (action === 'snooze') {
        // Schedule reminder for 2 minutes later
        event.waitUntil(
            scheduleReminder(notificationData.order, 2 * 60 * 1000)
        );
    } else {
        // Default: open app
        event.waitUntil(
            clients.openWindow('/dashboard.php')
        );
    }
    
    // Notify all clients about the click
    event.waitUntil(
        notifyAllClients({
            type: 'NOTIFICATION_CLICKED',
            action: action,
            data: notificationData
        })
    );
});

// Schedule reminder notification
function scheduleReminder(order, delay) {
    return new Promise((resolve) => {
        setTimeout(() => {
            showAggressiveNotification(order);
            resolve();
        }, delay);
    });
}

// Message handling from main app or other clients
self.addEventListener('message', (event) => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'ENABLE_AGGRESSIVE_MODE':
            enableAggressiveMode(data);
            break;
            
        case 'CHECK_ORDERS_NOW':
            checkForNewOrdersAggressive();
            break;
            
        case 'GET_BACKGROUND_STATUS':
            event.ports[0].postMessage({
                aggressiveMode: isAggressiveMode,
                timers: Array.from(backgroundTimers.keys())
            });
            break;
            
        case 'FORCE_ORDER_CHECK':
            checkForNewOrdersAggressive();
            break;
    }
});

// Network status monitoring
async function checkNetworkStatus() {
    try {
        const response = await fetch('/api/health-check.php', {
            method: 'HEAD',
            cache: 'no-cache'
        });
        
        if (!response.ok) {
            console.log('🌐 Network issues detected');
            // Could trigger alternative strategies
        }
    } catch (error) {
        console.log('🌐 Network offline, background will retry');
    }
}

// Health check
async function performHealthCheck() {
    // Verify service worker can still make requests
    try {
        await checkForNewOrdersAggressive();
    } catch (error) {
        console.log('Health check failed, but background continues');
    }
}

// Utility functions
async function getStoredToken() {
    // Get auth token from IndexedDB or cache
    const cache = await caches.open('auth-store');
    const response = await cache.match('/auth/token');
    if (response) {
        const data = await response.json();
        return data.token || '';
    }
    return '';
}

function notifyAllClients(message) {
    return clients.matchAll().then(clients => {
        clients.forEach(client => {
            client.postMessage(message);
        });
        return clients.length > 0;
    });
}

function checkIfClientsActive() {
    return clients.matchAll().then(clients => {
        return clients.length > 0;
    });
}

function storePendingOrder(order) {
    // Store in IndexedDB for when app opens
    return idbKeyval.set(`pending-order-${order.id}`, {
        order: order,
        timestamp: Date.now(),
        notified: true
    });
}

function clearAllBackgroundTimers() {
    backgroundTimers.forEach((timer, key) => {
        clearInterval(timer);
    });
    backgroundTimers.clear();
}

function enableAggressiveMode(settings) {
    isAggressiveMode = true;
    if (settings && settings.intervals) {
        Object.assign(BACKGROUND_INTERVALS, settings.intervals);
    }
    startBackgroundAggression();
}

// Start background aggression immediately when SW activates
startBackgroundAggression();
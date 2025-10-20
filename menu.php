<!-- Session Keep Alive -->
<script>
// Enhanced TWA Session Management
class TWASessionManager {
    constructor() {
        this.keepAliveInterval = 300000; // 5 minutes
        this.isTWA = this.detectTWA();
        this.init();
    }

    detectTWA() {
        // Check if running in TWA
        return window.navigator.standalone || 
               document.referrer.includes('android-app://') ||
               /Chrome/.test(navigator.userAgent) && !/Edge/.test(navigator.userAgent);
    }

    init() {
        if (this.isTWA) {
            console.log('TWA environment detected - enabling enhanced session management');
            this.startKeepAlive();
            this.setupVisibilityHandler();
            this.setupBeforeUnload();
        }
    }

    startKeepAlive() {
        // IMMEDIATE keep-alive on load
        this.keepSessionAlive();
        
        // Periodic keep-alive
        setInterval(() => {
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
                    'Pragma': 'no-cache'
                }
            });
            
            if (response.ok) {
                console.log('Session keep-alive successful');
            } else {
                console.warn('Session keep-alive failed');
            }
        } catch (error) {
            console.error('Keep-alive request failed:', error);
        }
    }

    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // App became visible - refresh session
                this.keepSessionAlive();
            }
        });
    }

    setupBeforeUnload() {
        window.addEventListener('beforeunload', () => {
            // Store session state in localStorage as backup
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem('twaSessionPreserved', 'true');
                localStorage.setItem('twaLastActive', Date.now());
            }
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new TWASessionManager();
});    
</script>
<!-- Session Keep Alive -->













<!-- FCM START -->
<script>
// FCM Notification Service - Clean Working Version
class FCMService {
    constructor() {
        this.vapidPublicKey = 'BA_40giep4c7wQZcDwmq_u23SFwDrgPwoCFrrPt2MR-aCMBW324yqvAsATjlzowX4cCtSbh1a7fC10rxi_3IY3U';
        this.isSubscribed = false;
        this.registration = null;
        this.userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>;
    }

    // Initialize FCM
    async initFCM() {
        console.log('🔄 FCM: Starting initialization...');
        
        if (!('serviceWorker' in navigator)) {
            console.error('❌ FCM: Service Worker not supported');
            return false;
        }

        if (!('PushManager' in window)) {
            console.error('❌ FCM: Push Manager not supported');
            return false;
        }

        if (!this.userId || this.userId === 0) {
            console.error('❌ FCM: User ID not available');
            return false;
        }

        console.log('✅ FCM: Browser supports push notifications');
        console.log('👤 FCM: User ID:', this.userId);

        try {
            // Register FCM service worker
            console.log('🔄 FCM: Registering service worker...');
            this.registration = await navigator.serviceWorker.register('/fcm-sw.js');
            console.log('✅ FCM: Service Worker registered successfully');

            // Wait for service worker to be ready
            await navigator.serviceWorker.ready;
            console.log('✅ FCM: Service Worker is ready');

            // Check current subscription
            const subscription = await this.registration.pushManager.getSubscription();
            
            if (subscription) {
                console.log('✅ FCM: Already subscribed to push notifications');
                this.isSubscribed = true;
                await this.saveSubscription(subscription);
                return true;
            }

            // Request notification permission
            console.log('🔄 FCM: Requesting notification permission...');
            const permission = await Notification.requestPermission();
            
            if (permission !== 'granted') {
                console.error('❌ FCM: Notification permission denied');
                return false;
            }

            console.log('✅ FCM: Notification permission granted');

            // Subscribe to push notifications
            console.log('🔄 FCM: Subscribing to push notifications...');
            const newSubscription = await this.subscribeToPush();
            
            if (newSubscription) {
                console.log('✅ FCM: Successfully subscribed to push notifications');
                this.isSubscribed = true;
                await this.saveSubscription(newSubscription);
                this.showSuccessMessage();
                return true;
            } else {
                console.error('❌ FCM: Failed to subscribe to push notifications');
                return false;
            }

        } catch (error) {
            console.error('❌ FCM: Initialization failed:', error);
            this.showErrorMessage(error.message);
            return false;
        }
    }

    // Subscribe to push notifications
    async subscribeToPush() {
        try {
            console.log('🔄 FCM: Creating push subscription...');
            
            const subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
            });
            
            console.log('✅ FCM: Push subscription created');
            console.log('📱 FCM: Endpoint:', subscription.endpoint);
            
            return subscription;
        } catch (error) {
            console.error('❌ FCM: Subscription failed:', error);
            return null;
        }
    }

    // Save subscription to server
    async saveSubscription(subscription) {
        try {
            console.log('🔄 FCM: Saving subscription to server...');
            
            const response = await fetch('save_fcm_token.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    token: JSON.stringify(subscription),
                    user_id: this.userId
                })
            });

            const result = await response.json();
            
            if (result.success) {
                console.log('✅ FCM: Token saved successfully on server');
                return true;
            } else {
                console.error('❌ FCM: Failed to save token:', result.message);
                return false;
            }
        } catch (error) {
            console.error('❌ FCM: Error saving token:', error);
            return false;
        }
    }

    // Convert VAPID key
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        
        return outputArray;
    }

    // Show success message
    showSuccessMessage() {
        console.log('🎉 FCM: Push notifications are now enabled!');
        if (typeof showToast === 'function') {
            showToast('🔔 Push notifications enabled! You will receive alerts for new orders.', 'success');
        }
    }

    // Show error message
    showErrorMessage(error) {
        console.error('❌ FCM: Setup failed:', error);
    }

    // Test FCM functionality
    async testFCM() {
        console.log('🧪 Testing FCM configuration...');
        
        // Check service worker
        if (!this.registration) {
            console.error('Service worker not registered');
            return false;
        }
        
        // Check subscription
        const subscription = await this.registration.pushManager.getSubscription();
        if (!subscription) {
            console.error('No push subscription found');
            return false;
        }
        
        console.log('FCM Subscription details:', {
            endpoint: subscription.endpoint,
            keys: subscription.options.applicationServerKey ? 'Present' : 'Missing'
        });
        
        return true;
    }
}

// Initialize FCM when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initializing FCM Service...');
    
    // Wait a bit for the page to load completely
    setTimeout(async function() {
        const fcmService = new FCMService();
        window.fcmService = fcmService;
        
        // Initialize FCM
        await fcmService.initFCM();
        
        console.log('✅ FCM initialization completed');
        
        // Test FCM after initialization
        setTimeout(async () => {
            await fcmService.testFCM();
        }, 1000);
        
    }, 3000);
});

// Manual FCM registration function
window.manualFCMRegister = async function() {
    if (window.fcmService) {
        console.log('🔄 Manually triggering FCM registration...');
        await window.fcmService.initFCM();
    } else {
        console.error('❌ FCM service not available');
    }
};

// Test push notification function
window.testPushNotification = async function() {
    try {
        console.log('🧪 Sending test push notification...');
        const response = await fetch('test_push.php');
        const result = await response.json();
        
        if (result.success) {
            console.log('✅ Test push sent successfully');
            if (typeof showToast === 'function') {
                showToast('Test push notification sent! Check your device.', 'success');
            }
        } else {
            console.error('❌ Test push failed:', result.message);
            if (typeof showToast === 'function') {
                showToast('Test push failed: ' + result.message, 'danger');
            }
        }
    } catch (error) {
        console.error('❌ Error sending test:', error);
        if (typeof showToast === 'function') {
            showToast('Test failed: ' + error.message, 'danger');
        }
    }
};

// Add FCM initialization to your existing order system
const originalInitOrderSystem = initOrderSystem;
initOrderSystem = async function() {
    console.log('Initializing order system with FCM support...');
    
    await initAudioSystem();
    initOrderPolling();
    setupEventListeners();
    
    // FCM is already initialized in DOMContentLoaded
    console.log('Order system initialized with FCM support');
};













// Add this to your existing FCM code in menu.php

// Test new order simulation function
window.testNewOrderSimulation = async function() {
    try {
        console.log('🧪 Simulating new order...');
        const response = await fetch('test_new_order.php');
        const result = await response.json();
        
        if (result.success) {
            console.log('✅ New order simulation successful!');
            if (typeof showToast === 'function') {
                showToast('New order simulation sent! Check your device for push notification with ring sound.', 'success');
            }
            
            // Show order details in console
            console.log('📋 Order Details:', result.order_details);
        } else {
            console.error('❌ New order simulation failed:', result.message);
            if (typeof showToast === 'function') {
                showToast('New order simulation failed: ' + result.message, 'danger');
            }
        }
    } catch (error) {
        console.error('❌ Error simulating new order:', error);
        if (typeof showToast === 'function') {
            showToast('Simulation failed: ' + error.message, 'danger');
        }
    }
};

// Add test button to debug panel (if you have one)
function addNewOrderTestButton() {
    if (document.getElementById('fcmDebugPanel')) {
        const testButton = document.createElement('button');
        testButton.innerHTML = '🧪 Test New Order';
        testButton.onclick = testNewOrderSimulation;
        testButton.style.cssText = `
            padding: 10px 20px;
            margin: 10px;
            background: #ff6c2f;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        `;
        document.getElementById('fcmDebugPanel').appendChild(testButton);
    }
}

// Call this after FCM initialization
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(addNewOrderTestButton, 5000);
});
</script>

<script>
// =============================================
// SERVICE WORKER MESSAGE HANDLER
// =============================================
// Handle messages from service worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', function(event) {
        console.log('📨 Received message from Service Worker:', event.data);
        
        const message = event.data;
        
        switch (message.type) {
            case 'NEW_ORDER_PUSH_NOTIFICATION':
                console.log('🎯 New order from push notification');
                // Start continuous ringing
                if (multiTrackAudio && !multiTrackAudio.isPlaying) {
                    multiTrackAudio.playContinuous();
                }
                // Show notification UI
                showVisualNotification();
                break;
                
            case 'STOP_NOTIFICATION_SOUND':
                console.log('🔇 Stopping notification sound');
                if (multiTrackAudio) {
                    multiTrackAudio.stop();
                }
                break;
                
            case 'NOTIFICATION_ACTION_ACCEPT':
                console.log('✅ Notification accept action received');
                // You can auto-accept orders here if needed
                break;
                
            case 'NOTIFICATION_ACTION_VIEW':
                console.log('📋 Notification view action received');
                // Focus on the specific order
                if (message.orderId) {
                    window.location.href = `orders.php?highlight_order=${message.orderId}`;
                }
                break;
        }
    });
}

// =============================================
// SERVICE WORKER REGISTRATION CHECK
// =============================================
function checkServiceWorker() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then(registration => {
            if (registration) {
                console.log('✅ Service Worker is registered:', registration);
                console.log('✅ Service Worker state:', registration.active?.state);
            } else {
                console.log('❌ No Service Worker registration found');
            }
        });
    }
}

// Check service worker status on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(checkServiceWorker, 2000);
});
</script>
<!-- FCM END -->



























<style>
/* Rejection dialog styles */
.rejection-dialog {
    animation: slideInUp 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.rejection-option {
    padding: 10px;
    margin: 5px 0;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.rejection-option:hover {
    background: #f8f9fa;
    border-color: #dc3545;
}

.rejection-option.selected {
    background: #fff5f5;
    border-color: #dc3545;
    color: #dc3545;
}

/* Additional styles for the enhanced order popup */
@keyframes slideInUp {
    from {
        transform: translate(-50%, 100%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
}

#floatingActionButtons {
    animation: slideInUp 0.3s ease-out;
}

.order-item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #f0f0f0;
}

.order-item-row:last-child {
    border-bottom: none;
}

.order-item-name {
    flex: 1;
    font-size: 13px;
}

.order-item-qty {
    font-weight: bold;
    color: #333;
    margin: 0 10px;
}

.order-item-price {
    font-weight: bold;
    color: #28a745;
}

.customer-address {
    background: #e9f7fe;
    border-radius: 5px;
    padding: 8px 10px;
    margin: 8px 0;
    border-left: 3px solid #17a2b8;
    font-size: 13px;
}

.order-total {
    background: #f8f9fa;
    border-radius: 5px;
    padding: 8px 10px;
    margin-top: 8px;
    font-weight: bold;
    text-align: right;
    border-top: 2px solid #dee2e6;
}
</style>

<script>
// Global polling configuration
const POLLING_CONFIG = {
    interval: 1000,
    active: true,
    lastOrderId: 0,
    isReloading: false,
    pageLoadTime: Math.floor(Date.now() / 1000),
    pendingOrders: new Map(),
    isSoundPlaying: false,
    audioElement: null,
    audioRetryCount: 0,
    maxAudioRetries: 3,
    refreshInterval: 5000, // Refresh pending orders every 5 seconds
    lastRefreshTime: Date.now(),
    autoRefreshEnabled: true // Enable auto page refresh
};

// Main initialization
async function initOrderSystem() {
    console.log('Initializing order system with continuous MP3 playback...');
    
    await initAudioSystem();
    initOrderPolling();
    setupEventListeners();
    
    console.log('Order system initialized');
}

// Audio System - Focus on continuous MP3 playback
async function initAudioSystem() {
    console.log('Initializing audio system...');
    
    // Create audio element for continuous playback
    POLLING_CONFIG.audioElement = new Audio();
    POLLING_CONFIG.audioElement.src = 'assets/sounds/new_order.mp3?' + Date.now(); // Cache buster
    POLLING_CONFIG.audioElement.loop = true; // Continuous looping
    POLLING_CONFIG.audioElement.volume = 0.9; // 90% volume
    POLLING_CONFIG.audioElement.preload = 'auto';
    
    // Event listeners for audio element
    POLLING_CONFIG.audioElement.addEventListener('canplaythrough', () => {
        console.log('Audio ready for playback');
    });
    
    POLLING_CONFIG.audioElement.addEventListener('error', (e) => {
        console.error('Audio error:', e);
        retryAudioLoad();
    });
    
    POLLING_CONFIG.audioElement.addEventListener('ended', () => {
        // Should not happen with loop=true, but just in case
        if (POLLING_CONFIG.isSoundPlaying) {
            playContinuousSound();
        }
    });
    
    // Load the audio
    POLLING_CONFIG.audioElement.load();
}

function retryAudioLoad() {
    if (POLLING_CONFIG.audioRetryCount >= POLLING_CONFIG.maxAudioRetries) {
        console.error('Max audio retries reached');
        return;
    }
    
    POLLING_CONFIG.audioRetryCount++;
    console.log(`Retrying audio load (attempt ${POLLING_CONFIG.audioRetryCount})`);
    
    setTimeout(() => {
        POLLING_CONFIG.audioElement.src = 'assets/sounds/new_order.mp3?' + Date.now();
        POLLING_CONFIG.audioElement.load();
    }, 1000);
}

// Play continuous sound with aggressive retry strategy
function playContinuousSound() {
    if (POLLING_CONFIG.isSoundPlaying) return;
    
    console.log('Starting continuous sound playback');
    POLLING_CONFIG.isSoundPlaying = true;
    
    const playSound = () => {
        if (!POLLING_CONFIG.isSoundPlaying) return;
        
        try {
            POLLING_CONFIG.audioElement.currentTime = 0;
            POLLING_CONFIG.audioElement.loop = true;
            
            const playPromise = POLLING_CONFIG.audioElement.play();
            
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    console.log('Continuous sound playing successfully');
                }).catch(error => {
                    console.log('Playback blocked, will retry:', error);
                    
                    // Aggressive retry strategy
                    setTimeout(() => {
                        if (POLLING_CONFIG.isSoundPlaying) {
                            playSound();
                        }
                    }, 1000);
                });
            }
        } catch (error) {
            console.error('Playback error:', error);
            // Retry after delay
            setTimeout(() => {
                if (POLLING_CONFIG.isSoundPlaying) {
                    playSound();
                }
            }, 2000);
        }
    };
    
    // Initial play attempt
    playSound();
    
    // Additional periodic play attempts to overcome browser restrictions
    const keepAliveInterval = setInterval(() => {
        if (!POLLING_CONFIG.isSoundPlaying) {
            clearInterval(keepAliveInterval);
            return;
        }
        
        // If audio is paused (might happen in background), try to resume
        if (POLLING_CONFIG.audioElement.paused) {
            console.log('Audio paused, attempting to resume...');
            playSound();
        }
    }, 3000);
}

function stopContinuousSound() {
    if (!POLLING_CONFIG.isSoundPlaying) return;
    
    console.log('Stopping continuous sound');
    POLLING_CONFIG.isSoundPlaying = false;
    
    try {
        POLLING_CONFIG.audioElement.pause();
        POLLING_CONFIG.audioElement.currentTime = 0;
        POLLING_CONFIG.audioElement.loop = false;
    } catch (error) {
        console.error('Error stopping sound:', error);
    }
}

// Enhanced notification function
function notifyNewOrder() {
    console.log('New order notification triggered');
    
    // Always try to play the continuous MP3 sound
    if (!POLLING_CONFIG.isSoundPlaying) {
        playContinuousSound();
    }
    
    // Visual notifications (optional)
    showVisualNotification();
    showOrderActionButtons();
}

// Visual notification (minimal - just the action buttons)
function showVisualNotification() {
    // Simple tab title update
    const originalTitle = document.title;
    if (!originalTitle.includes('🔔')) {
        document.title = '🔔 ' + originalTitle;
        
        // Restore title after 10 seconds
        setTimeout(() => {
            if (document.title.includes('🔔')) {
                document.title = originalTitle;
            }
        }, 10000);
    }
}

// Polling system
function initOrderPolling() {
    // Set initial lastOrderId
    const orderElements = document.querySelectorAll('[data-order-id]');
    if (orderElements.length > 0) {
        const orderIds = Array.from(orderElements)
            .map(el => parseInt(el.getAttribute('data-order-id')))
            .filter(id => !isNaN(id));
        
        if (orderIds.length > 0) {
            POLLING_CONFIG.lastOrderId = Math.max(...orderIds);
        }
    }
    
    console.log('Starting order polling');
    checkForNewOrders();
    
    // Start periodic refresh of pending orders
    setInterval(refreshPendingOrders, POLLING_CONFIG.refreshInterval);
}

function setupEventListeners() {
    // Resume audio on any user interaction
    const resumeEvents = ['click', 'mousedown', 'touchstart', 'keydown', 'focus'];
    
    resumeEvents.forEach(event => {
        document.addEventListener(event, () => {
            if (POLLING_CONFIG.isSoundPlaying && POLLING_CONFIG.audioElement.paused) {
                console.log('User interaction detected, resuming audio...');
                playContinuousSound();
            }
        }, { passive: true });
    });
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', () => {
        const isVisible = !document.hidden;
        console.log('Tab visibility changed:', isVisible);
        
        if (isVisible && POLLING_CONFIG.isSoundPlaying && POLLING_CONFIG.audioElement.paused) {
            // Tab became visible - try to resume playback
            setTimeout(() => {
                playContinuousSound();
            }, 500);
        }
    });
}

function checkForNewOrders() {
    if (POLLING_CONFIG.isReloading) return;
    
    const pageLoadTime = sessionStorage.getItem('pageLoadTime') || POLLING_CONFIG.pageLoadTime;
    
    fetch(`check_new_orders.php?last_order_id=${POLLING_CONFIG.lastOrderId}&page_load_time=${pageLoadTime}&t=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('API error:', data.error);
                return;
            }
            
            if (data.new_orders && data.new_orders.length > 0) {
                handleNewOrders(data.new_orders);
            }
        })
        .catch(error => {
            console.error('Polling error:', error);
        })
        .finally(() => {
            if (POLLING_CONFIG.active && !POLLING_CONFIG.isReloading) {
                setTimeout(checkForNewOrders, POLLING_CONFIG.interval);
            }
        });
}

// Refresh pending orders to remove orders that were processed by other devices
function refreshPendingOrders() {
    if (POLLING_CONFIG.pendingOrders.size === 0) return;
    
    fetch(`check_new_orders.php?last_order_id=0&page_load_time=${POLLING_CONFIG.pageLoadTime}&t=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.new_orders) {
                updatePendingOrdersList(data.new_orders);
            }
        })
        .catch(error => console.error('Refresh error:', error));
}

function updatePendingOrdersList(newOrders) {
    const currentPendingIds = new Set(POLLING_CONFIG.pendingOrders.keys());
    const newPendingIds = new Set(newOrders.map(order => order.order_id));
    
    let ordersWereRemoved = false;
    
    // Remove orders that are no longer pending
    currentPendingIds.forEach(orderId => {
        if (!newPendingIds.has(orderId)) {
            POLLING_CONFIG.pendingOrders.delete(orderId);
            console.log(`Order #${orderId} removed from pending list (processed by another device)`);
            ordersWereRemoved = true;
        }
    });
    
    // Update UI if orders were removed
    if (currentPendingIds.size !== POLLING_CONFIG.pendingOrders.size) {
        updateUI();
    }
    
    // Auto-refresh page if orders were processed by another device
    if (ordersWereRemoved && POLLING_CONFIG.autoRefreshEnabled) {
        autoRefreshPage();
    }
}

// Auto refresh page when orders are processed by other devices
function autoRefreshPage() {
    console.log('Orders were processed by another device - refreshing page...');
    
    // Show refresh notification
    showToast('Orders updated by another device. Refreshing page...', 'info');
    
    // Refresh after a short delay to show the notification
    setTimeout(() => {
        POLLING_CONFIG.isReloading = true;
        window.location.reload();
    }, 2000);
}

function handleNewOrders(newOrders) {
    const newMaxOrderId = Math.max(POLLING_CONFIG.lastOrderId, ...newOrders.map(o => o.order_id));
    
    if (newMaxOrderId > POLLING_CONFIG.lastOrderId) {
        POLLING_CONFIG.lastOrderId = newMaxOrderId;
        
        let hasNewPending = false;
        newOrders.forEach(order => {
            if (order.status === 'Pending' && !POLLING_CONFIG.pendingOrders.has(order.order_id)) {
                POLLING_CONFIG.pendingOrders.set(order.order_id, order);
                hasNewPending = true;
            }
        });
        
        if (hasNewPending) {
            console.log(`New pending orders detected: ${POLLING_CONFIG.pendingOrders.size}`);
            
            // Trigger continuous MP3 playback
            notifyNewOrder();
            
            // Show toast notification
            showToast(`New order received! Pending: ${POLLING_CONFIG.pendingOrders.size}`, 'success');
        }
    }
    
    updateUI();
}

function updateUI() {
    if (POLLING_CONFIG.pendingOrders.size > 0) {
        if (!document.getElementById('floatingActionButtons')) {
            showOrderActionButtons();
        } else {
            updateOrderActionButtons();
        }
    } else {
        hideOrderActionButtons();
        stopContinuousSound();
        // Restore original title
        document.title = document.title.replace('🔔 ', '');
    }
}

// Order Action Buttons with Order Details
function showOrderActionButtons() {
    hideOrderActionButtons();
    
    const buttonContainer = document.createElement('div');
    buttonContainer.id = 'floatingActionButtons';
    buttonContainer.style.cssText = `
        position: fixed;
        bottom: 15px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 15px;
        align-items: center;
        background: rgba(255, 255, 255, 0.98);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255,255,255,0.3);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    `;
    
    // Header
    const header = document.createElement('div');
    header.style.cssText = `
        text-align: center;
        width: 100%;
    `;
    header.innerHTML = `
        <h4 style="margin: 0; color: #333; font-weight: bold;">
            🔔 New Orders Pending (${POLLING_CONFIG.pendingOrders.size})
        </h4>
        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
            Action Required
        </p>
    `;
    buttonContainer.appendChild(header);
    
    // Order Details Container
    const ordersContainer = document.createElement('div');
    ordersContainer.style.cssText = `
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        background: #f8f9fa;
        margin-bottom: 10px;
    `;
    
    // Add each order's details
    POLLING_CONFIG.pendingOrders.forEach((order, orderId) => {
        const orderElement = createOrderElement(order, orderId);
        ordersContainer.appendChild(orderElement);
    });
    
    buttonContainer.appendChild(ordersContainer);
    
    // Action Buttons Container
    const actionButtonsContainer = document.createElement('div');
    actionButtonsContainer.style.cssText = `
        display: flex;
        gap: 15px;
        justify-content: center;
        width: 100%;
        flex-wrap: wrap;
    `;
    
    // Accept Button
    const acceptButton = document.createElement('button');
    acceptButton.id = 'acceptOrderButton';
    acceptButton.innerHTML = `✅ Accept (${POLLING_CONFIG.pendingOrders.size})`;
    acceptButton.style.cssText = `
        padding: 12px 25px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 25px;
        cursor: pointer;
        background-color: #28a745;
        border: 2px solid #28a745;
        color: white;
        min-width: 160px;
        text-align: center;
        transition: all 0.3s ease;
        animation: pulseGreen 2s infinite;
        flex: 1;
    `;
    
    acceptButton.addEventListener('click', acceptAllPendingOrders);
    
    // Reject Button
    const rejectButton = document.createElement('button');
    rejectButton.id = 'rejectOrderButton';
    rejectButton.innerHTML = `❌ Reject (${POLLING_CONFIG.pendingOrders.size})`;
    rejectButton.style.cssText = `
        padding: 12px 25px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 25px;
        cursor: pointer;
        background-color: #dc3545;
        border: 2px solid #dc3545;
        color: white;
        min-width: 160px;
        text-align: center;
        transition: all 0.3s ease;
        animation: pulseRed 2s infinite;
        flex: 1;
    `;
    
    rejectButton.addEventListener('click', rejectAllPendingOrders);
    
    // Add hover effects
    acceptButton.addEventListener('mouseenter', () => {
        acceptButton.style.backgroundColor = '#218838';
        acceptButton.style.transform = 'scale(1.05)';
    });
    acceptButton.addEventListener('mouseleave', () => {
        acceptButton.style.backgroundColor = '#28a745';
        acceptButton.style.transform = 'scale(1)';
    });
    
    rejectButton.addEventListener('mouseenter', () => {
        rejectButton.style.backgroundColor = '#c82333';
        rejectButton.style.transform = 'scale(1.05)';
    });
    rejectButton.addEventListener('mouseleave', () => {
        rejectButton.style.backgroundColor = '#dc3545';
        rejectButton.style.transform = 'scale(1)';
    });
    
    actionButtonsContainer.appendChild(acceptButton);
    actionButtonsContainer.appendChild(rejectButton);
    buttonContainer.appendChild(actionButtonsContainer);
    
    // Close Button
    const closeButton = document.createElement('button');
    closeButton.innerHTML = '✕ Close';
    closeButton.style.cssText = `
        padding: 8px 20px;
        font-size: 14px;
        border-radius: 20px;
        cursor: pointer;
        background-color: #6c757d;
        border: 2px solid #6c757d;
        color: white;
        margin-top: 10px;
        transition: all 0.3s ease;
        display:none;
    `;
    
    closeButton.addEventListener('click', hideOrderActionButtons);
    closeButton.addEventListener('mouseenter', () => {
        closeButton.style.backgroundColor = '#5a6268';
    });
    closeButton.addEventListener('mouseleave', () => {
        closeButton.style.backgroundColor = '#6c757d';
    });
    
    buttonContainer.appendChild(closeButton);
    document.body.appendChild(buttonContainer);
}

function createOrderElement(order, orderId) {
    const orderElement = document.createElement('div');
    orderElement.style.cssText = `
        background: white;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        border-left: 4px solid orange;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    `;
    
    // Order Header
    const orderHeader = document.createElement('div');
    orderHeader.style.cssText = `
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e9ecef;
    `;
    
    orderHeader.innerHTML = `
        <div style="flex: 1;">
            <strong style="color: #333;">Order #${orderId}</strong>
            <div style="font-size: 12px; color: #666;">
                ${new Date(order.created_at).toLocaleString()}
            </div>
        </div>
        <div style="text-align: right;">
            <span style="background: #ffc107; color: #000; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                ${order.status || 'Pending'}
            </span>
        </div>
    `;
    
    // Customer Info
    const customerInfo = document.createElement('div');
    customerInfo.style.cssText = `
        margin-bottom: 10px;
        font-size: 14px;
    `;
    
    customerInfo.innerHTML = `
        <div style="float:left;"><strong>👤 ${order.customer_name || 'Customer'}</strong></div>
        <div style="color: #666; float:right;">📞 ${order.customer_phone || 'No phone'}</div>
        <div style="clear:both;"></div>
    `;
    
    // Address (for delivery orders)
    let addressHtml = '';
    if (order.order_type === 'delivery' && order.delivery_address) {
        addressHtml = `
            <div style="margin-bottom: 10px; font-size: 13px;">
                <strong>📍 Delivery Address:</strong>
                <div style="color: #666; margin-top: 2px;">${order.delivery_address}</div>
            </div>
        `;
    } else if (order.order_type === 'dining' && order.table_number) {
        addressHtml = `
            <div style="margin-bottom: 10px; font-size: 13px;">
                <strong>🍽️ Table Number:</strong>
                <span style="color: #666;">${order.table_number}</span>
            </div>
        `;
    }
    
    // Order Items
    const itemsContainer = document.createElement('div');
    itemsContainer.style.cssText = `
        font-size: 13px;
    `;
    
    let itemsHtml = '<strong>🛒 Order Items:</strong><div style="margin-top: 5px;">';
    
    if (order.items && order.items.length > 0) {
        order.items.forEach(item => {
            itemsHtml += `
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px; padding: 2px 0;">
                    <span>${item.product_name || 'Item'}</span>
                    <span>
                        <strong>${item.quantity || 1} × ₹${parseFloat(item.price || 0)}</strong>
                    </span>
                </div>
            `;
        });
        
        // Total
        itemsHtml += `
            <div style="border-top: 1px solid #dee2e6; margin-top: 8px; padding-top: 5px; font-weight: bold;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Total:</span>
                    <span>₹${parseFloat(order.total_amount || 0)}</span>
                </div>
            </div>
        `;
    } else {
        itemsHtml += '<div style="color: #999; font-style: italic;">No items found</div>';
    }
    
    itemsHtml += '</div>';
    itemsContainer.innerHTML = itemsHtml;
    
    // Assemble order element
    orderElement.appendChild(orderHeader);
    orderElement.appendChild(customerInfo);
    
    if (addressHtml) {
        const addressElement = document.createElement('div');
        addressElement.innerHTML = addressHtml;
        orderElement.appendChild(addressElement);
    }
    
    orderElement.appendChild(itemsContainer);
    
    return orderElement;
}

function updateOrderActionButtons() {
    const container = document.getElementById('floatingActionButtons');
    if (container) {
        // Remove and recreate with updated data
        hideOrderActionButtons();
        showOrderActionButtons();
    }
}

function hideOrderActionButtons() {
    const container = document.getElementById('floatingActionButtons');
    if (container) container.remove();
}

async function acceptAllPendingOrders() {
    if (POLLING_CONFIG.pendingOrders.size === 0) return;
    
    const button = document.getElementById('acceptOrderButton');
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Processing...';
    button.disabled = true;
    
    // Also disable reject button during processing
    const rejectButton = document.getElementById('rejectOrderButton');
    if (rejectButton) rejectButton.disabled = true;
    
    try {
        const orderIds = Array.from(POLLING_CONFIG.pendingOrders.keys());
        
        // First, get business info and profile URL
        const businessData = await fetchBusinessData();
        
        const response = await fetch('accept_orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order_ids: orderIds, new_status: 'Confirmed'})
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Accepted ${result.affected_rows} order(s)!`, 'success');
            
            // Remove accepted orders from pending list
            result.orders_data.forEach(order => {
                POLLING_CONFIG.pendingOrders.delete(order.order_id);
            });
            
            // Send WhatsApp confirmation for each accepted order
            result.orders_data.forEach(order => {
                if (order.customer_phone) {
                    // Send WhatsApp message with slight delay to avoid rate limiting
                    setTimeout(() => {
                        sendOrderConfirmation(
                            order.order_id,
                            order.customer_phone,
                            order.customer_name || 'Customer',
                            order.order_type || 'delivery',
                            businessData.businessInfo,
                            businessData.userPhone,
                            businessData.profileUrl
                        );
                    }, 1000);
                }
            });
            
            // Stop the continuous sound
            stopContinuousSound();
            hideOrderActionButtons();
            document.title = document.title.replace('🔔 ', '');
            
            // Redirect to orders page after a short delay
            setTimeout(() => {
                window.location.href = 'orders.php';
            }, 2000);
        } else {
            // Handle case where orders were already processed
            if (result.error === 'No pending orders available') {
                showToast('Orders were already processed by another device', 'warning');
                // Clear all pending orders since they're already processed
                POLLING_CONFIG.pendingOrders.clear();
                updateUI();
                // Refresh page to show updated status
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(result.error);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error: ' + error.message, 'danger');
        button.innerHTML = originalText;
        button.disabled = false;
        if (rejectButton) rejectButton.disabled = false;
    }
}

// Function to fetch business data
async function fetchBusinessData() {
    try {
        const response = await fetch('get_business_data.php');
        const data = await response.json();
        
        if (data.success) {
            return {
                businessInfo: data.business_info,
                userPhone: data.user_phone,
                profileUrl: data.profile_url
            };
        } else {
            throw new Error('Failed to fetch business data');
        }
    } catch (error) {
        console.error('Error fetching business data:', error);
        // Return default values
        return {
            businessInfo: { business_name: 'Our Restaurant' },
            userPhone: '',
            profileUrl: ''
        };
    }
}

async function rejectAllPendingOrders() {
    if (POLLING_CONFIG.pendingOrders.size === 0) return;
    
    // Show rejection reason dialog
    const rejectionReason = await showRejectionReasonDialog();
    if (!rejectionReason) {
        return; // User cancelled
    }
    
    const button = document.getElementById('rejectOrderButton');
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Rejecting...';
    button.disabled = true;
    
    // Also disable accept button during processing
    const acceptButton = document.getElementById('acceptOrderButton');
    if (acceptButton) acceptButton.disabled = true;
    
    try {
        const orderIds = Array.from(POLLING_CONFIG.pendingOrders.keys());
        
        const response = await fetch('reject_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_ids: orderIds, 
                new_status: 'Cancelled',
                rejection_reason: rejectionReason
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        console.log('Reject response:', result);

        if (result.success) {
            showToast(result.message || `Rejected ${result.affected_rows} order(s)!`, 'warning');
            
            // Remove rejected orders from pending list
            result.orders_data.forEach(order => {
                POLLING_CONFIG.pendingOrders.delete(order.order_id);
            });
            
            // Send rejection notifications for each rejected order
            if (result.orders_data && result.orders_data.length > 0) {
                console.log('Sending rejection notifications for', result.orders_data.length, 'orders');
                
                result.orders_data.forEach((order, index) => {
                    setTimeout(() => {
                        sendOrderRejection(
                            order.order_id,
                            order.customer_phone,
                            order.customer_name || 'Customer',
                            order.order_type || 'delivery',
                            order.total_amount || 0,
                            result.business_info || { business_name: 'Our Restaurant' },
                            result.user_phone || '',
                            result.profile_url || '',
                            result.rejection_reason || rejectionReason
                        );
                    }, index * 2000);
                });
            }
            
            // Stop the continuous sound
            stopContinuousSound();
            hideOrderActionButtons();
            document.title = document.title.replace('🔔 ', '');
            
            // Refresh the page to show updated order status
            setTimeout(() => {
                window.location.reload();
            }, 2000);
            
        } else {
            // Handle case where orders were already processed
            if (result.error === 'No pending orders available') {
                showToast('Orders were already processed by another device', 'warning');
                POLLING_CONFIG.pendingOrders.clear();
                updateUI();
                // Refresh page to show updated status
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(result.error || 'Failed to reject orders');
            }
        }
        
    } catch (error) {
        console.error('Rejection error:', error);
        showToast('Error rejecting orders: ' + error.message, 'danger');
        
        // Restore buttons
        button.innerHTML = originalText;
        button.disabled = false;
        if (acceptButton) acceptButton.disabled = false;
    }
}

// Function to show rejection reason dialog
function showRejectionReasonDialog() {
    return new Promise((resolve) => {
        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        `;
        
        // Create dialog
        const dialog = document.createElement('div');
        dialog.style.cssText = `
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        `;
        
        dialog.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #dc3545; font-size: 20px;">
                <i class="bi bi-exclamation-triangle-fill" style="margin-right: 8px;"></i>
                Reject Orders
            </h3>
            <p style="margin: 0 0 20px 0; color: #666; font-size: 14px;">
                Please select a reason for rejecting these orders:
            </p>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">
                    Rejection Reason:
                </label>
                <select id="rejectionReasonSelect" style="
                    width: 100%;
                    padding: 10px;
                    border: 2px solid #e9ecef;
                    border-radius: 6px;
                    font-size: 14px;
                    background: white;
                ">
                    <option value="Out of stock">Items out of stock</option>
                    <option value="Restaurant closed">Restaurant is closed</option>
                    <option value="Delivery area not serviceable">Delivery area not serviceable</option>
                    <option value="Technical issue">Technical issue</option>
                    <option value="Customer request">Customer requested cancellation</option>
                    <option value="Other">Other reason</option>
                </select>
                
                <div id="customReasonContainer" style="display: none; margin-top: 10px;">
                    <textarea 
                        id="customReason" 
                        placeholder="Please specify the reason..."
                        style="
                            width: 100%;
                            padding: 10px;
                            border: 2px solid #e9ecef;
                            border-radius: 6px;
                            font-size: 14px;
                            resize: vertical;
                            min-height: 80px;
                        "
                    ></textarea>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="cancelReject" style="
                    padding: 10px 20px;
                    border: 2px solid #6c757d;
                    background: white;
                    color: #6c757d;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: bold;
                ">Cancel</button>
                <button id="confirmReject" style="
                    padding: 10px 20px;
                    border: 2px solid #dc3545;
                    background: #dc3545;
                    color: white;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: bold;
                ">Reject Orders</button>
            </div>
        `;
        
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);
        
        // Handle custom reason toggle
        const reasonSelect = dialog.querySelector('#rejectionReasonSelect');
        const customContainer = dialog.querySelector('#customReasonContainer');
        
        reasonSelect.addEventListener('change', function() {
            customContainer.style.display = this.value === 'Other' ? 'block' : 'none';
        });
        
        // Handle button clicks
        dialog.querySelector('#cancelReject').addEventListener('click', () => {
            document.body.removeChild(overlay);
            resolve(null);
        });
        
        dialog.querySelector('#confirmReject').addEventListener('click', () => {
            let reason = reasonSelect.value;
            if (reason === 'Other') {
                reason = dialog.querySelector('#customReason').value.trim();
                if (!reason) {
                    alert('Please specify the rejection reason.');
                    return;
                }
            }
            document.body.removeChild(overlay);
            resolve(reason);
        });
        
        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
                resolve(null);
            }
        });
    });
}

function showToast(message, type) {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.custom-toast');
    existingToasts.forEach(toast => toast.remove());
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show custom-toast`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    `;
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
}

// Add CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    @keyframes pulseGreen {
        0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
    }
    
    @keyframes pulseRed {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    #acceptOrderButton:hover {
        background-color: #218838 !important;
        transform: scale(1.05);
    }
    
    #rejectOrderButton:hover {
        background-color: #c82333 !important;
        transform: scale(1.05);
    }
    
    .custom-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    }
`;
document.head.appendChild(style);

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initOrderSystem);

// Additional initialization for when the page becomes visible
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && POLLING_CONFIG.isSoundPlaying) {
        // Page became visible - try to resume playback
        setTimeout(() => {
            if (POLLING_CONFIG.audioElement.paused) {
                playContinuousSound();
            }
        }, 100);
    }
});

// WhatsApp notification function formatted as per sample
function sendOrderConfirmation(orderId, customerPhone, customerName, orderType, businessInfo, businessPhone, profileUrl) {
    try {
        // Validate inputs
        if (!customerPhone || customerPhone.length < 10) {
            console.warn(`Invalid phone number for order ${orderId}: ${customerPhone}`);
            return false;
        }

        // Business details
        const businessName = businessInfo?.business_name || 'Our Restaurant';
        const businessAddress = businessInfo?.business_address || '';
        const phone = businessPhone || '';

        // Format customer phone
        let formattedCustomerPhone = customerPhone.replace(/\D/g, '');
        if (formattedCustomerPhone.length === 10) {
            formattedCustomerPhone = '91' + formattedCustomerPhone;
        }

        // URLs
        const orderStatusUrl = profileUrl 
            ? `https://deegeecard.com/order_status.php?order_id=${orderId}&profile_url=${encodeURIComponent(profileUrl)}`
            : `https://deegeecard.com/order_status.php?order_id=${orderId}`;
            
        const profileOrderUrl = profileUrl 
            ? `https://deegeecard.com/${profileUrl}`
            : 'https://deegeecard.com';

        // Create confirmation message exactly as per sample
        let message = `🚀 *Next time, order faster!*\n`;
        message += `Place your order easily here:\n`;
        message += `🔗 ${profileOrderUrl}\n\n`;
        
        message += `🍽 *${businessName.toUpperCase()}*\n`;
        message += `✅ Order Confirmed #${orderId}\n\n`;
        
        message += `👋 Dear ${customerName},\n`;
        message += `Your order has been confirmed and is now being processed!\n\n`;
        
        message += `📋 *Order Details:*\n`;
        message += `•⁠  ⁠Order Type: ${orderType === 'delivery' ? '🚚 Delivery' : orderType === 'dining' ? '🍽️ Dining' : orderType}\n`;
        message += `•⁠  ⁠Order ID: #${orderId}\n\n`;
        
        message += `🔎 *Track Your Order:*\n`;
        message += `${orderStatusUrl}\n\n`;
        
        message += `❤️ *Thank you for choosing ${businessName}!*\n`;
        message += `We truly appreciate your business.`;

        // Create WhatsApp URL
        const whatsappUrl = `https://wa.me/${formattedCustomerPhone}?text=${encodeURIComponent(message)}`;
        
        // Open WhatsApp in new tab
        const newWindow = window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
        
        console.log('WhatsApp confirmation sent to:', customerPhone);
        showToast(`Confirmation sent to ${customerName}`, 'success');
        return true;
        
    } catch (error) {
        console.error('Error sending WhatsApp confirmation:', error);
        showToast(`Error sending WhatsApp to ${customerName}`, 'danger');
        return false;
    }
}

// WhatsApp notification function for order rejection
function sendOrderRejection(orderId, customerPhone, customerName, orderType, totalAmount, businessInfo, businessPhone, profileUrl, rejectionReason) {
    try {
        // Validate inputs
        if (!customerPhone || customerPhone.length < 10) {
            console.warn(`Invalid phone number for order ${orderId}: ${customerPhone}`);
            return false;
        }

        // Business details
        const businessName = businessInfo?.business_name || 'Our Restaurant';
        const phone = businessPhone || '';

        // Format customer phone
        let formattedCustomerPhone = customerPhone.replace(/\D/g, '');
        if (formattedCustomerPhone.length === 10) {
            formattedCustomerPhone = '91' + formattedCustomerPhone;
        }

        // Create rejection message
        let message = `😔 *Order Cancelled* ❌\n\n`;
        message += `🍽 *${businessName.toUpperCase()}*\n`;
        message += `❌ Order Cancelled #${orderId}\n\n`;
        
        message += `👋 Dear ${customerName},\n`;
        message += `We regret to inform you that your order #${orderId} has been cancelled.\n\n`;
        
        message += `📋 *Order Details:*\n`;
        message += `•⁠  ⁠Order Type: ${orderType === 'delivery' ? '🚚 Delivery' : orderType === 'dining' ? '🍽️ Dining' : orderType}\n`;
        message += `•⁠  ⁠Order ID: #${orderId}\n`;
        message += `•⁠  ⁠Amount: ₹${parseFloat(totalAmount).toFixed(2)}\n\n`;
        
        message += `📝 *Cancellation Reason:*\n`;
        message += `${rejectionReason}\n\n`;
        
        message += `🚀 *Next time, order faster!*\n`;
        message += `Place your order easily here:\n`;
        if (profileUrl) {
            message += `🔗 https://deegeecard.com/${profileUrl}\n\n`;
        } else {
            message += `🔗 https://deegeecard.com\n\n`;
        }
        
        message += `❤️ *We apologize for any inconvenience.*\n`;
        message += `Thank you for considering ${businessName}!\n\n`;

        // Create WhatsApp URL
        const whatsappUrl = `https://wa.me/${formattedCustomerPhone}?text=${encodeURIComponent(message)}`;
        
        // Open WhatsApp in new tab
        const newWindow = window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
        
        
        console.log('WhatsApp rejection sent to:', customerPhone);
        showToast(`Rejection notification sent to ${customerName}`, 'warning');
        return true;
        
    } catch (error) {
        console.error('Error sending WhatsApp rejection:', error);
        showToast(`Error sending rejection to ${customerName}`, 'danger');
        return false;
    }
}
</script>

<div class="main-nav">
   <!-- Sidebar Logo -->
   <div class="logo-box">
      <a href="javascript:void(0)" class="logo-dark">
      <img src="assets/images/logo-sm.png" class="logo-sm" alt="logo sm">
      <img src="assets/images/logo-dark.png" class="logo-lg" alt="logo dark">
      </a>
      <a href="javascript:void(0)" class="logo-light">
      <img src="assets/images/logo-sm.png" class="logo-sm" alt="logo sm">
      <img src="assets/images/logo-light.png" class="logo-lg" alt="logo light">
      </a>
   </div>
   <!-- Menu Toggle Button (sm-hover) -->
   <button type="button" class="button-sm-hover" aria-label="Show Full Sidebar">
      <iconify-icon icon="solar:double-alt-arrow-right-bold-duotone" class="button-sm-hover-icon"></iconify-icon>
   </button>
   <div class="scrollbar" data-simplebar>
      <ul class="navbar-nav" id="navbar-nav">
          
          
          
         <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
               <span class="nav-icon">
                  <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
               </span>
               <span class="nav-text">Dashboard</span>
            </a>
         </li>
         <li class="menu-title">Orders</li>
         <li class="nav-item">
            <a class="nav-link" href="orders.php">
               <span class="nav-icon">
                  <iconify-icon icon="fluent-mdl2:activate-orders"></iconify-icon>
               </span>
               <span class="nav-text">List of Orders</span>
            </a>
         </li>
         <li class="nav-item">
            <a class="nav-link" href="kot.php">
               <span class="nav-icon">
                  <iconify-icon icon="streamline-ultimate:seasoning-food"></iconify-icon>
               </span>
               <span class="nav-text">KOT</span>
            </a>
         </li>
         <li class="nav-item">
            <a class="nav-link" href="sales_report.php">
               <span class="nav-icon">
                  <iconify-icon icon="carbon:sales-ops"></iconify-icon>
               </span>
               <span class="nav-text">Sales Report</span>
            </a>
         </li>
         <li class="nav-item">
            <a class="nav-link" href="customer_data.php">
               <span class="nav-icon">
                  <iconify-icon icon="streamline:information-desk-customer"></iconify-icon>
               </span>
               <span class="nav-text">Customer Data</span>
            </a>
         </li>

         <li class="nav-item">
            <a class="nav-link" href="whatsapp_marketing.php">
               <span class="nav-icon">
                  <iconify-icon icon="ic:sharp-whatsapp"></iconify-icon>
               </span>
               <span class="nav-text">Bulk WhatsApp Marketing</span>
            </a>
         </li>

         <li class="menu-title">Products</li>
         <li class="nav-item">
            <a class="nav-link menu-arrow" href="#personal" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="personal">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:card-account-details-outline"></iconify-icon>
               </span>
               <span class="nav-text"> Personal </span>
            </a>
            <div class="collapse" id="personal">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="profile_url.php">Profile URL</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="profile.php">Profile</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="profile-cover-photo.php">Profile & Cover Photo</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="social.php">Social Sites</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="theme.php">Themes</a>
                  </li>
               </ul>
            </div>
         </li>
         <li class="nav-item">
            <a class="nav-link menu-arrow" href="#services" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="services">
               <span class="nav-icon">
                  <iconify-icon icon="clarity:list-line"></iconify-icon>
               </span>
               <span class="nav-text"> Service Utilities </span>
            </a>
            <div class="collapse" id="services">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="store_timing.php">Store Timing</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="store_on_off.php">Store ON/OFF</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="delivery_charges.php">Delivery Charges</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="gst_charge.php">GST</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="discount.php">Discount</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="coupon.php">Coupon Code</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="products.php">Products</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="tags.php">Tags</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="photo-gallery.php">Photo Gallery</a>
                  </li>
               </ul>
            </div>
         </li>
         <li class="nav-item">
            <a class="nav-link menu-arrow" href="#business" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="business">
               <span class="nav-icon">
                  <iconify-icon icon="vaadin:shop"></iconify-icon>
               </span>
               <span class="nav-text"> Business </span>
            </a>
            <div class="collapse" id="business">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="business.php">Business</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="bank-details.php">Bank Details</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="qr-code-details.php">QR Code Details</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="upload_apk.php">Upload APK</a>
                  </li>
               </ul>
            </div>
         </li>
         <li class="nav-item">
            <a class="nav-link" href="customer-reviews.php">
               <span class="nav-icon">
                  <iconify-icon icon="solar:bill-list-line-duotone"></iconify-icon>
               </span>
               <span class="nav-text">Customer Reviews</span>
            </a>
         </li>

         <li class="nav-item">
            <a class="nav-link menu-arrow" href="#ticket" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="ticket">
               <span class="nav-icon">
                  <iconify-icon icon="material-symbols:help-outline"></iconify-icon>
               </span>
               <span class="nav-text"> Ticket </span>
            </a>
            <div class="collapse" id="ticket">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="create_ticket.php">Create Ticket</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="view_tickets.php">View Tickets</a>
                  </li>
               </ul>
            </div>
         </li>

         <li class="nav-item">
            <a class="nav-link" href="subscription.php">
               <span class="nav-icon">
                  <iconify-icon icon="streamline:subscription-cashflow"></iconify-icon>
               </span>
               <span class="nav-text">Subscription</span>
            </a>
         </li>
      </ul>
   </div>
</div>
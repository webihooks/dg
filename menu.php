<script>
// Debug function to check OneSignal status
function debugOneSignal() {
    let status = {};
    
    if (window.oneSignalLoginManager) {
        status.loginManager = window.oneSignalLoginManager.getRegistrationStatus();
    }
    
    if (window.oneSignalDashboardManager) {
        status.dashboardManager = {
            userId: window.oneSignalDashboardManager.userId,
            initialized: true
        };
    }
    
    status.localStorage = {
        pendingPlayerId: localStorage.getItem('pending_player_id'),
        playerId: localStorage.getItem('player_id'),
        userId: localStorage.getItem('user_id'),
        registered: localStorage.getItem('onesignal_registered')
    };
    
    status.session = {
        phpUserId: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>,
        isAndroidApp: <?php echo isset($_SESSION['is_android_app']) ? 'true' : 'false'; ?>
    };
    
    console.log('🔍 OneSignal Full Debug Info:', status);
    alert('OneSignal Debug Info:\n' + JSON.stringify(status, null, 2));
}

// Add debug button to your page (optional)
<button onclick="debugOneSignal()" class="btn btn-info">Debug OneSignal</button>
</script>











<!-- OneSignal Integration for Dashboard -->
<script>
// Dashboard OneSignal Maintenance
class DashboardOneSignal {
    constructor() {
        this.userId = <?php echo $_SESSION['user_id'] ?? 'null'; ?>;
        if (this.userId) {
            this.ensureDeviceRegistered();
        }
    }
    
    ensureDeviceRegistered() {
        // Check if we have a recent registration
        const lastReg = localStorage.getItem('last_registration');
        const oneDay = 24 * 60 * 60 * 1000;
        
        if (!lastReg || (Date.now() - new Date(lastReg).getTime()) > oneDay) {
            // Re-register device daily
            this.registerDevice();
        }
    }
    
    registerDevice() {
        if (typeof WTN !== 'undefined' && WTN.OneSignal) {
            WTN.OneSignal.getPlayerId().then(playerId => {
                if (playerId) {
                    this.sendRegistration(playerId, 'android_webtonative');
                }
            });
        }
    }
    
    sendRegistration(playerId, deviceType) {
        const payload = {
            player_id: playerId,
            device_type: deviceType,
            user_id: this.userId
        };
        
        fetch('register_device_unified.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                localStorage.setItem('last_registration', new Date().toISOString());
                console.log('Dashboard device maintenance: Registered');
            }
        });
    }
}

// Initialize on dashboard
document.addEventListener('DOMContentLoaded', function() {
    new DashboardOneSignal();
});
</script>
<!-- OneSignal Integration for Dashboard -->




<!-- SIMPLIFIED OneSignal Registration -->
<script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
<script>
// Enhanced Android-Only OneSignal Registration
class AndroidOneSignalRegister {
    constructor() {
        this.userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        console.log('🚀 Android Register - User ID:', this.userId);
        
        if (this.userId) {
            this.startAndroidRegistration();
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
            this.showMessage('✅ Ready for orders (Android app required for push notifications)', 'info');
        }
    }
    
    registerViaWebToNative() {
        WTN.OneSignal.getPlayerId().then(playerId => {
            if (playerId) {
                console.log('✅ Got Android Player ID:', playerId);
                this.sendRegistration(playerId, 'android_webtonative', 'android');
            } else {
                console.log('❌ No Player ID from WebToNative');
                this.showMessage('⚠️ Android notifications not available', 'warning');
            }
        }).catch(error => {
            console.error('❌ WebToNative error:', error);
            this.showMessage('❌ Android registration failed', 'error');
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
                    this.showMessage('ℹ️ ' + data.message, 'info');
                } else {
                    console.log('🎉 ANDROID DEVICE REGISTERED SUCCESSFULLY!');
                    this.showMessage('✅ Android device registered for push notifications!', 'success');
                }
            } else {
                console.error('❌ Registration failed:', data.message);
                this.showMessage('❌ Registration failed: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('❌ Request failed:', error);
            this.showMessage('❌ Network error: ' + error.message, 'error');
        });
    }
    
    showMessage(message, type) {
        // Create a visible notification (optional - remove if not needed)
        const div = document.createElement('div');
        div.style.cssText = `
            position: fixed;
            display: none;
            top: 20px;
            right: 20px;
            padding: 15px;
            background: ${type === 'success' ? '#d4edda' : 
                        type === 'info' ? '#d1ecf1' : 
                        type === 'warning' ? '#fff3cd' : '#f8d7da'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : 
                              type === 'info' ? '#bee5eb' : 
                              type === 'warning' ? '#ffeaa7' : '#f5c6cb'};
            border-radius: 5px;
            z-index: 10000;
            color: ${type === 'success' ? '#155724' : 
                    type === 'info' ? '#0c5460' : 
                    type === 'warning' ? '#856404' : '#721c24'};
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

// Start Android-only registration when page loads
document.addEventListener('DOMContentLoaded', function() {
    new AndroidOneSignalRegister();
});
</script>










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
.order-popup {
    bottom: 15px !important;
    max-height: 600px !important;
    max-width: 500px !important;
    border: 3px solid #ff6c2f !important;
}
@media only screen and (max-width: 400px) {
    .order-popup {
        max-width: 340px !important;
        border: 3px solid #ff6c2f !important;
    }
    .order-popup button {
        font-size: 14px !important;
        padding: 12px 8px !important;
        margin: 0 !important;
        min-width: 140px !important;
    }
}
</style>

<script>
// START: Enhanced Order System with Individual Order Popups

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
    refreshInterval: 5000,
    lastRefreshTime: Date.now(),
    autoRefreshEnabled: true,
    hasCheckedExistingOrders: false,
    // NEW: Track currently visible order popups
    visibleOrderPopups: new Set()
};

// Main initialization
async function initOrderSystem() {
    console.log('Initializing order system with individual order popups...');
    
    await initAudioSystem();
    await checkExistingPendingOrders();
    initOrderPolling();
    setupEventListeners();
    
    console.log('Order system initialized with individual order popups');
}

// Function to check for existing pending orders on page load
async function checkExistingPendingOrders() {
    if (POLLING_CONFIG.hasCheckedExistingOrders) return;
    
    console.log('🔄 Checking for existing pending orders...');
    
    try {
        const response = await fetch(`check_existing_pending_orders.php?t=${Date.now()}`);
        const data = await response.json();
        
        if (data.success && data.pending_orders && data.pending_orders.length > 0) {
            console.log(`✅ Found ${data.pending_orders.length} existing pending orders`);
            
            // Add all pending orders to our tracking
            data.pending_orders.forEach(order => {
                if (!POLLING_CONFIG.pendingOrders.has(order.order_id)) {
                    POLLING_CONFIG.pendingOrders.set(order.order_id, order);
                }
            });
            
            // Update last order ID
            if (data.pending_orders.length > 0) {
                const maxOrderId = Math.max(...data.pending_orders.map(o => o.order_id));
                if (maxOrderId > POLLING_CONFIG.lastOrderId) {
                    POLLING_CONFIG.lastOrderId = maxOrderId;
                }
            }
            
            // Show individual popups for each pending order
            if (POLLING_CONFIG.pendingOrders.size > 0) {
                console.log(`🎯 Showing popups for ${POLLING_CONFIG.pendingOrders.size} existing pending orders`);
                notifyNewOrder();
                updateUI();
            }
        } else {
            console.log('ℹ️ No existing pending orders found');
        }
        
    } catch (error) {
        console.error('❌ Error checking existing orders:', error);
    } finally {
        POLLING_CONFIG.hasCheckedExistingOrders = true;
    }
}

// Audio system initialization (unchanged)
async function initAudioSystem() {
    console.log('Initializing audio system...');
    
    POLLING_CONFIG.audioElement = new Audio();
    POLLING_CONFIG.audioElement.src = 'assets/sounds/new_order.mp3?' + Date.now();
    POLLING_CONFIG.audioElement.loop = true;
    POLLING_CONFIG.audioElement.volume = 0.9;
    POLLING_CONFIG.audioElement.preload = 'auto';
    
    POLLING_CONFIG.audioElement.addEventListener('canplaythrough', () => {
        console.log('Audio ready for playback');
    });
    
    POLLING_CONFIG.audioElement.addEventListener('error', (e) => {
        console.error('Audio error:', e);
        retryAudioLoad();
    });
    
    POLLING_CONFIG.audioElement.addEventListener('ended', () => {
        if (POLLING_CONFIG.isSoundPlaying) {
            playContinuousSound();
        }
    });
    
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
                    
                    setTimeout(() => {
                        if (POLLING_CONFIG.isSoundPlaying) {
                            playSound();
                        }
                    }, 1000);
                });
            }
        } catch (error) {
            console.error('Playback error:', error);
            setTimeout(() => {
                if (POLLING_CONFIG.isSoundPlaying) {
                    playSound();
                }
            }, 2000);
        }
    };
    
    playSound();
    
    const keepAliveInterval = setInterval(() => {
        if (!POLLING_CONFIG.isSoundPlaying) {
            clearInterval(keepAliveInterval);
            return;
        }
        
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

function notifyNewOrder() {
    console.log('New order notification triggered');
    
    if (!POLLING_CONFIG.isSoundPlaying) {
        playContinuousSound();
    }
    
    showVisualNotification();
    showIndividualOrderPopups();
}

function showVisualNotification() {
    const originalTitle = document.title;
    if (!originalTitle.includes('🔔')) {
        document.title = '🔔 ' + originalTitle;
        
        setTimeout(() => {
            if (document.title.includes('🔔')) {
                document.title = originalTitle;
            }
        }, 10000);
    }
}

// Enhanced initOrderPolling with better visibility handling
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
    
    // Start periodic refresh of pending orders (with visibility check)
    setInterval(() => {
        const isAnyPopupVisible = POLLING_CONFIG.visibleOrderPopups.size > 0;
        if (!isAnyPopupVisible) {
            refreshPendingOrders();
        }
    }, POLLING_CONFIG.refreshInterval);
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

// Enhanced refreshPendingOrders function with visibility check
function refreshPendingOrders() {
    // Don't refresh if any popup is visible
    const isAnyPopupVisible = POLLING_CONFIG.visibleOrderPopups.size > 0;
    
    if (POLLING_CONFIG.pendingOrders.size === 0 || isAnyPopupVisible) {
        return;
    }
    
    fetch(`check_new_orders.php?last_order_id=0&page_load_time=${POLLING_CONFIG.pageLoadTime}&t=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.new_orders) {
                updatePendingOrdersList(data.new_orders);
            }
        })
        .catch(error => console.error('Refresh error:', error));
}

// Modified updatePendingOrdersList function - no refresh when popups are visible
function updatePendingOrdersList(newOrders) {
    if (POLLING_CONFIG.pendingOrders.size === 0) return;
    
    const currentPendingIds = new Set(POLLING_CONFIG.pendingOrders.keys());
    const newPendingIds = new Set(newOrders.map(order => order.order_id));
    
    let ordersWereRemoved = false;
    
    // Remove orders that are no longer pending
    currentPendingIds.forEach(orderId => {
        if (!newPendingIds.has(orderId)) {
            POLLING_CONFIG.pendingOrders.delete(orderId);
            // Also remove the popup if it exists
            removeOrderPopup(orderId);
            console.log(`Order #${orderId} removed from pending list (processed by another device)`);
            ordersWereRemoved = true;
        }
    });
    
    // Update UI if orders were removed
    if (currentPendingIds.size !== POLLING_CONFIG.pendingOrders.size) {
        updateUI();
    }
    
    // DON'T auto-refresh if any popup is currently showing
    const isAnyPopupVisible = POLLING_CONFIG.visibleOrderPopups.size > 0;
    
    if (ordersWereRemoved && POLLING_CONFIG.autoRefreshEnabled && !isAnyPopupVisible) {
        // Only auto-refresh if no popups are showing and orders were processed elsewhere
        autoRefreshPage();
    } else if (ordersWereRemoved && isAnyPopupVisible) {
        // Just show a subtle notification without refreshing
        console.log('Orders processed by another device - but popups are visible, so no refresh');
        showToast('Some orders were processed by another device', 'info');
    }
}

// Enhanced autoRefreshPage function with additional checks
function autoRefreshPage() {
    // Double-check if any popup is visible before refreshing
    const isAnyPopupVisible = POLLING_CONFIG.visibleOrderPopups.size > 0;
    
    if (isAnyPopupVisible) {
        console.log('Auto-refresh cancelled - order popups are visible');
        return;
    }
    
    console.log('Orders were processed by another device - refreshing page...');
    
    // Show refresh notification
    showToast('Orders updated by another device. Refreshing page...', 'info');
    
    // Refresh after a short delay to show the notification
    setTimeout(() => {
        POLLING_CONFIG.isReloading = true;
        window.location.reload();
    }, 2000);
}

// In your existing order system - Enhanced notification handling
function handleNewOrders(newOrders) {
    const newMaxOrderId = Math.max(POLLING_CONFIG.lastOrderId, ...newOrders.map(o => o.order_id));
    
    if (newMaxOrderId > POLLING_CONFIG.lastOrderId) {
        POLLING_CONFIG.lastOrderId = newMaxOrderId;
        
        let hasNewPending = false;
        newOrders.forEach(order => {
            if (order.status === 'Pending' && !POLLING_CONFIG.pendingOrders.has(order.order_id)) {
                POLLING_CONFIG.pendingOrders.set(order.order_id, order);
                hasNewPending = true;
                
                // Send OneSignal notification for new orders
                console.log('🎯 Sending OneSignal notification for order:', order.order_id);
                
                // The notification will be automatically sent via place_order.php
                // But we log it here for tracking
                logNotificationEvent('new_order_detected', order);
            }
        });
        
        if (hasNewPending) {
            console.log(`🔔 New pending orders detected: ${POLLING_CONFIG.pendingOrders.size}`);
            notifyNewOrder();
            showToast(`New order received! Pending: ${POLLING_CONFIG.pendingOrders.size}`, 'success');
        }
    }
    
    updateUI();
}

function logNotificationEvent(type, order) {
    const event = {
        type: type,
        order_id: order.order_id,
        user_id: order.user_id,
        timestamp: new Date().toISOString(),
        source: 'order_polling'
    };
    console.log('📝 Notification Event:', event);
}

// NEW: Enhanced updateUI function for individual popups
function updateUI() {
    const isAnyPopupVisible = POLLING_CONFIG.visibleOrderPopups.size > 0;
    
    if (POLLING_CONFIG.pendingOrders.size > 0) {
        if (!isAnyPopupVisible) {
            showIndividualOrderPopups();
        } else {
            updateIndividualOrderPopups();
        }
    } else {
        // Hide all popups if no pending orders
        hideAllOrderPopups();
        stopContinuousSound();
        // Restore original title
        document.title = document.title.replace('🔔 ', '');
    }
}

// NEW: Function to show individual popup for each order
function showIndividualOrderPopups() {
    // First hide any existing popups
    hideAllOrderPopups();
    
    // Get pending orders sorted by order_id (oldest first)
    const sortedOrders = Array.from(POLLING_CONFIG.pendingOrders.entries())
        .sort(([idA], [idB]) => idA - idB);
    
    console.log(`Showing ${sortedOrders.length} individual order popups`);
    
    // Create popup for each order
    sortedOrders.forEach(([orderId, order], index) => {
        createOrderPopup(order, orderId, index);
    });
}

// NEW: Function to create individual order popup
function createOrderPopup(order, orderId, index) {
    // Calculate vertical position to stack popups
    const baseBottom = 15;
    const popupHeight = 400; // Approximate height of each popup
    const gap = 10;
    const bottomPosition = baseBottom + (index * (popupHeight + gap));
    
    const popupContainer = document.createElement('div');
    popupContainer.id = `orderPopup_${orderId}`;
    popupContainer.className = 'order-popup';
    popupContainer.style.cssText = `
        position: fixed;
        bottom: ${bottomPosition}px;
        left: 50%;
        transform: translateX(-50%);
        z-index: ${9999 + index};
        background: rgba(255, 255, 255, 0.98);
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255,255,255,0.3);
        max-width: 500px;
        width: 90%;
        max-height: 400px;
        overflow-y: auto;
        animation: slideInUp 0.3s ease-out;
    `;
    
    // Header with order info
    const header = document.createElement('div');
    header.style.cssText = `
        text-align: center;
        width: 100%;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    `;
    header.innerHTML = `
        <h4 style="margin: 0; color: #333; font-weight: bold;">
            🔔 New Order #${orderId}
        </h4>
        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
            ${new Date(order.created_at).toLocaleString()}
        </p>
    `;
    popupContainer.appendChild(header);
    
    // Order Details
    const orderDetails = createOrderDetailsElement(order, orderId);
    popupContainer.appendChild(orderDetails);
    
    // Action Buttons Container
    const actionButtonsContainer = document.createElement('div');
    actionButtonsContainer.style.cssText = `
        display: flex;
        gap: 15px;
        justify-content: center;
        width: 100%;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 2px solid #f0f0f0;
    `;
    
    // Accept Button
    const acceptButton = document.createElement('button');
    acceptButton.id = `acceptOrder_${orderId}`;
    acceptButton.innerHTML = `✅ Accept Order`;
    acceptButton.style.cssText = `
        padding: 12px 10px;
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
    
    acceptButton.addEventListener('click', () => acceptSingleOrder(orderId));
    
    // Reject Button
    const rejectButton = document.createElement('button');
    rejectButton.id = `rejectOrder_${orderId}`;
    rejectButton.innerHTML = `❌ Reject Order`;
    rejectButton.style.cssText = `
        padding: 12px 10px;
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
    
    rejectButton.addEventListener('click', () => rejectSingleOrder(orderId));
    
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
    popupContainer.appendChild(actionButtonsContainer);
    
    // Add to document and tracking
    document.body.appendChild(popupContainer);
    POLLING_CONFIG.visibleOrderPopups.add(orderId);
}

// NEW: Function to create order details element
function createOrderDetailsElement(order, orderId) {
    const detailsContainer = document.createElement('div');
    detailsContainer.style.cssText = `
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        border-left: 4px solid orange;
    `;
    
    // Customer Info
    const customerInfo = document.createElement('div');
    customerInfo.style.cssText = `
        margin-bottom: 10px;
        font-size: 14px;
    `;
    customerInfo.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <strong>👤 ${order.customer_name || 'Customer'}</strong>
            <span style="background: #ffc107; color: #000; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                ${order.status || 'Pending'}
            </span>
        </div>
        <div style="color: #666;">📞 ${order.customer_phone || 'No phone'}</div>
    `;
    detailsContainer.appendChild(customerInfo);
    
    // Address (for delivery orders)
    if (order.order_type === 'delivery' && order.delivery_address) {
        const addressElement = document.createElement('div');
        addressElement.style.cssText = `
            margin-bottom: 10px;
            font-size: 13px;
            padding: 8px;
            background: #e9f7fe;
            border-radius: 5px;
            border-left: 3px solid #17a2b8;
        `;
        addressElement.innerHTML = `
            <strong>📍 Delivery Address:</strong>
            <div style="color: #666; margin-top: 2px;">${order.delivery_address}</div>
        `;
        detailsContainer.appendChild(addressElement);
    } else if (order.order_type === 'dining' && order.table_number) {
        const tableElement = document.createElement('div');
        tableElement.style.cssText = `
            margin-bottom: 10px;
            font-size: 13px;
        `;
        tableElement.innerHTML = `
            <strong>🍽️ Table Number:</strong>
            <span style="color: #666;">${order.table_number}</span>
        `;
        detailsContainer.appendChild(tableElement);
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
    detailsContainer.appendChild(itemsContainer);
    
    return detailsContainer;
}

// NEW: Function to update individual order popups
function updateIndividualOrderPopups() {
    // Remove and recreate all popups with updated data
    hideAllOrderPopups();
    showIndividualOrderPopups();
}

// NEW: Function to hide all order popups
function hideAllOrderPopups() {
    POLLING_CONFIG.visibleOrderPopups.forEach(orderId => {
        removeOrderPopup(orderId);
    });
    POLLING_CONFIG.visibleOrderPopups.clear();
}

// NEW: Function to remove specific order popup
function removeOrderPopup(orderId) {
    const popup = document.getElementById(`orderPopup_${orderId}`);
    if (popup) {
        popup.remove();
    }
    POLLING_CONFIG.visibleOrderPopups.delete(orderId);
}

// NEW: Function to accept single order
async function acceptSingleOrder(orderId) {
    const order = POLLING_CONFIG.pendingOrders.get(orderId);
    if (!order) return;
    
    const button = document.getElementById(`acceptOrder_${orderId}`);
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Processing...';
    button.disabled = true;
    
    const rejectButton = document.getElementById(`rejectOrder_${orderId}`);
    if (rejectButton) rejectButton.disabled = true;
    
    try {
        const businessData = await fetchBusinessData();
        
        const response = await fetch('accept_orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order_ids: [orderId], new_status: 'Confirmed'})
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Order #${orderId} accepted!`, 'success');
            
            // Remove from pending orders and popup
            POLLING_CONFIG.pendingOrders.delete(orderId);
            removeOrderPopup(orderId);
            
            // Send confirmation message
            if (order.customer_phone) {
                sendOrderConfirmation(
                    order.order_id,
                    order.customer_phone,
                    order.customer_name || 'Customer',
                    order.order_type || 'delivery',
                    businessData.businessInfo,
                    businessData.userPhone,
                    businessData.profileUrl
                );
            }
            
            // Update UI and check if all orders are processed
            updateUI();
            
        } else {
            if (result.error === 'redirect_to_orders') {
                console.log('Order already processed - redirecting to orders page');
                showToast(result.message, 'warning');
                
                POLLING_CONFIG.pendingOrders.clear();
                stopContinuousSound();
                hideAllOrderPopups();
                
                setTimeout(() => {
                    window.location.href = result.redirect_url || 'orders.php';
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

// NEW: Function to reject single order
async function rejectSingleOrder(orderId) {
    const order = POLLING_CONFIG.pendingOrders.get(orderId);
    if (!order) return;
    
    const rejectionReason = await showRejectionReasonDialog();
    if (!rejectionReason) {
        return;
    }
    
    const button = document.getElementById(`rejectOrder_${orderId}`);
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Rejecting...';
    button.disabled = true;
    
    const acceptButton = document.getElementById(`acceptOrder_${orderId}`);
    if (acceptButton) acceptButton.disabled = true;
    
    try {
        const response = await fetch('reject_orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                order_ids: [orderId], 
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
            showToast(result.message || `Order #${orderId} rejected!`, 'warning');
            
            // Remove from pending orders and popup
            POLLING_CONFIG.pendingOrders.delete(orderId);
            removeOrderPopup(orderId);
            
            // Send rejection message
            if (order.customer_phone) {
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
            }
            
            // Update UI and check if all orders are processed
            updateUI();
            
        } else {
            if (result.error === 'redirect_to_orders') {
                console.log('Order already processed - redirecting to orders page');
                showToast(result.message, 'warning');
                
                POLLING_CONFIG.pendingOrders.clear();
                stopContinuousSound();
                hideAllOrderPopups();
                
                setTimeout(() => {
                    window.location.href = result.redirect_url || 'orders.php';
                }, 1500);
            } else {
                throw new Error(result.error || 'Failed to reject order');
            }
        }
        
    } catch (error) {
        console.error('Rejection error:', error);
        showToast('Error rejecting order: ' + error.message, 'danger');
        
        button.innerHTML = originalText;
        button.disabled = false;
        if (acceptButton) acceptButton.disabled = false;
    }
}

// Function to fetch business data (unchanged)
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
        return {
            businessInfo: { business_name: 'Our Restaurant' },
            userPhone: '',
            profileUrl: ''
        };
    }
}

// Function to show rejection reason dialog (unchanged)
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
                Reject Order
            </h3>
            <p style="margin: 0 0 20px 0; color: #666; font-size: 14px;">
                Please select a reason for rejecting this order:
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
                ">Reject Order</button>
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

// Add CSS for animations
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
    
    .order-popup button:hover {
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

// WhatsApp notification functions (unchanged)
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
        // showToast(`Confirmation sent to ${customerName}`, 'success');
        

        // Redirect to orders.php after successful sending
        setTimeout(() => {
            window.location.href = 'orders.php';
        }, 1500); // 1.5 second delay to allow toast to be visible
        
        return true;
        
    } catch (error) {
        console.error('Error sending WhatsApp confirmation:', error);
        showToast(`Error sending WhatsApp to ${customerName}`, 'danger');
        return false;
    }
}

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

// END: Enhanced Order System with Individual Order Popups
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
         <!-- <li class="nav-item">
            <a class="nav-link" href="kot.php">
               <span class="nav-icon">
                  <iconify-icon icon="streamline-ultimate:seasoning-food"></iconify-icon>
               </span>
               <span class="nav-text">KOT</span>
            </a>
         </li> -->
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
                     <a class="sub-nav-link" href="store_on_off.php">Dining Tables</a>
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
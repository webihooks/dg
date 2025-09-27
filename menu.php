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
    maxAudioRetries: 3
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
    showAcceptOrderButton();
}

// Visual notification (minimal - just the accept button)
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
        if (!document.getElementById('floatingAcceptButton')) {
            showAcceptOrderButton();
        } else {
            updateAcceptOrderButton();
        }
    } else {
        hideAcceptOrderButton();
        stopContinuousSound();
        // Restore original title
        document.title = document.title.replace('🔔 ', '');
    }
}

// Accept Order Button
function showAcceptOrderButton() {
    hideAcceptOrderButton();
    
    const acceptButton = document.createElement('button');
    acceptButton.id = 'floatingAcceptButton';
    acceptButton.innerHTML = `🎉 Accept Order (${POLLING_CONFIG.pendingOrders.size})`;
    
    acceptButton.style.cssText = `
        position: fixed;
        bottom: 15px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        padding: 10px 30px;
        font-size: 18px;
        font-weight: bold;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        animation: pulse 2s infinite;
        cursor: pointer;
        background-color: #ff6c2f;
        border: 2px solid #ff6c2f;
        color: white;
        min-width: 250px;
        text-align: center;
        transition: all 0.3s ease;
    `;
    
    acceptButton.addEventListener('click', acceptAllPendingOrders);
    document.body.appendChild(acceptButton);
}

function updateAcceptOrderButton() {
    const button = document.getElementById('floatingAcceptButton');
    if (button) {
        button.innerHTML = `🎉 Accept Order (${POLLING_CONFIG.pendingOrders.size})`;
    }
}

function hideAcceptOrderButton() {
    const button = document.getElementById('floatingAcceptButton');
    if (button) button.remove();
}

async function acceptAllPendingOrders() {
    if (POLLING_CONFIG.pendingOrders.size === 0) return;
    
    const button = document.getElementById('floatingAcceptButton');
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Processing...';
    button.disabled = true;
    
    try {
        const orderIds = Array.from(POLLING_CONFIG.pendingOrders.keys());
        const response = await fetch('accept_orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order_ids: orderIds, new_status: 'Confirmed'})
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Accepted ${orderIds.length} order(s)!`, 'success');
            
            // Stop the continuous sound
            stopContinuousSound();
            POLLING_CONFIG.pendingOrders.clear();
            hideAcceptOrderButton();
            document.title = document.title.replace('🔔 ', '');
            
            // Redirect to orders page
            setTimeout(() => {
                window.location.href = 'orders.php';
            }, 1000);
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error: ' + error.message, 'danger');
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

function showToast(message, type) {
    // Your existing toast implementation
    console.log(`${type}: ${message}`);
}

// Add CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: translateX(-50%) scale(1); }
        50% { transform: translateX(-50%) scale(1.05); }
        100% { transform: translateX(-50%) scale(1); }
    }
    
    #floatingAcceptButton:hover {
        background-color: #ff5a1a;
        transform: translateX(-50%) scale(1.05);
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
</script>

<!-- Add to your main HTML file -->
<script src="socket-manager.js"></script>
<script>
// Additional initialization
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js')
        .then(registration => {
            console.log('SW registered for background websocket');
        });
}

// Handle new orders from WebSocket
document.addEventListener('newOrder', (event) => {
    const order = event.detail;
    // Update your UI here
    displayNewOrder(order);
});

function displayNewOrder(order) {
    // Your order display logic
    console.log('New order received:', order);
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
                  <!-- <li class="sub-nav-item">
                     <a class="sub-nav-link" href="loyalty.php">Loyalty Card</a>
                  </li> -->
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









         <!-- <li class="menu-title">Services</li>
         <li class="nav-item">
            <a class="nav-link" href="services.php">
               <span class="nav-icon">
                  <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon>
               </span>
               <span class="nav-text">Services</span>
            </a>
         </li> -->
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

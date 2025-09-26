<script>
// Global polling configuration
const POLLING_CONFIG = {
    interval: 1000, // 3 seconds for better performance
    active: true,
    lastOrderId: 0,
    isReloading: false,
    notificationSound: 'assets/sounds/new_order.mp3?' + Date.now(),
    pageLoadTime: Math.floor(Date.now() / 1000),
    soundInterval: null,
    pendingOrders: new Set(), // Track pending orders
    isSoundPlaying: true,
    audioElement: null // Single audio element for continuous play
};

// Initialize polling for new orders
function initOrderPolling() {
    sessionStorage.setItem('pageLoadTime', POLLING_CONFIG.pageLoadTime);
    
    // Set initial lastOrderId from existing orders on page
    const orderElements = document.querySelectorAll('[data-order-id]');
    if (orderElements.length > 0) {
        const orderIds = Array.from(orderElements)
            .map(el => parseInt(el.dataset.orderId))
            .filter(id => !isNaN(id));
        
        if (orderIds.length > 0) {
            POLLING_CONFIG.lastOrderId = Math.max(...orderIds);
        }
    }

    // Initialize audio element
    initNotificationAudio();
    
    // Start polling
    checkForNewOrders();
    
    // Tab visibility handling
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', () => POLLING_CONFIG.active = true);
    window.addEventListener('focus', () => {
        POLLING_CONFIG.active = true; 
        checkForNewOrders();
    });
}

function handleVisibilityChange() {
    POLLING_CONFIG.active = !document.hidden;
    if (POLLING_CONFIG.active) {
        checkForNewOrders();
    }
}

function checkForNewOrders() {
    if (!POLLING_CONFIG.active || POLLING_CONFIG.isReloading) return;
    
    const pageLoadTime = sessionStorage.getItem('pageLoadTime') || POLLING_CONFIG.pageLoadTime;
    const timestamp = Date.now();
    
    fetch(`check_new_orders.php?last_order_id=${POLLING_CONFIG.lastOrderId}&page_load_time=${pageLoadTime}&t=${timestamp}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error('Poll error:', data.error);
                return;
            }
            
            if (data.new_orders?.length > 0) {
                const newMaxOrderId = Math.max(
                    POLLING_CONFIG.lastOrderId, 
                    ...data.new_orders.map(o => o.order_id)
                );
                
                if (newMaxOrderId > POLLING_CONFIG.lastOrderId) {
                    POLLING_CONFIG.lastOrderId = newMaxOrderId;
                    
                    // Add new pending orders to the set
                    data.new_orders.forEach(order => {
                        if (order.status === 'Pending') {
                            POLLING_CONFIG.pendingOrders.add(order.order_id);
                        }
                    });
                    
                    // Start continuous sound if there are pending orders
                    if (POLLING_CONFIG.pendingOrders.size > 0 && !POLLING_CONFIG.isSoundPlaying) {
                        startContinuousSound();
                        showAcceptOrderButton();
                    }
                    
                    // Show notification
                    // const orderText = data.new_orders.length > 1 ? 
                    //     `${data.new_orders.length} new orders` : 
                    //     'New order';
                    // showToast(`${orderText} received!`, 'success');
                    
                    // // Special handling for orders page
                    // if (window.location.pathname.includes('orders.php')) {
                    //     if (!POLLING_CONFIG.isReloading) {
                    //         POLLING_CONFIG.isReloading = true;
                    //         setTimeout(() => {
                    //             window.location.reload();
                    //         }, 3000);
                    //     }
                    // }
                }
            }
            
            // Check if there are still pending orders
            if (POLLING_CONFIG.pendingOrders.size === 0 && POLLING_CONFIG.isSoundPlaying) {
                stopContinuousSound();
                hideAcceptOrderButton();
            }
        })
        .catch(error => {
            console.error('Poll failed:', error);
        })
        .finally(() => {
            if (POLLING_CONFIG.active && !POLLING_CONFIG.isReloading) {
                setTimeout(checkForNewOrders, POLLING_CONFIG.interval);
            }
        });
}

// Continuous sound functionality - plays without interval (loops continuously)
function startContinuousSound() {
    if (POLLING_CONFIG.isSoundPlaying || !POLLING_CONFIG.audioElement) return;
    
    POLLING_CONFIG.isSoundPlaying = true;
    
    try {
        // Set audio to loop continuously
        POLLING_CONFIG.audioElement.loop = true;
        POLLING_CONFIG.audioElement.currentTime = 0;
        
        const playPromise = POLLING_CONFIG.audioElement.play();
        
        if (playPromise !== undefined) {
            playPromise.catch(e => {
                console.log('Continuous sound play blocked:', e);
                // If blocked, try to play on user interaction
                enableAudioOnInteraction();
            });
        }
    } catch (e) {
        console.error('Continuous sound error:', e);
    }
}

function stopContinuousSound() {
    if (!POLLING_CONFIG.isSoundPlaying || !POLLING_CONFIG.audioElement) return;
    
    POLLING_CONFIG.isSoundPlaying = false;
    
    try {
        POLLING_CONFIG.audioElement.loop = false;
        POLLING_CONFIG.audioElement.pause();
        POLLING_CONFIG.audioElement.currentTime = 0;
    } catch (e) {
        console.error('Error stopping sound:', e);
    }
}

function enableAudioOnInteraction() {
    const enableAudio = () => {
        document.removeEventListener('click', enableAudio);
        document.removeEventListener('keydown', enableAudio);
        if (POLLING_CONFIG.pendingOrders.size > 0) {
            startContinuousSound();
        }
    };
    
    document.addEventListener('click', enableAudio, { once: true });
    document.addEventListener('keydown', enableAudio, { once: true });
}

// Accept Order Button functionality
function showAcceptOrderButton() {
    // Remove existing button if any
    hideAcceptOrderButton();
    
    // Create floating accept order button
    const acceptButton = document.createElement('button');
    acceptButton.id = 'floatingAcceptButton';
    acceptButton.innerHTML = '🎉 Accept Order (' + POLLING_CONFIG.pendingOrders.size + ')';
    acceptButton.className = 'btn btn-lg';
    acceptButton.style.cssText = `
        position: fixed;
        bottom: 15px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        padding: 10px 30px;
        font-size: 18px;
        font-weight: bold;
        border-radius: 40px;
        box-shadow: rgba(0, 0, 0, 0.3) 0px 4px 15px;
        animation: 2s infinite pulse;
        cursor: pointer;
        background-color: rgb(255, 108, 47);
        border: 2px solid rgb(255, 108, 47);
        color: white;
        min-width: 250px;
        text-align: center;
    `;
    
    // Add hover effects
    acceptButton.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#ff5a1a';
        this.style.borderColor = '#ff5a1a';
        this.style.transform = 'translateX(-50%) scale(1.05)';
    });
    
    acceptButton.addEventListener('mouseleave', function() {
        this.style.backgroundColor = '#ff6c2f';
        this.style.borderColor = '#ff6c2f';
        this.style.transform = 'translateX(-50%) scale(1)';
    });
    
    acceptButton.addEventListener('click', function() {
        acceptAllPendingOrders();
    });
    
    document.body.appendChild(acceptButton);
}

function hideAcceptOrderButton() {
    const existingButton = document.getElementById('floatingAcceptButton');
    if (existingButton) {
        existingButton.remove();
    }
}

function updateAcceptOrderButton() {
    const button = document.getElementById('floatingAcceptButton');
    if (button && POLLING_CONFIG.pendingOrders.size > 0) {
        button.innerHTML = '🎉 Accept Order (' + POLLING_CONFIG.pendingOrders.size + ')';
        
        // Ensure the button stays centered when content changes
        button.style.left = '50%';
        button.style.transform = 'translateX(-50%)';
    } else {
        hideAcceptOrderButton();
    }
}

async function acceptAllPendingOrders() {
    if (POLLING_CONFIG.pendingOrders.size === 0) return;
    
    const button = document.getElementById('floatingAcceptButton');
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Processing...';
    button.disabled = true;
    
    try {
        // Convert Set to Array for order IDs
        const orderIds = Array.from(POLLING_CONFIG.pendingOrders);
        
        // Send request to update all pending orders
        const response = await fetch('accept_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_ids: orderIds,
                new_status: 'Confirmed'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Successfully accepted ${orderIds.length} order(s)! Redirecting to orders...`, 'success');
            
            // Clear pending orders and stop sound immediately
            POLLING_CONFIG.pendingOrders.clear();
            stopContinuousSound();
            hideAcceptOrderButton();
            
            // Redirect to orders.php after 1 second
            setTimeout(() => {
                window.location.href = 'orders.php';
            }, 1000);
            
        } else {
            throw new Error(result.error || 'Failed to accept orders');
        }
    } catch (error) {
        console.error('Error accepting orders:', error);
        showToast('Error accepting orders: ' + error.message, 'danger');
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Enhanced notification audio - single element for continuous play
function initNotificationAudio() {
    if (POLLING_CONFIG.audioElement) return;
    
    try {
        POLLING_CONFIG.audioElement = new Audio(POLLING_CONFIG.notificationSound);
        POLLING_CONFIG.audioElement.volume = 0.7;
        POLLING_CONFIG.audioElement.preload = 'auto';
        
        // Handle audio events
        POLLING_CONFIG.audioElement.addEventListener('canplaythrough', () => {
            console.log('Audio ready for continuous playback');
        });
        
        POLLING_CONFIG.audioElement.addEventListener('error', (e) => {
            console.error('Audio loading failed:', e);
            tryFallbackAudio();
        });
        
        POLLING_CONFIG.audioElement.addEventListener('ended', () => {
            // This should not happen with loop=true, but just in case
            if (POLLING_CONFIG.isSoundPlaying) {
                POLLING_CONFIG.audioElement.play().catch(console.error);
            }
        });
        
        // Load the audio
        POLLING_CONFIG.audioElement.load();
        
    } catch (e) {
        console.error('Audio initialization failed:', e);
        tryFallbackAudio();
    }
}

function tryFallbackAudio() {
    try {
        const fallbackSound = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMc';
        POLLING_CONFIG.audioElement = new Audio(fallbackSound);
        POLLING_CONFIG.audioElement.volume = 0.7;
        POLLING_CONFIG.audioElement.loop = true;
    } catch (fallbackError) {
        console.error('Fallback audio also failed:', fallbackError);
    }
}

function showToast(message, type = 'success') {
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const toastInstance = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 5000
    });
    toastInstance.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Add CSS for pulse animation and button styles
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: translateX(-50%) scale(1); }
        50% { transform: translateX(-50%) scale(1.05); }
        100% { transform: translateX(-50%) scale(1); }
    }
    
    @keyframes glow {
        0% { box-shadow: 0 0 20px rgba(255, 108, 47, 0.7); }
        50% { box-shadow: 0 0 30px rgba(255, 108, 47, 0.9); }
        100% { box-shadow: 0 0 20px rgba(255, 108, 47, 0.7); }
    }
    
    #floatingAcceptButton {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        padding: 15px 30px;
        font-size: 18px;
        font-weight: bold;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        animation: pulse 2s infinite, glow 2s infinite;
        transition: all 0.3s ease;
        background-color: #ff6c2f;
        border: 2px solid #ff6c2f;
        color: white;
        min-width: 200px;
        text-align: center;
        cursor: pointer;
    }
    
    #floatingAcceptButton:hover {
        background-color: #ff5a1a !important;
        border-color: #ff5a1a !important;
        transform: translateX(-50%) scale(1.05) !important;
        animation: none; /* Disable animation on hover for smoother effect */
        box-shadow: 0 6px 25px rgba(255, 108, 47, 0.8) !important;
    }
    
    #floatingAcceptButton:active {
        transform: translateX(-50%) scale(0.95) !important;
        box-shadow: 0 2px 10px rgba(255, 108, 47, 0.6) !important;
    }
    
    #floatingAcceptButton:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: translateX(-50%) scale(1) !important;
        animation: none;
    }
    
    #floatingAcceptButton:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 108, 47, 0.3) !important;
    }
    
    .toast-container {
        z-index: 10000;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
        #floatingAcceptButton {
            bottom: 15px;
            padding: 12px 20px;
            font-size: 16px;
            left: 50%;
            transform: translateX(-50%);
            margin: 0 auto;
        }
    }
    
    @media (max-width: 480px) {
        #floatingAcceptButton {
            bottom: 10px;
            padding: 10px 15px;
            font-size: 14px;
            left: 50%;
            transform: translateX(-50%);
        }
    }
`;
document.head.appendChild(style);

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    let initAttempts = 0;
    const maxInitAttempts = 3;
    
    function initializePolling() {
        if (typeof bootstrap !== 'undefined') {
            initOrderPolling();
        } else if (initAttempts < maxInitAttempts) {
            initAttempts++;
            setTimeout(initializePolling, 1000);
        } else {
            console.error('Failed to initialize polling after multiple attempts');
        }
    }
    
    initializePolling();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    sessionStorage.removeItem('pageLoadTime');
    stopContinuousSound();
});

// Periodic cleanup for sessionStorage
setInterval(() => {
    const storedTime = sessionStorage.getItem('pageLoadTime');
    const currentTime = Math.floor(Date.now() / 1000);
    
    if (storedTime && (currentTime - parseInt(storedTime)) > 86400) {
        sessionStorage.setItem('pageLoadTime', currentTime);
    }
}, 3600000);
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

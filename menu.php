<script>
// Global polling configuration
const POLLING_CONFIG = {
    interval: 1000, // 1 seconds
    active: true,
    lastOrderId: 0,
    isReloading: false,
    notificationSound: 'assets/sounds/new_order.mp3?' + Date.now(), // Cache buster
    pageLoadTime: Math.floor(Date.now() / 1000)
};

// Initialize polling for new orders
function initOrderPolling() {
    // Store page load time in session storage
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
    
    // Add current timestamp to prevent caching issues
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
                // Update lastOrderId to the maximum value
                const newMaxOrderId = Math.max(
                    POLLING_CONFIG.lastOrderId, 
                    ...data.new_orders.map(o => o.order_id)
                );
                
                if (newMaxOrderId > POLLING_CONFIG.lastOrderId) {
                    POLLING_CONFIG.lastOrderId = newMaxOrderId;
                    
                    // Play notification sound
                    playNotification();
                    
                    // Show toast notification
                    const orderText = data.new_orders.length > 1 ? 
                        `${data.new_orders.length} new orders` : 
                        'New order';
                    showToast(`${orderText} received!`, 'success');
                    
                    // Special handling for orders page - reload immediately instead of waiting
                    if (window.location.pathname.includes('orders.php')) {
                        if (!POLLING_CONFIG.isReloading) {
                            POLLING_CONFIG.isReloading = true;
                            // Reload immediately instead of waiting 5 seconds
                            setTimeout(() => {
                                window.location.reload();
                            }, 5000); // 1 second delay to show notification
                        }
                    }
                }
            }
        })
        .catch(error => {
            console.error('Poll failed:', error);
            // Continue polling even if there's an error
        })
        .finally(() => {
            if (POLLING_CONFIG.active && !POLLING_CONFIG.isReloading) {
                setTimeout(checkForNewOrders, POLLING_CONFIG.interval);
            }
        });
}

// Enhanced notification audio with better error handling
let notificationAudio = null;
let audioInitialized = false;

function initNotificationAudio() {
    if (audioInitialized) return;
    
    try {
        notificationAudio = new Audio(POLLING_CONFIG.notificationSound);
        notificationAudio.volume = 0.7; // Increased volume
        notificationAudio.preload = 'auto';
        
        // Handle audio loading
        notificationAudio.addEventListener('canplaythrough', () => {
            audioInitialized = true;
        });
        
        notificationAudio.addEventListener('error', (e) => {
            console.error('Audio loading failed:', e);
            // Try fallback sound
            tryFallbackAudio();
        });
        
        // Load the audio
        notificationAudio.load();
    } catch (e) {
        console.error('Audio initialization failed:', e);
        tryFallbackAudio();
    }
}

function tryFallbackAudio() {
    try {
        // Try a simple beep sound as fallback
        const fallbackSound = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hREA1LnuTyu2EcBjiR1/LMciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMc';
        notificationAudio = new Audio(fallbackSound);
        notificationAudio.volume = 0.7;
        audioInitialized = true;
    } catch (fallbackError) {
        console.error('Fallback audio also failed:', fallbackError);
    }
}

function playNotification() {
    if (!audioInitialized) {
        initNotificationAudio();
    }
    
    if (!notificationAudio || !audioInitialized) {
        console.log('Audio not available for notification');
        return;
    }
    
    try {
        // Reset and play
        notificationAudio.currentTime = 0;
        const playPromise = notificationAudio.play();
        
        if (playPromise !== undefined) {
            playPromise.catch(e => {
                console.log('Audio play blocked, requiring user interaction:', e);
                // Set up one-time click handler to enable audio
                const enableAudio = () => {
                    document.removeEventListener('click', enableAudio);
                    document.removeEventListener('keydown', enableAudio);
                    notificationAudio.play().catch(console.error);
                };
                
                document.addEventListener('click', enableAudio, { once: true });
                document.addEventListener('keydown', enableAudio, { once: true });
            });
        }
    } catch (e) {
        console.error('Audio playback error:', e);
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

// Enhanced initialization with retry logic
document.addEventListener('DOMContentLoaded', function() {
    let initAttempts = 0;
    const maxInitAttempts = 3;
    
    function initializePolling() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            initNotificationAudio();
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

window.addEventListener('beforeunload', () => {
    sessionStorage.removeItem('pageLoadTime');
});

// Add periodic cleanup for sessionStorage
setInterval(() => {
    const storedTime = sessionStorage.getItem('pageLoadTime');
    const currentTime = Math.floor(Date.now() / 1000);
    
    // If stored time is more than 24 hours old, refresh it
    if (storedTime && (currentTime - parseInt(storedTime)) > 86400) {
        sessionStorage.setItem('pageLoadTime', currentTime);
    }
}, 3600000); // Check every hour
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

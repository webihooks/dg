<!-- OneSignal Integration for Room Management Dashboard -->
<script>
// Room Management Dashboard OneSignal Maintenance
class RoomDashboardOneSignal {
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
            user_id: this.userId,
            source: 'room_management_dashboard'
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
                console.log('Room Dashboard device maintenance: Registered');
            }
        });
    }
}

// Initialize on room management dashboard
document.addEventListener('DOMContentLoaded', function() {
    new RoomDashboardOneSignal();
});
</script>

<!-- SIMPLIFIED OneSignal Registration for Room Management -->
<script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
<script>
// Detect Android WebView for Room Management
function isAndroidWebView() {
    return navigator.userAgent.toLowerCase().indexOf("wv") > -1 || 
           (navigator.userAgent.toLowerCase().indexOf("android") > -1 && 
            navigator.userAgent.toLowerCase().indexOf("chrome") === -1);
}

// Hide download button if in Android WebView
if (isAndroidWebView()) {
    document.addEventListener('DOMContentLoaded', function() {
        class RoomManagementOneSignalRegister {
            constructor() {
                this.userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
                console.log('🏨 Room Management - User ID:', this.userId);
                
                if (this.userId) {
                    this.startAndroidRegistration();
                }
            }
            
            startAndroidRegistration() {
                console.log('🔄 Starting Room Management Android registration...');
                
                // ONLY attempt registration for Android WebToNative
                if (typeof WTN !== 'undefined' && WTN.OneSignal) {
                    console.log('📱 Room Management: Android WebToNative detected - registering...');
                    this.registerViaWebToNative();
                } else {
                    console.log('🌐 Web browser detected - skipping device registration');
                    this.showMessage('✅ Ready for room management (Android app required for push notifications)', 'info');
                }
            }
            
            registerViaWebToNative() {
                WTN.OneSignal.getPlayerId().then(playerId => {
                    if (playerId) {
                        console.log('✅ Got Room Management Player ID:', playerId);
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
                    source: 'room_management_menu'
                };
                
                console.log('📨 Sending Room Management registration:', payload);
                
                fetch('register_device_unified.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    console.log('✅ Room Management registration response:', data);
                    if (data.success) {
                        if (data.skipped) {
                            console.log('ℹ️ Room Management registration skipped:', data.reason);
                            this.showMessage('ℹ️ ' + data.message, 'info');
                        } else {
                            console.log('🎉 ROOM MANAGEMENT ANDROID DEVICE REGISTERED SUCCESSFULLY!');
                            this.showMessage('✅ Android device registered for room management notifications!', 'success');
                        }
                    } else {
                        console.error('❌ Room Management registration failed:', data.message);
                        this.showMessage('❌ Registration failed: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('❌ Room Management request failed:', error);
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
        
        new RoomManagementOneSignalRegister();
    });
}
</script>

<style>
/* Room Management Specific Styles */
.room-status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

/*.room-status-available { background-color: #28a745; color: white; }
.room-status-occupied { background-color: #dc3545; color: white; }
.room-status-maintenance { background-color: #ffc107; color: #000; }
.room-status-cleaning { background-color: #17a2b8; color: white; }
.room-status-reserved { background-color: #6f42c1; color: white; }*/

.booking-status-checked_in { background-color: #28a745; color: white; }
.booking-status-reserved { background-color: #007bff; color: white; }
.booking-status-completed { background-color: #6c757d; color: white; }
.booking-status-cancelled { background-color: #dc3545; color: white; }
.booking-status-no_show { background-color: #fd7e14; color: white; }

/* Quick stats in menu */
.room-stats-widget {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 10px;
    margin: 10px 15px;
    text-align: center;
}

.room-stats-widget h6 {
    margin: 0 0 10px 0;
    font-size: 14px;
    opacity: 0.9;
}

.room-stats-numbers {
    display: flex;
    justify-content: space-around;
    font-size: 12px;
}

.room-stat-item {
    text-align: center;
}

.room-stat-number {
    font-size: 18px;
    font-weight: bold;
    display: block;
}

.room-stat-label {
    font-size: 10px;
    opacity: 0.8;
}


/* Floating action buttons for room management */
.room-floating-buttons {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 1000;
}

.room-floating-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    text-decoration: none;
    color: white;
    font-size: 20px;
}

.room-floating-btn:hover {
    transform: scale(1.1);
    color: white;
}

.room-floating-btn.quick-checkin { background: #28a745; }
.room-floating-btn.quick-checkout { background: #ffc107; color: #000; }
.room-floating-btn.quick-booking { background: #007bff; }
.room-floating-btn.quick-room { background: #6f42c1; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .room-stats-widget {
        margin: 10px 5px;
        padding: 10px;
    }
    
    .room-stat-number {
        font-size: 16px;
    }
    
    .room-floating-buttons {
        bottom: 70px;
        right: 15px;
    }
    
    .room-floating-btn {
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
}
</style>

<script>
// Room Management Session Protection
function setupRoomManagementSessionProtection() {
    if (typeof WTN === 'undefined') return;
    
    console.log('🏨 Room Management: Setting up Android session protection');
    
    // Force immediate cookie update
    setTimeout(() => {
        if (WTN.forceUpdateCookies) {
            WTN.forceUpdateCookies();
            console.log('🔧 Room Management: Initial cookie update completed');
        }
    }, 1000);
    
    // Additional protection for page transitions
    window.addEventListener('pageshow', function(event) {
        if (event.persisted && WTN.forceUpdateCookies) {
            setTimeout(() => {
                WTN.forceUpdateCookies();
                console.log('🔧 Room Management: Page restored from cache - cookies updated');
            }, 500);
        }
    });
    
    // Enhanced visibility change handling for room management
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
            setTimeout(() => {
                WTN.forceUpdateCookies();
                console.log('🏨 Room Management: Visibility change - cookies updated');
            }, 300);
        }
    });
}

// Initialize room management protection
document.addEventListener('DOMContentLoaded', function() {
    setupRoomManagementSessionProtection();
    
    // Enhanced session monitoring for room management
    if (typeof WTN !== 'undefined') {
        // More frequent updates for room management system
        setInterval(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
                console.log('🏨 Room Management: Periodic cookie update');
            }
        }, 45000); // Every 45 seconds for room management
        
        // Update cookies on room management activities
        const roomActivities = ['mousemove', 'keydown', 'click', 'touchstart'];
        roomActivities.forEach(activity => {
            document.addEventListener(activity, () => {
                setTimeout(() => {
                    if (WTN.forceUpdateCookies) {
                        WTN.forceUpdateCookies();
                    }
                }, 2000);
            }, { passive: true });
        });
    }
});

// Room Management Session Manager
class RoomManagementSessionManager {
    constructor() {
        this.isAndroidApp = <?php echo (isset($_SESSION['is_android_app']) && $_SESSION['is_android_app']) ? 'true' : 'false'; ?>;
        this.isWebToNative = typeof WTN !== 'undefined';
        this.init();
    }

    init() {
        if (this.isWebToNative) {
            console.log('🏨 Room Management Session Manager: WebToNative detected');
            this.startSessionMaintenance();
        }
    }

    startSessionMaintenance() {
        // More frequent updates for room management (every 30 seconds)
        setInterval(() => {
            this.maintainSession();
        }, 30000);
    }

    maintainSession() {
        if (!this.isWebToNative) return;

        // Update cookies
        if (WTN.forceUpdateCookies) {
            WTN.forceUpdateCookies();
        }

        // Send session ping
        this.sendRoomManagementPing();
    }

    async sendRoomManagementPing() {
        try {
            await fetch('session-keepalive.php', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'X-Room-Management-Ping': 'true',
                    'X-WebToNative': 'true'
                }
            });
            console.log('🏨 Room Management: Session ping sent');
        } catch (error) {
            console.log('🏨 Room Management: Ping failed (app may be in background)');
        }
    }

    // Force session refresh for room management
    forceSessionRefresh() {
        if (this.isWebToNative && WTN.forceUpdateCookies) {
            WTN.forceUpdateCookies();
            this.sendRoomManagementPing();
            console.log('🔧 Room Management: Forced session refresh');
        }
    }
}

// Initialize room management session manager
document.addEventListener('DOMContentLoaded', function() {
    window.roomManagementSessionManager = new RoomManagementSessionManager();
});

// Room Management Quick Stats Loader
function loadRoomQuickStats() {
    fetch('get_room_quick_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateRoomStatsWidget(data.stats);
            }
        })
        .catch(error => {
            console.error('Error loading room stats:', error);
        });
}

function updateRoomStatsWidget(stats) {
    const widget = document.getElementById('roomQuickStats');
    if (widget) {
        widget.innerHTML = `
            <h6>📊 Room Status</h6>
            <div class="room-stats-numbers">
                <div class="room-stat-item">
                    <span class="room-stat-number" style="color: #28a745;">${stats.available || 0}</span>
                    <span class="room-stat-label">Available</span>
                </div>
                <div class="room-stat-item">
                    <span class="room-stat-number" style="color: #dc3545;">${stats.occupied || 0}</span>
                    <span class="room-stat-label">Occupied</span>
                </div>
                <div class="room-stat-item">
                    <span class="room-stat-number" style="color: #ffc107;">${stats.maintenance || 0}</span>
                    <span class="room-stat-label">Maintenance</span>
                </div>
            </div>
        `;
    }
}

// Initialize room stats when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Load quick stats
    loadRoomQuickStats();
    
    // Refresh stats every 2 minutes
    setInterval(loadRoomQuickStats, 120000);
    
    // Highlight current page in menu
    highlightCurrentRoomPage();
});

function highlightCurrentRoomPage() {
    const currentPage = window.location.pathname.split('/').pop();
    const menuItems = document.querySelectorAll('.nav-link');
    
    menuItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href === currentPage) {
            item.parentElement.classList.add('room-management-active');
        }
    });
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
      <!-- Room Management Quick Stats Widget -->
      <div class="room-stats-widget" id="roomQuickStats">
         <h6>📊 Room Status</h6>
         <div class="room-stats-numbers">
            <div class="room-stat-item">
               <span class="room-stat-number" style="color: #28a745;">0</span>
               <span class="room-stat-label">Available</span>
            </div>
            <div class="room-stat-item">
               <span class="room-stat-number" style="color: #dc3545;">0</span>
               <span class="room-stat-label">Occupied</span>
            </div>
            <div class="room-stat-item">
               <span class="room-stat-number" style="color: #ffc107;">0</span>
               <span class="room-stat-label">Maintenance</span>
            </div>
         </div>
      </div>

      <ul class="navbar-nav" id="navbar-nav">
         <li class="nav-item">
            <a class="nav-link" href="room-dashboard.php">
               <span class="nav-icon">
                  <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
               </span>
               <span class="nav-text">Room Dashboard</span>
            </a>
         </li>










         

         <!-- <li class="menu-title">Room Operations</li>
         
         <li class="nav-item">
            <a class="nav-link" href="add-booking.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:calendar-plus"></iconify-icon>
               </span>
               <span class="nav-text">New Booking</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="quick-checkin.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:login"></iconify-icon>
               </span>
               <span class="nav-text">Quick Check-In</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="quick-checkout.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:logout"></iconify-icon>
               </span>
               <span class="nav-text">Quick Check-Out</span>
            </a>
         </li> -->
         
         <li class="nav-item">
            <a class="nav-link" href="walkin-customers.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:walk"></iconify-icon>
               </span>
               <span class="nav-text">Walk-in Customers</span>
            </a>
         </li>

         <li class="menu-title">Room Management</li>
         
         <li class="nav-item">
            <a class="nav-link" href="manage-rooms.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:bed"></iconify-icon>
               </span>
               <span class="nav-text">Manage Rooms</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="room-types.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:format-list-bulleted-type"></iconify-icon>
               </span>
               <span class="nav-text">Room Types</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="room-rates.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:currency-inr"></iconify-icon>
               </span>
               <span class="nav-text">Room Rates</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="room-amenities.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:star-circle"></iconify-icon>
               </span>
               <span class="nav-text">Room Amenities</span>
            </a>
         </li>

         <li class="menu-title">Bookings & Reservations</li>
         
         <li class="nav-item">
            <a class="nav-link" href="bookings.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:bookmark-multiple"></iconify-icon>
               </span>
               <span class="nav-text">All Bookings</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="today-checkins.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:calendar-today"></iconify-icon>
               </span>
               <span class="nav-text">Today's Check-ins</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="today-checkouts.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:calendar-arrow-right"></iconify-icon>
               </span>
               <span class="nav-text">Today's Check-outs</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="upcoming-bookings.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:calendar-clock"></iconify-icon>
               </span>
               <span class="nav-text">Upcoming Bookings</span>
            </a>
         </li>

         <li class="menu-title">Guest Management</li>
         
         <li class="nav-item">
            <a class="nav-link" href="manage-guests.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:account-group"></iconify-icon>
               </span>
               <span class="nav-text">Manage Guests</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="guest-history.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:history"></iconify-icon>
               </span>
               <span class="nav-text">Guest History</span>
            </a>
         </li>
         
         <!-- <li class="nav-item">
            <a class="nav-link" href="loyalty-program.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:crown"></iconify-icon>
               </span>
               <span class="nav-text">Loyalty Program</span>
            </a>
         </li> -->

         <li class="menu-title">Reports & Analytics</li>
         
         <li class="nav-item">
            <a class="nav-link" href="room-occupancy-report.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:chart-bar"></iconify-icon>
               </span>
               <span class="nav-text">Occupancy Report</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="revenue-report.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:cash-multiple"></iconify-icon>
               </span>
               <span class="nav-text">Revenue Report</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="booking-analytics.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:chart-line"></iconify-icon>
               </span>
               <span class="nav-text">Booking Analytics</span>
            </a>
         </li>
         
         <li class="nav-item">
            <a class="nav-link" href="room-utilization.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:chart-pie"></iconify-icon>
               </span>
               <span class="nav-text">Room Utilization</span>
            </a>
         </li>


         
         
         
         <!-- <li class="nav-item">
            <a class="nav-link menu-arrow" href="#notifications" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="notifications">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:bell"></iconify-icon>
               </span>
               <span class="nav-text">Notifications</span>
            </a>
            <div class="collapse" id="notifications">
               <ul class="nav sub-navbar-nav">
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="booking-alerts.php">Booking Alerts</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="guest-notifications.php">Guest Notifications</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="staff-alerts.php">Staff Alerts</a>
                  </li>
               </ul>
            </div>
         </li> -->

         <!-- <li class="nav-item">
            <a class="nav-link" href="room-maintenance.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:toolbox"></iconify-icon>
               </span>
               <span class="nav-text">Maintenance</span>
            </a>
         </li> -->
         
         <!-- <li class="nav-item">
            <a class="nav-link" href="housekeeping.php">
               <span class="nav-icon">
                  <iconify-icon icon="mdi:broom"></iconify-icon>
               </span>
               <span class="nav-text">Housekeeping</span>
            </a>
         </li> -->


         <li class="nav-item">
            <a class="nav-link" href="whatsapp_marketing.php">
               <span class="nav-icon">
                  <iconify-icon icon="ic:sharp-whatsapp"></iconify-icon>
               </span>
               <span class="nav-text">Bulk WhatsApp Marketing</span>
            </a>
         </li>




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
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="card_design.php">Cards Design</a>
                  </li>
                  <li class="sub-nav-item">
                     <a class="sub-nav-link" href="standy_design.php">Sticker Design</a>
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
                  <!-- <li class="sub-nav-item">
                     <a class="sub-nav-link" href="bank-details.php">Bank Details</a>
                  </li> -->
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
            <a class="nav-link" href="customer_data.php">
               <span class="nav-icon">
                  <iconify-icon icon="streamline:information-desk-customer"></iconify-icon>
               </span>
               <span class="nav-text">Customer Data</span>
            </a>
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
            <a class="nav-link" href="tax-settings.php">
               <span class="nav-icon">
                  <iconify-icon icon="heroicons-outline:receipt-tax"></iconify-icon>
               </span>
               <span class="nav-text">Tax Settings</span>
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

<!-- Floating Action Buttons for Room Management -->
<!-- <div class="room-floating-buttons">
   <a href="quick-checkin.php" class="room-floating-btn quick-checkin" data-tooltip="Quick Check-In">
      <iconify-icon icon="mdi:login"></iconify-icon>
   </a>
   <a href="quick-checkout.php" class="room-floating-btn quick-checkout" data-tooltip="Quick Check-Out">
      <iconify-icon icon="mdi:logout"></iconify-icon>
   </a>
   <a href="add-booking.php" class="room-floating-btn quick-booking" data-tooltip="New Booking">
      <iconify-icon icon="mdi:calendar-plus"></iconify-icon>
   </a>
   <a href="manage-rooms.php" class="room-floating-btn quick-room" data-tooltip="Manage Rooms">
      <iconify-icon icon="mdi:bed"></iconify-icon>
   </a>
</div> -->

<script>
// Tooltip initialization for floating buttons
document.addEventListener('DOMContentLoaded', function() {
    const floatingButtons = document.querySelectorAll('.room-floating-btn');
    floatingButtons.forEach(button => {
        const tooltip = button.getAttribute('data-tooltip');
        if (tooltip) {
            button.setAttribute('title', tooltip);
        }
    });
});
</script>
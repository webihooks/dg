// socket-manager.js - Simplified Real-time Order Manager (Polling Only)
if (typeof RealTimeOrderManager === 'undefined') {

class RealTimeOrderManager {
    constructor() {
        this.pollingInterval = null;
        this.isBackground = false;
        this.lastCheck = new Date().toISOString();
        this.connectionState = 'polling';
        
        this.init();
    }
    
    init() {
        console.log('🔄 Initializing Real-time Order Manager (Polling Mode)');
        this.setupVisibilityHandling();
        this.startPolling();
        this.updateConnectionDisplay('polling', 'Polling Active');
    }
    
    startPolling() {
        // Clear existing interval
        if (this.pollingInterval) clearInterval(this.pollingInterval);
        
        // Check for new orders every 30 seconds
        this.pollingInterval = setInterval(() => {
            this.checkForNewOrders();
        }, 30000);
        
        // Initial check after 5 seconds
        setTimeout(() => {
            this.checkForNewOrders();
        }, 5000);
        
        console.log('✅ Real-time polling started (30s intervals)');
    }
    
    checkForNewOrders() {
        const url = `orders.php?realtime_check=1&last_check=${encodeURIComponent(this.lastCheck)}`;
        
        fetch(url, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.new_orders && data.new_orders.length > 0) {
                console.log(`🔄 Found ${data.new_orders.length} new order(s)`);
                this.handleNewOrders(data.new_orders);
            }
            
            // Update last check timestamp
            this.lastCheck = data.timestamp || new Date().toISOString();
            this.connectionState = 'polling';
        })
        .catch(error => {
            // console.error('❌ Polling check failed:', error);
            this.connectionState = 'disconnected';
            this.updateConnectionDisplay('disconnected', 'Polling Failed');
        });
    }
    
    handleNewOrders(newOrders) {
        if (newOrders.length > 0) {
            // Show browser notification if available
            this.showBrowserNotification(newOrders.length);
            
            // Show toast notification
            this.showToast(`🚨 ${newOrders.length} new order(s) received!`, 'success');
            
            // Play notification sound
            this.playNotificationSound();
            
            // Vibrate if available
            this.vibrateDevice();
            
            // Refresh page after delay to show new orders
            setTimeout(() => {
                if (confirm(`${newOrders.length} new order(s) received. Refresh page to view?`)) {
                    location.reload();
                }
            }, 2000);
        }
    }
    
    showBrowserNotification(orderCount) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification('🚨 New Orders Received!', {
                body: `${orderCount} new order(s) waiting for your action`,
                icon: '/images/dg_logo.png',
                tag: 'new-orders',
                requireInteraction: true
            });
            
            notification.onclick = () => {
                window.focus();
                notification.close();
            };
        }
    }
    
    showToast(message, type) {
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
    
    playNotificationSound() {
        try {
            const audio = new Audio('/assets/sounds/new_order.mp3');
            audio.play().catch(e => console.log('🔇 Sound play failed:', e));
        } catch (error) {
            console.log('🔇 Sound play error:', error);
        }
    }
    
    vibrateDevice() {
        if (navigator.vibrate) {
            navigator.vibrate([200, 100, 200]);
        }
    }
    
    setupVisibilityHandling() {
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            this.isBackground = document.hidden;
            
            if (!this.isBackground) {
                // Page became visible - check immediately
                console.log('🔔 Page visible - checking for new orders');
                this.checkForNewOrders();
            }
        });
        
        // Handle online/offline status
        window.addEventListener('online', () => {
            console.log('🌐 Online - resuming polling');
            this.startPolling();
            this.updateConnectionDisplay('polling', 'Polling Active');
        });
        
        window.addEventListener('offline', () => {
            console.log('🌐 Offline - pausing polling');
            if (this.pollingInterval) clearInterval(this.pollingInterval);
            this.updateConnectionDisplay('disconnected', 'Offline');
        });
    }
    
    updateConnectionDisplay(status, text) {
        const event = new CustomEvent('websocketStatus', {
            detail: { status, text }
        });
        document.dispatchEvent(event);
    }
    
    // Clean up method
    destroy() {
        console.log('🧹 Cleaning up Real-time Order Manager');
        if (this.pollingInterval) clearInterval(this.pollingInterval);
    }
}

// Safe initialization
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Starting Real-time Order Manager');
    
    // Check if already initialized
    if (window.realTimeManager) {
        console.log('⚠️ Real-time manager already initialized');
        return;
    }
    
    try {
        window.realTimeManager = new RealTimeOrderManager();
        
        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (window.realTimeManager) {
                window.realTimeManager.destroy();
            }
        });
    } catch (error) {
        console.error('💥 Failed to initialize real-time manager:', error);
    }
});

} // End of if undefined check
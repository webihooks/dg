// =============================================
// ENHANCED FCM SERVICE WORKER - COMPATIBLE VERSION
// =============================================
// START Enhanced FCM Service Worker
console.log('✅ FCM Service Worker loading...');

const CACHE_NAME = 'dgcard-push-v2';
const APP_SERVER = 'https://dgcard.online';

// Simple notification configuration
const NOTIFICATION_CONFIG = {
    defaultIcon: '/assets/images/logo-sm.png',
    defaultBadge: '/assets/images/logo-sm.png',
    vibrationPattern: [300, 200, 300, 200, 300]
};

// =============================================
// SERVICE WORKER INSTALLATION
// =============================================
self.addEventListener('install', (event) => {
    console.log('🔄 Service Worker installing...');
    self.skipWaiting(); // Activate immediately
});

// =============================================
// SERVICE WORKER ACTIVATION
// =============================================
self.addEventListener('activate', (event) => {
    console.log('🎯 Service Worker activating...');
    event.waitUntil(self.clients.claim());
});

// =============================================
// PUSH NOTIFICATION HANDLER (SIMPLIFIED)
// =============================================
self.addEventListener('push', function(event) {
    console.log('🔔 Push event received');
    
    if (!event.data) {
        console.log('⚠️ No data in push event');
        return;
    }

    let data;
    try {
        // Parse the push data
        data = event.data.json();
        console.log('📨 Push data:', data);
    } catch (e) {
        console.log('📨 Push data is text, parsing...');
        try {
            data = JSON.parse(event.data.text());
        } catch (parseError) {
            console.error('❌ Cannot parse push data');
            data = createFallbackData();
        }
    }

    // Process the notification
    event.waitUntil(showNotification(data));
});

// =============================================
// CREATE FALLBACK NOTIFICATION DATA
// =============================================
function createFallbackData() {
    return {
        title: 'New Order!',
        body: 'You have received a new order',
        data: {
            order_id: 'unknown',
            type: 'new_order'
        }
    };
}

// =============================================
// SHOW NOTIFICATION (MAIN FUNCTION)
// =============================================
async function showNotification(data) {
    console.log('🔄 Showing notification...');
    
    // Enhanced notification options
    const options = {
        body: data.body || 'New order received!',
        icon: data.icon || NOTIFICATION_CONFIG.defaultIcon,
        badge: data.badge || NOTIFICATION_CONFIG.defaultBadge,
        image: data.image,
        data: data.data || {},
        tag: data.data?.order_id ? `order-${data.data.order_id}` : 'new-order',
        renotify: true,
        requireInteraction: true,
        silent: false,
        vibrate: NOTIFICATION_CONFIG.vibrationPattern,
        actions: [
            {
                action: 'view',
                title: '📋 View Order',
                icon: '/assets/images/view-icon.png'
            },
            {
                action: 'accept', 
                title: '✅ Accept',
                icon: '/assets/images/accept-icon.png'
            }
        ]
    };

    try {
        // Show the notification
        await self.registration.showNotification(
            data.title || 'New Order!',
            options
        );
        
        console.log('✅ Notification shown successfully');
        
        // Notify all open pages
        notifyAllClients({
            type: 'NEW_ORDER_PUSH_NOTIFICATION',
            orderData: data.data,
            action: 'play_sound'
        });
        
    } catch (error) {
        console.error('❌ Notification failed:', error);
        
        // Fallback: Try without actions
        try {
            const fallbackOptions = { ...options };
            delete fallbackOptions.actions;
            
            await self.registration.showNotification(
                data.title || 'New Order!',
                fallbackOptions
            );
            console.log('✅ Fallback notification shown');
        } catch (fallbackError) {
            console.error('❌ Fallback notification also failed:', fallbackError);
        }
    }
}

// =============================================
// NOTIFY ALL CLIENTS (TABS)
// =============================================
async function notifyAllClients(message) {
    try {
        const clients = await self.clients.matchAll();
        console.log(`📢 Notifying ${clients.length} clients`);
        
        clients.forEach(client => {
            try {
                client.postMessage(message);
            } catch (e) {
                console.error('Failed to notify client:', e);
            }
        });
    } catch (error) {
        console.error('Error notifying clients:', error);
    }
}

// =============================================
// NOTIFICATION CLICK HANDLER
// =============================================
self.addEventListener('notificationclick', function(event) {
    console.log('🖱️ Notification clicked:', event.action);
    
    event.notification.close();
    
    const data = event.notification.data;
    const action = event.action;
    
    // Stop sounds on any click
    notifyAllClients({
        type: 'STOP_NOTIFICATION_SOUND'
    });

    // Handle different actions
    if (action === 'view' || action === 'accept') {
        handleOrderAction(data, action);
    } else {
        handleDefaultClick(data);
    }
});

// =============================================
// HANDLE ORDER ACTIONS
// =============================================
function handleOrderAction(data, action) {
    console.log(`🎯 Handling ${action} action for order:`, data.order_id);
    
    const url = `${APP_SERVER}/orders.php`;
    
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(windowClients => {
            // Check if there's already a window open
            for (const client of windowClients) {
                if (client.url.includes(APP_SERVER) && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            
            // Open new window if none exists
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        }).then(() => {
            // Notify clients about the action
            notifyAllClients({
                type: `NOTIFICATION_ACTION_${action.toUpperCase()}`,
                orderId: data.order_id
            });
        })
    );
}

// =============================================
// HANDLE DEFAULT CLICK
// =============================================
function handleDefaultClick(data) {
    console.log('🖱️ Default click for order:', data.order_id);
    
    const url = data.order_id ? 
        `${APP_SERVER}/orders.php?highlight_order=${data.order_id}` :
        `${APP_SERVER}/orders.php`;
    
    event.waitUntil(clients.openWindow(url));
}

// =============================================
// MESSAGE HANDLER (CLIENT COMMUNICATION)
// =============================================
self.addEventListener('message', function(event) {
    console.log('📨 Service Worker received message:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// =============================================
// INITIALIZATION COMPLETE
// =============================================
console.log('✅ FCM Service Worker loaded successfully');
// =============================================
// END Enhanced FCM Service Worker
// =============================================
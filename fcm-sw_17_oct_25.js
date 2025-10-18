// Service Worker for Web Push Notifications with Continuous Ring
const CACHE_NAME = 'dgcard-push-v1';
const APP_SERVER = 'https://dgcard.online';

// Audio management for continuous playback
let audioContext = null;
let audioSource = null;
let isPlaying = true;
let currentOrderId = null;

// Install event
self.addEventListener('install', (event) => {
    console.log('Service Worker installed');
    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('Service Worker activated');
    event.waitUntil(self.clients.claim());
});

// Function to play continuous ring sound
async function playContinuousRing() {
    if (isPlaying) return;
    
    console.log('Starting continuous ring sound');
    isPlaying = true;
    
    try {
        // Create audio context if not exists
        if (!audioContext) {
            audioContext = new (self.AudioContext || self.webkitAudioContext)();
        }
        
        // Fetch and play the ring sound in a loop
        const response = await fetch('https://dgcard.online/assets/sounds/new_order.wav');
        const arrayBuffer = await response.arrayBuffer();
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
        
        function playLoop() {
            if (!isPlaying) return;
            
            audioSource = audioContext.createBufferSource();
            audioSource.buffer = audioBuffer;
            audioSource.connect(audioContext.destination);
            audioSource.loop = false; // We'll handle looping manually
            
            audioSource.onended = function() {
                if (isPlaying) {
                    console.log('Ring sound ended, restarting...');
                    setTimeout(playLoop, 100); // Small delay before restart
                }
            };
            
            audioSource.start();
            console.log('Ring sound started');
        }
        
        playLoop();
        
    } catch (error) {
        console.error('Error playing ring sound:', error);
        // Fallback: Try to play using simple audio element
        playFallbackRing();
    }
}

// Fallback method using Audio element
function playFallbackRing() {
    console.log('Using fallback audio method');
    
    // This will be handled by the client page when it receives the message
    // Send message to all clients to play sound
    self.clients.matchAll().then(clients => {
        clients.forEach(client => {
            client.postMessage({
                type: 'PLAY_CONTINUOUS_RING',
                orderId: currentOrderId
            });
        });
    });
}

// Function to stop continuous ring sound
function stopContinuousRing() {
    console.log('Stopping continuous ring sound');
    isPlaying = false;
    currentOrderId = null;
    
    // Stop Web Audio API playback
    if (audioSource) {
        try {
            audioSource.stop();
            audioSource.disconnect();
        } catch (e) {
            console.log('Error stopping audio source:', e);
        }
        audioSource = null;
    }
    
    // Also notify clients to stop sound
    self.clients.matchAll().then(clients => {
        clients.forEach(client => {
            client.postMessage({
                type: 'STOP_CONTINUOUS_RING'
            });
        });
    });
}

// Push event listener - handles background push notifications
self.addEventListener('push', function(event) {
    console.log('Push event received:', event);
    
    if (!event.data) return;

    let data;
    try {
        data = event.data.json();
        console.log('Push data:', data);
    } catch (e) {
        console.log('Push data is text:', event.data.text());
        try {
            data = JSON.parse(event.data.text());
        } catch (parseError) {
            console.log('Push data is not JSON');
            data = {
                title: 'New Order!',
                body: 'You have received a new order',
                data: {
                    order_id: 'unknown',
                    type: 'new_order'
                }
            };
        }
    }

    // Store current order ID for sound management
    currentOrderId = data.data?.order_id || 'unknown';
    
    // Start continuous ring sound
    playContinuousRing();

    // Enhanced notification options with all order details
    const options = {
        body: data.body || 'New order received!',
        icon: data.icon || '/assets/images/logo-sm.png',
        badge: data.badge || '/assets/images/logo-sm.png',
        image: data.image,
        data: data.data || {},
        tag: data.data?.order_id ? `order-${data.data.order_id}` : 'new-order',
        renotify: true,
        requireInteraction: true,
        silent: false,
        sound: data.sound || 'https://dgcard.online/assets/sounds/new_order.wav',
        vibrate: [200, 100, 200, 100, 200, 300, 200, 100, 200],
        actions: data.actions || [
            {
                action: 'view',
                title: '📋 View Order',
                icon: '/assets/images/eye-icon.png'
            },
            {
                action: 'dismiss',
                title: '❌ Dismiss',
                icon: '/assets/images/close-icon.png'
            }
        ],
        timestamp: data.data?.timestamp || Date.now(),
        dir: 'auto',
        lang: 'en-US'
    };

    console.log('Showing notification with options:', options);

    event.waitUntil(
        self.registration.showNotification(
            data.title || 'New Order!',
            options
        ).then(() => {
            console.log('Notification shown successfully');
            
            // Also notify all open pages about the new order
            return self.clients.matchAll();
        }).then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'NEW_ORDER_NOTIFICATION',
                    orderId: currentOrderId,
                    notificationData: data
                });
            });
        }).catch(error => {
            console.error('Error showing notification:', error);
        })
    );
});

// Notification click event - enhanced with order details
self.addEventListener('notificationclick', function(event) {
    console.log('Notification clicked:', event);
    console.log('Notification data:', event.notification.data);
    
    // Stop the continuous ring sound when any action is taken
    stopContinuousRing();
    
    event.notification.close();

    const orderId = event.notification.data?.order_id;
    const customerName = event.notification.data?.customer_name || 'Customer';
    const totalAmount = event.notification.data?.total_amount || '0';
    
    let url = `${APP_SERVER}/orders.php`;
    
    // Add order ID to URL for direct navigation
    if (orderId && orderId !== 'unknown') {
        url += `?highlight_order=${orderId}`;
    }

    if (event.action === 'view') {
        console.log('View Order action clicked for order:', orderId);
        
        event.waitUntil(
            clients.matchAll({type: 'window'}).then(windowClients => {
                // Check if there's already a window/tab open with the target URL
                for (let client of windowClients) {
                    if (client.url.includes(APP_SERVER) && 'focus' in client) {
                        // Navigate to specific order and focus
                        client.navigate(url);
                        return client.focus();
                    }
                }
                // If no window/tab is open, open a new one
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
        );
    } else if (event.action === 'dismiss') {
        // Just close the notification (sound already stopped)
        console.log('Notification dismissed for order:', orderId);
    } else {
        // Default click behavior - open orders page
        console.log('Default notification click for order:', orderId);
        
        event.waitUntil(
            clients.openWindow(url)
        );
    }
});

// Handle notification close
self.addEventListener('notificationclose', function(event) {
    console.log('Notification closed:', event.notification);
    console.log('Order ID:', event.notification.data?.order_id);
    
    // Stop sound when notification is closed without clicking actions
    stopContinuousRing();
});

// Message event for communication with main thread
self.addEventListener('message', function(event) {
    console.log('Service Worker received message:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    // Handle sound control messages from client
    if (event.data && event.data.type === 'STOP_RING_SOUND') {
        stopContinuousRing();
    }
});

// Background sync for offline support
self.addEventListener('sync', function(event) {
    if (event.tag === 'background-sync') {
        console.log('Background sync triggered');
        event.waitUntil(doBackgroundSync());
    }
});

async function doBackgroundSync() {
    console.log('Performing background sync');
    try {
        const cache = await caches.open(CACHE_NAME);
        console.log('Background sync completed');
    } catch (error) {
        console.error('Background sync error:', error);
    }
}
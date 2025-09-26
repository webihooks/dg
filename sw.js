// sw.js - Enhanced Service Worker for Background Notifications
const CACHE_NAME = 'deegeecard-v2.0';
const urlsToCache = [
    '/',
    '/login.php',
    '/index.php',
    '/register.php',
    '/contact.php',
    '/help.php',
    '/assets/sounds/new_order.mp3',
    'https://deegeecard.com/images/dg_logo.png',
    'https://cdn.tailwindcss.com',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Install event - Cache resources
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                return cache.addAll(urlsToCache);
            })
    );
    self.skipWaiting(); // Activate immediately
});

// Activate event - Clean up old caches
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    return self.clients.claim(); // Take control immediately
});

// Fetch event - Serve cached resources
self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                return response || fetch(event.request);
            })
    );
});

// Push Notification Event - Triggered when app is closed
self.addEventListener('push', function(event) {
    if (!event.data) return;
    
    const data = event.data.json();
    const options = {
        body: data.body || 'New order received!',
        icon: 'https://deegeecard.com/images/dg_logo.png',
        badge: 'https://deegeecard.com/images/dg_logo.png',
        vibrate: [200, 100, 200, 100, 200], // Vibration pattern
        requireInteraction: true, // Stay until user interacts
        actions: [
            {
                action: 'view',
                title: 'View Order',
                icon: 'https://deegeecard.com/images/view-icon.png'
            },
            {
                action: 'dismiss',
                title: 'Dismiss',
                icon: 'https://deegeecard.com/images/close-icon.png'
            }
        ],
        data: data.data || {},
        tag: 'new-order', // Group similar notifications
        renotify: true // Notify again for same tag
    };
    
    // Add sound if available
    if (data.sound) {
        options.sound = data.sound;
    }
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'New Order!', options)
    );
});

// Notification Click Event
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    if (event.action === 'view') {
        // Open the orders page
        event.waitUntil(
            clients.openWindow('/admin-dashboard.php?page=orders')
        );
    } else if (event.action === 'dismiss') {
        // Just close the notification
    } else {
        // Default click behavior
        event.waitUntil(
            clients.openWindow('/admin-dashboard.php')
        );
    }
});

// Background Sync for offline functionality
self.addEventListener('sync', function(event) {
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

// Periodic Sync (for Chrome)
self.addEventListener('periodicsync', function(event) {
    if (event.tag === 'check-orders') {
        event.waitUntil(checkForNewOrders());
    }
});

// Function to check for new orders
async function checkForNewOrders() {
    try {
        // This would typically make an API call to check for new orders
        const response = await fetch('/api/check-orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        if (response.ok) {
            const orders = await response.json();
            if (orders.length > 0) {
                // Show notification for new orders
                self.registration.showNotification('New Orders!', {
                    body: `You have ${orders.length} new orders`,
                    icon: 'https://deegeecard.com/images/dg_logo.png',
                    sound: '/assets/sounds/new_order.mp3'
                });
            }
        }
    } catch (error) {
        console.error('Background sync error:', error);
    }
}
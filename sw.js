// sw.js - Simple Service Worker for Visual Notifications
self.addEventListener('install', (event) => {
    self.skipWaiting();
    console.log('Service Worker installed');
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
    console.log('Service Worker activated');
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'NEW_ORDER') {
        const notificationOptions = {
            body: `You have ${event.data.count} new pending order(s)`,
            icon: '/assets/images/logo-sm.png',
            badge: '/assets/images/logo-sm.png',
            tag: 'new-order',
            requireInteraction: true,
            vibrate: [200, 100, 200, 100, 200],
            actions: [
                {
                    action: 'accept',
                    title: '✅ Accept Orders'
                },
                {
                    action: 'view',
                    title: '📋 View Orders'
                }
            ]
        };

        event.waitUntil(
            self.registration.showNotification('New Order Received!', notificationOptions)
        );
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    event.waitUntil(
        clients.matchAll({type: 'window'}).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes('orders.php') && 'focus' in client) {
                    return client.focus().then(() => {
                        if (event.action === 'accept') {
                            client.postMessage({type: 'ACCEPT_ORDERS'});
                        }
                    });
                }
            }
            return clients.openWindow('orders.php');
        })
    );
});
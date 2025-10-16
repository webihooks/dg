// firebase-messaging-sw.js
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js');

// Your web app's Firebase configuration
const firebaseConfig = {
  apiKey: "AIzaSyAWMbksJRig5XKQnDEGPO-BW3VzX7c6bug",
  authDomain: "deegeecard---web-14-oct-25.firebaseapp.com",
  projectId: "deegeecard---web-14-oct-25",
  storageBucket: "deegeecard---web-14-oct-25.firebasestorage.app",
  messagingSenderId: "252167545803",
  appId: "1:252167545803:web:22ae4c128a5cd4b8544c4e",
  measurementId: "G-E57TF8E47D"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Enhanced background message handler with ring notification
messaging.onBackgroundMessage((payload) => {
  console.log('📬 Background message received:', payload);

  const notificationTitle = payload.notification?.title || '🛎️ New Order Received!';
  const notificationOptions = {
    body: payload.notification?.body || 'Check the new order details immediately',
    icon: '/assets/images/logo-sm.png',
    badge: '/assets/images/logo-sm.png',
    image: payload.data?.image || '/assets/images/logo-sm.png',
    tag: 'new-order-background',
    renotify: true,
    requireInteraction: true,
    silent: false, // This ensures sound plays
    vibrate: [200, 100, 200, 100, 200, 100, 400], // Vibrate pattern
    data: payload.data,
    actions: [
      {
        action: 'view_order',
        title: '📱 View Order'
      },
      {
        action: 'dismiss',
        title: '❌ Dismiss'
      }
    ],
    // Android-specific options
    android: {
      sound: 'default',
      priority: 'high'
    }
  };

  // Play ring sound for new orders
  if (payload.data && payload.data.type === 'new_order') {
    playNotificationSound();
  }

  return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Enhanced notification click handler
self.addEventListener('notificationclick', (event) => {
  console.log('🔔 Notification click received:', event);
  
  event.notification.close();

  const action = event.action;
  const orderId = event.notification.data?.order_id;

  if (action === 'view_order' || !action) {
    // Focus on orders page
    event.waitUntil(
      clients.matchAll({type: 'window'}).then((clientList) => {
        // Check if there's already a window open with orders page
        for (const client of clientList) {
          if (client.url.includes('/orders.php') && 'focus' in client) {
            return client.focus();
          }
        }
        // If no orders window found, open one
        if (clients.openWindow) {
          return clients.openWindow('/orders.php');
        }
      })
    );
  } else if (action === 'dismiss') {
    // Just close the notification
    event.notification.close();
  }
});

// Function to play notification sound
function playNotificationSound() {
  // Create audio context for playing sound
  try {
    // Try to play the MP3 sound file
    self.registration.showNotification('New Order', {
      body: 'Ring ring! New order received!',
      silent: false // This should trigger the default notification sound
    });
    
    // Additional sound playing attempt
    fetch('/assets/sounds/new_order.mp3')
      .then(response => response.blob())
      .then(blob => {
        const audio = new Audio(URL.createObjectURL(blob));
        audio.play().catch(e => console.log('Audio play failed:', e));
      })
      .catch(err => console.log('Sound fetch failed:', err));
      
  } catch (error) {
    console.log('Sound play error:', error);
  }
}

// Handle push subscription updates
self.addEventListener('pushsubscriptionchange', (event) => {
  console.log('Push subscription changed:', event);
  
  event.waitUntil(
    self.registration.pushManager.subscribe(event.oldSubscription.options)
      .then((subscription) => {
        // Send new subscription to server
        return fetch('/fcm-token-handler.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            token: subscription.endpoint,
            action: 'update_subscription'
          })
        });
      })
  );
});
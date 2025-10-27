// // firebase-messaging-sw.js
// importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
// importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

// // Firebase configuration for TWA
// const firebaseConfig = {
//   apiKey: "your-api-key",
//   authDomain: "deegeecard-16-oct-25.firebaseapp.com",
//   projectId: "deegeecard-16-oct-25",
//   storageBucket: "deegeecard-16-oct-25.appspot.com",
//   messagingSenderId: "your-sender-id",
//   appId: "your-app-id"
// };

// // Initialize Firebase
// firebase.initializeApp(firebaseConfig);

// const messaging = firebase.messaging();

// // Enhanced background message handler for all Android versions
// messaging.onBackgroundMessage(function(payload) {
//   console.log('📱 [SW] Received background message:', payload);
  
//   const notificationTitle = payload.data?.title || 'New Order Received';
//   const notificationOptions = {
//     body: payload.data?.body || 'You have a new order to process',
//     icon: 'https://dgcard.online/assets/images/logo-sm.png',
//     badge: 'https://dgcard.online/assets/images/logo-sm.png',
//     image: 'https://dgcard.online/assets/images/logo-lg.png',
//     data: payload.data || {},
//     tag: 'new-order', // Group notifications
//     requireInteraction: true,
//     actions: [
//       {
//         action: 'view',
//         title: '📱 View Order'
//       },
//       {
//         action: 'dismiss',
//         title: 'Dismiss'
//       }
//     ],
//     // Enhanced for Android compatibility
//     vibrate: [200, 100, 200, 100, 200],
//     timestamp: Date.now()
//   };

//   // Add sound for browsers that support it
//   if ('sound' in Notification.prototype) {
//     notificationOptions.sound = 'https://dgcard.online/assets/sounds/new_order.mp3';
//   }

//   return self.registration.showNotification(notificationTitle, notificationOptions);
// });

// // Enhanced notification click handler
// self.addEventListener('notificationclick', function(event) {
//   console.log('📱 [SW] Notification clicked:', event);
  
//   event.notification.close();

//   if (event.action === 'view') {
//     // Open the orders page in TWA
//     event.waitUntil(
//       clients.matchAll({type: 'window', includeUncontrolled: true}).then(function(windowClients) {
//         let ordersUrl = 'https://dgcard.online/orders.php';
        
//         // Check if window is already open
//         for (let i = 0; i < windowClients.length; i++) {
//           let client = windowClients[i];
//           if (client.url.includes('dgcard.online') && 'focus' in client) {
//             return client.navigate(ordersUrl).then(client => client.focus());
//           }
//         }
        
//         // Open new window
//         if (clients.openWindow) {
//           return clients.openWindow(ordersUrl);
//         }
//       })
//     );
//   } else if (event.action === 'dismiss') {
//     // Notification dismissed - no action needed
//     console.log('Notification dismissed');
//   } else {
//     // Default click behavior
//     event.waitUntil(
//       clients.openWindow('https://dgcard.online/orders.php')
//     );
//   }
// });

// // Enhanced push subscription handler
// self.addEventListener('pushsubscriptionchange', function(event) {
//   console.log('📱 [SW] Push subscription changed');
  
//   event.waitUntil(
//     self.registration.pushManager.subscribe({
//       userVisibleOnly: true,
//       applicationServerKey: 'your-vapid-public-key'
//     }).then(function(newSubscription) {
//       // Send new subscription to server
//       return fetch('https://dgcard.online/fcm-token-handler.php', {
//         method: 'POST',
//         headers: {
//           'Content-Type': 'application/json'
//         },
//         body: JSON.stringify({
//           token: newSubscription,
//           device_type: 'web',
//           action: 'update'
//         })
//       });
//     })
//   );
// });
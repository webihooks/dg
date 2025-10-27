const CACHE_NAME = 'deegeecard-aggressive-v3';
const OFFLINE_URL = '/login.php';
const API_BASE_URL = 'https://deegeecard.com';
const SYNC_INTERVAL = 5 * 60 * 1000; // 5 minutes

// Critical assets for offline functionality
const CORE_ASSETS = [
  OFFLINE_URL,
  '/manifest.json',
  '/images/dg_logo.png',
  '/assets/css/vendor.min.css',
  '/assets/css/icons.min.css',
  '/assets/css/app.min.css',
  '/assets/js/vendor.js',
  '/assets/js/app.js',
  '/assets/js/config.js',
  '/assets/images/logo-dark.png',
  '/assets/images/logo-light.png',
  '/assets/images/small/img-10.jpg',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css'
];

// Aggressive caching strategy - Install
self.addEventListener('install', event => {
  console.log('[SW] Installing aggressive service worker...');
  self.skipWaiting(); // Force immediate activation
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[SW] Caching core assets aggressively');
        return cache.addAll(CORE_ASSETS);
      })
      .then(() => {
        console.log('[SW] All core assets cached');
        return self.skipWaiting();
      })
  );
});

// Aggressive activation - Take control immediately
self.addEventListener('activate', event => {
  console.log('[SW] Activating aggressive service worker...');
  
  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      }),
      
      // Take control of all clients immediately
      self.clients.claim(),
      
      // Start background sync immediately
      startBackgroundSync()
    ])
  );
});

// Aggressive fetch handling - Cache first, then network
self.addEventListener('fetch', event => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;

  const requestUrl = new URL(event.request.url);
  
  // API requests - Network first with aggressive caching
  if (requestUrl.pathname.includes('/api/')) {
    event.respondWith(handleApiRequest(event.request));
    return;
  }
  
  // Static assets - Cache first, then network
  event.respondWith(handleStaticRequest(event.request));
});

async function handleApiRequest(request) {
  const cache = await caches.open(CACHE_NAME);
  
  try {
    // Try network first for API calls
    const networkResponse = await fetch(request);
    
    // Cache successful API responses for quick offline access
    if (networkResponse.ok) {
      const clone = networkResponse.clone();
      cache.put(request, clone);
    }
    
    return networkResponse;
  } catch (error) {
    // If network fails, try cache
    console.log('[SW] API network failed, trying cache:', request.url);
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // If no cache, return offline page or error
    return new Response(JSON.stringify({ error: 'Offline - Please check connection' }), {
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

async function handleStaticRequest(request) {
  const cache = await caches.open(CACHE_NAME);
  const cachedResponse = await cache.match(request);
  
  // Return cached version immediately
  if (cachedResponse) {
    // Update cache in background
    fetch(request)
      .then(networkResponse => {
        if (networkResponse.ok) {
          cache.put(request, networkResponse);
        }
      })
      .catch(() => {}); // Silent fail for background update
      
    return cachedResponse;
  }
  
  // If not in cache, try network
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    // Final fallback to offline page
    if (request.mode === 'navigate') {
      return caches.match(OFFLINE_URL);
    }
    
    return new Response('Offline', { status: 503 });
  }
}

// ===== AGGRESSIVE BACKGROUND FEATURES =====

// Push Notifications - Wake up the app
self.addEventListener('push', event => {
  console.log('[SW] Push notification received aggressively');
  
  let data = {
    title: 'DeeGee Card Update',
    body: 'You have new updates!',
    url: OFFLINE_URL,
    icon: '/images/dg_logo.png',
    badge: '/images/dg_logo.png',
    tag: 'deegeecard-update'
  };
  
  if (event.data) {
    try {
      data = { ...data, ...event.data.json() };
    } catch (e) {
      console.log('[SW] Push data parsing error:', e);
    }
  }

  const options = {
    body: data.body,
    icon: data.icon,
    badge: data.badge,
    tag: data.tag,
    vibrate: [200, 100, 200, 100, 200],
    requireInteraction: true, // Keep notification visible until interaction
    actions: [
      { action: 'open', title: 'Open App' },
      { action: 'view_orders', title: 'View Orders' }
    ],
    data: {
      url: data.url,
      timestamp: Date.now()
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
      .then(() => {
        // Immediately sync data when notification is shown
        return syncOrdersAndData();
      })
  );
});

// Notification click handling
self.addEventListener('notificationclick', event => {
  console.log('[SW] Notification clicked aggressively');
  event.notification.close();
  
  const action = event.action;
  let url = event.notification.data.url;
  
  // Handle different actions
  if (action === 'view_orders') {
    url = '/orders.php'; // Direct to orders page
  }

  event.waitUntil(
    clients.matchAll({ 
      type: 'window',
      includeUncontrolled: true 
    }).then(windowClients => {
      // Check if app is already open
      for (let client of windowClients) {
        if (client.url.includes(url) && 'focus' in client) {
          return client.focus();
        }
      }
      
      // Open new window
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});

// Background Sync for offline data
self.addEventListener('sync', event => {
  console.log('[SW] Background sync triggered:', event.tag);
  
  if (event.tag === 'sync-orders') {
    event.waitUntil(syncOrdersAndData());
  } else if (event.tag === 'sync-pending') {
    event.waitUntil(syncPendingActions());
  }
});

// Periodic Sync (Experimental but aggressive)
self.addEventListener('periodicsync', event => {
  if (event.tag === 'periodic-check') {
    console.log('[SW] Periodic sync triggered aggressively');
    event.waitUntil(performAggressiveSync());
  }
});

// ===== AGGRESSIVE SYNC FUNCTIONS =====

async function startBackgroundSync() {
  if ('periodicSync' in self.registration) {
    try {
      await self.registration.periodicSync.register('periodic-check', {
        minInterval: SYNC_INTERVAL // Try every 5 minutes
      });
      console.log('[SW] Periodic background sync registered aggressively');
    } catch (error) {
      console.log('[SW] Periodic sync not supported:', error);
    }
  }
  
  // Register for one-off syncs
  if ('sync' in self.registration) {
    try {
      await self.registration.sync.register('sync-orders');
      console.log('[SW] Background sync registered');
    } catch (error) {
      console.log('[SW] Background sync registration failed:', error);
    }
  }
}

async function performAggressiveSync() {
  console.log('[SW] Performing aggressive sync');
  
  try {
    // Sync multiple data sources aggressively
    await Promise.all([
      syncOrdersAndData(),
      syncUserStatus(),
      checkForUpdates()
    ]);
    
    console.log('[SW] Aggressive sync completed successfully');
  } catch (error) {
    console.error('[SW] Aggressive sync failed:', error);
  }
}

async function syncOrdersAndData() {
  try {
    console.log('[SW] Syncing orders and data aggressively');
    
    // Check for new orders
    const ordersResponse = await fetch('/api/check-new-orders', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Background-Sync': 'true'
      },
      credentials: 'include'
    });
    
    if (ordersResponse.ok) {
      const ordersData = await ordersResponse.json();
      
      if (ordersData.newOrders && ordersData.newOrders.length > 0) {
        // Show immediate notification for new orders
        await self.registration.showNotification(`New Orders (${ordersData.newOrders.length})`, {
          body: `You have ${ordersData.newOrders.length} new orders waiting!`,
          icon: '/images/dg_logo.png',
          badge: '/images/dg_logo.png',
          vibrate: [300, 100, 300],
          requireInteraction: true,
          data: { url: '/admin-dashboard.php' }
        });
      }
      
      // Update badge count if supported
      if ('setAppBadge' in navigator) {
        navigator.setAppBadge(ordersData.newOrders.length);
      }
    }
    
    return true;
  } catch (error) {
    console.error('[SW] Order sync failed:', error);
    return false;
  }
}

async function syncUserStatus() {
  try {
    const response = await fetch('/api/user-status', {
      credentials: 'include'
    });
    
    if (response.ok) {
      const status = await response.json();
      // Update local storage or send message to client
      const clients = await self.clients.matchAll();
      clients.forEach(client => {
        client.postMessage({
          type: 'USER_STATUS_UPDATE',
          payload: status
        });
      });
    }
  } catch (error) {
    console.error('[SW] User status sync failed:', error);
  }
}

async function checkForUpdates() {
  try {
    const response = await fetch('/api/check-updates', {
      cache: 'no-cache'
    });
    
    if (response.ok) {
      const updates = await response.json();
      if (updates.available) {
        // Notify about app updates
        self.registration.showNotification('App Update Available', {
          body: 'A new version of DeeGee Card is available!',
          icon: '/images/dg_logo.png',
          tag: 'app-update'
        });
      }
    }
  } catch (error) {
    console.error('[SW] Update check failed:', error);
  }
}

async function syncPendingActions() {
  // Sync any pending actions that were queued while offline
  console.log('[SW] Syncing pending actions');
  // Implementation depends on your specific pending actions
}

// Message handling from main thread
self.addEventListener('message', event => {
  console.log('[SW] Message received:', event.data);
  
  if (event.data.type === 'FORCE_SYNC') {
    performAggressiveSync();
  } else if (event.data.type === 'REGISTER_PUSH') {
    // Handle push registration from client
  }
});

// Keep alive mechanism - periodic wake-ups
setInterval(() => {
  console.log('[SW] Keep-alive ping');
  syncOrdersAndData().catch(() => {});
}, SYNC_INTERVAL);
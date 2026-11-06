// ══════════════════════════════════════════════════════════════
// WINCHESTER HEALTH CENTRE - ADMIN APP SERVICE WORKER
// Enables PWA Standalone Mode, Offline Shell & Native Push Notifications
// ══════════════════════════════════════════════════════════════

const CACHE_NAME = 'winchester-admin-v1.0.0';
const STATIC_ASSETS = [
  './manifest.json',
  './icons/icon-192.png',
  './icons/icon-512.png',
  './icons/icon-maskable.png',
  './icons/apple-touch-icon.png',
  './icons/favicon.png'
];

// Install: Cache static icons & manifest
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activate: Clean up older caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch: Network First strategy for dynamic PHP data
self.addEventListener('fetch', event => {
  const request = event.request;
  
  // Non-GET requests (e.g. POST to update_booking.php) always bypass service worker
  if (request.method !== 'GET') {
    return;
  }

  event.respondWith(
    fetch(request)
      .then(networkResponse => {
        // Cache successful responses for static assets only
        if (networkResponse && networkResponse.status === 200) {
          const url = request.url;
          if (url.includes('/icons/') || url.includes('manifest.json')) {
            const clone = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
          }
        }
        return networkResponse;
      })
      .catch(() => {
        // Fallback to cache if offline
        return caches.match(request);
      })
  );
});

// ══════════════════════════════════════════════════════════════
// NATIVE PUSH NOTIFICATION LISTENER
// ══════════════════════════════════════════════════════════════
self.addEventListener('push', event => {
  let data = {
    title: 'Winchester Health Centre',
    body: 'New appointment update received.',
    icon: './icons/icon-192.png',
    badge: './icons/favicon.png',
    url: './index.php'
  };

  if (event.data) {
    try {
      const parsed = event.data.json();
      data = Object.assign(data, parsed);
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon,
    badge: data.badge,
    vibrate: [200, 100, 200, 100, 200],
    data: {
      url: data.url || './index.php',
      booking_id: data.booking_id || null
    },
    actions: [
      { action: 'open', title: '👁️ View Appointment' },
      { action: 'close', title: 'Dismiss' }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification Click Handler: Focus or open app
self.addEventListener('notificationclick', event => {
  event.notification.close();

  if (event.action === 'close') {
    return;
  }

  const targetUrl = event.notification.data && event.notification.data.url 
    ? event.notification.data.url 
    : './index.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      // If app is already open, focus it
      for (let client of windowClients) {
        if ('focus' in client) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }
      // Otherwise open a new standalone window
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});

// sw.js - Service Worker with Push Notifications

const CACHE_NAME = 'paisapay-v2';
const ASSETS = [
    '/',
    '/index.html',
    '/login.html',
    '/signup.html',
    '/dashboard.html',
    '/earn.html',
    '/leaderboard.html',
    '/invite.html',
    '/withdraw.html',
    '/profile.html',
    '/css/style.css',
    '/js/main.js',
    '/js/config.js',
    '/js/api.js',
    '/js/auth.js',
    '/js/dashboard.js',
    '/js/firebase-config.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            console.log('Caching assets...');
            return cache.addAll(ASSETS);
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
            );
        })
    );
    return self.clients.claim();
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request).catch(() => {
                if (event.request.mode === 'navigate') {
                    return caches.match('/index.html');
                }
            });
        })
    );
});

// ============================================
// ✅ PUSH NOTIFICATIONS
// ============================================

self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }
    
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = {
                title: 'PaisaPay',
                body: event.data.text() || 'New notification'
            };
        }
    }
    
    const title = data.title || 'PaisaPay';
    const options = {
        body: data.body || 'New notification',
        icon: data.icon || '/icon-192x192.png',
        badge: data.badge || '/icon-192x192.png',
        data: data.data || {},
        actions: data.actions || [
            {
                action: 'open',
                title: 'Open'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    const url = event.notification.data?.click_action || '/dashboard.html';
    
    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(function(windowClients) {
            // Check if there's already a window/tab open with the target URL
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            // If not, open a new window/tab
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
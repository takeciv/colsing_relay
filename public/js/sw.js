/* Service Worker for Colsing Relay PWA */

const CACHE_NAME = 'colsing-relay-v1';
const STATIC_ASSETS = [
  '/css/style.css',
  '/js/app.js',
  '/manifest.json',
];

// インストール時に静的アセットをキャッシュ
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// 古いキャッシュを削除
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// フェッチ：静的アセットはキャッシュファースト、その他はネットワークファースト
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (STATIC_ASSETS.includes(url.pathname)) {
    event.respondWith(
      caches.match(event.request).then((cached) => cached || fetch(event.request))
    );
  }
  // 通常のナビゲーションはキャッシュしない（常に最新のPHPレスポンスを取得）
});

// プッシュ通知受信
self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { title: 'Colsing Relay', body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'Colsing Relay';
  const options = {
    body: data.body || '',
    icon: '/images/icon-192.png',
    badge: '/images/icon-192.png',
    data: { url: data.url || '/index' },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// 通知クリック時にイベント詳細画面へ遷移
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || '/index';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url === targetUrl && 'focus' in client) {
          return client.focus();
        }
      }
      return clients.openWindow(targetUrl);
    })
  );
});

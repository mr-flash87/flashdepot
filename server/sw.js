/* Service worker FlashDépôt : rend le site installable et affiche les notifications (avec le son du téléphone). */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
// Pas de gestionnaire « fetch » : les pages vont directement au serveur (plus rapide, montants toujours à jour).

self.addEventListener('push', (event) => {
  let d = {};
  try { d = event.data ? event.data.json() : {}; } catch (e) { d = { title: 'FlashDépôt', body: event.data ? event.data.text() : '' }; }
  const base = self.registration.scope;
  event.waitUntil((async () => {
    // Application ouverte : on prévient la page pour qu'elle joue son propre son et se rafraîchisse.
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    wins.forEach((c) => c.postMessage({ type: 'push', title: d.title, body: d.body }));
    await self.registration.showNotification(d.title || 'FlashDépôt', {
      body: d.body || '',
      tag: d.tag || undefined,
      renotify: true,
      silent: false,
      requireInteraction: false,
      icon: base + 'assets/icon-192.png',
      badge: base + 'assets/badge-96.png',
      vibrate: [200, 100, 200, 100, 300],
      data: { url: d.url || base },
    });
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data && event.notification.data.url;
  event.waitUntil((async () => {
    const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of all) {
      if (c.url.startsWith(self.registration.scope) && 'focus' in c) {
        await c.focus();
        if (url && 'navigate' in c) { try { await c.navigate(url); } catch (e) {} }
        return;
      }
    }
    if (url) await self.clients.openWindow(url);
  })());
});

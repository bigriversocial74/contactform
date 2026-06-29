/* Microgifter PWA service worker: push delivery only. Do not cache authenticated API responses here. */
const MG_DEFAULT_URL = '/notifications.php';
const MG_DEFAULT_ICON = '/images/logo_main_drk.png';

function safeInternalUrl(value) {
  try {
    const raw = String(value || MG_DEFAULT_URL).trim();
    if (!raw || !raw.startsWith('/') || raw.startsWith('//') || /[\u0000-\u001f\u007f]/.test(raw)) return MG_DEFAULT_URL;
    const url = new URL(raw, self.location.origin);
    if (url.origin !== self.location.origin) return MG_DEFAULT_URL;
    return url.pathname + url.search + url.hash;
  } catch (error) { return MG_DEFAULT_URL; }
}

function safeAssetUrl(value, fallback) {
  try {
    const raw = String(value || fallback || MG_DEFAULT_ICON).trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return fallback || MG_DEFAULT_ICON;
    const url = new URL(raw, self.location.origin);
    if (url.protocol !== 'https:' && url.origin !== self.location.origin) return fallback || MG_DEFAULT_ICON;
    return url.href;
  } catch (error) { return fallback || MG_DEFAULT_ICON; }
}

async function manifestIconFallback() {
  try {
    const response = await fetch('/manifest.php', { cache: 'no-store', credentials: 'omit' });
    const manifest = await response.json();
    const icons = Array.isArray(manifest.icons) ? manifest.icons : [];
    const picked = icons.find((icon) => String(icon.sizes || '').includes('192')) || icons.find((icon) => String(icon.sizes || '').includes('512')) || icons[0];
    return picked && picked.src ? safeAssetUrl(picked.src, MG_DEFAULT_ICON) : MG_DEFAULT_ICON;
  } catch (error) { return MG_DEFAULT_ICON; }
}

self.addEventListener('install', (event) => { event.waitUntil(self.skipWaiting()); });
self.addEventListener('activate', (event) => { event.waitUntil(self.clients.claim()); });

self.addEventListener('push', (event) => {
  event.waitUntil((async () => {
    let payload = {};
    try { payload = event.data ? event.data.json() : {}; }
    catch (error) { payload = { title: 'Microgifter update', body: 'Open Microgifter for details.' }; }
    const manifestIcon = await manifestIconFallback();
    const payloadIcon = String(payload.icon || '');
    const payloadBadge = String(payload.badge || '');
    const icon = payloadIcon === MG_DEFAULT_ICON || payloadIcon === '' ? manifestIcon : safeAssetUrl(payloadIcon, manifestIcon);
    const badge = payloadBadge === MG_DEFAULT_ICON || payloadBadge === '' ? manifestIcon : safeAssetUrl(payloadBadge, manifestIcon);
    const title = String(payload.title || 'Microgifter update').slice(0, 90);
    const options = {
      body: String(payload.body || 'Open Microgifter for details.').slice(0, 180),
      icon: icon,
      badge: badge,
      tag: String(payload.notification_id || payload.notification_type || 'microgifter-update').slice(0, 120),
      renotify: false,
      data: {
        notification_id: String(payload.notification_id || ''),
        notification_type: String(payload.notification_type || 'system'),
        action_url: safeInternalUrl(payload.action_url),
        created_at: payload.created_at || new Date().toISOString()
      }
    };
    await self.registration.showNotification(title, options);
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const data = event.notification.data || {};
  const actionUrl = safeInternalUrl(data.action_url);
  const notificationId = String(data.notification_id || '');
  const trackOpen = notificationId ? fetch('/api/pwa/notification-open.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'MicrogifterPWA' }, body: JSON.stringify({ notification_id: notificationId }) }).catch(() => null) : Promise.resolve(null);
  const openWindow = self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
    const absoluteUrl = new URL(actionUrl, self.location.origin).href;
    for (const client of clients) if (client.url === absoluteUrl && 'focus' in client) return client.focus();
    return self.clients.openWindow(actionUrl);
  });
  event.waitUntil(Promise.all([trackOpen, openWindow]));
});

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var panel = document.querySelector('[data-pwa-notification-panel]');
  if (!panel || !window.Microgifter) return;

  var MG = window.Microgifter;
  function ensureManifestLink() {
    if (document.querySelector('link[rel="manifest"]')) return;
    var link = document.createElement('link');
    link.rel = 'manifest';
    link.href = '/manifest.webmanifest';
    document.head.appendChild(link);
  }

  ensureManifestLink();

  var statusText = panel.querySelector('[data-pwa-status-text]');
  var detailText = panel.querySelector('[data-pwa-detail-text]');
  var enableButton = panel.querySelector('[data-pwa-enable]');
  var disableButton = panel.querySelector('[data-pwa-disable]');
  var statusGrid = panel.querySelector('[data-pwa-status-grid]');
  var serverStatus = null;
  var currentRegistration = null;

  function setCopy(status, detail) {
    if (statusText) statusText.textContent = status || 'PWA notifications';
    if (detailText) detailText.textContent = detail || '';
  }

  function pill(label, value, tone) {
    var item = document.createElement('span');
    item.className = 'mg-pwa-status-pill is-' + (tone || 'neutral');
    item.innerHTML = '<strong></strong><em></em>';
    item.querySelector('strong').textContent = label;
    item.querySelector('em').textContent = value;
    return item;
  }

  function yesNo(value) { return value ? 'Yes' : 'No'; }

  function supportState() {
    return {
      serviceWorker: 'serviceWorker' in navigator,
      pushManager: 'PushManager' in window,
      notification: 'Notification' in window,
      secureContext: window.isSecureContext === true
    };
  }

  function render() {
    var support = supportState();
    var permission = support.notification ? Notification.permission : 'unsupported';
    var subscribed = Boolean(serverStatus && Number(serverStatus.active_subscriptions || 0) > 0);
    var configured = Boolean(serverStatus && serverStatus.public_key_configured);
    var tablesReady = Boolean(serverStatus && serverStatus.subscription_tables_ready);
    var canSubscribe = Boolean(serverStatus && serverStatus.can_subscribe && support.serviceWorker && support.pushManager && support.notification && support.secureContext);

    if (statusGrid) {
      statusGrid.replaceChildren(
        pill('Service worker', yesNo(support.serviceWorker), support.serviceWorker ? 'good' : 'bad'),
        pill('Push API', yesNo(support.pushManager), support.pushManager ? 'good' : 'bad'),
        pill('Permission', permission, permission === 'granted' ? 'good' : (permission === 'denied' ? 'bad' : 'neutral')),
        pill('Subscription', subscribed ? 'Active' : 'Not active', subscribed ? 'good' : 'neutral'),
        pill('PWA tables', tablesReady ? 'Ready' : 'Missing', tablesReady ? 'good' : 'bad'),
        pill('VAPID key', configured ? 'Configured' : 'Missing', configured ? 'good' : 'bad')
      );
    }

    if (!support.secureContext) setCopy('HTTPS required', 'Browser push notifications require HTTPS, except on localhost for development.');
    else if (!support.serviceWorker || !support.pushManager || !support.notification) setCopy('Browser not supported', 'This browser does not expose all required PWA notification APIs.');
    else if (!tablesReady) setCopy('PWA tables missing', 'Run the PWA notification migration before enabling browser push.');
    else if (!configured) setCopy('Server key missing', 'Add the PWA VAPID public/private keys before users can subscribe.');
    else if (permission === 'denied') setCopy('Notifications blocked', 'Notifications are blocked in this browser. Update site permissions to enable them again.');
    else if (subscribed) setCopy('PWA notifications active', 'This browser can receive Microgifter notification events as push-style messages.');
    else setCopy('Enable browser notifications', 'Receive Microgifter gifts, claims, campaign, merchant, admin, and agent updates from this browser.');

    if (enableButton) enableButton.disabled = !canSubscribe || subscribed || permission === 'denied';
    if (disableButton) disableButton.disabled = !subscribed;
  }

  async function loadStatus() {
    var response = await MG.get('/api/pwa/push-subscription.php');
    var data = response.data || response;
    serverStatus = data;
    render();
    return data;
  }

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - base64String.length % 4) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = window.atob(base64);
    var output = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
    return output;
  }

  async function getRegistration() {
    if (currentRegistration) return currentRegistration;
    var swUrl = serverStatus && serverStatus.service_worker_url ? serverStatus.service_worker_url : '/sw.js';
    var scope = serverStatus && serverStatus.service_worker_scope ? serverStatus.service_worker_scope : '/';
    currentRegistration = await navigator.serviceWorker.register(swUrl, { scope: scope });
    await navigator.serviceWorker.ready;
    return currentRegistration;
  }

  async function subscribe() {
    if (!serverStatus) await loadStatus();
    var permission = await Notification.requestPermission();
    if (permission !== 'granted') { render(); return; }
    var registration = await getRegistration();
    var subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(serverStatus.public_key) });
    }
    var response = await MG.post('/api/pwa/push-subscription.php', { action: 'subscribe', subscription: subscription.toJSON() });
    var data = response.data || response;
    serverStatus = data.status_payload || serverStatus;
    render();
    if (MG.toast) MG.toast('PWA notifications enabled for this browser.', 'success');
  }

  async function unsubscribe() {
    var registration = await getRegistration();
    var subscription = await registration.pushManager.getSubscription();
    var subscriptionJson = subscription ? subscription.toJSON() : null;
    if (subscription) await subscription.unsubscribe();
    var response = await MG.post('/api/pwa/push-subscription.php', { action: 'unsubscribe', subscription: subscriptionJson });
    var data = response.data || response;
    serverStatus = data.status_payload || serverStatus;
    render();
    if (MG.toast) MG.toast('PWA notifications disabled for this browser.', 'success');
  }

  if (enableButton) enableButton.addEventListener('click', async function () {
    enableButton.disabled = true;
    enableButton.textContent = 'Enabling…';
    try { await subscribe(); } catch (error) { if (MG.toast) MG.toast(error.message || 'Unable to enable PWA notifications.', 'error'); render(); }
    finally { enableButton.textContent = 'Enable PWA notifications'; }
  });

  if (disableButton) disableButton.addEventListener('click', async function () {
    disableButton.disabled = true;
    disableButton.textContent = 'Disabling…';
    try { await unsubscribe(); } catch (error) { if (MG.toast) MG.toast(error.message || 'Unable to disable PWA notifications.', 'error'); render(); }
    finally { disableButton.textContent = 'Disable on this browser'; }
  });

  render();
  loadStatus().catch(function (error) {
    setCopy('Unable to load PWA notification status', error.message || 'Try again shortly.');
    if (enableButton) enableButton.disabled = true;
    if (disableButton) disableButton.disabled = true;
  });
});

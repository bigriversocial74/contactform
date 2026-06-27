document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!window.Microgifter) return;
  var app = document.querySelector('[data-notifications-page]');
  if (!app) return;
  var list = app.querySelector('[data-notification-list]');
  if (!list) return;
  var sourceById = Object.create(null);

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[c];
    });
  }
  function clean(value) {
    return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }
  function safeUrl(value) {
    var raw = String(value || '').trim();
    if (!raw || !raw.startsWith('/') || raw.startsWith('//') || /[\u0000-\u001f\u007f]/.test(raw)) return '';
    return raw;
  }
  function sourceInfo(item) {
    var context = item && item.context && typeof item.context === 'object' ? item.context : {};
    var system = String(context.source_system || '').trim();
    var channel = String(context.source_channel || '').trim();
    var label = String(context.source_label || '').trim();
    if (!label && system === 'merchant_crm') label = 'Merchant CRM';
    if (!label && system === 'store_canvas') label = 'Store Canvas';
    if (!label && item.type === 'message') label = 'Messages';
    return {
      id: item.public_id || item.id,
      label: label,
      system: system,
      channel: channel,
      thread: String(context.thread_public_id || context.thread_id || '').trim(),
      message: String(context.message_id || '').trim(),
      action: safeUrl(item.action_url || '')
    };
  }
  async function loadSourceIndex() {
    try {
      var response = await Microgifter.get('/api/notifications/index.php?limit=100');
      var data = response.data || response;
      (data.notifications || []).forEach(function (item) {
        var info = sourceInfo(item);
        if (info.id && (info.label || info.system || info.thread || info.message)) sourceById[info.id] = info;
      });
      decorate();
    } catch (error) {}
  }
  function decorate() {
    list.querySelectorAll('[data-notification-id]').forEach(function (card) {
      if (card.dataset.sourceDecorated === 'true') return;
      var info = sourceById[card.dataset.notificationId];
      if (!info || !info.label) return;
      var meta = card.querySelector('.mg-notification-meta');
      if (!meta) return;
      var proof = document.createElement('span');
      proof.className = 'mg-delivery-source-chip';
      proof.dataset.sourceSystem = info.system || '';
      proof.innerHTML = esc(info.label) + (info.channel ? ' · ' + esc(clean(info.channel)) : '');
      meta.prepend(proof);
      if (info.thread || info.message) {
        var ids = document.createElement('span');
        ids.className = 'mg-delivery-proof-ids';
        ids.textContent = [info.thread ? 'Thread ' + info.thread : '', info.message ? 'Message ' + info.message : ''].filter(Boolean).join(' · ');
        meta.appendChild(ids);
      }
      var action = card.querySelector('[data-notification-open]');
      if (action && info.action) action.setAttribute('href', info.action);
      card.dataset.sourceDecorated = 'true';
    });
  }
  new MutationObserver(decorate).observe(list, { childList: true, subtree: true });
  loadSourceIndex();
});

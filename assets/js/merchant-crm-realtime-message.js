document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var shell = document.querySelector('[data-merchant-crm-shell]');
  if (!shell || !window.Microgifter) return;

  if (!document.querySelector('script[data-crm-customer-refund-script]')) {
    var refundScript = document.createElement('script');
    refundScript.src = '/assets/js/merchant-crm-customer-refund.js';
    refundScript.defer = true;
    refundScript.setAttribute('data-crm-customer-refund-script', '1');
    document.head.appendChild(refundScript);
  }

  var pendingBody = '';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function list(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function first(selector, root) {
    return (root || document).querySelector(selector);
  }

  function timeText(value) {
    var timestamp = value ? Date.parse(String(value).replace(' ', 'T')) : Date.now();
    return Number.isFinite(timestamp) ? new Date(timestamp).toLocaleString() : 'Now';
  }

  function rememberComposerBody() {
    var modal = first('[data-crm-message-modal]');
    if (!modal || modal.hidden) return;
    var textarea = first('[data-crm-message-body]', modal);
    pendingBody = textarea ? String(textarea.value || '').trim() : '';
  }

  function crmBubble(message) {
    return '<article class="mg-message-bubble is-mine" data-crm-live-message="' + esc(message.id || '') + '"><strong>' + esc(message.sender_name || 'Merchant') + '</strong><p>' + esc(message.body || '') + '</p><small>' + esc(timeText(message.created_at)) + '</small></article>';
  }

  function profileBubble(message) {
    return '<div class="mg-cp-message is-blue" data-crm-live-message="' + esc(message.id || '') + '"><strong>' + esc(message.sender_name || 'Merchant') + '</strong><p>' + esc(message.body || '') + '</p><small>' + esc(timeText(message.created_at)) + '</small></div>';
  }

  function appendOptimistic(message) {
    var didAppend = false;

    list('.mg-message-stream').forEach(function (stream) {
      if (message.id && first('[data-crm-live-message="' + esc(message.id) + '"]', stream)) return;
      stream.insertAdjacentHTML('beforeend', crmBubble(message));
      stream.scrollTop = stream.scrollHeight;
      didAppend = true;
    });

    list('[data-cp-messages], [data-cp-messages-full]').forEach(function (box) {
      if (message.id && first('[data-crm-live-message="' + esc(message.id) + '"]', box)) return;
      if (/loading messages|no messages/i.test(box.textContent || '')) box.innerHTML = '';
      box.insertAdjacentHTML('afterbegin', profileBubble(message));
      didAppend = true;
    });

    list('[data-customer-message-list], [data-customer-messages-list], [data-crm-thread-messages], [data-customer-thread-messages], .mg-customer-message-list, .mg-thread-messages').forEach(function (box) {
      if (message.id && first('[data-crm-live-message="' + esc(message.id) + '"]', box)) return;
      box.insertAdjacentHTML('beforeend', crmBubble(message));
      box.scrollTop = box.scrollHeight;
      didAppend = true;
    });

    return didAppend;
  }

  function renderThread(thread) {
    var messages = thread && Array.isArray(thread.messages) ? thread.messages : [];
    if (!messages.length) return;
    var html = messages.map(function (message) {
      var mine = !!message.mine;
      return '<article class="mg-message-bubble ' + (mine ? 'is-mine' : '') + '" data-crm-live-message="' + esc(message.id || '') + '"><strong>' + esc(message.sender_name || (mine ? 'Merchant' : 'Customer')) + '</strong><p>' + esc(message.body || '') + '</p><small>' + esc(timeText(message.created_at)) + '</small></article>';
    }).join('');

    list('.mg-message-stream').forEach(function (stream) {
      stream.innerHTML = html;
      stream.scrollTop = stream.scrollHeight;
    });
  }

  async function refreshThread(threadId) {
    if (!threadId || typeof Microgifter.get !== 'function') return;
    try {
      var response = await Microgifter.get('/api/merchant/crm-messages.php?thread=' + encodeURIComponent(threadId));
      var data = response.data || response;
      if (data && data.thread) renderThread(data.thread);
    } catch (error) {}
  }

  document.addEventListener('click', function (event) {
    if (event.target && event.target.closest && event.target.closest('[data-crm-message-submit]')) rememberComposerBody();
  }, true);

  document.addEventListener('submit', function (event) {
    if (event.target && event.target.matches && event.target.matches('[data-crm-message-form]')) rememberComposerBody();
  }, true);

  document.addEventListener('mg:crm-messages:refresh', function (event) {
    var detail = event.detail || {};
    var threadId = detail.thread_id || '';
    var messageId = detail.message_id || ('sent-' + Date.now());
    if (pendingBody) {
      appendOptimistic({ id: messageId, body: pendingBody, sender_name: 'Merchant', mine: true, created_at: new Date().toISOString() });
      pendingBody = '';
    }
    window.setTimeout(function () { refreshThread(threadId); }, 250);
  });
});

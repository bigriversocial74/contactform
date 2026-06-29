window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !MG) return;

  var activeConversationId = '';
  var activeClusterKey = '';

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function payload(response) { return response && response.data ? response.data : response; }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function apiPost(path, body) {
    if (MG.post) return MG.post(path, body || {});
    return fetch(path, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': MG.getCsrfToken ? MG.getCsrfToken() : '' }, body: JSON.stringify(body || {}) }).then(function (r) { return r.json(); });
  }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }

  function drawer() {
    var node = qs('[data-world-drawer]') || document.querySelector('[data-world-drawer]');
    if (node && node.parentElement !== document.body) document.body.appendChild(node);
    return node;
  }

  function collectClusterNodes(key) {
    return qsa('[data-world-node]').filter(function (node) { return node.dataset.worldConversationKey === key; }).map(function (node) {
      return {
        type: node.dataset.worldType || 'signal',
        location: node.dataset.worldLocationKey || '',
        title: (node.querySelector('.mg-world-node-copy strong') || {}).textContent || 'World signal',
        subtitle: (node.querySelector('.mg-world-node-copy span') || {}).textContent || ''
      };
    });
  }

  function titleFor(nodes) {
    var avatars = nodes.filter(function (node) { return node.type === 'avatar'; }).length;
    var rewards = nodes.filter(function (node) { return node.type === 'reward'; }).length;
    var claims = nodes.filter(function (node) { return node.type === 'claim'; }).length;
    if (claims) return claims + ' claim conversation';
    if (rewards) return rewards + ' reward conversation';
    if (avatars) return avatars + ' avatar conversation';
    return 'World Canvas conversation';
  }

  function timeText(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('T') === -1 ? 'Z' : ''));
    if (Number.isNaN(parsed.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(parsed);
  }

  function dispatchConversation(conversation, clusterKey, locationKey) {
    window.MicrogifterWorldConversation = { conversation: conversation, cluster_key: clusterKey || '', location_key: locationKey || '' };
    try { document.dispatchEvent(new CustomEvent('mg:world-conversation-opened', { detail: window.MicrogifterWorldConversation })); } catch (error) {}
  }

  function renderConversation(conversation) {
    var d = drawer();
    if (!d) return;
    activeConversationId = conversation.id || '';
    d.dataset.worldConversationId = activeConversationId;
    d.dataset.worldConversationClusterKey = activeClusterKey || conversation.cluster_key || '';
    d.dataset.worldConversationLocationKey = conversation.location_key || '';
    var title = d.querySelector('[data-world-drawer-title]');
    var subtitle = d.querySelector('[data-world-drawer-subtitle]');
    var type = d.querySelector('[data-world-drawer-type]');
    var body = d.querySelector('[data-world-drawer-body]');
    if (type) type.textContent = 'World conversation';
    if (title) title.textContent = conversation.title || 'World Canvas conversation';
    if (subtitle) subtitle.textContent = (conversation.participant_count || 0) + ' participants · ' + (conversation.message_count || 0) + ' messages';
    var member = conversation.member || {};
    var messages = Array.isArray(conversation.messages) ? conversation.messages : [];
    if (body) {
      body.innerHTML = '<section class="mg-world-conversation-summary"><strong>Avatar conversation space</strong><p>This is a temporary World Canvas chat generated from avatar proximity, shared location, campaign, reward, or affinity signals. Private CRM remains inside Merchant Store Canvas.</p><span>Posting as: <b>' + escapeHtml(member.display_label || 'Anonymous avatar') + '</b></span></section>' +
        '<section class="mg-world-conversation-reward-slot" data-world-reward-drop-slot></section>' +
        '<section class="mg-world-conversation-messages" data-world-conversation-messages>' + (messages.length ? messages.map(renderMessage).join('') : '<p class="mg-world-conversation-empty">No messages yet. Start the conversation.</p>') + '</section>' +
        '<form class="mg-world-conversation-form" data-world-conversation-form><label>Message this cluster<textarea data-world-conversation-input maxlength="700" placeholder="Share a quick note, question, or local reward idea..."></textarea></label><button type="submit">Send Message</button></form>';
    }
    d.classList.add('is-open');
    d.setAttribute('aria-hidden', 'false');
    var list = d.querySelector('[data-world-conversation-messages]');
    if (list) list.scrollTop = list.scrollHeight;
    dispatchConversation(conversation, d.dataset.worldConversationClusterKey || '', d.dataset.worldConversationLocationKey || '');
  }

  function renderMessage(message) {
    return '<article class="mg-world-conversation-message is-' + escapeHtml(message.identity_mode || 'anonymous') + '"><header><strong>' + escapeHtml(message.sender_label || 'Anonymous avatar') + '</strong><time>' + escapeHtml(timeText(message.created_at)) + '</time></header><p>' + escapeHtml(message.message_body || '') + '</p></article>';
  }

  async function openConversation(clusterKey) {
    activeClusterKey = clusterKey;
    var nodes = collectClusterNodes(clusterKey);
    var locationKey = nodes.reduce(function (picked, node) { return picked || node.location || ''; }, '');
    var d = drawer();
    if (d) {
      d.dataset.worldConversationClusterKey = clusterKey || '';
      d.dataset.worldConversationLocationKey = locationKey || '';
      d.classList.add('is-open');
      d.setAttribute('aria-hidden', 'false');
      var title = d.querySelector('[data-world-drawer-title]');
      var subtitle = d.querySelector('[data-world-drawer-subtitle]');
      var type = d.querySelector('[data-world-drawer-type]');
      var body = d.querySelector('[data-world-drawer-body]');
      if (type) type.textContent = 'World conversation';
      if (title) title.textContent = 'Opening conversation...';
      if (subtitle) subtitle.textContent = 'Resolving the avatar cluster.';
      if (body) body.innerHTML = '<div class="mg-world-drawer-empty"><strong>Opening cluster chat</strong><p>Creating or loading the temporary avatar conversation.</p></div>';
    }
    try {
      var data = payload(await apiPost('/api/world-canvas/conversations.php', {
        cluster_key: clusterKey,
        title: titleFor(nodes),
        conversation_type: 'cluster',
        location_key: locationKey,
        node_count: nodes.length
      }));
      renderConversation(data.conversation || data);
    } catch (error) {
      toast(error.message || 'Unable to open World Canvas conversation.', 'error');
    }
  }

  async function sendMessage(form) {
    var input = form.querySelector('[data-world-conversation-input]');
    var message = input ? input.value.trim() : '';
    if (!message || !activeConversationId) return;
    var button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    try {
      var data = payload(await apiPost('/api/world-canvas/conversation-message.php', { conversation_id: activeConversationId, message_body: message }));
      if (input) input.value = '';
      renderConversation(data.conversation || data);
    } catch (error) {
      toast(error.message || 'Unable to send World Canvas message.', 'error');
    } finally {
      if (button) button.disabled = false;
    }
  }

  document.addEventListener('click', function (event) {
    var cluster = event.target.closest('[data-world-cluster-key]');
    if (!cluster || !root.contains(cluster)) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    qsa('[data-world-node]').forEach(function (node) {
      node.classList.toggle('is-active', node.dataset.worldConversationKey === cluster.dataset.worldClusterKey);
    });
    openConversation(cluster.dataset.worldClusterKey || '');
  }, true);

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-world-conversation-form]');
    if (!form) return;
    event.preventDefault();
    sendMessage(form);
  });
})(window, document);
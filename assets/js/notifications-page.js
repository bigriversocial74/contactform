document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-notifications-page]');
  if (!app || !window.Microgifter) return;

  var list = app.querySelector('[data-notification-list]');
  var search = app.querySelector('[data-notification-search]');
  var category = app.querySelector('[data-notification-category]');
  var markAll = app.querySelector('[data-mark-all-read]');
  var items = [];

  function classify(item) {
    if (['claim_locked','claim_expired','delivery_failed','distribution_failed','system_alert'].includes(item.type)) return 'operational';
    if (item.type === 'message') return 'message';
    return 'activity';
  }

  function typeLabel(type) {
    return ({
      gift: 'Gift',
      message: 'Message',
      social: 'Social',
      claim: 'Claim',
      delivery: 'Delivery',
      distribution: 'Distribution',
      campaign: 'Campaign',
      merchant: 'Merchant',
      security: 'Security',
      system: 'System'
    })[type] || 'Activity';
  }

  function safeUrl(value) {
    var raw = String(value || '').trim();
    if (!raw || !raw.startsWith('/') || raw.startsWith('//') || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    return raw;
  }

  function safeImage(value) {
    var raw = String(value || '').trim();
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return null;
    try {
      var parsed = new URL(raw, window.location.origin);
      if (!['http:','https:'].includes(parsed.protocol)) return null;
      return raw.startsWith('/') && !raw.startsWith('//') ? parsed.pathname + parsed.search : parsed.href;
    } catch (error) {
      return null;
    }
  }

  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function actorVisual(item) {
    var wrap = element('div', 'mg-notification-actor');
    var actor = item.actor || null;
    var imageUrl = actor ? safeImage(actor.avatar_url) : null;
    if (imageUrl) {
      var image = document.createElement('img');
      image.src = imageUrl;
      image.alt = '';
      image.loading = 'lazy';
      wrap.appendChild(image);
    } else {
      var label = actor && actor.name ? actor.name : typeLabel(item.type);
      wrap.textContent = String(label).trim().charAt(0).toUpperCase() || 'M';
    }
    return wrap;
  }

  function formatTime(value) {
    if (!value) return '';
    var date = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  }

  function notificationCard(item) {
    var unread = !(item.read_at || item.read);
    var card = element('article', 'mg-notification-card' + (unread ? ' is-unread' : ''));
    card.dataset.notificationId = item.public_id || item.id;
    card.appendChild(actorVisual(item));

    var content = element('div', 'mg-notification-copy');
    var heading = element('div', 'mg-notification-heading');
    heading.appendChild(element('h3', '', item.title || 'Notification'));
    if (Number(item.occurrence_count || 1) > 1) {
      heading.appendChild(element('span', 'mg-notification-count', String(item.occurrence_count) + ' updates'));
    }
    content.appendChild(heading);
    if (item.body) content.appendChild(element('p', '', item.body));

    var meta = element('div', 'mg-notification-meta');
    meta.appendChild(element('span', '', typeLabel(item.type)));
    if (item.actor && item.actor.name) meta.appendChild(element('span', '', item.actor.name));
    if (item.created_at) meta.appendChild(element('time', '', formatTime(item.created_at)));
    content.appendChild(meta);
    card.appendChild(content);

    var actions = element('div', 'mg-notification-actions');
    var actionUrl = safeUrl(item.action_url);
    if (actionUrl) {
      var open = element('a', 'mg-btn mg-btn-soft', 'Open');
      open.href = actionUrl;
      open.dataset.notificationOpen = '';
      actions.appendChild(open);
    }
    if (unread) {
      var read = element('button', 'mg-btn mg-btn-ghost', 'Mark read');
      read.type = 'button';
      read.dataset.markRead = '';
      actions.appendChild(read);
    }
    card.appendChild(actions);
    return card;
  }

  function render() {
    var query = String(search.value || '').trim().toLowerCase();
    var selectedCategory = category.value;
    var filtered = items.filter(function (item) {
      if (selectedCategory !== 'all' && classify(item) !== selectedCategory) return false;
      var haystack = [item.title, item.body, item.type, item.actor && item.actor.name].filter(Boolean).join(' ').toLowerCase();
      return !query || haystack.includes(query);
    });

    list.replaceChildren();
    if (!filtered.length) {
      var empty = element('div', 'mg-empty-state');
      empty.appendChild(element('strong', '', 'No notifications found.'));
      list.appendChild(empty);
      return;
    }
    filtered.forEach(function (item) { list.appendChild(notificationCard(item)); });
  }

  async function load() {
    var response = await Microgifter.get('/api/notifications/index.php?limit=100');
    var data = response.data || response;
    items = Array.isArray(data.notifications) ? data.notifications : [];
    render();
    if (Microgifter.setNotificationCount) Microgifter.setNotificationCount(data.unread_count || 0);
  }

  async function markRead(id) {
    var response = await Microgifter.post('/api/notifications/read.php', { id: id });
    var data = response.data || response;
    var item = items.find(function (entry) { return String(entry.public_id || entry.id) === String(id); });
    if (item) {
      item.read = true;
      item.read_at = new Date().toISOString();
    }
    render();
    if (Microgifter.setNotificationCount) Microgifter.setNotificationCount(data.unread_count || 0);
  }

  search.addEventListener('input', render);
  category.addEventListener('change', render);
  markAll.addEventListener('click', async function () {
    markAll.disabled = true;
    try {
      await Microgifter.post('/api/notifications/read.php', { id: 'all' });
      items.forEach(function (item) { item.read = true; item.read_at = item.read_at || new Date().toISOString(); });
      render();
      if (Microgifter.setNotificationCount) Microgifter.setNotificationCount(0);
    } finally {
      markAll.disabled = false;
    }
  });

  list.addEventListener('click', async function (event) {
    var card = event.target.closest('[data-notification-id]');
    if (!card) return;
    var id = card.dataset.notificationId;
    var readButton = event.target.closest('[data-mark-read]');
    if (readButton) {
      event.preventDefault();
      readButton.disabled = true;
      try { await markRead(id); } finally { readButton.disabled = false; }
      return;
    }
    var openLink = event.target.closest('[data-notification-open]');
    if (openLink && card.classList.contains('is-unread')) {
      event.preventDefault();
      var href = openLink.getAttribute('href');
      try { await markRead(id); } finally { window.location.assign(href); }
    }
  });

  load().catch(function (error) {
    list.replaceChildren();
    var empty = element('div', 'mg-empty-state');
    empty.append(element('strong', '', 'Unable to load notifications.'), element('p', '', error.message || 'Try again shortly.'));
    list.appendChild(empty);
  });
});

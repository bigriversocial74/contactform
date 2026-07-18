document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app || app.dataset.voucherDrawerV1 === 'true') return;
  app.dataset.voucherDrawerV1 = 'true';

  var list = app.querySelector('[data-gift-list]');
  var drawer = app.querySelector('[data-gift-drawer]');
  var drawerContent = app.querySelector('[data-gift-drawer-content]');
  var drawerTitle = app.querySelector('[data-gift-drawer-title]');
  var drawerBackdrop = app.querySelector('[data-gift-drawer-backdrop]');
  var contracts = new Map();

  if (!list || !drawer || !drawerContent) return;

  function object(value) {
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  }

  function text(value, fallback) {
    var clean = String(value == null ? '' : value).trim();
    return clean || String(fallback == null ? '' : fallback);
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function safeUrl(value) {
    var raw = text(value);
    if (!raw || /[\u0000-\u001f\u007f]/.test(raw)) return '';
    try {
      var parsed = new URL(raw, window.location.origin);
      if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password) return '';
      if (raw.charAt(0) === '/') return raw.indexOf('//') === 0 || parsed.origin !== window.location.origin ? '' : parsed.pathname + parsed.search + parsed.hash;
      return parsed.href;
    } catch (error) {
      return '';
    }
  }

  function payload(response) {
    return response && response.data && typeof response.data === 'object' ? response.data : response;
  }

  async function apiGet(path) {
    if (window.Microgifter && typeof window.Microgifter.api === 'function') {
      return payload(await window.Microgifter.api(path, { method: 'GET' }));
    }
    var response = await fetch(path, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    var data = {};
    try { data = await response.json(); } catch (error) {}
    if (!response.ok || data.ok === false) throw new Error(data.message || data.error || 'Unable to load voucher details.');
    return payload(data);
  }

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: text(currency, 'USD').toUpperCase()
      }).format(Math.max(0, Number(cents || 0)) / 100);
    } catch (error) {
      return text(currency, 'USD').toUpperCase() + ' ' + (Math.max(0, Number(cents || 0)) / 100).toFixed(2);
    }
  }

  function dateTime(value, fallback) {
    if (!value) return fallback || 'Not recorded';
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return text(value, fallback || 'Not recorded');
    return date.toLocaleString(undefined, {
      month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
    });
  }

  function dateOnly(value, fallback) {
    if (!value) return fallback || 'No expiration';
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return text(value, fallback || 'No expiration');
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function parts(contract) {
    var gift = object(contract && contract.gift);
    return {
      gift: gift,
      snapshot: object(gift.snapshot),
      presentation: object(contract && contract.presentation),
      linked: object(contract && contract.linked_resource),
      source: object(contract && contract.source),
      participants: object(contract && contract.participants),
      merchant: object(contract && contract.merchant),
      location: object(contract && contract.location),
      redemption: object(contract && contract.redemption),
      activity: object(contract && contract.activity),
      media: object(contract && contract.media),
      flags: object(contract && contract.flags)
    };
  }

  function statusText(contract) {
    var info = parts(contract).gift;
    return text(info.state, text(info.status, app.dataset.initialFolder || 'inbox')).replace(/[_-]+/g, ' ');
  }

  function statusClass(contract) {
    return 'is-status-' + statusText(contract).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }

  function folderContext(contract) {
    var info = parts(contract);
    var folder = text(contract.folder, app.dataset.initialFolder || 'inbox');
    var recipient = text(object(info.participants.recipient).name, 'recipient');
    var location = text(info.location.name, text(info.redemption.location_name, 'participating merchant'));
    if (folder === 'sent') return 'Sent to ' + recipient;
    if (folder === 'claimed') {
      return info.redemption.redeemed_at ? 'Redeemed at ' + location : 'Claimed and ready for redemption';
    }
    return 'Available in your Inbox';
  }

  function storeContracts(items) {
    (Array.isArray(items) ? items : []).forEach(function (contract) {
      var id = text(contract && contract.action_item_id);
      if (id) contracts.set(id, contract);
    });
  }

  function contractForRow(row) {
    return row ? contracts.get(text(row.dataset.giftId)) || null : null;
  }

  function enhanceEmptyState() {
    var empty = list.querySelector('.mg-gift-empty-list');
    if (!empty) return;
    var folder = app.dataset.initialFolder || 'inbox';
    var strong = empty.querySelector('strong');
    var paragraph = empty.querySelector('p');
    var copy = {
      inbox: ['No gifts in your Inbox', 'New gifts, rewards, and received vouchers will appear here.'],
      sent: ['No sent gifts', 'Gifts you send or regift will appear here with delivery and Follow Up status.'],
      claimed: ['No claimed gifts', 'Claimed, redeemed, and expired vouchers will appear here.']
    }[folder];
    if (copy && strong && paragraph && !empty.classList.contains('is-error')) {
      strong.textContent = copy[0];
      paragraph.textContent = copy[1];
    }
  }

  function enhanceRows() {
    var folder = app.dataset.initialFolder || 'inbox';
    list.querySelectorAll('.mg-gift-row[data-gift-id]').forEach(function (row) {
      var contract = contractForRow(row);
      if (!contract) return;
      var info = parts(contract);
      row.classList.add('mg-gift-row-polished-v1', 'is-folder-' + folder, statusClass(contract));

      var titleWrap = row.querySelector('.mg-gift-card-v3-title');
      if (titleWrap && !titleWrap.querySelector('[data-gift-value-chip]')) {
        var value = document.createElement('span');
        value.className = 'mg-gift-value-chip';
        value.dataset.giftValueChip = 'true';
        value.textContent = money(info.snapshot.value_cents, info.snapshot.currency);
        titleWrap.appendChild(value);
      }

      var copy = row.querySelector('.mg-gift-card-v3-copy');
      var meta = row.querySelector('.mg-gift-card-v3-meta');
      if (copy && meta && !copy.querySelector('[data-gift-folder-context]')) {
        var context = document.createElement('div');
        context.className = 'mg-gift-folder-context';
        context.dataset.giftFolderContext = 'true';
        context.textContent = folderContext(contract);
        copy.insertBefore(context, meta);
      }

      row.querySelectorAll('[data-gift-action]').forEach(function (button) {
        var action = text(button.dataset.giftAction);
        button.classList.add('is-action-' + action);
        button.setAttribute('aria-label', text(button.textContent) + ': ' + text(info.snapshot.title, 'Microgift'));
      });
    });
    enhanceEmptyState();
  }

  function detailCard(label, value, className) {
    return '<div class="mg-voucher-fact ' + esc(className || '') + '"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

  function timelineMarkup(events) {
    events = Array.isArray(events) ? events : [];
    if (!events.length) return '<p class="mg-voucher-muted">No ownership events have been recorded yet.</p>';
    return '<ol class="mg-voucher-timeline">' + events.map(function (event) {
      var actor = text(event.actor);
      var recipient = text(event.recipient);
      var route = actor && recipient ? actor + ' → ' + recipient : actor || recipient;
      return '<li class="is-' + esc(text(event.type, 'event').replace(/[^a-z0-9-]/gi, '-').toLowerCase()) + '">' +
        '<span class="mg-voucher-timeline-dot" aria-hidden="true"></span><div><strong>' + esc(text(event.label, 'Ownership updated')) + '</strong>' +
        (route ? '<span>' + esc(route) + '</span>' : '') +
        (event.detail ? '<span>' + esc(event.detail) + '</span>' : '') +
        '<time>' + esc(dateTime(event.occurred_at)) + '</time></div></li>';
    }).join('') + '</ol>';
  }

  function mediaMarkup(contract) {
    var info = parts(contract);
    var posts = Array.isArray(info.media.posts) ? info.media.posts : [];
    if (!posts.length) return '';
    return '<section class="mg-voucher-section"><div class="mg-voucher-section-heading"><span>Included content</span><strong>' + posts.length + ' item' + (posts.length === 1 ? '' : 's') + '</strong></div><div class="mg-voucher-content-grid">' + posts.map(function (post) {
      post = object(post);
      var type = text(post.type, 'content').toLowerCase();
      var url = safeUrl(post.url);
      var media = '';
      if (url && ['cover', 'image'].includes(type)) media = '<img src="' + esc(url) + '" alt="" loading="lazy">';
      else if (url && type === 'audio') media = '<audio controls preload="metadata" src="' + esc(url) + '"></audio>';
      else if (url && type === 'video') media = '<video controls preload="metadata" src="' + esc(url) + '"></video>';
      else if (url) media = '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer">Open content</a>';
      return '<article class="mg-voucher-content-item is-' + esc(type) + '">' + media + '<div><span>' + esc(type.replace(/[_-]+/g, ' ')) + '</span><strong>' + esc(text(post.title, info.snapshot.title)) + '</strong><p>' + esc(text(post.body)).replace(/\n/g, '<br>') + '</p></div></article>';
    }).join('') + '</div></section>';
  }

  function qrMarkup(tokenData, tokenError, detail, contract) {
    var info = parts(contract);
    var folder = text(contract.folder, app.dataset.initialFolder || 'inbox');
    if (folder === 'sent') {
      return '<section class="mg-voucher-section mg-voucher-qr-section is-unavailable"><div class="mg-voucher-section-heading"><span>Merchant scan</span><strong>Current holder only</strong></div><p>The recipient will receive the protected QR and claim code in their own Inbox.</p></section>';
    }
    if (tokenData && tokenData.qr_image_url) {
      var code = text(tokenData.scan_payload, tokenData.token_id);
      return '<section class="mg-voucher-section mg-voucher-qr-section"><div class="mg-voucher-section-heading"><span>Merchant scan</span><strong>Protected QR</strong></div><div class="mg-voucher-qr-grid"><div class="mg-voucher-qr-image"><img src="' + esc(safeUrl(tokenData.qr_image_url)) + '" alt="Voucher QR code"></div><div class="mg-voucher-qr-copy"><span>Scan / claim code</span><code data-voucher-claim-code>' + esc(code) + '</code><button type="button" class="mg-btn mg-btn-soft" data-copy-voucher-code>Copy code</button><small>Refreshes after ' + esc(dateTime(tokenData.expires_at)) + '.</small></div></div></section>';
    }
    var redemption = object(detail && detail.redemption);
    var message = redemption.redeemed_at ? 'This voucher was redeemed on ' + dateTime(redemption.redeemed_at) + '.' : text(tokenError, 'A protected QR is not available for the current voucher state.');
    return '<section class="mg-voucher-section mg-voucher-qr-section is-unavailable"><div class="mg-voucher-section-heading"><span>Merchant scan</span><strong>' + esc(text(redemption.status, statusText(contract)).replace(/[_-]+/g, ' ')) + '</strong></div><p>' + esc(message) + '</p></section>';
  }

  function renderDrawer(contract, detail, tokenData, tokenError) {
    var info = parts(contract);
    var image = safeUrl(info.presentation.image_url);
    var productUrl = safeUrl(info.linked.url);
    var redemption = object(detail && detail.redemption);
    var title = text(info.snapshot.title, 'Microgift');
    var location = text(redemption.location_name, text(info.location.name, 'Participating locations'));
    var sender = text(object(info.participants.sender).name, text(info.merchant.name, 'Microgifter'));
    var recipient = text(object(info.participants.recipient).name, 'Current recipient');
    var terms = text(detail && detail.terms, 'Merchant terms apply.');
    var expirationPolicy = text(detail && detail.expiration_policy, 'No expiration policy is listed.');

    drawerContent.innerHTML = '<div class="mg-voucher-drawer-v1" data-voucher-drawer-v1>' +
      '<section class="mg-voucher-hero">' +
      (image ? '<div class="mg-voucher-hero-image"><img src="' + esc(image) + '" alt="" loading="eager"></div>' : '<div class="mg-voucher-hero-image is-placeholder"><span>' + esc(title.charAt(0).toUpperCase()) + '</span></div>') +
      '<div class="mg-voucher-hero-copy"><div class="mg-voucher-hero-top"><span class="mg-voucher-status ' + esc(statusClass(contract)) + '">' + esc(statusText(contract)) + '</span><span class="mg-voucher-value">' + esc(money(info.snapshot.value_cents, info.snapshot.currency)) + '</span></div><span class="mg-voucher-merchant">' + esc(text(info.merchant.name, sender)) + '</span><h2>' + esc(title) + '</h2><p>' + esc(text(info.snapshot.description, 'Gift ready to open.')) + '</p>' +
      (productUrl ? '<a class="mg-btn mg-btn-soft" href="' + esc(productUrl) + '">View current product</a>' : '') + '</div></section>' +
      '<section class="mg-voucher-facts">' +
      detailCard('Recipient', recipient, 'is-recipient') +
      detailCard('Sender', sender, 'is-sender') +
      detailCard('Location', location, 'is-location') +
      detailCard('Expires', dateOnly(info.snapshot.expires_at), 'is-expiration') +
      detailCard('Voucher ID', text(info.gift.id, contract.action_item_id), 'is-id') +
      detailCard('Redemption', text(redemption.status, statusText(contract)).replace(/[_-]+/g, ' '), 'is-redemption') +
      '</section>' +
      qrMarkup(tokenData, tokenError, detail, contract) +
      '<section class="mg-voucher-section"><div class="mg-voucher-section-heading"><span>Terms and expiration</span><strong>Voucher rules</strong></div><div class="mg-voucher-terms"><div><strong>Terms</strong><p>' + esc(terms) + '</p></div><div><strong>Expiration policy</strong><p>' + esc(expirationPolicy) + '</p></div></div></section>' +
      '<section class="mg-voucher-section"><div class="mg-voucher-section-heading"><span>Ownership timeline</span><strong>' + esc(String(Array.isArray(detail && detail.timeline) ? detail.timeline.length : 0)) + ' events</strong></div>' + timelineMarkup(detail && detail.timeline) + '</section>' +
      mediaMarkup(contract) + '</div>';
  }

  function openDrawerShell(contract) {
    if (drawerTitle) drawerTitle.textContent = text(parts(contract).snapshot.title, 'Gift details');
    drawer.classList.add('is-open', 'mg-voucher-drawer-shell-v1');
    drawer.setAttribute('aria-hidden', 'false');
    if (drawerBackdrop) drawerBackdrop.hidden = false;
    document.body.classList.add('mg-modal-lock');
    drawerContent.innerHTML = '<div class="mg-voucher-drawer-loading"><span></span><strong>Loading voucher</strong><p>Reading protected product, ownership, and redemption details.</p></div>';
    drawerContent.scrollTop = 0;
  }

  async function openEnhancedDrawer(contract) {
    openDrawerShell(contract);
    var id = text(contract.action_item_id);
    var latestContract = contract;
    var detail = { timeline: [], redemption: {} };
    var detailError = '';

    try {
      var latest = await apiGet('/api/account/action-center-detail.php?id=' + encodeURIComponent(id));
      if (latest && latest.item) {
        latestContract = latest.item;
        contracts.set(id, latestContract);
      }
    } catch (error) {
      detailError = error && error.message ? error.message : '';
    }

    try {
      detail = await apiGet('/api/account/action-center-voucher-detail.php?id=' + encodeURIComponent(id));
    } catch (error) {
      detailError = error && error.message ? error.message : detailError;
    }

    var tokenData = null;
    var tokenError = '';
    if (detail && detail.claim_qr_supported && !object(parts(latestContract).flags).demo_preview && !object(parts(latestContract).flags).system_demo) {
      try {
        tokenData = await apiGet('/api/account/action-center-voucher-token.php?action_item_id=' + encodeURIComponent(id));
      } catch (error) {
        tokenError = error && error.message ? error.message : 'Protected QR is unavailable.';
      }
    } else if (detailError) {
      tokenError = detailError;
    }

    renderDrawer(latestContract, detail || {}, tokenData, tokenError);
  }

  app.addEventListener('mg:action-center:loaded', function (event) {
    var detail = object(event.detail);
    storeContracts(detail.contracts);
    window.setTimeout(enhanceRows, 0);
  });

  document.addEventListener('mg:action-center:rendered', function () {
    window.setTimeout(enhanceRows, 0);
  });

  list.addEventListener('click', function (event) {
    var button = event.target.closest('[data-gift-action="load"]');
    if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true') return;
    var row = button.closest('[data-gift-id]');
    var contract = contractForRow(row);
    if (!contract) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    list.querySelectorAll('.mg-gift-row.is-active').forEach(function (active) { active.classList.remove('is-active'); });
    row.classList.add('is-active');
    openEnhancedDrawer(contract);
  }, true);

  drawerContent.addEventListener('click', async function (event) {
    var copy = event.target.closest('[data-copy-voucher-code]');
    if (!copy) return;
    var codeNode = drawerContent.querySelector('[data-voucher-claim-code]');
    var value = codeNode ? codeNode.textContent : '';
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      copy.textContent = 'Copied';
      window.setTimeout(function () { copy.textContent = 'Copy code'; }, 1600);
    } catch (error) {
      var textarea = document.createElement('textarea');
      textarea.value = value;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      textarea.remove();
      copy.textContent = 'Copied';
    }
  });

  window.setTimeout(enhanceRows, 0);
});

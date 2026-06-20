document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app) return;

  var list = app.querySelector('[data-gift-list]');
  var drawer = app.querySelector('[data-gift-drawer]');
  var drawerContent = app.querySelector('[data-gift-drawer-content]');
  var drawerTitle = app.querySelector('[data-gift-drawer-title]');
  var backdrop = app.querySelector('[data-gift-drawer-backdrop]');
  var modal = app.querySelector('[data-action-modal]');
  var modalBackdrop = app.querySelector('[data-action-modal-backdrop]');
  var modalBody = app.querySelector('[data-action-modal-body]');
  var modalTitle = app.querySelector('[data-action-modal-title]');
  var modalEyebrow = app.querySelector('[data-action-modal-eyebrow]');
  var folderLabel = app.querySelector('[data-gift-folder-label]');
  var folderSubtitle = app.querySelector('[data-gift-folder-subtitle]');
  var folderDescription = app.querySelector('[data-gift-folder-description]');

  var folderCopy = {
    inbox: {
      title: 'Inbox',
      subtitle: 'Owned Microgifts ready to open, regift, claim, or redeem.'
    },
    sent: {
      title: 'Sent',
      subtitle: 'Transferred Microgifts with private Follow Up messaging for the current recipient.'
    },
    claimed: {
      title: 'Claimed',
      subtitle: 'Claimed and redeemed Microgifts with messaging and tip actions.'
    }
  };

  var state = {
    folder: app.dataset.initialFolder || 'inbox',
    folders: { inbox: [], sent: [], claimed: [] },
    counts: { inbox: 0, sent: 0, claimed: 0 },
    unread: { inbox: 0, sent: 0, claimed: 0 },
    selected: null,
    loading: false
  };

  var demoEnabled = app.dataset.demoEnabled === 'true';
  var demos = {
    inbox: [
      {
        action_item_id: 'demo-coffee-001', folder: 'inbox', state: 'redeemable',
        instance_id: 'MG-DEMO-001', template_name: 'Coffee for two',
        merchant_name: 'Local Coffee House', location_name: 'Phoenix, AZ',
        face_value_cents: 2500, currency: 'USD', expires_at: 'No expiration',
        sender_name: 'Local Coffee House', recipient_name: 'Super Admin',
        product_type: 'Prepaid gift', received_at: '2026-06-08T09:05:00Z',
        claim_code: '123456', message: 'A local coffee experience with a protected voucher underneath.',
        is_demo: true,
        posts: [
          { type: 'cover', title: 'Coffee for two', body: 'A small local gift, already waiting for you.', meta: 'Envelope cover' },
          { type: 'media', title: 'Meet the roaster', body: 'Demo video module placeholder. Rich media rides with the tracked envelope.', meta: 'Video content' },
          { type: 'message', title: 'A note from Microgifter', body: 'Enjoy coffee for two at Local Coffee House.', meta: 'Gift message' },
          { type: 'offer', title: 'Your $25.00 voucher', body: 'The voucher remains the protected value-bearing component under the content stack.', meta: 'Redeemable voucher' }
        ]
      },
      {
        action_item_id: 'demo-music-002', folder: 'inbox', state: 'received',
        instance_id: 'MG-DEMO-002', template_name: 'Dinner and a playlist',
        merchant_name: 'Roosevelt Row Kitchen', location_name: 'Downtown Phoenix',
        face_value_cents: 5000, currency: 'USD', expires_at: 'Dec 31, 2026',
        sender_name: 'Alex Morgan', recipient_name: 'Super Admin',
        product_type: 'Gift experience', received_at: '2026-06-07T18:30:00Z',
        claim_code: '654321', message: 'A dinner voucher delivered with a music experience.',
        is_demo: true,
        posts: [
          { type: 'cover', title: 'Dinner and a playlist', body: 'Open the envelope and enjoy the experience before the offer.', meta: 'Envelope cover' },
          { type: 'media', title: 'Dinner soundtrack', body: 'Demo music-player module placeholder.', meta: 'Audio content' },
          { type: 'message', title: 'From Alex', body: 'Dinner is on me. Play this on the way there.', meta: 'Personal message' },
          { type: 'offer', title: '$50 dinner voucher', body: 'Valid at the participating Roosevelt Row location.', meta: 'Redeemable voucher' }
        ]
      },
      {
        action_item_id: 'demo-carousel-003', folder: 'inbox', state: 'redeemable',
        instance_id: 'MG-DEMO-003', template_name: 'Desert spa afternoon',
        merchant_name: 'Sonoran Wellness Spa', location_name: 'Scottsdale, AZ',
        face_value_cents: 7500, currency: 'USD', expires_at: 'Mar 31, 2027',
        sender_name: 'Microgifter Demo Merchant', recipient_name: 'Super Admin',
        product_type: 'Experience voucher', received_at: '2026-06-06T14:00:00Z',
        claim_code: '777888', message: 'A visual story layered over a spa voucher.',
        is_demo: true,
        posts: [
          { type: 'cover', title: 'Desert spa afternoon', body: 'A calm experience delivered inside a tracked envelope.', meta: 'Envelope cover' },
          { type: 'media', title: 'Experience gallery', body: 'Demo image-carousel module placeholder.', meta: 'Image carousel' },
          { type: 'offer', title: '$75 spa voucher', body: 'Redeem at the listed Scottsdale location.', meta: 'Redeemable voucher' }
        ]
      }
    ],
    sent: [
      {
        action_item_id: 'demo-sent-001', folder: 'sent', state: 'claimable',
        instance_id: 'MG-DEMO-010', template_name: 'Neighborhood bookstore credit',
        merchant_name: 'Changing Hands Bookstore', location_name: 'Phoenix, AZ',
        face_value_cents: 3000, currency: 'USD', expires_at: 'No expiration',
        sender_name: 'Super Admin', recipient_name: 'Jordan Lee',
        product_type: 'Store credit', sent_at: '2026-06-05T11:00:00Z',
        last_follow_up_at: '2026-06-06T10:30:00Z', follow_up_count: 1,
        can_follow_up: true,
        message: 'A book recommendation and store voucher sent together.',
        is_demo: true,
        posts: [
          { type: 'message', title: 'A book for your next weekend', body: 'Pick something unexpected.', meta: 'Sent message' },
          { type: 'offer', title: '$30 bookstore voucher', body: 'The voucher travels with the envelope after transfer.', meta: 'Transferred voucher' }
        ]
      },
      {
        action_item_id: 'demo-sent-002', folder: 'sent', state: 'claimable',
        instance_id: 'MG-DEMO-011', template_name: 'Local lunch reward',
        merchant_name: 'Cactus Table', location_name: 'Tempe, AZ',
        face_value_cents: 2000, currency: 'USD', expires_at: 'Sep 30, 2026',
        sender_name: 'Super Admin', recipient_name: 'Taylor Reed',
        product_type: 'Workplace reward', sent_at: '2026-06-04T16:15:00Z',
        follow_up_count: 0, can_follow_up: true,
        message: 'A workplace reward with branded content attached.',
        is_demo: true,
        posts: [
          { type: 'media', title: 'Team thank-you', body: 'Demo branded video module placeholder.', meta: 'Video content' },
          { type: 'offer', title: '$20 lunch voucher', body: 'Delivered to the recipient with the original envelope history.', meta: 'Transferred voucher' }
        ]
      }
    ],
    claimed: [
      {
        action_item_id: 'demo-claimed-001', folder: 'claimed', state: 'redeemed',
        instance_id: 'MG-DEMO-020', template_name: 'Farmers market basket',
        merchant_name: 'Uptown Farmers Market', location_name: 'Phoenix, AZ',
        face_value_cents: 4000, currency: 'USD', expires_at: 'Redeemed',
        sender_name: 'Morgan Chen', recipient_name: 'Super Admin',
        product_type: 'Market voucher', redeemed_at: '2026-06-03T10:45:00Z',
        message: 'Successfully redeemed at an authorized merchant location.',
        is_demo: true,
        posts: [
          { type: 'message', title: 'Enjoy the market', body: 'A message that remained attached through redemption.', meta: 'Gift content' },
          { type: 'offer', title: 'Redeemed $40 voucher', body: 'The protected voucher is now shown with its completed redemption state.', meta: 'Redeemed voucher' }
        ]
      },
      {
        action_item_id: 'demo-claimed-002', folder: 'claimed', state: 'redeemed',
        instance_id: 'MG-DEMO-021', template_name: 'Independent cinema night',
        merchant_name: 'FilmBar Phoenix', location_name: 'Phoenix, AZ',
        face_value_cents: 3500, currency: 'USD', expires_at: 'Redeemed',
        sender_name: 'Microgifter Demo Merchant', recipient_name: 'Super Admin',
        product_type: 'Entertainment voucher', redeemed_at: '2026-06-01T20:00:00Z',
        message: 'A completed gift journey from payment through redemption.',
        is_demo: true,
        posts: [
          { type: 'media', title: 'Tonight at the cinema', body: 'Demo trailer module placeholder.', meta: 'Video content' },
          { type: 'offer', title: 'Redeemed $35 cinema voucher', body: 'Redemption history remains attached to the tracked envelope.', meta: 'Redeemed voucher' }
        ]
      }
    ]
  };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function money(cents, currency) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(cents || 0) / 100);
  }

  function dateLabel(value) {
    if (!value) return '';
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function normalize(item) {
    var metadata = {};
    try {
      metadata = typeof item.metadata_json === 'string' ? JSON.parse(item.metadata_json) : item.metadata_json || {};
    } catch (error) {}
    return Object.assign({}, item, {
      template_name: item.template_name || item.title || 'Microgift',
      merchant_name: item.merchant_name || item.sender_name || 'Participating merchant',
      product_type: item.product_type || metadata.product_type || metadata.productType || 'PPPM gift',
      sent_at: item.sent_at || item.created_at || metadata.sent_at || metadata.sentAt || '',
      last_follow_up_at: item.last_follow_up_at || metadata.last_follow_up_at || metadata.lastFollowUpAt || '',
      follow_up_count: Number(item.follow_up_count || metadata.follow_up_count || metadata.followUpCount || 0),
      can_follow_up: item.can_follow_up === undefined ? Boolean(item.is_demo) : Boolean(item.can_follow_up),
      received_at: item.received_at || metadata.received_at || metadata.receivedAt || '',
      redeemed_at: item.redeemed_at || metadata.redeemed_at || metadata.redeemedAt || '',
      posts: Array.isArray(item.posts) ? item.posts : (Array.isArray(metadata.posts) ? metadata.posts : []),
      is_demo: Boolean(item.is_demo)
    });
  }

  function setCounts(counts) {
    ['inbox', 'sent', 'claimed'].forEach(function (folder) {
      var source = counts && counts[folder];
      var total = source && typeof source === 'object' ? source.total : source;
      var unread = source && typeof source === 'object' ? source.unread : 0;
      state.counts[folder] = Number(total || 0);
      state.unread[folder] = Number(unread || 0);
      document.querySelectorAll('[data-gift-count="' + folder + '"],[data-gift-nav-count="' + folder + '"]').forEach(function (element) {
        element.textContent = String(state.counts[folder]);
      });
      document.querySelectorAll('[data-gift-nav-unread="' + folder + '"]').forEach(function (element) {
        var value = state.unread[folder] || state.counts[folder];
        element.textContent = String(value);
        element.classList.toggle('has-unread', value > 0);
        element.hidden = value <= 0;
      });
    });
  }

  function currentItems() {
    return state.folders[state.folder] || [];
  }

  function filtered() {
    var input = app.querySelector('[data-gift-search]');
    var query = (input && input.value || '').toLowerCase();
    return currentItems().filter(function (item) {
      return [item.template_name, item.merchant_name, item.recipient_name, item.sender_name, item.instance_id, item.product_type, item.state]
        .join(' ').toLowerCase().includes(query);
    });
  }

  function updateFolderText() {
    var copy = folderCopy[state.folder] || folderCopy.inbox;
    if (folderLabel) folderLabel.textContent = copy.title;
    if (folderSubtitle) folderSubtitle.textContent = copy.subtitle;
    if (folderDescription) folderDescription.textContent = copy.subtitle;
    document.querySelectorAll('[data-system-tab]').forEach(function (tab) {
      var link = tab.querySelector('a');
      if (link) link.classList.toggle('is-active', tab.dataset.systemTab === state.folder);
    });
  }

  function metadata(item) {
    var parts = [];
    if (state.folder === 'inbox') {
      parts.push('From: ' + (item.sender_name || item.merchant_name || 'Unknown'));
      parts.push('Received: ' + (dateLabel(item.received_at || item.sent_at) || 'Recently'));
    } else if (state.folder === 'sent') {
      parts.push('To: ' + (item.recipient_name || 'Recipient'));
      parts.push('Sent: ' + (dateLabel(item.sent_at || item.created_at) || 'Recently'));
      if (item.last_follow_up_at) parts.push('Last Follow Up: ' + dateLabel(item.last_follow_up_at));
      if (item.follow_up_count > 0) parts.push('Follow Ups: ' + item.follow_up_count);
      if (!item.can_follow_up) parts.push('Recipient has transferred or closed this gift');
    } else {
      parts.push('Merchant: ' + (item.merchant_name || 'Merchant'));
      parts.push('Claimed: ' + (dateLabel(item.redeemed_at || item.claimed_at || item.updated_at) || 'Recently'));
    }
    parts.push('Type: ' + (item.product_type || 'PPPM gift'));
    parts.push('Value: ' + money(item.face_value_cents, item.currency));
    parts.push('Status: ' + (item.state || state.folder));
    if (item.expires_at) parts.push('Expires: ' + item.expires_at);
    if (item.is_demo) parts.push('Demo content');
    return parts;
  }

  function rowActions(item) {
    if (state.folder === 'inbox') {
      return '<button class="mg-gift-row-action is-primary" type="button" data-gift-action="send">Regift</button>' +
        '<button class="mg-gift-row-action" type="button" data-gift-action="claim">Claim</button>' +
        '<button class="mg-gift-row-action" type="button" data-gift-action="load">Load</button>';
    }
    if (state.folder === 'sent') {
      var followUp = item.can_follow_up
        ? '<button class="mg-gift-row-action is-primary" type="button" data-gift-action="follow-up">Follow Up</button>'
        : '<button class="mg-gift-row-action" type="button" disabled title="Only the most recent sender can follow up">Follow Up unavailable</button>';
      return followUp + '<button class="mg-gift-row-action" type="button" data-gift-action="load">Load</button>';
    }
    return '<button class="mg-gift-row-action is-primary" type="button" data-gift-action="message">Message</button>' +
      '<button class="mg-gift-row-action" type="button" data-gift-action="tip">Tip</button>';
  }

  function renderList() {
    updateFolderText();
    var items = filtered();
    list.innerHTML = items.length ? items.map(function (item) {
      var active = state.selected && state.selected.action_item_id === item.action_item_id;
      var meta = metadata(item).map(function (piece) { return '<span>' + esc(piece) + '</span>'; }).join('');
      return '<article class="mg-gift-row ' + (active ? 'is-active ' : '') + (item.is_demo ? 'is-demo' : '') + '" data-gift-id="' + esc(item.action_item_id) + '">' +
        '<div class="mg-gift-thumb" aria-hidden="true">' + esc(String(item.template_name || 'G').charAt(0).toUpperCase()) + '</div>' +
        '<div class="mg-gift-row-main"><div class="mg-gift-row-top"><h3>' + esc(item.template_name) + '</h3>' +
        '<span class="mg-gift-status ' + (state.folder === 'claimed' ? 'is-claimed' : '') + '">' + esc(item.is_demo ? 'demo · ' + state.folder : state.folder) + '</span></div>' +
        '<p>' + esc(item.message || item.location_name || 'Gift ready to open') + '</p><div class="mg-gift-row-meta">' + meta + '</div></div>' +
        '<div class="mg-gift-row-actions">' + rowActions(item) + '</div></article>';
    }).join('') : '<div class="mg-gift-empty-list"><strong>No ' + esc(state.folder) + ' gifts</strong><p>Items matching this folder will appear here.</p></div>';
  }

  function claimBlock(item) {
    if (item.is_demo) {
      return '<div class="mg-gift-claim-code"><span>Demo merchant claim code</span><strong>' + esc(item.claim_code || 'DEMO') + '</strong>' +
        '<small>Super Admin preview only. No real claim, payment, ownership, ledger, notification, payout, or webhook action is created.</small></div>';
    }
    return '<div class="mg-gift-claim-code"><span>Merchant claim</span><strong>Ready</strong>' +
      '<small>Present the gift at the merchant. The authorized location claim code is entered into this voucher and recorded with a timestamp.</small></div>';
  }

  function couponCard(item) {
    var claimed = item.folder === 'claimed' || item.state === 'redeemed';
    return '<section class="mg-gift-card-preview"><div class="mg-gift-card-hero"><span class="mg-eyebrow">' + esc(item.merchant_name) + '</span>' +
      '<h2>' + esc(item.template_name) + '</h2><p>' + esc(item.message || 'A gift is waiting for you.') + '</p></div>' +
      '<div class="mg-gift-card-body"><div class="mg-gift-value">' + money(item.face_value_cents, item.currency) + '</div>' + claimBlock(item) +
      '<div class="mg-gift-meta"><div><span>Status</span><strong>' + esc(claimed ? 'Claimed' : 'Received') + '</strong></div>' +
      '<div><span>Location</span><strong>' + esc(item.location_name || 'Participating locations') + '</strong></div>' +
      '<div><span>Gift ID</span><strong>' + esc(item.instance_id || '') + '</strong></div>' +
      '<div><span>Expires</span><strong>' + esc(item.expires_at || 'No expiration') + '</strong></div></div></div></section>';
  }

  function defaultPosts(item) {
    return [{ type: 'gift', title: item.template_name || 'Gift content', body: item.message || 'This PPPM item does not yet include additional content.', meta: item.merchant_name || 'Microgifter' }];
  }

  function postIcon(type) {
    return type === 'message' ? '✉' : type === 'offer' ? '🎟' : type === 'media' ? '▶' : '🎁';
  }

  function openContent(item) {
    state.selected = item;
    renderList();
    var posts = item.posts && item.posts.length ? item.posts : defaultPosts(item);
    drawerTitle.textContent = item.template_name || 'Gift content';
    drawerContent.innerHTML = '<div class="mg-pppm-post-stack">' + posts.map(function (post, index) {
      return '<article class="mg-pppm-post"><div class="mg-pppm-post-media">' + postIcon(post.type) + '</div>' +
        '<span class="mg-eyebrow">Content ' + (index + 1) + ' of ' + posts.length + '</span><h3>' + esc(post.title || item.template_name) + '</h3>' +
        '<p>' + esc(post.body || '').replace(/\n/g, '<br>') + '</p><div class="mg-pppm-post-meta"><span>' + esc(post.meta || 'PPPM content') + '</span>' +
        '<span>' + esc(item.instance_id || '') + '</span></div></article>';
    }).join('') + '</div><div class="mg-gift-drawer-card"><span class="mg-eyebrow">Protected voucher</span>' + couponCard(item) + '</div>';
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    backdrop.hidden = false;
    document.body.classList.add('mg-modal-lock');
    drawerContent.scrollTop = 0;
  }

  function closeDrawer() {
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    backdrop.hidden = true;
    document.body.classList.remove('mg-modal-lock');
  }

  function formField(label, name, type, placeholder, required) {
    if (type === 'textarea') {
      return '<label>' + label + '<textarea name="' + name + '" placeholder="' + esc(placeholder || '') + '" ' + (required ? 'required' : '') + '></textarea></label>';
    }
    return '<label>' + label + '<input type="' + type + '" name="' + name + '" placeholder="' + esc(placeholder || '') + '" ' + (required ? 'required' : '') + '></label>';
  }

  function modalForm(action, item) {
    var note = '<div class="mg-action-form-note">' + (item.is_demo
      ? 'Super Admin demo content cannot execute real transactional actions.'
      : 'This action is recorded against the same PPPM gift ID with its own timestamp.') + '</div>';

    if (action === 'send') {
      return '<form class="mg-action-form" data-action-form="send">' +
        formField('Recipient', 'recipient', 'text', 'Search and select a recipient', true) +
        formField('Message', 'message', 'textarea', 'Add a note to travel with the gift', false) + note +
        '<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button>' +
        '<button class="mg-btn mg-btn-primary" type="submit">Regift Microgift</button></div></form>';
    }
    if (action === 'follow-up') {
      return '<form class="mg-action-form" data-action-form="follow-up">' +
        '<div class="mg-action-form-note"><strong>Follow up with ' + esc(item.recipient_name || 'the current recipient') + '</strong><br>' +
        'This sends a private message in the conversation created by your transfer. Ownership and delivery history do not change.</div>' +
        formField('Message', 'message', 'textarea', 'Write a helpful follow-up', true) +
        '<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button>' +
        '<button class="mg-btn mg-btn-primary" type="submit">Send Follow Up</button></div></form>';
    }
    if (action === 'claim') {
      return '<form class="mg-action-form" data-action-form="claim">' +
        formField('Merchant or location', 'merchant', 'text', item.location_name || 'Select merchant location', true) +
        formField('Claim code', 'claim_code', 'text', item.is_demo ? (item.claim_code || 'DEMO') : 'Enter merchant claim code', true) + note +
        '<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button>' +
        '<button class="mg-btn mg-btn-primary" type="submit">Submit claim</button></div></form>';
    }
    if (action === 'tip') {
      return '<form class="mg-action-form" data-action-form="tip">' + formField('Tip amount', 'amount', 'number', '5.00', true) +
        formField('Message', 'message', 'textarea', 'Add a thank-you note', false) + note +
        '<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button>' +
        '<button class="mg-btn mg-btn-primary" type="submit">Stage 12 preview</button></div></form>';
    }
    return '<form class="mg-action-form" data-action-form="message">' +
      formField('To', 'recipient', 'text', item.recipient_name || item.sender_name || 'Gift participant', true) +
      formField('Message', 'message', 'textarea', 'Write a message', true) + note +
      '<div class="mg-action-form-footer"><button class="mg-btn mg-btn-soft" type="button" data-action-modal-close>Cancel</button>' +
      '<button class="mg-btn mg-btn-primary" type="submit">Send message</button></div></form>';
  }

  function openModal(action, item) {
    state.selected = item;
    renderList();
    var titles = { send: 'Regift Microgift', 'follow-up': 'Follow Up', claim: 'Claim gift', tip: 'Send a tip', message: 'Message participant' };
    modalEyebrow.textContent = item.template_name || 'PPPM gift';
    modalTitle.textContent = titles[action] || 'Gift action';
    modalBody.innerHTML = modalForm(action, item);
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    modalBackdrop.hidden = false;
    document.body.classList.add('mg-modal-lock');
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    modalBackdrop.hidden = true;
    document.body.classList.remove('mg-modal-lock');
    modalBody.innerHTML = '';
  }

  async function loadFolder(folder, force) {
    if (state.loading) return;
    state.folder = folder;
    state.loading = true;
    updateFolderText();
    list.innerHTML = '<div class="mg-gift-empty-list"><strong>Loading gifts…</strong></div>';
    try {
      if (window.Microgifter && (force || !state.folders[folder].length)) {
        var response = await Microgifter.get('/api/account/action-center.php?folder=' + encodeURIComponent(folder) + '&limit=100');
        var data = response.data || response;
        state.folders[folder] = (data.items || []).map(normalize);
        setCounts(data.counts || state.counts);
      }
    } catch (error) {
      console.error(error);
    }
    if (demoEnabled && !state.folders[folder].length) {
      state.folders[folder] = (demos[folder] || []).map(normalize);
      state.counts[folder] = state.folders[folder].length;
      state.unread[folder] = folder === 'inbox' ? state.folders[folder].length : 0;
      setCounts({
        inbox: { total: state.counts.inbox, unread: state.unread.inbox },
        sent: { total: state.counts.sent, unread: state.unread.sent },
        claimed: { total: state.counts.claimed, unread: state.unread.claimed }
      });
    }
    state.loading = false;
    state.selected = null;
    renderList();
  }

  document.querySelectorAll('[data-system-tab]').forEach(function (tab) {
    var link = tab.querySelector('a');
    if (!link) return;
    var folder = tab.dataset.systemTab;
    if (!['inbox', 'sent', 'claimed'].includes(folder)) return;
    link.addEventListener('click', function (event) {
      event.preventDefault();
      window.history.pushState({}, '', link.getAttribute('href'));
      loadFolder(folder, false);
    });
  });

  list.addEventListener('click', function (event) {
    var row = event.target.closest('[data-gift-id]');
    if (!row) return;
    var item = currentItems().find(function (candidate) { return candidate.action_item_id === row.dataset.giftId; });
    if (!item) return;
    state.selected = item;
    if (!event.target.closest('[data-gift-action]')) renderList();
  });

  app.addEventListener('click', function (event) {
    var action = event.target.closest('[data-gift-action]');
    if (action) {
      var row = action.closest('[data-gift-id]');
      var item = row ? currentItems().find(function (candidate) { return candidate.action_item_id === row.dataset.giftId; }) : state.selected;
      if (!item) return;
      var type = action.dataset.giftAction;
      if (type === 'load') openContent(item);
      else openModal(type, item);
    }
    if (event.target.closest('[data-action-modal-close]')) closeModal();
  });

  modalBody.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-action-form]');
    if (!form) return;
    event.preventDefault();
    if (state.selected && state.selected.is_demo) {
      modalBody.innerHTML = '<div class="mg-action-success"><strong>Demo preview only</strong>' +
        '<p>No real payment, ownership transfer, regift, Follow Up, claim, message, tip, notification, ledger entry, payout, or webhook was created.</p>' +
        '<button class="mg-btn mg-btn-primary" type="button" data-action-modal-close>Done</button></div>';
      return;
    }
    var type = form.dataset.actionForm;
    var data = Object.fromEntries(new FormData(form).entries());
    app.dispatchEvent(new CustomEvent('mg:gift-action:submit', { bubbles: true, detail: { type: type, item: state.selected, data: data } }));
  });

  var search = app.querySelector('[data-gift-search]');
  var refresh = app.querySelector('[data-gift-refresh]');
  var drawerClose = app.querySelector('[data-gift-drawer-close]');
  if (search) search.addEventListener('input', renderList);
  if (refresh) refresh.addEventListener('click', function () { state.folders[state.folder] = []; loadFolder(state.folder, true); });
  if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
  backdrop.addEventListener('click', closeDrawer);
  modalBackdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { closeDrawer(); closeModal(); }
  });

  loadFolder(state.folder, true);
});

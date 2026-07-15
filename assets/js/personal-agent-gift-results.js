document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = window.MicrogifterPersonalAgent;
  if (!app || !app.root || !window.Microgifter) return;

  var root = app.root;
  var feed = app.ui && app.ui.feed;
  var dataOf = app.dataOf;
  var setStatus = app.setStatus;
  if (!feed) return;

  var modal = null;
  var backdrop = null;
  var modalBody = null;
  var modalTitle = null;
  var modalEyebrow = null;
  var searchController = null;
  var searchTimer = 0;
  var activeCard = null;
  var activeArticle = null;
  var activeGrid = null;
  var activeIndex = -1;
  var confirmed = false;
  var confirmKey = '';
  var confirmIdempotencyKey = '';

  function text(value) {
    return String(value == null ? '' : value).trim();
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function accountGift(card) {
    return card && text(card.type).toLowerCase() === 'account_gift' && text(card.action_item_id || card.id) !== '';
  }

  function internalHref(value) {
    try {
      var url = new URL(text(value), window.location.origin);
      if (url.origin !== window.location.origin) return '';
      return url.pathname + url.search + url.hash;
    } catch (error) {
      return '';
    }
  }

  function imageHref(value) {
    try {
      var url = new URL(text(value), window.location.origin);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
      return url.href;
    } catch (error) {
      return '';
    }
  }

  function element(tag, className, value) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (value != null) node.textContent = String(value);
    return node;
  }

  function link(href, className, label) {
    var safe = internalHref(href);
    if (!safe) return null;
    var node = element('a', className, label);
    node.href = safe;
    return node;
  }

  function shortDate(value) {
    if (!value) return '';
    var raw = String(value);
    var date = new Date(/[zZ]$|[+-]\d{2}:?\d{2}$/.test(raw) ? raw : raw.replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
  }

  function renderMeta(card, host) {
    var items = Array.isArray(card.meta) ? card.meta : [];
    if (!items.length) return;
    var list = element('dl', 'mg-agent-gift-meta');
    items.slice(0, 5).forEach(function (item) {
      if (!item || item.value == null || text(item.value) === '') return;
      var row = element('div', 'mg-agent-gift-meta-item');
      row.appendChild(element('dt', '', item.label || 'Detail'));
      var value = ['Completed', 'Expires', 'Received', 'Sent'].indexOf(String(item.label || '')) !== -1 ? shortDate(item.value) : item.value;
      row.appendChild(element('dd', '', value));
      list.appendChild(row);
    });
    if (list.children.length) host.appendChild(list);
  }

  function renderGiftCard(article, card, index) {
    var primary = internalHref(card.url);
    var image = imageHref(card.image_url);
    var folder = text(card.folder).toLowerCase() || 'inbox';

    article.className = 'mg-personal-agent-chat-card mg-agent-gift-card is-' + folder;
    article.setAttribute('data-account-gift-result', text(card.action_item_id || card.id));
    article.setAttribute('data-agent-gift-card-index', String(index));
    article.setAttribute('role', 'listitem');
    article.innerHTML = '';

    var media = primary ? link(primary, 'mg-agent-gift-media', '') : element('div', 'mg-agent-gift-media');
    if (image) {
      var img = document.createElement('img');
      img.src = image;
      img.alt = text(card.image_alt || card.title || 'Microgift image');
      img.loading = 'lazy';
      img.decoding = 'async';
      media.appendChild(img);
    } else {
      var fallback = element('span', 'mg-agent-gift-media-fallback', text(card.title || 'G').charAt(0).toUpperCase() || 'G');
      fallback.setAttribute('aria-hidden', 'true');
      media.appendChild(fallback);
    }
    article.appendChild(media);

    var content = element('div', 'mg-agent-gift-content');
    var heading = element('div', 'mg-agent-gift-heading');
    var titleWrap = element('div', 'mg-agent-gift-title-wrap');
    titleWrap.appendChild(element('span', 'mg-agent-gift-eyebrow', card.eyebrow || (folder + ' Microgift')));
    var title = element('h3', '', '');
    var titleLink = primary ? link(primary, '', card.title || 'Microgift') : null;
    if (titleLink) title.appendChild(titleLink);
    else title.textContent = text(card.title || 'Microgift');
    titleWrap.appendChild(title);
    heading.appendChild(titleWrap);
    if (card.price) heading.appendChild(element('strong', 'mg-agent-gift-price', card.price));
    content.appendChild(heading);

    if (card.body) content.appendChild(element('p', 'mg-agent-gift-description', card.body));
    renderMeta(card, content);

    var actions = element('footer', 'mg-agent-gift-actions');
    if (card.can_send) {
      var send = element('button', 'mg-agent-gift-action is-primary', card.send_label || 'Send');
      send.type = 'button';
      send.setAttribute('data-agent-gift-send', text(card.action_item_id || card.id));
      actions.appendChild(send);
    } else {
      actions.appendChild(element('span', 'mg-agent-gift-view-only', folder === 'sent' ? 'Already sent' : (folder === 'claimed' ? 'View only' : 'Not transferable')));
    }
    var open = primary ? link(primary, 'mg-agent-gift-action', card.url_label || 'Open gift') : null;
    if (open) actions.appendChild(open);
    if (actions.children.length) content.appendChild(actions);

    article.appendChild(content);
  }

  function enhanceGrid(grid) {
    if (!grid || grid.getAttribute('data-account-gift-enhanced') === '1') return;
    var cards = Array.isArray(grid._agentCards) ? grid._agentCards : [];
    if (!cards.some(accountGift)) return;

    grid.setAttribute('data-account-gift-enhanced', '1');
    grid.classList.add('mg-agent-gift-grid');
    grid.setAttribute('role', 'list');
    var articles = Array.prototype.slice.call(grid.children);
    cards.forEach(function (card, index) {
      if (!accountGift(card) || !articles[index]) return;
      renderGiftCard(articles[index], card, index);
    });
  }

  function scan(node) {
    if (!node || node.nodeType !== 1) return;
    if (node.matches && node.matches('.mg-personal-agent-card-grid')) enhanceGrid(node);
    if (node.querySelectorAll) node.querySelectorAll('.mg-personal-agent-card-grid').forEach(enhanceGrid);
  }

  function ensureModal() {
    if (modal) return;

    backdrop = element('div', 'mg-action-modal-backdrop');
    backdrop.setAttribute('data-personal-agent-gift-send-backdrop', '');
    backdrop.hidden = true;

    modal = element('section', 'mg-action-modal mg-send-exact-modal mg-agent-gift-send-modal');
    modal.setAttribute('data-personal-agent-gift-send-modal', '');
    modal.setAttribute('aria-hidden', 'true');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'personal-agent-gift-send-title');
    modal.innerHTML = '<header class="mg-action-modal-header">' +
      '<div><span class="mg-account-eyebrow" data-personal-agent-gift-send-eyebrow>Send Microgift</span>' +
      '<h2 id="personal-agent-gift-send-title" data-personal-agent-gift-send-title>Send gift</h2></div>' +
      '<button type="button" data-personal-agent-gift-send-close aria-label="Close form">×</button>' +
      '</header><div class="mg-action-modal-body" data-personal-agent-gift-send-body></div>';

    document.body.appendChild(backdrop);
    document.body.appendChild(modal);
    modalBody = modal.querySelector('[data-personal-agent-gift-send-body]');
    modalTitle = modal.querySelector('[data-personal-agent-gift-send-title]');
    modalEyebrow = modal.querySelector('[data-personal-agent-gift-send-eyebrow]');

    backdrop.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
      if (event.target.closest('[data-personal-agent-gift-send-close]')) closeModal();
      var result = event.target.closest('[data-recipient-index]');
      if (result) {
        var results = modalBody.querySelector('[data-send-recipient-results]');
        var items = results && results.__items ? results.__items : [];
        var profile = items[Number(result.getAttribute('data-recipient-index'))];
        var form = modalBody.querySelector('[data-action-form="send"]');
        if (profile && form) selectRecipient(form, profile);
      }
      if (event.target.closest('[data-clear-recipient]')) {
        var activeForm = modalBody.querySelector('[data-action-form="send"]');
        if (activeForm) {
          clearSelectedRecipient(activeForm);
          var input = activeForm.querySelector('input[name="recipient"]');
          if (input) { input.value = ''; input.focus(); }
        }
      }
    });

    modalBody.addEventListener('input', function (event) {
      var form = event.target.closest('[data-action-form="send"]');
      if (!form) return;
      resetConfirmation(form);
      if (event.target.matches('input[name="recipient"]')) {
        clearSelectedRecipient(form);
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () { searchRecipients(form, event.target.value); }, 180);
      }
      if (event.target.matches('textarea[name="message"]')) {
        var counter = form.querySelector('[data-send-message-count]');
        if (counter) counter.textContent = String(event.target.value.length) + '/500';
      }
    }, true);

    modalBody.addEventListener('submit', submitSend, true);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal && modal.getAttribute('aria-hidden') === 'false') closeModal();
    });
  }

  function openModal() {
    ensureModal();
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    backdrop.hidden = false;
    document.body.classList.add('mg-modal-lock');
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    backdrop.hidden = true;
    document.body.classList.remove('mg-modal-lock');
    modalBody.innerHTML = '';
    activeCard = null;
    activeArticle = null;
    activeGrid = null;
    activeIndex = -1;
    confirmed = false;
    confirmKey = '';
    confirmIdempotencyKey = '';
  }

  function thumbMarkup(card) {
    var image = imageHref(card && card.image_url);
    if (image) return '<img src="' + esc(image) + '" alt="' + esc(card.title || 'Microgift') + ' product image">';
    return '<span>' + esc(text(card && card.title || 'G').charAt(0).toUpperCase() || 'G') + '</span>';
  }

  function recipientAvatarMarkup(profile) {
    var name = profile.display_name || profile.name || profile.slug || 'User';
    var avatar = imageHref(profile.avatar_url || profile.avatar || profile.profile_image_url || '');
    if (avatar) return '<img src="' + esc(avatar) + '" alt="" loading="lazy">';
    return '<span>' + esc(text(name).charAt(0).toUpperCase() || 'U') + '</span>';
  }

  function recipientPublicId(profile) {
    return text(profile.id || profile.public_id || profile.profile_id || '');
  }

  function resultMarkup(profile, index) {
    var name = profile.display_name || profile.name || profile.slug || 'Microgifter user';
    var handle = profile.slug ? '@' + profile.slug : 'Microgifter user';
    var headline = profile.headline || profile.location || profile.profile_type || 'Searchable Microgifter profile';
    var type = profile.profile_type ? String(profile.profile_type).replace(/_/g, ' ') : 'profile';
    var location = profile.location ? ' · ' + profile.location : '';
    return '<button class="mg-send-result" type="button" role="option" data-recipient-index="' + String(index) + '">' +
      '<span class="mg-send-result-avatar">' + recipientAvatarMarkup(profile) + '</span>' +
      '<span class="mg-send-result-main"><strong>' + esc(name) + '</strong><em>' + esc(headline) + '</em><small>' + esc(handle + ' · ' + type + location) + '</small></span>' +
      '</button>';
  }

  function renderResults(form, items, message) {
    var results = form.querySelector('[data-send-recipient-results]');
    var input = form.querySelector('input[name="recipient"]');
    if (!results) return;
    results.hidden = false;
    if (input) input.setAttribute('aria-expanded', 'true');
    results.innerHTML = items && items.length
      ? '<div class="mg-send-results-list">' + items.map(resultMarkup).join('') + '</div>'
      : '<div class="mg-send-results-empty">' + esc(message || 'No matching users found.') + '</div>';
    results.__items = items || [];
  }

  function clearResults(form) {
    var results = form.querySelector('[data-send-recipient-results]');
    var input = form.querySelector('input[name="recipient"]');
    if (!results) return;
    results.hidden = true;
    results.innerHTML = '';
    results.__items = [];
    if (input) input.setAttribute('aria-expanded', 'false');
  }

  function clearSelectedRecipient(form) {
    ['recipient_profile_id', 'recipient_user_id', 'recipient_slug'].forEach(function (name) {
      var field = form.querySelector('input[name="' + name + '"]');
      if (field) field.value = '';
    });
    delete form.dataset.recipientProfileId;
    delete form.dataset.recipientSlug;
    delete form.dataset.recipientLabel;
    var selected = form.querySelector('[data-selected-recipient]');
    if (selected) {
      selected.hidden = true;
      selected.innerHTML = '';
      delete selected.dataset.recipientProfileId;
      delete selected.dataset.recipientSlug;
      delete selected.dataset.recipientLabel;
    }
  }

  function selectRecipient(form, profile) {
    var input = form.querySelector('input[name="recipient"]');
    var profileId = form.querySelector('input[name="recipient_profile_id"]');
    var userId = form.querySelector('input[name="recipient_user_id"]');
    var slug = form.querySelector('input[name="recipient_slug"]');
    var selected = form.querySelector('[data-selected-recipient]');
    var name = profile.display_name || profile.name || profile.slug || '';
    var publicId = recipientPublicId(profile);
    var slugValue = text(profile.slug);
    var label = name || (slugValue ? '@' + slugValue : 'Selected user');

    if (input) input.value = name;
    if (profileId) profileId.value = publicId;
    if (userId) userId.value = publicId;
    if (slug) slug.value = slugValue;
    form.dataset.recipientProfileId = publicId;
    form.dataset.recipientSlug = slugValue;
    form.dataset.recipientLabel = label;

    if (selected) {
      selected.hidden = false;
      selected.dataset.recipientProfileId = publicId;
      selected.dataset.recipientSlug = slugValue;
      selected.dataset.recipientLabel = label;
      selected.innerHTML = '<span class="mg-send-selected-avatar">' + recipientAvatarMarkup(profile) + '</span>' +
        '<span><strong>' + esc(label) + '</strong><em>' + esc(slugValue ? '@' + slugValue : (profile.profile_type || 'profile')) + '</em></span>' +
        '<button type="button" data-clear-recipient aria-label="Clear selected recipient">×</button>';
    }
    clearResults(form);
    resetConfirmation(form);
  }

  async function searchRecipients(form, query) {
    if (searchController) searchController.abort();
    if (!query || query.trim().length < 1) {
      clearResults(form);
      return;
    }
    searchController = new AbortController();
    renderResults(form, [], 'Searching Microgifter users…');
    try {
      var response = await fetch('/api/public/discover.php?q=' + encodeURIComponent(query.trim()) + '&limit=8&sort=trending', {
        headers: { 'Accept': 'application/json' },
        signal: searchController.signal
      });
      if (!response.ok) throw new Error('Search failed');
      var payload = await response.json();
      var items = payload && payload.data && payload.data.results && Array.isArray(payload.data.results.items)
        ? payload.data.results.items
        : [];
      renderResults(form, items, 'No matching Microgifter users found.');
    } catch (error) {
      if (error.name === 'AbortError') return;
      renderResults(form, [], 'Unable to load users. Keep typing or try again.');
    }
  }

  function buildSendForm(card) {
    ensureModal();
    confirmed = false;
    confirmKey = '';
    confirmIdempotencyKey = '';
    modalEyebrow.textContent = card.eyebrow || 'Inbox Microgift';
    modalTitle.textContent = 'Send Microgift';
    var recipientId = 'mg-agent-send-recipient-' + Date.now();
    var resultsId = recipientId + '-results';

    modalBody.innerHTML = '<form class="mg-send-exact-form" data-action-form="send">' +
      '<input type="hidden" name="action_item_id" value="' + esc(card.action_item_id || card.id) + '">' +
      '<section class="mg-send-exact-product" aria-label="Selected gift">' +
        '<div class="mg-send-exact-thumb">' + thumbMarkup(card) + '</div>' +
        '<div class="mg-send-exact-product-copy"><h2>' + esc(card.title || 'Microgift') + '</h2><p>' + esc((card.merchant_name || 'Microgifter') + (card.price ? ' · ' + card.price : '')) + '</p></div>' +
      '</section>' +
      '<div class="mg-send-exact-field mg-send-exact-recipient">' +
        '<label for="' + recipientId + '">Send to</label>' +
        '<div class="mg-send-exact-input-shell"><span class="mg-send-exact-search" aria-hidden="true"></span>' +
          '<input id="' + recipientId + '" type="text" name="recipient" required autocomplete="off" placeholder="Search any Microgifter user" aria-expanded="false" aria-controls="' + resultsId + '">' +
          '<input type="hidden" name="recipient_profile_id"><input type="hidden" name="recipient_user_id"><input type="hidden" name="recipient_slug">' +
        '</div>' +
        '<div class="mg-send-selected" data-selected-recipient hidden></div>' +
        '<div class="mg-send-results" id="' + resultsId + '" data-send-recipient-results role="listbox" hidden></div>' +
        '<small>Search public Microgifter profiles and select the recipient. Nothing is sent until the confirmation step.</small>' +
      '</div>' +
      '<div class="mg-send-exact-field mg-send-exact-message"><label>Message</label>' +
        '<textarea name="message" maxlength="500" placeholder="Add a note to travel with the gift"></textarea><em data-send-message-count>0/500</em>' +
      '</div>' +
      '<div class="mg-send-confirm" data-regift-confirm hidden><strong>Confirm gift transfer</strong><br>Review the selected recipient before sending.</div>' +
      '<p class="mg-form-status" data-regift-status aria-live="polite"></p>' +
      '<div class="mg-send-exact-actions"><button class="mg-send-exact-primary" type="submit">Review Send</button></div>' +
    '</form>';
    openModal();
    var input = modalBody.querySelector('input[name="recipient"]');
    if (input) input.focus();
  }

  function status(form, message, type) {
    var node = form.querySelector('[data-regift-status]');
    if (!node) return;
    node.textContent = message || '';
    node.setAttribute('data-status-type', type || '');
  }

  function resetConfirmation(form) {
    confirmed = false;
    confirmKey = '';
    confirmIdempotencyKey = '';
    var box = form && form.querySelector('[data-regift-confirm]');
    if (box) box.hidden = true;
    var button = form && form.querySelector('button[type="submit"]');
    if (button) button.textContent = 'Review Send';
  }

  function selectedRecipient(form) {
    var ref = text((form.querySelector('input[name="recipient_user_id"]') || {}).value || (form.querySelector('input[name="recipient_profile_id"]') || {}).value || (form.querySelector('input[name="recipient_slug"]') || {}).value);
    return {
      ref: ref,
      slug: text((form.querySelector('input[name="recipient_slug"]') || {}).value),
      label: text(form.dataset.recipientLabel || (form.querySelector('input[name="recipient"]') || {}).value || 'this recipient'),
      selected: !!ref && !!form.dataset.recipientProfileId
    };
  }

  async function submitSend(event) {
    var form = event.target.closest('[data-action-form="send"]');
    if (!form) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();

    var recipient = selectedRecipient(form);
    var giftId = text((form.querySelector('input[name="action_item_id"]') || {}).value);
    var message = text((form.querySelector('textarea[name="message"]') || {}).value);
    var button = form.querySelector('button[type="submit"]');
    if (!giftId) return status(form, 'This gift is missing its Action Center item id.', 'error');
    if (!recipient.ref || !recipient.selected) return status(form, 'Search for a recipient and tap a result before sending.', 'error');

    var key = giftId + '|' + recipient.ref + '|' + message;
    if (!confirmed || confirmKey !== key) {
      confirmed = true;
      confirmKey = key;
      confirmIdempotencyKey = 'agent-regift:' + giftId + ':' + recipient.ref + ':' + Date.now();
      var box = form.querySelector('[data-regift-confirm]');
      if (box) box.hidden = false;
      if (button) button.textContent = 'Yes, Send Gift';
      return status(form, 'Review the recipient, then click Yes, Send Gift to confirm.', '');
    }

    if (button) { button.disabled = true; button.textContent = 'Sending…'; }
    status(form, 'Sending Microgift…', '');
    try {
      var response = await Microgifter.post('/api/account/action-center-send.php', {
        action_item_id: giftId,
        recipient_user_id: recipient.ref,
        recipient_slug: recipient.slug,
        recipient: recipient.ref,
        message: message,
        idempotency_key: confirmIdempotencyKey
      });
      var payload = typeof dataOf === 'function' ? dataOf(response) : (response && response.data ? response.data : response || {});
      if (activeCard) {
        activeCard.can_send = false;
        activeCard.folder = 'sent';
        activeCard.eyebrow = 'Sent Microgift';
        activeCard.state = 'sent';
        activeCard.meta = (Array.isArray(activeCard.meta) ? activeCard.meta : []).filter(function (item) { return item && item.label !== 'Status'; });
        activeCard.meta.push({ label: 'Status', value: 'Sent' });
        activeCard.meta.push({ label: 'To', value: recipient.label });
      }
      if (activeGrid && Array.isArray(activeGrid._agentCards) && activeIndex >= 0) activeGrid._agentCards[activeIndex] = activeCard;
      if (activeArticle && activeCard) renderGiftCard(activeArticle, activeCard, activeIndex);
      if (Microgifter.toast) Microgifter.toast('Microgift sent to ' + recipient.label + '.');
      if (typeof setStatus === 'function') setStatus('Microgift sent successfully. The recipient should now see it in their Inbox.', 'success');
      modalBody.innerHTML = '<div class="mg-action-success"><strong>Gift sent successfully</strong>' +
        '<p>The Microgift was sent to ' + esc(recipient.label) + ' and should now appear in their Inbox.</p>' +
        '<button class="mg-btn mg-btn-primary" type="button" data-personal-agent-gift-send-close>Done</button></div>';
      document.dispatchEvent(new CustomEvent('mg:personal-agent:gift-sent', { detail: { gift_id: giftId, recipient: recipient, response: payload } }));
    } catch (error) {
      status(form, error && error.message ? error.message : 'Unable to send this Microgift.', 'error');
      resetConfirmation(form);
    } finally {
      if (button && document.body.contains(button)) button.disabled = false;
    }
  }

  async function currentInboxGift(giftId) {
    try {
      var response = await Microgifter.get('/api/account/action-center.php?folder=inbox&limit=100');
      var payload = typeof dataOf === 'function' ? dataOf(response) : (response && response.data ? response.data : response || {});
      var items = Array.isArray(payload.items) ? payload.items : [];
      return items.find(function (item) { return text(item && item.action_item_id) === giftId; }) || null;
    } catch (error) {
      return null;
    }
  }

  function showUnavailable(card) {
    ensureModal();
    modalEyebrow.textContent = card && card.eyebrow ? card.eyebrow : 'Microgift';
    modalTitle.textContent = 'Gift unavailable';
    modalBody.innerHTML = '<div class="mg-action-success"><strong>This gift is no longer transferable</strong>' +
      '<p>It may have already been sent, claimed, redeemed, expired, or removed from your Inbox. Reload the chat or open the Action Center for its latest state.</p>' +
      '<button class="mg-btn mg-btn-primary" type="button" data-personal-agent-gift-send-close>Done</button></div>';
    openModal();
  }

  root.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-agent-gift-send]');
    if (!trigger) return;
    event.preventDefault();
    event.stopPropagation();

    var article = trigger.closest('[data-agent-gift-card-index]');
    var grid = trigger.closest('.mg-personal-agent-card-grid');
    var cards = grid && Array.isArray(grid._agentCards) ? grid._agentCards : [];
    var index = article ? Number(article.getAttribute('data-agent-gift-card-index')) : -1;
    var card = cards[index];
    if (!accountGift(card) || !card.can_send) return;

    trigger.disabled = true;
    trigger.textContent = 'Checking…';
    currentInboxGift(text(card.action_item_id || card.id)).then(function (item) {
      trigger.disabled = false;
      trigger.textContent = card.send_label || 'Send';
      if (!item) {
        card.can_send = false;
        renderGiftCard(article, card, index);
        showUnavailable(card);
        return;
      }
      activeCard = card;
      activeArticle = article;
      activeGrid = grid;
      activeIndex = index;
      buildSendForm(card);
    });
  }, true);

  feed.querySelectorAll('.mg-personal-agent-card-grid').forEach(enhanceGrid);
  if (window.MutationObserver) {
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        Array.prototype.forEach.call(mutation.addedNodes || [], scan);
      });
    }).observe(feed, { childList: true, subtree: true });
  }
});

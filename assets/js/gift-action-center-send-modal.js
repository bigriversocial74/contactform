document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app) return;

  var modal = app.querySelector('[data-action-modal]');
  var modalBody = app.querySelector('[data-action-modal-body]');
  var modalTitle = app.querySelector('[data-action-modal-title]');
  var modalEyebrow = app.querySelector('[data-action-modal-eyebrow]');
  if (!modal || !modalBody) return;

  var searchController = null;
  var searchTimer = null;
  var regiftConfirmed = false;
  var regiftRecipientKey = '';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function toast(message) {
    if (window.Microgifter && Microgifter.toast) Microgifter.toast(message);
  }

  function busy(button, on, text) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = !!on;
    button.textContent = on ? (text || 'Working…') : button.dataset.originalText;
  }

  function firstLetter(value) {
    var text = String(value || 'Microgift').trim();
    return (text.charAt(0) || 'M').toUpperCase();
  }

  function rowMerchant(row) {
    var fallback = 'Microgifter';
    if (!row) return fallback;
    var meta = Array.from(row.querySelectorAll('.mg-gift-row-meta span')).map(function (node) {
      return node.textContent.trim();
    });
    var from = meta.find(function (value) { return /^From:/i.test(value); });
    if (from) return from.replace(/^From:\s*/i, '').trim() || fallback;
    var merchant = meta.find(function (value) { return /^Merchant:/i.test(value); });
    if (merchant) return merchant.replace(/^Merchant:\s*/i, '').trim() || fallback;
    return fallback;
  }

  function thumbMarkup(row, title) {
    var thumb = row ? row.querySelector('.mg-gift-thumb') : null;
    var image = thumb ? thumb.querySelector('img') : null;
    if (image && image.getAttribute('src')) {
      return '<img src="' + esc(image.getAttribute('src')) + '" alt="' + esc(title) + ' product image">';
    }
    return '<span>' + esc(firstLetter(title)) + '</span>';
  }

  function recipientAvatarMarkup(profile) {
    var name = profile.display_name || profile.name || profile.slug || 'User';
    var avatar = profile.avatar_url || profile.avatar || profile.profile_image_url || '';
    if (avatar) return '<img src="' + esc(avatar) + '" alt="" loading="lazy">';
    return '<span>' + esc(firstLetter(name)) + '</span>';
  }

  function resultMarkup(profile, index) {
    var name = profile.display_name || profile.name || profile.slug || 'Microgifter user';
    var handle = profile.slug ? '@' + profile.slug : 'Microgifter user';
    var headline = profile.headline || profile.location || profile.profile_type || 'Searchable Microgifter profile';
    var type = profile.profile_type ? String(profile.profile_type).replace(/_/g, ' ') : 'profile';
    var location = profile.location ? ' · ' + profile.location : '';
    var meta = handle + ' · ' + type + location;
    return '<button class="mg-send-result" type="button" role="option" data-recipient-index="' + String(index) + '">' +
      '<span class="mg-send-result-avatar">' + recipientAvatarMarkup(profile) + '</span>' +
      '<span class="mg-send-result-main"><strong>' + esc(name) + '</strong><em>' + esc(headline) + '</em><small>' + esc(meta) + '</small></span>' +
      '</button>';
  }

  function setStatus(form, message, kind) {
    var status = form.querySelector('[data-regift-status]');
    if (!status) return;
    status.textContent = message || '';
    status.dataset.statusType = kind || '';
  }

  function resetConfirm(form) {
    regiftConfirmed = false;
    regiftRecipientKey = '';
    var confirm = form && form.querySelector('[data-regift-confirm]');
    if (confirm) confirm.hidden = true;
    var button = form && form.querySelector('[data-regift-submit]');
    if (button) {
      button.dataset.originalText = 'Review Regift';
      button.textContent = 'Review Regift';
    }
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

  function selectRecipient(form, profile) {
    var input = form.querySelector('input[name="recipient"]');
    var id = form.querySelector('input[name="recipient_profile_id"]');
    var slug = form.querySelector('input[name="recipient_slug"]');
    var selected = form.querySelector('[data-selected-recipient]');
    var name = profile.display_name || profile.name || profile.slug || '';
    if (input) input.value = name;
    if (id) id.value = profile.id || profile.public_id || '';
    if (slug) slug.value = profile.slug || '';
    if (selected) {
      selected.hidden = false;
      selected.innerHTML = '<span class="mg-send-selected-avatar">' + recipientAvatarMarkup(profile) + '</span>' +
        '<span><strong>' + esc(name || 'Selected user') + '</strong><em>' + esc(profile.slug ? '@' + profile.slug : (profile.profile_type || 'profile')) + '</em></span>' +
        '<button type="button" data-clear-recipient aria-label="Clear selected recipient">×</button>';
    }
    resetConfirm(form);
    clearResults(form);
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
      var url = '/api/public/discover.php?q=' + encodeURIComponent(query.trim()) + '&limit=8&sort=trending';
      var response = await fetch(url, { headers: { 'Accept': 'application/json' }, signal: searchController.signal });
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

  function wireRecipientSearch(form) {
    var input = form.querySelector('input[name="recipient"]');
    var results = form.querySelector('[data-send-recipient-results]');
    var selected = form.querySelector('[data-selected-recipient]');
    var id = form.querySelector('input[name="recipient_profile_id"]');
    var slug = form.querySelector('input[name="recipient_slug"]');
    if (!input || !results) return;

    input.addEventListener('input', function () {
      if (id) id.value = '';
      if (slug) slug.value = '';
      if (selected) {
        selected.hidden = true;
        selected.innerHTML = '';
      }
      resetConfirm(form);
      if (searchTimer) window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(function () { searchRecipients(form, input.value); }, 180);
    });

    input.addEventListener('focus', function () {
      if (input.value.trim()) searchRecipients(form, input.value);
    });

    results.addEventListener('click', function (event) {
      var button = event.target.closest('[data-recipient-index]');
      if (!button) return;
      var items = results.__items || [];
      var profile = items[Number(button.dataset.recipientIndex)];
      if (profile) selectRecipient(form, profile);
    });

    if (selected) {
      selected.addEventListener('click', function (event) {
        if (!event.target.closest('[data-clear-recipient]')) return;
        selected.hidden = true;
        selected.innerHTML = '';
        if (id) id.value = '';
        if (slug) slug.value = '';
        input.value = '';
        resetConfirm(form);
        input.focus();
      });
    }
  }

  function buildExactSendModal(row) {
    var title = row && row.querySelector('.mg-gift-row-main h3') ? row.querySelector('.mg-gift-row-main h3').textContent.trim() : 'Microgift';
    var merchant = rowMerchant(row);
    var recipientId = 'mg-send-recipient-' + Date.now();
    var resultsId = recipientId + '-results';
    var actionItemId = row ? (row.getAttribute('data-gift-id') || '') : '';

    modal.classList.add('mg-send-product-modal');
    modal.classList.add('mg-send-exact-modal');
    if (modalTitle) modalTitle.textContent = '';
    if (modalEyebrow) modalEyebrow.textContent = '';

    modalBody.innerHTML = '<form class="mg-send-exact-form" data-action-form="send" data-exact-regift-form>' +
      '<input type="hidden" name="action_item_id" value="' + esc(actionItemId) + '">' +
      '<section class="mg-send-exact-product" aria-label="Selected gift">' +
        '<div class="mg-send-exact-thumb">' + thumbMarkup(row, title) + '</div>' +
        '<div class="mg-send-exact-product-copy"><h2>' + esc(title) + '</h2><p>' + esc(merchant) + '</p></div>' +
      '</section>' +
      '<div class="mg-send-exact-field mg-send-exact-recipient">' +
        '<label for="' + recipientId + '">Regift to</label>' +
        '<div class="mg-send-exact-input-shell">' +
          '<span class="mg-send-exact-search" aria-hidden="true"></span>' +
          '<input id="' + recipientId + '" type="text" name="recipient" required autocomplete="off" placeholder="Search any Microgifter user" aria-expanded="false" aria-controls="' + resultsId + '">' +
          '<input type="hidden" name="recipient_profile_id">' +
          '<input type="hidden" name="recipient_slug">' +
        '</div>' +
        '<div class="mg-send-selected" data-selected-recipient hidden></div>' +
        '<div class="mg-send-results" id="' + resultsId + '" data-send-recipient-results role="listbox" hidden></div>' +
        '<small>Search public Microgifter profiles. Tap a result to choose the recipient.</small>' +
      '</div>' +
      '<div class="mg-send-exact-field mg-send-exact-message">' +
        '<label>Message</label>' +
        '<textarea name="message" maxlength="500" placeholder="Add a note to travel with the gift"></textarea>' +
        '<em data-send-message-count>0/500</em>' +
      '</div>' +
      '<div class="mg-send-confirm" data-regift-confirm hidden><strong>Confirm regift</strong><p data-regift-confirm-text></p></div>' +
      '<p class="mg-form-status" data-regift-status></p>' +
      '<div class="mg-send-exact-actions"><button class="mg-send-exact-primary" type="submit" data-regift-submit>Review Regift</button></div>' +
    '</form>';

    var form = modalBody.querySelector('.mg-send-exact-form');
    var textarea = modalBody.querySelector('textarea[name="message"]');
    var counter = modalBody.querySelector('[data-send-message-count]');
    if (textarea && counter) {
      textarea.addEventListener('input', function () {
        counter.textContent = String(textarea.value.length) + '/500';
        if (form) resetConfirm(form);
      });
    }
    if (form) wireRecipientSearch(form);
  }

  async function submitRegift(form) {
    var actionItemId = String((form.elements.action_item_id || {}).value || '').trim();
    var recipientId = String((form.elements.recipient_profile_id || {}).value || '').trim();
    var slug = String((form.elements.recipient_slug || {}).value || '').trim();
    var recipientTyped = String((form.elements.recipient || {}).value || '').trim();
    var recipient = recipientId || slug || recipientTyped;
    var message = String((form.elements.message || {}).value || '').trim();
    var recipientLabel = recipientTyped || slug || 'this recipient';
    var key = recipient + '|' + message;
    var confirmBox = form.querySelector('[data-regift-confirm]');
    var confirmText = form.querySelector('[data-regift-confirm-text]');
    var submit = form.querySelector('[data-regift-submit]');

    if (!actionItemId) {
      setStatus(form, 'This gift is missing an Action Center item id.', 'error');
      return;
    }
    if (!recipient) {
      setStatus(form, 'Choose a recipient from the search results before sending.', 'error');
      return;
    }
    if (!recipientId && !slug) {
      setStatus(form, 'Tap a search result to confirm the recipient before sending.', 'error');
      return;
    }

    if (!regiftConfirmed || regiftRecipientKey !== key) {
      regiftConfirmed = true;
      regiftRecipientKey = key;
      if (confirmText) confirmText.textContent = 'Send this Microgift to ' + recipientLabel + '? This transfers gift ownership to the recipient.';
      if (confirmBox) confirmBox.hidden = false;
      if (submit) submit.textContent = 'Confirm & Regift';
      setStatus(form, 'Review and click Confirm & Regift to send.', '');
      return;
    }

    busy(submit, true, 'Sending…');
    setStatus(form, 'Sending Microgift…', '');
    try {
      var payload = {
        action_item_id: actionItemId,
        recipient_user_id: recipient,
        recipient_slug: slug,
        message: message,
        idempotency_key: 'regift:' + actionItemId + ':' + recipient + ':' + Date.now()
      };
      var response = await Microgifter.post('/api/account/action-center-send.php', payload);
      var data = response.data || response;
      toast('Microgift regifted.');
      modalBody.innerHTML = '<div class="mg-action-success"><strong>Microgift regifted</strong>' +
        '<p>The gift was sent and ownership was transferred to ' + esc(recipientLabel) + '.</p>' +
        '<button class="mg-btn mg-btn-primary" type="button" data-action-modal-close>Done</button></div>';
      var refresh = app.querySelector('[data-gift-refresh]');
      if (refresh) setTimeout(function () { refresh.click(); }, 350);
    } catch (error) {
      setStatus(form, error && error.message ? error.message : 'Unable to regift this Microgift.', 'error');
      resetConfirm(form);
    } finally {
      busy(submit, false);
    }
  }

  app.addEventListener('click', function (event) {
    var action = event.target.closest('[data-gift-action]');
    if (!action) return;
    if (action.dataset.giftAction !== 'send') {
      modal.classList.remove('mg-send-product-modal');
      modal.classList.remove('mg-send-exact-modal');
      return;
    }
    var row = action.closest('[data-gift-id]');
    regiftConfirmed = false;
    regiftRecipientKey = '';
    window.requestAnimationFrame(function () { buildExactSendModal(row); });
  });

  modalBody.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-exact-regift-form]');
    if (!form) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    submitRegift(form);
  }, true);
});

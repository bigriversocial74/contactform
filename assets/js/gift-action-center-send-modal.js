document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app) return;

  var list = app.querySelector('[data-gift-list]');
  var overlay = null;
  var activeRow = null;
  var selectedRecipient = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function key(type, id) {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return 'ac-' + type + '-' + window.crypto.randomUUID();
    return 'ac-' + type + '-' + String(id || 'item') + '-' + Date.now();
  }

  function firstLetter(value) {
    var text = String(value || 'Microgift').trim();
    return (text.charAt(0) || 'M').toUpperCase();
  }

  function rowTitle(row) {
    var title = row && row.querySelector('.mg-gift-row-main h3');
    return title ? title.textContent.trim() : 'Microgift';
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
    var image = row && row.querySelector('.mg-gift-thumb img');
    if (image && image.getAttribute('src')) {
      return '<img src="' + esc(image.getAttribute('src')) + '" alt="' + esc(title) + ' product image">';
    }
    return '<span>' + esc(firstLetter(title)) + '</span>';
  }

  function closeSendModal() {
    if (!overlay) return;
    overlay.remove();
    overlay = null;
    activeRow = null;
    selectedRecipient = null;
    document.body.classList.remove('mg-send-modal-lock');
  }

  function showStatus(title, message, isError) {
    if (!overlay) return;
    var body = overlay.querySelector('[data-send-modal-body]');
    if (!body) return;
    body.innerHTML = '<div class="mg-send-result ' + (isError ? 'is-error' : 'is-ok') + '">' +
      '<strong>' + esc(title) + '</strong>' +
      '<p>' + esc(message) + '</p>' +
      '<button type="button" data-send-close>Done</button>' +
    '</div>';
  }

  function renderRecipientResults(items) {
    var results = overlay && overlay.querySelector('[data-send-recipient-results]');
    if (!results) return;
    if (!items.length) {
      results.innerHTML = '<div class="mg-send-recipient-empty">No matching followers or users.</div>';
      return;
    }
    results.innerHTML = items.map(function (item) {
      return '<button type="button" data-send-recipient-option data-recipient-id="' + esc(item.recipient_user_id) + '" data-recipient-label="' + esc(item.display_name) + '">' +
        '<strong>' + esc(item.display_name) + '</strong>' +
        '<span>' + esc(item.email_hint || item.source || 'Microgifter member') + '</span>' +
      '</button>';
    }).join('');
  }

  function searchRecipients(input) {
    var results = overlay && overlay.querySelector('[data-send-recipient-results]');
    if (!results) return;
    selectedRecipient = null;
    var query = input.value.trim();
    if (query.length < 2) {
      results.innerHTML = '<div class="mg-send-recipient-empty">Start typing to find followers and users.</div>';
      return;
    }
    if (!window.Microgifter) {
      results.innerHTML = '<div class="mg-send-recipient-empty">Recipient search is unavailable.</div>';
      return;
    }
    results.innerHTML = '<div class="mg-send-recipient-empty">Searching…</div>';
    Microgifter.get('/api/account/action-center-recipient-search.php?q=' + encodeURIComponent(query))
      .then(function (response) {
        var data = response.data || response;
        renderRecipientResults(data.recipients || []);
      })
      .catch(function () {
        results.innerHTML = '<div class="mg-send-recipient-empty">Unable to search recipients.</div>';
      });
  }

  function openSendModal(row) {
    closeSendModal();
    activeRow = row;
    selectedRecipient = null;

    var title = rowTitle(row);
    var merchant = rowMerchant(row);

    overlay = document.createElement('div');
    overlay.className = 'mg-send-modal-overlay';
    overlay.innerHTML = '<div class="mg-send-modal-backdrop" data-send-close></div>' +
      '<section class="mg-send-modal-card" role="dialog" aria-modal="true" aria-label="Regift Microgift">' +
        '<button class="mg-send-modal-close" type="button" data-send-close aria-label="Close form">×</button>' +
        '<div class="mg-send-modal-body" data-send-modal-body>' +
          '<form class="mg-send-modal-form" data-send-form>' +
            '<section class="mg-send-product-row" aria-label="Selected gift">' +
              '<div class="mg-send-product-art">' + thumbMarkup(row, title) + '</div>' +
              '<div class="mg-send-product-text"><h2>' + esc(title) + '</h2><p>' + esc(merchant) + '</p></div>' +
            '</section>' +
            '<label class="mg-send-field mg-send-to-field"><span>Regift to</span>' +
              '<div class="mg-send-input-wrap"><span aria-hidden="true"></span><input type="search" name="recipient" autocomplete="off" placeholder="Start typing a follower or user" required></div>' +
              '<small>Start typing to find followers and users.</small>' +
              '<div class="mg-send-recipient-results" data-send-recipient-results><div class="mg-send-recipient-empty">Start typing to find followers and users.</div></div>' +
            '</label>' +
            '<label class="mg-send-field mg-send-message-field"><span>Message</span>' +
              '<textarea name="message" maxlength="500" placeholder="Add a note to travel with the gift"></textarea>' +
              '<em data-send-count>0/500</em>' +
            '</label>' +
            '<div class="mg-send-actions"><button type="button" class="mg-send-cancel" data-send-close>Cancel</button><button type="submit" class="mg-send-primary">Regift Microgift</button></div>' +
          '</form>' +
        '</div>' +
      '</section>';
    document.body.appendChild(overlay);
    document.body.classList.add('mg-send-modal-lock');

    var input = overlay.querySelector('input[name="recipient"]');
    var textarea = overlay.querySelector('textarea[name="message"]');
    var counter = overlay.querySelector('[data-send-count]');
    if (textarea && counter) {
      textarea.addEventListener('input', function () { counter.textContent = String(textarea.value.length) + '/500'; });
    }
    if (input) {
      input.addEventListener('input', function () {
        clearTimeout(input._sendRecipientTimer);
        input._sendRecipientTimer = setTimeout(function () { searchRecipients(input); }, 180);
      });
      window.setTimeout(function () { input.focus(); }, 80);
    }
  }

  app.addEventListener('click', function (event) {
    var action = event.target.closest('[data-gift-action="send"]');
    if (!action) return;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    openSendModal(action.closest('[data-gift-id]'));
  }, true);

  document.addEventListener('click', function (event) {
    if (!overlay) return;
    var close = event.target.closest('[data-send-close]');
    if (close) {
      event.preventDefault();
      closeSendModal();
      return;
    }
    var option = event.target.closest('[data-send-recipient-option]');
    if (option && overlay.contains(option)) {
      var input = overlay.querySelector('input[name="recipient"]');
      selectedRecipient = { id: option.dataset.recipientId || '', label: option.dataset.recipientLabel || '' };
      if (input) input.value = selectedRecipient.label;
      var results = overlay.querySelector('[data-send-recipient-results]');
      if (results) results.innerHTML = '<div class="mg-send-recipient-selected">Selected: ' + esc(selectedRecipient.label || 'recipient') + '</div>';
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-send-form]');
    if (!form || !overlay || !overlay.contains(form)) return;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    if (activeRow && activeRow.classList.contains('is-demo')) {
      showStatus('Demo preview only', 'No real payment, ownership transfer, regift, notification, ledger entry, payout, or webhook was created.', false);
      return;
    }

    var actionItemId = activeRow && activeRow.dataset ? activeRow.dataset.giftId : '';
    var message = form.querySelector('textarea[name="message"]') ? form.querySelector('textarea[name="message"]').value : '';
    if (!selectedRecipient || !selectedRecipient.id) {
      showStatus('Select a recipient', 'Start typing and choose a follower or user from the recipient list.', true);
      return;
    }
    if (!window.Microgifter) {
      showStatus('Action unavailable', 'Microgifter API helpers are not loaded on this page.', true);
      return;
    }

    showStatus('Processing regift…', 'Please keep this window open.', false);
    Microgifter.post('/api/account/action-center-send.php', {
      action_item_id: actionItemId,
      idempotency_key: key('send', actionItemId),
      recipient_user_id: selectedRecipient.id,
      recipient: selectedRecipient.id,
      message: message
    }).then(function (response) {
      var responseData = response && response.data ? response.data : response;
      var timestamp = responseData && responseData.delivery_event && responseData.delivery_event.occurred_at ? responseData.delivery_event.occurred_at : '';
      var detail = (response && response.message) || 'The Action Center has been updated.';
      if (timestamp) detail += ' Recorded at ' + new Date(timestamp).toLocaleString() + '.';
      showStatus('Regift complete', detail, false);
      var refresh = app.querySelector('[data-gift-refresh]');
      if (refresh) refresh.click();
    }).catch(function (error) {
      showStatus('Action failed', (error && error.message) || 'Unable to complete this action.', true);
    });
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeSendModal();
  });
});

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app) return;

  var modal = app.querySelector('[data-action-modal]');
  var modalBody = app.querySelector('[data-action-modal-body]');
  var modalTitle = app.querySelector('[data-action-modal-title]');
  var modalEyebrow = app.querySelector('[data-action-modal-eyebrow]');
  if (!modal || !modalBody) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
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

  function buildExactSendModal(row) {
    var title = row && row.querySelector('.mg-gift-row-main h3') ? row.querySelector('.mg-gift-row-main h3').textContent.trim() : 'Microgift';
    var merchant = rowMerchant(row);

    modal.classList.add('mg-send-product-modal');
    modal.classList.add('mg-send-exact-modal');
    if (modalTitle) modalTitle.textContent = '';
    if (modalEyebrow) modalEyebrow.textContent = '';

    modalBody.innerHTML = '<form class="mg-send-exact-form" data-action-form="send">' +
      '<section class="mg-send-exact-product" aria-label="Selected gift">' +
        '<div class="mg-send-exact-thumb">' + thumbMarkup(row, title) + '</div>' +
        '<div class="mg-send-exact-product-copy">' +
          '<h2>' + esc(title) + '</h2>' +
          '<p>' + esc(merchant) + '</p>' +
        '</div>' +
      '</section>' +
      '<label class="mg-send-exact-field mg-send-exact-recipient">' +
        '<span>Regift to</span>' +
        '<div class="mg-send-exact-input-shell">' +
          '<span class="mg-send-exact-search" aria-hidden="true"></span>' +
          '<input type="text" name="recipient" required autocomplete="off" placeholder="Start typing a follower or user">' +
        '</div>' +
        '<small>Start typing to find followers and users.</small>' +
      '</label>' +
      '<label class="mg-send-exact-field mg-send-exact-message">' +
        '<span>Message</span>' +
        '<textarea name="message" maxlength="500" placeholder="Add a note to travel with the gift"></textarea>' +
        '<em data-send-message-count>0/500</em>' +
      '</label>' +
      '<div class="mg-send-exact-actions">' +
        '<button class="mg-send-exact-cancel" type="button" data-action-modal-close>Cancel</button>' +
        '<button class="mg-send-exact-primary" type="submit">Regift Microgift</button>' +
      '</div>' +
    '</form>';

    var textarea = modalBody.querySelector('textarea[name="message"]');
    var counter = modalBody.querySelector('[data-send-message-count]');
    if (textarea && counter) {
      textarea.addEventListener('input', function () {
        counter.textContent = String(textarea.value.length) + '/500';
      });
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
    window.requestAnimationFrame(function () { buildExactSendModal(row); });
  });
});

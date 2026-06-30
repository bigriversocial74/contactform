document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app) return;

  var modal = app.querySelector('[data-action-modal]');
  var modalTitle = app.querySelector('[data-action-modal-title]');
  var modalEyebrow = app.querySelector('[data-action-modal-eyebrow]');
  if (!modal) return;

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

  function updateLabelText(label, value) {
    if (!label) return;
    for (var i = 0; i < label.childNodes.length; i += 1) {
      if (label.childNodes[i].nodeType === Node.TEXT_NODE && label.childNodes[i].nodeValue.trim()) {
        label.childNodes[i].nodeValue = value;
        return;
      }
    }
    label.insertBefore(document.createTextNode(value), label.firstChild);
  }

  function polishSendModal(row) {
    var form = modal.querySelector('.mg-action-form[data-action-form="send"]');
    if (!form) return;

    modal.classList.add('mg-send-product-modal');
    if (modalTitle) modalTitle.textContent = 'Regift Microgift';
    if (modalEyebrow) modalEyebrow.textContent = '';

    form.querySelectorAll('.mg-action-form-note').forEach(function (note) { note.remove(); });

    var title = row && row.querySelector('.mg-gift-row-main h3') ? row.querySelector('.mg-gift-row-main h3').textContent.trim() : 'Microgift';
    var merchant = rowMerchant(row);

    if (!form.querySelector('[data-send-product-hero]')) {
      form.insertAdjacentHTML('afterbegin',
        '<section class="mg-send-product-hero" data-send-product-hero>' +
          '<div class="mg-send-product-thumb">' + thumbMarkup(row, title) + '</div>' +
          '<div class="mg-send-product-copy">' +
            '<h3>' + esc(title) + '</h3>' +
            '<p>' + esc(merchant) + '</p>' +
          '</div>' +
        '</section>'
      );
    }

    var labels = form.querySelectorAll('label');
    var recipientLabel = labels[0] || null;
    var messageLabel = labels[1] || null;
    var recipient = recipientLabel ? recipientLabel.querySelector('input') : null;
    var message = messageLabel ? messageLabel.querySelector('textarea') : null;

    if (recipientLabel) {
      recipientLabel.classList.add('mg-send-recipient-field');
      updateLabelText(recipientLabel, 'Regift to');
      if (!recipientLabel.querySelector('.mg-send-recipient-helper')) {
        recipientLabel.insertAdjacentHTML('afterend', '<p class="mg-send-recipient-helper">Start typing to find followers and users.</p>');
      }
    }
    if (recipient) {
      recipient.placeholder = 'Start typing a follower or user';
      recipient.setAttribute('autocomplete', 'off');
    }

    if (messageLabel) {
      messageLabel.classList.add('mg-send-message-field');
      updateLabelText(messageLabel, 'Message');
    }
    if (message) {
      message.placeholder = 'Add a note to travel with the gift';
      message.maxLength = 500;
      if (!messageLabel.querySelector('.mg-send-char-count')) {
        messageLabel.insertAdjacentHTML('beforeend', '<span class="mg-send-char-count">0/500</span>');
      }
      var counter = messageLabel.querySelector('.mg-send-char-count');
      var update = function () { counter.textContent = String(message.value.length) + '/500'; };
      message.addEventListener('input', update);
      update();
    }
  }

  app.addEventListener('click', function (event) {
    var action = event.target.closest('[data-gift-action]');
    if (!action) return;
    if (action.dataset.giftAction !== 'send') {
      modal.classList.remove('mg-send-product-modal');
      return;
    }
    var row = action.closest('[data-gift-id]');
    window.requestAnimationFrame(function () { polishSendModal(row); });
  });
});

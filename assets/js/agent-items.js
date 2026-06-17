document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-agent-items-app]');
  var modal = document.querySelector('[data-item-modal]');
  if (!app || !modal || !window.Microgifter) return;

  var box = app.dataset.listMode || 'inbox';
  var listBody = app.querySelector('.mg-agent-list-body');
  var preview = app.querySelector('[data-gift-preview]');
  var previewBody = preview && preview.querySelector('[data-preview-body]');
  var modalBody = modal.querySelector('[data-modal-body]');
  var modalTitle = modal.querySelector('[data-modal-title]');
  var modalEyebrow = modal.querySelector('[data-modal-eyebrow]');
  var gifts = [];
  var currentIndex = 0;
  var lastTrigger = null;
  var wheelLocked = false;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function actionsForBox() {
    if (box === 'inbox') return [['send', 'Send'], ['claim', 'Claim'], ['load', 'Load']];
    if (box === 'sent') return [['message', 'Message'], ['load', 'Load']];
    return [['message', 'Message'], ['tip', 'Tip'], ['load', 'Load']];
  }

  function renderList() {
    if (!listBody) return;
    if (!gifts.length) {
      listBody.innerHTML = '<div class="mg-agent-list-empty"><strong>No ' + escapeHtml(box) + ' gifts yet</strong><p>Gift activity will appear here when records are available.</p></div>';
      return;
    }

    listBody.innerHTML = gifts.map(function (gift) {
      var actions = actionsForBox().map(function (action) {
        return '<button type="button" data-item-action="' + action[0] + '">' + action[1] + '</button>';
      }).join('');
      var source = gift.pppm_id ? 'pppm' : 'legacy';
      return '<article class="mg-gift-item" data-gift-item data-item-source="' + source + '" data-item-id="' + escapeHtml(gift.id) + '">' +
        '<img class="mg-gift-item-avatar" src="' + escapeHtml(gift.avatar) + '" alt="' + escapeHtml(gift.sent_from) + ' profile picture">' +
        '<div class="mg-gift-item-copy"><div class="mg-gift-item-heading"><div><strong>' + escapeHtml(gift.title) + '</strong><span class="mg-gift-item-id">Product ID: ' + escapeHtml(gift.id) + '</span></div><time>' + escapeHtml(gift.time_label) + '</time></div>' +
        '<p>' + escapeHtml(gift.description) + '</p><dl class="mg-gift-item-meta">' +
        '<div><dt>Sent from</dt><dd>' + escapeHtml(gift.sent_from) + '</dd></div>' +
        '<div><dt>Timestamp</dt><dd>' + escapeHtml(gift.time_label) + '</dd></div>' +
        '<div><dt>Gift type</dt><dd>' + escapeHtml(gift.gift_type) + '</dd></div>' +
        '<div><dt>Value</dt><dd>' + escapeHtml(gift.value) + '</dd></div></dl></div>' +
        '<div class="mg-gift-item-actions" aria-label="Item actions">' + actions + '</div></article>';
    }).join('');
  }

  async function loadActivity() {
    if (listBody) listBody.innerHTML = '<div class="mg-agent-list-empty"><strong>Loading activity…</strong></div>';
    try {
      var response = await Microgifter.get('/api/gifts/list.php?box=' + encodeURIComponent(box));
      gifts = response.data && Array.isArray(response.data.gifts) ? response.data.gifts : [];
      renderList();
    } catch (error) {
      if (listBody) listBody.innerHTML = '<div class="mg-agent-list-empty"><strong>Unable to load activity</strong><p>' + escapeHtml(error.message || 'Try again shortly.') + '</p></div>';
    }
  }

  function setPreviewText(selector, value) {
    var node = preview && preview.querySelector(selector);
    if (node) node.textContent = value || '';
  }

  async function renderPreview(index, direction) {
    if (!preview || !gifts.length) return;
    currentIndex = Math.max(0, Math.min(gifts.length - 1, index));
    var gift = gifts[currentIndex];

    if (previewBody && direction) {
      previewBody.classList.remove('is-entering-up', 'is-entering-down');
      void previewBody.offsetWidth;
      previewBody.classList.add(direction === 'next' ? 'is-entering-up' : 'is-entering-down');
    }

    try {
      var response = await Microgifter.get('/api/gifts/item.php?id=' + encodeURIComponent(gift.id));
      gift = response.data.gift;
      gifts[currentIndex] = gift;
    } catch (error) {
      setPreviewText('[data-preview-description]', error.message || 'Unable to load gift details.');
    }

    setPreviewText('[data-preview-id]', gift.id);
    setPreviewText('[data-preview-title]', gift.title);
    setPreviewText('[data-preview-description]', gift.description);
    setPreviewText('[data-preview-sent-from]', gift.sent_from);
    setPreviewText('[data-preview-recipient]', gift.recipient);
    setPreviewText('[data-preview-time]', gift.time_label);
    setPreviewText('[data-preview-type]', gift.gift_type);
    setPreviewText('[data-preview-value]', gift.value);
    setPreviewText('[data-preview-card-title]', gift.title);
    setPreviewText('[data-preview-card-value]', gift.value);
    setPreviewText('[data-preview-counter]', (currentIndex + 1) + ' / ' + gifts.length);
    var image = preview.querySelector('[data-preview-avatar]');
    if (image) image.src = gift.avatar || '/assets/images/default-avatar.svg';
    var previous = preview.querySelector('[data-preview-prev]');
    var next = preview.querySelector('[data-preview-next]');
    if (previous) previous.disabled = currentIndex === 0;
    if (next) next.disabled = currentIndex === gifts.length - 1;
    app.querySelectorAll('[data-gift-item]').forEach(function (item, itemIndex) {
      item.classList.toggle('is-loaded', itemIndex === currentIndex);
    });
  }

  async function openPreview(id) {
    var index = gifts.findIndex(function (gift) { return gift.id === id; });
    if (index < 0) return;
    await renderPreview(index);
    app.classList.add('is-preview-open');
    preview.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-gift-preview-open');
    if (previewBody) previewBody.scrollTop = 0;
  }

  function closePreview() {
    app.classList.remove('is-preview-open');
    if (preview) preview.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-gift-preview-open');
    app.querySelectorAll('[data-gift-item]').forEach(function (item) { item.classList.remove('is-loaded'); });
  }

  function movePreview(direction) {
    var nextIndex = direction === 'next' ? currentIndex + 1 : currentIndex - 1;
    if (nextIndex >= 0 && nextIndex < gifts.length) renderPreview(nextIndex, direction);
  }

  function actionMarkup(action, gift) {
    if (action === 'message') {
      return '<form data-message-form><p class="mg-modal-note">Message about <strong>' + escapeHtml(gift.id) + '</strong>.</p><label>Message<textarea name="body" maxlength="4000" required placeholder="Write a message"></textarea></label><div class="mg-item-modal-actions"><button type="button" data-modal-close>Cancel</button><button class="is-primary" type="submit">Send message</button></div></form>';
    }
    if (action === 'send') {
      return '<p class="mg-modal-note">Forwarding and follower selection will connect to the delivery package.</p><label>Recipient<input type="text" placeholder="Name, username, email, or phone"></label><div class="mg-item-modal-actions"><button type="button" data-modal-close>Close</button></div>';
    }
    if (action === 'claim') {
      return '<form data-claim-form><div class="mg-claim-qr" aria-label="Item claim QR code"></div><p class="mg-modal-note">Enter the merchant claim code for <strong>' + escapeHtml(gift.id) + '</strong>.</p><label>Claim code<input name="code" type="text" minlength="4" maxlength="64" placeholder="Enter merchant claim code" autocomplete="one-time-code" required></label><div class="mg-item-modal-actions"><button type="button" data-modal-close>Cancel</button><button class="is-primary" type="submit">Verify code</button></div></form>';
    }
    return '<p class="mg-modal-note">Tip processing will connect to the commerce package.</p><div class="mg-item-modal-actions"><button type="button" data-modal-close>Close</button></div>';
  }

  function openModal(action, gift, trigger) {
    lastTrigger = trigger || null;
    modalEyebrow.textContent = gift.id;
    modalTitle.textContent = ({ send: 'Send item', claim: 'Claim item', message: 'Message recipient', tip: 'Send a tip' })[action] || 'Item action';
    modalBody.innerHTML = actionMarkup(action, gift);
    modal.dataset.giftId = gift.id;
    modal.dataset.itemSource = gift.pppm_id ? 'pppm' : 'legacy';
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-item-modal-open');
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mg-item-modal-open');
    delete modal.dataset.itemSource;
    if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
    lastTrigger = null;
  }

  app.addEventListener('click', function (event) {
    if (event.target.closest('[data-preview-close]')) return closePreview();
    if (event.target.closest('[data-preview-prev]')) return movePreview('previous');
    if (event.target.closest('[data-preview-next]')) return movePreview('next');
    var button = event.target.closest('[data-item-action]');
    if (!button) return;
    var item = button.closest('[data-gift-item]');
    var gift = gifts.find(function (record) { return record.id === item.dataset.itemId; });
    if (!gift) return;
    if (button.dataset.itemAction === 'load') openPreview(gift.id);
    else openModal(button.dataset.itemAction, gift, button);
  });

  modal.addEventListener('submit', async function (event) {
    var messageForm = event.target.closest('[data-message-form]');
    var claimForm = event.target.closest('[data-claim-form]');
    if (!messageForm && !claimForm) return;
    event.preventDefault();

    var form = messageForm || claimForm;
    var submit = form.querySelector('[type="submit"]');
    var note = form.querySelector('.mg-modal-note');
    submit.disabled = true;

    try {
      if (messageForm) {
        await Microgifter.post('/api/messages/send.php', {
          gift_id: modal.dataset.giftId,
          item_source: modal.dataset.itemSource || 'legacy',
          body: messageForm.elements.body.value
        });
        closeModal();
        document.dispatchEvent(new CustomEvent('mg:messages:refresh'));
        return;
      }

      await Microgifter.post('/api/gifts/verify-claim.php', {
        id: modal.dataset.giftId,
        code: claimForm.elements.code.value
      });
      submit.textContent = 'Redeeming…';
      await Microgifter.post('/api/gifts/redeem-claim.php', {
        id: modal.dataset.giftId
      });
      if (note) note.textContent = 'Item claimed successfully.';
      submit.textContent = 'Claimed';
      window.setTimeout(function () {
        closeModal();
        loadActivity();
        document.dispatchEvent(new CustomEvent('mg:notifications:refresh'));
      }, 700);
    } catch (error) {
      submit.disabled = false;
      submit.textContent = claimForm ? 'Verify code' : 'Send message';
      if (note) note.textContent = error.message || (claimForm ? 'Unable to claim this item.' : 'Unable to send message.');
    }
  });

  modal.addEventListener('click', function (event) {
    if (event.target.closest('[data-modal-close]')) closeModal();
  });

  if (preview) {
    preview.addEventListener('wheel', function (event) {
      if (!app.classList.contains('is-preview-open') || window.innerWidth < 981 || wheelLocked || Math.abs(event.deltaY) < 30) return;
      event.preventDefault();
      wheelLocked = true;
      movePreview(event.deltaY > 0 ? 'next' : 'previous');
      window.setTimeout(function () { wheelLocked = false; }, 420);
    }, { passive: false });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) return closeModal();
    if (!app.classList.contains('is-preview-open')) return;
    if (event.key === 'Escape') closePreview();
    if (event.key === 'ArrowUp' || event.key === 'PageUp') movePreview('previous');
    if (event.key === 'ArrowDown' || event.key === 'PageDown') movePreview('next');
  });

  loadActivity();
});
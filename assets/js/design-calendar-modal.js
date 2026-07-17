document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-design-content-calendar]');
  if (!root) return;

  var MG = window.Microgifter || {};
  var endpoint = '/api/merchant/design-content-calendar.php';
  var activeArticle = null;
  var activeItem = null;

  var themeKeys = {
    'Product Spotlight': 'product_spotlight',
    'Gift Idea': 'gift_idea',
    'Reward Promotion': 'reward_promotion',
    'Merchant Story': 'merchant_story',
    'Customer Review': 'customer_review',
    'Local Support': 'local_support'
  };
  var themeLabels = Object.keys(themeKeys).reduce(function (map, label) {
    map[themeKeys[label]] = label;
    return map;
  }, {});
  var formatLabels = { square: 'Post · 1:1', portrait: 'Portrait · 4:5', story: 'Story / Reel · 9:16' };
  var layoutLabels = { spotlight: 'Spotlight', split: 'Split Feature', bold: 'Bold Offer' };
  var statusLabels = { planned: 'Planned', downloaded: 'Downloaded', posted: 'Posted', skipped: 'Skipped' };
  var platforms = ['general', 'facebook', 'instagram', 'linkedin'];

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function payload(response) {
    return response && response.data ? response.data : (response || {});
  }

  async function request(url) {
    if (typeof MG.api === 'function') return payload(await MG.api(url));
    var response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    var json = await response.json().catch(function () { return {}; });
    var data = payload(json);
    if (!response.ok || json.ok === false || json.success === false) throw new Error(json.message || data.message || 'Request failed.');
    return data;
  }

  async function post(body) {
    if (typeof MG.post === 'function') return payload(await MG.post(endpoint, body));
    throw new Error('Secure calendar updates are unavailable on this page.');
  }

  var modal = document.createElement('div');
  modal.className = 'mg-calendar-entry-modal';
  modal.hidden = true;
  modal.setAttribute('data-calendar-entry-modal', '');
  modal.innerHTML = '<div class="mg-calendar-entry-backdrop" data-calendar-modal-close></div>'
    + '<section class="mg-calendar-entry-dialog" role="dialog" aria-modal="true" aria-labelledby="calendar-entry-title">'
    + '<header class="mg-calendar-entry-head"><div><span data-calendar-modal-theme>Scheduled post</span><h2 id="calendar-entry-title" data-calendar-modal-title>Post preview</h2></div><button type="button" data-calendar-modal-close aria-label="Close post preview">×</button></header>'
    + '<div class="mg-calendar-entry-body">'
    + '<aside class="mg-calendar-entry-preview">'
    + '<div class="mg-calendar-entry-image"><img data-calendar-modal-image alt=""><div data-calendar-modal-image-empty>Microgifter</div></div>'
    + '<div class="mg-calendar-entry-preview-copy"><span data-calendar-modal-preview-kicker>Scheduled content</span><h3 data-calendar-modal-preview-title></h3><p data-calendar-modal-preview-caption></p><div class="mg-calendar-entry-preview-meta"><span data-calendar-modal-preview-format></span><span data-calendar-modal-preview-layout></span><span data-calendar-modal-preview-status></span></div></div>'
    + '<footer><strong data-calendar-modal-preview-cta></strong><a data-calendar-modal-preview-link target="_blank" rel="noopener">View product</a></footer>'
    + '</aside>'
    + '<form class="mg-calendar-entry-settings" data-calendar-modal-form>'
    + '<div class="mg-calendar-entry-setting-grid">'
    + '<label><span>Date</span><input type="date" name="scheduled_date" required></label>'
    + '<label><span>Time</span><input type="time" name="scheduled_time"></label>'
    + '<label><span>Timezone</span><input name="timezone" readonly></label>'
    + '<label><span>Format</span><select name="post_format"><option value="square">Post · 1:1</option><option value="portrait">Portrait · 4:5</option><option value="story">Story / Reel · 9:16</option></select></label>'
    + '<label><span>Layout</span><select name="layout_key"><option value="spotlight">Spotlight</option><option value="split">Split Feature</option><option value="bold">Bold Offer</option></select></label>'
    + '<label><span>Status</span><select name="status"><option value="planned">Planned</option><option value="downloaded">Downloaded</option><option value="posted">Posted</option><option value="skipped">Skipped</option></select></label>'
    + '<label class="is-wide"><span>Campaign theme</span><select name="campaign_theme"><option value="product_spotlight">Product Spotlight</option><option value="gift_idea">Gift Idea</option><option value="reward_promotion">Reward Promotion</option><option value="merchant_story">Merchant Story</option><option value="customer_review">Customer Review</option><option value="local_support">Local Support</option></select></label>'
    + '<label class="is-wide"><span>Internal notes</span><textarea name="notes" rows="2"></textarea></label>'
    + '<label class="is-wide"><span>Short caption</span><textarea name="caption_short" rows="2"></textarea></label>'
    + '<label class="is-wide"><span>Standard caption</span><textarea name="caption_standard" rows="5"></textarea></label>'
    + '<label class="is-wide"><span>Extended caption</span><textarea name="caption_extended" rows="7"></textarea></label>'
    + '<label class="is-wide"><span>Hashtags</span><input name="hashtags"></label>'
    + '<label class="is-wide"><span>Product link</span><input name="product_link"></label>'
    + '<label class="is-wide"><span>Call to action</span><input name="call_to_action"></label>'
    + '</div>'
    + '<section class="mg-calendar-entry-platforms"><header><span>Platform copy</span><strong>General, Facebook, Instagram, and LinkedIn</strong></header><div data-calendar-modal-platforms></div></section>'
    + '<div class="mg-calendar-entry-form-status" data-calendar-modal-status role="status" aria-live="polite"></div>'
    + '</form>'
    + '</div>'
    + '<footer class="mg-calendar-entry-actions"><div><button type="button" data-calendar-modal-design>Open in Design Studio</button><button type="button" data-calendar-modal-duplicate>Duplicate</button><button type="button" class="is-danger" data-calendar-modal-remove>Remove</button></div><button type="submit" form="calendar-entry-settings-form" class="mg-btn mg-btn-primary" data-calendar-modal-save>Save settings</button></footer>'
    + '</section>';
  document.body.appendChild(modal);

  var form = modal.querySelector('[data-calendar-modal-form]');
  form.id = 'calendar-entry-settings-form';
  var modalStatus = modal.querySelector('[data-calendar-modal-status]');
  var saveButton = modal.querySelector('[data-calendar-modal-save]');

  function platformMarkup(item) {
    var copy = item.platform_copy || {};
    return platforms.map(function (platform) {
      var values = copy[platform] || {};
      return '<section class="mg-calendar-entry-platform"><h4>' + escapeHtml(platform.charAt(0).toUpperCase() + platform.slice(1)) + '</h4>'
        + ['short', 'standard', 'extended'].map(function (size) {
          return '<label><span>' + escapeHtml(size.charAt(0).toUpperCase() + size.slice(1)) + '</span><textarea rows="' + (size === 'short' ? '2' : (size === 'standard' ? '4' : '6')) + '" data-modal-platform-copy="' + platform + ':' + size + '">' + escapeHtml(values[size] || '') + '</textarea></label>';
        }).join('') + '</section>';
    }).join('');
  }

  function setField(name, value) {
    var field = form.elements.namedItem(name);
    if (field) field.value = value == null ? '' : String(value);
  }

  function updatePreviewFromForm() {
    if (!activeItem) return;
    var title = String(activeItem.title || activeItem.slug || 'Scheduled product');
    var theme = String(form.elements.campaign_theme.value || 'product_spotlight');
    var format = String(form.elements.post_format.value || 'square');
    var layout = String(form.elements.layout_key.value || 'spotlight');
    var status = String(form.elements.status.value || 'planned');
    modal.querySelector('[data-calendar-modal-theme]').textContent = themeLabels[theme] || 'Scheduled post';
    modal.querySelector('[data-calendar-modal-preview-kicker]').textContent = themeLabels[theme] || 'Scheduled post';
    modal.querySelector('[data-calendar-modal-preview-title]').textContent = title;
    modal.querySelector('[data-calendar-modal-preview-caption]').textContent = form.elements.caption_standard.value || form.elements.caption_short.value || 'Add posting copy in the settings panel.';
    modal.querySelector('[data-calendar-modal-preview-format]').textContent = formatLabels[format] || format;
    modal.querySelector('[data-calendar-modal-preview-layout]').textContent = layoutLabels[layout] || layout;
    modal.querySelector('[data-calendar-modal-preview-status]').textContent = statusLabels[status] || status;
    modal.querySelector('[data-calendar-modal-preview-cta]').textContent = form.elements.call_to_action.value || 'Discover local';
    var link = modal.querySelector('[data-calendar-modal-preview-link]');
    link.href = form.elements.product_link.value || '#';
  }

  function fillModal(item) {
    activeItem = item;
    var title = String(item.title || item.slug || 'Scheduled product');
    modal.querySelector('[data-calendar-modal-title]').textContent = title;
    setField('scheduled_date', item.scheduled_date || '');
    setField('scheduled_time', String(item.scheduled_time || '').slice(0, 5));
    setField('timezone', item.timezone || 'UTC');
    setField('post_format', item.post_format || 'square');
    setField('layout_key', item.layout_key || 'spotlight');
    setField('status', item.status || 'planned');
    setField('campaign_theme', item.campaign_theme || 'product_spotlight');
    setField('notes', item.notes || '');
    setField('caption_short', item.caption_short || '');
    setField('caption_standard', item.caption_standard || '');
    setField('caption_extended', item.caption_extended || '');
    setField('hashtags', item.hashtags || '');
    setField('product_link', item.product_link || '');
    setField('call_to_action', item.call_to_action || '');
    modal.querySelector('[data-calendar-modal-platforms]').innerHTML = platformMarkup(item);

    var image = modal.querySelector('[data-calendar-modal-image]');
    var empty = modal.querySelector('[data-calendar-modal-image-empty]');
    if (item.image_url) {
      image.hidden = false;
      image.src = item.image_url;
      image.alt = title;
      empty.hidden = true;
    } else {
      image.hidden = true;
      image.removeAttribute('src');
      empty.hidden = false;
    }
    updatePreviewFromForm();
  }

  async function fetchItem(article) {
    var id = String(article.getAttribute('data-calendar-event') || '');
    var dateField = article.querySelector('[data-calendar-field="scheduled_date"]');
    var date = String(dateField ? dateField.value : '').trim();
    if (!id || !date) throw new Error('The scheduled post could not be identified.');
    var data = await request(endpoint + '?from=' + encodeURIComponent(date) + '&to=' + encodeURIComponent(date));
    var item = Array.isArray(data.items) ? data.items.find(function (row) { return String(row.public_id) === id; }) : null;
    if (!item) throw new Error('The scheduled post could not be loaded.');
    return item;
  }

  async function openModal(article) {
    activeArticle = article;
    modal.hidden = false;
    document.body.classList.add('mg-calendar-modal-open');
    modalStatus.textContent = 'Loading post settings…';
    saveButton.disabled = true;
    try {
      fillModal(await fetchItem(article));
      modalStatus.textContent = '';
      saveButton.disabled = false;
      modal.querySelector('[data-calendar-modal-close]').focus();
    } catch (error) {
      modalStatus.textContent = error.message || 'Unable to load the scheduled post.';
    }
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('mg-calendar-modal-open');
    activeItem = null;
    var returnTarget = activeArticle;
    activeArticle = null;
    if (returnTarget) returnTarget.focus();
  }

  function decorateCards() {
    root.querySelectorAll('[data-calendar-event]').forEach(function (article) {
      var label = article.querySelector('.mg-design-calendar-event-head > span');
      var theme = themeKeys[String(label ? label.textContent : '').trim()] || 'product_spotlight';
      Object.keys(themeLabels).forEach(function (key) { article.classList.remove('theme-' + key); });
      article.classList.add('theme-' + theme);
      article.setAttribute('aria-label', 'Open scheduled post settings');
      var openButton = article.querySelector('[data-calendar-open]');
      if (openButton) openButton.textContent = 'Preview';
    });
  }

  root.addEventListener('click', function (event) {
    var article = event.target.closest('[data-calendar-event]');
    if (!article) return;
    if (event.target.closest('[data-calendar-select-item],[data-calendar-duplicate],[data-calendar-remove]')) return;
    if (event.target.closest('input,select,textarea,details,summary')) return;
    if (event.target.closest('[data-calendar-open]')) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
    openModal(article);
  }, true);

  root.addEventListener('keydown', function (event) {
    var article = event.target.closest('[data-calendar-event]');
    if (!article || event.target !== article || (event.key !== 'Enter' && event.key !== ' ')) return;
    event.preventDefault();
    openModal(article);
  });

  modal.querySelectorAll('[data-calendar-modal-close]').forEach(function (button) { button.addEventListener('click', closeModal); });
  form.addEventListener('input', updatePreviewFromForm);
  form.addEventListener('change', updatePreviewFromForm);

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!activeItem) return;
    var platformCopy = {};
    modal.querySelectorAll('[data-modal-platform-copy]').forEach(function (field) {
      var parts = String(field.getAttribute('data-modal-platform-copy') || '').split(':');
      if (parts.length !== 2) return;
      platformCopy[parts[0]] = platformCopy[parts[0]] || {};
      platformCopy[parts[0]][parts[1]] = field.value;
    });
    var changes = {
      action: 'update',
      schedule_id: activeItem.public_id,
      scheduled_date: form.elements.scheduled_date.value,
      scheduled_time: form.elements.scheduled_time.value,
      post_format: form.elements.post_format.value,
      layout_key: form.elements.layout_key.value,
      status: form.elements.status.value,
      campaign_theme: form.elements.campaign_theme.value,
      notes: form.elements.notes.value,
      caption_short: form.elements.caption_short.value,
      caption_standard: form.elements.caption_standard.value,
      caption_extended: form.elements.caption_extended.value,
      hashtags: form.elements.hashtags.value,
      product_link: form.elements.product_link.value,
      call_to_action: form.elements.call_to_action.value,
      platform_copy: platformCopy
    };
    saveButton.disabled = true;
    modalStatus.textContent = 'Saving all post settings…';
    try {
      await post(changes);
      modalStatus.textContent = 'Post settings saved. Refreshing calendar…';
      window.setTimeout(function () { window.location.reload(); }, 350);
    } catch (error) {
      modalStatus.textContent = error.message || 'Unable to save post settings.';
      saveButton.disabled = false;
    }
  });

  modal.querySelector('[data-calendar-modal-design]').addEventListener('click', function () {
    if (!activeItem) return;
    var url = '/design-studio.php?mode=social'
      + '&product=' + encodeURIComponent(activeItem.product_id || '')
      + '&format=' + encodeURIComponent(form.elements.post_format.value || 'square')
      + '&layout=' + encodeURIComponent(form.elements.layout_key.value || 'spotlight')
      + '&schedule=' + encodeURIComponent(activeItem.public_id || '');
    window.location.assign(url);
  });
  modal.querySelector('[data-calendar-modal-duplicate]').addEventListener('click', function () {
    var button = activeArticle && activeArticle.querySelector('[data-calendar-duplicate]');
    closeModal();
    if (button) button.click();
  });
  modal.querySelector('[data-calendar-modal-remove]').addEventListener('click', function () {
    var button = activeArticle && activeArticle.querySelector('[data-calendar-remove]');
    closeModal();
    if (button) button.click();
  });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !modal.hidden) closeModal(); });

  new MutationObserver(decorateCards).observe(root, { childList: true, subtree: true });
  decorateCards();
});
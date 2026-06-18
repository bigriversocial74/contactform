window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-social-feed]');
  var form = root && root.querySelector('[data-post-form]');
  if (!root || !form || !MG.get) return;

  var fieldNames = { product: 'product_id', microgift: 'microgift_id', plan: 'subscription_plan_id' };
  var labels = {
    product: { singular: 'product', title: 'Attach a product', description: 'Choose a product owned by this merchant account.', button: 'Choose product' },
    microgift: { singular: 'Microgift', title: 'Attach a Microgift', description: 'Choose a Microgift you own or issued.', button: 'Choose Microgift' },
    plan: { singular: 'plan', title: 'Choose a member plan', description: 'Required for Subscribers or Premium members.', button: 'Choose plan' }
  };
  var selected = { product: null, microgift: null, plan: null };
  var activeType = null;
  var searchTimer = null;
  var requestToken = 0;

  function ensureStyles() {
    if (document.querySelector('link[href="/assets/css/social-feed-attachments.css"]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/assets/css/social-feed-attachments.css';
    document.head.appendChild(link);
  }

  function hiddenField(type) {
    return form.elements[fieldNames[type]];
  }

  function prepareTechnicalFields() {
    Object.keys(fieldNames).forEach(function (type) {
      var field = hiddenField(type);
      if (!field) return;
      var label = field.closest('label');
      field.type = 'hidden';
      form.appendChild(field);
      if (label) label.remove();
    });
    form.querySelectorAll('.mg-feed-technical-links').forEach(function (details) {
      if (!details.querySelector('input:not([type="hidden"]),textarea,select')) details.remove();
    });
  }

  function node(tag, className, text) {
    var element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
  }

  function money(item) {
    var cents = Number(item && item.amount_cents || 0);
    var currency = String(item && item.currency || 'USD').toUpperCase();
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency }).format(cents / 100); }
    catch (error) { return currency + ' ' + (cents / 100).toFixed(2); }
  }

  function itemMeta(item) {
    if (!item) return '';
    if (item.kind === 'product') return money(item) + ' · ' + String(item.product_type || 'product').replace(/_/g, ' ');
    if (item.kind === 'microgift') return money(item) + ' · ' + String(item.status || 'active').replace(/_/g, ' ');
    var interval = Number(item.interval_count || 1) > 1 ? String(item.interval_count) + ' ' + String(item.interval_unit || 'month') + 's' : String(item.interval_unit || 'month');
    return money(item) + ' / ' + interval;
  }

  function preview(item, className) {
    var wrap = node('div', className);
    if (item && item.preview_url) {
      var image = document.createElement('img');
      image.src = item.preview_url;
      image.alt = '';
      image.loading = 'lazy';
      wrap.appendChild(image);
    } else {
      wrap.textContent = item && item.kind === 'microgift' ? 'Gift' : (item && item.kind === 'plan' ? 'Plan' : 'Product');
    }
    return wrap;
  }

  function buildPickerSection() {
    var section = node('section', 'mg-feed-item-attachments');
    section.dataset.feedAttachmentPicker = '';
    section.setAttribute('aria-labelledby', 'mg-feed-item-attachments-title');

    var head = node('div', 'mg-feed-item-attachments-head');
    var headCopy = document.createElement('div');
    headCopy.append(node('span', 'mg-kicker', 'Microgifter items'));
    var heading = node('h3', '', 'Attach something from Microgifter');
    heading.id = 'mg-feed-item-attachments-title';
    headCopy.append(heading, node('p', '', 'Add one product, one Microgift, and an optional subscriber plan without copying IDs.'));
    var summary = node('span', '', 'Nothing attached');
    summary.dataset.attachmentSummary = '';
    head.append(headCopy, summary);

    var grid = node('div', 'mg-feed-item-attachment-grid');
    Object.keys(labels).forEach(function (type) {
      var slot = node('article', 'mg-feed-item-attachment-slot');
      slot.dataset.attachmentSlot = type;
      var copy = node('div', 'mg-feed-item-attachment-copy');
      copy.append(node('span', '', type === 'plan' ? 'Subscriber access' : labels[type].singular));
      copy.append(node('strong', '', labels[type].title));
      copy.append(node('p', '', labels[type].description));
      var selection = node('div', 'mg-feed-item-selection');
      selection.dataset.attachmentSelection = type;
      var choose = node('button', 'mg-btn mg-btn-soft', labels[type].button);
      choose.type = 'button';
      choose.dataset.attachmentOpen = type;
      slot.append(copy, selection, choose);
      grid.appendChild(slot);
    });

    var status = node('div', 'mg-feed-attachment-status');
    status.dataset.attachmentStatus = '';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    section.append(head, grid, status);
    return section;
  }

  function buildDialog() {
    var dialog = node('dialog', 'mg-feed-attachment-dialog');
    dialog.dataset.attachmentDialog = '';
    dialog.setAttribute('aria-labelledby', 'mg-feed-attachment-dialog-title');
    var shell = node('div', 'mg-feed-attachment-dialog-shell');
    var header = document.createElement('header');
    var copy = document.createElement('div');
    var kicker = node('span', 'mg-kicker', 'Microgifter items');
    kicker.dataset.attachmentDialogKicker = '';
    var title = node('h3', '', 'Choose an attachment');
    title.id = 'mg-feed-attachment-dialog-title';
    title.dataset.attachmentDialogTitle = '';
    copy.append(kicker, title);
    var close = node('button', 'mg-btn mg-btn-ghost', 'Close');
    close.type = 'button';
    close.dataset.attachmentClose = '';
    header.append(copy, close);

    var searchLabel = node('label', 'mg-feed-attachment-search', 'Search');
    var search = document.createElement('input');
    search.type = 'search';
    search.maxLength = 100;
    search.autocomplete = 'off';
    search.placeholder = 'Search by title or description';
    search.dataset.attachmentSearch = '';
    searchLabel.appendChild(search);

    var status = node('div', 'mg-feed-attachment-dialog-status');
    status.dataset.attachmentDialogStatus = '';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    var options = node('div', 'mg-feed-attachment-options');
    options.dataset.attachmentOptions = '';
    shell.append(header, searchLabel, status, options);
    dialog.appendChild(shell);
    return dialog;
  }

  function installUi() {
    if (form.querySelector('[data-feed-attachment-picker]')) return;
    var section = buildPickerSection();
    var audience = form.querySelector('.mg-feed-publish-settings');
    var advanced = form.querySelector('.mg-feed-advanced');
    form.insertBefore(section, audience || advanced || form.querySelector('.mg-feed-composer-footer'));
    form.closest('[data-post-composer]').appendChild(buildDialog());
  }

  function status(message, kind) {
    var target = form.querySelector('[data-attachment-status]');
    target.textContent = message || '';
    target.className = 'mg-feed-attachment-status' + (kind ? ' is-' + kind : '');
  }

  function dialogStatus(message, kind) {
    var target = form.closest('[data-post-composer]').querySelector('[data-attachment-dialog-status]');
    target.textContent = message || '';
    target.className = 'mg-feed-attachment-dialog-status' + (kind ? ' is-' + kind : '');
  }

  function renderSelection(type) {
    var container = form.querySelector('[data-attachment-selection="' + type + '"]');
    var slot = form.querySelector('[data-attachment-slot="' + type + '"]');
    var item = selected[type];
    container.replaceChildren();
    slot.classList.toggle('is-selected', Boolean(item));
    if (!item) return;

    var card = node('div', 'mg-feed-selected-item');
    card.appendChild(preview(item, 'mg-feed-selected-preview'));
    var copy = node('div', 'mg-feed-selected-copy');
    copy.append(node('strong', '', item.title || labels[type].singular));
    copy.append(node('span', '', itemMeta(item)));
    var remove = node('button', 'mg-feed-selected-remove', '×');
    remove.type = 'button';
    remove.dataset.attachmentRemove = type;
    remove.setAttribute('aria-label', 'Remove attached ' + labels[type].singular);
    card.append(copy, remove);
    container.appendChild(card);
  }

  function renderSummary() {
    var count = Object.keys(selected).filter(function (type) { return Boolean(selected[type]); }).length;
    form.querySelector('[data-attachment-summary]').textContent = count ? count + ' item' + (count === 1 ? '' : 's') + ' attached' : 'Nothing attached';
    validatePlan(false);
  }

  function setSelection(type, item, announce) {
    selected[type] = item || null;
    var field = hiddenField(type);
    if (field) field.value = item ? item.id : '';
    renderSelection(type);
    renderSummary();
    if (announce) status(item ? item.title + ' attached.' : labels[type].singular + ' removed.', 'success');
  }

  function optionCard(item) {
    var button = node('button', 'mg-feed-attachment-option');
    button.type = 'button';
    button.dataset.attachmentChoose = item.id;
    button.classList.toggle('is-selected', Boolean(selected[activeType] && selected[activeType].id === item.id));
    button.appendChild(preview(item, 'mg-feed-attachment-option-preview'));
    var copy = node('div', 'mg-feed-attachment-option-copy');
    copy.append(node('strong', '', item.title || 'Untitled'));
    copy.append(node('p', '', item.description || 'No description available.'));
    var meta = node('div', 'mg-feed-attachment-option-meta');
    meta.append(node('span', '', itemMeta(item)));
    if (item.status) meta.append(node('span', '', String(item.status).replace(/_/g, ' ')));
    copy.appendChild(meta);
    button.appendChild(copy);
    button._attachmentItem = item;
    return button;
  }

  function renderOptions(items) {
    var container = form.closest('[data-post-composer]').querySelector('[data-attachment-options]');
    container.replaceChildren();
    if (!items.length) {
      container.appendChild(node('div', 'mg-feed-attachment-empty', 'No matching ' + labels[activeType].singular + ' items were found.'));
      return;
    }
    items.forEach(function (item) { container.appendChild(optionCard(item)); });
  }

  async function loadOptions(type, query, exactId) {
    var token = ++requestToken;
    dialogStatus('Loading ' + labels[type].singular + ' options…');
    var url = '/api/social/attachment-options.php?type=' + encodeURIComponent(type) + '&limit=24';
    if (exactId) url += '&selected=' + encodeURIComponent(exactId);
    else if (query) url += '&q=' + encodeURIComponent(query);
    try {
      var response = await MG.get(url);
      if (token !== requestToken) return [];
      var data = response && response.data ? response.data : response;
      var items = Array.isArray(data && data.items) ? data.items : [];
      if (!exactId) renderOptions(items);
      dialogStatus(items.length ? items.length + ' option' + (items.length === 1 ? '' : 's') + ' found.' : 'No matching options.');
      return items;
    } catch (error) {
      if (token !== requestToken) return [];
      if (!exactId) renderOptions([]);
      dialogStatus(error.message || 'Unable to load attachment options.', 'error');
      return [];
    }
  }

  function openDialog(type) {
    activeType = type;
    var dialog = form.closest('[data-post-composer]').querySelector('[data-attachment-dialog]');
    dialog.querySelector('[data-attachment-dialog-title]').textContent = labels[type].title;
    dialog.querySelector('[data-attachment-dialog-kicker]').textContent = type === 'plan' ? 'Subscriber access' : 'Microgifter ' + labels[type].singular;
    var search = dialog.querySelector('[data-attachment-search]');
    search.value = '';
    renderOptions([]);
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
    loadOptions(type, '', '');
    window.setTimeout(function () { search.focus(); }, 20);
  }

  function closeDialog() {
    var dialog = form.closest('[data-post-composer]').querySelector('[data-attachment-dialog]');
    requestToken += 1;
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
    activeType = null;
  }

  function requiresPlan() {
    return form.elements.visibility && ['subscribers','premium'].includes(form.elements.visibility.value);
  }

  function validatePlan(announce) {
    var required = requiresPlan() && !hiddenField('plan').value;
    var slot = form.querySelector('[data-attachment-slot="plan"]');
    slot.classList.toggle('is-required', required);
    if (required && announce) {
      status('Choose an active member plan for subscriber-only posts.', 'error');
      slot.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else if (!required && form.querySelector('[data-attachment-status]').classList.contains('is-error')) {
      status('');
    }
    return !required;
  }

  async function syncType(type) {
    var field = hiddenField(type);
    var id = String(field && field.value || '').trim();
    if (!id) {
      if (selected[type]) setSelection(type, null, false);
      return;
    }
    if (selected[type] && selected[type].id === id) return;
    var items = await loadOptions(type, '', id);
    if (items[0]) setSelection(type, items[0], false);
    else {
      selected[type] = { kind: type, id: id, title: 'Attached ' + labels[type].singular, status: 'selected' };
      renderSelection(type);
      renderSummary();
    }
  }

  function syncFromFields() {
    Promise.all(Object.keys(labels).map(syncType)).then(renderSummary);
  }

  ensureStyles();
  prepareTechnicalFields();
  installUi();
  Object.keys(labels).forEach(renderSelection);
  renderSummary();

  root.addEventListener('click', function (event) {
    var open = event.target.closest('[data-attachment-open]');
    if (open) {
      event.preventDefault();
      openDialog(open.dataset.attachmentOpen);
      return;
    }
    var remove = event.target.closest('[data-attachment-remove]');
    if (remove) {
      event.preventDefault();
      setSelection(remove.dataset.attachmentRemove, null, true);
      return;
    }
    var choose = event.target.closest('[data-attachment-choose]');
    if (choose && activeType) {
      event.preventDefault();
      setSelection(activeType, choose._attachmentItem, true);
      closeDialog();
      return;
    }
    if (event.target.closest('[data-attachment-close]')) {
      event.preventDefault();
      closeDialog();
      return;
    }
    if (event.target.closest('[data-composer-toggle],[data-post-action="owner_edit"],[data-post-cancel-edit]')) {
      window.setTimeout(syncFromFields, 0);
    }
  });

  var dialog = form.closest('[data-post-composer]').querySelector('[data-attachment-dialog]');
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) closeDialog();
  });
  dialog.addEventListener('cancel', function (event) {
    event.preventDefault();
    closeDialog();
  });

  dialog.querySelector('[data-attachment-search]').addEventListener('input', function (event) {
    var value = event.target.value.trim();
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(function () {
      if (activeType) loadOptions(activeType, value, '');
    }, 250);
  });

  form.elements.visibility.addEventListener('change', function () {
    if (requiresPlan() && !hiddenField('plan').value) status('Subscriber audiences require an active member plan.', 'error');
    validatePlan(false);
  });

  form.addEventListener('reset', function () {
    window.setTimeout(function () {
      Object.keys(labels).forEach(function (type) { selected[type] = null; renderSelection(type); });
      renderSummary();
    }, 0);
  });

  form.addEventListener('submit', function (event) {
    if (!validatePlan(true)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  root.addEventListener('click', function (event) {
    if (event.target.closest('[data-post-save-draft]') && !validatePlan(true)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  syncFromFields();
})(window, document);

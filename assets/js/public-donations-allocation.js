document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

  function node(tag, className, text) {
    var item = document.createElement(tag);
    if (className) item.className = className;
    if (text != null) item.textContent = String(text);
    return item;
  }

  function safeArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function apiData(response) {
    return response && response.data ? response.data : (response || {});
  }

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: String(currency || 'USD'),
        maximumFractionDigits: 2
      }).format(Number(cents || 0) / 100);
    } catch (error) {
      return '$' + (Number(cents || 0) / 100).toFixed(2);
    }
  }

  function integer(value, fallback) {
    var parsed = Number.parseInt(String(value == null ? '' : value), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function statusText(value) {
    var raw = String(value || '');
    return raw ? raw.charAt(0).toUpperCase() + raw.slice(1).replace(/_/g, ' ') : 'Unknown';
  }

  function setMessage(parts, message, tone) {
    parts.status.textContent = message || '';
    parts.status.classList.toggle('is-error', tone === 'error');
    parts.status.classList.toggle('is-success', tone === 'success');
  }

  function randomKey() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return 'public-donation:' + window.crypto.randomUUID();
    }
    return 'public-donation:' + Date.now().toString(36) + ':' + Math.random().toString(36).slice(2);
  }

  function waitForAssignmentPanel(callback) {
    var existing = document.querySelector('[data-community-assignment-panel]');
    if (existing) {
      callback(existing);
      return;
    }
    var observer = new MutationObserver(function () {
      var panel = document.querySelector('[data-community-assignment-panel]');
      if (!panel) return;
      observer.disconnect();
      callback(panel);
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function build(panel) {
    var card = node('section', 'mg-donation-allocation-card');
    card.setAttribute('data-donation-allocation-card', '');

    var head = node('header', 'mg-donation-allocation-head');
    var copy = node('div');
    copy.appendChild(node('span', 'mg-eyebrow', 'Reward allocation'));
    copy.appendChild(node('h3', '', 'Issue rewards to Community accounts'));
    copy.appendChild(node('p', '', 'Preview inventory first, then issue each unit through Wallet, PPPM, Microgift, and Inbox in one transaction.'));
    head.appendChild(copy);
    var architecture = node('div', 'mg-donation-allocation-architecture');
    ['Wallet', 'PPPM', 'Microgift', 'Inbox'].forEach(function (label) {
      architecture.appendChild(node('span', '', label));
    });
    head.appendChild(architecture);
    card.appendChild(head);

    var controls = node('div', 'mg-donation-allocation-controls');
    var templateLabel = node('label');
    templateLabel.appendChild(node('span', '', 'Active reward template'));
    var template = node('select');
    template.setAttribute('data-donation-template', '');
    templateLabel.appendChild(template);
    controls.appendChild(templateLabel);

    var modeLabel = node('label');
    modeLabel.appendChild(node('span', '', 'Quantity mode'));
    var mode = node('select');
    [['same', 'Same quantity'], ['custom', 'Custom quantities']].forEach(function (entry) {
      var option = node('option', '', entry[1]);
      option.value = entry[0];
      mode.appendChild(option);
    });
    modeLabel.appendChild(mode);
    controls.appendChild(modeLabel);

    var quantityLabel = node('label');
    quantityLabel.appendChild(node('span', '', 'Quantity per account'));
    var quantity = node('input');
    quantity.type = 'number';
    quantity.min = '1';
    quantity.max = '1000';
    quantity.value = '1';
    quantityLabel.appendChild(quantity);
    controls.appendChild(quantityLabel);
    card.appendChild(controls);

    var selectionHead = node('div', 'mg-donation-selection-head');
    var selectionCopy = node('div');
    selectionCopy.appendChild(node('strong', '', 'Active Community assignments'));
    selectionCopy.appendChild(node('span', '', 'Up to 50 accounts and 1,000 total units per operation.'));
    selectionHead.appendChild(selectionCopy);
    var selectAll = node('button', 'mg-btn mg-btn-soft', 'Select all');
    selectAll.type = 'button';
    selectionHead.appendChild(selectAll);
    card.appendChild(selectionHead);

    var recipients = node('div', 'mg-donation-recipient-list');
    recipients.setAttribute('data-donation-recipients', '');
    card.appendChild(recipients);

    var notes = node('div', 'mg-donation-note-grid');
    var messageLabel = node('label');
    messageLabel.appendChild(node('span', '', 'Recipient message (optional)'));
    var message = node('textarea');
    message.maxLength = 1000;
    message.rows = 3;
    message.placeholder = 'Shown in the reward allocation context.';
    messageLabel.appendChild(message);
    notes.appendChild(messageLabel);
    var noteLabel = node('label');
    noteLabel.appendChild(node('span', '', 'Internal note (optional)'));
    var internalNote = node('textarea');
    internalNote.maxLength = 2000;
    internalNote.rows = 3;
    internalNote.placeholder = 'Merchant-only operation note.';
    noteLabel.appendChild(internalNote);
    notes.appendChild(noteLabel);
    card.appendChild(notes);

    var preview = node('div', 'mg-donation-preflight');
    preview.setAttribute('data-donation-preflight', '');
    card.appendChild(preview);

    var confirmation = node('label', 'mg-donation-large-confirmation');
    confirmation.hidden = true;
    var confirmationInput = node('input');
    confirmationInput.type = 'checkbox';
    confirmation.appendChild(confirmationInput);
    confirmation.appendChild(node('span', '', 'I confirm this large operation (100+ units or $1,000+ stated value).'));
    card.appendChild(confirmation);

    var actions = node('div', 'mg-donation-allocation-actions');
    var preflightButton = node('button', 'mg-btn mg-btn-soft', 'Preview allocation');
    preflightButton.type = 'button';
    var allocateButton = node('button', 'mg-btn mg-btn-primary', 'Allocate rewards');
    allocateButton.type = 'button';
    allocateButton.disabled = true;
    actions.appendChild(preflightButton);
    actions.appendChild(allocateButton);
    card.appendChild(actions);

    var status = node('div', 'mg-donation-allocation-status');
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    card.appendChild(status);

    var history = node('section', 'mg-donation-allocation-history');
    var historyHead = node('div', 'mg-community-section-head');
    var historyCopy = node('div');
    historyCopy.appendChild(node('span', 'mg-eyebrow', 'Tracking'));
    historyCopy.appendChild(node('h3', '', 'Recent allocation operations'));
    historyCopy.appendChild(node('p', '', 'Track recipients, units, reward templates, values, and completion status.'));
    historyHead.appendChild(historyCopy);
    history.appendChild(historyHead);
    var historyList = node('div', 'mg-donation-operation-list');
    history.appendChild(historyList);
    card.appendChild(history);

    var privacy = panel.querySelector('.mg-community-privacy-note');
    if (privacy) panel.insertBefore(card, privacy);
    else panel.appendChild(card);

    return {
      panel: panel,
      card: card,
      campaignSelect: panel.querySelector('[data-community-campaign-select]'),
      template: template,
      mode: mode,
      quantity: quantity,
      quantityLabel: quantityLabel,
      selectAll: selectAll,
      recipients: recipients,
      message: message,
      internalNote: internalNote,
      preview: preview,
      confirmation: confirmation,
      confirmationInput: confirmationInput,
      preflightButton: preflightButton,
      allocateButton: allocateButton,
      status: status,
      historyList: historyList
    };
  }

  function invalidate(parts, state) {
    state.preflight = null;
    state.idempotencyKey = randomKey();
    parts.allocateButton.disabled = true;
    parts.confirmation.hidden = true;
    parts.confirmationInput.checked = false;
    parts.preview.replaceChildren();
  }

  function assignmentRow(parts, state, item) {
    var assignment = item.assignment || {};
    var row = node('label', 'mg-donation-recipient-row');
    row.setAttribute('data-assignment-id', assignment.id || '');
    var checkbox = node('input');
    checkbox.type = 'checkbox';
    checkbox.setAttribute('data-recipient-check', '');
    row.appendChild(checkbox);

    var copy = node('span', 'mg-donation-recipient-copy');
    copy.appendChild(node('strong', '', item.display_name || 'Community member'));
    var meta = node('small', '', item.general_location || item.username || 'Community account');
    copy.appendChild(meta);
    row.appendChild(copy);

    var quantity = node('input', 'mg-donation-recipient-quantity');
    quantity.type = 'number';
    quantity.min = '1';
    quantity.max = '1000';
    quantity.value = String(integer(parts.quantity.value, 1));
    quantity.setAttribute('data-recipient-quantity', '');
    quantity.hidden = state.mode !== 'custom';
    quantity.setAttribute('aria-label', 'Quantity for ' + (item.display_name || 'Community member'));
    row.appendChild(quantity);
    return row;
  }

  function renderRecipients(parts, state) {
    if (!state.assignments.length) {
      var empty = node('div', 'mg-community-empty');
      empty.appendChild(node('p', '', 'No active Community assignments are available for allocation.'));
      parts.recipients.replaceChildren(empty);
      return;
    }
    var fragment = document.createDocumentFragment();
    state.assignments.forEach(function (item) {
      fragment.appendChild(assignmentRow(parts, state, item));
    });
    parts.recipients.replaceChildren(fragment);
  }

  function populateTemplates(parts, state) {
    var current = parts.template.value;
    parts.template.replaceChildren();
    var placeholder = node('option', '', state.templates.length ? 'Choose a reward template' : 'No active reward templates');
    placeholder.value = '';
    parts.template.appendChild(placeholder);
    state.templates.forEach(function (template) {
      var remaining = template.remaining_inventory == null ? 'Unlimited' : String(template.remaining_inventory) + ' remaining';
      var option = node('option', '', template.title + ' · ' + money(template.value_cents, template.currency) + ' · ' + remaining);
      option.value = template.id;
      parts.template.appendChild(option);
    });
    if (current && state.templates.some(function (template) { return template.id === current; })) parts.template.value = current;
  }

  function operationRow(operation) {
    var row = node('article', 'mg-donation-operation-row');
    var copy = node('div');
    copy.appendChild(node('strong', '', operation.reward_template ? operation.reward_template.title : 'Reward allocation'));
    copy.appendChild(node('span', '', String(operation.completed_quantity || 0) + ' of ' + String(operation.requested_quantity || 0) + ' units · ' + String(operation.recipient_count || 0) + ' accounts'));
    copy.appendChild(node('small', '', money(operation.total_stated_value_cents, operation.currency) + ' · ' + statusText(operation.status)));
    row.appendChild(copy);
    var badge = node('span', 'mg-community-status is-' + String(operation.status || ''), statusText(operation.status));
    row.appendChild(badge);
    return row;
  }

  function renderHistory(parts, operations) {
    operations = safeArray(operations);
    if (!operations.length) {
      var empty = node('div', 'mg-community-empty');
      empty.appendChild(node('p', '', 'No allocation operations have been completed for this campaign.'));
      parts.historyList.replaceChildren(empty);
      return;
    }
    var fragment = document.createDocumentFragment();
    operations.forEach(function (operation) { fragment.appendChild(operationRow(operation)); });
    parts.historyList.replaceChildren(fragment);
  }

  function selectedRecipients(parts, state) {
    var rows = Array.from(parts.recipients.querySelectorAll('[data-assignment-id]'));
    var sameQuantity = Math.max(1, integer(parts.quantity.value, 1));
    return rows.filter(function (row) {
      var checkbox = row.querySelector('[data-recipient-check]');
      return checkbox && checkbox.checked;
    }).map(function (row) {
      var custom = row.querySelector('[data-recipient-quantity]');
      return {
        assignment_id: row.getAttribute('data-assignment-id') || '',
        quantity: state.mode === 'custom' ? Math.max(1, integer(custom ? custom.value : 1, 1)) : sameQuantity
      };
    });
  }

  function requestPayload(parts, state, action) {
    return {
      action: action,
      campaign_id: parts.campaignSelect ? String(parts.campaignSelect.value || '') : '',
      reward_template_id: String(parts.template.value || ''),
      recipients: selectedRecipients(parts, state),
      message: String(parts.message.value || '').trim(),
      internal_note: String(parts.internalNote.value || '').trim(),
      idempotency_key: state.idempotencyKey,
      confirm_large_operation: parts.confirmationInput.checked
    };
  }

  function metric(label, value) {
    var item = node('article');
    item.appendChild(node('span', '', label));
    item.appendChild(node('strong', '', value));
    return item;
  }

  function renderPreflight(parts, state, preflight) {
    var box = node('div', 'mg-donation-preflight-box');
    var title = node('div', 'mg-donation-preflight-title');
    title.appendChild(node('strong', '', 'Allocation preview'));
    title.appendChild(node('span', '', 'Inventory is checked but not reserved until execution.'));
    box.appendChild(title);
    var metrics = node('div', 'mg-donation-preflight-metrics');
    metrics.appendChild(metric('Accounts', preflight.recipient_count || 0));
    metrics.appendChild(metric('Units', preflight.requested_quantity || 0));
    metrics.appendChild(metric('Stated value', money(preflight.total_stated_value_cents, preflight.currency)));
    var remaining = preflight.inventory && preflight.inventory.available_after;
    metrics.appendChild(metric('Remaining', remaining == null ? 'Unlimited' : remaining));
    box.appendChild(metrics);
    if (preflight.large_operation) {
      box.appendChild(node('p', 'mg-donation-large-warning', 'Large-operation confirmation is required before execution.'));
      parts.confirmation.hidden = false;
    } else {
      parts.confirmation.hidden = true;
      parts.confirmationInput.checked = false;
    }
    parts.preview.replaceChildren(box);
    state.preflight = preflight;
    parts.allocateButton.disabled = false;
  }

  async function load(parts, state) {
    var campaignId = parts.campaignSelect ? String(parts.campaignSelect.value || '') : '';
    invalidate(parts, state);
    state.assignments = [];
    state.templates = [];
    renderRecipients(parts, state);
    renderHistory(parts, []);
    if (!campaignId) {
      setMessage(parts, 'Choose a Public Donations campaign before allocating rewards.');
      return;
    }
    setMessage(parts, 'Loading allocation workspace…');
    try {
      var response = await Microgifter.get('/api/merchant/public-donations-allocation.php?campaign_id=' + encodeURIComponent(campaignId));
      var data = apiData(response);
      if (data.schema_ready === false) {
        setMessage(parts, response.message || 'Allocation schema is unavailable.', 'error');
        return;
      }
      state.assignments = safeArray(data.active_assignments);
      state.templates = safeArray(data.reward_templates);
      populateTemplates(parts, state);
      renderRecipients(parts, state);
      renderHistory(parts, data.recent_operations);
      setMessage(parts, response.message || 'Allocation workspace ready.', 'success');
    } catch (error) {
      setMessage(parts, error.message || 'Unable to load reward allocation.', 'error');
    }
  }

  async function preflight(parts, state) {
    var payload = requestPayload(parts, state, 'preflight');
    if (!payload.campaign_id || !payload.reward_template_id || !payload.recipients.length) {
      setMessage(parts, 'Choose a campaign, reward template, and at least one Community account.', 'error');
      return;
    }
    parts.preflightButton.disabled = true;
    parts.allocateButton.disabled = true;
    setMessage(parts, 'Checking assignments, inventory, and stated value…');
    try {
      var response = await Microgifter.post('/api/merchant/public-donations-allocation.php', payload);
      var data = apiData(response);
      renderPreflight(parts, state, data.preflight || {});
      setMessage(parts, response.message || 'Allocation preview ready.', 'success');
    } catch (error) {
      invalidate(parts, state);
      setMessage(parts, error.message || 'Unable to preview allocation.', 'error');
    } finally {
      parts.preflightButton.disabled = false;
    }
  }

  async function allocate(parts, state) {
    if (!state.preflight) {
      setMessage(parts, 'Preview the allocation before issuing rewards.', 'error');
      return;
    }
    if (state.preflight.large_operation && !parts.confirmationInput.checked) {
      setMessage(parts, 'Confirm the large operation before issuing rewards.', 'error');
      return;
    }
    var payload = requestPayload(parts, state, 'allocate');
    parts.allocateButton.disabled = true;
    parts.preflightButton.disabled = true;
    setMessage(parts, 'Issuing canonical reward lifecycles…');
    try {
      var response = await Microgifter.post('/api/merchant/public-donations-allocation.php', payload);
      var data = apiData(response);
      var operation = data.operation || {};
      setMessage(parts, response.message || 'Rewards allocated successfully.', 'success');
      state.idempotencyKey = randomKey();
      state.preflight = null;
      parts.preview.replaceChildren();
      parts.confirmation.hidden = true;
      parts.confirmationInput.checked = false;
      await load(parts, state);
      if (operation.completed_quantity) {
        setMessage(parts, String(operation.completed_quantity) + ' rewards were delivered to Microgifter Inbox.', 'success');
      }
      var refresh = parts.panel.querySelector('[data-community-refresh]');
      if (refresh) refresh.click();
    } catch (error) {
      parts.allocateButton.disabled = false;
      setMessage(parts, error.message || 'Unable to allocate rewards.', 'error');
    } finally {
      parts.preflightButton.disabled = false;
    }
  }

  waitForAssignmentPanel(function (panel) {
    var parts = build(panel);
    if (!parts.campaignSelect) return;
    var state = {
      assignments: [],
      templates: [],
      mode: 'same',
      preflight: null,
      idempotencyKey: randomKey()
    };

    parts.campaignSelect.addEventListener('change', function () { load(parts, state); });
    parts.template.addEventListener('change', function () { invalidate(parts, state); });
    parts.mode.addEventListener('change', function () {
      state.mode = parts.mode.value === 'custom' ? 'custom' : 'same';
      parts.quantityLabel.hidden = state.mode === 'custom';
      parts.recipients.querySelectorAll('[data-recipient-quantity]').forEach(function (input) {
        input.hidden = state.mode !== 'custom';
      });
      invalidate(parts, state);
    });
    parts.quantity.addEventListener('input', function () { invalidate(parts, state); });
    parts.message.addEventListener('input', function () { invalidate(parts, state); });
    parts.internalNote.addEventListener('input', function () { invalidate(parts, state); });
    parts.recipients.addEventListener('change', function () { invalidate(parts, state); });
    parts.recipients.addEventListener('input', function (event) {
      if (event.target.matches('[data-recipient-quantity]')) invalidate(parts, state);
    });
    parts.selectAll.addEventListener('click', function () {
      var checks = Array.from(parts.recipients.querySelectorAll('[data-recipient-check]'));
      var shouldSelect = checks.some(function (check) { return !check.checked; });
      checks.forEach(function (check) { check.checked = shouldSelect; });
      parts.selectAll.textContent = shouldSelect ? 'Clear all' : 'Select all';
      invalidate(parts, state);
    });
    parts.preflightButton.addEventListener('click', function () { preflight(parts, state); });
    parts.allocateButton.addEventListener('click', function () { allocate(parts, state); });
    parts.confirmationInput.addEventListener('change', function () {
      parts.allocateButton.disabled = !state.preflight;
    });

    if (parts.campaignSelect.value) load(parts, state);
  });
});

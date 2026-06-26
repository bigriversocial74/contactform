window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var definitions = {};
  var modal = null;
  var form = null;
  var lastTrigger = null;

  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function parseDefinitions() {
    var node = document.getElementById('mg-share-market-action-definitions');
    if (!node) return {};
    try {
      var parsed = JSON.parse(node.textContent || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function definition(action) {
    return definitions[action] || null;
  }

  function setText(selector, value) {
    var node = qs(selector, modal || document);
    if (node) node.textContent = value === undefined || value === null || value === '' ? '—' : String(value);
  }

  function setStatus(message, tone) {
    var node = qs('[data-share-action-status]', modal);
    if (!node) return;
    node.textContent = message || '';
    node.className = 'sm-workflow-status' + (tone ? ' is-' + tone : '');
  }

  function hidePreview() {
    var preview = qs('[data-share-action-preview]', modal);
    if (preview) preview.hidden = true;
    var output = qs('[data-share-manifest-json]', modal);
    if (output) output.textContent = '';
  }

  function populateStates(item) {
    var wrapper = qs('[data-share-state-field]', modal);
    var select = qs('[data-share-current-state]', modal);
    if (!wrapper || !select) return;

    var states = Array.isArray(item.allowed_from_states) ? item.allowed_from_states : [];
    wrapper.hidden = states.length === 0;
    select.required = Boolean(item.current_state_required);
    select.innerHTML = '';

    if (!item.current_state_required) {
      var blank = document.createElement('option');
      blank.value = '';
      blank.textContent = 'Not required';
      select.appendChild(blank);
    }

    states.forEach(function (state) {
      var option = document.createElement('option');
      option.value = state;
      option.textContent = state.replace(/_/g, ' ');
      select.appendChild(option);
    });
  }

  function applyAction(action, preserveTarget) {
    var item = definition(action);
    if (!item || !form) return;

    form.elements.action.value = action;
    var actionSelect = qs('[data-share-action-select]', modal);
    if (actionSelect && actionSelect.value !== action) actionSelect.value = action;

    setText('[data-share-action-title]', item.label);
    setText('[data-share-event-type]', item.event_type);
    setText('[data-share-risk]', item.risk);
    setText('[data-share-approvals]', String(item.required_approvals) + (Number(item.required_approvals) === 1 ? ' approval' : ' approvals'));
    setText('[data-share-confirmation-phrase]', item.confirmation);

    var target = form.elements.target_id;
    if (target && (!preserveTarget || target.value.trim() === '')) target.value = item.default_target_id || '';

    var amountWrapper = qs('[data-share-amount-field]', modal);
    if (amountWrapper) amountWrapper.hidden = !item.amount_required;
    if (form.elements.amount) {
      form.elements.amount.required = Boolean(item.amount_required);
      if (!item.amount_required) form.elements.amount.value = '';
    }

    if (form.elements.admin_note) form.elements.admin_note.required = Boolean(item.note_required);
    if (form.elements.confirmation) form.elements.confirmation.value = '';

    populateStates(item);
    hidePreview();
    setStatus(item.description || '', '');
  }

  function openModal(action, trigger) {
    if (!modal || !form || !definition(action)) return;
    lastTrigger = trigger || null;
    applyAction(action, false);
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('sm-workflow-modal-open');
    window.setTimeout(function () {
      var target = form.elements.target_id;
      if (target) target.focus();
    }, 0);
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('sm-workflow-modal-open');
    setStatus('', '');
    hidePreview();
    if (lastTrigger && typeof lastTrigger.focus === 'function') lastTrigger.focus();
    lastTrigger = null;
  }

  function renderPreview(payload) {
    var data = payload && payload.data ? payload.data : payload;
    var manifest = data && data.manifest ? data.manifest : null;
    if (!manifest) throw new Error('The action manifest was not returned.');

    setText('[data-share-preview-id]', manifest.manifest_id);
    setText('[data-share-preview-event]', manifest.event_type);
    setText('[data-share-preview-target]', manifest.target_type + ':' + manifest.target_id);
    setText('[data-share-preview-state]', (manifest.current_state || '—') + ' → ' + (manifest.next_state || 'no state change'));
    setText('[data-share-preview-approvals]', manifest.required_approvals);
    setText('[data-share-preview-hash]', manifest.payload_hash);

    var output = qs('[data-share-manifest-json]', modal);
    if (output) output.textContent = JSON.stringify(manifest, null, 2);
    var preview = qs('[data-share-action-preview]', modal);
    if (preview) preview.hidden = false;

    var validation = {
      manifest: manifest,
      validation_token: data && data.validation_token ? data.validation_token : '',
      guardrails: data && data.guardrails ? data.guardrails : {},
      definition: data && data.definition ? data.definition : {}
    };
    MG.shareMarketValidation = validation;
    document.dispatchEvent(new CustomEvent('mg:share-market-manifest-validated', { detail: validation }));
    setStatus((payload && payload.message) || 'Action validated. No mutation was performed.', 'success');
  }

  async function submitAction(event) {
    event.preventDefault();
    if (!form || !form.reportValidity()) return;

    var button = qs('[data-share-action-submit]', form);
    var payload = MG.readForm(form);
    delete payload.action_select;

    hidePreview();
    setStatus('Validating permissions, transition rules, confirmation, and event hash…', '');
    if (button) MG.setBusy(button, true, 'Validating…');

    try {
      var response = await MG.post('/api/admin/share-market/validate-action.php', payload);
      renderPreview(response);
    } catch (error) {
      setStatus(error.message || 'Unable to validate this Share Market action.', 'error');
      if (MG.toast) MG.toast(error.message || 'Unable to validate this Share Market action.', 'error');
    } finally {
      if (button) MG.setBusy(button, false);
    }
  }

  function enhanceExistingControls() {
    var labelMap = {};
    Object.keys(definitions).forEach(function (key) {
      labelMap[String(definitions[key].label || '').toLowerCase()] = key;
    });

    qsa('.sm-admin-control').forEach(function (card) {
      if (card.querySelector('[data-share-action]')) return;
      var title = card.querySelector('strong');
      if (!title) return;
      var key = labelMap[title.textContent.trim().toLowerCase()];
      if (!key) return;
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'sm-workflow-open';
      button.setAttribute('data-share-action', key);
      button.textContent = 'Prepare action';
      card.appendChild(button);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = qs('[data-share-market-workflow]');
    modal = qs('[data-share-action-modal]');
    form = qs('[data-share-action-form]');
    if (!root || !modal || !form) return;

    definitions = parseDefinitions();
    enhanceExistingControls();

    document.addEventListener('click', function (event) {
      var open = event.target.closest('[data-share-action]');
      if (open) {
        openModal(open.getAttribute('data-share-action'), open);
        return;
      }
      if (event.target.closest('[data-share-action-close]')) closeModal();
    });

    var select = qs('[data-share-action-select]', modal);
    if (select) select.addEventListener('change', function () { applyAction(select.value, false); });
    form.addEventListener('submit', submitAction);

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
    });
  });
})(window, document);

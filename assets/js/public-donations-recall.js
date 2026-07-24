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

  function integer(value, fallback) {
    var parsed = Number.parseInt(String(value == null ? '' : value), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function randomKey() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return 'public-donation-recall:' + window.crypto.randomUUID();
    }
    return 'public-donation-recall:' + Date.now().toString(36) + ':' + Math.random().toString(36).slice(2);
  }

  function waitForAllocationCard(callback) {
    var existing = document.querySelector('[data-donation-allocation-card]');
    if (existing) {
      callback(existing);
      return;
    }
    var observer = new MutationObserver(function () {
      var card = document.querySelector('[data-donation-allocation-card]');
      if (!card) return;
      observer.disconnect();
      callback(card);
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function setMessage(parts, message, tone) {
    parts.status.textContent = message || '';
    parts.status.classList.toggle('is-error', tone === 'error');
    parts.status.classList.toggle('is-success', tone === 'success');
  }

  function countMetric(label, value, className) {
    var item = node('article', className || '');
    item.appendChild(node('span', '', label));
    item.appendChild(node('strong', '', String(value || 0)));
    return item;
  }

  function batchLabel(batch) {
    var community = batch.community || {};
    var reward = batch.reward_template || {};
    return (community.display_name || 'Community account') + ' · ' +
      (reward.title || 'Reward') + ' · ' +
      String(batch.quantity || 0) + ' allocated / ' + String(batch.recalled_quantity || 0) + ' recalled';
  }

  function build(allocationCard) {
    var card = node('section', 'mg-donation-recall-card');
    card.setAttribute('data-donation-recall-card', '');

    var head = node('header', 'mg-donation-recall-head');
    var copy = node('div');
    copy.appendChild(node('span', 'mg-eyebrow', 'Recall controls'));
    copy.appendChild(node('h3', '', 'Recall untouched Community rewards'));
    copy.appendChild(node('p', '', 'Only rewards still owned by the original Community account and never claimed, redeemed, expired, cancelled, or regifted can be recalled.'));
    head.appendChild(copy);
    head.appendChild(node('span', 'mg-donation-recall-protection', 'Downstream recipients protected'));
    card.appendChild(head);

    var controls = node('div', 'mg-donation-recall-controls');
    var batchLabelNode = node('label');
    batchLabelNode.appendChild(node('span', '', 'Allocation batch'));
    var batch = node('select');
    batch.setAttribute('data-recall-batch', '');
    batchLabelNode.appendChild(batch);
    controls.appendChild(batchLabelNode);

    var quantityLabel = node('label');
    quantityLabel.appendChild(node('span', '', 'Recall quantity'));
    var quantity = node('input');
    quantity.type = 'number';
    quantity.min = '1';
    quantity.max = '1000';
    quantity.value = '1';
    quantityLabel.appendChild(quantity);
    controls.appendChild(quantityLabel);

    var reasonLabel = node('label', 'mg-donation-recall-reason');
    reasonLabel.appendChild(node('span', '', 'Recall reason'));
    var reason = node('textarea');
    reason.rows = 3;
    reason.maxLength = 500;
    reason.placeholder = 'Required. This reason is preserved in the recall history.';
    reasonLabel.appendChild(reason);
    controls.appendChild(reasonLabel);
    card.appendChild(controls);

    var preview = node('div', 'mg-donation-recall-preview');
    preview.setAttribute('data-recall-preview', '');
    card.appendChild(preview);

    var actions = node('div', 'mg-donation-recall-actions');
    var refresh = node('button', 'mg-btn mg-btn-soft', 'Refresh preview');
    refresh.type = 'button';
    var recall = node('button', 'mg-btn mg-btn-danger', 'Recall eligible rewards');
    recall.type = 'button';
    recall.disabled = true;
    actions.appendChild(refresh);
    actions.appendChild(recall);
    card.appendChild(actions);

    var status = node('div', 'mg-donation-recall-status');
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    card.appendChild(status);

    allocationCard.insertAdjacentElement('afterend', card);
    return {
      card: card,
      campaignSelect: document.querySelector('[data-community-campaign-select]'),
      batch: batch,
      quantity: quantity,
      reason: reason,
      preview: preview,
      refresh: refresh,
      recall: recall,
      status: status
    };
  }

  function populateBatches(parts, state) {
    var current = parts.batch.value;
    parts.batch.replaceChildren();
    var placeholder = node('option', '', state.batches.length ? 'Choose an allocation batch' : 'No allocation batches');
    placeholder.value = '';
    parts.batch.appendChild(placeholder);
    state.batches.forEach(function (batch) {
      var option = node('option', '', batchLabel(batch));
      option.value = batch.id || '';
      parts.batch.appendChild(option);
    });
    if (current && state.batches.some(function (batch) { return batch.id === current; })) {
      parts.batch.value = current;
    }
  }

  function renderPreview(parts, state) {
    var preview = state.preview;
    if (!preview) {
      var empty = node('div', 'mg-community-empty');
      empty.appendChild(node('p', '', 'Choose an allocation batch to inspect recall eligibility.'));
      parts.preview.replaceChildren(empty);
      parts.recall.disabled = true;
      return;
    }

    var box = node('div', 'mg-donation-recall-preview-box');
    var summary = node('div', 'mg-donation-recall-preview-title');
    summary.appendChild(node('strong', '', (preview.community && preview.community.display_name) || 'Community account'));
    summary.appendChild(node('span', '', ((preview.reward_template && preview.reward_template.title) || 'Reward') + ' · batch ' + ((preview.batch && preview.batch.id) || '')));
    box.appendChild(summary);

    var counts = preview.counts || {};
    var metrics = node('div', 'mg-donation-recall-metrics');
    metrics.appendChild(countMetric('Original', counts.original));
    metrics.appendChild(countMetric('Recallable', counts.recallable, 'is-recallable'));
    metrics.appendChild(countMetric('Regifted', counts.regifted));
    metrics.appendChild(countMetric('Claimed', counts.claimed));
    metrics.appendChild(countMetric('Redeemed', counts.redeemed));
    metrics.appendChild(countMetric('Expired', counts.expired));
    metrics.appendChild(countMetric('Already recalled', counts.already_recalled));
    box.appendChild(metrics);

    var maximum = integer(preview.maximum_recall_quantity, 0);
    parts.quantity.max = String(Math.max(1, maximum));
    if (integer(parts.quantity.value, 1) > maximum && maximum > 0) parts.quantity.value = String(maximum);
    var note = node('p', 'mg-donation-recall-note');
    note.textContent = maximum > 0
      ? 'Up to ' + String(maximum) + ' untouched reward' + (maximum === 1 ? '' : 's') + ' can be recalled now. Eligibility is checked again inside the transaction.'
      : 'No rewards in this batch are currently recallable.';
    box.appendChild(note);
    parts.preview.replaceChildren(box);
    parts.recall.disabled = maximum < 1 || !String(parts.reason.value || '').trim();
  }

  async function load(parts, state, preserveBatch) {
    var campaignId = parts.campaignSelect ? String(parts.campaignSelect.value || '') : '';
    var selectedBatch = preserveBatch ? String(parts.batch.value || '') : '';
    state.preview = null;
    state.batches = [];
    renderPreview(parts, state);
    setMessage(parts, 'Loading recall batches…');
    try {
      var url = '/api/merchant/public-donations-recall.php';
      if (campaignId) url += '?campaign_id=' + encodeURIComponent(campaignId);
      var response = await Microgifter.get(url);
      var data = apiData(response);
      if (data.schema_ready === false) {
        setMessage(parts, response.message || 'Recall schema is unavailable.', 'error');
        return;
      }
      state.batches = safeArray(data.batches);
      populateBatches(parts, state);
      if (selectedBatch && state.batches.some(function (batch) { return batch.id === selectedBatch; })) {
        parts.batch.value = selectedBatch;
        await loadPreview(parts, state);
        return;
      }
      setMessage(parts, response.message || 'Recall workspace ready.', 'success');
    } catch (error) {
      setMessage(parts, error.message || 'Unable to load recall controls.', 'error');
    }
  }

  async function loadPreview(parts, state) {
    var batchId = String(parts.batch.value || '');
    state.preview = null;
    renderPreview(parts, state);
    if (!batchId) {
      setMessage(parts, 'Choose an allocation batch to preview recall eligibility.');
      return;
    }
    setMessage(parts, 'Recalculating recall eligibility…');
    parts.refresh.disabled = true;
    try {
      var response = await Microgifter.get('/api/merchant/public-donations-recall.php?batch_id=' + encodeURIComponent(batchId));
      var data = apiData(response);
      state.preview = data.preview || null;
      renderPreview(parts, state);
      setMessage(parts, response.message || 'Recall preview ready.', 'success');
    } catch (error) {
      setMessage(parts, error.message || 'Unable to preview recall eligibility.', 'error');
    } finally {
      parts.refresh.disabled = false;
    }
  }

  async function executeRecall(parts, state) {
    var batchId = String(parts.batch.value || '');
    var maximum = state.preview ? integer(state.preview.maximum_recall_quantity, 0) : 0;
    var quantity = integer(parts.quantity.value, 0);
    var reason = String(parts.reason.value || '').trim();
    if (!batchId || maximum < 1 || quantity < 1 || quantity > maximum || !reason) {
      setMessage(parts, 'Choose a batch, enter a valid quantity, and provide a recall reason.', 'error');
      return;
    }
    var confirmed = window.confirm('Recall ' + String(quantity) + ' untouched reward' + (quantity === 1 ? '' : 's') + '? This cannot affect regifted or claimed rewards.');
    if (!confirmed) return;

    parts.recall.disabled = true;
    parts.refresh.disabled = true;
    setMessage(parts, 'Recalling eligible Wallet, PPPM, Microgift, and Inbox records…');
    try {
      var response = await Microgifter.post('/api/merchant/public-donations-recall.php', {
        action: 'recall',
        batch_id: batchId,
        quantity: quantity,
        reason: reason,
        idempotency_key: state.idempotencyKey
      });
      var data = apiData(response);
      var operation = data.operation || {};
      state.idempotencyKey = randomKey();
      state.preview = operation.preview_after || null;
      renderPreview(parts, state);
      setMessage(parts, response.message || 'Recall completed.', 'success');
      await load(parts, state, true);
      var allocationCampaign = document.querySelector('[data-community-campaign-select]');
      if (allocationCampaign) allocationCampaign.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (error) {
      setMessage(parts, error.message || 'Unable to recall rewards.', 'error');
    } finally {
      parts.refresh.disabled = false;
      renderPreview(parts, state);
    }
  }

  waitForAllocationCard(function (allocationCard) {
    var parts = build(allocationCard);
    var state = { batches: [], preview: null, idempotencyKey: randomKey() };

    if (parts.campaignSelect) {
      parts.campaignSelect.addEventListener('change', function () { load(parts, state, false); });
    }
    parts.batch.addEventListener('change', function () { loadPreview(parts, state); });
    parts.quantity.addEventListener('input', function () { renderPreview(parts, state); });
    parts.reason.addEventListener('input', function () { renderPreview(parts, state); });
    parts.refresh.addEventListener('click', function () { loadPreview(parts, state); });
    parts.recall.addEventListener('click', function () { executeRecall(parts, state); });

    load(parts, state, false);
  });
});

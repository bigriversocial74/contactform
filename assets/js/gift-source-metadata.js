document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-gift-center]');
  if (!app || !window.Microgifter) return;

  var sourceMap = Object.create(null);
  var loading = false;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function parseMetadata(item) {
    try {
      if (typeof item.metadata_json === 'string' && item.metadata_json !== '') return JSON.parse(item.metadata_json) || {};
      if (item.metadata_json && typeof item.metadata_json === 'object') return item.metadata_json;
    } catch (error) {}
    return {};
  }

  function fallbackLabel(system) {
    system = String(system || '').trim();
    if (!system) return '';
    if (system === 'campaigns' || system === 'campaign_reward') return 'Campaign Rewards';
    if (system === 'store_canvas') return 'Store Canvas';
    if (system === 'in_out_box' || system === 'action_center') return 'IN/OUT Box';
    return system.replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function remember(item, force) {
    if (!item || !item.action_item_id) return;
    var metadata = parseMetadata(item);
    var system = item.source_system || metadata.source_system || metadata.sourceSystem || metadata.system || '';
    var label = item.source_label || metadata.source_label || metadata.sourceLabel || fallbackLabel(system);
    var detail = item.source_detail || metadata.source_detail || metadata.sourceDetail || metadata.campaign_title || metadata.campaign_type || '';
    var reference = item.source_reference || metadata.source_reference || metadata.sourceReference || metadata.wallet_item_id || '';
    if (!label && !system && !detail && !reference) return;
    if (!force && sourceMap[item.action_item_id] && sourceMap[item.action_item_id].system === 'store_canvas') return;
    sourceMap[item.action_item_id] = {
      system: String(system || '').trim(),
      label: String(label || '').trim(),
      detail: String(detail || '').trim(),
      reference: String(reference || '').trim()
    };
  }

  async function loadSources() {
    if (loading) return;
    loading = true;
    try {
      await Promise.all(['inbox', 'sent', 'claimed'].map(async function (folder) {
        var response = await Microgifter.get('/api/account/action-center.php?folder=' + encodeURIComponent(folder) + '&limit=100');
        var data = response.data || response;
        (data.items || []).forEach(function (item) { remember(item, false); });
      }));
      try {
        var overrideResponse = await Microgifter.get('/api/account/wallet-source-metadata.php?limit=200');
        var overrideData = overrideResponse.data || overrideResponse;
        (overrideData.items || []).forEach(function (item) { remember(item, true); });
      } catch (overrideError) {}
      decorateRows(true);
    } catch (error) {
      console.error(error);
    } finally {
      loading = false;
    }
  }

  function decorateRows(refresh) {
    app.querySelectorAll('[data-gift-id]').forEach(function (row) {
      if (refresh) {
        row.querySelectorAll('[data-gift-source-meta]').forEach(function (node) { node.remove(); });
      } else if (row.querySelector('[data-gift-source-meta]')) return;
      var source = sourceMap[row.dataset.giftId];
      if (!source || (!source.label && !source.system)) return;
      var meta = row.querySelector('.mg-gift-row-meta');
      if (!meta) return;
      var label = source.label || fallbackLabel(source.system);
      var detail = source.detail ? ' · ' + source.detail : '';
      var span = document.createElement('span');
      span.setAttribute('data-gift-source-meta', 'true');
      span.title = source.reference ? 'Source reference: ' + source.reference : '';
      span.innerHTML = 'Source: ' + esc(label + detail);
      meta.prepend(span);
    });
  }

  var list = app.querySelector('[data-gift-list]');
  if (list) {
    new MutationObserver(function () {
      decorateRows(false);
      if (!Object.keys(sourceMap).length) loadSources();
    }).observe(list, { childList: true, subtree: true });
  }

  loadSources();
});

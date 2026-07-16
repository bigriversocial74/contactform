document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-saved-opportunities]');
  if (!root || !window.Microgifter) return;
  var grid = root.querySelector('[data-saved-opportunity-grid]');
  var status = root.querySelector('[data-saved-opportunity-status]');

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' })[character];
    });
  }
  function actionFor(item) {
    if (item.entity_type === 'campaign') return 'join_campaign';
    if (item.entity_type === 'merchant') return 'view_merchant';
    return 'open_product';
  }
  function destination(item) {
    var url = String(item.destination_url || '');
    var separator = url.indexOf('?') === -1 ? '?' : '&';
    return url + separator + 'agent_attribution=' + encodeURIComponent(item.attribution_token || '')
      + '&agent_opportunity=' + encodeURIComponent(item.id || '')
      + '&agent_action=' + encodeURIComponent(actionFor(item));
  }
  function remember(item) {
    try {
      window.sessionStorage.setItem('mg:agent-attribution:v1', JSON.stringify({
        token:item.attribution_token, opportunity_id:item.id, action:actionFor(item), saved_at:new Date().toISOString()
      }));
    } catch (_) { /* optional */ }
  }
  function render(items) {
    if (!grid) return;
    if (!items.length) {
      grid.innerHTML = '<div class="mg-saved-opportunities-empty"><strong>No saved opportunities yet</strong><p>Save a product, campaign, experience, or merchant from your Personal Agent recommendations.</p></div>';
      return;
    }
    grid.innerHTML = items.map(function (item) {
      return '<article class="mg-saved-opportunity-card" data-saved-opportunity-card="' + esc(item.id) + '">'
        + '<div><small>' + esc(item.entity_type) + '</small><h3>' + esc(item.title) + '</h3><small>Saved ' + esc(item.updated_at || item.created_at || '') + '</small></div>'
        + '<div class="mg-saved-opportunity-actions"><a href="' + esc(destination(item)) + '" data-saved-opportunity-open="' + esc(item.id) + '">Open</a>'
        + '<button type="button" data-saved-opportunity-remove="' + esc(item.id) + '">Remove</button></div></article>';
    }).join('');
    items.forEach(function (item) {
      var link = grid.querySelector('[data-saved-opportunity-open="' + CSS.escape(item.id) + '"]');
      if (link) link.addEventListener('click', function () { remember(item); });
    });
  }
  function load() {
    if (status) status.textContent = 'Loading saved opportunities…';
    window.Microgifter.get('/api/user-agent/opportunities.php?state=saved&limit=50').then(function (response) {
      var data = response && response.data ? response.data : response || {};
      render(Array.isArray(data.items) ? data.items : []);
      if (status) status.textContent = data.count ? data.count + ' saved' : '';
    }).catch(function (error) {
      if (status) status.textContent = error.message || 'Saved opportunities are unavailable.';
      if (grid) grid.innerHTML = '<div class="mg-saved-opportunities-empty">Saved opportunities are unavailable until the attribution migration is imported.</div>';
    });
  }
  root.addEventListener('click', function (event) {
    var button = event.target.closest('[data-saved-opportunity-remove]');
    if (!button) return;
    button.disabled = true;
    window.Microgifter.post('/api/user-agent/opportunity-action.php', {
      opportunity_id:button.getAttribute('data-saved-opportunity-remove'), action:'unsave'
    }).then(function () {
      var card = button.closest('[data-saved-opportunity-card]');
      if (card) card.remove();
      if (grid && !grid.querySelector('[data-saved-opportunity-card]')) render([]);
    }).catch(function (error) {
      button.disabled = false;
      if (status) status.textContent = error.message || 'Unable to remove saved opportunity.';
    });
  });
  load();
});

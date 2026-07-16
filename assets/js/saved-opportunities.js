document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-saved-opportunities]');
  if (!root || !window.Microgifter) return;
  var grid = root.querySelector('[data-saved-opportunity-grid]');
  var status = root.querySelector('[data-saved-opportunity-status]');
  var selectedId = new URLSearchParams(window.location.search).get('opportunity') || '';
  var activeFilter = 'all';
  var allItems = [];

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' })[character];
    });
  }
  function typeKey(item) {
    return String(item && item.entity_type || '').toLowerCase().trim();
  }
  function typeLabel(item) {
    var key = typeKey(item) || 'opportunity';
    return key.charAt(0).toUpperCase() + key.slice(1);
  }
  function actionFor(item) {
    if (item.entity_type === 'campaign') return 'join_campaign';
    if (item.entity_type === 'merchant') return 'view_merchant';
    return 'open_product';
  }
  function destination(item) {
    var url = String(item.destination_url || '/');
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
  function filteredItems() {
    if (activeFilter === 'all') return allItems.slice();
    return allItems.filter(function (item) { return typeKey(item) === activeFilter; });
  }
  function updateFilterButtons() {
    root.querySelectorAll('[data-saved-opportunity-filter]').forEach(function (button) {
      var isActive = button.getAttribute('data-saved-opportunity-filter') === activeFilter;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }
  function updateStatus(visibleCount) {
    if (!status) return;
    if (!allItems.length) {
      status.textContent = '';
      return;
    }
    status.textContent = activeFilter === 'all'
      ? allItems.length + ' saved'
      : visibleCount + ' shown of ' + allItems.length + ' saved';
  }
  function render() {
    if (!grid) return;
    var items = filteredItems();
    updateFilterButtons();
    updateStatus(items.length);
    if (!allItems.length) {
      grid.innerHTML = '<div class="mg-saved-opportunities-empty"><strong>No saved items yet</strong><p>Save a product, campaign, experience, reward, or merchant from your Personal Agent recommendations.</p></div>';
      return;
    }
    if (!items.length) {
      grid.innerHTML = '<div class="mg-saved-opportunities-empty"><strong>No saved ' + esc(activeFilter) + ' items</strong><p>Choose another category or save one from a Personal Agent recommendation.</p></div>';
      return;
    }
    grid.innerHTML = items.map(function (item) {
      var selected = item.id === selectedId;
      return '<article class="mg-saved-opportunity-card' + (selected ? ' is-recovery-target' : '') + '" data-saved-opportunity-card="' + esc(item.id) + '" data-entity-type="' + esc(typeKey(item)) + '">'
        + '<div class="mg-saved-opportunity-copy"><small>' + esc(typeLabel(item)) + '</small><h3>' + esc(item.title) + '</h3><small>Saved ' + esc(item.updated_at || item.created_at || '') + '</small></div>'
        + '<div class="mg-saved-opportunity-actions"><a href="' + esc(destination(item)) + '" data-saved-opportunity-open="' + esc(item.id) + '">Open</a>'
        + '<button type="button" data-saved-opportunity-remind="' + esc(item.id) + '" data-token="' + esc(item.attribution_token) + '">Remind me</button>'
        + '<button class="is-danger" type="button" data-saved-opportunity-remove="' + esc(item.id) + '" aria-label="Unsave ' + esc(item.title) + '">Unsave</button></div></article>';
    }).join('');
    items.forEach(function (item) {
      var link = grid.querySelector('[data-saved-opportunity-open="' + CSS.escape(item.id) + '"]');
      if (link) link.addEventListener('click', function () { remember(item); });
    });
    if (selectedId) {
      var selected = grid.querySelector('[data-saved-opportunity-card="' + CSS.escape(selectedId) + '"]');
      if (selected) selected.scrollIntoView({ behavior:'smooth', block:'center' });
    }
  }
  function load() {
    if (status) status.textContent = 'Loading saved items…';
    window.Microgifter.get('/api/user-agent/opportunities.php?state=saved&limit=50').then(function (response) {
      var data = response && response.data ? response.data : response || {};
      allItems = Array.isArray(data.items) ? data.items : [];
      render();
    }).catch(function (error) {
      if (status) status.textContent = error.message || 'Saved items are unavailable.';
      if (grid) grid.innerHTML = '<div class="mg-saved-opportunities-empty">Saved items are unavailable until the attribution migration is imported.</div>';
    });
  }
  root.addEventListener('click', function (event) {
    var filter = event.target.closest('[data-saved-opportunity-filter]');
    if (filter) {
      activeFilter = filter.getAttribute('data-saved-opportunity-filter') || 'all';
      render();
      return;
    }
    var remind = event.target.closest('[data-saved-opportunity-remind]');
    if (remind) {
      remind.disabled = true;
      if (status) status.textContent = 'Scheduling reminder…';
      window.Microgifter.post('/api/user-agent/opportunity-recovery.php', {
        action:'schedule',
        opportunity_id:remind.getAttribute('data-saved-opportunity-remind'),
        attribution_token:remind.getAttribute('data-token'),
        delay_hours:24,
        page_path:window.location.pathname + window.location.search
      }).then(function () {
        remind.textContent = 'Reminder set';
        updateStatus(filteredItems().length);
      }).catch(function (error) {
        remind.disabled = false;
        if (status) status.textContent = error.message || 'Unable to schedule reminder.';
      });
      return;
    }
    var button = event.target.closest('[data-saved-opportunity-remove]');
    if (!button) return;
    var opportunityId = button.getAttribute('data-saved-opportunity-remove') || '';
    button.disabled = true;
    button.textContent = 'Removing…';
    if (status) status.textContent = 'Removing saved item…';
    window.Microgifter.post('/api/user-agent/opportunity-action.php', {
      opportunity_id:opportunityId, action:'unsave'
    }).then(function () {
      allItems = allItems.filter(function (item) { return item.id !== opportunityId; });
      render();
    }).catch(function (error) {
      button.disabled = false;
      button.textContent = 'Unsave';
      if (status) status.textContent = error.message || 'Unable to remove saved item.';
    });
  });
  load();
});
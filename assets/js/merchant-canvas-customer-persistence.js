window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !window.Microgifter) return;
  var drawer = document.querySelector('[data-canvas-drawer]') || root.querySelector('[data-canvas-drawer]');
  if (!drawer) return;

  var storageKey = 'mgCustomerCrmNotes:v1';
  var endpoint = '/api/merchant/customer-crm-state.php';
  var hydrated = {};

  function qs(selector, scope) { return (scope || drawer).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || drawer).querySelectorAll(selector)); }
  function clean(value) { return String(value || '').replace(/\s+/g, ' ').trim(); }
  function drawerName() { var node = qs('[data-drawer-name]'); return clean(node ? node.textContent : '') || 'Customer'; }
  function selectedSessionId() {
    var name = drawerName().toLowerCase();
    var cards = Array.from(root.querySelectorAll('.mg-canvas-avatar-card[data-session-id]'));
    var matched = cards.find(function (card) { return clean((card.querySelector('strong') || {}).textContent || '').toLowerCase() === name; });
    return matched ? (matched.dataset.sessionId || '') : (cards[0] ? cards[0].dataset.sessionId || '' : '');
  }
  function noteId() { return selectedSessionId() || drawerName().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'customer'; }
  function readStore() {
    try { return JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {}; } catch (error) { return {}; }
  }
  function writeStore(store) {
    try { window.localStorage.setItem(storageKey, JSON.stringify(store || {})); } catch (error) {}
  }
  function syncLocal(data) {
    if (!data || !data.found) return;
    var store = readStore();
    store[noteId()] = { tags: Array.isArray(data.tags) ? data.tags : [], note: data.note || '', updatedAt: new Date().toISOString(), backend: true, contactId: data.contact && data.contact.id ? data.contact.id : '' };
    writeStore(store);
  }
  function payload(extra) {
    var data = Object.assign({
      session_id: selectedSessionId(),
      customer_name: drawerName()
    }, extra || {});
    var local = readStore()[noteId()] || {};
    if (local.contactId && !data.contact_id) data.contact_id = local.contactId;
    return data;
  }
  async function hydrate() {
    if (!window.Microgifter.get || !drawer.classList.contains('is-open')) return;
    var key = noteId();
    if (hydrated[key]) return;
    hydrated[key] = true;
    try {
      var q = new URLSearchParams(payload()).toString();
      var res = await window.Microgifter.get(endpoint + '?' + q);
      var data = res.data || res;
      syncLocal(data);
      var panel = qs('[data-customer-panel="notes"]');
      if (panel && !panel.hidden) document.dispatchEvent(new CustomEvent('mg:customerCrmStateSynced', { detail: data }));
    } catch (error) {}
  }
  async function saveNotes(panel) {
    if (!window.Microgifter.post) return;
    panel = panel || qs('[data-customer-panel="notes"]') || drawer;
    var tags = qsa('[data-customer-note-tag].is-active', panel).map(function (node) { return node.dataset.customerNoteTag || node.textContent || ''; }).filter(Boolean);
    var text = clean((qs('[data-customer-note-text]', panel) || {}).value || '');
    var status = qs('[data-customer-note-status]', panel);
    if (status) status.textContent = 'Saving to CRM...';
    try {
      var res = await window.Microgifter.post(endpoint, payload({ tags: tags, note: text, action_status: { source: 'store_canvas_customer_crm', active_tab: drawer.dataset.customerActiveTab || '' } }));
      var data = res.data || res;
      syncLocal(data);
      if (status) status.textContent = 'Saved to Merchant CRM';
      document.dispatchEvent(new CustomEvent('mg:customerCrmStateSynced', { detail: data }));
    } catch (error) {
      if (status) status.textContent = (error && error.message) ? error.message : 'Saved locally. CRM sync failed.';
    }
  }

  drawer.addEventListener('click', function (event) {
    if (event.target.closest('[data-customer-tab]')) window.setTimeout(hydrate, 80);
    var save = event.target.closest('[data-customer-note-save]');
    if (save) window.setTimeout(function () { saveNotes(save.closest('[data-customer-panel="notes"]') || drawer); }, 60);
  }, true);

  var observer = new MutationObserver(function () { window.setTimeout(hydrate, 120); });
  observer.observe(drawer, { attributes: true, attributeFilter: ['class', 'aria-hidden'], childList: true, subtree: true });
  document.addEventListener('mg:storeCanvasIntelligenceLoaded', hydrate);
  window.addEventListener('beforeunload', function () { observer.disconnect(); });
})(window, document);

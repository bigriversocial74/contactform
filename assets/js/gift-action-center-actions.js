document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (window.__mgActionCenterMutationsV1Booted) return;
  window.__mgActionCenterMutationsV1Booted = true;

  var app = document.querySelector('[data-gift-center]');
  if (!app || !window.Microgifter) return;
  var modalBody = app.querySelector('[data-action-modal-body]');
  var list = app.querySelector('[data-gift-list]');
  var ACTIVE = ['send','follow-up','claim','message','tip'];
  var STATE = ['read','unread','archive','restore'];
  var inFlight = new Map();

  function runtime() { return window.MicrogifterActionCenterRuntime || null; }
  function responseData(response) { return response && response.data ? response.data : (response || {}); }
  function text(value) { return String(value == null ? '' : value).trim(); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }
  function object(value) { return value && typeof value === 'object' && !Array.isArray(value) ? value : {}; }
  function key(type, item) {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return 'ac-' + type + '-' + window.crypto.randomUUID();
    return 'ac-' + type + '-' + String(item.action_item_id || 'item') + '-' + Date.now();
  }
  function endpoint(type){return '/api/account/action-center-'+type+'.php';}
  function contractView(item) {
    var adapter = window.MicrogifterActionCenterContract;
    return adapter && typeof adapter.view === 'function' ? adapter.view(item) : item;
  }
  function selectedContract() {
    var api = runtime();
    if (!api) return null;
    if (typeof api.getSelectedContract === 'function') return api.getSelectedContract();
    var row = list && list.querySelector('.mg-gift-row.is-active[data-gift-id]');
    return row && typeof api.getContract === 'function' ? api.getContract(row.dataset.giftId) : null;
  }
  function actionId(item) { return text(item && item.action_item_id); }
  function capabilities(item) { return object(item && item.capabilities); }
  function capabilityName(type) { return type === 'follow-up' ? 'follow_up' : type; }
  function assertCapability(type, item) {
    var capability = capabilityName(type);
    if (!Object.prototype.hasOwnProperty.call(capabilities(item), capability)) return;
    if (capabilities(item)[capability]) return;
    var reasons = object(item.capability_reasons);
    throw new Error(text(reasons[capability]) || 'This action is no longer available.');
  }
  function currency(item) {
    var snapshot = object(object(object(item).gift).snapshot);
    return text(snapshot.currency) || 'USD';
  }

  function payload(type,item,data){
    var request={action_item_id:item.action_item_id,idempotency_key:key(type,item)};
    if(type==='send'){
      request.recipient_user_id=data.recipient_user_id||data.recipient_profile_id||data.recipient_slug||'';
      request.recipient=request.recipient_user_id||data.recipient||'';
      request.recipient_slug=data.recipient_slug||'';
      request.message=data.message||'';
    }else if(type==='follow-up'||type==='message'){
      request.message=data.message||'';
    }else if(type==='claim'){
      request.code=data.claim_code||'';
    }else if(type==='tip'){
      request.amount_cents=Math.round(Number(data.amount||0)*100);
      request.currency=currency(item);
      request.funding_type='wallet';
      request.message=data.message||'';
    }
    return request;
  }

  function preferredActionItemId(type, originalId, data) {
    var actionCenter = object(data.action_center);
    if (type === 'send') return text(actionCenter.sent_item_id);
    return text(data.action_item_id || originalId);
  }
  function formatTimestamp(value) {
    var date = value ? new Date(value) : new Date();
    if (Number.isNaN(date.getTime())) date = new Date();
    return date.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit' });
  }
  function timestampFrom(type, data) {
    return (data.delivery_event && data.delivery_event.occurred_at) ||
      (data.delivery_summary && data.delivery_summary.sent_at) || data.created_at || data.sent_at ||
      (type === 'message' || type === 'tip' ? new Date().toISOString() : '');
  }
  function result(title, message, timestamp) {
    if (!modalBody) return;
    var time = timestamp ? '<p><strong>Timestamp:</strong> ' + esc(formatTimestamp(timestamp)) + '</p>' : '';
    modalBody.innerHTML = '<div class="mg-action-success"><strong>' + esc(title) + '</strong><p>' + esc(message) + '</p>' + time + '<button class="mg-btn mg-btn-primary" type="button" data-action-modal-close>Done</button></div>';
  }
  function setCounts(counts) {
    ['inbox','sent','claimed'].forEach(function (folder) {
      var value = Number(object(counts)[folder] && object(counts)[folder].total || 0);
      var unread = Number(object(counts)[folder] && object(counts)[folder].unread || 0);
      document.querySelectorAll('[data-gift-count="' + folder + '"],[data-gift-nav-count="' + folder + '"]').forEach(function (node) { node.textContent = String(value); });
      document.querySelectorAll('[data-gift-nav-unread="' + folder + '"]').forEach(function (node) {
        node.textContent = String(unread); node.hidden = unread <= 0; node.classList.toggle('has-unread', unread > 0);
      });
    });
  }
  async function applyEnvelope(envelope, options) {
    envelope = object(envelope);
    options = object(options);
    if (Number(envelope.mutation_contract_version || 0) !== 1) throw new Error('Unsupported Action Center mutation response.');
    setCounts(envelope.counts);
    var api = runtime();
    if (options.refresh !== false && api && typeof api.refresh === 'function') await api.refresh();
    app.dispatchEvent(new CustomEvent('mg:action-center:mutation-applied', { bubbles: true, detail: envelope }));
    return envelope;
  }
  async function synchronize(type, originalId, preferredId, duplicate, options) {
    var response = await Microgifter.post('/api/account/action-center-mutation-state.php', {
      action: type,
      action_item_id: originalId,
      preferred_action_item_id: preferredId || '',
      duplicate: duplicate ? 1 : 0
    });
    return applyEnvelope(responseData(response), options);
  }

  async function execute(type, item, data) {
    if (!ACTIVE.includes(type)) throw new Error('Unsupported Action Center action.');
    item = item || selectedContract();
    if (!item || !actionId(item)) throw new Error('Select an Action Center item first.');
    assertCapability(type, item);
    var lock = type + '|' + actionId(item);
    if (inFlight.has(lock)) return inFlight.get(lock);
    var operation = (async function () {
      var response = await Microgifter.post(endpoint(type),payload(type,item,data||{}));
      var raw = responseData(response);
      var envelope = await synchronize(type, actionId(item), preferredActionItemId(type, actionId(item), raw), !!raw.duplicate);
      return { response: response, data: raw, mutation: envelope };
    })();
    inFlight.set(lock, operation);
    try { return await operation; } finally { inFlight.delete(lock); }
  }

  async function executeState(type, itemOrId) {
    if (!STATE.includes(type)) throw new Error('Unsupported Action Center state action.');
    var id = typeof itemOrId === 'string' ? text(itemOrId) : actionId(itemOrId || selectedContract());
    if (!id) throw new Error('Select an Action Center item first.');
    var response = await Microgifter.post(endpoint(type), { action_item_id: id });
    return applyEnvelope(responseData(response));
  }

  async function finalizeExternal(type, originalId, raw) {
    raw = object(raw);
    originalId = text(originalId || raw.action_item_id || raw.gift_id);
    if (!originalId) return null;
    return synchronize(type, originalId, preferredActionItemId(type, originalId, object(raw.response || raw)), !!raw.duplicate, { refresh: false });
  }

  function enhanceSendAutocomplete() {
    var form = modalBody && modalBody.querySelector('[data-action-form="send"]');
    if (!form || form.classList.contains('mg-send-exact-form') || form.querySelector('[data-recipient-autocomplete]')) return;
    var input = form.querySelector('input[name="recipient"]');
    var label = input && input.closest('label');
    if (!label) return;
    label.innerHTML = 'Regift to<div class="mg-recipient-autocomplete" data-recipient-autocomplete><input type="search" name="recipient" data-recipient-search autocomplete="off" placeholder="Start typing a follower or user" required><input type="hidden" name="recipient_user_id"><div class="mg-recipient-results" data-recipient-results><div class="mg-recipient-empty">Start typing to find followers and users.</div></div></div>';
  }
  function enhanceTipForm() {
    var form = modalBody && modalBody.querySelector('[data-action-form="tip"]');
    if (!form || form.dataset.tipEnhanced === 'true') return;
    form.dataset.tipEnhanced = 'true';
    var amount = form.querySelector('input[name="amount"]');
    if (amount && !amount.value) amount.value = '5.00';
  }
  if (modalBody) new MutationObserver(function () { enhanceSendAutocomplete(); enhanceTipForm(); }).observe(modalBody, { childList: true, subtree: true });

  async function loadRecipientOptions(input) {
    var wrap = input.closest('[data-recipient-autocomplete]');
    var results = wrap && wrap.querySelector('[data-recipient-results]');
    var hidden = wrap && wrap.querySelector('input[name="recipient_user_id"]');
    if (!results || !hidden) return;
    hidden.value = '';
    var q = input.value.trim();
    if (q.length < 2) { results.innerHTML = '<div class="mg-recipient-empty">Type at least 2 characters.</div>'; return; }
    try {
      var response = await Microgifter.get('/api/account/action-center-recipient-search.php?q=' + encodeURIComponent(q));
      var items = responseData(response).recipients || [];
      results.innerHTML = items.length ? items.map(function (x) {
        return '<button type="button" data-recipient-option data-recipient-id="' + esc(x.recipient_user_id) + '" data-recipient-label="' + esc(x.display_name) + '"><strong>' + esc(x.display_name) + '</strong><span>' + esc(x.email_hint || x.source || 'Microgifter member') + '</span></button>';
      }).join('') : '<div class="mg-recipient-empty">No matching followers and users.</div>';
    } catch (error) { results.innerHTML = '<div class="mg-recipient-empty">Unable to search recipients.</div>'; }
  }

  if (modalBody) {
    modalBody.addEventListener('input', function (event) {
      var input = event.target.closest('[data-recipient-search]');
      if (!input) return;
      clearTimeout(input._recipientTimer);
      input._recipientTimer = setTimeout(function () { loadRecipientOptions(input); }, 180);
    });
    modalBody.addEventListener('click', function (event) {
      var option = event.target.closest('[data-recipient-option]');
      if (!option) return;
      var wrap = option.closest('[data-recipient-autocomplete]');
      var input = wrap && wrap.querySelector('[data-recipient-search]');
      var hidden = wrap && wrap.querySelector('input[name="recipient_user_id"]');
      if (input) input.value = option.dataset.recipientLabel || '';
      if (hidden) hidden.value = option.dataset.recipientId || '';
      option.parentNode.innerHTML = '<div class="mg-recipient-selected">Selected: ' + esc(option.dataset.recipientLabel || 'recipient') + '</div>';
    });
  }

  function dispatchActionSubmit(type,item,data){
    app.dispatchEvent(new CustomEvent('mg:gift-action:submit',{bubbles:true,detail:{type:type,item:item,data:data}}));
  }
  if (modalBody) modalBody.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-action-form]');
    if (!form || form.dataset.actionForm === 'redeem') return;
    if (form.dataset.actionForm === 'send' && form.classList.contains('mg-send-exact-form')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    var item = selectedContract();
    var data = Object.fromEntries(new FormData(form).entries());
    dispatchActionSubmit(form.dataset.actionForm,item,data);
  }, true);

  app.addEventListener('mg:gift-action:submit', async function (event) {
    var detail = event.detail || {}, type = detail.type, item = detail.item || selectedContract(), data = detail.data || {};
    if (!ACTIVE.includes(type)) return;
    if (type === 'send' && !(data.recipient_user_id || data.recipient_profile_id || data.recipient_slug)) return result('Select a recipient', 'Start typing and choose a follower or user from the recipient list.');
    if ((type === 'follow-up' || type === 'message') && !text(data.message)) return result(type === 'follow-up' ? 'Write a Follow Up' : 'Write a message', 'Add a message for the current recipient or merchant.');
    if (type === 'tip' && Number(data.amount || 0) < 1) return result('Enter a tip amount', 'Tip amount must be at least $1.00.');
    result(type === 'send' ? 'Processing regift…' : 'Processing ' + type + '…', 'Please keep this window open.');
    try {
      var completed = await execute(type,item,data);
      var raw = completed.data, timestamp = timestampFrom(type,raw);
      var title = type === 'send' ? 'Regift complete' : type === 'follow-up' ? 'Follow Up sent' : type === 'message' ? 'Message sent' : type === 'tip' ? 'Tip sent' : 'Action complete';
      result(title, (completed.response && completed.response.message) || 'The Action Center has been updated.', timestamp);
    } catch (error) { result('Action failed', (error && error.message) || 'Unable to complete this action.'); }
  });

  document.addEventListener('mg:action-center:voucher-claimed', function (event) {
    finalizeExternal('voucher-redeem', event.detail && event.detail.action_item_id, event.detail || {}).catch(function () {});
  }, true);
  document.addEventListener('mg:action-center:regift-sent', function (event) {
    var detail = event.detail || {};
    finalizeExternal('send', detail.gift_id, detail.response || {}).catch(function () {});
  }, true);

  window.MicrogifterActionCenterMutations = Object.freeze({
    version: 1,
    contractVersion: 2,
    execute: execute,
    executeState: executeState,
    synchronize: synchronize,
    finalizeExternal: finalizeExternal,
    selectedContract: selectedContract
  });
});

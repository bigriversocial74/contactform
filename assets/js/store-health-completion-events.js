window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var storageKey = 'mgStoreHealthActions:v1';
  var endpoint = '/api/merchant/store-health-actions.php';
  var completedKey = 'mgStoreHealthAutoCompleted:v1';

  function readJson(key) { try { return JSON.parse(window.localStorage.getItem(key) || '{}') || {}; } catch (error) { return {}; } }
  function writeJson(key, value) { try { window.localStorage.setItem(key, JSON.stringify(value || {})); } catch (error) {} }
  function nowIso() { return new Date().toISOString(); }
  function clean(value) { return String(value || '').replace(/\s+/g, ' ').trim(); }
  function path() { return window.location.pathname || ''; }
  function params() { return new URLSearchParams(window.location.search || ''); }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }

  function allActions() {
    var store = readJson(storageKey);
    return Object.keys(store).map(function (key) { return store[key]; }).filter(function (record) { return record && record.key; });
  }

  function saveAction(record, status, metadata) {
    if (!record || !record.key) return;
    var store = readJson(storageKey);
    var updated = Object.assign({}, store[record.key] || record, {
      status: status,
      updatedAt: nowIso(),
      metadata: Object.assign({}, (record.metadata || {}), metadata || {})
    });
    if (status === 'started' && !updated.startedAt) updated.startedAt = nowIso();
    if (status === 'completed') updated.completedAt = nowIso();
    if (status === 'dismissed') updated.dismissedAt = nowIso();
    store[record.key] = updated;
    writeJson(storageKey, store);
    if (MG.post) {
      MG.post(endpoint, { action: updated }).catch(function () {});
    }
  }

  function latestStarted(type) {
    return allActions().filter(function (record) {
      return record.type === type && record.status === 'started';
    }).sort(function (a, b) {
      return String(b.updatedAt || '').localeCompare(String(a.updatedAt || ''));
    })[0] || null;
  }

  function latestAction(type) {
    return latestStarted(type) || allActions().filter(function (record) {
      return record.type === type && record.status !== 'completed' && record.status !== 'dismissed';
    }).sort(function (a, b) {
      return String(b.updatedAt || '').localeCompare(String(a.updatedAt || ''));
    })[0] || null;
  }

  function completionId(record, reason) {
    return String(record && record.key || '') + ':' + String(reason || '') + ':' + path() + ':' + window.location.search + ':' + window.location.hash;
  }

  function completeOnce(record, reason, metadata) {
    if (!record || !record.key) return false;
    var completed = readJson(completedKey);
    var id = completionId(record, reason);
    if (completed[id]) return false;
    completed[id] = nowIso();
    writeJson(completedKey, completed);
    saveAction(record, 'completed', Object.assign({ autoCompletedBy: reason, autoCompletedAt: nowIso(), path: path(), query: window.location.search || '', hash: window.location.hash || '' }, metadata || {}));
    toast('Store Health action completed automatically.', 'success');
    return true;
  }

  function startOnce(record, reason, metadata) {
    if (!record || !record.key || record.status === 'started' || record.status === 'completed') return false;
    saveAction(record, 'started', Object.assign({ autoStartedBy: reason, autoStartedAt: nowIso(), path: path(), query: window.location.search || '', hash: window.location.hash || '' }, metadata || {}));
    return true;
  }

  function routeBasedCompletion() {
    var p = path();
    var q = params();
    if (p.indexOf('/merchant-followups.php') !== -1) {
      var follow = latestAction('follow_up');
      if (q.has('campaign_contact_id') || q.has('email')) completeOnce(follow, 'followup_context_opened', { campaign_contact_id: q.get('campaign_contact_id') || '', email: q.get('email') || '' });
      else startOnce(follow, 'followup_queue_opened');
    }
    if (p.indexOf('/merchant-claims.php') !== -1) {
      var claims = latestAction('nudge_claims');
      if ((q.get('filter') || '').indexOf('claimed_not_redeemed') !== -1) completeOnce(claims, 'claimed_not_redeemed_queue_opened', { filter: q.get('filter') || '' });
      else startOnce(claims, 'claims_page_opened');
    }
    if (p.indexOf('/merchant-crm.php') !== -1) {
      var filter = q.get('filter') || '';
      if (filter.indexOf('do_not_message') !== -1) completeOnce(latestAction('audit_safeguards'), 'do_not_message_filter_opened', { filter: filter });
      if (filter.indexOf('high_intent') !== -1 || filter.indexOf('reward_sent') !== -1) startOnce(latestAction('send_reward'), 'crm_reward_context_opened', { filter: filter });
    }
    if (p.indexOf('/merchant-customer.php') !== -1 && window.location.hash === '#rewards') {
      completeOnce(latestAction('send_reward'), 'customer_reward_context_opened', { hash: window.location.hash });
    }
  }

  function eventBasedCompletion() {
    document.addEventListener('submit', function (event) {
      var formText = clean((event.target && event.target.textContent) || '');
      var formAction = clean(event.target && event.target.getAttribute && event.target.getAttribute('action'));
      if (/follow|task/i.test(formText + ' ' + formAction)) completeOnce(latestAction('follow_up'), 'followup_form_submitted');
      if (/reward|voucher|offer/i.test(formText + ' ' + formAction)) completeOnce(latestAction('send_reward'), 'reward_form_submitted');
    }, true);

    document.addEventListener('click', function (event) {
      var target = event.target && event.target.closest ? event.target.closest('button,a,[role="button"],[data-canvas-persistent-zone],[data-canvas-trigger-zone]') : null;
      if (!target) return;
      var label = clean(target.textContent || target.getAttribute('aria-label') || target.getAttribute('title') || '');
      var attr = clean([target.getAttribute('href'), target.getAttribute('data-action'), target.getAttribute('data-canvas-trigger-zone')].join(' '));
      var combined = label + ' ' + attr;
      if (/save|create|add|complete/i.test(combined) && /follow|task/i.test(combined)) completeOnce(latestAction('follow_up'), 'followup_action_clicked', { label: label });
      if (/send|issue|create|save/i.test(combined) && /reward|voucher|offer/i.test(combined)) completeOnce(latestAction('send_reward'), 'reward_action_clicked', { label: label });
      if (/claim|redeem|verify/i.test(combined)) completeOnce(latestAction('nudge_claims'), 'claim_action_clicked', { label: label });
      if (/do not message|safeguard|tag/i.test(combined)) completeOnce(latestAction('audit_safeguards'), 'safeguard_action_clicked', { label: label });
      if (target.matches('[data-canvas-persistent-zone],[data-canvas-trigger-zone]') || /trigger|zone|automation|rule/i.test(combined)) startOnce(latestAction('review_triggers'), 'trigger_settings_opened', { label: label });
      if (/save|update|pause/i.test(combined) && /trigger|zone|automation|rule/i.test(combined)) completeOnce(latestAction('review_triggers'), 'trigger_settings_saved', { label: label });
    }, true);
  }

  function observeCanvasDrawers() {
    if (path().indexOf('/merchant-canvas.php') === -1) return;
    var observer = new MutationObserver(function () {
      var triggerDrawer = document.querySelector('.mg-trigger-control-drawer.is-open,[data-trigger-settings-body]');
      if (triggerDrawer) startOnce(latestAction('review_triggers'), 'trigger_drawer_opened');
      var customerDrawer = document.querySelector('[data-canvas-drawer][aria-hidden="false"],[data-canvas-drawer].is-open');
      if (customerDrawer) startOnce(latestAction('send_reward'), 'customer_drawer_opened');
    });
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class','aria-hidden'] });
  }

  window.Microgifter.storeHealthCompletionEvents = {
    completeLatest: function (type, reason, metadata) { return completeOnce(latestAction(type), reason || 'manual_bridge', metadata || {}); },
    startLatest: function (type, reason, metadata) { return startOnce(latestAction(type), reason || 'manual_bridge', metadata || {}); }
  };

  routeBasedCompletion();
  eventBasedCompletion();
  observeCanvasDrawers();
})(window, document);

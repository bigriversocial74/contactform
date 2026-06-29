window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function clean(value) { return String(value || '').replace(/\s+/g, ' ').trim(); }
  function toast(message, type) { if (window.Microgifter && window.Microgifter.toast) window.Microgifter.toast(message, type || 'info'); }

  function openUrl(url) {
    if (!url) return false;
    window.location.href = url;
    return true;
  }

  function healthData() {
    var intel = window.Microgifter && window.Microgifter.storeCanvasIntelligence;
    if (!intel || typeof intel.getStoreHealth !== 'function') return null;
    try { return intel.getStoreHealth(); } catch (error) { return null; }
  }

  function firstCustomer(groupName) {
    var health = healthData() || {};
    var list = Array.isArray(health[groupName]) ? health[groupName] : [];
    return list[0] || null;
  }

  function contactProfileUrl(contact) {
    if (!contact) return '/merchant-crm.php';
    if (contact.customer_profile_url) return contact.customer_profile_url;
    if (contact.id) return '/merchant-customer.php?campaign_contact_id=' + encodeURIComponent(contact.id);
    if (contact.email) return '/merchant-customer.php?email=' + encodeURIComponent(contact.email);
    return '/merchant-crm.php';
  }

  function openCustomerDrawer(contact) {
    var name = clean(contact && (contact.name || contact.email || contact.id)) || '';
    if (!name) return false;
    var cards = qsa('.mg-canvas-avatar-card[data-session-id]', root);
    var match = cards.find(function (card) {
      return clean((card.querySelector('strong') || {}).textContent || '').toLowerCase() === name.toLowerCase();
    });
    if (match) {
      match.click();
      toast('Opened Customer CRM for ' + name + '.', 'success');
      return true;
    }
    return false;
  }

  function openRewardFor(contact) {
    if (openCustomerDrawer(contact)) {
      window.setTimeout(function () {
        var drawer = qs('[data-canvas-drawer]');
        var rewardButton = drawer && qsa('button,a', drawer).find(function (node) { return /reward/i.test(clean(node.textContent)); });
        if (rewardButton) rewardButton.click();
      }, 240);
      return true;
    }
    return openUrl(contactProfileUrl(contact) + '#rewards');
  }

  function openTriggerReview() {
    var zones = qsa('[data-canvas-persistent-zone], .mg-canvas-trigger-zone, [data-canvas-trigger-zone]', root);
    if (zones.length) {
      zones[0].click();
      toast('Opened trigger zone settings for review.', 'success');
      return true;
    }
    toast('No trigger zone is visible to open yet.', 'warning');
    return false;
  }

  function createFollowupContext(contact) {
    var url = '/merchant-followups.php';
    if (contact && contact.id) url += '?campaign_contact_id=' + encodeURIComponent(contact.id);
    else if (contact && contact.email) url += '?email=' + encodeURIComponent(contact.email);
    return openUrl(url);
  }

  function executeAction(action) {
    var contact;
    switch (action) {
      case 'send_reward':
        contact = firstCustomer('highIntent') || firstCustomer('followup') || firstCustomer('rewardsUnclaimed');
        toast('Opening reward context for the highest-priority customer.', 'info');
        return openRewardFor(contact);
      case 'follow_up':
        contact = firstCustomer('followup') || firstCustomer('highIntent') || firstCustomer('rewardsUnclaimed');
        toast('Opening follow-up task context.', 'info');
        return createFollowupContext(contact);
      case 'review_campaign':
        return openUrl('/merchant-campaigns.php?view=performance');
      case 'pause_trigger':
      case 'review_triggers':
        return openTriggerReview();
      case 'nudge_claims':
        return openUrl('/merchant-claims.php?filter=claimed_not_redeemed');
      case 'audit_safeguards':
        return openUrl('/merchant-crm.php?filter=do_not_message');
      case 'open_crm':
        return openUrl('/merchant-crm.php');
      case 'refresh':
        if (window.Microgifter && window.Microgifter.storeCanvasIntelligence && typeof window.Microgifter.storeCanvasIntelligence.refresh === 'function') {
          window.Microgifter.storeCanvasIntelligence.refresh();
          toast('Refreshing Store Health data.', 'info');
          return true;
        }
        window.location.reload();
        return true;
      default:
        return false;
    }
  }

  function actionTypeFromText(text) {
    text = String(text || '').toLowerCase();
    if (text.indexOf('reward') !== -1 && (text.indexOf('send') !== -1 || text.indexOf('review') !== -1 || text.indexOf('recover') !== -1)) return 'send_reward';
    if (text.indexOf('follow') !== -1) return 'follow_up';
    if (text.indexOf('campaign') !== -1) return 'review_campaign';
    if (text.indexOf('trigger') !== -1 || text.indexOf('zone') !== -1 || text.indexOf('pause') !== -1) return 'review_triggers';
    if (text.indexOf('claim') !== -1 || text.indexOf('nudge') !== -1) return 'nudge_claims';
    if (text.indexOf('do not message') !== -1 || text.indexOf('audit') !== -1 || text.indexOf('safeguard') !== -1) return 'audit_safeguards';
    if (text.indexOf('refresh') !== -1) return 'refresh';
    return 'open_crm';
  }

  function upgradeHealthButtons() {
    qsa('.mg-merchant-health-actions article').forEach(function (card) {
      if (card.dataset.actionExecutionReady === '1') return;
      card.dataset.actionExecutionReady = '1';
      var title = clean((card.querySelector('strong') || {}).textContent || '');
      var copy = clean((card.querySelector('span') || {}).textContent || '');
      var existing = card.querySelector('a,button');
      var type = actionTypeFromText(title + ' ' + copy + ' ' + clean(existing && existing.textContent));
      if (existing) {
        existing.setAttribute('data-merchant-action-execute', type);
        existing.classList.add('mg-action-execute-btn');
        if (existing.tagName.toLowerCase() === 'a') existing.setAttribute('role', 'button');
      }
      card.insertAdjacentHTML('beforeend', '<small class="mg-action-execution-note">Ready to execute</small>');
    });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-merchant-action-execute]');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    var action = button.getAttribute('data-merchant-action-execute') || '';
    button.classList.add('is-executing');
    button.textContent = 'Opening...';
    window.setTimeout(function () {
      var ok = executeAction(action);
      if (!ok) {
        button.classList.remove('is-executing');
        button.textContent = 'Open CRM';
        toast('Could not execute that action from the current Store Canvas state.', 'warning');
      }
    }, 60);
  }, true);

  new MutationObserver(function () { window.requestAnimationFrame(upgradeHealthButtons); }).observe(document.body, { childList: true, subtree: true });
  document.addEventListener('mg:storeCanvasIntelligenceLoaded', function () { window.requestAnimationFrame(upgradeHealthButtons); });
  upgradeHealthButtons();
})(window, document);

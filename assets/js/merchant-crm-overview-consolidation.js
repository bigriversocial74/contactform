document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var shell = document.querySelector('[data-merchant-crm-shell]');
  if (!shell) return;

  var removedTargets = {
    campaigns: true,
    performance: true,
    rewards: true,
    segments: true,
    media_segments: true,
    drafts: true,
    draft_review: true,
    launch_audit: true
  };
  var organizing = false;

  function normalize(value) {
    return String(value == null ? '' : value)
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  function isRemoved(value) {
    return removedTargets[normalize(value)] === true;
  }

  function setText(root, selector, value) {
    var node = root && root.querySelector(selector);
    if (node) node.textContent = value;
  }

  function updateOverviewCopy(overview) {
    if (!overview || overview.dataset.crmOverviewConsolidated === '1') return;
    overview.dataset.crmOverviewConsolidated = '1';

    var cards = Array.prototype.slice.call(
      overview.querySelectorAll('[data-crm-command-scoreboard] .mg-crm-command-card')
    );
    var cardCopy = [
      ['Contacts', 'Review account status, email readiness, reward state, and customer history from one operational table.'],
      ['Performance', 'Track builder runs, audience reach, messages, rewards, claims, redemptions, follow-ups, and failures directly in Overview.'],
      ['Insights', 'Use merchant-scoped CRM signals to identify responsive audiences and the next best customer actions.'],
      ['Retention', 'Review deterministic retention recommendations and merchant-approved next actions in one dedicated tab.']
    ];
    cards.forEach(function (card, index) {
      if (!cardCopy[index]) return;
      setText(card, 'h3', cardCopy[index][0]);
      setText(card, 'p', cardCopy[index][1]);
    });

    var commandPanel = overview.querySelector('.mg-crm-primary-grid .mg-crm-card');
    setText(
      commandPanel,
      '.mg-crm-card-head p',
      'Move between contacts, performance, insights, campaign score, and retention without leaving Merchant CRM.'
    );

    var segmentAction = commandPanel && commandPanel.querySelector('[data-crm-tab-target="segments"]');
    if (segmentAction) segmentAction.remove();

    var feedItems = commandPanel
      ? Array.prototype.slice.call(commandPanel.querySelectorAll('[data-crm-command-feed] article'))
      : [];
    if (feedItems[2]) {
      setText(feedItems[2], 'strong', 'Performance reporting is consolidated');
      setText(feedItems[2], 'small', 'Campaign conversion, builder runs, segments, and recent activity now appear directly in Overview.');
      setText(feedItems[2], '.mg-crm-badge', 'overview');
    }
  }

  function movePerformanceIntoOverview() {
    var overview = shell.querySelector('[data-crm-tab-panel="overview"]');
    if (!overview) return null;

    var existing = shell.querySelector('[data-crm-performance-section]');
    if (existing) {
      existing.hidden = false;
      return existing;
    }

    var performance = shell.querySelector('[data-crm-tab-panel="performance"]');
    if (!performance) return null;

    performance.hidden = false;
    performance.removeAttribute('hidden');
    performance.removeAttribute('data-crm-tab-panel');
    performance.removeAttribute('role');
    performance.classList.remove('mg-crm-tab-panel');
    performance.classList.add('mg-crm-overview-performance');
    performance.setAttribute('data-crm-performance-section', '');

    var insight = overview.querySelector('.mg-crm-insight-card');
    if (insight && insight.nextSibling) {
      overview.insertBefore(performance, insight.nextSibling);
    } else {
      overview.appendChild(performance);
    }

    return performance;
  }

  function removeDiscardedTabsAndPanels() {
    Array.prototype.slice.call(shell.querySelectorAll('[data-crm-tab-target]')).forEach(function (trigger) {
      var target = trigger.getAttribute('data-crm-tab-target') || trigger.textContent;
      if (isRemoved(target)) trigger.remove();
    });

    Array.prototype.slice.call(shell.querySelectorAll('[data-crm-tab-panel]')).forEach(function (panel) {
      if (isRemoved(panel.getAttribute('data-crm-tab-panel'))) panel.remove();
    });
  }

  function restoreOverviewWhenNeeded() {
    var active = shell.getAttribute('data-crm-active-tab');
    var panels = Array.prototype.slice.call(shell.querySelectorAll('[data-crm-tab-panel]'));
    var visibleAllowed = panels.some(function (panel) {
      return !isRemoved(panel.getAttribute('data-crm-tab-panel')) && panel.hidden === false;
    });

    if (!isRemoved(active) && visibleAllowed) return;

    var overview = shell.querySelector('[data-crm-tab-panel="overview"]');
    if (!overview) return;

    panels.forEach(function (panel) {
      panel.hidden = panel !== overview;
    });
    Array.prototype.slice.call(shell.querySelectorAll('[data-crm-tab-target]')).forEach(function (trigger) {
      var selected = normalize(trigger.getAttribute('data-crm-tab-target')) === 'overview';
      trigger.classList.toggle('is-active', selected);
      if (trigger.getAttribute('role') === 'tab') {
        trigger.setAttribute('aria-selected', selected ? 'true' : 'false');
        trigger.tabIndex = selected ? 0 : -1;
      }
    });
    shell.setAttribute('data-crm-active-tab', 'overview');
    if (window.history && history.replaceState && /^#crm-(campaigns|performance|rewards|segments|media[-_]segments|drafts|draft[-_]review|launch[-_]audit)$/i.test(location.hash || '')) {
      history.replaceState(null, '', '#crm-overview');
    }
  }

  function organize() {
    if (organizing) return;
    organizing = true;
    try {
      var overview = shell.querySelector('[data-crm-tab-panel="overview"]');
      updateOverviewCopy(overview);
      movePerformanceIntoOverview();
      removeDiscardedTabsAndPanels();
      restoreOverviewWhenNeeded();
    } finally {
      organizing = false;
    }
  }

  organize();

  if (window.MutationObserver) {
    new MutationObserver(function () {
      organize();
    }).observe(shell, { childList: true, subtree: true });
  }

  document.addEventListener('mg:crm-tab:changed', function (event) {
    if (event.detail && isRemoved(event.detail.tab)) organize();
  });
});

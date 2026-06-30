window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;

  var iconMap = [
    ['settings', '⚙'], ['setting', '⚙'],
    ['customer', '👥'], ['user', '👤'], ['profile', '👤'], ['merchant', '🏪'],
    ['history', '↺'], ['activity', '↺'], ['events', '↺'],
    ['analytics', '▥'], ['insight', '▥'], ['stats', '▥'],
    ['simulator', '▷'], ['simulate', '▷'], ['test', '▷'],
    ['reward', '🎁'], ['message', '💬'], ['crm', '◎'], ['trigger', '⌁'],
    ['automation', '⚡'], ['rules', '⚡'], ['target', '◎'], ['schedule', '◷'],
    ['overview', '◉'], ['details', '≡'], ['campaign', '📣'], ['handoff', '↗']
  ];

  function labelFor(button) {
    return (button.getAttribute('aria-label') || button.dataset.tab || button.dataset.panel || button.dataset.drawerTab || button.textContent || '').trim();
  }

  function iconFor(label) {
    var lower = String(label || '').toLowerCase();
    for (var i = 0; i < iconMap.length; i++) {
      if (lower.indexOf(iconMap[i][0]) !== -1) return iconMap[i][1];
    }
    return '•';
  }

  function isTabButton(node) {
    if (!node || !node.matches) return false;
    if (node.matches('[role="tab"], [data-tab], [data-drawer-tab], [data-canvas-tab], [data-trigger-tab], [data-customer-tab]')) return true;
    var parent = node.parentElement;
    if (!parent) return false;
    return parent.matches('[role="tablist"], .mg-canvas-tabs, .mg-canvas-tab-menu, .mg-trigger-tabs, .mg-customer-tabs');
  }

  function inCanvasDrawer(node) {
    return !!(node && node.closest('.mg-canvas-crm-drawer, .mg-canvas-trigger-settings-drawer, .mg-canvas-trigger-drawer, .mg-canvas-settings-drawer, .mg-canvas-drawer, [data-canvas-drawer], [data-trigger-settings-drawer]'));
  }

  function decorateButton(button) {
    if (!button || button.dataset.mobileIconReady === '1' || !isTabButton(button) || !inCanvasDrawer(button)) return;
    var label = labelFor(button);
    if (!label) return;
    button.dataset.mobileIconReady = '1';
    button.classList.add('mg-canvas-mobile-icon-tab');
    if (!button.querySelector('.mg-canvas-tab-label')) {
      var existing = button.innerHTML;
      button.innerHTML = '<span class="mg-canvas-tab-icon" aria-hidden="true">' + iconFor(label) + '</span><span class="mg-canvas-tab-label">' + existing + '</span>';
    } else if (!button.querySelector('.mg-canvas-tab-icon')) {
      button.insertAdjacentHTML('afterbegin', '<span class="mg-canvas-tab-icon" aria-hidden="true">' + iconFor(label) + '</span>');
    }
    if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', label.replace(/\s+/g, ' '));
  }

  function decorate() {
    root.querySelectorAll('button,a,[role="tab"]').forEach(decorateButton);
    document.querySelectorAll('.mg-canvas-crm-drawer button,.mg-canvas-crm-drawer a,.mg-canvas-trigger-settings-drawer button,.mg-canvas-trigger-settings-drawer a,.mg-canvas-trigger-drawer button,.mg-canvas-trigger-drawer a,.mg-canvas-settings-drawer button,.mg-canvas-settings-drawer a,[data-canvas-drawer] button,[data-canvas-drawer] a,[data-trigger-settings-drawer] button,[data-trigger-settings-drawer] a').forEach(decorateButton);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', decorate, { once:true });
  decorate();
  new MutationObserver(decorate).observe(document.body, { childList:true, subtree:true });
})(window, document);

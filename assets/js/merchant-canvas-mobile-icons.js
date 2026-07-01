window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;

  var iconMap = [
    ['control', '⚙'], ['settings', '⚙'], ['setting', '⚙'],
    ['engagement', '👥'], ['customer', '👥'], ['customers', '👥'], ['user', '👤'], ['profile', '👤'], ['avatar', '👤'], ['merchant', '🏪'],
    ['campaign', '📣'], ['attached', '▣'], ['movement', '↔'], ['behavior', '💬'],
    ['history', '↺'], ['activity', '↺'], ['events', '↺'], ['logs', '↺'],
    ['analytics', '▥'], ['insight', '▥'], ['stats', '▥'], ['store health', '◉'], ['health', '◉'],
    ['simulator', '▷'], ['simulate', '▷'], ['test', '▷'],
    ['reward', '🎁'], ['message', '💬'], ['crm', '◎'], ['trigger', '⌁'],
    ['automation', '⚡'], ['rules', '⚡'], ['target', '◎'], ['schedule', '◷'],
    ['overview', '◉'], ['details', '≡'], ['handoff', '↗']
  ];

  function labelFor(button) {
    return (button.getAttribute('aria-label') || button.getAttribute('title') || button.dataset.tab || button.dataset.panel || button.dataset.drawerTab || button.dataset.canvasTab || button.dataset.triggerTab || button.dataset.customerTab || button.textContent || '').trim();
  }

  function iconFor(label) {
    var lower = String(label || '').toLowerCase().replace(/\s+/g, ' ');
    for (var i = 0; i < iconMap.length; i++) {
      if (lower.indexOf(iconMap[i][0]) !== -1) return iconMap[i][1];
    }
    return '•';
  }

  function isTabButton(node) {
    if (!node || !node.matches) return false;
    if (node.matches('[role="tab"], [data-tab], [data-drawer-tab], [data-canvas-tab], [data-trigger-tab], [data-customer-tab], [data-merchant-tab], [data-avatar-tab]')) return true;
    var parent = node.parentElement;
    if (!parent) return false;
    if (parent.matches('[role="tablist"], .mg-canvas-tabs, .mg-canvas-tab-menu, .mg-trigger-tabs, .mg-customer-tabs, .mg-avatar-tabs, .mg-merchant-tabs, .mg-canvas-drawer-tabs')) return true;
    var className = String(node.className || '') + ' ' + String(parent.className || '');
    return /tab|tabs|tablist|drawer-nav|drawer-menu/i.test(className);
  }

  function inCanvasDrawer(node) {
    return !!(node && node.closest('.mg-canvas-crm-drawer, .mg-canvas-trigger-settings-drawer, .mg-canvas-trigger-drawer, .mg-canvas-settings-drawer, .mg-canvas-drawer, .mg-merchant-avatar-drawer, .mg-customer-avatar-drawer, .mg-avatar-drawer, [data-canvas-drawer], [data-trigger-settings-drawer], [data-merchant-drawer], [data-customer-drawer], [data-avatar-drawer]'));
  }

  function decorateButton(button) {
    if (!button || button.dataset.mobileIconReady === '1' || !isTabButton(button) || !inCanvasDrawer(button)) return;
    var label = labelFor(button);
    if (!label) return;
    button.dataset.mobileIconReady = '1';
    button.classList.add('mg-canvas-mobile-icon-tab');
    button.dataset.mobileIcon = iconFor(label);
    if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', label.replace(/\s+/g, ' '));
  }

  function decorateAvatars() {
    root.querySelectorAll('.mg-canvas-avatar-card,.mg-canvas-agent-node,.mg-canvas-merchant-node,[data-merchant-avatar-settings],[data-canvas-customer-avatar]').forEach(function (card) {
      if (card.dataset.mobileAvatarReady === '1') return;
      card.dataset.mobileAvatarReady = '1';
      card.classList.add('mg-canvas-mobile-simple-avatar');
      if (!card.getAttribute('aria-label')) {
        var name = card.querySelector('strong,[data-canvas-avatar-name]');
        if (name && name.textContent) card.setAttribute('aria-label', name.textContent.trim());
      }
    });
  }

  function decorate() {
    root.querySelectorAll('button,a,[role="tab"]').forEach(decorateButton);
    document.querySelectorAll('.mg-canvas-crm-drawer button,.mg-canvas-crm-drawer a,.mg-canvas-trigger-settings-drawer button,.mg-canvas-trigger-settings-drawer a,.mg-canvas-trigger-drawer button,.mg-canvas-trigger-drawer a,.mg-canvas-settings-drawer button,.mg-canvas-settings-drawer a,.mg-merchant-avatar-drawer button,.mg-merchant-avatar-drawer a,.mg-customer-avatar-drawer button,.mg-customer-avatar-drawer a,.mg-avatar-drawer button,.mg-avatar-drawer a,[data-canvas-drawer] button,[data-canvas-drawer] a,[data-trigger-settings-drawer] button,[data-trigger-settings-drawer] a,[data-merchant-drawer] button,[data-merchant-drawer] a,[data-customer-drawer] button,[data-customer-drawer] a,[data-avatar-drawer] button,[data-avatar-drawer] a').forEach(decorateButton);
    decorateAvatars();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', decorate, { once:true });
  decorate();
  new MutationObserver(decorate).observe(document.body, { childList:true, subtree:true });
})(window, document);

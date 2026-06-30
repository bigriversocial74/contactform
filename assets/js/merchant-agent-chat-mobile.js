document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root) return;

  var drawer = root.querySelector('[data-agent-chat-drawer]');
  var open = root.querySelector('[data-agent-chat-drawer-open]');
  var closeTriggers = root.querySelectorAll('[data-agent-chat-drawer-close]');
  var summary = root.querySelector('[data-agent-chat-summary]');
  var mobileSummary = root.querySelector('[data-agent-chat-summary-mobile]');

  function isMobile() {
    return window.matchMedia && window.matchMedia('(max-width: 760px)').matches;
  }

  function syncSummary() {
    if (!summary || !mobileSummary) return;
    mobileSummary.textContent = summary.textContent || 'Overview · Last 90 days · Action plan';
  }

  function setDrawer(isOpen) {
    root.classList.toggle('is-drawer-open', !!isOpen);
    document.body.classList.toggle('mg-agent-chat-drawer-open', !!isOpen);
    if (open) open.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (drawer) drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    closeTriggers.forEach(function (trigger) {
      if (trigger.classList && trigger.classList.contains('mg-agent-chat-drawer-backdrop')) {
        trigger.hidden = !isOpen;
      }
    });
  }

  function isMenuTrigger(element) {
    if (!element) return false;
    var trigger = element.closest('button,a');
    if (!trigger) return false;
    if (trigger.matches('[data-agent-chat-drawer-open]')) return true;
    if (trigger.matches('[data-app-sidebar-toggle],[data-sidebar-toggle],[data-mobile-menu-toggle],[data-menu-toggle],.mg-public-menu-toggle,.mg-app-menu-toggle,.mg-mobile-menu-toggle,.mg-hamburger,.mg-header-menu-toggle')) return true;
    var label = (trigger.getAttribute('aria-label') || trigger.textContent || '').toLowerCase();
    return label.indexOf('menu') !== -1 || label.indexOf('navigation') !== -1 || label.indexOf('sidebar') !== -1;
  }

  if (open) {
    open.addEventListener('click', function (event) {
      event.preventDefault();
      setDrawer(true);
    });
  }

  document.addEventListener('click', function (event) {
    if (!isMobile()) return;
    if (!isMenuTrigger(event.target)) return;
    if (drawer && drawer.contains(event.target)) return;
    event.preventDefault();
    event.stopPropagation();
    setDrawer(true);
  }, true);

  closeTriggers.forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      setDrawer(false);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && root.classList.contains('is-drawer-open')) {
      setDrawer(false);
    }
  });

  root.querySelectorAll('[data-agent-chat-scope],[data-agent-chat-days],[data-agent-chat-output],[data-agent-chat-approval],[data-agent-skill]').forEach(function (element) {
    element.addEventListener('change', function () {
      window.setTimeout(syncSummary, 0);
    });
  });

  if (summary && 'MutationObserver' in window) {
    new MutationObserver(syncSummary).observe(summary, { childList: true, characterData: true, subtree: true });
  }

  syncSummary();
  setDrawer(false);
});

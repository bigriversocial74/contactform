document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root) return;

  var drawer = root.querySelector('[data-agent-chat-drawer]');
  var open = root.querySelector('[data-agent-chat-drawer-open]');
  var closeTriggers = root.querySelectorAll('[data-agent-chat-drawer-close]');
  var summary = root.querySelector('[data-agent-chat-summary]');
  var mobileSummary = root.querySelector('[data-agent-chat-summary-mobile]');

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

  if (open) {
    open.addEventListener('click', function () {
      setDrawer(true);
    });
  }

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

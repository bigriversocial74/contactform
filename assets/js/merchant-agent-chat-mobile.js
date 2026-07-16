document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root) return;

  var drawer = root.querySelector('[data-agent-chat-drawer]');
  var openButton = root.querySelector('[data-agent-chat-drawer-open]');
  var closeTriggers = root.querySelectorAll('[data-agent-chat-drawer-close]');
  var closeButton = drawer ? drawer.querySelector('[data-agent-chat-drawer-close]') : null;
  var backdrop = root.querySelector('.mg-agent-chat-drawer-backdrop');
  var summary = root.querySelector('[data-agent-chat-summary]');
  var mobileSummary = root.querySelector('[data-agent-chat-summary-mobile]');

  function syncSummary() {
    if (!summary || !mobileSummary) return;
    mobileSummary.textContent = summary.textContent || 'Overview · Last 90 days · Action plan';
  }

  function setDrawer(isOpen, restoreFocus) {
    var shouldOpen = !!isOpen;

    root.classList.toggle('is-drawer-open', shouldOpen);
    document.body.classList.toggle('mg-agent-chat-drawer-open', shouldOpen);

    if (openButton) openButton.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    if (drawer) drawer.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
    if (backdrop) backdrop.hidden = !shouldOpen;

    if (shouldOpen && closeButton) {
      window.requestAnimationFrame(function () {
        closeButton.focus({ preventScroll: true });
      });
    } else if (restoreFocus && openButton) {
      window.requestAnimationFrame(function () {
        openButton.focus({ preventScroll: true });
      });
    }
  }

  if (openButton) {
    openButton.addEventListener('click', function () {
      setDrawer(true, false);
    });
  }

  closeTriggers.forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      setDrawer(false, true);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && root.classList.contains('is-drawer-open')) setDrawer(false, true);
  });

  root.querySelectorAll('[data-agent-chat-scope],[data-agent-chat-days],[data-agent-chat-output],[data-agent-chat-approval],[data-agent-skill]').forEach(function (element) {
    element.addEventListener('change', function () {
      window.setTimeout(syncSummary, 0);
    });
  });

  if (summary && 'MutationObserver' in window) {
    new MutationObserver(syncSummary).observe(summary, {
      childList: true,
      characterData: true,
      subtree: true
    });
  }

  syncSummary();
  setDrawer(false, false);
});

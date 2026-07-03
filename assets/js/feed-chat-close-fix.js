window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var dock = document.querySelector('[data-feed-chat-dock]');
  if (!dock) return;

  function normalizeCloseButtons() {
    dock.querySelectorAll('.mg-feed-chat-close').forEach(function (button) {
      button.type = 'button';
      button.dataset.chatClose = '1';
      button.dataset.feedBridgeClose = '1';
      if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', 'Close chat');
    });
  }

  function hardClose() {
    dock.replaceChildren();
    if (window.Microgifter && window.Microgifter.feedOnlineChatBridge && typeof window.Microgifter.feedOnlineChatBridge.close === 'function') {
      try { window.Microgifter.feedOnlineChatBridge.close(); } catch (error) {}
    }
  }

  dock.addEventListener('click', function (event) {
    var trigger = event.target.closest('.mg-feed-chat-close, [data-chat-close], [data-feed-bridge-close]');
    if (!trigger || !dock.contains(trigger)) return;
    normalizeCloseButtons();
    window.setTimeout(function () {
      if (dock.querySelector('.mg-feed-chat-window')) hardClose();
    }, 0);
  }, true);

  var observer = new MutationObserver(normalizeCloseButtons);
  observer.observe(dock, { childList: true, subtree: true });
  normalizeCloseButtons();
})(window, document);

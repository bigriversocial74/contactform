document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root) return;

  var params = new URLSearchParams(window.location.search);
  var requestedThread = String(params.get('thread') || '').trim();
  var createNew = params.get('new') === '1';
  if (!requestedThread && !createNew) return;

  var attempts = 0;
  var maxAttempts = 50;

  function clearDeepLink() {
    if (!window.history || !window.history.replaceState) return;
    var url = new URL(window.location.href);
    url.searchParams.delete('thread');
    url.searchParams.delete('new');
    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
  }

  function apply() {
    attempts += 1;
    var select = root.querySelector('[data-agent-thread-select]');
    var newButton = root.querySelector('[data-agent-new-thread]');

    if (createNew && newButton && !newButton.disabled && select && select.options.length) {
      newButton.click();
      clearDeepLink();
      return;
    }

    if (requestedThread && select && select.options.length) {
      var match = Array.prototype.some.call(select.options, function (option) {
        return String(option.value || '') === requestedThread;
      });
      if (match) {
        select.value = requestedThread;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        clearDeepLink();
        return;
      }
    }

    if (attempts < maxAttempts) {
      window.setTimeout(apply, 120);
    }
  }

  apply();
});

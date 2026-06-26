(() => {
  'use strict';

  function stateResultsUrl(stateCode) {
    const code = String(stateCode || '').trim().toUpperCase();
    return code ? `/state-results.php?state=${encodeURIComponent(code)}` : '/discover.php';
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-discover-state]');
    if (!trigger || !trigger.closest('[data-profile-discovery]')) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    window.location.href = stateResultsUrl(trigger.getAttribute('data-discover-state'));
  }, true);
})();

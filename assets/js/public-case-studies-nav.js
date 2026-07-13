(() => {
  'use strict';

  const href = '/featured-case-studies.php';
  const label = 'Case Studies';

  function addLink(nav) {
    if (!nav || nav.querySelector(`a[href="${href}"]`)) return;
    const explore = nav.querySelector('a[href="/discover.php"]');
    const link = document.createElement('a');
    link.href = href;
    link.textContent = label;
    if (explore && explore.parentNode === nav) explore.insertAdjacentElement('afterend', link);
    else nav.appendChild(link);
  }

  function init() {
    document.querySelectorAll('.mg-public-nav, .mg-public-mobile-nav').forEach(addLink);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();

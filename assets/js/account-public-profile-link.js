(function (document, window) {
  'use strict';

  function updateLink(link) {
    if (!link) return;
    var href = String(link.getAttribute('href') || '');
    if (href.indexOf('/api/public/profile.php?') !== 0) return;
    try {
      var source = new URL(href, window.location.origin);
      var slug = source.searchParams.get('slug') || '';
      link.href = '/profile.php?slug=' + encodeURIComponent(slug);
    } catch (error) {
      link.href = '/profile.php';
    }
  }

  function init() {
    var link = document.querySelector('[data-profile-public-link]');
    if (!link) return;
    updateLink(link);
    new MutationObserver(function () { updateLink(link); }).observe(link, {
      attributes: true,
      attributeFilter: ['href'],
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(document, window);

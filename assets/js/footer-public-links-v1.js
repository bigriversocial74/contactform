(() => {
  'use strict';

  const footer = document.querySelector('[data-mg-universal-footer]');
  if (!footer) return;

  const columns = Array.from(footer.querySelectorAll('.mg-footer-column'));
  const platformColumn = columns.find((column) => {
    const heading = column.querySelector('h2');
    return heading && heading.textContent.trim().toLowerCase() === 'platform';
  });

  const ensureLink = (href, label, afterHref) => {
    if (!platformColumn || platformColumn.querySelector(`a[href="${href}"]`)) return;

    const link = document.createElement('a');
    link.href = href;
    link.textContent = label;

    const anchor = afterHref ? platformColumn.querySelector(`a[href="${afterHref}"]`) : null;
    if (anchor) anchor.insertAdjacentElement('afterend', link);
    else platformColumn.appendChild(link);
  };

  ensureLink('/creator-campaigns-overview.php', 'Merchant & Creator Campaigns', '/featured-case-studies.php');
  ensureLink('/pitch-deck.php', 'Pitch Deck', '/investors.php');

  const bottomLinks = footer.querySelector('.mg-footer-bottom-links');
  if (bottomLinks) {
    const removeHrefs = new Set(['/index.php', '/pricing.php', '/investors.php', '/mcp-server.php', '/signin.php']);
    bottomLinks.querySelectorAll('a').forEach((link) => {
      const href = link.getAttribute('href') || '';
      if (removeHrefs.has(href)) link.remove();
    });

    bottomLinks.querySelectorAll('[data-mg-cookie-settings], .mg-footer-cookie-settings').forEach((control) => control.remove());
  }
})();

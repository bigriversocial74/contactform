(() => {
  'use strict';
  const href = '/admin/public-donations-operations.php';
  const nav = document.querySelector('.mg-admin-side-nav');
  if (nav && !nav.querySelector(`a[href="${href}"]`)) {
    const link = document.createElement('a');
    link.href = href;
    link.className = location.pathname === href ? 'is-active' : '';
    const strong = document.createElement('strong');
    strong.textContent = 'Public Donations';
    const detail = document.createElement('span');
    detail.textContent = 'Rollout, integrity, repair';
    link.append(strong, detail);
    const mcp = nav.querySelector('a[href="/admin/mcp-connections.php"]');
    if (mcp?.nextSibling) nav.insertBefore(link, mcp.nextSibling);
    else nav.appendChild(link);
  }

  const shortcuts = document.querySelector('[data-admin-shortcuts]');
  if (shortcuts && !shortcuts.querySelector(`a[href="${href}"]`)) {
    const link = document.createElement('a');
    link.href = href;
    link.className = 'mg-admin-shortcut';
    const strong = document.createElement('strong');
    strong.textContent = 'Public Donations operations';
    const detail = document.createElement('span');
    detail.textContent = 'Control rollout, run reconciliation, and review receipts.';
    link.append(strong, detail);
    shortcuts.appendChild(link);
  }
})();

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var modal = document.querySelector('[data-create-menu]');
  if (!modal) return;

  var manageRoutes = {
    product: '/merchant-products.php',
    campaign: '/merchant-campaigns.php',
    reward: '/merchant-reward-templates.php',
    post: '/feed.php?view=mine',
    storefront: '/merchant-storefront.php',
    location: '/merchant-locations.php',
    list: '/lists.php'
  };

  function removeIntro() {
    var intro = modal.querySelector('.mg-create-center-welcome');
    if (intro) intro.remove();
  }

  function enhanceCard(card) {
    if (!card || card.dataset.createManageEnhanced === '1') return;
    var key = String(card.getAttribute('data-create-tool-key') || '');
    var manageHref = manageRoutes[key] || '';
    if (!key || !manageHref) return;

    var shell = document.createElement('article');
    shell.className = 'mg-create-center-card mg-create-center-managed-card';
    shell.setAttribute('role', 'listitem');
    shell.setAttribute('data-create-manage-card', key);

    var open = document.createElement('button');
    open.type = 'button';
    open.className = 'mg-create-center-card-open';
    open.setAttribute('data-create-menu-option', card.getAttribute('data-create-menu-option') || key);
    open.setAttribute('data-create-tool-key', key);
    open.setAttribute('data-create-inline-target', card.getAttribute('data-create-inline-target') || key);
    open.setAttribute('aria-controls', card.getAttribute('aria-controls') || ('mg-create-center-' + key));
    open.setAttribute('aria-label', 'Create ' + key);

    while (card.firstChild) open.appendChild(card.firstChild);

    var manage = document.createElement('a');
    manage.className = 'mg-create-center-manage';
    manage.href = manageHref;
    manage.textContent = 'Manage';
    manage.setAttribute('data-create-center-manage', key);
    manage.setAttribute('aria-label', 'Manage ' + key);

    shell.append(open, manage);
    card.dataset.createManageEnhanced = '1';
    card.replaceWith(shell);
  }

  function enhance() {
    removeIntro();
    modal.querySelectorAll('.mg-create-menu-grid > a.mg-create-center-card[data-create-tool-key]').forEach(enhanceCard);
  }

  enhance();

  var grid = modal.querySelector('.mg-create-menu-grid');
  if (grid) {
    new MutationObserver(enhance).observe(grid, { childList: true });
  }
});

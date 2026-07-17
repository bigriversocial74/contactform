(function (window, document) {
  'use strict';

  var STORAGE_KEY = 'mg_agent_upgrade_return_v1';
  var panel = document.querySelector('.mg-subscription-redesign');
  if (!panel) return;

  var config = {
    personal: {
      label: 'Personal Agent',
      target: '/agent.php',
      title: 'Unlock Personal Agent',
      copy: 'A paid Microgifter package unlocks Personal Agent access. Starter is the lowest-cost eligible package and all higher packages also qualify.'
    },
    merchant: {
      label: 'Merchant Agent',
      target: '/merchant-agent-chat.php',
      title: 'Unlock Merchant Agent',
      copy: 'Merchant Agent requires an active merchant package. Starter is the minimum eligible package and all higher merchant packages also qualify.'
    }
  };

  function normalize(value) {
    value = String(value || '').trim().toLowerCase();
    return Object.prototype.hasOwnProperty.call(config, value) ? value : '';
  }

  function store(value) {
    try {
      if (value) window.sessionStorage.setItem(STORAGE_KEY, value);
      else window.sessionStorage.removeItem(STORAGE_KEY);
    } catch (_) {}
  }

  function stored() {
    try { return normalize(window.sessionStorage.getItem(STORAGE_KEY)); } catch (_) { return ''; }
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  var params = new URLSearchParams(window.location.search || '');
  var intent = normalize(params.get('agent')) || stored();
  if (!intent) return;
  store(intent);
  panel.setAttribute('data-agent-upgrade-intent', intent);
  panel.setAttribute('data-agent-return-url', config[intent].target);

  function body() {
    return panel.querySelector('.mg-app-panel-body');
  }

  function chooseStarter() {
    var action = panel.querySelector('.mg-sub-plan-card[data-package-id="starter"] .mg-sub-action');
    if (!action) return;
    action.scrollIntoView({ behavior: 'smooth', block: 'center' });
    window.setTimeout(function () { action.click(); }, 320);
  }

  function bannerMode() {
    var checkout = String(params.get('checkout') || '').toLowerCase();
    if (checkout === 'activated') return 'activated';
    if (checkout === 'cancelled') return 'cancelled';
    return 'upgrade';
  }

  function ensureBanner() {
    var mount = body();
    if (!mount) return;
    var old = mount.querySelector('[data-agent-access-banner]');
    if (old) old.remove();

    var mode = bannerMode();
    var item = config[intent];
    var title = item.title;
    var copy = item.copy;
    var kicker = 'Agent access upgrade';
    var icon = '✦';
    var actions = '<button class="is-primary" type="button" data-agent-choose-starter>Choose Starter</button><a href="#plans-and-pricing" data-agent-compare-plans>Compare plans</a>';

    if (mode === 'activated') {
      kicker = 'Access activated';
      title = item.label + ' is ready';
      copy = 'Your subscription has been verified. Microgifter is returning you to ' + item.label + '.';
      icon = '✓';
      actions = '<a class="is-primary" href="' + esc(item.target) + '" data-agent-open-now>Open ' + esc(item.label) + ' now</a>';
    } else if (mode === 'cancelled') {
      kicker = 'Checkout cancelled';
      title = item.label + ' remains locked';
      copy = 'No subscription change was made. Choose an eligible package when you are ready to continue.';
      icon = '↺';
      actions = '<button class="is-primary" type="button" data-agent-choose-starter>Resume with Starter</button><a href="' + esc(item.target) + '">Return to ' + esc(item.label) + '</a>';
    }

    var banner = document.createElement('section');
    banner.className = 'mg-agent-access-banner is-' + mode;
    banner.setAttribute('data-agent-access-banner', mode);
    banner.setAttribute('role', 'status');
    banner.innerHTML = '<div class="mg-agent-access-icon" aria-hidden="true">' + icon + '</div>'
      + '<div class="mg-agent-access-copy"><span>' + esc(kicker) + '</span><strong>' + esc(title) + '</strong><p>' + esc(copy) + '</p></div>'
      + '<div class="mg-agent-access-actions">' + actions + '</div>';
    mount.insertBefore(banner, mount.firstChild);

    var starter = banner.querySelector('[data-agent-choose-starter]');
    if (starter) starter.addEventListener('click', chooseStarter);
    var compare = banner.querySelector('[data-agent-compare-plans]');
    if (compare) compare.addEventListener('click', function (event) {
      event.preventDefault();
      var plans = panel.querySelector('.mg-sub-plans');
      if (plans) plans.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  function decorateCards() {
    panel.querySelectorAll('.mg-sub-plan-card[data-package-id]').forEach(function (card) {
      var packageId = String(card.getAttribute('data-package-id') || '');
      card.classList.add('is-agent-eligible');
      card.setAttribute('data-agent-eligible-for', intent);
      var inner = card.querySelector('.mg-sub-plan-inner');
      if (!inner) return;

      var badge = inner.querySelector('[data-agent-plan-badge]');
      var note = inner.querySelector('[data-agent-plan-note]');
      if (packageId === 'starter') {
        card.classList.add('is-agent-recommended');
        if (!badge) {
          badge = document.createElement('div');
          badge.className = 'mg-agent-plan-badge';
          badge.setAttribute('data-agent-plan-badge', '');
          inner.insertBefore(badge, inner.firstChild);
        }
        badge.textContent = 'Recommended starting plan for ' + config[intent].label;
      } else {
        card.classList.remove('is-agent-recommended');
        if (badge) badge.remove();
      }

      var action = inner.querySelector('.mg-sub-action');
      if (action && !note) {
        note = document.createElement('div');
        note.className = 'mg-agent-plan-note';
        note.setAttribute('data-agent-plan-note', '');
        note.textContent = 'Includes ' + config[intent].label + ' access';
        action.insertAdjacentElement('afterend', note);
      }
    });
  }

  function redirectAfterActivation() {
    if (bannerMode() !== 'activated') return;
    var target = config[intent].target;
    window.setTimeout(function () {
      store('');
      window.location.replace(target);
    }, 1800);
  }

  var scheduled = false;
  function refresh() {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      decorateCards();
    });
  }

  ensureBanner();
  decorateCards();
  new MutationObserver(refresh).observe(panel, { childList: true, subtree: true });
  redirectAfterActivation();
})(window, document);

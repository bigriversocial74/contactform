document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-customer-profile-page]');
  if (!root) return;

  var icons = {
    overview: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>',
    followups: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/><path d="m8.5 12 2 2 4-4"/></svg>',
    timeline: '<svg viewBox="0 0 24 24"><path d="M4 6h4"/><path d="M4 12h7"/><path d="M4 18h10"/><circle cx="16" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
    rewards: '<svg viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13"/><path d="M3 12h18"/><path d="M12 8H8.5A2.5 2.5 0 1 1 11 5.5V8Z"/><path d="M12 8h3.5A2.5 2.5 0 1 0 13 5.5V8Z"/></svg>',
    messages: '<svg viewBox="0 0 24 24"><path d="M20 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h9a4 4 0 0 1 4 4Z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>',
    redemptions: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/><path d="M12 3v3"/><path d="M12 18v3"/></svg>',
    tips: '<svg viewBox="0 0 24 24"><path d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10Z"/><path d="M12 8v7"/><path d="M9.5 10.5h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3h4"/></svg>',
    notes: '<svg viewBox="0 0 24 24"><path d="M5 3h10l4 4v14H5Z"/><path d="M15 3v5h5"/><path d="M8 12h8"/><path d="M8 16h6"/></svg>'
  };

  function enhanceTabs() {
    root.querySelectorAll('[data-profile-tab]').forEach(function (button) {
      if (button.dataset.mobileIconReady === 'true') return;

      var name = String(button.getAttribute('data-profile-tab') || 'overview');
      var label = String(button.textContent || name).trim();
      var icon = document.createElement('span');
      var text = document.createElement('span');

      icon.className = 'mg-cp-tab-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.innerHTML = icons[name] || icons.overview;

      text.className = 'mg-cp-tab-label';
      text.textContent = label;

      button.textContent = '';
      button.appendChild(icon);
      button.appendChild(text);
      button.setAttribute('aria-label', label);
      button.setAttribute('title', label);
      button.dataset.mobileIconReady = 'true';
    });
  }

  function setMetricsOpen(toggle, body, open) {
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    body.hidden = !open;
  }

  function buildMetricsAccordion() {
    var kpis = root.querySelector('.mg-cp-kpis');
    if (!kpis || kpis.closest('[data-cp-mobile-metrics]')) return;

    var wrapper = document.createElement('section');
    var toggle = document.createElement('button');
    var body = document.createElement('div');
    var bodyId = 'cpMobileMetricsBody';

    wrapper.className = 'mg-cp-mobile-metrics';
    wrapper.setAttribute('data-cp-mobile-metrics', '');

    toggle.className = 'mg-cp-mobile-metrics-toggle';
    toggle.type = 'button';
    toggle.setAttribute('data-cp-mobile-metrics-toggle', '');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-controls', bodyId);
    toggle.innerHTML = '<span class="mg-cp-mobile-metrics-toggle-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19H2"/></svg></span><span class="mg-cp-mobile-metrics-toggle-copy"><strong>Customer Metrics</strong><small>Rewards, claims, follow-ups, tips, and value</small></span><span class="mg-cp-mobile-metrics-chevron" aria-hidden="true"></span>';

    body.className = 'mg-cp-mobile-metrics-body';
    body.id = bodyId;
    body.setAttribute('data-cp-mobile-metrics-body', '');

    kpis.parentNode.insertBefore(wrapper, kpis);
    wrapper.appendChild(toggle);
    wrapper.appendChild(body);
    body.appendChild(kpis);

    toggle.addEventListener('click', function () {
      setMetricsOpen(toggle, body, toggle.getAttribute('aria-expanded') !== 'true');
    });

    setMetricsOpen(toggle, body, true);
  }

  enhanceTabs();
  buildMetricsAccordion();
});

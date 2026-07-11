document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  if (!root) return;

  var nav = root.querySelector('.mg-campaign-tabs');
  var panelHost = root.querySelector('.mg-campaign-tab-panels');
  if (!nav || !panelHost) return;

  var removedTopTabs = ['active', 'performance', 'contacts'];
  var analyticsKeys = ['recommendations', 'landing_qa', 'refund_qa', 'queue'];
  var organizing = false;
  var observer = null;

  function normalize(value) {
    return String(value == null ? '' : value).trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
  }

  function linkKey(link) {
    if (!link) return '';
    var explicit = link.getAttribute('data-campaign-tab') || link.getAttribute('data-campaign-analytics-key') || '';
    var href = link.getAttribute('href') || '';
    var label = normalize(link.textContent || '');
    var combined = normalize(explicit + ' ' + href + ' ' + label);
    if (combined.indexOf('recommend') !== -1 || link.hasAttribute('data-predictive-campaign-tab-link')) return 'recommendations';
    if (combined.indexOf('landing_qa') !== -1 || combined.indexOf('landing_page_qa') !== -1) return 'landing_qa';
    if (combined.indexOf('refund') !== -1 && combined.indexOf('qa') !== -1) return 'refund_qa';
    if (combined.indexOf('queue') !== -1) return 'queue';
    if (combined.indexOf('performance') !== -1) return 'performance';
    if (combined.indexOf('contacts') !== -1) return 'contacts';
    if (combined.indexOf('active') !== -1) return 'active';
    return normalize(explicit || label || href);
  }

  function panelKey(panel) {
    if (!panel) return '';
    var explicit = panel.getAttribute('data-campaign-tab-panel') || panel.getAttribute('data-campaign-analytics-key') || '';
    var label = panel.getAttribute('aria-label') || '';
    var heading = panel.querySelector('h1,h2,h3');
    var combined = normalize(explicit + ' ' + panel.id + ' ' + label + ' ' + (heading ? heading.textContent : ''));
    if (combined.indexOf('recommend') !== -1 || panel.id === 'campaign-recommendations') return 'recommendations';
    if (combined.indexOf('landing_qa') !== -1 || combined.indexOf('landing_page_qa') !== -1) return 'landing_qa';
    if (combined.indexOf('refund') !== -1 && combined.indexOf('qa') !== -1) return 'refund_qa';
    if (combined.indexOf('queue') !== -1) return 'queue';
    if (combined.indexOf('performance') !== -1) return 'performance';
    if (combined.indexOf('contacts') !== -1) return 'contacts';
    if (combined.indexOf('active') !== -1) return 'active';
    return normalize(explicit || panel.id || label);
  }

  function removeClosestPanel(selector) {
    root.querySelectorAll(selector).forEach(function (node) {
      var panel = node.closest('.mg-app-panel, .mg-campaign-panel, aside, section');
      if (panel && panel !== root) panel.remove();
    });
  }

  function removeSideContent() {
    root.querySelectorAll('.mg-campaign-side').forEach(function (side) { side.remove(); });
    removeClosestPanel('[data-agent-action-list]');
    removeClosestPanel('[data-campaign-insights-list]');
    removeClosestPanel('[data-media-performance-list]');
    removeClosestPanel('[data-customer-refund-creator-prompt]');
  }

  function removeTopTab(key) {
    Array.prototype.slice.call(nav.querySelectorAll('a')).forEach(function (link) {
      if (linkKey(link) === key) link.remove();
    });
    Array.prototype.slice.call(panelHost.querySelectorAll(':scope > [data-campaign-tab-panel]')).forEach(function (panel) {
      if (panelKey(panel) === key) panel.remove();
    });
  }

  function ensureAnalyticsShell() {
    var link = nav.querySelector('[data-campaign-analytics-link]');
    if (!link) {
      link = document.createElement('a');
      link.href = '#campaign-analytics';
      link.textContent = 'Analytics';
      link.setAttribute('data-campaign-analytics-link', '');
      var formsLink = nav.querySelector('[data-campaign-tab="forms"]');
      if (formsLink && formsLink.nextSibling) nav.insertBefore(link, formsLink.nextSibling);
      else nav.appendChild(link);
    }

    var shell = root.querySelector('[data-campaign-analytics-shell]');
    if (!shell) {
      shell = document.createElement('section');
      shell.id = 'campaign-analytics';
      shell.className = 'mg-campaign-analytics-shell';
      shell.hidden = true;
      shell.setAttribute('data-campaign-analytics-shell', '');
      shell.innerHTML = '<header class="mg-campaign-analytics-head"><div><span class="mg-eyebrow">Campaign intelligence</span><h2>Analytics and quality assurance</h2><p>Review recommendations, landing-page readiness, refund validation, and campaign delivery queues from one workspace.</p></div></header><nav class="mg-campaign-analytics-tabs" data-campaign-analytics-tabs aria-label="Campaign analytics sections"></nav><div class="mg-campaign-analytics-content" data-campaign-analytics-content></div>';
      var overview = panelHost.querySelector('[data-campaign-tab-panel="overview"]');
      if (overview && overview.nextSibling) panelHost.insertBefore(shell, overview.nextSibling);
      else panelHost.appendChild(shell);
    }
    return { link: link, shell: shell, tabs: shell.querySelector('[data-campaign-analytics-tabs]'), content: shell.querySelector('[data-campaign-analytics-content]') };
  }

  function findPanelForLink(link, key) {
    var href = link ? String(link.getAttribute('href') || '') : '';
    if (href.charAt(0) === '#') {
      var byId = root.querySelector(href);
      if (byId) return byId;
    }
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-campaign-tab-panel]'));
    return panels.find(function (panel) { return panelKey(panel) === key; }) || null;
  }

  function moveAnalyticsSection(key, shellParts) {
    var link = Array.prototype.slice.call(nav.querySelectorAll('a')).find(function (item) { return linkKey(item) === key; });
    if (!link) {
      link = Array.prototype.slice.call(root.querySelectorAll('[data-predictive-campaign-tab-link], [data-campaign-tab-link], a[href^="#campaign-"]')).find(function (item) {
        return item.closest('[data-campaign-analytics-tabs]') == null && linkKey(item) === key;
      });
    }
    var panel = findPanelForLink(link, key);
    if (!panel) {
      panel = Array.prototype.slice.call(root.querySelectorAll('[data-campaign-tab-panel]')).find(function (item) { return panelKey(item) === key; }) || null;
    }
    if (!link || !panel) return false;

    link.setAttribute('data-campaign-analytics-key', key);
    link.classList.remove('is-active');
    link.removeAttribute('aria-current');
    shellParts.tabs.appendChild(link);

    panel.setAttribute('data-campaign-analytics-key', key);
    panel.classList.remove('is-active');
    panel.hidden = true;
    shellParts.content.appendChild(panel);
    return true;
  }

  function showAnalyticsShell(shellParts) {
    shellParts.shell.hidden = false;
    root.querySelectorAll('.mg-campaign-tab-panels > [data-campaign-tab-panel]').forEach(function (panel) {
      panel.classList.remove('is-active');
      panel.hidden = true;
    });
    nav.querySelectorAll('a').forEach(function (item) {
      item.classList.toggle('is-active', item === shellParts.link);
      if (item === shellParts.link) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
  }

  function hideAnalyticsShell(shellParts) {
    shellParts.shell.hidden = true;
    shellParts.link.classList.remove('is-active');
    shellParts.link.removeAttribute('aria-current');
  }

  function activateAnalyticsSection(key, shellParts) {
    showAnalyticsShell(shellParts);
    var link = shellParts.tabs.querySelector('[data-campaign-analytics-key="' + key + '"]');
    if (!link) link = shellParts.tabs.querySelector('a');
    if (!link) return;
    link.click();
    window.setTimeout(function () {
      showAnalyticsShell(shellParts);
      shellParts.tabs.querySelectorAll('a').forEach(function (item) {
        var active = item === link;
        item.classList.toggle('is-active', active);
        if (active) item.setAttribute('aria-current', 'page');
        else item.removeAttribute('aria-current');
      });
      if (history.replaceState) history.replaceState(null, '', '#campaign-analytics-' + linkKey(link));
    }, 0);
  }

  function organize() {
    if (organizing) return;
    organizing = true;
    try {
      removeSideContent();
      removedTopTabs.forEach(removeTopTab);
      var shellParts = ensureAnalyticsShell();
      analyticsKeys.forEach(function (key) { moveAnalyticsSection(key, shellParts); });
      root.classList.add('is-campaign-cleanup-ready');

      if (!shellParts.link.dataset.campaignAnalyticsBound) {
        shellParts.link.dataset.campaignAnalyticsBound = 'true';
        shellParts.link.addEventListener('click', function (event) {
          event.preventDefault();
          activateAnalyticsSection('recommendations', shellParts);
        });
      }

      if (!nav.dataset.campaignAnalyticsBound) {
        nav.dataset.campaignAnalyticsBound = 'true';
        nav.addEventListener('click', function (event) {
          var link = event.target.closest('a');
          if (!link || link === shellParts.link) return;
          hideAnalyticsShell(shellParts);
        }, true);
      }

      if (!shellParts.tabs.dataset.campaignAnalyticsBound) {
        shellParts.tabs.dataset.campaignAnalyticsBound = 'true';
        shellParts.tabs.addEventListener('click', function (event) {
          var link = event.target.closest('a');
          if (!link) return;
          showAnalyticsShell(shellParts);
          window.setTimeout(function () {
            showAnalyticsShell(shellParts);
            shellParts.tabs.querySelectorAll('a').forEach(function (item) {
              item.classList.toggle('is-active', item === link);
            });
          }, 0);
        }, true);
      }

      var hash = normalize(window.location.hash);
      analyticsKeys.some(function (key) {
        if (hash.indexOf(key) !== -1 || (key === 'recommendations' && hash.indexOf('recommend') !== -1)) {
          activateAnalyticsSection(key, shellParts);
          return true;
        }
        return false;
      });
    } finally {
      organizing = false;
    }
  }

  organize();
  window.setTimeout(organize, 80);
  window.setTimeout(organize, 320);

  observer = new MutationObserver(function () {
    window.clearTimeout(observer._campaignCleanupTimer);
    observer._campaignCleanupTimer = window.setTimeout(organize, 20);
  });
  observer.observe(root, { childList: true, subtree: true });
});

window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var activePopover = null;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[character];
    });
  }
  function text(selector, scope) { var node = qs(selector, scope); return node ? node.textContent.trim() : ''; }
  function percent(value) { var parsed = parseFloat(value); return Number.isFinite(parsed) ? parsed : 50; }
  function currentTier() { return root.dataset.worldZoomTier || 'world'; }

  function decorateNode(node) {
    var type = node.dataset.worldType || 'avatar';
    var title = text('.mg-world-node-copy strong', node) || (type === 'merchant' ? 'Merchant' : type === 'reward' ? 'Reward' : type === 'claim' ? 'Claim' : 'User');
    var subtitle = text('.mg-world-node-copy span', node) || '';
    var meta = text('.mg-world-node-meta', node) || '';
    node.dataset.worldTitle = title;
    node.dataset.worldSubtitle = subtitle;
    node.dataset.worldMeta = meta;
    node.dataset.worldProgressiveNode = '1';
    node.setAttribute('aria-label', [title, subtitle, meta].filter(Boolean).join(' · '));
    node.classList.remove('is-cluster-hidden');
  }

  function clearLegacyClusters() {
    qsa('.mg-world-dot-cluster-layer,.mg-world-geo-cluster-layer').forEach(function (layer) { layer.replaceChildren(); layer.hidden = true; });
    qsa('[data-world-node]').forEach(decorateNode);
    var rewardRadius = qs('[data-world-reward-radius-layer]');
    if (rewardRadius) rewardRadius.replaceChildren();
  }

  function nodeInfo(node) {
    var type = node.dataset.worldType || 'avatar';
    return {
      type: type,
      title: node.dataset.worldTitle || 'World Canvas signal',
      subtitle: node.dataset.worldSubtitle || 'Active World Canvas signal',
      meta: node.dataset.worldMeta || 'Active',
      location: node.dataset.worldLocationKey || 'World location',
      affinity: node.dataset.worldAffinity || 'activity, location, affinity',
      geo: node.dataset.worldGeoLocked === 'true' || node.classList.contains('is-geo') ? 'Geo anchored' : 'Affinity placed'
    };
  }

  function closePopover() {
    if (activePopover && activePopover.parentNode) activePopover.remove();
    activePopover = null;
    qsa('[data-world-node].is-selected').forEach(function (node) { node.classList.remove('is-selected'); });
  }

  function openPopover(node) {
    var map = qs('[data-world-map]');
    if (!map) return;
    closePopover();
    node.classList.add('is-selected');
    var info = nodeInfo(node);
    var popover = document.createElement('article');
    popover.className = 'mg-world-dot-popover is-' + info.type;
    popover.style.left = percent(node.style.left || node.dataset.worldTargetX || 50) + '%';
    popover.style.top = percent(node.style.top || node.dataset.worldTargetY || 50) + '%';
    popover.dataset.worldTargetNodeId = node.dataset.worldNodeId || '';
    popover.innerHTML = '<header><span>' + escapeHtml(info.type) + '</span><button type="button" data-world-popover-close aria-label="Close">×</button></header><strong>' + escapeHtml(info.title) + '</strong><p>' + escapeHtml(info.subtitle) + '</p><dl><dt>Status</dt><dd>' + escapeHtml(info.meta) + '</dd><dt>Placement</dt><dd>' + escapeHtml(info.geo) + '</dd><dt>Location</dt><dd>' + escapeHtml(info.location) + '</dd><dt>Affinity</dt><dd>' + escapeHtml(info.affinity) + '</dd></dl><footer><button type="button" data-world-open-detail>Open detail</button></footer>';
    map.appendChild(popover);
    activePopover = popover;
  }

  function addFocusStrip() {
    var head = qs('.mg-world-stage-head');
    if (!head || qs('[data-world-focus-strip]')) return;
    var strip = document.createElement('div');
    strip.className = 'mg-world-focus-strip';
    strip.dataset.worldFocusStrip = '1';
    strip.innerHTML = '<button type="button" class="is-active" data-world-focus="all">All</button><button type="button" data-world-focus="avatar">Avatars</button><button type="button" data-world-focus="merchant">Merchants</button><button type="button" data-world-focus="reward">Rewards</button><button type="button" data-world-focus="claim">Claims</button><button type="button" data-world-focus="affinity">Affinity</button>';
    head.parentNode.insertBefore(strip, head.nextSibling);
  }

  function loadIdentityLayer() {
    if (!document.querySelector('link[data-world-identity-layer-css]')) {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = '/assets/css/world-canvas-identity-layer.css';
      link.dataset.worldIdentityLayerCss = '1';
      document.head.appendChild(link);
    }
    if (!document.querySelector('script[data-world-identity-layer-js]')) {
      var script = document.createElement('script');
      script.src = '/assets/js/world-canvas-identity-layer.js';
      script.defer = true;
      script.dataset.worldIdentityLayerJs = '1';
      document.body.appendChild(script);
    }
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-world-popover-close]')) { closePopover(); return; }
    if (event.target.closest('[data-world-open-detail]')) {
      var id = activePopover ? activePopover.dataset.worldTargetNodeId : '';
      var node = id ? qs('[data-world-node-id="' + CSS.escape(id) + '"]') : null;
      closePopover();
      if (node) node.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      return;
    }

    var focus = event.target.closest('[data-world-focus]');
    if (focus && root.contains(focus)) {
      root.dataset.worldFocus = focus.dataset.worldFocus || 'all';
      qsa('[data-world-focus]').forEach(function (button) { button.classList.toggle('is-active', button === focus); });
      return;
    }

    var node = event.target.closest('[data-world-node]');
    if (node && root.contains(node)) {
      decorateNode(node);
      if (['world','region','city'].indexOf(currentTier()) !== -1) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        openPopover(node);
      } else {
        closePopover();
      }
      return;
    }
    if (!event.target.closest('.mg-world-dot-popover')) closePopover();
  }, true);

  document.addEventListener('mg:world-zoom-change', function () {
    clearLegacyClusters();
    if (activePopover && ['store','detail'].indexOf(currentTier()) !== -1) closePopover();
  });

  var nodeLayer = qs('[data-world-nodes]');
  if (nodeLayer) new MutationObserver(clearLegacyClusters).observe(nodeLayer, { childList: true, subtree: true });

  addFocusStrip();
  loadIdentityLayer();
  clearLegacyClusters();
  window.setInterval(clearLegacyClusters, 1200);
})(window, document);

window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;

  function qs(sel, scope) { return (scope || root).querySelector(sel); }
  function qsa(sel, scope) { return Array.from((scope || root).querySelectorAll(sel)); }
  function clean(value) { return String(value == null ? '' : value).replace(/\s+/g, ' ').trim(); }
  function esc(value) {
    return clean(value).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; });
  }
  function text(sel, scope) { var node = qs(sel, scope); return node ? clean(node.textContent) : ''; }
  function initials(value, fallback) {
    var words = clean(value).split(' ').filter(Boolean);
    if (!words.length) return fallback || 'AV';
    if (words.length === 1) return words[0].slice(0,2).toUpperCase();
    return (words[0][0] + words[1][0]).toUpperCase();
  }

  function typeFor(node) {
    if (node.classList.contains('is-merchant')) return 'merchant';
    if (node.classList.contains('is-reward')) return 'reward';
    if (node.classList.contains('is-claim')) return 'claim';
    if (node.classList.contains('is-avatar')) return 'avatar';
    return node.dataset.worldType || 'avatar';
  }

  function placementFor(node) {
    if (node.dataset.worldGeoLocked === '1' || node.classList.contains('is-geo')) return 'Geo anchored';
    if ((node.dataset.worldLocationKey || '').indexOf('merchant:') === 0) return 'Store anchored';
    if (node.classList.contains('is-reward')) return 'Reward drop zone';
    if (node.classList.contains('is-claim')) return 'Claim signal';
    return 'Affinity placed';
  }

  function relationshipFor(node) {
    var meta = clean(text('.mg-world-node-meta', node) + ' ' + text('.mg-world-node-subtitle', node)).toLowerCase();
    if (node.classList.contains('is-reward')) return 'Reward available';
    if (node.classList.contains('is-claim') || meta.indexOf('claim') !== -1) return 'Claimed';
    if (meta.indexOf('message') !== -1 || meta.indexOf('chat') !== -1) return 'Messaging';
    if (meta.indexOf('exit') !== -1) return 'Exited';
    if (node.classList.contains('is-merchant')) return 'Live store';
    return 'Browsing';
  }

  function identityFor(node) {
    var type = typeFor(node);
    var title = node.dataset.worldTitle || text('.mg-world-node-title', node) || '';
    var label = 'Anonymous avatar';
    var badge = 'AV';
    if (type === 'merchant') { label = node.classList.contains('is-owned') ? 'Owned merchant avatar' : 'Merchant avatar'; badge = initials(title || 'Merchant', 'M'); }
    else if (type === 'reward') { label = 'Reward marker'; badge = 'R'; }
    else if (type === 'claim') { label = 'Claim marker'; badge = 'C'; }
    else { label = node.classList.contains('is-owned') ? 'My avatar' : 'Anonymous user avatar'; badge = initials(title || 'Avatar', 'AV'); }
    return { type:type, label:label, badge:badge, title:title || label, placement:placementFor(node), relationship:relationshipFor(node), affinity:node.dataset.worldAffinity || 'Local commerce activity' };
  }

  function enrichNodes() {
    qsa('[data-world-node]').forEach(function (node) {
      var info = identityFor(node);
      node.dataset.worldIdentityLabel = info.label;
      node.dataset.worldPlacementReason = info.placement;
      node.dataset.worldRelationship = info.relationship.toLowerCase().replace(/\s+/g, '_');
      node.dataset.worldTitle = node.dataset.worldTitle || info.title;
      var badge = qs('.mg-world-avatar-badge', node);
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'mg-world-avatar-badge';
        node.appendChild(badge);
      }
      badge.textContent = info.badge;
      badge.title = info.label;
    });
  }

  function addLegend() {
    var strip = qs('[data-world-focus-strip]') || qs('.mg-world-stage-head');
    if (!strip || qs('[data-world-identity-legend]')) return;
    var legend = document.createElement('div');
    legend.className = 'mg-world-identity-legend';
    legend.dataset.worldIdentityLegend = '1';
    legend.innerHTML = '<span class="is-owned"><i></i>Owned merchant</span><span class="is-public"><i></i>Public merchant</span><span class="is-anon"><i></i>Anonymous avatar</span><span class="is-reward"><i></i>Reward marker</span>';
    strip.parentNode.insertBefore(legend, strip.nextSibling);
  }

  function popoverNode(popover) {
    var id = popover.dataset.worldTargetNodeId || '';
    return id ? qs('[data-world-node-id="' + id + '"]') : null;
  }

  function enrichPopover(popover) {
    if (!popover || popover.dataset.identityEnhanced === '1') return;
    var node = popoverNode(popover);
    if (!node) return;
    var info = identityFor(node);
    var panel = document.createElement('section');
    panel.className = 'mg-world-identity-summary';
    panel.innerHTML = '<strong>Identity layer <span>' + esc(info.label) + '</span></strong><ul><li>Relationship <b>' + esc(info.relationship) + '</b></li><li>Placement <b>' + esc(info.placement) + '</b></li><li>Affinity <b>' + esc(info.affinity) + '</b></li><li>Map object <b>' + esc(info.type) + '</b></li></ul>';
    var footer = qs('footer', popover);
    if (footer) popover.insertBefore(panel, footer); else popover.appendChild(panel);
    popover.dataset.identityEnhanced = '1';
  }

  function watchPopovers() {
    qsa('.mg-world-dot-popover').forEach(enrichPopover);
  }

  addLegend();
  enrichNodes();
  watchPopovers();
  window.setInterval(function () { enrichNodes(); watchPopovers(); }, 1000);
})(window, document);

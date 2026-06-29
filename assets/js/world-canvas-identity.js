window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;

  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }

  function identityFor(node) {
    var type = node.dataset.worldType || '';
    if (type === 'merchant') return { mode: node.classList.contains('is-owned') ? 'my-merchant' : 'public-merchant', label: node.classList.contains('is-owned') ? 'My merchant' : 'Merchant' };
    if (type !== 'avatar') return { mode: 'signal', label: type ? type.charAt(0).toUpperCase() + type.slice(1) : 'Signal' };
    if (node.classList.contains('is-owned')) return { mode: 'my-avatar', label: 'My avatar' };
    if (node.classList.contains('is-geo')) return { mode: 'anonymous-geo', label: 'Anon + geo' };
    return { mode: 'anonymous', label: 'Anonymous' };
  }

  function decorateNode(node) {
    if (!node || node.dataset.worldIdentityDecorated === '1') return;
    var identity = identityFor(node);
    node.dataset.worldIdentity = identity.mode;
    var badge = document.createElement('span');
    badge.className = 'mg-world-identity-badge is-' + identity.mode;
    badge.textContent = identity.label;
    node.appendChild(badge);
    node.dataset.worldIdentityDecorated = '1';
  }

  function decorateAll() {
    qsa('[data-world-node]').forEach(decorateNode);
  }

  var layer = root.querySelector('[data-world-nodes]');
  if (layer) {
    new MutationObserver(decorateAll).observe(layer, { childList: true, subtree: true });
  }
  decorateAll();
})(window, document);

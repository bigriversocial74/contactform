(function () {
  'use strict';

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: String(currency || 'USD').toUpperCase()
      }).format(Number(cents || 0) / 100);
    } catch (error) {
      return '$' + (Number(cents || 0) / 100).toFixed(2);
    }
  }

  function renderCard(card, helpers) {
    if (!card || card.type !== 'marketplace_product') return '';
    var esc = helpers.esc;
    var internalUrl = helpers.internalUrl;
    var href = internalUrl(card.url || '');
    var image = internalUrl(card.image_url || '');
    var payload = esc(JSON.stringify(card.review_payload || {}));
    var action = '';

    if (card.action === 'shortlist_product') {
      action = '<button type="button" data-shortlist-product data-shortlist-payload="' + payload + '">' + esc(card.action_label || 'Shortlist') + '</button>';
    } else if (card.action === 'remove_shortlist') {
      action = '<button type="button" data-shortlist-remove data-shortlist-payload="' + payload + '">' + esc(card.action_label || 'Remove from shortlist') + '</button>';
    }

    var mediaTag = href ? 'a' : 'div';
    var mediaAttributes = href ? ' href="' + esc(href) + '" data-agent-open-link' : '';
    var media = '<' + mediaTag + ' class="mg-agent-shortlist-media"' + mediaAttributes + '>';
    media += image
      ? '<img src="' + esc(image) + '" alt="" loading="lazy" decoding="async">'
      : esc(String(card.title || 'G').charAt(0).toUpperCase());
    media += '</' + mediaTag + '>';

    return '<article class="is-marketplace_product" data-agent-product-card>'
      + media
      + '<div class="mg-agent-shortlist-copy">'
      + '<span>Published local gift</span>'
      + '<h4>' + esc(card.title || 'Local gift') + '</h4>'
      + '<p>' + esc(card.body || '') + '</p>'
      + '<div class="mg-agent-shortlist-meta">'
      + '<strong>' + esc(money(card.price_cents, card.currency)) + '</strong>'
      + '<span>' + esc(card.merchant_name || 'Local merchant') + '</span>'
      + (card.location ? '<span>' + esc(card.location) + '</span>' : '')
      + '</div></div>'
      + '<div class="mg-agent-shortlist-actions">'
      + (href ? '<a href="' + esc(href) + '" data-agent-open-link>Review product</a>' : '')
      + action
      + '</div></article>';
  }

  window.MicrogifterTaskAgentShortlist = { renderCard: renderCard };

  document.addEventListener('DOMContentLoaded', function () {
    var selectedNode = document.getElementById('mg-selected-agent-id');
    var agentId = selectedNode ? JSON.parse(selectedNode.textContent || '""') : '';
    var root = document.querySelector('[data-agent-instance-canvas]');
    if (!agentId || !root) return;

    var status = root.querySelector('[data-agent-runtime-status]');

    function csrf() {
      var node = document.querySelector('meta[name="csrf-token"]');
      return node ? node.content : '';
    }

    async function request(payload) {
      var response = await fetch('/api/agents/runtime.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrf()
        },
        body: JSON.stringify(payload)
      });
      var json = await response.json();
      if (!response.ok || !json.ok) throw new Error(json.message || 'Unable to update the shortlist.');
      return json.data || json;
    }

    function payload(button) {
      try {
        return JSON.parse(button.getAttribute('data-shortlist-payload') || '{}');
      } catch (error) {
        return {};
      }
    }

    document.addEventListener('click', function (event) {
      var addButton = event.target.closest('[data-shortlist-product]');
      if (addButton) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var addPayload = payload(addButton);
        addButton.disabled = true;
        request({
          id: agentId,
          action: 'add_shortlist',
          product_id: addPayload.product_id || '',
          recipient_context: addPayload.recipient_context || {}
        }).then(function () {
          addButton.textContent = 'Shortlisted';
          if (status) status.textContent = 'Product added to this agent’s shortlist. No AI credits used.';
        }).catch(function (error) {
          addButton.disabled = false;
          if (status) status.textContent = error.message;
        });
        return;
      }

      var removeButton = event.target.closest('[data-shortlist-remove]');
      if (!removeButton) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      var removePayload = payload(removeButton);
      removeButton.disabled = true;
      request({
        id: agentId,
        action: 'remove_shortlist',
        shortlist_id: removePayload.shortlist_id || ''
      }).then(function () {
        var card = removeButton.closest('[data-agent-product-card]');
        if (card) card.remove();
        if (status) status.textContent = 'Product removed from this agent’s shortlist. No AI credits used.';
      }).catch(function (error) {
        removeButton.disabled = false;
        if (status) status.textContent = error.message;
      });
    }, true);
  });
})();

(function () {
  'use strict';

  var prior = window.MicrogifterTaskAgentShortlist || {};
  var priorRender = typeof prior.renderCard === 'function' ? prior.renderCard : function () { return ''; };

  function text(value) { return String(value == null ? '' : value); }
  function esc(value) {
    return text(value).replace(/[&<>"']/g, function (ch) {
      return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' })[ch];
    });
  }
  function internalUrl(value) {
    try {
      var parsed = new URL(text(value), window.location.origin);
      if (parsed.origin !== window.location.origin || ['http:','https:'].indexOf(parsed.protocol) === -1) return '';
      return parsed.pathname + parsed.search + parsed.hash;
    } catch (error) { return ''; }
  }
  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style:'currency', currency:text(currency || 'USD').toUpperCase() }).format(Number(cents || 0) / 100);
    } catch (error) { return '$' + (Number(cents || 0) / 100).toFixed(2); }
  }
  function statusLabel(value) {
    return text(value || 'pending').replace(/_/g, ' ');
  }

  function renderTracking(card) {
    if (!card || card.type !== 'purchase_tracking') return '';
    var order = card.order || {};
    var line = card.line || {};
    var plan = card.plan || {};
    var receipt = card.receipt || null;
    var issuance = card.issuance || {};
    var links = card.links || {};
    var confirmation = internalUrl(links.confirmation || card.url || '');
    var orders = internalUrl(links.orders || '/account/orders.php');
    var commerce = internalUrl(links.commerce_center || '/account-commerce.php');
    var inbox = internalUrl(links.inbox || '/inbox.php');

    return '<article class="is-purchase_tracking mg-purchase-tracking-card">'
      + '<span>Purchase and PPPM tracking</span>'
      + '<h4>' + esc(card.title || line.title || 'Purchased gift') + '</h4>'
      + '<p>' + esc(card.body || '') + '</p>'
      + '<div class="mg-purchase-tracking-summary">'
      + '<div><small>Gift plan</small><strong>' + esc(plan.title || 'Gift plan') + '</strong></div>'
      + '<div><small>Order total</small><strong>' + esc(money(order.total_cents, order.currency)) + '</strong></div>'
      + '<div><small>Payment</small><strong>' + esc(statusLabel(order.payment_status)) + '</strong></div>'
      + '<div><small>Fulfillment</small><strong>' + esc(statusLabel(order.fulfillment_status)) + '</strong></div>'
      + '</div>'
      + '<div class="mg-purchase-tracking-issuance">'
      + '<div><strong>' + esc(issuance.issued_units || 0) + '/' + esc(issuance.expected_units || 0) + '</strong><small>Issued units</small></div>'
      + '<div><strong>' + esc(issuance.pppm_items || 0) + '</strong><small>PPPM items</small></div>'
      + '<div><strong>' + esc(issuance.microgifts || 0) + '</strong><small>Microgifts</small></div>'
      + '<div><strong>' + esc(issuance.inbox_items || 0) + '</strong><small>Inbox items</small></div>'
      + '</div>'
      + '<dl class="mg-purchase-tracking-meta">'
      + '<div><dt>Order</dt><dd>' + esc(order.id || '') + '</dd></div>'
      + '<div><dt>Receipt</dt><dd>' + esc(receipt ? receipt.number : 'Pending') + '</dd></div>'
      + '<div><dt>Issuance</dt><dd>' + esc(statusLabel(issuance.state)) + '</dd></div>'
      + '<div><dt>Match</dt><dd>Exact product version</dd></div>'
      + '</dl>'
      + '<div class="mg-agent-shortlist-actions">'
      + (confirmation ? '<a href="' + esc(confirmation) + '" data-agent-open-link>Open order confirmation</a>' : '')
      + (inbox ? '<a href="' + esc(inbox) + '" data-agent-open-link>Open Inbox</a>' : '')
      + (orders ? '<a href="' + esc(orders) + '" data-agent-open-link>View orders</a>' : '')
      + (commerce ? '<a href="' + esc(commerce) + '" data-agent-open-link>Commerce center</a>' : '')
      + '</div>'
      + '<small class="mg-purchase-tracking-safety">Read only · No payment, issuance repair, refund, send, claim, or redemption action</small>'
      + '</article>';
  }

  window.MicrogifterTaskAgentShortlist = {
    renderCard: function (card, helpers) {
      return renderTracking(card) || priorRender(card, helpers);
    }
  };
})();

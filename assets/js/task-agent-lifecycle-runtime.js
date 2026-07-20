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
    try { return new Intl.NumberFormat(undefined, { style:'currency', currency:text(currency || 'USD').toUpperCase() }).format(Number(cents || 0) / 100); }
    catch (error) { return '$' + (Number(cents || 0) / 100).toFixed(2); }
  }
  function label(value) { return text(value || 'unknown').replace(/_/g, ' '); }

  function capabilityRows(card) {
    var values = card.capabilities || {};
    var reasons = card.capability_reasons || {};
    var names = { send:'Send or regift', claim:'Claim', redeem:'Redeem', follow_up:'Follow up', message:'Message' };
    return Object.keys(names).map(function (key) {
      var enabled = !!values[key];
      var reason = text(reasons[key] || (enabled ? 'Available in Action Center' : 'Not available in the current lifecycle state'));
      return '<li class="' + (enabled ? 'is-available' : 'is-unavailable') + '"><div><strong>' + esc(names[key]) + '</strong><small>' + esc(reason) + '</small></div><span>' + (enabled ? 'Available' : 'Unavailable') + '</span></li>';
    }).join('');
  }

  function renderLifecycle(card) {
    if (!card || card.type !== 'gift_lifecycle_tracking') return '';
    var gift = card.gift || {};
    var activity = card.activity || {};
    var participants = card.participants || {};
    var redemption = card.redemption || {};
    var href = internalUrl(card.url || '/inbox.php');
    return '<article class="is-gift_lifecycle_tracking mg-lifecycle-card">'
      + '<span>Gift lifecycle</span><h4>' + esc(card.title || gift.title || 'Microgift') + '</h4><p>' + esc(card.body || '') + '</p>'
      + '<div class="mg-lifecycle-summary">'
      + '<div><small>Folder</small><strong>' + esc(label(card.folder)) + '</strong></div>'
      + '<div><small>State</small><strong>' + esc(label(gift.state || gift.status)) + '</strong></div>'
      + '<div><small>Value</small><strong>' + esc(money(gift.value_cents, gift.currency)) + '</strong></div>'
      + '<div><small>Expires</small><strong>' + esc(gift.expires_at || 'No expiry shown') + '</strong></div>'
      + '</div>'
      + '<div class="mg-lifecycle-participants"><span>From <strong>' + esc(participants.sender_name || 'Microgifter') + '</strong></span><span>To <strong>' + esc(participants.recipient_name || 'Recipient') + '</strong></span></div>'
      + '<dl class="mg-lifecycle-activity">'
      + '<div><dt>Sent</dt><dd>' + esc(activity.sent_at || 'Not sent') + '</dd></div>'
      + '<div><dt>Claimed</dt><dd>' + esc(activity.claimed_at || 'Not claimed') + '</dd></div>'
      + '<div><dt>Redeemed</dt><dd>' + esc(activity.redeemed_at || redemption.redeemed_at || 'Not redeemed') + '</dd></div>'
      + '<div><dt>Follow-ups</dt><dd>' + esc(activity.follow_up_count || 0) + '</dd></div>'
      + '</dl>'
      + '<ul class="mg-lifecycle-capabilities">' + capabilityRows(card) + '</ul>'
      + '<div class="mg-agent-shortlist-actions">' + (href ? '<a href="' + esc(href) + '" data-agent-open-link>Open Action Center</a>' : '') + '</div>'
      + '<small class="mg-lifecycle-safety">Read only · Send, regift, claim, redemption, follow-up, and messaging require an explicit Action Center action</small>'
      + '</article>';
  }

  window.MicrogifterTaskAgentShortlist = {
    renderCard: function (card, helpers) {
      return renderLifecycle(card) || priorRender(card, helpers);
    }
  };
})();

(function () {
  'use strict';

  var prior = window.MicrogifterTaskAgentShortlist || {};
  var priorRender = typeof prior.renderCard === 'function' ? prior.renderCard : function () { return ''; };

  function money(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: String(currency || 'USD').toUpperCase() }).format(Number(cents || 0) / 100);
    } catch (error) {
      return '$' + (Number(cents || 0) / 100).toFixed(2);
    }
  }

  function readinessRows(card, esc) {
    var readiness = card.readiness || {};
    return [
      ['Product selected', !!readiness.selected_product],
      ['Recipient identified', !!readiness.recipient_identified],
      ['Delivery address available', !!readiness.delivery_address_available],
      ['Gift preferences available', !!readiness.gift_preferences_available]
    ].map(function (row) {
      return '<li class="' + (row[1] ? 'is-ready' : 'is-missing') + '"><span>' + esc(row[0]) + '</span><strong>' + (row[1] ? 'Ready' : 'Needed') + '</strong></li>';
    }).join('');
  }

  function renderDelivery(card, helpers) {
    if (!card || !['delivery_preparation', 'recipient_permission_request'].includes(card.type)) return '';
    var esc = helpers.esc;
    var product = card.product || {};
    var plan = card.plan || {};
    var recipient = card.recipient || {};
    var schedule = card.schedule || null;
    var payload = esc(JSON.stringify(card.review_payload || {}));
    var action = '';

    if (card.action === 'create_delivery_schedule') {
      action = '<button type="button" data-delivery-schedule-create data-delivery-payload="' + payload + '">' + esc(card.action_label || 'Create send-later preparation') + '</button>';
    } else if (card.action === 'update_delivery_schedule') {
      action = '<button type="button" data-delivery-schedule-update data-delivery-payload="' + payload + '">' + esc(card.action_label || 'Update preparation') + '</button>';
    } else if (card.action === 'manage_delivery_schedule') {
      action = '<div class="mg-delivery-manage" data-delivery-payload="' + payload + '">'
        + '<button type="button" data-delivery-schedule-action="approve">Approve</button>'
        + '<button type="button" data-delivery-schedule-action="pause">Pause</button>'
        + '<button type="button" data-delivery-schedule-action="resume">Resume</button>'
        + '<button type="button" data-delivery-schedule-action="prepare">Mark prepared</button>'
        + '<button type="button" data-delivery-schedule-action="cancel">Cancel</button>'
        + '</div>';
    } else if (card.action === 'create_recipient_request') {
      action = '<button type="button" data-recipient-request-create data-delivery-payload="' + payload + '">' + esc(card.action_label || 'Send permission request') + '</button>';
    } else if (card.action === 'seed_prompt' && card.prompt) {
      action = '<button type="button" data-agent-seed-prompt="' + esc(card.prompt) + '">' + esc(card.action_label || 'Continue in chat') + '</button>';
    }

    var scheduleHtml = schedule
      ? '<dl class="mg-delivery-schedule"><div><dt>Status</dt><dd>' + esc(schedule.status || 'draft') + '</dd></div><div><dt>Prepared for</dt><dd>' + esc(schedule.scheduled_for || '') + ' · ' + esc(schedule.timezone || 'UTC') + '</dd></div><div><dt>Mode</dt><dd>Prepare only</dd></div></dl>'
      : '<p class="mg-delivery-no-schedule">No send-later preparation has been created.</p>';

    if (card.type === 'recipient_permission_request') {
      return '<article class="is-recipient_permission_request mg-delivery-card">'
        + '<span>Recipient permission</span><h4>' + esc(card.title || 'Request gifting information') + '</h4>'
        + '<p>' + esc(card.body || '') + '</p>'
        + '<div class="mg-delivery-recipient"><strong>' + esc(recipient.name || 'Connected recipient') + '</strong><small>Recipient-controlled sharing</small></div>'
        + '<div class="mg-agent-shortlist-actions">' + action + '</div></article>';
    }

    return '<article class="is-delivery_preparation mg-delivery-card">'
      + '<span>Delivery preparation</span><h4>' + esc(card.title || 'Selected gift') + '</h4><p>' + esc(card.body || '') + '</p>'
      + '<div class="mg-delivery-summary"><div><small>Plan</small><strong>' + esc(plan.title || 'Gift plan') + '</strong></div><div><small>Recipient</small><strong>' + esc(recipient.name || 'Not identified') + '</strong></div><div><small>Product value</small><strong>' + esc(money(product.value_cents, product.currency)) + '</strong></div></div>'
      + '<ul class="mg-delivery-readiness">' + readinessRows(card, esc) + '</ul>'
      + scheduleHtml
      + '<div class="mg-agent-shortlist-actions">' + action + '</div>'
      + '<small class="mg-delivery-safety">Approval required · No purchase, send, claim, or redemption</small>'
      + '</article>';
  }

  window.MicrogifterTaskAgentShortlist = {
    renderCard: function (card, helpers) {
      return renderDelivery(card, helpers) || priorRender(card, helpers);
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    var selectedNode = document.getElementById('mg-selected-agent-id');
    var agentId = selectedNode ? JSON.parse(selectedNode.textContent || '""') : '';
    var root = document.querySelector('[data-agent-instance-canvas]');
    var status = root ? root.querySelector('[data-agent-runtime-status]') : null;
    if (!agentId || !root) return;

    function csrf() {
      var node = document.querySelector('meta[name="csrf-token"]');
      return node ? node.content : '';
    }

    function decode(button) {
      try { return JSON.parse(button.getAttribute('data-delivery-payload') || '{}'); }
      catch (error) { return {}; }
    }

    async function request(payload) {
      var response = await fetch('/api/agents/runtime.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
        body: JSON.stringify(Object.assign({ id: agentId }, payload))
      });
      var json = await response.json();
      if (!response.ok || !json.ok) throw new Error(json.message || 'Unable to update delivery preparation.');
      return json.data || json;
    }

    function complete(button, message) {
      button.disabled = true;
      if (status) status.textContent = message;
      window.setTimeout(function () { window.location.reload(); }, 250);
    }

    document.addEventListener('click', function (event) {
      var create = event.target.closest('[data-delivery-schedule-create]');
      if (create) {
        event.preventDefault(); event.stopImmediatePropagation(); create.disabled = true;
        var createPayload = decode(create);
        request(Object.assign({ action: 'create_delivery_schedule' }, createPayload)).then(function () {
          complete(create, 'Send-later preparation created. No gift was sent and no AI credits were used.');
        }).catch(function (error) { create.disabled = false; if (status) status.textContent = error.message; });
        return;
      }

      var update = event.target.closest('[data-delivery-schedule-update]');
      if (update) {
        event.preventDefault(); event.stopImmediatePropagation(); update.disabled = true;
        var updatePayload = decode(update);
        request(Object.assign({ action: 'update_delivery_schedule' }, updatePayload)).then(function () {
          complete(update, 'Delivery preparation updated. No commerce was executed.');
        }).catch(function (error) { update.disabled = false; if (status) status.textContent = error.message; });
        return;
      }

      var manage = event.target.closest('[data-delivery-schedule-action]');
      if (manage) {
        event.preventDefault(); event.stopImmediatePropagation(); manage.disabled = true;
        var holder = manage.closest('[data-delivery-payload]');
        var managePayload = holder ? decode(holder) : {};
        managePayload.action = 'update_delivery_schedule';
        managePayload.schedule_action = manage.getAttribute('data-delivery-schedule-action') || '';
        request(managePayload).then(function () {
          complete(manage, 'Delivery preparation updated. No commerce was executed.');
        }).catch(function (error) { manage.disabled = false; if (status) status.textContent = error.message; });
        return;
      }

      var permission = event.target.closest('[data-recipient-request-create]');
      if (!permission) return;
      event.preventDefault(); event.stopImmediatePropagation(); permission.disabled = true;
      var requestPayload = decode(permission);
      request(Object.assign({ action: 'create_recipient_request' }, requestPayload)).then(function () {
        complete(permission, 'Recipient permission request created. The recipient controls what is shared.');
      }).catch(function (error) { permission.disabled = false; if (status) status.textContent = error.message; });
    }, true);
  });
})();

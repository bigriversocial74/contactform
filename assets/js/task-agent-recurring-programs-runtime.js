(function () {
  'use strict';

  var prior = window.MicrogifterTaskAgentShortlist || {};
  var priorRender = typeof prior.renderCard === 'function' ? prior.renderCard : function () { return ''; };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character];
    });
  }

  function localInput(value) {
    if (!value) return '';
    var raw = String(value).replace(' ', 'T');
    var hasZone = /(?:Z|[+-]\d\d:\d\d)$/i.test(raw);
    var date = new Date(raw + (hasZone ? '' : 'Z'));
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 16).replace(' ', 'T');
    var local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
  }

  function moneyRange(program) {
    var min = program.budget_min;
    var max = program.budget_max;
    var currency = String(program.currency || 'USD').toUpperCase();
    var format = function (value) {
      try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency }).format(Number(value)); }
      catch (error) { return '$' + Number(value).toFixed(2); }
    };
    if (min == null && max == null) return 'No saved budget cap';
    if (min != null && max != null) return format(min) + ' – ' + format(max);
    return max != null ? 'Up to ' + format(max) : 'From ' + format(min);
  }

  function renderBuilder(card) {
    if (!card || card.type !== 'recurring_program_builder') return '';
    var payload = card.review_payload || {};
    return '<article class="is-recurring_program_builder mg-recurring-card mg-recurring-builder">'
      + '<span>Recurring draft program</span><h4>' + esc(card.title || 'Create recurring gift program') + '</h4>'
      + '<p>' + esc(card.body || '') + '</p>'
      + '<form data-recurring-program-create data-recurring-base="' + esc(JSON.stringify(payload)) + '">'
      + '<label class="is-wide">Program title<input name="title" maxlength="190" value="' + esc(payload.title || '') + '" required></label>'
      + '<label>Cadence<select name="cadence">'
      + ['weekly', 'monthly', 'quarterly', 'yearly', 'custom'].map(function (cadence) {
          return '<option value="' + cadence + '"' + (cadence === payload.cadence ? ' selected' : '') + '>' + esc(cadence.charAt(0).toUpperCase() + cadence.slice(1)) + '</option>';
        }).join('')
      + '</select></label>'
      + '<label>Every<input type="number" name="interval_count" min="1" max="52" value="' + esc(payload.interval_count || 1) + '" required></label>'
      + '<label>First review<input type="datetime-local" name="next_run_at" value="' + esc(localInput(payload.next_run_at)) + '" required></label>'
      + '<label>Optional end<input type="datetime-local" name="end_at" value="' + esc(localInput(payload.end_at)) + '"></label>'
      + '<label>Minimum budget<input type="number" name="budget_min" min="0" step="0.01" value="' + esc(payload.budget_min == null ? '' : payload.budget_min) + '"></label>'
      + '<label>Maximum budget<input type="number" name="budget_max" min="0" step="0.01" value="' + esc(payload.budget_max == null ? '' : payload.budget_max) + '"></label>'
      + '<label>Currency<input name="currency" maxlength="3" value="' + esc(payload.currency || 'USD') + '" required></label>'
      + '<div class="mg-recurring-boundary is-wide">Creates draft plans only. No product, cart, charge, message, or delivery is automatic.</div>'
      + '<footer class="is-wide"><button type="submit">' + esc(card.action_label || 'Create recurring program') + '</button></footer>'
      + '</form></article>';
  }

  function actionButton(label, action, payload, primary) {
    return '<button type="button" data-recurring-program-action="' + esc(action) + '" data-recurring-payload="' + esc(JSON.stringify(payload)) + '"' + (primary ? ' class="is-primary"' : '') + '>' + esc(label) + '</button>';
  }

  function renderLink(card) {
    if (!card || card.type !== 'recurring_program_link') return '';
    var program = card.program || {};
    return '<article class="is-recurring_program_link mg-recurring-card">'
      + '<span>Existing Personal Agent program</span>'
      + '<h4>' + esc(card.title || 'Existing recurring program') + '</h4>'
      + '<p>' + esc(card.body || '') + '</p>'
      + '<dl class="mg-recurring-facts">'
      + '<div><dt>Status</dt><dd>' + esc(program.status || 'draft') + '</dd></div>'
      + '<div><dt>Cadence</dt><dd>Every ' + esc(program.interval_count || 1) + ' ' + esc(program.cadence || '') + '</dd></div>'
      + '<div><dt>Next review</dt><dd>' + esc(program.next_run_at || '') + '</dd></div>'
      + '<div><dt>Recipient / list</dt><dd>' + esc(program.context_name || 'General program') + '</dd></div>'
      + '</dl>'
      + '<div class="mg-recurring-actions">' + actionButton(card.action_label || 'Use with this agent', 'link_existing', card.review_payload || {}, true) + '</div>'
      + '<small class="mg-recurring-safety">Reuses the canonical program · No copied data · No AI credits</small>'
      + '</article>';
  }

  function renderProgram(card) {
    if (!card || card.type !== 'recurring_gift_program') return '';
    var program = card.program || {};
    var payload = card.review_payload || {};
    var actions = Array.isArray(program.actions) ? program.actions : [];
    var controls = '';
    if (actions.indexOf('activate') !== -1) controls += actionButton('Activate', 'activate', payload, true);
    if (actions.indexOf('generate_draft') !== -1) controls += actionButton('Generate next draft', 'generate_draft', payload, true);
    if (actions.indexOf('skip_next') !== -1) controls += actionButton('Skip next cycle', 'skip_next', payload, false);
    if (actions.indexOf('pause') !== -1) controls += actionButton('Pause', 'pause', payload, false);
    if (actions.indexOf('resume') !== -1) controls += actionButton('Resume', 'resume', payload, true);
    if (actions.indexOf('cancel') !== -1) controls += actionButton('Cancel', 'cancel', payload, false);

    var lastRun = program.last_run || null;
    var lastRunHtml = lastRun
      ? '<div><dt>Last cycle</dt><dd>' + esc(lastRun.status || '') + (lastRun.plan ? ' · ' + esc(lastRun.plan.title || 'Draft plan') : '') + '</dd></div>'
      : '<div><dt>Last cycle</dt><dd>No cycle generated yet</dd></div>';

    return '<article class="is-recurring_gift_program mg-recurring-card' + (program.due ? ' is-due' : '') + '">'
      + '<span>' + (program.due ? 'Review due' : 'Recurring gift program') + '</span>'
      + '<h4>' + esc(card.title || program.title || 'Recurring gift program') + '</h4>'
      + '<p>' + esc(card.body || '') + '</p>'
      + '<dl class="mg-recurring-facts">'
      + '<div><dt>Status</dt><dd>' + esc(program.status || 'draft') + '</dd></div>'
      + '<div><dt>Recipient / list</dt><dd>' + esc(program.context_name || 'General program') + '</dd></div>'
      + '<div><dt>Next review</dt><dd>' + esc(program.next_run_at || '') + '</dd></div>'
      + '<div><dt>Budget</dt><dd>' + esc(moneyRange(program)) + '</dd></div>'
      + lastRunHtml
      + '</dl>'
      + '<div class="mg-recurring-actions">' + controls + '<a href="' + esc(card.url || '/agent.php?view=recurring') + '">Open recurring programs</a></div>'
      + '<small class="mg-recurring-safety">Approval required · Draft plans only · Zero AI credits · No automatic checkout</small>'
      + '</article>';
  }

  window.MicrogifterTaskAgentShortlist = {
    renderCard: function (card, helpers) {
      return renderBuilder(card) || renderLink(card) || renderProgram(card) || priorRender(card, helpers);
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

    function parse(value) {
      try { return JSON.parse(value || '{}'); }
      catch (error) { return {}; }
    }

    function iso(value) {
      if (!value) return '';
      var date = new Date(value);
      return Number.isNaN(date.getTime()) ? '' : date.toISOString();
    }

    async function request(payload) {
      var response = await fetch('/api/agents/runtime.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
        body: JSON.stringify(Object.assign({ id: agentId }, payload))
      });
      var json = await response.json();
      if (!response.ok || !json.ok) throw new Error(json.message || 'Unable to update recurring program.');
      return json.data || json;
    }

    function finish(message) {
      if (status) status.textContent = message;
      window.setTimeout(function () { window.location.reload(); }, 250);
    }

    function requireFreshState(programAction, payload) {
      if (programAction === 'link_existing') return;
      if (!String(payload.expected_status || '').trim()) {
        throw new Error('Refresh this recurring program before changing its status.');
      }
      if ((programAction === 'generate_draft' || programAction === 'skip_next')
        && !String(payload.expected_next_run_at || '').trim()) {
        throw new Error('Refresh this recurring program before changing its next cycle.');
      }
    }

    document.addEventListener('submit', function (event) {
      var form = event.target.closest('[data-recurring-program-create]');
      if (!form) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      var button = form.querySelector('[type="submit"]');
      if (button) button.disabled = true;
      var payload = parse(form.getAttribute('data-recurring-base'));
      new FormData(form).forEach(function (value, key) { payload[key] = value; });
      payload.action = 'create_recurring_program';
      payload.next_run_at = iso(payload.next_run_at);
      payload.end_at = iso(payload.end_at);
      request(payload).then(function () {
        finish('Recurring draft program created. No commerce or AI was used.');
      }).catch(function (error) {
        if (button) button.disabled = false;
        if (status) status.textContent = error.message;
      });
    }, true);

    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-recurring-program-action]');
      if (!button) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      button.disabled = true;
      var programAction = button.getAttribute('data-recurring-program-action') || '';
      var payload = parse(button.getAttribute('data-recurring-payload'));
      try {
        requireFreshState(programAction, payload);
      } catch (error) {
        button.disabled = false;
        if (status) status.textContent = error.message;
        return;
      }
      if (programAction === 'link_existing') payload.action = 'link_recurring_program';
      else if (programAction === 'generate_draft') payload.action = 'generate_recurring_draft';
      else if (programAction === 'skip_next') payload.action = 'skip_recurring_run';
      else {
        payload.action = 'update_recurring_program';
        payload.program_action = programAction;
      }
      request(payload).then(function () {
        var message = programAction === 'link_existing'
          ? 'Existing recurring program connected to this agent.'
          : (programAction === 'generate_draft'
            ? 'Recurring draft plan prepared for review.'
            : (programAction === 'skip_next' ? 'Next recurring cycle skipped.' : 'Recurring program updated.'));
        finish(message + ' No commerce or AI was used.');
      }).catch(function (error) {
        button.disabled = false;
        if (status) status.textContent = error.message;
      });
    }, true);
  });
})();

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

  var claimList = document.querySelector('[data-claim-list]');
  var claimForm = document.querySelector('[data-claim-verify-form]');
  var lookupButton = document.querySelector('[data-claim-lookup]');
  var claimStatus = document.querySelector('[data-claim-verify-status]');
  var loadedClaim = document.querySelector('[data-loaded-claim]');
  var searchInput = document.querySelector('[data-claim-search]');
  var resultFilter = document.querySelector('[data-claim-status]');
  var locationFilter = document.querySelector('[data-claim-filter-location]');
  var claimLocation = document.querySelector('[data-claim-location]');
  var codeLocation = document.querySelector('[data-code-location]');
  var loadedPreview = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function money(cents, currency) {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: currency || 'USD'
    }).format(Number(cents || 0) / 100);
  }

  function idempotencyKey() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return 'merchant-redemption-' + window.crypto.randomUUID();
    }
    return 'merchant-redemption-' + Date.now() + '-' + Math.random().toString(16).slice(2);
  }

  function rows(items, renderer, emptyText) {
    return items && items.length
      ? items.map(renderer).join('')
      : '<div class="mg-empty-state">' + esc(emptyText) + '</div>';
  }

  function replaceLocationOptions(select, locations, includeAll) {
    if (!select) return;
    var current = select.value;
    select.innerHTML = includeAll ? '<option value="all">All locations</option>' : '<option value="">Select a location</option>';
    (locations || []).filter(function (location) {
      return includeAll || location.status === 'active';
    }).forEach(function (location) {
      select.add(new Option(location.name, location.public_id));
    });
    if ([].some.call(select.options, function (option) { return option.value === current; })) {
      select.value = current;
    }
  }

  function renderKpis(counts) {
    var root = document.querySelector('[data-claim-kpis]');
    if (!root) return;
    root.innerHTML = [
      ['Attempts', counts.total],
      ['Approved', counts.approved],
      ['Failed', counts.failed],
      ['Redeemed', counts.redeemed],
      ['Rate limited', counts.rate_limited],
      ['Invalid codes', counts.invalid_code]
    ].map(function (item) {
      return '<div class="mg-merchant-kpi"><span>' + esc(item[0]) + '</span><strong>' + Number(item[1] || 0).toLocaleString() + '</strong></div>';
    }).join('');
  }

  function renderAttempts(attempts) {
    if (!claimList) return;
    claimList.innerHTML = rows(attempts, function (attempt) {
      var approved = attempt.result === 'approved';
      var title = attempt.title_snapshot || 'Microgift redemption attempt';
      var value = attempt.redemption_amount_cents != null ? attempt.redemption_amount_cents : attempt.face_value_cents;
      var currency = attempt.redemption_currency || attempt.currency;
      return '<article class="mg-claim-row">' +
        '<div><h3>' + esc(title) + '</h3>' +
        '<p>' + esc(attempt.instance_id || 'Unknown Microgift') + (attempt.pppm_id ? ' · ' + esc(attempt.pppm_id) : '') + '</p>' +
        '<div class="mg-claim-meta"><span>' + esc(attempt.location_name || 'Unassigned location') + '</span>' +
        '<span>' + esc(attempt.reason_code || attempt.result) + '</span>' +
        '<span>' + esc(attempt.attempted_at || '') + '</span></div></div>' +
        '<div><span class="mg-claim-state is-' + esc(approved ? 'redeemed' : 'locked') + '">' + esc(attempt.result) + '</span></div>' +
        '<div><strong>' + money(value, currency) + '</strong><p>' + esc(attempt.redemption_id || attempt.attempt_id) + '</p></div>' +
        '<div class="mg-claim-actions">' + (attempt.redemption_id ? '<span>Confirmed</span>' : '<span>Recorded</span>') + '</div>' +
        '</article>';
    }, 'No canonical redemption attempts match the filters.');
  }

  function renderCodes(codes) {
    var root = document.querySelector('[data-claim-code-list]');
    if (!root) return;
    root.innerHTML = rows(codes, function (code) {
      var usage = Number(code.usage_count || 0) + (code.usage_limit ? '/' + Number(code.usage_limit) : '');
      return '<div class="mg-code-row"><span><strong>' + esc(code.label) + '</strong><br>' +
        '<small>' + esc(code.location_name) + ' · ••••' + esc(code.code_last4) + ' · ' + usage + ' uses</small></span>' +
        '<div class="mg-code-actions">' +
        '<button type="button" data-code-status="' + esc(code.public_id) + '" data-status="' + (code.status === 'active' ? 'inactive' : 'active') + '">' + (code.status === 'active' ? 'Disable' : 'Activate') + '</button>' +
        '<button type="button" data-code-rotate="' + esc(code.public_id) + '">Rotate</button>' +
        '<button type="button" data-code-status="' + esc(code.public_id) + '" data-status="revoked">Revoke</button>' +
        '</div></div>';
    }, 'No location claim codes are configured.');
  }

  function renderExceptions(exceptions) {
    var root = document.querySelector('[data-claim-exception-list]');
    if (!root) return;
    root.innerHTML = rows(exceptions, function (exception) {
      return '<div class="mg-exception-row"><span><strong>' + esc(exception.summary) + '</strong><br>' +
        '<small>' + esc(exception.exception_type) + ' · ' + esc(exception.instance_id || 'No Microgift') + ' · ' + esc(exception.created_at) + '</small></span>' +
        '<span class="mg-claim-state">' + esc(exception.priority) + ' / ' + esc(exception.status) + '</span></div>';
    }, 'No open canonical redemption exceptions.');
  }

  function bindCodeActions() {
    document.querySelectorAll('[data-code-status]').forEach(function (button) {
      button.onclick = async function () {
        await Microgifter.post('/api/merchant/claim-code-action.php', {
          action: 'status',
          claim_code_id: button.dataset.codeStatus,
          status: button.dataset.status
        });
        await loadDashboard();
      };
    });
    document.querySelectorAll('[data-code-rotate]').forEach(function (button) {
      button.onclick = async function () {
        var code = window.prompt('Enter the replacement location claim code. The old code will be revoked.');
        if (!code) return;
        await Microgifter.post('/api/merchant/claim-code-action.php', {
          action: 'rotate',
          claim_code_id: button.dataset.codeRotate,
          code: code
        });
        await loadDashboard();
      };
    });
  }

  async function loadDashboard() {
    if (!claimList) return;
    var query = searchInput ? searchInput.value : '';
    var result = resultFilter ? resultFilter.value : 'all';
    var location = locationFilter ? locationFilter.value : 'all';
    var response = await Microgifter.get(
      '/api/merchant/claims-dashboard.php?q=' + encodeURIComponent(query) +
      '&result=' + encodeURIComponent(result) +
      '&location=' + encodeURIComponent(location)
    );
    var data = response.data || response;
    renderKpis(data.counts || {});
    renderAttempts(data.attempts || []);
    renderCodes(data.claim_codes || []);
    renderExceptions(data.exceptions || []);
    replaceLocationOptions(locationFilter, data.locations || [], true);
    replaceLocationOptions(claimLocation, data.locations || [], false);
    replaceLocationOptions(codeLocation, data.locations || [], false);
    bindCodeActions();
  }

  function renderPreview(data) {
    loadedPreview = data;
    if (!loadedClaim) return;
    var gift = data.microgift || {};
    var location = data.location || {};
    var redemption = data.redemption || null;
    loadedClaim.innerHTML = '<div class="mg-claim-facts">' + [
      ['Microgift ID', gift.instance_id],
      ['PPPM ID', gift.pppm_id || 'Not linked'],
      ['Title', gift.title],
      ['Status', gift.status],
      ['Value', money(gift.value_cents, gift.currency)],
      ['Location', location.name],
      ['Expires', gift.expires_at || 'No expiration'],
      ['Eligible now', gift.redeemable ? 'Yes' : 'No'],
      ['Prior redemption', redemption ? redemption.public_id : 'None']
    ].map(function (fact) {
      return '<div><span>' + esc(fact[0]) + '</span><strong>' + esc(fact[1]) + '</strong></div>';
    }).join('') + '</div>';
  }

  function renderConfirmation(result) {
    if (!loadedClaim) return;
    loadedClaim.innerHTML = '<div class="mg-action-success"><strong>Redemption confirmed</strong>' +
      '<p>' + esc(result.location_name || 'Merchant location') + ' redeemed ' + esc(result.instance_id || '') + ' for ' + money(result.amount_cents, result.currency) + '.</p>' +
      '<div class="mg-claim-facts">' + [
        ['Redemption ID', result.redemption_id],
        ['Attempt ID', result.attempt_id || 'Existing attempt'],
        ['Customer confirmation', result.customer_notification_id || 'Recorded'],
        ['Merchant confirmation', result.merchant_notification_id || 'Recorded'],
        ['Status', result.status],
        ['Replay', result.duplicate ? 'Existing result' : 'New redemption']
      ].map(function (fact) {
        return '<div><span>' + esc(fact[0]) + '</span><strong>' + esc(fact[1]) + '</strong></div>';
      }).join('') + '</div></div>';
  }

  async function lookupMicrogift() {
    if (!claimForm) return null;
    var instanceId = claimForm.elements.instance_id.value.trim();
    var locationId = claimForm.elements.location_id.value;
    if (!instanceId || !locationId) throw new Error('Enter a Microgift ID and select a location.');
    var response = await Microgifter.get(
      '/api/merchant/microgift-claim-lookup.php?instance_id=' + encodeURIComponent(instanceId) +
      '&location_id=' + encodeURIComponent(locationId)
    );
    var data = response.data || response;
    renderPreview(data);
    return data;
  }

  if (lookupButton) {
    lookupButton.addEventListener('click', function () {
      claimStatus.textContent = 'Loading…';
      lookupMicrogift().then(function () {
        claimStatus.textContent = loadedPreview && loadedPreview.microgift && loadedPreview.microgift.redeemable
          ? 'Microgift is eligible for redemption.'
          : 'Microgift is not currently eligible for redemption.';
      }).catch(function (error) {
        claimStatus.textContent = error.message;
      });
    });
  }

  if (claimForm) {
    claimForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      claimStatus.textContent = 'Redeeming…';
      try {
        var preview = await lookupMicrogift();
        if (!preview.microgift || !preview.microgift.redeemable) throw new Error('This Microgift is not eligible for redemption.');
        var payload = Object.fromEntries(new FormData(claimForm).entries());
        payload.idempotency_key = idempotencyKey();
        var response = await Microgifter.post('/api/merchant/microgift-claim.php', payload);
        var result = response.data || response;
        claimStatus.textContent = response.message || 'Microgift redeemed.';
        claimForm.elements.claim_code.value = '';
        renderConfirmation(result);
        await loadDashboard();
      } catch (error) {
        claimStatus.textContent = error.message || 'Unable to redeem this Microgift.';
      }
    });
  }

  var codeForm = document.querySelector('[data-claim-code-form]');
  if (codeForm) {
    codeForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      var status = document.querySelector('[data-claim-code-status]');
      var payload = Object.fromEntries(new FormData(codeForm).entries());
      payload.valid_until = payload.valid_until ? payload.valid_until.replace('T', ' ') : null;
      payload.usage_limit = payload.usage_limit === '' ? null : Number(payload.usage_limit);
      try {
        status.textContent = 'Creating…';
        var response = await Microgifter.post('/api/merchant/claim-codes.php', payload);
        status.textContent = response.message || 'Claim code created.';
        codeForm.reset();
        await loadDashboard();
      } catch (error) {
        status.textContent = error.message;
      }
    });
  }

  document.querySelectorAll('[data-claim-tab]').forEach(function (button) {
    button.addEventListener('click', function () {
      document.querySelectorAll('[data-claim-tab]').forEach(function (candidate) {
        candidate.classList.toggle('is-active', candidate === button);
      });
      document.querySelectorAll('[data-claim-panel]').forEach(function (panel) {
        panel.hidden = panel.dataset.claimPanel !== button.dataset.claimTab;
      });
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      window.clearTimeout(searchInput._claimTimer);
      searchInput._claimTimer = window.setTimeout(function () { loadDashboard().catch(console.error); }, 220);
    });
  }
  if (resultFilter) resultFilter.addEventListener('change', function () { loadDashboard().catch(console.error); });
  if (locationFilter) locationFilter.addEventListener('change', function () { loadDashboard().catch(console.error); });

  loadDashboard().catch(console.error);
});

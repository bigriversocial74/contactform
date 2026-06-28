document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function approvalPanel(form) {
    var panel = form.querySelector('[data-merchant-claim-approval]');
    if (!panel) {
      panel = document.createElement('div');
      panel.className = 'mg-merchant-claim-approval';
      panel.setAttribute('data-merchant-claim-approval', '');
      form.appendChild(panel);
    }
    return panel;
  }

  function renderApproval(form, payload, context) {
    var data = payload && payload.data ? payload.data : payload || {};
    var gift = data.gift || {};
    var panel = approvalPanel(form);
    panel.innerHTML = '<strong>Approved — ready to claim</strong>' +
      '<p>This voucher was verified for the selected merchant location. Confirm to complete the claim.</p>' +
      '<div class="mg-merchant-claim-approval-grid">' +
      '<div><span>Gift</span><b>' + esc(gift.title || context.itemId || 'Microgift') + '</b></div>' +
      '<div><span>Location</span><b>' + esc(data.location_name || context.locationName || 'Selected location') + '</b></div>' +
      '<div><span>Claim code</span><b>' + esc(data.claim_code_last4 ? '•••• ' + data.claim_code_last4 : 'Verified') + '</b></div>' +
      '<div><span>Status</span><b>Approved</b></div>' +
      '</div>';
    form.classList.add('mg-merchant-claim-approved');
  }

  function renderClaimed(form, payload) {
    var data = payload && payload.data ? payload.data : payload || {};
    var gift = data.gift || {};
    var panel = approvalPanel(form);
    panel.innerHTML = '<strong>Claimed successfully</strong>' +
      '<p>' + esc(gift.title || 'This gift') + ' was redeemed' + (data.location_name ? ' at ' + esc(data.location_name) : '') + '.</p>' +
      '<div class="mg-merchant-claim-approval-grid">' +
      '<div><span>Status</span><b>Claimed</b></div>' +
      '<div><span>Redemption</span><b>' + esc(data.redemption_id || data.claim_id || 'Recorded') + '</b></div>' +
      '</div>';
    form.classList.remove('mg-merchant-claim-approved');
    form.classList.add('mg-merchant-claim-complete');
  }

  async function hydrateClaimForm(form) {
    if (!form || form.dataset.merchantClaimReady === 'true') return;
    form.dataset.merchantClaimReady = 'true';

    var note = form.querySelector('.mg-modal-note');
    var codeInput = form.elements.code;
    if (!codeInput) return;

    var locationLabel = document.createElement('label');
    locationLabel.textContent = 'Merchant location';
    var select = document.createElement('select');
    select.name = 'location_id';
    select.required = true;
    select.innerHTML = '<option value="">Loading locations…</option>';
    locationLabel.appendChild(select);
    codeInput.closest('label').before(locationLabel);

    try {
      var response = await Microgifter.get('/api/merchant/locations.php');
      var locations = response.data && Array.isArray(response.data.locations) ? response.data.locations : [];
      select.innerHTML = '<option value="">Choose location</option>' + locations.map(function (location) {
        return '<option value="' + String(location.public_id).replace(/"/g, '&quot;') + '">' + String(location.name).replace(/[&<>]/g, function (character) {
          return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[character];
        }) + '</option>';
      }).join('');
      if (!locations.length && note) note.textContent = 'Create an active merchant location before redeeming items.';
    } catch (error) {
      select.innerHTML = '<option value="">Unable to load locations</option>';
      if (note) note.textContent = error.message || 'Unable to load merchant locations.';
    }
  }

  var observer = new MutationObserver(function () {
    document.querySelectorAll('[data-claim-form]').forEach(hydrateClaimForm);
  });
  observer.observe(document.body, { childList: true, subtree: true });

  document.addEventListener('click', function (event) {
    var reset = event.target.closest('[data-merchant-claim-reset]');
    if (!reset) return;
    var form = reset.closest('[data-claim-form]');
    if (!form) return;
    event.preventDefault();
    form.dataset.merchantClaimStage = '';
    form.classList.remove('mg-merchant-claim-approved');
    var panel = form.querySelector('[data-merchant-claim-approval]');
    if (panel) panel.remove();
    var submit = form.querySelector('[type="submit"]');
    if (submit) {
      submit.disabled = false;
      submit.textContent = 'Verify claim';
    }
  });

  document.addEventListener('submit', async function (event) {
    var form = event.target.closest('[data-claim-form]');
    if (!form || !form.elements.location_id) return;

    event.preventDefault();
    event.stopImmediatePropagation();

    var modal = form.closest('[data-item-modal]');
    var submit = form.querySelector('[type="submit"]');
    var note = form.querySelector('.mg-modal-note');
    var itemId = modal && modal.dataset.giftId;
    var locationId = form.elements.location_id.value;
    var locationName = form.elements.location_id.options[form.elements.location_id.selectedIndex] ? form.elements.location_id.options[form.elements.location_id.selectedIndex].textContent : '';
    var code = form.elements.code.value;
    var isPppm = modal && modal.dataset.itemSource === 'pppm';
    var verifyUrl = isPppm ? '/api/pppm/verify-merchant-claim.php' : '/api/gifts/verify-claim.php';
    var redeemUrl = isPppm ? '/api/pppm/redeem-merchant-claim.php' : '/api/gifts/redeem-claim.php';

    if (!itemId || !locationId || (!code && form.dataset.merchantClaimStage !== 'approved')) {
      if (note) note.textContent = 'Choose a location and enter the merchant claim code.';
      return;
    }

    submit.disabled = true;

    try {
      if (form.dataset.merchantClaimStage === 'approved') {
        submit.textContent = 'Claiming…';
        var redeemed = await Microgifter.post(redeemUrl, {
          id: itemId,
          location_id: locationId
        });
        renderClaimed(form, redeemed);
        if (note) note.textContent = 'Gift claimed successfully.';
        submit.textContent = 'Claimed';
        window.setTimeout(function () { window.location.reload(); }, 900);
        return;
      }

      submit.textContent = 'Verifying…';
      var verified = await Microgifter.post(verifyUrl, {
        id: itemId,
        location_id: locationId,
        code: code
      });
      form.dataset.merchantClaimStage = 'approved';
      renderApproval(form, verified, { itemId: itemId, locationName: locationName });
      if (note) note.textContent = 'Approved. Confirm claim to complete redemption.';
      submit.disabled = false;
      submit.textContent = 'Confirm claim';
    } catch (error) {
      submit.disabled = false;
      submit.textContent = form.dataset.merchantClaimStage === 'approved' ? 'Confirm claim' : 'Verify claim';
      if (note) note.textContent = error.message || 'Unable to redeem this item.';
    }
  }, true);
});
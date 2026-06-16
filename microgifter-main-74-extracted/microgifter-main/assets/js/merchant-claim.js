document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter) return;

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
    var code = form.elements.code.value;
    var isPppm = modal && modal.dataset.itemSource === 'pppm';
    var verifyUrl = isPppm ? '/api/pppm/verify-merchant-claim.php' : '/api/gifts/verify-claim.php';
    var redeemUrl = isPppm ? '/api/pppm/redeem-merchant-claim.php' : '/api/gifts/redeem-claim.php';

    if (!itemId || !locationId || !code) {
      if (note) note.textContent = 'Choose a location and enter the merchant claim code.';
      return;
    }

    submit.disabled = true;
    submit.textContent = 'Verifying…';

    try {
      await Microgifter.post(verifyUrl, {
        id: itemId,
        location_id: locationId,
        code: code
      });
      submit.textContent = 'Redeeming…';
      await Microgifter.post(redeemUrl, {
        id: itemId,
        location_id: locationId
      });
      if (note) note.textContent = 'Item redeemed successfully.';
      submit.textContent = 'Redeemed';
      window.setTimeout(function () { window.location.reload(); }, 700);
    } catch (error) {
      submit.disabled = false;
      submit.textContent = 'Verify and redeem';
      if (note) note.textContent = error.message || 'Unable to redeem this item.';
    }
  }, true);
});
window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var modal = null;
  var stream = null;
  var detector = null;
  var scanLoop = 0;
  var scanBusy = false;
  var lastScanValue = '';
  var lastScanAt = 0;
  var pendingConfirmation = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[char];
    });
  }

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  function openModal() {
    ensureModal();
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-agent-tool-open');
  }

  function closeModal() {
    stopScanner();
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.mg-agent-tool-modal.is-open')) document.body.classList.remove('mg-agent-tool-open');
  }

  function ensureModal() {
    modal = modal || document.querySelector('[data-scanner-modal]');
    if (modal && document.body && modal.parentNode !== document.body) document.body.appendChild(modal);
    return modal;
  }

  function status(message) {
    if (!ensureModal()) return;
    var node = modal.querySelector('[data-scanner-status]');
    if (node) node.textContent = message;
  }

  function result(message, type, link) {
    if (!ensureModal()) return;
    var node = modal.querySelector('[data-scanner-result]');
    if (!node) return;
    if (!message) {
      node.hidden = true;
      node.textContent = '';
      node.className = 'mg-scanner-result';
      return;
    }
    node.hidden = false;
    node.className = 'mg-scanner-result is-' + (type || 'info');
    node.textContent = message;
    if (link && link.href) {
      var a = document.createElement('a');
      a.href = link.href;
      a.className = 'mg-scanner-receipt-link';
      a.textContent = link.label || 'View receipt';
      node.appendChild(document.createElement('br'));
      node.appendChild(a);
    }
  }

  function formatMoney(cents, currency) {
    var value = Number(cents || 0) / 100;
    try { return new Intl.NumberFormat([], { style:'currency', currency:currency || 'USD' }).format(value); }
    catch (error) { return '$' + value.toFixed(2); }
  }

  function renderConfirmationDetails(data) {
    data = data || {};
    var confirmation = data.confirmation || null;
    var gift = confirmation || data.gift || {};
    var customer = confirmation && confirmation.customer ? confirmation.customer : null;
    var location = confirmation && confirmation.location ? confirmation.location : { name:data.location_name || '' };
    var rows = [
      ['Gift', gift.title || data.gift_id || 'Microgift'],
      ['Value', formatMoney(gift.value_cents || (data.gift && data.gift.value_cents), gift.currency || (data.gift && data.gift.currency) || 'USD')],
      ['Customer', customer ? ((customer.name || 'Customer') + (customer.masked_email ? ' · ' + customer.masked_email : '')) : 'Customer present'],
      ['Location', location.name || data.location_name || 'Selected location'],
      ['Claim code', 'Ending ' + (data.claim_code_last4 || gift.claim_code_last4 || '••••')]
    ];
    return '<div class="mg-scanner-confirm-card">' + rows.map(function (row) {
      return '<div class="mg-scanner-confirm-row"><span>' + esc(row[0]) + '</span><strong>' + esc(row[1]) + '</strong></div>';
    }).join('') + '</div>';
  }

  function showConfirm(data) {
    if (!ensureModal()) return;
    pendingConfirmation = data || null;
    var box = modal.querySelector('[data-scanner-confirm]');
    var copy = modal.querySelector('[data-scanner-confirm-copy]');
    var details = modal.querySelector('[data-scanner-confirm-details]');
    if (!box) return;
    if (!pendingConfirmation) {
      box.hidden = true;
      if (details) details.innerHTML = '';
      return;
    }
    box.hidden = false;
    if (copy) {
      var confirmation = pendingConfirmation.confirmation || {};
      copy.textContent = confirmation.copy || ('Gift ' + (pendingConfirmation.gift_id || '') + ' is verified for ' + (pendingConfirmation.location_name || 'this location') + '. Confirm to permanently redeem it.');
    }
    if (details) details.innerHTML = renderConfirmationDetails(pendingConfirmation);
  }

  function locationHasClaimCode(value) {
    return value === true || value === 1 || String(value || '') === '1' || String(value || '').toLowerCase() === 'true';
  }

  function selectedLocation() {
    if (!ensureModal()) return null;
    var select = modal.querySelector('[data-scanner-location]');
    if (!select || !select.value) return null;
    var option = select.options[select.selectedIndex];
    return {
      id: select.value,
      name: option ? option.textContent.replace(/ · claim \*\*\*\*.*$/, '').replace(/ · no active claim code$/, '') : 'Selected location',
      claimCodeLast4: option ? (option.getAttribute('data-claim-last4') || '') : '',
      hasClaimCode: option ? option.getAttribute('data-has-claim-code') === '1' : false
    };
  }

  function updateScanButton() {
    if (!ensureModal()) return;
    var button = modal.querySelector('[data-scanner-start]');
    var location = selectedLocation();
    if (button) button.disabled = !(location && location.hasClaimCode);
  }

  function updateLocationNote() {
    if (!ensureModal()) return;
    var note = modal.querySelector('[data-scanner-location-note]');
    var location = selectedLocation();
    if (!note) return;
    if (!location) {
      note.textContent = 'Select a merchant location with an active claim code before scanning PPPM vouchers.';
      note.className = 'mg-scanner-location-note is-warning';
      status('Scanner blocked until a valid merchant location is selected.');
    } else if (location.hasClaimCode) {
      note.textContent = 'Active claim code assigned to this location. Ending ' + (location.claimCodeLast4 || '••••') + '.';
      note.className = 'mg-scanner-location-note is-ready';
      status('Ready. Camera starts after permission is approved.');
    } else {
      note.textContent = 'This location does not have an active claim code. Add one under Locations before scanning PPPM vouchers.';
      note.className = 'mg-scanner-location-note is-warning';
      status('Scanner blocked for this location.');
    }
    updateScanButton();
  }

  async function loadLocations() {
    if (!ensureModal()) return 0;
    var select = modal.querySelector('[data-scanner-location]');
    if (!select) return 0;
    select.innerHTML = '<option value="">Loading scanner locations…</option>';
    showConfirm(null);
    result('', 'info');
    try {
      var response = await window.Microgifter.get('/api/merchant/locations.php');
      var data = payload(response) || {};
      var locations = data && Array.isArray(data.locations) ? data.locations : (data.data && Array.isArray(data.data.locations) ? data.data.locations : []);
      select.innerHTML = '<option value="">Choose scanner location</option>';
      var readyCount = 0;
      locations.forEach(function (location) {
        if (location.status && location.status !== 'active') return;
        var ready = locationHasClaimCode(location.has_active_claim_code);
        var option = document.createElement('option');
        option.value = location.public_id || '';
        option.textContent = (location.name || 'Merchant location') + (ready && location.claim_code_last4 ? ' · claim ****' + location.claim_code_last4 : ' · no active claim code');
        option.setAttribute('data-claim-last4', location.claim_code_last4 || '');
        option.setAttribute('data-has-claim-code', ready ? '1' : '0');
        if (!ready) option.disabled = true;
        if (ready) readyCount++;
        select.appendChild(option);
      });
      var firstReady = Array.prototype.slice.call(select.options).find(function (option) { return option.value && option.getAttribute('data-has-claim-code') === '1'; });
      if (firstReady) select.value = firstReady.value;
      if (!locations.length) {
        select.innerHTML = '<option value="">No merchant locations set</option>';
        result('Scanner unavailable. Add a merchant location before scanning PPPM vouchers.', 'error');
      } else if (!readyCount) {
        result('Scanner unavailable. Add an active claim code to a merchant location before scanning PPPM vouchers.', 'error');
      }
      updateLocationNote();
      return readyCount;
    } catch (error) {
      select.innerHTML = '<option value="">Unable to load locations</option>';
      updateLocationNote();
      result(error.message || 'Unable to load merchant locations.', 'error');
      return 0;
    }
  }

  function stopScanner() {
    if (scanLoop) {
      cancelAnimationFrame(scanLoop);
      scanLoop = 0;
    }
    if (stream) {
      stream.getTracks().forEach(function (track) { track.stop(); });
      stream = null;
    }
    if (modal) {
      var video = modal.querySelector('[data-scanner-video]');
      if (video) video.srcObject = null;
    }
  }

  function extractScanIdentifier(raw) {
    var value = String(raw || '').trim();
    if (!value) return '';
    try {
      var url = new URL(value, window.location.origin);
      var token = url.searchParams.get('t') || url.searchParams.get('token') || url.searchParams.get('voucher_token');
      if (token) return value;
      var keys = ['gift', 'gift_id', 'id', 'item', 'action_item', 'action_item_id', 'voucher', 'voucher_id', 'g', 'claim', 'code'];
      for (var i = 0; i < keys.length; i++) {
        var candidate = url.searchParams.get(keys[i]);
        if (candidate && /GFT-[A-Z0-9-]+/i.test(candidate)) return candidate.match(/GFT-[A-Z0-9-]+/i)[0].toUpperCase();
      }
    } catch (error) {}
    var match = value.match(/GFT-[A-Z0-9-]+/i);
    return match ? match[0].toUpperCase() : value;
  }

  async function submitClaim(scanValue, confirmed) {
    if (!ensureModal() || scanBusy) return;
    var location = selectedLocation();
    var api = modal.getAttribute('data-scanner-api') || '/api/merchant/scanner-claim-trust.php';
    scanValue = extractScanIdentifier(scanValue || '');
    showConfirm(null);
    if (!location || !location.hasClaimCode) {
      result('Scanner unavailable. Select a merchant location with an active claim code first.', 'error');
      return;
    }
    if (!scanValue) {
      result('No PPPM voucher QR code detected yet.', 'warning');
      return;
    }
    scanBusy = true;
    result('Processing PPPM voucher…', 'info');
    try {
      var response = await window.Microgifter.post(api, {
        action: 'redeem',
        scan: scanValue,
        location_id: location.id,
        require_confirmation: true,
        confirmed: !!confirmed
      });
      var data = payload(response) || {};
      if (data.needs_confirmation) {
        showConfirm(data);
        result(response.message || 'Voucher verified. Confirm redemption before claiming.', 'warning');
      } else if (data.redeemed) {
        result(response.message || 'Voucher redeemed.', 'success', data.receipt_url ? { href:data.receipt_url, label:'View redemption receipt' } : null);
        status('Voucher claimed successfully.');
      } else if (data.verified) {
        showConfirm(data);
        result(response.message || 'Voucher verified for this location.', 'success');
        status('Voucher verified.');
      } else {
        result(response.message || 'Scan processed.', 'success');
      }
    } catch (error) {
      result(error.message || 'Unable to process scanner claim.', 'error');
      status('Scanner needs attention.');
    } finally {
      scanBusy = false;
    }
  }

  async function handleScan(rawValue) {
    if (!rawValue) return;
    var value = extractScanIdentifier(rawValue);
    var now = Date.now();
    if (value === lastScanValue && now - lastScanAt < 3500) return;
    lastScanValue = value;
    lastScanAt = now;
    var input = modal.querySelector('[data-scanner-scan-value]');
    if (input) input.value = value;
    status('Scan detected. Checking voucher…');
    await submitClaim(value, false);
  }

  async function detectLoop(video) {
    if (!modal || !video || !detector || !stream) return;
    try {
      if (video.readyState >= 2) {
        var codes = await detector.detect(video);
        if (codes && codes.length) {
          var raw = codes[0].rawValue || codes[0].rawData || '';
          if (raw) await handleScan(raw);
        }
      }
    } catch (error) {}
    if (stream) scanLoop = requestAnimationFrame(function () { detectLoop(video); });
  }

  async function startScanner() {
    if (!ensureModal()) return;
    var location = selectedLocation();
    var video = modal.querySelector('[data-scanner-video]');
    if (!location || !location.hasClaimCode) {
      stopScanner();
      result('Scanner unavailable. Select a merchant location with an active claim code first.', 'error');
      status('Scanner blocked until the merchant has a valid location claim code.');
      updateScanButton();
      return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      result('Camera access is not supported in this browser.', 'error');
      status('Camera access is not supported.');
      return;
    }
    stopScanner();
    result('', 'info');
    status('Requesting front camera permission…');
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal:'user' } },
        audio: false
      });
      if (video) {
        video.srcObject = stream;
        await video.play();
      }
      if ('BarcodeDetector' in window) {
        detector = new BarcodeDetector({ formats:['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e'] });
        status('Front camera active. Point camera at a Microgifter PPPM voucher QR code.');
        detectLoop(video);
      } else {
        detector = null;
        status('Front camera active. Browser scan detection is unavailable.');
        result('This browser opened the camera but does not support QR detection. Use a supported mobile browser.', 'warning');
      }
    } catch (error) {
      result('Camera permission was denied or unavailable.', 'error');
      status('Camera permission was denied or unavailable.');
    }
  }

  async function openScanner() {
    openModal();
    var ready = await loadLocations();
    if (ready > 0) startScanner();
  }

  function install() {
    ensureModal();
    if (!modal || modal.dataset.scannerCleanupReady === '1') return;
    modal.dataset.scannerCleanupReady = '1';

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-scanner-trigger]');
      if (!trigger) return;
      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) event.stopImmediatePropagation();
      document.body.classList.remove('mg-mobile-sidebar-open');
      var sidebar = document.querySelector('[data-agent-sidebar]');
      if (sidebar) sidebar.classList.remove('is-mobile-open');
      openScanner();
    }, true);

    modal.addEventListener('click', function (event) {
      if (event.target.closest('[data-scanner-start]')) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        startScanner();
      }
      if (event.target.closest('[data-scanner-close]')) {
        event.preventDefault();
        event.stopPropagation();
        if (event.stopImmediatePropagation) event.stopImmediatePropagation();
        closeModal();
      }
      if (event.target.closest('[data-scanner-confirm-claim]')) {
        event.preventDefault();
        event.stopPropagation();
        submitClaim((modal.querySelector('[data-scanner-scan-value]') || {}).value || '', true);
      }
      if (event.target.closest('[data-scanner-cancel-confirm]')) {
        event.preventDefault();
        event.stopPropagation();
        showConfirm(null);
        result('Redemption canceled. Voucher is verified but not claimed.', 'warning');
      }
    }, true);

    var select = modal.querySelector('[data-scanner-location]');
    if (select) select.addEventListener('change', function () { updateLocationNote(); if (selectedLocation()) startScanner(); });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', install, { once:true });
  else install();
})(window, document);

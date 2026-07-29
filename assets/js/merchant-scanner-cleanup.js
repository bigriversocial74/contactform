window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var modal = null;
  var stream = null;
  var detector = null;
  var scanLoop = 0;
  var scanBusy = false;
  var cameraStarting = false;
  var lastScanValue = '';
  var lastScanAt = 0;
  var pendingConfirmation = null;
  var settings = {
    require_confirmation: 1,
    lock_scanner_to_location: 0,
    allow_manual_entry: 1,
    max_failed_scans_per_hour: 8,
    require_manager_review_high_risk: 1,
    high_risk_threshold: 65
  };

  window.MicrogifterMerchantScannerRuntime = 'cleanup-v3-signed-token-preservation';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[char];
    });
  }

  function payload(response) {
    return response && response.data ? response.data : response;
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
      var anchor = document.createElement('a');
      anchor.href = link.href;
      anchor.className = 'mg-scanner-receipt-link';
      anchor.textContent = link.label || 'View receipt';
      node.appendChild(document.createElement('br'));
      node.appendChild(anchor);
    }
  }

  function openModal() {
    ensureModal();
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-agent-tool-open');
  }

  function stopScanner() {
    if (scanLoop) cancelAnimationFrame(scanLoop);
    scanLoop = 0;
    cameraStarting = false;
    if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
    stream = null;
    detector = null;
    if (modal) {
      var video = modal.querySelector('[data-scanner-video]');
      if (video) video.srcObject = null;
    }
  }

  function closeModal() {
    stopScanner();
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.mg-agent-tool-modal.is-open')) document.body.classList.remove('mg-agent-tool-open');
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

  function locationHasClaimCode(value) {
    return value === true || value === 1 || String(value || '') === '1' || String(value || '').toLowerCase() === 'true';
  }

  function renderSettings() {
    if (!ensureModal()) return;
    var host = modal.querySelector('[data-scanner-active-settings]');
    if (!host) {
      host = document.createElement('div');
      host.className = 'mg-scanner-active-settings';
      host.setAttribute('data-scanner-active-settings', '');
      var note = modal.querySelector('[data-scanner-location-note]');
      if (note) note.insertAdjacentElement('afterend', host);
    }
    host.innerHTML = '<strong>Active scanner settings</strong>' +
      '<span>' + (settings.require_confirmation ? 'Final confirmation required' : 'Immediate redemption allowed') + '</span>' +
      '<span>' + (settings.lock_scanner_to_location ? 'Scanner locked to location' : 'Location can be selected') + '</span>' +
      '<span>' + (settings.allow_manual_entry ? 'Manual entry allowed' : 'Camera scans only') + '</span>' +
      '<span>Issue limit: ' + esc(settings.max_failed_scans_per_hour || 8) + '/hour</span>';
    var select = modal.querySelector('[data-scanner-location]');
    if (select) select.disabled = !!settings.lock_scanner_to_location && !!select.value;
  }

  async function loadSettings() {
    try {
      var response = await window.Microgifter.get('/api/merchant/scanner-settings.php');
      var data = payload(response) || {};
      settings = Object.assign(settings, data.settings || data);
    } catch (error) {
      result('Scanner settings could not be loaded. Safe confirmation defaults remain active.', 'warning');
    }
    renderSettings();
  }

  function updateLocationNote() {
    if (!ensureModal()) return;
    var note = modal.querySelector('[data-scanner-location-note]');
    var button = modal.querySelector('[data-scanner-start]');
    var location = selectedLocation();
    if (!note) return;
    if (!location) {
      note.textContent = 'Select a merchant location with an active claim code before scanning.';
      note.className = 'mg-scanner-location-note is-warning';
      status('Scanner blocked until a valid merchant location is selected.');
    } else if (location.hasClaimCode) {
      note.textContent = 'Active claim code assigned. Ending ' + (location.claimCodeLast4 || '••••') + '.';
      note.className = 'mg-scanner-location-note is-ready';
      status('Ready. Front camera starts after permission is approved.');
    } else {
      note.textContent = 'This location does not have an active claim code.';
      note.className = 'mg-scanner-location-note is-warning';
      status('Scanner blocked for this location.');
    }
    if (button) button.disabled = !(location && location.hasClaimCode);
  }

  async function loadLocations() {
    if (!ensureModal()) return 0;
    var select = modal.querySelector('[data-scanner-location]');
    if (!select) return 0;
    select.disabled = false;
    select.innerHTML = '<option value="">Loading scanner locations…</option>';
    try {
      var response = await window.Microgifter.get('/api/merchant/locations.php');
      var data = payload(response) || {};
      var locations = Array.isArray(data.locations) ? data.locations : [];
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
      var firstReady = Array.prototype.slice.call(select.options).find(function (option) {
        return option.value && option.getAttribute('data-has-claim-code') === '1';
      });
      if (firstReady) select.value = firstReady.value;
      if (!readyCount) result('Scanner unavailable. Add an active claim code to a merchant location.', 'error');
      updateLocationNote();
      renderSettings();
      return readyCount;
    } catch (error) {
      select.innerHTML = '<option value="">Unable to load locations</option>';
      result(error.message || 'Unable to load merchant locations.', 'error');
      updateLocationNote();
      return 0;
    }
  }

  function isSignedVoucherPayload(value) {
    return /^MGFT-(?:WALLET-)?CLAIM-TOKEN\|(?:mgwv1_|mgv1_)/i.test(value) ||
      /^(?:mgwv1_|mgv1_)[0-9a-f-]{36}_[a-f0-9]{32}$/i.test(value);
  }

  function extractScanIdentifier(raw) {
    var value = String(raw || '').trim();
    if (!value) return '';

    // Signed QR payloads must remain byte-for-byte intact. The previous generic
    // GFT matcher truncated MGFT-CLAIM-TOKEN|... to GFT-CLAIM-TOKEN and deleted
    // the token before it reached the merchant claim API.
    if (isSignedVoucherPayload(value)) return value;
    if (/^MGFT-WALLET-CLAIM\|wallet-[0-9a-f-]{36}$/i.test(value)) return value;
    if (/^MGFT-CLAIM\|/i.test(value)) return value;

    try {
      var url = new URL(value, window.location.origin);
      var token = url.searchParams.get('wt') || url.searchParams.get('wallet_token') || url.searchParams.get('wallet_voucher_token') ||
        url.searchParams.get('t') || url.searchParams.get('token') || url.searchParams.get('voucher_token');
      if (token) return value;
      var keys = ['gift','gift_id','id','item','action_item','action_item_id','voucher','voucher_id','wallet','wallet_id','g','claim','code'];
      for (var i = 0; i < keys.length; i++) {
        var candidate = url.searchParams.get(keys[i]);
        if (candidate && /^wallet-[0-9a-f-]{36}$/i.test(candidate)) return candidate.toLowerCase();
        if (candidate && /(?:^|[^A-Z0-9])GFT-[A-Z0-9-]+/i.test(candidate)) {
          return candidate.match(/(?:^|[^A-Z0-9])(GFT-[A-Z0-9-]+)/i)[1].toUpperCase();
        }
      }
    } catch (error) {}

    var wallet = value.match(/wallet-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
    if (wallet) return wallet[0].toLowerCase();
    var match = value.match(/(?:^|[^A-Z0-9])(GFT-[A-Z0-9-]+)/i);
    return match ? match[1].toUpperCase() : value;
  }

  function showConfirm(data) {
    if (!ensureModal()) return;
    pendingConfirmation = data || null;
    var box = modal.querySelector('[data-scanner-confirm]');
    var copy = modal.querySelector('[data-scanner-confirm-copy]');
    var details = modal.querySelector('[data-scanner-confirm-details]');
    if (!box) return;
    box.hidden = !pendingConfirmation;
    if (!pendingConfirmation) {
      if (details) details.innerHTML = '';
      return;
    }
    var confirmation = pendingConfirmation.confirmation || {};
    if (copy) copy.textContent = confirmation.copy || 'Voucher verified. Confirm to permanently redeem it.';
    if (details) details.innerHTML = '<div class="mg-scanner-confirm-card"><div class="mg-scanner-confirm-row"><span>Gift</span><strong>' + esc(confirmation.title || pendingConfirmation.gift_id || 'Microgift') + '</strong></div><div class="mg-scanner-confirm-row"><span>Location</span><strong>' + esc((confirmation.location && confirmation.location.name) || pendingConfirmation.location_name || 'Selected location') + '</strong></div></div>';
  }

  async function submitClaim(scanValue, confirmed) {
    if (!ensureModal() || scanBusy) return;
    var location = selectedLocation();
    if (!location || !location.hasClaimCode) {
      result('Select a merchant location with an active claim code first.', 'error');
      return;
    }
    scanValue = extractScanIdentifier(scanValue || '');
    if (!scanValue) return;
    scanBusy = true;
    showConfirm(null);
    result('Processing voucher…', 'info');
    try {
      var response = await window.Microgifter.post(modal.getAttribute('data-scanner-api') || '/api/merchant/scanner-claim-ops.php', {
        action: 'redeem',
        scan: scanValue,
        scan_source: 'camera',
        location_id: location.id,
        require_confirmation: !!settings.require_confirmation,
        confirmed: !!confirmed
      });
      var data = payload(response) || {};
      if (data.needs_confirmation) {
        showConfirm(data);
        result(response.message || 'Voucher verified. Confirm redemption.', 'warning');
      } else if (data.redeemed) {
        result(response.message || 'Voucher redeemed.', 'success', data.receipt_url ? { href:data.receipt_url, label:'View redemption receipt' } : null);
        status('Voucher redeemed successfully.');
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
    var value = extractScanIdentifier(rawValue);
    if (!value) return;
    var now = Date.now();
    if (value === lastScanValue && now - lastScanAt < 3500) return;
    lastScanValue = value;
    lastScanAt = now;
    var input = modal.querySelector('[data-scanner-scan-value]');
    if (input) input.value = value;
    status('QR code detected. Checking voucher…');
    await submitClaim(value, false);
  }

  async function detectLoop(video) {
    if (!video || !detector || !stream) return;
    try {
      if (video.readyState >= 2) {
        var codes = await detector.detect(video);
        if (codes && codes.length) await handleScan(codes[0].rawValue || codes[0].rawData || '');
      }
    } catch (error) {}
    if (stream) scanLoop = requestAnimationFrame(function () { detectLoop(video); });
  }

  async function requestFrontCamera() {
    try {
      return await navigator.mediaDevices.getUserMedia({ video:{ facingMode:{ exact:'user' } }, audio:false });
    } catch (strictError) {
      return navigator.mediaDevices.getUserMedia({ video:{ facingMode:{ ideal:'user' }, width:{ ideal:1280 }, height:{ ideal:720 } }, audio:false });
    }
  }

  async function startScanner() {
    if (!ensureModal() || cameraStarting) return;
    var location = selectedLocation();
    var video = modal.querySelector('[data-scanner-video]');
    if (!location || !location.hasClaimCode) {
      stopScanner();
      result('Select a valid merchant location first.', 'error');
      return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      result('Camera access is not supported in this browser.', 'error');
      return;
    }
    cameraStarting = true;
    stopScanner();
    cameraStarting = true;
    result('', 'info');
    status('Requesting front camera permission…');
    try {
      stream = await requestFrontCamera();
      var track = stream.getVideoTracks()[0];
      var facing = track && track.getSettings ? String(track.getSettings().facingMode || '') : '';
      if (video) {
        video.srcObject = stream;
        await video.play();
      }
      if ('BarcodeDetector' in window) {
        detector = new BarcodeDetector({ formats:['qr_code'] });
        status((facing === 'user' ? 'Front camera active.' : 'Camera active; front-camera preference applied.') + ' Point it at a Microgifter voucher QR code.');
        detectLoop(video);
      } else {
        result('Camera opened, but this browser does not support QR detection.', 'warning');
        status('Use a current mobile Chrome, Edge, or supported browser.');
      }
    } catch (error) {
      var name = error && error.name ? error.name : '';
      if (name === 'NotAllowedError' || name === 'SecurityError') result('Camera permission was denied. Allow camera access in browser settings and try again.', 'error');
      else if (name === 'NotFoundError' || name === 'OverconstrainedError') result('A usable front camera was not found on this device.', 'error');
      else result('Camera could not be started. Close other camera apps and try again.', 'error');
      status('Camera unavailable.');
      stopScanner();
    } finally {
      cameraStarting = false;
    }
  }

  function ensureMobileShortcut() {
    if (!ensureModal() || document.querySelector('[data-scanner-mobile-primary]')) return;
    var workspace = document.querySelector('.mg-app-workspace, [data-agent-control-center] .mg-agent-workspace');
    if (!workspace) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-scanner-mobile-primary';
    button.setAttribute('data-scanner-trigger', '');
    button.setAttribute('data-scanner-mobile-primary', '');
    button.innerHTML = '<span aria-hidden="true">⌗</span><strong>Scan QR Code</strong><small>Open front camera</small>';
    workspace.insertBefore(button, workspace.firstChild);
  }

  async function openScanner() {
    openModal();
    await loadSettings();
    var ready = await loadLocations();
    if (ready > 0) startScanner();
  }

  function install() {
    ensureModal();
    if (!modal || modal.dataset.scannerCleanupReady === '3') return;
    modal.dataset.scannerCleanupReady = '3';
    ensureMobileShortcut();

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
      if (event.target.closest('[data-scanner-start]')) startScanner();
      if (event.target.closest('[data-scanner-close]')) closeModal();
      if (event.target.closest('[data-scanner-confirm-claim]')) submitClaim((modal.querySelector('[data-scanner-scan-value]') || {}).value || '', true);
      if (event.target.closest('[data-scanner-cancel-confirm]')) {
        showConfirm(null);
        result('Redemption canceled. Voucher remains unredeemed.', 'warning');
      }
    }, true);

    var select = modal.querySelector('[data-scanner-location]');
    if (select) select.addEventListener('change', function () {
      updateLocationNote();
      if (selectedLocation()) startScanner();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', install, { once:true });
  else install();
})(window, document);

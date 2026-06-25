document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var MODEL_KEY = 'mg_selected_agent_model_v1';
  var modelTrigger = document.querySelector('[data-agent-model-trigger]');
  var modelModal = document.querySelector('[data-agent-model-modal]');
  var scannerTriggers = Array.prototype.slice.call(document.querySelectorAll('[data-scanner-trigger]'));
  var scannerModal = document.querySelector('[data-scanner-modal]');
  var stream = null;
  var detector = null;
  var scanLoop = 0;
  var scanBusy = false;
  var lastScanValue = '';
  var lastScanAt = 0;
  var pendingConfirmation = null;

  function addBuildShortcut() {
    var tools = document.querySelector('.mg-header-agent-tools');
    var search = tools && tools.querySelector('.mg-header-agent-search');
    if (!tools || !search || tools.querySelector('[data-header-build-link]')) return;

    var link = document.createElement('a');
    link.href = '/build.php';
    link.className = 'mg-header-build-link';
    link.dataset.headerBuildLink = '';
    link.setAttribute('aria-label', 'Open gift builder');
    link.title = 'Open gift builder';
    link.textContent = '+';
    search.insertAdjacentElement('afterend', link);
  }

  addBuildShortcut();

  var models = [
    { id: 'claude', name: 'Claude', provider: 'Anthropic', detail: 'Default model for Microgifter agents.', enabled: true, default: true },
    { id: 'gemma', name: 'Gemma', provider: 'Google', detail: 'Open model option for lightweight workflows.', enabled: true },
    { id: 'kimi', name: 'Kimi', provider: 'Moonshot AI', detail: 'Long-context model option.', enabled: true },
    { id: 'gpt', name: 'GPT', provider: 'OpenAI', detail: 'General-purpose reasoning and automation.', enabled: true },
    { id: 'llama', name: 'Llama', provider: 'Meta', detail: 'Open-weight model option.', enabled: true }
  ];

  function readSelectedModel() {
    try { return localStorage.getItem(MODEL_KEY) || 'claude'; }
    catch (error) { return 'claude'; }
  }

  function writeSelectedModel(value) {
    try { localStorage.setItem(MODEL_KEY, value); } catch (error) {}
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-agent-tool-open');
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.mg-agent-tool-modal.is-open')) document.body.classList.remove('mg-agent-tool-open');
  }

  function renderModels() {
    if (!modelModal) return;
    var host = modelModal.querySelector('[data-model-options]');
    if (!host) return;
    var selected = readSelectedModel();
    host.innerHTML = '';

    models.forEach(function (model) {
      var label = document.createElement('label');
      label.className = 'mg-model-option';
      if (selected === model.id) label.classList.add('is-selected');
      label.innerHTML = '<input type="radio" name="agent_model" value="' + model.id + '"><span class="mg-model-option-mark"></span><span class="mg-model-option-copy"><strong></strong><small></small><em></em></span>';
      label.querySelector('input').checked = selected === model.id;
      label.querySelector('strong').textContent = model.name;
      label.querySelector('small').textContent = model.provider + (model.default ? ' · Default' : '');
      label.querySelector('em').textContent = model.detail;
      host.appendChild(label);
    });
  }

  function scannerStatus(message) {
    if (!scannerModal) return;
    var status = scannerModal.querySelector('[data-scanner-status]');
    if (status) status.textContent = message;
  }

  function scannerResult(message, type) {
    if (!scannerModal) return;
    var result = scannerModal.querySelector('[data-scanner-result]');
    if (!result) return;
    if (!message) {
      result.hidden = true;
      result.textContent = '';
      result.className = 'mg-scanner-result';
      return;
    }
    result.hidden = false;
    result.className = 'mg-scanner-result is-' + (type || 'info');
    result.textContent = message;
  }

  function showScannerConfirm(data) {
    if (!scannerModal) return;
    pendingConfirmation = data || null;
    var confirm = scannerModal.querySelector('[data-scanner-confirm]');
    var copy = scannerModal.querySelector('[data-scanner-confirm-copy]');
    if (!confirm) return;
    if (!pendingConfirmation) {
      confirm.hidden = true;
      return;
    }
    confirm.hidden = false;
    if (copy) {
      copy.textContent = 'Gift ' + (pendingConfirmation.gift_id || '') + ' is verified for ' + (pendingConfirmation.location_name || 'this location') + '. Confirm to permanently redeem it.';
    }
  }

  function locationHasClaimCode(location) {
    return location === true || location === 1 || String(location || '') === '1' || String(location || '').toLowerCase() === 'true';
  }

  function selectedLocation() {
    if (!scannerModal) return null;
    var select = scannerModal.querySelector('[data-scanner-location]');
    if (!select || !select.value) return null;
    var option = select.options[select.selectedIndex];
    return {
      id: select.value,
      name: option ? option.textContent.replace(/ · claim \*\*\*\*.*$/, '').replace(/ · no active claim code$/, '') : 'Selected location',
      claimCodeLast4: option ? (option.getAttribute('data-claim-last4') || '') : '',
      hasClaimCode: option ? option.getAttribute('data-has-claim-code') === '1' : false
    };
  }

  function updateLocationNote() {
    if (!scannerModal) return;
    var note = scannerModal.querySelector('[data-scanner-location-note]');
    var location = selectedLocation();
    if (!note) return;
    if (!location) {
      note.textContent = 'Choose a location with an active claim code.';
      note.className = 'mg-scanner-location-note';
      return;
    }
    if (location.hasClaimCode) {
      note.textContent = 'Active claim code assigned to this location. Ending ' + (location.claimCodeLast4 || '••••') + '.';
      note.className = 'mg-scanner-location-note is-ready';
    } else {
      note.textContent = 'This location does not have an active claim code. Add one under Locations before scanner redemption.';
      note.className = 'mg-scanner-location-note is-warning';
    }
  }

  async function loadScannerLocations() {
    if (!scannerModal || scannerModal.dataset.locationsLoaded === 'true') return;
    var select = scannerModal.querySelector('[data-scanner-location]');
    if (!select) return;
    select.innerHTML = '<option value="">Loading locations…</option>';
    try {
      var data = await Microgifter.get('/api/merchant/locations.php');
      var locations = data && data.data && Array.isArray(data.data.locations) ? data.data.locations : (Array.isArray(data.locations) ? data.locations : []);
      select.innerHTML = '<option value="">Choose scanner location</option>';
      locations.forEach(function (location) {
        if (location.status && location.status !== 'active') return;
        var ready = locationHasClaimCode(location.has_active_claim_code);
        var option = document.createElement('option');
        option.value = location.public_id || '';
        option.textContent = (location.name || 'Merchant location') + (ready && location.claim_code_last4 ? ' · claim ****' + location.claim_code_last4 : ' · no active claim code');
        option.setAttribute('data-claim-last4', location.claim_code_last4 || '');
        option.setAttribute('data-has-claim-code', ready ? '1' : '0');
        if (!ready) option.disabled = true;
        select.appendChild(option);
      });
      var firstReady = Array.prototype.slice.call(select.options).find(function (option) { return option.value && option.getAttribute('data-has-claim-code') === '1'; });
      if (firstReady) select.value = firstReady.value;
      scannerModal.dataset.locationsLoaded = 'true';
      updateLocationNote();
    } catch (error) {
      select.innerHTML = '<option value="">Unable to load locations</option>';
      scannerResult(error.message || 'Unable to load merchant locations.', 'error');
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
    if (scannerModal) {
      var video = scannerModal.querySelector('[data-scanner-video]');
      if (video) video.srcObject = null;
      scannerStatus('Camera is off.');
    }
  }

  function extractScanIdentifier(raw) {
    var value = String(raw || '').trim();
    if (!value) return '';
    try {
      var url = new URL(value, window.location.origin);
      var keys = ['gift', 'gift_id', 'id', 'item', 'g', 'claim', 'code'];
      for (var i = 0; i < keys.length; i++) {
        var candidate = url.searchParams.get(keys[i]);
        if (candidate && /GFT-[A-Z0-9-]+/i.test(candidate)) return candidate.match(/GFT-[A-Z0-9-]+/i)[0].toUpperCase();
      }
    } catch (error) {}
    var match = value.match(/GFT-[A-Z0-9-]+/i);
    return match ? match[0].toUpperCase() : value;
  }

  async function submitScannerClaim(action, confirmed) {
    if (!scannerModal || scanBusy) return;
    var valueInput = scannerModal.querySelector('[data-scanner-scan-value]');
    var auto = scannerModal.querySelector('[data-scanner-auto-claim]');
    var twoStep = scannerModal.querySelector('[data-scanner-two-step]');
    var api = scannerModal.getAttribute('data-scanner-api') || '/api/merchant/scanner-claim.php';
    var location = selectedLocation();
    var scanValue = valueInput ? extractScanIdentifier(valueInput.value) : '';

    showScannerConfirm(null);
    if (!location) {
      scannerResult('Choose a merchant location before scanning.', 'error');
      return;
    }
    if (!location.hasClaimCode) {
      scannerResult('Selected location does not have an active claim code.', 'error');
      return;
    }
    if (!scanValue) {
      scannerResult('Scan a Microgifter QR code or enter a gift ID.', 'error');
      return;
    }

    scanBusy = true;
    scannerResult(action === 'verify' ? 'Verifying scanned gift…' : 'Processing voucher claim…', 'info');
    try {
      var response = await Microgifter.post(api, {
        action: action || (auto && auto.checked ? 'redeem' : 'verify'),
        scan: scanValue,
        location_id: location.id,
        require_confirmation: !!(twoStep && twoStep.checked),
        confirmed: !!confirmed
      });
      var payload = response && response.data ? response.data : response;
      if (payload && payload.needs_confirmation) {
        showScannerConfirm(payload);
        scannerResult(response.message || 'Gift verified. Confirm redemption before claiming voucher.', 'warning');
      } else if (payload && payload.redeemed) {
        scannerResult(response.message || 'Gift redeemed.', 'success');
        scannerStatus('Voucher claimed successfully.');
      } else if (payload && payload.verified) {
        scannerResult(response.message || 'Gift verified for this location.', 'success');
        scannerStatus('Gift verified.');
      } else {
        scannerResult(response.message || 'Scan processed.', 'success');
      }
    } catch (error) {
      scannerResult(error.message || 'Unable to process scanner claim.', 'error');
      scannerStatus('Scanner needs attention.');
    } finally {
      scanBusy = false;
    }
  }

  async function handleScan(rawValue) {
    if (!scannerModal || !rawValue) return;
    var value = extractScanIdentifier(rawValue);
    var now = Date.now();
    if (value === lastScanValue && now - lastScanAt < 3500) return;
    lastScanValue = value;
    lastScanAt = now;

    var input = scannerModal.querySelector('[data-scanner-scan-value]');
    var auto = scannerModal.querySelector('[data-scanner-auto-claim]');
    if (input) input.value = value;
    scannerStatus('Scan detected: ' + value);
    scannerResult('Scan detected. ' + (auto && auto.checked ? 'Checking voucher…' : 'Ready to verify.'), 'info');
    if (auto && auto.checked) await submitScannerClaim('redeem', false);
  }

  async function detectLoop(video) {
    if (!scannerModal || !video || !detector || !stream) return;
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
    if (!scannerModal) return;
    await loadScannerLocations();
    var video = scannerModal.querySelector('[data-scanner-video]');
    var camera = scannerModal.querySelector('[data-scanner-camera]');
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      scannerStatus('Camera access is not supported in this browser. Use hardware/manual entry.');
      return;
    }

    stopScanner();
    scannerStatus('Requesting camera access…');
    scannerResult('', 'info');
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: camera ? camera.value : 'environment' },
        audio: false
      });
      if (video) {
        video.srcObject = stream;
        await video.play();
      }
      if ('BarcodeDetector' in window) {
        try {
          detector = new BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e'] });
          scannerStatus('Scanner is active. Point camera at a Microgifter QR code.');
          detectLoop(video);
        } catch (error) {
          detector = null;
          scannerStatus('Camera is active. Browser scan detection is unavailable; use manual or hardware scanner input.');
        }
      } else {
        scannerStatus('Camera is active. Browser scan detection is unavailable; use manual or hardware scanner input.');
      }
    } catch (error) {
      scannerStatus('Camera permission was denied or unavailable.');
      scannerResult('Camera permission was denied or unavailable. Use manual/hardware scanner entry instead.', 'error');
    }
  }

  if (modelTrigger && modelModal) {
    modelTrigger.addEventListener('click', function () {
      renderModels();
      openModal(modelModal);
    });

    modelModal.addEventListener('change', function (event) {
      if (!event.target.matches('input[name="agent_model"]')) return;
      modelModal.querySelectorAll('.mg-model-option').forEach(function (item) {
        item.classList.toggle('is-selected', item.contains(event.target));
      });
    });

    var save = modelModal.querySelector('[data-model-save]');
    if (save) save.addEventListener('click', function () {
      var checked = modelModal.querySelector('input[name="agent_model"]:checked');
      writeSelectedModel(checked ? checked.value : 'claude');
      var model = models.find(function (item) { return item.id === readSelectedModel(); });
      modelTrigger.textContent = model ? model.name : 'Agent';
      closeModal(modelModal);
    });

    var active = models.find(function (item) { return item.id === readSelectedModel(); });
    if (active) modelTrigger.textContent = active.name;
  }

  if (scannerTriggers.length && scannerModal) {
    scannerTriggers.forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        document.body.classList.remove('mg-mobile-sidebar-open');
        var sidebar = document.querySelector('[data-agent-sidebar]');
        if (sidebar) sidebar.classList.remove('is-mobile-open');
        openModal(scannerModal);
        loadScannerLocations();
      });
    });

    var start = scannerModal.querySelector('[data-scanner-start]');
    var stop = scannerModal.querySelector('[data-scanner-stop]');
    var verify = scannerModal.querySelector('[data-scanner-verify]');
    var locationSelect = scannerModal.querySelector('[data-scanner-location]');
    var scanInput = scannerModal.querySelector('[data-scanner-scan-value]');
    var confirmClaim = scannerModal.querySelector('[data-scanner-confirm-claim]');
    var cancelConfirm = scannerModal.querySelector('[data-scanner-cancel-confirm]');
    if (start) start.addEventListener('click', startScanner);
    if (stop) stop.addEventListener('click', stopScanner);
    if (verify) verify.addEventListener('click', function () { submitScannerClaim('verify', false); });
    if (locationSelect) locationSelect.addEventListener('change', updateLocationNote);
    if (scanInput) scanInput.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      var auto = scannerModal.querySelector('[data-scanner-auto-claim]');
      submitScannerClaim(auto && auto.checked ? 'redeem' : 'verify', false);
    });
    if (confirmClaim) confirmClaim.addEventListener('click', function () { submitScannerClaim('redeem', true); });
    if (cancelConfirm) cancelConfirm.addEventListener('click', function () { showScannerConfirm(null); scannerResult('Redemption canceled. Gift is verified but not claimed.', 'warning'); });
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-agent-tool-close]')) closeModal(modelModal);
    if (event.target.closest('[data-scanner-close]')) {
      stopScanner();
      closeModal(scannerModal);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (modelModal && modelModal.classList.contains('is-open')) closeModal(modelModal);
    if (scannerModal && scannerModal.classList.contains('is-open')) {
      stopScanner();
      closeModal(scannerModal);
    }
  });
});

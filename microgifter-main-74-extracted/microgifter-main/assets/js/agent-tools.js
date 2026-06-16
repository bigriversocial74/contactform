document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var MODEL_KEY = 'mg_selected_agent_model_v1';
  var modelTrigger = document.querySelector('[data-agent-model-trigger]');
  var modelModal = document.querySelector('[data-agent-model-modal]');
  var scannerTriggers = Array.prototype.slice.call(document.querySelectorAll('[data-scanner-trigger]'));
  var scannerModal = document.querySelector('[data-scanner-modal]');
  var stream = null;

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

  function stopScanner() {
    if (stream) {
      stream.getTracks().forEach(function (track) { track.stop(); });
      stream = null;
    }
    if (scannerModal) {
      var video = scannerModal.querySelector('[data-scanner-video]');
      var status = scannerModal.querySelector('[data-scanner-status]');
      if (video) video.srcObject = null;
      if (status) status.textContent = 'Camera is off.';
    }
  }

  async function startScanner() {
    if (!scannerModal) return;
    var video = scannerModal.querySelector('[data-scanner-video]');
    var status = scannerModal.querySelector('[data-scanner-status]');
    var camera = scannerModal.querySelector('[data-scanner-camera]');
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      if (status) status.textContent = 'Camera access is not supported in this browser.';
      return;
    }

    stopScanner();
    if (status) status.textContent = 'Requesting camera access…';
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: camera ? camera.value : 'environment' },
        audio: false
      });
      if (video) {
        video.srcObject = stream;
        await video.play();
      }
      if (status) status.textContent = 'Scanner is active.';
    } catch (error) {
      if (status) status.textContent = 'Camera permission was denied or unavailable.';
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
      });
    });

    var start = scannerModal.querySelector('[data-scanner-start]');
    var stop = scannerModal.querySelector('[data-scanner-stop]');
    if (start) start.addEventListener('click', startScanner);
    if (stop) stop.addEventListener('click', stopScanner);
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
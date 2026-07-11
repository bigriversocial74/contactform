document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-location-redemption-manager]');
  if (!root) return;

  var form = root.querySelector('[data-location-form]');
  var editor = root.querySelector('#location-editor-panel');

  function resetAddLocationForm() {
    if (!form) return;

    form.reset();

    if (form.elements.location_id) form.elements.location_id.value = '';
    if (form.elements.country_code) form.elements.country_code.value = 'US';
    if (form.elements.timezone) form.elements.timezone.value = form.elements.timezone.defaultValue || 'America/Phoenix';
    if (form.elements.status) form.elements.status.value = 'active';
    if (form.elements.claim_code) {
      form.elements.claim_code.value = '';
      form.elements.claim_code.required = true;
      form.elements.claim_code.placeholder = 'PHX-001';
    }

    var help = form.querySelector('[data-location-code-help]');
    if (help) help.textContent = 'Required for a new location. Codes are stored securely and cannot be displayed again.';

    var status = form.querySelector('[data-location-status]');
    if (status) status.textContent = '';
  }

  function scrollToEditor() {
    if (!editor || typeof editor.scrollIntoView !== 'function') return;
    window.requestAnimationFrame(function () {
      editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  root.addEventListener('click', function (event) {
    var addTrigger = event.target.closest('[data-location-open-add]');
    if (addTrigger && root.contains(addTrigger)) {
      resetAddLocationForm();
      scrollToEditor();
      return;
    }

    var editableLocation = event.target.closest('[data-location]');
    if (editableLocation && root.contains(editableLocation)) {
      scrollToEditor();
    }
  });
});

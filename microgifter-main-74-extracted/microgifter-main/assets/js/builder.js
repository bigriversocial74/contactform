window.Microgifter = window.Microgifter || {};

(function () {
  'use strict';

  function bindBuilder(root) {
    var form = root.querySelector('[data-mg-builder-form]');
    var preview = root.querySelector('[data-mg-builder-preview]');
    var status = root.querySelector('[data-builder-status]');
    if (!form || !preview) return;

    function draft() {
      return {
        title: form.elements.title ? form.elements.title.value : '',
        merchant: form.elements.merchant ? form.elements.merchant.value : '',
        offer: form.elements.offer ? form.elements.offer.value : '',
        message: form.elements.message ? form.elements.message.value : ''
      };
    }

    function syncPreview() {
      Microgifter.setText('[data-preview-title]', form.elements.title.value, preview);
      Microgifter.setText('[data-preview-merchant]', form.elements.merchant.value, preview);
      Microgifter.setText('[data-preview-offer]', form.elements.offer.value, preview);
      Microgifter.setText('[data-preview-message]', form.elements.message.value, preview);
      if (Microgifter.onboarding && !Microgifter.isAuthenticated()) {
        Microgifter.onboarding.saveDraft('builder_draft', draft());
      }
      Microgifter.setText(status, Microgifter.isAuthenticated() ? 'Account draft' : 'Guest draft');
    }

    form.addEventListener('input', syncPreview);
    syncPreview();

    var save = root.querySelector('[data-mg-save-draft]');
    if (save) {
      save.addEventListener('click', function () {
        if (!Microgifter.isAuthenticated()) {
          if (Microgifter.onboarding) Microgifter.onboarding.saveDraft('builder_draft', draft());
          window.location.href = '/signup.php';
          return;
        }
        Microgifter.setStatus(status, 'Draft save endpoint will connect in a later stage.', 'success');
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-mg-builder]').forEach(bindBuilder);
  });
})();

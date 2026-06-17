window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var DRAFT_PREFIX = 'microgifter_guest_';

  MG.onboarding = {
    setReturnUrl: function (url) {
      window.sessionStorage.setItem(DRAFT_PREFIX + 'return_url', url || window.location.pathname);
    },
    getReturnUrl: function () {
      return window.sessionStorage.getItem(DRAFT_PREFIX + 'return_url') || '/account.php';
    },
    saveDraft: function (key, value) {
      window.sessionStorage.setItem(DRAFT_PREFIX + key, JSON.stringify(value || {}));
    },
    readDraft: function (key) {
      try {
        return JSON.parse(window.sessionStorage.getItem(DRAFT_PREFIX + key) || '{}');
      } catch (error) {
        return {};
      }
    },
    clearDraft: function (key) {
      window.sessionStorage.removeItem(DRAFT_PREFIX + key);
    }
  };

  function bindAccountRequiredLinks() {
    document.querySelectorAll('[data-requires-account]').forEach(function (node) {
      node.addEventListener('click', function () {
        if (MG.isAuthenticated()) return;
        MG.onboarding.setReturnUrl(node.dataset.returnUrl || window.location.pathname);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.classList.toggle('mg-is-authenticated', MG.isAuthenticated());
    document.documentElement.classList.toggle('mg-is-guest', !MG.isAuthenticated());
    bindAccountRequiredLinks();
  });
})(window, document);

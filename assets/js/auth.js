window.Microgifter = window.Microgifter || {};

(function () {
  'use strict';

  function resolveRedirect(form, data) {
    if (data && data.data && data.data.redirect) return data.data.redirect;
    var explicit = form.getAttribute('data-success-redirect');
    if (explicit) return explicit;
    if (form.dataset.authForm === 'signin' || form.dataset.authForm === 'signup') return '/inbox.php';
    return '';
  }

  function validatePasswordConfirmation(form) {
    var password = form.elements.password;
    var confirmation = form.elements.password_confirmation;
    if (!password || !confirmation) return true;

    var matches = confirmation.value === '' || password.value === confirmation.value;
    confirmation.setCustomValidity(matches ? '' : 'Passwords do not match.');
    return matches;
  }

  function bindPasswordConfirmation(form) {
    var password = form.elements.password;
    var confirmation = form.elements.password_confirmation;
    if (!password || !confirmation) return;

    function validate() {
      validatePasswordConfirmation(form);
    }

    password.addEventListener('input', validate);
    confirmation.addEventListener('input', validate);
  }

  function bindPasswordToggles(root) {
    root.querySelectorAll('[data-password-toggle]').forEach(function (button) {
      if (button.dataset.passwordToggleBound === 'true') return;
      button.dataset.passwordToggleBound = 'true';

      button.addEventListener('click', function () {
        var inputId = button.getAttribute('data-password-target');
        var input = inputId ? document.getElementById(inputId) : null;
        if (!input) return;

        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-pressed', showing ? 'false' : 'true');

        var fieldName = input.name === 'password_confirmation' ? 'confirmed password' : 'password';
        var label = showing ? 'Show ' + fieldName : 'Hide ' + fieldName;
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        input.focus({ preventScroll: true });
      });
    });
  }

  function enhanceForm(form) {
    bindPasswordConfirmation(form);

    form.addEventListener('submit', async function (event) {
      if (!validatePasswordConfirmation(form)) {
        event.preventDefault();
        var confirmation = form.elements.password_confirmation;
        if (confirmation) confirmation.focus();
        form.reportValidity();
        return;
      }
      if (!window.fetch || !Microgifter.submitForm) return;
      event.preventDefault();

      var submit = form.querySelector('[type="submit"]');
      var status = form.querySelector('[data-auth-status]') || document.querySelector('[data-auth-status]');
      Microgifter.setBusy(submit, true);
      Microgifter.setStatus(status, '', '');

      try {
        var data = await Microgifter.submitForm(form);
        var redirect = resolveRedirect(form, data);
        Microgifter.setStatus(status, data.message || 'Success.', 'success');
        if (redirect) {
          window.setTimeout(function () {
            window.location.href = redirect;
          }, 250);
        }
      } catch (error) {
        var errorRedirect = error && error.data && error.data.redirect;
        Microgifter.setStatus(status, error.message || 'Unable to complete request.', 'error');
        if (errorRedirect) {
          window.setTimeout(function () { window.location.href = errorRedirect; }, 500);
        }
      } finally {
        Microgifter.setBusy(submit, false);
      }
    });
  }

  function bindLogout() {
    document.querySelectorAll('[data-auth-logout]').forEach(function (button) {
      if (button.dataset.authLogoutBound === 'true') return;
      button.dataset.authLogoutBound = 'true';
      button.addEventListener('click', async function (event) {
        event.preventDefault();
        Microgifter.setBusy(button, true);
        try {
          var data = await Microgifter.post('/api/auth/logout.php', {});
          window.location.href = (data.data && data.data.redirect) || '/index.php';
        } catch (error) {
          Microgifter.toast(error.message || 'Unable to sign out.', 'error');
        } finally {
          Microgifter.setBusy(button, false);
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-auth-form]').forEach(enhanceForm);
    bindPasswordToggles(document);
    bindLogout();
  });
})();

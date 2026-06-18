window.Microgifter = window.Microgifter || {};

(function () {
  'use strict';

  function resolveRedirect(form, data) {
    var explicit = form.getAttribute('data-success-redirect');
    if (explicit) return explicit;
    if (form.dataset.authForm === 'signin' || form.dataset.authForm === 'signup') return '/inbox.php';
    if (data && data.data && data.data.redirect) return data.data.redirect;
    return '';
  }

  function enhanceForm(form) {
    form.addEventListener('submit', async function (event) {
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
        Microgifter.setStatus(status, error.message || 'Unable to complete request.', 'error');
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
    bindLogout();
  });
})();
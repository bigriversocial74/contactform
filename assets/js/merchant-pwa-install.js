document.addEventListener('DOMContentLoaded', function () {
  var root = document.querySelector('[data-merchant-pwa-install]');
  if (!root) return;
  var deferredPrompt = null;
  var button = root.querySelector('[data-pwa-install-button]');
  var hint = root.querySelector('[data-pwa-install-hint]');
  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    if (button) button.disabled = false;
    if (hint) hint.textContent = 'This merchant app is ready to install on this device.';
  });
  if (button) {
    button.addEventListener('click', function () {
      if (!deferredPrompt) {
        if (hint) hint.textContent = 'Open your browser menu and choose Add to Home Screen or Install App.';
        return;
      }
      button.disabled = true;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(function () {
        deferredPrompt = null;
        button.textContent = 'Install app';
        button.disabled = false;
      });
    });
  }
});

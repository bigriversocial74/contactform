(function (document) {
  'use strict';

  var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-admin-agent-final-readiness]'));
  if (buttons.length < 2) return;

  buttons.slice(1).forEach(function (button) {
    button.addEventListener('click', function () {
      buttons[0].click();
    });
  });
})(document);

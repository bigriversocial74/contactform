document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var page = document.querySelector('[data-user-lists-page]');
  if (!page) return;

  function openListCreate(event) {
    if (event) event.preventDefault();

    var modal = document.querySelector('[data-create-menu]');
    if (!modal) return;

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-create-menu-open');

    document.querySelectorAll('[data-user-list-open-create]').forEach(function (trigger) {
      trigger.setAttribute('aria-expanded', 'true');
    });

    var title = modal.querySelector('[data-create-center-title]');
    var description = modal.querySelector('[data-create-center-description]');
    if (title) title.textContent = 'Create a contact list';
    if (description) description.textContent = 'Organize people for birthdays, recurring gifts, group plans, and agent recommendations.';

    modal.querySelectorAll('[data-create-center-view]').forEach(function (view) {
      var active = view.getAttribute('data-create-center-view') === 'list';
      view.hidden = !active;
      view.classList.toggle('is-active', active);
    });

    modal.querySelectorAll('.mg-create-center-rail-link').forEach(function (link) {
      link.classList.toggle('is-active', link.getAttribute('data-create-tool-key') === 'list');
    });

    var homeButton = modal.querySelector('.mg-create-center-home');
    if (homeButton) homeButton.classList.remove('is-active');

    window.requestAnimationFrame(function () {
      var input = modal.querySelector('[data-create-inline-form="list"] input[name="name"]');
      if (input) input.focus();
    });
  }

  document.querySelectorAll('[data-user-list-open-create]').forEach(function (button) {
    button.addEventListener('click', openListCreate);
  });

  if (new URLSearchParams(window.location.search).get('action') === 'create') {
    window.requestAnimationFrame(function () { openListCreate(); });
  }
});

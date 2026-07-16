document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var modal = document.querySelector('[data-create-menu]');
  if (!modal) return;

  function appendTemplate(templateId, target) {
    var template = document.getElementById(templateId);
    if (!template || !target) return null;
    var fragment = template.content.cloneNode(true);
    var first = fragment.firstElementChild;
    target.appendChild(fragment);
    return first;
  }

  var railLink = modal.querySelector('[data-create-tool-key="list"]');
  var cardLink = modal.querySelector('.mg-create-menu-grid [data-create-tool-key="list"]');
  var listView = modal.querySelector('[data-create-center-view="list"]');

  if (!railLink) {
    railLink = appendTemplate('mg-create-list-rail-template', modal.querySelector('.mg-create-center-rail-nav'));
  }
  if (!cardLink) {
    cardLink = appendTemplate('mg-create-list-card-template', modal.querySelector('.mg-create-menu-grid'));
  }
  if (!listView) {
    listView = appendTemplate('mg-create-list-view-template', modal.querySelector('.mg-create-center-content'));
  }
  if (!listView) return;

  var title = modal.querySelector('[data-create-center-title]');
  var description = modal.querySelector('[data-create-center-description]');
  var content = modal.querySelector('.mg-create-center-content');
  var form = listView.querySelector('[data-create-inline-form="list"]');
  var success = listView.querySelector('[data-create-inline-success="list"]');
  var status = listView.querySelector('[data-create-inline-status="list"]');
  var reset = listView.querySelector('[data-create-inline-reset="list"]');

  function showView(key, focusField) {
    var selected = null;
    modal.querySelectorAll('[data-create-center-view]').forEach(function (view) {
      var active = view.getAttribute('data-create-center-view') === key;
      view.hidden = !active;
      view.classList.toggle('is-active', active);
      view.setAttribute('aria-hidden', active ? 'false' : 'true');
      if (active) selected = view;
    });

    modal.querySelectorAll('.mg-create-center-rail-link').forEach(function (link) {
      link.classList.toggle('is-active', key !== 'home' && link.getAttribute('data-create-tool-key') === key);
    });

    var homeButton = modal.querySelector('.mg-create-center-home');
    if (homeButton) homeButton.classList.toggle('is-active', key === 'home');

    if (title) title.textContent = key === 'list' ? 'Create a contact list' : 'Create something new';
    if (description) {
      description.textContent = key === 'list'
        ? 'Organize people for birthdays, recurring gifts, group plans, and agent recommendations.'
        : 'Choose a tool, complete the form, and submit without leaving the current page.';
    }
    modal.dataset.createCenterCurrentView = key;
    if (content) content.scrollTop = 0;

    if (focusField !== false && selected) {
      window.requestAnimationFrame(function () {
        var input = selected.querySelector('input:not([type="hidden"]),select,textarea');
        if (input) input.focus({ preventScroll: true });
      });
    }
  }

  window.MicrogifterCreateCenterList = {
    show: function (focusField) {
      showView('list', focusField !== false);
    }
  };

  function openList(event) {
    if (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
    modal.dataset.createCenterRequestedView = 'list';
    showView('list', true);
    window.requestAnimationFrame(function () {
      showView('list', true);
      delete modal.dataset.createCenterRequestedView;
    });
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    var option = target && target.closest ? target.closest('[data-create-inline-target="list"]') : null;
    if (!option || !modal.contains(option)) return;
    openList(event);
  }, true);

  if (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var submit = form.querySelector('button[type="submit"]');
      var payload = {};
      new FormData(form).forEach(function (value, key) { payload[key] = value; });
      submit.disabled = true;
      submit.textContent = 'Creating…';
      status.textContent = '';
      status.classList.remove('is-error', 'is-success');
      try {
        var response = await Microgifter.post('/api/user-lists/create.php', payload);
        var data = response && response.data ? response.data : {};
        var list = data.list || {};
        var link = success.querySelector('[data-create-success-link]');
        var message = success.querySelector('[data-create-success-message]');
        if (link) link.href = data.open_url || '/lists.php';
        if (message) message.textContent = (list.name || 'Your list') + ' is ready for contacts.';
        form.hidden = true;
        success.hidden = false;
        if (typeof success.focus === 'function') success.focus();
      } catch (error) {
        status.textContent = error.message || 'Unable to create the list.';
        status.classList.add('is-error');
      } finally {
        submit.disabled = false;
        submit.textContent = 'Create list';
      }
    });
  }

  if (reset) {
    reset.addEventListener('click', function () {
      form.reset();
      success.hidden = true;
      form.hidden = false;
      status.textContent = '';
      showView('list', true);
    });
  }

  if (modal.dataset.createCenterRequestedView === 'list') {
    window.requestAnimationFrame(function () { showView('list', true); });
  }
});

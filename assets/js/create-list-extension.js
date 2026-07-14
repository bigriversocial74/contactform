document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var modal = document.querySelector('[data-create-menu]');
  if (!modal || document.querySelector('[data-create-tool-key="list"]')) return;

  function appendTemplate(templateId, target) {
    var template = document.getElementById(templateId);
    if (!template || !target) return null;
    var fragment = template.content.cloneNode(true);
    var first = fragment.firstElementChild;
    target.appendChild(fragment);
    return first;
  }

  var railLink = appendTemplate('mg-create-list-rail-template', modal.querySelector('.mg-create-center-rail-nav'));
  var cardLink = appendTemplate('mg-create-list-card-template', modal.querySelector('.mg-create-menu-grid'));
  var listView = appendTemplate('mg-create-list-view-template', modal.querySelector('.mg-create-center-content'));
  if (!listView) return;

  var title = modal.querySelector('[data-create-center-title]');
  var description = modal.querySelector('[data-create-center-description]');
  var homeView = modal.querySelector('[data-create-center-view="home"]');
  var form = listView.querySelector('[data-create-inline-form="list"]');
  var success = listView.querySelector('[data-create-inline-success="list"]');
  var status = listView.querySelector('[data-create-inline-status="list"]');
  var reset = listView.querySelector('[data-create-inline-reset="list"]');

  function showView(key) {
    modal.querySelectorAll('[data-create-center-view]').forEach(function (view) {
      var active = view.dataset.createCenterView === key;
      view.hidden = !active;
      view.classList.toggle('is-active', active);
    });
    modal.querySelectorAll('.mg-create-center-rail-link').forEach(function (link) {
      link.classList.toggle('is-active', key !== 'home' && link.dataset.createToolKey === key);
    });
    var homeButton = modal.querySelector('.mg-create-center-home');
    if (homeButton) homeButton.classList.toggle('is-active', key === 'home');
    if (title) title.textContent = key === 'list' ? 'Create a contact list' : 'Create something new';
    if (description) description.textContent = key === 'list'
      ? 'Organize people for birthdays, recurring gifts, group plans, and agent recommendations.'
      : 'Choose a tool, complete the form, and submit without leaving the current page.';
  }

  function openList(event) {
    event.preventDefault();
    showView('list');
    window.requestAnimationFrame(function () {
      var input = listView.querySelector('input[name="name"]');
      if (input) input.focus();
    });
  }

  if (railLink) railLink.addEventListener('click', openList);
  if (cardLink) cardLink.addEventListener('click', openList);

  modal.addEventListener('click', function (event) {
    var home = event.target.closest('[data-create-center-home]');
    if (!home) return;
    event.preventDefault();
    showView('home');
  });

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
      success.focus && success.focus();
    } catch (error) {
      status.textContent = error.message || 'Unable to create the list.';
      status.classList.add('is-error');
    } finally {
      submit.disabled = false;
      submit.textContent = 'Create list';
    }
  });

  if (reset) {
    reset.addEventListener('click', function () {
      form.reset();
      success.hidden = true;
      form.hidden = false;
      status.textContent = '';
      var input = form.querySelector('input[name="name"]');
      if (input) input.focus();
    });
  }

  if (!homeView || homeView.hidden) showView('home');
});

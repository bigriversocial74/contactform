document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = window.MicrogifterPersonalAgent;
  if (!app) return;
  var root = app.root;
  var state = app.state;
  var ui = app.ui;
  var esc = app.esc;
  var dataOf = app.dataOf;
  var setStatus = app.setStatus;
  var setButtonBusy = app.setButtonBusy;
  var renderSettings = app.renderSettings;
  var loadDashboard = app.loadDashboard;
  var selectContext = app.selectContext;
  var formPayload = app.formPayload;
  var openDialog = app.openDialog;
  var closeDialog = app.closeDialog;
  var currentContextPayload = app.currentContextPayload;
  var showContext = app.showContext;

  function appendMessage(message) {
    if (!ui.feed || !message) return;
    var wrapper = document.createElement('div');
    wrapper.className = 'mg-personal-agent-message ' + (message.role === 'user' ? 'is-user' : 'is-assistant');
    wrapper.innerHTML = '<div>' + esc(message.body || '') + '</div>';
    ui.feed.appendChild(wrapper);
    if (message.cards && message.cards.length) {
      var grid = document.createElement('div');
      grid.className = 'mg-personal-agent-card-grid';
      grid.innerHTML = message.cards.map(function (card, index) {
        return '<article class="mg-personal-agent-chat-card"><strong>' + esc(card.title) + '</strong><p>' + esc(card.body) + '</p>'
          + (card.reason ? '<small><b>Why:</b> ' + esc(card.reason) + '</small>' : '')
          + (card.timing ? '<small><b>Timing:</b> ' + esc(card.timing) + '</small>' : '')
          + (card.warning ? '<small><b>Review:</b> ' + esc(card.warning) + '</small>' : '')
          + (card.action && card.action !== 'none' ? '<button type="button" data-agent-card-index="' + index + '">' + esc(card.action_label || 'Review') + '</button>' : '')
          + '</article>';
      }).join('');
      grid._agentCards = message.cards;
      ui.feed.appendChild(grid);
    }
    ui.feed.scrollIntoView({ block: 'end', behavior: 'smooth' });
  }

  async function submitChat(event) {
    event.preventDefault();
    if (!ui.composer) return;
    var input = ui.composer.querySelector('textarea,input');
    var button = ui.composer.querySelector('button[type="submit"]');
    var message = String(input && input.value || '').trim();
    if (!message) return;
    appendMessage({ role: 'user', body: message, cards: [] });
    input.value = '';
    setButtonBusy(button, true, 'Thinking…');
    setStatus('Preparing an approval-first recommendation…');
    try {
      var payload = Object.assign({
        message: message,
        thread_id: state.threadId,
        model_id: state.dashboard && state.dashboard.settings ? state.dashboard.settings.preferred_model_id : ''
      }, currentContextPayload());
      var response = await Microgifter.post('/api/user-agent/chat.php', payload);
      var data = dataOf(response);
      state.threadId = data.thread && data.thread.id || state.threadId;
      appendMessage(data.assistant_message);
      setStatus(data.used_ai ? 'Claude recommendation ready for review.' : 'Safe planning recommendation ready for review.', 'success');
    } catch (error) {
      setStatus(error.message || 'Unable to complete the agent request.', 'error');
    } finally {
      setButtonBusy(button, false);
      if (input) input.focus();
    }
  }

  async function saveCardAction(grid, index, button) {
    var cards = grid && grid._agentCards || [];
    var card = cards[index];
    if (!card) return;
    if (card.action === 'save_draft_plan') {
      setButtonBusy(button, true, 'Saving…');
      try {
        var payload = Object.assign({}, card.review_payload || {}, { action: 'create', source: 'agent', recommendation: card });
        if (!payload.context_type || !payload.context_id) Object.assign(payload, currentContextPayload());
        await Microgifter.post('/api/user-agent/plans.php', payload);
        setStatus('Draft plan saved. Review it before taking action.', 'success');
        await loadDashboard(false);
        button.textContent = 'Draft saved';
        button.disabled = true;
      } catch (error) {
        setStatus(error.message || 'Unable to save the draft plan.', 'error');
        setButtonBusy(button, false);
      }
      return;
    }
    if (card.action === 'create_reminder') {
      var reminderSeed = {
        title: card.review_payload && card.review_payload.title || card.title,
        notes: card.review_payload && card.review_payload.notes || card.body
      };
      var contextType = card.review_payload && card.review_payload.context_type || '';
      var contextId = card.review_payload && card.review_payload.context_id || '';
      if (contextType && contextId) {
        await selectContext(contextType, contextId);
      }
      openDialog('reminder', reminderSeed);
      return;
    }
    if (card.action === 'open_list' && state.context.type === 'list') {
      window.location.href = '/list.php?id=' + encodeURIComponent(state.context.id);
      return;
    }
    if (card.action === 'open_contact') {
      window.location.href = '/agent.php?view=contacts';
    }
  }

  async function submitPlan(form) {
    var button = form.querySelector('button[type="submit"]');
    var status = form.querySelector('[data-personal-agent-plan-status]');
    var payload = Object.assign(formPayload(form), currentContextPayload(), { action: 'create', source: 'manual' });
    setButtonBusy(button, true, 'Saving…');
    if (status) status.textContent = '';
    try {
      await Microgifter.post('/api/user-agent/plans.php', payload);
      if (status) status.textContent = 'Draft plan saved.';
      form.reset();
      closeDialog(form.closest('[data-personal-agent-dialog]'));
      setStatus('Draft plan saved. Nothing has been purchased or sent.', 'success');
      await loadDashboard(false);
    } catch (error) {
      if (status) status.textContent = error.message || 'Unable to save the plan.';
    } finally {
      setButtonBusy(button, false);
    }
  }

  async function submitReminder(form) {
    var button = form.querySelector('button[type="submit"]');
    var status = form.querySelector('[data-personal-agent-reminder-status]');
    var payload = Object.assign(formPayload(form), currentContextPayload(), { action: 'create' });
    if (payload.remind_at) {
      var localDate = new Date(payload.remind_at);
      if (!Number.isNaN(localDate.getTime())) payload.remind_at = localDate.toISOString();
    }
    setButtonBusy(button, true, 'Saving…');
    if (status) status.textContent = '';
    try {
      await Microgifter.post('/api/user-agent/reminders.php', payload);
      if (status) status.textContent = 'Reminder saved.';
      form.reset();
      closeDialog(form.closest('[data-personal-agent-dialog]'));
      setStatus('Reminder scheduled in your Personal Gifting Agent.', 'success');
      await loadDashboard(false);
    } catch (error) {
      if (status) status.textContent = error.message || 'Unable to save the reminder.';
    } finally {
      setButtonBusy(button, false);
    }
  }

  async function submitMemory(form) {
    var button = form.querySelector('button[type="submit"]');
    var status = form.querySelector('[data-personal-agent-memory-status]');
    setButtonBusy(button, true, 'Saving…');
    if (status) status.textContent = '';
    try {
      await Microgifter.post('/api/user-agent/memory.php', Object.assign({ action: 'save' }, formPayload(form)));
      form.reset();
      closeDialog(form.closest('[data-personal-agent-dialog]'));
      setStatus('Agent Memory preference saved.', 'success');
      await loadDashboard(false);
    } catch (error) {
      if (status) status.textContent = error.message || 'Unable to save Agent Memory.';
    } finally {
      setButtonBusy(button, false);
    }
  }

  async function submitDate(form) {
    var button = form.querySelector('button[type="submit"]');
    var status = form.querySelector('[data-personal-agent-date-status]');
    setButtonBusy(button, true, 'Saving…');
    if (status) status.textContent = '';
    try {
      await Microgifter.post('/api/user-agent/dates.php', formPayload(form));
      form.reset();
      closeDialog(form.closest('[data-personal-agent-dialog]'));
      setStatus('Important date saved.', 'success');
      await loadDashboard(false);
    } catch (error) {
      if (status) status.textContent = error.message || 'Unable to save the date.';
    } finally {
      setButtonBusy(button, false);
    }
  }

  async function updatePlanStatus(id, status, button) {
    setButtonBusy(button, true, 'Saving…');
    try {
      await Microgifter.post('/api/user-agent/plans.php', { action: 'status', plan_id: id, status: status });
      setStatus('Plan status updated.', 'success');
      await loadDashboard(false);
    } catch (error) {
      setStatus(error.message || 'Unable to update the plan.', 'error');
    } finally {
      setButtonBusy(button, false);
    }
  }

  async function updateReminderStatus(id, status, button) {
    setButtonBusy(button, true, 'Saving…');
    try {
      await Microgifter.post('/api/user-agent/reminders.php', { action: 'status', reminder_id: id, status: status });
      setStatus('Reminder updated.', 'success');
      await loadDashboard(false);
    } catch (error) {
      setStatus(error.message || 'Unable to update the reminder.', 'error');
    } finally {
      setButtonBusy(button, false);
    }
  }

  async function archiveMemory(id, button) {
    setButtonBusy(button, true, 'Archiving…');
    try {
      await Microgifter.post('/api/user-agent/memory.php', { action: 'archive', memory_id: id });
      setStatus('Agent Memory item archived.', 'success');
      await loadDashboard(false);
    } catch (error) {
      setStatus(error.message || 'Unable to archive Agent Memory.', 'error');
    } finally {
      setButtonBusy(button, false);
    }
  }

  async function saveSettings(form) {
    var button = form.querySelector('button[type="submit"]');
    var status = form.querySelector('[data-personal-agent-settings-status]');
    setButtonBusy(button, true, 'Saving…');
    if (status) status.textContent = '';
    try {
      var response = await Microgifter.post('/api/user-agent/settings.php', formPayload(form));
      var data = dataOf(response);
      if (state.dashboard) {
        state.dashboard.settings = data.settings || state.dashboard.settings;
        state.dashboard.models = data.models || state.dashboard.models;
      }
      renderSettings(data.settings || {}, data.models || []);
      if (status) status.textContent = 'Settings saved.';
      setStatus('Personal Agent settings saved.', 'success');
    } catch (error) {
      if (status) status.textContent = error.message || 'Unable to save settings.';
    } finally {
      setButtonBusy(button, false);
    }
  }

  root.addEventListener('click', function (event) {
    var contextButtonNode = event.target.closest('[data-select-agent-context]');
    if (contextButtonNode) {
      selectContext(contextButtonNode.getAttribute('data-select-agent-context'), contextButtonNode.getAttribute('data-context-id'));
      return;
    }
    var promptButton = event.target.closest('[data-agent-prompt]');
    if (promptButton && ui.composer) {
      var input = ui.composer.querySelector('textarea,input');
      if (input) {
        input.value = promptButton.getAttribute('data-agent-prompt') || '';
        input.focus();
      }
      return;
    }
    var dialogButton = event.target.closest('[data-open-agent-dialog]');
    if (dialogButton) {
      openDialog(dialogButton.getAttribute('data-open-agent-dialog'));
      return;
    }
    var closeButton = event.target.closest('[data-close-agent-dialog]');
    if (closeButton) {
      closeDialog(closeButton.closest('[data-personal-agent-dialog]'));
      return;
    }
    var planStatus = event.target.closest('[data-plan-status]');
    if (planStatus) {
      updatePlanStatus(planStatus.getAttribute('data-plan-status'), planStatus.getAttribute('data-status'), planStatus);
      return;
    }
    var reminderStatus = event.target.closest('[data-reminder-status]');
    if (reminderStatus) {
      updateReminderStatus(reminderStatus.getAttribute('data-reminder-status'), reminderStatus.getAttribute('data-status'), reminderStatus);
      return;
    }
    var memoryArchive = event.target.closest('[data-memory-archive]');
    if (memoryArchive) {
      archiveMemory(memoryArchive.getAttribute('data-memory-archive'), memoryArchive);
      return;
    }
    var cardAction = event.target.closest('[data-agent-card-index]');
    if (cardAction) {
      saveCardAction(cardAction.closest('.mg-personal-agent-card-grid'), Number(cardAction.getAttribute('data-agent-card-index')), cardAction);
      return;
    }
    var opportunityDraft = event.target.closest('[data-opportunity-draft]');
    if (opportunityDraft && state.dashboard) {
      var item = (state.dashboard.opportunities || []).find(function (candidate) { return candidate.id === opportunityDraft.getAttribute('data-opportunity-draft'); });
      if (item) {
        selectContext(item.context.type, item.context.id).then(function () {
          openDialog('plan', {
            title: item.title,
            occasion_label: item.title.split('·').pop().trim(),
            target_date: item.event_date,
            budget_min: item.budget_min,
            budget_max: item.budget_max,
            currency: item.currency
          });
        });
      }
      return;
    }
    if (event.target.closest('[data-personal-agent-context-clear]')) {
      showContext({ type: 'none', id: '', name: '', details: {} });
      if (ui.context) ui.context.classList.remove('is-mobile-open');
      return;
    }
    if (event.target.closest('[data-personal-agent-context-chip]') && ui.context) {
      ui.context.classList.toggle('is-mobile-open');
      return;
    }
    if (event.target.closest('[data-personal-agent-refresh]')) {
      loadDashboard(true);
      return;
    }
    if (event.target.closest('[data-personal-agent-new-thread]')) {
      state.threadId = '';
      if (ui.feed) ui.feed.innerHTML = '';
      setStatus('New personal gifting conversation started.', 'success');
    }
  });

  if (ui.composer) {
    ui.composer.addEventListener('submit', submitChat);
    var text = ui.composer.querySelector('textarea');
    if (text) {
      text.addEventListener('input', function () {
        text.style.height = 'auto';
        text.style.height = Math.min(150, text.scrollHeight) + 'px';
      });
      text.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
          event.preventDefault();
          ui.composer.requestSubmit();
        }
      });
    }
  }

  var planForm = root.querySelector('[data-personal-agent-plan-form]');
  var reminderForm = root.querySelector('[data-personal-agent-reminder-form]');
  var memoryForm = root.querySelector('[data-personal-agent-memory-form]');
  var dateForm = root.querySelector('[data-personal-agent-date-form]');
  if (planForm) planForm.addEventListener('submit', function (event) { event.preventDefault(); submitPlan(planForm); });
  if (reminderForm) reminderForm.addEventListener('submit', function (event) { event.preventDefault(); submitReminder(reminderForm); });
  if (memoryForm) memoryForm.addEventListener('submit', function (event) { event.preventDefault(); submitMemory(memoryForm); });
  if (dateForm) dateForm.addEventListener('submit', function (event) { event.preventDefault(); submitDate(dateForm); });
  if (ui.settingsForm) ui.settingsForm.addEventListener('submit', function (event) { event.preventDefault(); saveSettings(ui.settingsForm); });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var open = root.querySelector('[data-personal-agent-dialog][aria-hidden="false"]');
    if (open) closeDialog(open);
    else if (ui.context) ui.context.classList.remove('is-mobile-open');
  });

  showContext(state.context);
  loadDashboard(false);
});

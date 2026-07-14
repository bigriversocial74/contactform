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
  var empty = app.empty;
  var initials = app.initials;
  var dateParts = app.dateParts;
  var dateTime = app.dateTime;
  var moneyRange = app.moneyRange;

  function renderSummary(summary) {
    if (!ui.summary) return;
    var cards = [
      ['Lists', summary.lists || 0, 'People grouped for gifting'],
      ['Contacts', summary.contacts || 0, 'Private and mutual follows'],
      ['Upcoming', summary.upcoming_dates || 0, 'Within your planning horizon'],
      ['Draft plans', summary.draft_plans || 0, 'Waiting for your review'],
      ['Due reminders', summary.due_reminders || 0, 'Need attention'],
      ['Memory', summary.memory_items || 0, 'Reusable preferences']
    ];
    ui.summary.innerHTML = cards.map(function (card) {
      return '<article class="mg-personal-agent-stat"><span>' + esc(card[0]) + '</span><strong>' + esc(card[1]) + '</strong><small>' + esc(card[2]) + '</small></article>';
    }).join('');
  }

  function contextButton(context, label) {
    return '<button type="button" data-select-agent-context="' + esc(context.type) + '" data-context-id="' + esc(context.id) + '">' + esc(label || 'Select') + '</button>';
  }

  function renderDates(target, dates, birthdaysOnly) {
    if (!target) return;
    var rows = (dates || []).filter(function (item) {
      return !birthdaysOnly || item.type === 'birthday';
    });
    if (!rows.length) {
      target.innerHTML = empty(birthdaysOnly ? 'No birthdays are available in the selected horizon.' : 'No important dates are available yet.');
      return;
    }
    target.innerHTML = rows.map(function (item) {
      var date = dateParts(item.event_date);
      return '<article class="mg-personal-agent-date-row">'
        + '<div class="mg-personal-agent-date-badge"><strong>' + esc(date.day) + '</strong><span>' + esc(date.month) + '</span></div>'
        + '<div class="mg-personal-agent-row-copy"><strong>' + esc(item.contact_name) + ' · ' + esc(item.label) + '</strong><span>' + esc(date.label) + ' · ' + esc(item.days_until) + ' days away</span><small>' + esc(item.relationship || item.type) + '</small></div>'
        + '<div class="mg-personal-agent-row-actions">'
        + contextButton({ type: 'contact', id: item.contact_id }, 'Select')
        + '<button type="button" data-agent-prompt="' + esc('Help me plan a ' + String(item.label || 'gift').toLowerCase() + ' gift for ' + item.contact_name + '.') + '">Ask agent</button>'
        + '</div></article>';
    }).join('');
  }

  function renderOpportunities(items) {
    if (!ui.opportunities) return;
    if (!items || !items.length) {
      ui.opportunities.innerHTML = empty('Add birthdays or important dates to receive proactive suggestions.');
      return;
    }
    ui.opportunities.innerHTML = items.map(function (item) {
      return '<article class="mg-personal-agent-opportunity">'
        + '<header><h3>' + esc(item.title) + '</h3><span>' + esc(item.timing) + '</span></header>'
        + '<p>' + esc(item.body) + '</p>'
        + '<footer>'
        + contextButton(item.context || { type: 'none', id: '' }, 'Select context')
        + '<button type="button" data-agent-prompt="' + esc(item.prompt || '') + '">Ask agent</button>'
        + '<button type="button" data-opportunity-draft="' + esc(item.id) + '">Draft plan</button>'
        + '</footer></article>';
    }).join('');
  }

  function renderContacts(items) {
    if (!ui.contacts) return;
    if (!items || !items.length) {
      ui.contacts.innerHTML = empty('No contacts yet. Create a list or add a private contact.');
      return;
    }
    ui.contacts.innerHTML = items.map(function (item) {
      var avatar = item.avatar_url
        ? '<img src="' + esc(item.avatar_url) + '" alt="">'
        : esc(initials(item.display_name));
      var details = [item.relationship, item.list_names, item.gift_preferences || item.interests].filter(Boolean).join(' · ');
      return '<article class="mg-personal-agent-contact">'
        + '<header><div class="mg-personal-agent-avatar">' + avatar + '</div><div><h3>' + esc(item.display_name) + '</h3><span class="mg-personal-agent-pill">' + esc(item.type === 'linked_user' ? 'Mutual follow' : 'Private contact') + '</span></div></header>'
        + '<p>' + esc(details || 'Add preferences, dates, or a relationship label.') + '</p>'
        + '<p>' + esc(moneyRange(item.budget_min, item.budget_max, (state.dashboard && state.dashboard.settings.default_currency) || 'USD')) + '</p>'
        + '<footer>' + contextButton(item, 'Select')
        + '<button type="button" data-agent-prompt="' + esc('Suggest a thoughtful gift plan for ' + item.display_name + '.') + '">Ask agent</button></footer>'
        + '</article>';
    }).join('');
  }

  function renderPlans(items) {
    if (!ui.plans) return;
    if (!items || !items.length) {
      ui.plans.innerHTML = empty('No gifting plans yet. Start with a contact, list, or upcoming date.');
      return;
    }
    ui.plans.innerHTML = items.map(function (item) {
      var date = dateParts(item.target_date);
      return '<article class="mg-personal-agent-plan-row">'
        + '<div class="mg-personal-agent-date-badge"><strong>' + esc(item.target_date ? date.day : '—') + '</strong><span>' + esc(item.target_date ? date.month : 'Draft') + '</span></div>'
        + '<div class="mg-personal-agent-row-copy"><strong>' + esc(item.title) + '</strong><span>' + esc(item.context && item.context.name || 'No selected recipient') + ' · ' + esc(moneyRange(item.budget_min, item.budget_max, item.currency)) + '</span><small>' + esc(item.occasion_label || item.occasion_type) + (item.target_date ? ' · ' + esc(date.label) : '') + '</small></div>'
        + '<div class="mg-personal-agent-row-actions">'
        + '<span class="mg-personal-agent-pill is-' + esc(item.status) + '">' + esc(item.status) + '</span>'
        + contextButton({ type: 'plan', id: item.id }, 'Inspect')
        + (item.status === 'draft' ? '<button type="button" data-plan-status="' + esc(item.id) + '" data-status="planned">Mark planned</button>' : '')
        + (['draft', 'planned'].indexOf(item.status) !== -1 ? '<button type="button" data-plan-status="' + esc(item.id) + '" data-status="ready">Ready</button>' : '')
        + (item.status === 'ready' ? '<button type="button" data-plan-status="' + esc(item.id) + '" data-status="completed">Complete</button>' : '')
        + '</div></article>';
    }).join('');
  }

  function renderReminders(items) {
    if (!ui.reminders) return;
    if (!items || !items.length) {
      ui.reminders.innerHTML = empty('No scheduled reminders.');
      return;
    }
    ui.reminders.innerHTML = items.map(function (item) {
      var due = new Date(String(item.remind_at).replace(' ', 'T') + 'Z').getTime() <= Date.now();
      return '<article class="mg-personal-agent-reminder-row">'
        + '<div class="mg-personal-agent-date-badge"><strong>' + (due ? '!' : '•') + '</strong><span>' + esc(due ? 'Due' : 'Soon') + '</span></div>'
        + '<div class="mg-personal-agent-row-copy"><strong>' + esc(item.title) + '</strong><span>' + esc(dateTime(item.remind_at)) + '</span><small>' + esc(item.context && item.context.name || item.type) + '</small></div>'
        + '<div class="mg-personal-agent-row-actions">'
        + (item.context && item.context.type !== 'none' ? contextButton(item.context, 'Select') : '')
        + '<button type="button" data-reminder-status="' + esc(item.id) + '" data-status="completed">Complete</button>'
        + '<button type="button" data-reminder-status="' + esc(item.id) + '" data-status="dismissed">Dismiss</button>'
        + '</div></article>';
    }).join('');
  }

  function renderMemory(items) {
    if (!ui.memory) return;
    if (!items || !items.length) {
      ui.memory.innerHTML = empty('No Agent Memory items yet. Save a budget, timing, merchant, or gifting-style preference.');
      return;
    }
    ui.memory.innerHTML = items.map(function (item) {
      var text = item.value && item.value.text ? item.value.text : '';
      return '<article class="mg-personal-agent-memory"><span class="mg-personal-agent-pill">' + esc(item.category) + '</span><h3>' + esc(item.title) + '</h3><p>' + esc(text) + '</p><footer><button type="button" data-memory-archive="' + esc(item.id) + '">Archive</button></footer></article>';
    }).join('');
  }

  function renderGroupLists(items) {
    if (!ui.groupLists) return;
    var lists = (items || []).filter(function (item) { return !item.is_archived; });
    if (!lists.length) {
      ui.groupLists.innerHTML = empty('Create a contact list before starting a group gifting plan.');
      return;
    }
    ui.groupLists.innerHTML = lists.map(function (item) {
      return '<article class="mg-personal-agent-list-card"><span class="mg-personal-agent-pill">' + esc(item.list_type) + '</span><h3>' + esc(item.name) + '</h3><p>' + esc(item.member_count) + ' members · ' + esc(item.description || 'No description') + '</p><footer>'
        + contextButton({ type: 'list', id: item.id }, 'Select list')
        + '<button type="button" data-agent-prompt="' + esc('Create a shared draft gift plan for my ' + item.name + ' list.') + '">Ask agent</button>'
        + '</footer></article>';
    }).join('');
  }

  function renderSettings(settings, models) {
    if (!ui.settingsForm) return;
    ui.settingsModels.innerHTML = '<option value="">Automatic default</option>' + (models || []).map(function (model) {
      return '<option value="' + esc(model.id) + '">' + esc(model.name + ' · ' + model.provider) + '</option>';
    }).join('');
    Object.keys(settings || {}).forEach(function (key) {
      var field = ui.settingsForm.elements.namedItem(key);
      if (!field) return;
      if (field.type === 'checkbox') field.checked = Boolean(settings[key]);
      else field.value = settings[key] == null ? '' : String(settings[key]);
    });
  }

  function populateDateContacts(items) {
    if (!ui.dateContacts) return;
    var contacts = (items || []).filter(function (item) { return item.type === 'contact'; });
    ui.dateContacts.innerHTML = '<option value="">Choose a private contact</option>' + contacts.map(function (item) {
      return '<option value="' + esc(item.id) + '">' + esc(item.display_name) + '</option>';
    }).join('');
  }

  function renderDashboard(data) {
    state.dashboard = data;
    renderSummary(data.summary || {});
    renderDates(ui.upcoming, (data.upcoming_dates || []).slice(0, 8), false);
    renderDates(ui.birthdays, data.upcoming_dates || [], true);
    renderDates(ui.calendar, data.upcoming_dates || [], false);
    renderOpportunities(data.opportunities || []);
    renderContacts(data.contacts || []);
    renderPlans(data.plans || []);
    renderReminders(data.reminders || []);
    renderMemory(data.memory || []);
    renderGroupLists(data.lists || []);
    renderSettings(data.settings || {}, data.models || []);
    populateDateContacts(data.contacts || []);
  }

  async function loadDashboard(announce) {
    if (state.loading) return;
    state.loading = true;
    if (announce) setStatus('Refreshing your personal gifting brief…');
    try {
      var response = await Microgifter.get('/api/user-agent/dashboard.php');
      renderDashboard(dataOf(response));
      setStatus(announce ? 'Personal gifting brief refreshed.' : '', announce ? 'success' : '');
    } catch (error) {
      setStatus(error.message || 'Unable to load the Personal Gifting Agent.', 'error');
    } finally {
      state.loading = false;
    }
  }

  function contextFacts(details) {
    var rows = [];
    Object.keys(details || {}).forEach(function (key) {
      var value = details[key];
      if (value == null || value === '' || key === 'members' || typeof value === 'object') return;
      rows.push([key.replace(/_/g, ' '), value]);
    });
    if (Array.isArray(details && details.members)) {
      rows.push(['Members', details.members.slice(0, 12).map(function (item) { return item.name; }).join(', ')]);
    }
    return rows;
  }

  function showContext(context) {
    state.context = context || { type: 'none', id: '', name: '', details: {} };
    var selected = state.context.type !== 'none' && state.context.id;
    if (ui.contextTitle) ui.contextTitle.textContent = selected ? state.context.name : 'No contact or list selected';
    if (ui.contextChip) {
      ui.contextChip.hidden = !selected;
      ui.contextChip.textContent = selected ? state.context.name : '';
    }
    if (ui.contextBody) {
      if (!selected) {
        ui.contextBody.innerHTML = '<p>Select a contact, list, date, or plan. The agent will use only the safe details shown here.</p>';
      } else {
        var facts = contextFacts(state.context.details || {});
        ui.contextBody.innerHTML = '<p><span class="mg-personal-agent-pill">' + esc(state.context.type) + '</span></p>'
          + (facts.length ? '<div class="mg-personal-agent-context-facts">' + facts.map(function (row) {
            return '<div class="mg-personal-agent-context-fact"><span>' + esc(row[0]) + '</span><strong>' + esc(row[1]) + '</strong></div>';
          }).join('') + '</div>' : '<p>No additional safe details are available.</p>');
      }
    }
  }

  async function selectContext(type, id) {
    if (!type || !id) return;
    setStatus('Loading selected context…');
    try {
      var response = await Microgifter.get('/api/user-agent/context.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id));
      var data = dataOf(response);
      showContext(data.context || { type: 'none', id: '', name: '', details: {} });
      setStatus('Context selected.', 'success');
      if (window.innerWidth <= 1040 && ui.context) ui.context.classList.add('is-mobile-open');
    } catch (error) {
      setStatus(error.message || 'Unable to select this context.', 'error');
    }
  }

  function formPayload(form) {
    var data = new FormData(form);
    var payload = {};
    data.forEach(function (value, key) {
      payload[key] = value;
    });
    form.querySelectorAll('input[type="checkbox"]').forEach(function (field) {
      payload[field.name] = field.checked;
    });
    return payload;
  }

  function openDialog(name, seed) {
    var dialog = root.querySelector('[data-personal-agent-dialog="' + name + '"]');
    if (!dialog) return;
    dialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mg-agent-tool-open');
    var form = dialog.querySelector('form');
    if (form && seed) {
      Object.keys(seed).forEach(function (key) {
        var field = form.elements.namedItem(key);
        if (field && seed[key] != null) field.value = seed[key];
      });
    }
    var first = dialog.querySelector('input,select,textarea,button');
    if (first) window.setTimeout(function () { first.focus(); }, 20);
  }

  function closeDialog(dialog) {
    if (!dialog) return;
    dialog.setAttribute('aria-hidden', 'true');
    if (!root.querySelector('[data-personal-agent-dialog][aria-hidden="false"]')) {
      document.body.classList.remove('mg-agent-tool-open');
    }
  }

  function currentContextPayload() {
    return { context_type: state.context.type || 'none', context_id: state.context.id || '' };
  }

  Object.assign(app, {
    renderSettings: renderSettings,
    loadDashboard: loadDashboard,
    selectContext: selectContext,
    formPayload: formPayload,
    openDialog: openDialog,
    closeDialog: closeDialog,
    currentContextPayload: currentContextPayload,
    showContext: showContext
  });
});

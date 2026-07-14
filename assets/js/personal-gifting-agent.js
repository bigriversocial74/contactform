document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-personal-gifting-agent]');
  if (!root || root.getAttribute('data-schema-ready') !== 'true' || !window.Microgifter) return;

  var state = {
    dashboard: null,
    context: { type: 'none', id: '', name: '', details: {} },
    threadId: '',
    loading: false
  };

  var ui = {
    status: root.querySelector('[data-personal-agent-status]'),
    summary: root.querySelector('[data-personal-agent-summary]'),
    upcoming: root.querySelector('[data-personal-agent-upcoming]'),
    opportunities: root.querySelector('[data-personal-agent-opportunities]'),
    contacts: root.querySelector('[data-personal-agent-contacts]'),
    birthdays: root.querySelector('[data-personal-agent-birthdays]'),
    calendar: root.querySelector('[data-personal-agent-calendar]'),
    plans: root.querySelector('[data-personal-agent-plans]'),
    reminders: root.querySelector('[data-personal-agent-reminders]'),
    memory: root.querySelector('[data-personal-agent-memory]'),
    groupLists: root.querySelector('[data-personal-agent-group-lists]'),
    feed: root.querySelector('[data-personal-agent-feed]'),
    composer: root.querySelector('[data-personal-agent-composer]'),
    context: root.querySelector('[data-personal-agent-context]'),
    contextTitle: root.querySelector('[data-personal-agent-context-title]'),
    contextBody: root.querySelector('[data-personal-agent-context-body]'),
    contextChip: root.querySelector('[data-personal-agent-context-chip]'),
    settingsForm: root.querySelector('[data-personal-agent-settings-form]'),
    settingsModels: root.querySelector('[data-personal-agent-models]'),
    dateContacts: root.querySelector('[data-personal-agent-date-contacts]')
  };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }

  function dataOf(response) {
    return response && response.data ? response.data : (response || {});
  }

  function setStatus(message, type) {
    if (!ui.status) return;
    ui.status.textContent = message || '';
    ui.status.classList.toggle('is-error', type === 'error');
    ui.status.classList.toggle('is-success', type === 'success');
  }

  function empty(message) {
    return '<div class="mg-personal-agent-empty">' + esc(message) + '</div>';
  }

  function initials(name) {
    return String(name || '?').split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0).toUpperCase();
    }).join('') || '?';
  }

  function dateParts(value) {
    if (!value) return { month: '—', day: '—', label: 'No date' };
    var date = new Date(String(value) + (String(value).length === 10 ? 'T00:00:00Z' : ''));
    if (Number.isNaN(date.getTime())) return { month: '—', day: '—', label: String(value) };
    return {
      month: date.toLocaleDateString(undefined, { month: 'short', timeZone: 'UTC' }),
      day: date.toLocaleDateString(undefined, { day: '2-digit', timeZone: 'UTC' }),
      label: date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' })
    };
  }

  function dateTime(value) {
    if (!value) return '—';
    var raw = String(value);
    var normalized = /[zZ]$|[+-]\d{2}:?\d{2}$/.test(raw) ? raw : raw.replace(' ', 'T') + 'Z';
    var date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? raw : date.toLocaleString();
  }

  function moneyRange(min, max, currency) {
    if (min == null && max == null) return 'Budget not set';
    var format = function (value) {
      try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD', maximumFractionDigits: 0 }).format(Number(value));
      } catch (error) {
        return Number(value).toFixed(0) + ' ' + (currency || 'USD');
      }
    };
    if (min != null && max != null) return format(min) + '–' + format(max);
    return min != null ? 'From ' + format(min) : 'Up to ' + format(max);
  }

  function setButtonBusy(button, busy, label) {
    if (!button) return;
    if (busy) {
      button.dataset.originalLabel = button.textContent;
      button.textContent = label || 'Working…';
      button.disabled = true;
    } else {
      button.textContent = button.dataset.originalLabel || button.textContent;
      button.disabled = false;
    }
  }

  window.MicrogifterPersonalAgent = {
    root: root,
    state: state,
    ui: ui,
    esc: esc,
    dataOf: dataOf,
    setStatus: setStatus,
    empty: empty,
    initials: initials,
    dateParts: dateParts,
    dateTime: dateTime,
    moneyRange: moneyRange,
    setButtonBusy: setButtonBusy
  };
});

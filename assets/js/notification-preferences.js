document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-notification-preferences]');
  if (!app || !window.Microgifter) return;
  var list = app.querySelector('[data-preferences-list]');

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' })[character];
    });
  }

  function checked(value) {
    return Number(value) ? ' checked' : '';
  }

  function row(item) {
    var modes = ['immediate','hourly','daily','weekly','off'];
    var modeLabels = {
      immediate: 'Immediately',
      hourly: 'Hourly digest',
      daily: 'Daily digest',
      weekly: 'Weekly digest',
      off: 'Off'
    };
    return '<form class="mg-preference-row" data-preference-form>' +
      '<div class="mg-preference-copy"><strong>' + esc(item.label || item.notification_type) + '</strong><small>' + esc(item.description || '') + '</small></div>' +
      '<label data-label="In app"><input type="checkbox" name="in_app_enabled"' + checked(item.in_app_enabled) + '><span class="mg-sr-only">In app</span></label>' +
      '<label data-label="Email"><input type="checkbox" name="email_enabled"' + checked(item.email_enabled) + '><span class="mg-sr-only">Email</span></label>' +
      '<label data-label="SMS"><input type="checkbox" name="sms_enabled"' + checked(item.sms_enabled) + '><span class="mg-sr-only">SMS</span></label>' +
      '<label data-label="Push"><input type="checkbox" name="push_enabled"' + checked(item.push_enabled) + '><span class="mg-sr-only">Push</span></label>' +
      '<select name="digest_mode" aria-label="Delivery timing">' + modes.map(function (mode) {
        return '<option value="' + mode + '"' + (item.digest_mode === mode ? ' selected' : '') + '>' + modeLabels[mode] + '</option>';
      }).join('') + '</select>' +
      '<input type="hidden" name="notification_type" value="' + esc(item.notification_type) + '">' +
      '<button class="mg-btn mg-btn-soft" type="submit">Save</button>' +
      '<details class="mg-preference-schedule"><summary>Quiet hours and timezone</summary>' +
        '<div class="mg-preference-schedule-grid">' +
          '<label>From<input type="time" name="quiet_hours_start" value="' + esc(String(item.quiet_hours_start || '').slice(0,5)) + '"></label>' +
          '<label>Until<input type="time" name="quiet_hours_end" value="' + esc(String(item.quiet_hours_end || '').slice(0,5)) + '"></label>' +
          '<label>Timezone<input type="text" name="timezone" maxlength="64" value="' + esc(item.timezone || 'UTC') + '" placeholder="America/Phoenix"></label>' +
        '</div>' +
      '</details>' +
    '</form>';
  }

  async function load() {
    var response = await Microgifter.get('/api/communications/preferences.php');
    var items = (response.data || response).preferences || [];
    list.innerHTML = items.map(row).join('') || '<div class="mg-empty-state"><strong>No preference categories found.</strong></div>';
  }

  list.addEventListener('submit', async function (event) {
    var form = event.target.closest('[data-preference-form]');
    if (!form) return;
    event.preventDefault();
    var button = form.querySelector('button[type="submit"]');
    var payload = Object.fromEntries(new FormData(form).entries());
    ['in_app_enabled','email_enabled','sms_enabled','push_enabled'].forEach(function (key) {
      payload[key] = form.elements[key].checked ? 1 : 0;
    });
    button.disabled = true;
    button.textContent = 'Saving…';
    try {
      await Microgifter.post('/api/communications/preferences.php', payload);
      button.textContent = 'Saved';
      window.setTimeout(function () { button.textContent = 'Save'; button.disabled = false; }, 900);
    } catch (error) {
      button.textContent = 'Retry';
      button.disabled = false;
    }
  });

  load().catch(function (error) {
    list.innerHTML = '<div class="mg-empty-state"><strong>Unable to load preferences.</strong><p>' + esc(error.message || 'Try again shortly.') + '</p></div>';
  });
});

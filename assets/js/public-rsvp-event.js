document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('[data-rsvp-event-form]').forEach(function (form) {
    var status = form.querySelector('[data-rsvp-event-status]') || form.querySelector('[data-campaign-status]');
    var submit = form.querySelector('[data-rsvp-submit]');
    var attendance = form.querySelector('[data-rsvp-attendance-toggle]');
    var attendancePanel = form.querySelector('[data-rsvp-attendance-panel]');

    function setStatus(message, type) {
      if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
        Microgifter.setStatus(status, message, type || '');
        return;
      }
      if (status) status.textContent = message || '';
    }

    function syncAttendance() {
      var enabled = !!(attendance && attendance.checked);
      if (attendancePanel) attendancePanel.hidden = !enabled;
      var code = form.elements.entry_attendance_code;
      if (code) code.required = enabled;
      if (submit) submit.textContent = enabled ? 'Confirm attendance and claim reward →' : 'Submit RSVP →';
    }

    if (attendance) attendance.addEventListener('change', syncAttendance);
    syncAttendance();

    form.addEventListener('submit', function () {
      if (submit) {
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.textContent = attendance && attendance.checked ? 'Confirming attendance…' : 'Recording RSVP…';
      }
      setStatus(attendance && attendance.checked ? 'Confirming attendance and checking reward eligibility…' : 'Recording your RSVP…');
      window.setTimeout(function () {
        if (!submit) return;
        submit.disabled = false;
        submit.removeAttribute('aria-busy');
        syncAttendance();
      }, 6000);
    }, true);
  });
});

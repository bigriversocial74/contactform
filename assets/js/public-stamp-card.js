document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('[data-stamp-card-experience]').forEach(function (page) {
    var forms = Array.prototype.slice.call(page.querySelectorAll('[data-stamp-card-form]'));
    var statuses = Array.prototype.slice.call(page.querySelectorAll('[data-stamp-card-status], [data-stamp-card-form] [data-campaign-status]'));
    var buttons = Array.prototype.slice.call(page.querySelectorAll('[data-stamp-card-submit]'));
    var visual = page.querySelector('[data-stamp-card-visual]');
    var slots = Array.prototype.slice.call(page.querySelectorAll('[data-stamp-slot]'));
    var countNode = page.querySelector('[data-stamp-count]');
    var requiredNode = page.querySelector('[data-stamp-required]');
    var remainingNode = page.querySelector('[data-stamp-remaining]');
    var progressCopy = page.querySelector('[data-stamp-progress-copy]');
    var state = page.querySelector('[data-stamp-summary-state]') || page.querySelector('[data-campaign-foundation-cards] article:last-child h3');
    var stage = page.querySelector('[data-stamp-stage]');
    var required = parseInt(stage && stage.getAttribute('data-required-count') || requiredNode && requiredNode.textContent || '5', 10) || 5;

    if (!forms.length || !stage) return;

    function setStatus(message, type) {
      statuses.forEach(function (status) {
        if (window.Microgifter && typeof window.Microgifter.setStatus === 'function') {
          window.Microgifter.setStatus(status, message, type || '');
        } else if (status) {
          status.textContent = message || '';
        }
      });
    }

    function setState(message) {
      if (state) state.textContent = message || '';
    }

    function buttonLabel(button) {
      return button.getAttribute('data-idle-label') || button.textContent || 'Add stamp';
    }

    buttons.forEach(function (button) {
      button.setAttribute('data-idle-label', buttonLabel(button));
    });

    function setButtonsBusy(busy) {
      buttons.forEach(function (button) {
        button.disabled = !!busy;
        if (busy) {
          button.setAttribute('aria-busy', 'true');
          button.textContent = 'Recording stamp…';
        } else {
          button.removeAttribute('aria-busy');
          button.textContent = buttonLabel(button);
        }
      });
    }

    function updateProgress(count, total) {
      count = Math.max(0, parseInt(count || 0, 10) || 0);
      total = Math.max(1, parseInt(total || required, 10) || required);
      var remaining = Math.max(0, total - count);
      var percent = Math.max(0, Math.min(100, Math.round((count / total) * 100)));

      if (countNode) countNode.textContent = String(count);
      if (requiredNode) requiredNode.textContent = String(total);
      if (remainingNode) remainingNode.textContent = remaining === 0 ? 'Reward unlocked' : remaining + ' remaining';
      if (progressCopy) progressCopy.textContent = percent + '% complete';
      if (visual) visual.style.setProperty('--stamp-progress', percent + '%');

      slots.forEach(function (slot) {
        var index = parseInt(slot.getAttribute('data-stamp-slot') || '0', 10) || 0;
        slot.classList.toggle('is-stamped', index <= count);
      });
    }

    forms.forEach(function (form) {
      form.addEventListener('submit', function () {
        setButtonsBusy(true);
        setState('Recording loyalty progress…');
        setStatus('Verifying this visit and recording the stamp…');
      }, true);

      form.addEventListener('microgifter:campaign-submitted', function (event) {
        var payload = event.detail && event.detail.payload || {};
        var entry = payload.entry || {};
        var count = payload.stamp_count || entry.stamp_count || (payload.wallet_item_id ? required : 0);
        var total = payload.required_count || entry.required_count || required;
        var remaining = payload.stamps_remaining;
        if (remaining == null && entry.stamps_remaining != null) remaining = entry.stamps_remaining;

        updateProgress(count, total);
        setButtonsBusy(false);

        if (payload.reward_unlocked || payload.wallet_item_id || entry.stamp_result === 'unlocked') {
          setState('Reward sent to Inbox');
          setStatus('Stamp card complete. Reward sent to Microgifter Inbox.', 'success');
        } else if (remaining != null) {
          setState('Stamp recorded');
          setStatus('Stamp recorded. ' + remaining + ' more to unlock your reward.', 'success');
        } else {
          setState('Stamp recorded');
          setStatus('Stamp recorded.', 'success');
        }
      });

      form.addEventListener('microgifter:campaign-submit-failed', function () {
        setButtonsBusy(false);
        setState('Ready for the next stamp');
      });
    });

    updateProgress(0, required);
  });
});
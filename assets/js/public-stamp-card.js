document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('[data-stamp-card-form]').forEach(function (form) {
    var page = form.closest('[data-stamp-card-experience]') || document;
    var status = form.querySelector('[data-stamp-card-status]') || form.querySelector('[data-campaign-status]');
    var button = form.querySelector('[data-stamp-card-submit]');
    var visual = page.querySelector('[data-stamp-card-visual]');
    var slots = Array.prototype.slice.call(page.querySelectorAll('[data-stamp-slot]'));
    var countNode = page.querySelector('[data-stamp-count]');
    var requiredNode = page.querySelector('[data-stamp-required]');
    var remainingNode = page.querySelector('[data-stamp-remaining]');
    var progressCopy = page.querySelector('[data-stamp-progress-copy]');
    var stage = page.querySelector('[data-stamp-stage]');
    var required = parseInt(stage && stage.getAttribute('data-required-count') || requiredNode && requiredNode.textContent || '5', 10) || 5;

    function setStatus(message, type) {
      if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
        Microgifter.setStatus(status, message, type || '');
        return;
      }
      if (status) status.textContent = message || '';
    }

    function setButtonBusy(busy) {
      if (!button) return;
      button.disabled = !!busy;
      if (busy) {
        button.setAttribute('aria-busy', 'true');
        button.textContent = 'Verifying stamp…';
      } else {
        button.removeAttribute('aria-busy');
        button.textContent = 'Add verified stamp →';
      }
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

    form.addEventListener('submit', function () {
      setButtonBusy(true);
      setStatus('Verifying cashier code and recording the official stamp…');
    }, true);

    form.addEventListener('microgifter:campaign-submitted', function (event) {
      var payload = event.detail && event.detail.payload || {};
      var entry = payload.entry || {};
      var count = payload.stamp_count || entry.stamp_count || (payload.wallet_item_id ? required : 0);
      var total = payload.required_count || entry.required_count || required;
      var remaining = payload.stamps_remaining;
      if (remaining == null && entry.stamps_remaining != null) remaining = entry.stamps_remaining;
      updateProgress(count, total);
      setButtonBusy(false);
      if (payload.reward_unlocked || payload.wallet_item_id || entry.stamp_result === 'unlocked') {
        setStatus('Stamp card complete. Reward sent to Microgifter Inbox.', 'success');
      } else if (remaining != null) {
        setStatus('Verified stamp recorded. ' + remaining + ' more to unlock your reward.', 'success');
      } else {
        setStatus('Verified stamp recorded.', 'success');
      }
    });

    form.addEventListener('microgifter:campaign-submit-failed', function () {
      setButtonBusy(false);
    });

    updateProgress(0, required);
  });
});
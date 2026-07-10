document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }

  function formData(form) {
    var data = Object.fromEntries(new FormData(form).entries());
    var entry = {};
    Object.keys(data).forEach(function (key) {
      if (key.indexOf('entry_') !== 0) return;
      entry[key.replace(/^entry_/, '')] = data[key];
      delete data[key];
    });
    if (Object.keys(entry).length) data.entry = entry;
    return data;
  }

  function number(value, fallback) {
    var parsed = parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function getProgress(payload, fallbackRequired) {
    var data = (payload && payload.data) || payload || {};
    var entry = data.entry || {};
    var required = number(data.required_count || entry.required_count, fallbackRequired || 5);
    var count = number(data.stamp_count || entry.stamp_count, 0);
    var remaining = number(data.stamps_remaining || entry.stamps_remaining, Math.max(0, required - count));
    var unlocked = !!(data.reward_unlocked || data.wallet_item_id || entry.stamp_result === 'unlocked' || count >= required);
    return {
      data: data,
      entry: entry,
      required: Math.max(1, required),
      count: Math.max(0, count),
      remaining: Math.max(0, remaining),
      unlocked: unlocked
    };
  }

  function buildPunches(grid, required) {
    if (!grid) return;
    var current = grid.querySelectorAll('[data-stamp-index]').length;
    if (current === required) return;
    grid.innerHTML = '';
    for (var i = 1; i <= required; i += 1) {
      var punch = document.createElement('span');
      punch.className = 'mg-stamp-punch';
      punch.setAttribute('data-stamp-index', String(i));
      punch.innerHTML = '<b>' + i + '</b>';
      grid.appendChild(punch);
    }
  }

  function renderProgress(form, progress, message) {
    var visual = form.querySelector('[data-stamp-card-visual]');
    if (!visual) return;
    var required = Math.max(1, progress.required || 5);
    var count = Math.min(required, Math.max(0, progress.count || 0));
    var label = form.getAttribute('data-stamp-label') || 'Visit';
    var copy = form.querySelector('[data-stamp-progress-copy]');
    var grid = form.querySelector('[data-stamp-punch-grid]');
    var fill = form.querySelector('[data-stamp-meter-fill]');
    var progressMessage = form.querySelector('[data-stamp-progress-message]');
    buildPunches(grid, required);
    form.setAttribute('data-stamp-count', String(count));
    form.setAttribute('data-stamp-required-count', String(required));
    if (copy) copy.textContent = count + ' of ' + required + ' ' + label.toLowerCase() + ' punches';
    if (grid) {
      grid.querySelectorAll('[data-stamp-index]').forEach(function (node) {
        var index = number(node.getAttribute('data-stamp-index'), 0);
        node.classList.toggle('is-stamped', index <= count);
      });
    }
    if (fill) fill.style.width = Math.min(100, Math.round((count / required) * 100)) + '%';
    visual.classList.toggle('is-unlocked', !!progress.unlocked);
    if (progressMessage) {
      if (progress.unlocked) {
        progressMessage.textContent = 'Reward unlocked. Your completed punch card has been sent to Microgifter Inbox.';
      } else if (message) {
        progressMessage.textContent = message;
      } else {
        progressMessage.textContent = progress.remaining + ' more punch' + (progress.remaining === 1 ? '' : 'es') + ' to unlock your reward.';
      }
    }
  }

  document.querySelectorAll('[data-stamp-card-form]').forEach(function (form) {
    var status = form.querySelector('[data-stamp-card-status]') || form.querySelector('[data-campaign-status]');
    var button = form.querySelector('[data-stamp-card-submit]');
    var result = form.parentElement && form.parentElement.querySelector('[data-campaign-result]') || document.querySelector('[data-campaign-result]');
    var fallbackRequired = number(form.getAttribute('data-stamp-required-count'), 5);

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
      button.setAttribute('aria-busy', busy ? 'true' : 'false');
      button.innerHTML = busy ? 'Recording punch…' : 'Add Stamp / Check Reward';
    }

    renderProgress(form, { required: fallbackRequired, count: 0, remaining: fallbackRequired, unlocked: false }, fallbackRequired + ' punches required before reward issuance is honored.');

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        if (typeof form.reportValidity === 'function') form.reportValidity();
        return;
      }
      if (!window.Microgifter || typeof Microgifter.post !== 'function') {
        setStatus('Microgifter is still loading. Please try again.', 'error');
        return;
      }
      var endpoint = form.dataset.submitEndpoint || '/api/public/campaigns/stamp-card.php';
      setButtonBusy(true);
      setStatus('Recording your punch and checking reward progress…');
      try {
        var response = await Microgifter.post(endpoint, formData(form));
        var progress = getProgress(response, fallbackRequired);
        var message = response.message || (progress.unlocked ? 'Reward unlocked.' : 'Punch recorded.');
        renderProgress(form, progress, message);
        setStatus(message, progress.unlocked ? 'success' : '');
        if (result) {
          result.classList.add('is-visible');
          if (progress.unlocked) {
            result.innerHTML = '<strong>Punch card complete</strong><p>Your reward was sent to Microgifter Inbox.</p>' + (progress.data.inbox_url || progress.data.wallet_item_id ? '<div class="mg-public-campaign-result-actions"><a class="mg-btn mg-btn-primary" href="' + esc(progress.data.inbox_url || '/inbox.php') + '">Open Microgifter Inbox</a></div>' : '');
          } else {
            result.innerHTML = '<strong>Punch recorded</strong><p>' + esc(message) + '</p><div class="mg-public-campaign-result-details"><span>' + esc(progress.count + ' of ' + progress.required + ' punches complete') + '</span><span>' + esc(progress.remaining + ' remaining') + '</span></div>';
          }
        }
      } catch (error) {
        setStatus(error.message || 'Unable to record stamp card visit.', 'error');
      } finally {
        setButtonBusy(false);
      }
    }, true);
  });
});
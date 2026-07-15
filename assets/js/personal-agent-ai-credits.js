document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-personal-gifting-agent]');
  var composer = root && root.querySelector('[data-personal-agent-composer]');
  var chip = composer && composer.querySelector('[data-personal-agent-credit-chip]');
  var detail = composer && composer.querySelector('[data-personal-agent-credit-detail]');
  var submit = composer && composer.querySelector('button[type="submit"]');
  var status = root && root.querySelector('[data-personal-agent-status]');
  var feed = root && root.querySelector('[data-personal-agent-feed]');
  if (!root || !composer || !chip || !window.Microgifter) return;

  var current = null;
  var timer = 0;
  var loading = false;

  function payloadOf(response) {
    return response && response.data ? response.data : (response || {});
  }

  function number(value) {
    return Number(value || 0).toLocaleString();
  }

  function reason(value) {
    return String(value || 'credit policy').replace(/[_-]+/g, ' ');
  }

  function render(credit) {
    current = credit || {};
    chip.classList.remove('is-low', 'is-blocked');
    composer.dataset.aiCreditBlocked = 'false';
    if (submit) submit.disabled = false;

    if (!credit || credit.schema_ready === false) {
      chip.querySelector('strong').textContent = 'AI credits · Legacy access';
      chip.title = credit && credit.message ? credit.message : 'AI credit migration pending.';
      if (detail) detail.textContent = '';
      return;
    }

    var packageName = credit.package && credit.package.name ? credit.package.name : 'Free';
    var available = credit.available_tokens;
    var balance = available == null ? 'Unlimited' : number(available) + ' tokens';
    chip.querySelector('strong').textContent = packageName + ' · ' + balance;

    var dayUsed = credit.usage && credit.usage.day || 0;
    var dayLimit = credit.limits && credit.limits.day;
    if (detail) detail.textContent = dayLimit == null ? number(dayUsed) + ' used today' : number(dayUsed) + ' / ' + number(dayLimit) + ' today';

    if (!credit.can_use) {
      chip.classList.add('is-blocked');
      composer.dataset.aiCreditBlocked = 'true';
      if (submit) submit.disabled = true;
      chip.title = 'AI unavailable: ' + reason(credit.block_reason) + '.';
    } else {
      var allocated = Number(credit.package_tokens_allocated || 0) + Number(credit.manual_tokens_remaining || 0);
      if (available != null && (Number(available) < 2500 || (allocated > 0 && Number(available) / allocated <= 0.1))) chip.classList.add('is-low');
      chip.title = 'Package balance: ' + (credit.package_tokens_remaining == null ? 'Unlimited' : number(credit.package_tokens_remaining))
        + '. Bonus balance: ' + number(credit.manual_tokens_remaining || 0) + '.';
    }
  }

  async function refresh() {
    if (loading) return;
    loading = true;
    try {
      var response = await Microgifter.get('/api/user-agent/ai-credits.php');
      var data = payloadOf(response);
      render(data.credits || data);
    } catch (error) {
      chip.querySelector('strong').textContent = 'AI credits unavailable';
      chip.classList.add('is-low');
      chip.title = error.message || 'Unable to load AI credits.';
    } finally {
      loading = false;
    }
  }

  function queueRefresh() {
    window.clearTimeout(timer);
    timer = window.setTimeout(refresh, 450);
  }

  composer.addEventListener('submit', function (event) {
    if (current && current.schema_ready !== false && !current.can_use) {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (status) {
        status.textContent = 'AI access is unavailable because of your current token balance or allowance. Review your subscription package.';
        status.classList.add('is-error');
      }
      return;
    }
    window.setTimeout(queueRefresh, 1200);
  }, true);

  if (feed && window.MutationObserver) {
    new MutationObserver(function (mutations) {
      var assistantAdded = mutations.some(function (mutation) {
        return Array.prototype.some.call(mutation.addedNodes || [], function (node) {
          return node.nodeType === 1 && (node.matches('.mg-personal-agent-message.is-assistant') || node.querySelector && node.querySelector('.mg-personal-agent-message.is-assistant'));
        });
      });
      if (assistantAdded) queueRefresh();
    }).observe(feed, { childList: true, subtree: true });
  }

  window.MicrogifterPersonalAgentAiCredits = { refresh: refresh, current: function () { return current; } };
  refresh();
});

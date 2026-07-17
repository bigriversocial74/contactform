document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root) return;

  var feed = root.querySelector('[data-agent-chat-feed]');
  var form = root.querySelector('[data-agent-chat-form]');
  var textarea = form ? form.querySelector('[data-agent-chat-textarea],textarea[name="message"]') : null;
  var snapshotPattern = /^(?:\/?snapshot|current snapshot|merchant snapshot)(?:\s+(?:7|14|30|60|90|180|365)(?:\s+days?)?)?$/i;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function text(element) {
    return element ? String(element.textContent || '').trim() : '';
  }

  function cleanNumber(value) {
    var normalized = String(value || '').replace(/[^0-9.-]/g, '');
    if (!normalized || normalized === '-' || normalized === '.') return null;
    var parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function currentWindow(message) {
    var source = text(message.querySelector('.mg-agent-metric-block .mg-agent-block-head span')) + ' ' + text(message.querySelector('.mg-agent-chat-bubble > p'));
    var match = source.match(/last\s+(7|14|30|60|90|180|365)\s+days/i);
    return match ? Number(match[1]) : 30;
  }

  function submitPrompt(prompt) {
    if (!textarea || !form || !prompt) return;
    textarea.value = prompt;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  }

  function metricRows(message) {
    var rows = [];
    message.querySelectorAll('.mg-agent-metric-block').forEach(function (block) {
      var section = text(block.querySelector('.mg-agent-block-head strong')) || 'Snapshot';
      block.querySelectorAll('.mg-agent-metrics article').forEach(function (article) {
        var value = text(article.querySelector('strong'));
        var label = text(article.querySelector('span'));
        if (!label) return;
        rows.push({ section: section, label: label, value: value, numeric: cleanNumber(value) });
      });
    });
    return rows;
  }

  function metricMap(rows) {
    var map = {};
    rows.forEach(function (row) { map[row.label.toLowerCase()] = row; });
    return map;
  }

  function metricValue(map, label) {
    var row = map[String(label || '').toLowerCase()];
    return row ? row.value : '0';
  }

  function metricNumeric(map, label) {
    var row = map[String(label || '').toLowerCase()];
    return row && row.numeric != null ? row.numeric : 0;
  }

  function wrapExpandableBlocks(message) {
    message.querySelectorAll('.mg-agent-metric-block,.mg-agent-chart-block').forEach(function (block, index) {
      if (block.closest('.mg-agent-snapshot-section')) return;
      var head = block.querySelector('.mg-agent-block-head');
      var title = text(head && head.querySelector('strong')) || (block.classList.contains('mg-agent-chart-block') ? 'Activity chart' : 'Snapshot metrics');
      var description = text(head && head.querySelector('span'));
      var details = document.createElement('details');
      details.className = 'mg-agent-snapshot-section';
      details.open = index < 2;
      details.innerHTML = '<summary><span><strong>' + esc(title) + '</strong>' + (description ? '<small>' + esc(description) + '</small>' : '') + '</span><b aria-hidden="true">+</b></summary>';
      block.parentNode.insertBefore(details, block);
      details.appendChild(block);
      if (head) head.hidden = true;
    });
  }

  function toolbarHtml(days, updated) {
    return '<section class="mg-agent-snapshot-toolbar" aria-label="Merchant snapshot controls">' +
      '<div class="mg-agent-snapshot-toolbar-title"><span>Database Action Center</span><strong>Merchant Snapshot</strong><small>Updated ' + esc(updated || 'just now') + ' · no external AI used</small></div>' +
      '<div class="mg-agent-snapshot-window" role="group" aria-label="Snapshot date range">' + [7, 30, 90].map(function (value) {
        return '<button type="button" data-agent-snapshot-days="' + value + '" class="' + (days === value ? 'is-active' : '') + '" aria-pressed="' + (days === value ? 'true' : 'false') + '">' + value + ' days</button>';
      }).join('') + '</div>' +
      '<div class="mg-agent-snapshot-tools"><button type="button" data-agent-snapshot-refresh="' + days + '">Refresh</button><button type="button" data-agent-snapshot-export>Export CSV</button><button type="button" data-agent-snapshot-print>Print</button></div>' +
      '</section>';
  }

  function actionDefinitions(map) {
    return [
      {
        title: 'Customers needing follow-up',
        value: metricValue(map, 'Need action'),
        detail: 'Review inactive, unclaimed, and unredeemed customer signals.',
        href: '/merchant-crm.php',
        label: 'Open CRM',
        prompt: 'Prepare a prioritized follow-up plan for customers needing action. Keep the plan approval-first and do not send anything.'
      },
      {
        title: 'Purchases and order recovery',
        value: metricValue(map, 'Purchases'),
        detail: 'Inspect current purchases and identify pending or failed order work.',
        href: '/merchant-orders.php',
        label: 'Review orders',
        prompt: 'Review my current purchase activity and prepare a recovery plan for any pending or failed orders.'
      },
      {
        title: 'Campaign signups',
        value: metricValue(map, 'Campaign signups'),
        detail: 'Turn recent signup and engagement activity into the next campaign step.',
        href: '/merchant-campaigns.php',
        label: 'Open campaigns',
        prompt: 'Create a campaign recommendation from my latest campaign signups, followers, comments, and customer activity.'
      },
      {
        title: 'Unclaimed rewards',
        value: metricValue(map, 'Unclaimed rewards'),
        detail: 'Prepare a safe reminder or reward follow-up for review.',
        href: '/merchant-crm.php',
        label: 'Review customers',
        prompt: 'Draft a customer reminder for unclaimed rewards. Keep it concise and place it in the approval workflow before sending.'
      },
      {
        title: 'Comments and engagement',
        value: metricValue(map, 'Comments'),
        detail: 'Review current conversation activity and response opportunities.',
        href: '/merchant.php',
        label: 'Open Merchant Center',
        prompt: 'Review my recent comment and follower activity and suggest the three best engagement responses.'
      },
      {
        title: 'Agent Review queue',
        value: metricValue(map, 'Review queue'),
        detail: 'Approve, defer, or reject recommendations before execution.',
        href: '/merchant-agent-approvals.php',
        label: 'Open review queue',
        prompt: 'Summarize what should be reviewed first in my Agent Review queue and explain why.'
      }
    ];
  }

  function actionCenterHtml(rows) {
    var map = metricMap(rows);
    var active = rows.some(function (row) { return row.numeric != null && row.numeric > 0; });
    var actions = actionDefinitions(map);
    var table = '<div class="mg-agent-snapshot-table-wrap"><table class="mg-agent-snapshot-table"><thead><tr><th>Section</th><th>Metric</th><th>Current value</th></tr></thead><tbody>' + rows.map(function (row) {
      return '<tr><td>' + esc(row.section) + '</td><th scope="row">' + esc(row.label) + '</th><td>' + esc(row.value) + '</td></tr>';
    }).join('') + '</tbody></table></div>';
    var cards = '<div class="mg-agent-snapshot-actions">' + actions.map(function (action) {
      return '<article><div><span>' + esc(action.value) + '</span><strong>' + esc(action.title) + '</strong><p>' + esc(action.detail) + '</p></div><div><a href="' + esc(action.href) + '">' + esc(action.label) + '</a><button type="button" data-agent-snapshot-prompt="' + esc(action.prompt) + '">Ask Agent</button></div></article>';
    }).join('') + '</div>';
    var empty = active ? '' : '<div class="mg-agent-snapshot-empty"><strong>No stored activity was found for this window.</strong><p>Add or publish products, start a campaign, register locations, or build CRM activity, then refresh this snapshot.</p><div><a href="/merchant-products.php">Products</a><a href="/merchant-campaigns.php">Campaigns</a><a href="/merchant-locations.php">Locations</a><a href="/merchant-crm.php">CRM</a></div></div>';
    return '<section class="mg-agent-snapshot-action-center"><div class="mg-agent-snapshot-action-head"><div><span>Operational drill-down</span><strong>What needs attention next</strong><p>Open the underlying workflow or ask Merchant Agent to prepare an approval-first plan.</p></div></div>' + table + cards + empty + '</section>';
  }

  function enhanceSnapshot(message) {
    if (!message || message.dataset.snapshotEnhanced === 'true') return;
    var title = text(message.querySelector('.mg-agent-metric-block .mg-agent-block-head strong'));
    if (!/current merchant snapshot/i.test(title)) return;
    message.dataset.snapshotEnhanced = 'true';
    message.classList.add('is-snapshot-action-center');
    var bubble = message.querySelector('.mg-agent-chat-bubble');
    var blocks = message.querySelector('.mg-agent-chat-blocks');
    if (!bubble || !blocks) return;
    var days = currentWindow(message);
    var updated = text(message.querySelector('.mg-agent-chat-meta time')) || 'just now';
    var toolbar = document.createElement('div');
    toolbar.innerHTML = toolbarHtml(days, updated);
    bubble.insertBefore(toolbar.firstElementChild, blocks);
    wrapExpandableBlocks(message);
    var center = document.createElement('div');
    center.innerHTML = actionCenterHtml(metricRows(message));
    blocks.parentNode.insertBefore(center.firstElementChild, blocks.nextSibling);
  }

  function addRetry(message) {
    if (!message || message.dataset.snapshotRetryReady === 'true' || !message.classList.contains('is-error')) return;
    var previous = message.previousElementSibling;
    if (!previous || !previous.classList.contains('is-user')) return;
    var prompt = text(previous.querySelector('.mg-agent-chat-bubble > p'));
    if (!snapshotPattern.test(prompt)) return;
    message.dataset.snapshotRetryReady = 'true';
    var bubble = message.querySelector('.mg-agent-chat-bubble');
    if (!bubble) return;
    var retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'mg-agent-snapshot-retry';
    retry.setAttribute('data-agent-snapshot-retry', prompt);
    retry.textContent = 'Retry snapshot';
    bubble.appendChild(retry);
  }

  function enhance() {
    if (!feed) return;
    feed.querySelectorAll('.mg-agent-chat-message.is-agent').forEach(enhanceSnapshot);
    feed.querySelectorAll('.mg-agent-chat-message.is-error').forEach(addRetry);
  }

  function csvCell(value) {
    return '"' + String(value == null ? '' : value).replace(/"/g, '""') + '"';
  }

  function exportSnapshot(message) {
    var rows = metricRows(message);
    var csv = [['Section', 'Metric', 'Current value']].concat(rows.map(function (row) { return [row.section, row.label, row.value]; })).map(function (row) {
      return row.map(csvCell).join(',');
    }).join('\r\n');
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    var url = window.URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = 'merchant-snapshot-' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  }

  function printSnapshot(message) {
    message.classList.add('is-snapshot-print-target');
    document.body.classList.add('mg-print-merchant-snapshot');
    window.print();
    window.setTimeout(function () {
      message.classList.remove('is-snapshot-print-target');
      document.body.classList.remove('mg-print-merchant-snapshot');
    }, 500);
  }

  root.addEventListener('click', function (event) {
    var day = event.target.closest && event.target.closest('[data-agent-snapshot-days]');
    if (day) {
      submitPrompt('snapshot ' + day.getAttribute('data-agent-snapshot-days') + ' days');
      return;
    }
    var refresh = event.target.closest && event.target.closest('[data-agent-snapshot-refresh]');
    if (refresh) {
      submitPrompt('snapshot ' + (refresh.getAttribute('data-agent-snapshot-refresh') || '30') + ' days');
      return;
    }
    var prompt = event.target.closest && event.target.closest('[data-agent-snapshot-prompt]');
    if (prompt) {
      submitPrompt(prompt.getAttribute('data-agent-snapshot-prompt') || 'What should I focus on next?');
      return;
    }
    var retry = event.target.closest && event.target.closest('[data-agent-snapshot-retry]');
    if (retry) {
      submitPrompt(retry.getAttribute('data-agent-snapshot-retry') || 'snapshot');
      return;
    }
    var exportButton = event.target.closest && event.target.closest('[data-agent-snapshot-export]');
    if (exportButton) {
      var exportMessage = exportButton.closest('.mg-agent-chat-message');
      if (exportMessage) exportSnapshot(exportMessage);
      return;
    }
    var printButton = event.target.closest && event.target.closest('[data-agent-snapshot-print]');
    if (printButton) {
      var printMessage = printButton.closest('.mg-agent-chat-message');
      if (printMessage) printSnapshot(printMessage);
    }
  });

  if (feed && window.MutationObserver) {
    new MutationObserver(enhance).observe(feed, { childList: true, subtree: true });
  }
  window.addEventListener('afterprint', function () {
    document.body.classList.remove('mg-print-merchant-snapshot');
    document.querySelectorAll('.is-snapshot-print-target').forEach(function (message) { message.classList.remove('is-snapshot-print-target'); });
  });
  enhance();
});

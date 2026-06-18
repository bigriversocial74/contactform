window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';
  var MG = window.Microgifter;
  var root = document.querySelector('[data-demand-commitments]');
  if (!root || !MG.get) return;

  var status = '';
  var cursor = null;
  var loading = false;

  function qs(selector) { return root.querySelector(selector); }
  function qsa(selector) { return Array.from(root.querySelectorAll(selector)); }
  function hide(node, value) { if (node) node.classList.toggle('mg-hidden', Boolean(value)); }
  function clear(node) { if (node) node.replaceChildren(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function money(cents, currency) {
    try { return new Intl.NumberFormat(undefined, { style:'currency', currency:String(currency || 'USD') }).format(Number(cents || 0) / 100); }
    catch (error) { return '$' + (Number(cents || 0) / 100).toFixed(2); }
  }
  function date(value) {
    if (!value) return 'No date set';
    var parsed = new Date(String(value).replace(' ', 'T') + (String(value).includes('T') ? '' : 'Z'));
    return Number.isNaN(parsed.getTime()) ? String(value) : new Intl.DateTimeFormat(undefined, { dateStyle:'medium' }).format(parsed);
  }
  function label(value) { return String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }

  function metric(title, value, note) {
    var card = document.createElement('article'); card.className = 'mg-commitment-metric';
    var span = document.createElement('span'); span.textContent = title;
    var strong = document.createElement('strong'); strong.textContent = value;
    var small = document.createElement('small'); small.textContent = note;
    card.append(span, strong, small); return card;
  }

  function renderSummary(summary) {
    var wrap = qs('[data-commitment-summary]'); clear(wrap);
    wrap.append(
      metric('Upcoming', Number(summary.outstanding || 0).toLocaleString(), 'Prepaid and not yet redeemed'),
      metric('Committed value', money(summary.committed_value_cents, summary.currency), 'Outstanding prepaid value'),
      metric('Redeemed', Number(summary.redeemed || 0).toLocaleString(), 'Realized merchant demand'),
      metric('Realized value', money(summary.realized_value_cents, summary.currency), 'Completed redemptions')
    );
  }

  function commitmentCard(item) {
    var card = document.createElement('article'); card.className = 'mg-commitment-card';
    var head = document.createElement('header');
    var identity = document.createElement('div');
    var title = document.createElement('h2'); title.textContent = item.title || 'Microgift';
    var meta = document.createElement('span'); meta.textContent = label(item.role) + ' · ' + label(item.state);
    identity.append(title, meta);
    var value = document.createElement('strong'); value.textContent = money(item.value_cents, item.currency);
    head.append(identity, value); card.appendChild(head);

    var details = document.createElement('dl');
    [['Expected', date(item.expected_from)], ['Merchant', item.merchant && item.merchant.name || 'Merchant'], ['Status', label(item.signal_status)], ['Window source', label(item.window_source)]].forEach(function (pair) {
      var div = document.createElement('div'); var dt = document.createElement('dt'); dt.textContent = pair[0]; var dd = document.createElement('dd'); dd.textContent = pair[1]; div.append(dt, dd); details.appendChild(div);
    });
    card.appendChild(details);

    var actions = document.createElement('div'); actions.className = 'mg-commitment-actions';
    if (item.merchant && item.merchant.profile_slug) {
      var merchant = document.createElement('a'); merchant.className = 'mg-btn mg-btn-ghost'; merchant.href = '/profile.php?slug=' + encodeURIComponent(item.merchant.profile_slug); merchant.textContent = 'View merchant'; actions.appendChild(merchant);
    }
    var gift = document.createElement('a'); gift.className = 'mg-btn mg-btn-soft'; gift.href = '/agent.php?gift=' + encodeURIComponent(item.microgift_id); gift.textContent = 'Open gift'; actions.appendChild(gift);
    card.appendChild(actions);
    return card;
  }

  function resetStates() {
    hide(qs('[data-commitment-loading]'), true); hide(qs('[data-commitment-signin]'), true); hide(qs('[data-commitment-empty]'), true); hide(qs('[data-commitment-error]'), true);
  }

  async function load(append) {
    if (loading) return;
    loading = true; resetStates();
    if (!append) { cursor = null; clear(qs('[data-commitment-list]')); hide(qs('[data-commitment-list]'), true); hide(qs('[data-commitment-loading]'), false); }
    qs('[data-commitment-status-text]').textContent = append ? 'Loading more commitments…' : 'Loading commitments…';
    var path = '/api/account/demand-commitments.php?limit=20&status=' + encodeURIComponent(status);
    if (append && cursor) path += '&cursor=' + encodeURIComponent(cursor);
    try {
      var data = payload(await MG.get(path));
      renderSummary(data.summary || {});
      var list = qs('[data-commitment-list]');
      var items = data.commitments && Array.isArray(data.commitments.items) ? data.commitments.items : [];
      items.forEach(function (item) { list.appendChild(commitmentCard(item)); });
      cursor = data.commitments && data.commitments.has_more ? String(data.commitments.next_cursor || '') : null;
      hide(qs('[data-commitment-pagination]'), !cursor);
      hide(list, list.children.length === 0);
      hide(qs('[data-commitment-empty]'), list.children.length > 0);
      qs('[data-commitment-status-text]').textContent = list.children.length ? 'Commitments loaded.' : '';
    } catch (error) {
      if (error && error.status === 401) hide(qs('[data-commitment-signin]'), false);
      else { hide(qs('[data-commitment-error]'), false); qs('[data-commitment-error-message]').textContent = error.message || 'Unable to load commitments.'; }
      qs('[data-commitment-status-text]').textContent = '';
    } finally { loading = false; hide(qs('[data-commitment-loading]'), true); }
  }

  root.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-commitment-status]');
    if (tab) { status = tab.dataset.commitmentStatus; qsa('[data-commitment-status]').forEach(function (button) { button.classList.toggle('is-active', button === tab); }); load(false); return; }
    if (event.target.closest('[data-commitment-refresh]') || event.target.closest('[data-commitment-retry]')) return void load(false);
    if (event.target.closest('[data-commitment-more]')) return void load(true);
  });
  load(false);
})(window, document);

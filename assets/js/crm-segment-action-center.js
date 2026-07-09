document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-crm-segment-action-center]');
  if (!root || !window.Microgifter) return;

  var currentSegment = null;
  var selectedSegment = root.getAttribute('data-selected-segment') || '';

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function count(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
  function pct(value) { return (Number(value || 0)).toFixed(Number(value || 0) % 1 === 0 ? 0 : 1) + '%'; }
  function compactDate(value) { return value ? String(value).replace('T', ' ').replace(/\.\d+Z$/, '') : '—'; }
  function qs(selector) { return root.querySelector(selector); }
  function setAlert(message, tone) { var node = qs('[data-segment-alert]'); if (!node) return; node.hidden = !message; node.className = 'mg-embed-analytics-alert' + (tone ? ' is-' + tone : ''); node.innerHTML = message || ''; }
  function toast(message) { if (Microgifter.toast) Microgifter.toast(message); else setAlert('<strong>' + esc(message) + '</strong>', 'info'); }

  function segmentId() {
    var input = qs('[data-segment-input]');
    return input && input.value ? input.value.trim() : selectedSegment;
  }

  function apiUrl() { return '/api/merchant/crm-media-segment-action.php?segment=' + encodeURIComponent(segmentId()); }

  async function postAction(payload) {
    payload = payload || {};
    payload.segment_id = segmentId();
    var response = await Microgifter.post('/api/merchant/crm-media-segment-action.php', payload);
    return response.data || response;
  }

  function setLink(selector, href) {
    var node = qs(selector);
    if (node) node.href = href || '#';
  }

  function renderStats(segment) {
    var node = qs('[data-segment-stats]');
    if (!node) return;
    var delta = Number(segment.count_delta || 0);
    var deltaLabel = delta > 0 ? '+' + delta : String(delta);
    var health = segment.health || {};
    node.innerHTML = [
      ['Current contacts', count(segment.current_count)],
      ['Previous count', count(segment.previous_count)],
      ['Delta', deltaLabel],
      ['Window', count(segment.days) + ' days'],
      ['Health', health.label || 'Stable']
    ].map(function (card) { return '<article><b>' + esc(card[1]) + '</b><span>' + esc(card[0]) + '</span></article>'; }).join('');
  }

  function renderRules(segment) {
    var node = qs('[data-segment-rules-panel]');
    if (!node) return;
    node.innerHTML = '<article><b>' + esc(segment.name || 'Saved segment') + '</b><span>' + esc(segment.campaign_title || 'Media campaign') + '</span><small>' + esc(segment.behavior_label || 'All contacts') + ' · ' + esc(segment.days || 30) + ' days' + (segment.search ? ' · search: ' + esc(segment.search) : '') + '</small></article>' +
      '<article><b>Dynamic rules</b><span>This segment refreshes from current campaign activity.</span><small>Campaign type: ' + esc(segment.campaign_type || 'media') + '</small></article>';
  }

  function renderHealth(segment) {
    var node = qs('[data-segment-health]');
    if (!node) return;
    var health = segment.health || {};
    node.innerHTML = '<article><b>' + esc(health.label || 'Stable') + '</b><span>' + esc(health.summary || 'No contact count change since last refresh.') + '</span><small>Last refreshed: ' + esc(compactDate(segment.last_refreshed_at)) + '</small></article>' +
      '<article><b>Movement</b><span>' + esc(segment.count_delta > 0 ? 'New contacts entered this audience.' : (segment.count_delta < 0 ? 'Some contacts moved out of this audience.' : 'Audience count is unchanged.')) + '</span><small>Previous ' + count(segment.previous_count) + ' · current ' + count(segment.current_count) + '</small></article>';
  }

  function renderMembers(segment) {
    var table = qs('[data-segment-contact-table]');
    if (!table) return;
    var members = Array.isArray(segment.members) ? segment.members : [];
    if (!members.length) {
      table.innerHTML = '<tbody><tr><td><div class="mg-empty-actions"><strong>No contacts currently match this segment.</strong><p>Try opening the rules or refreshing after more campaign activity.</p></div></td></tr></tbody>';
      return;
    }
    table.innerHTML = '<thead><tr><th>Contact</th><th>Behavior</th><th>Progress</th><th>Reward State</th><th>Source</th><th>Last Activity</th></tr></thead><tbody>' + members.map(function (member) {
      return '<tr><td><strong>' + esc(member.name || 'Customer') + '</strong><small>' + esc(member.email || '') + (member.phone ? ' · ' + esc(member.phone) : '') + '</small></td>' +
        '<td>' + esc(member.behavior_label || member.behavior_bucket || '—') + '</td>' +
        '<td>' + pct(member.progress_percent) + '<small>' + count(member.starts) + ' starts · ' + count(member.progress_events) + ' progress events</small></td>' +
        '<td>' + count(member.wallet_items) + ' issued<small>' + count(member.claimed) + ' claimed · ' + count(member.redeemed) + ' redeemed</small></td>' +
        '<td>' + esc(member.origin_host || 'Public page') + '<small>' + esc(member.embed_mode || '') + '</small></td>' +
        '<td>' + esc(compactDate(member.last_activity_at)) + '</td></tr>';
    }).join('') + '</tbody>';
  }

  function renderActivity(segment) {
    var node = qs('[data-segment-activity]');
    if (!node) return;
    var items = Array.isArray(segment.activity_log) ? segment.activity_log : [];
    if (!items.length) { node.innerHTML = '<article><b>No segment activity yet.</b><span>Actions will appear here as the segment is used.</span></article>'; return; }
    node.innerHTML = items.map(function (item) {
      return '<article><b>' + esc(item.label || item.type || 'Segment event') + '</b><span>' + esc(item.type || '') + '</span><small>' + esc(compactDate(item.at)) + (item.count !== undefined ? ' · count ' + count(item.count) : '') + '</small></article>';
    }).join('');
  }

  function renderActions(segment) {
    var urls = segment.urls || {};
    setLink('[data-segment-message]', urls.message);
    setLink('[data-segment-reward]', urls.reward);
    setLink('[data-segment-followup]', urls.followup);
    setLink('[data-segment-export]', urls.export);
    setLink('[data-segment-rules]', urls.open_rules);
  }

  function renderManage(segment) {
    var name = qs('[data-segment-name]');
    var desc = qs('[data-segment-description-input]');
    if (name) name.value = segment.name || '';
    if (desc) desc.value = segment.description || '';
  }

  function render(segment) {
    currentSegment = segment;
    var title = qs('[data-segment-title]');
    var desc = qs('[data-segment-description]');
    if (title) title.textContent = segment.name || 'Saved Segment Action Center';
    if (desc) desc.textContent = [segment.campaign_title, segment.behavior_label, count(segment.current_count) + ' contacts'].filter(Boolean).join(' · ');
    renderStats(segment);
    renderRules(segment);
    renderHealth(segment);
    renderMembers(segment);
    renderActivity(segment);
    renderActions(segment);
    renderManage(segment);
    setAlert('', '');
    if (window.history) window.history.replaceState({}, '', '/merchant-crm-segment-action-center.php?segment=' + encodeURIComponent(segment.id));
  }

  async function load(refresh) {
    if (!segmentId()) return setAlert('<strong>Enter a saved segment ID.</strong>', 'warn');
    setAlert('<strong>Loading segment action center...</strong>', 'info');
    try {
      var response = refresh ? await postAction({ action: 'refresh' }) : await Microgifter.get(apiUrl());
      var data = response.data || response;
      if (data.schema_ready === false) return setAlert('<strong>Saved segment SQL is not installed.</strong> Import database/merchant_crm_media_segments_v1.sql first.', 'warn');
      render(data.segment || {});
      if (refresh) toast('Segment count refreshed.');
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to load segment action center.') + '</strong>', 'warn');
    }
  }

  async function renameSegment(event) {
    event.preventDefault();
    if (!currentSegment) return;
    var name = qs('[data-segment-name]');
    var desc = qs('[data-segment-description-input]');
    try {
      var data = await postAction({ action: 'rename', name: name ? name.value : '', description: desc ? desc.value : '' });
      render(data.segment || {});
      toast('Segment renamed.');
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to rename segment.') + '</strong>', 'warn');
    }
  }

  async function duplicateSegment() {
    if (!currentSegment) return;
    var suggested = (currentSegment.name || 'Saved segment') + ' Copy';
    var name = window.prompt('Name the duplicate segment:', suggested);
    if (!name) return;
    try {
      var data = await postAction({ action: 'duplicate', name: name });
      var segment = data.segment || {};
      toast('Segment duplicated.');
      window.location.href = '/merchant-crm-segment-action-center.php?segment=' + encodeURIComponent(segment.id || '');
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to duplicate segment.') + '</strong>', 'warn');
    }
  }

  async function deleteSegment() {
    if (!currentSegment || !window.confirm('Delete this saved segment?')) return;
    try {
      await postAction({ action: 'delete' });
      toast('Segment deleted.');
      window.location.href = '/merchant-crm.php';
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to delete segment.') + '</strong>', 'warn');
    }
  }

  var form = qs('[data-segment-loader]');
  if (form) form.addEventListener('submit', function (event) { event.preventDefault(); load(false); });
  var manage = qs('[data-segment-manage-form]');
  if (manage) manage.addEventListener('submit', renameSegment);
  root.addEventListener('click', function (event) {
    if (event.target && event.target.matches('[data-segment-refresh]')) { event.preventDefault(); load(true); }
    if (event.target && event.target.matches('[data-segment-duplicate]')) { event.preventDefault(); duplicateSegment(); }
    if (event.target && event.target.matches('[data-segment-delete]')) { event.preventDefault(); deleteSegment(); }
    if (event.target && event.target.matches('[data-segment-message],[data-segment-reward],[data-segment-followup],[data-segment-export],[data-segment-rules]') && event.target.getAttribute('href') === '#') {
      event.preventDefault();
      setAlert('<strong>Load a segment first.</strong>', 'warn');
    }
  });

  load(false);
});

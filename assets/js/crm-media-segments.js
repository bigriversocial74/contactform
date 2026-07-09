document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  if (!window.Microgifter || !document.querySelector('[data-merchant-crm-shell]')) return;

  var params = new URLSearchParams(location.search || '');
  var activeSegmentId = params.get('saved_segment') || '';
  var activeAction = String(params.get('action') || '').toLowerCase();
  var activeSegment = null;
  var segments = [];
  var selectedOnce = false;

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function count(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
  function compactDate(value) { return value ? String(value).replace('T', ' ').replace(/\.\d+Z$/, '') : '—'; }
  function toast(message) { if (window.Microgifter && Microgifter.toast) Microgifter.toast(message); }

  function ensurePanel() {
    var app = document.querySelector('[data-merchant-crm-app] .mg-app-panel-body') || document.querySelector('[data-merchant-crm-app]');
    if (!app || document.querySelector('[data-crm-media-segments-panel]')) return;
    var panel = document.createElement('section');
    panel.className = 'mg-crm-insight-card';
    panel.setAttribute('data-crm-media-segments-panel', '');
    panel.innerHTML = '<div class="mg-crm-insight-icon">▶</div><div style="min-width:0;flex:1"><h2>Saved Media Segments</h2><p>Reusable Watch/Listen CRM audiences from media performance filters.</p><div class="mg-crm-mini-feed" data-crm-media-segments-list><article><div><strong>Loading saved media segments...</strong><small>Segments appear after they are saved from Media Performance.</small></div></article></div></div><div class="mg-crm-card-actions"><a class="mg-btn mg-btn-soft" href="/merchant-campaign-media-performance.php">Media Performance</a><button class="mg-btn mg-btn-soft" type="button" data-crm-media-segments-refresh>Refresh</button></div>';
    app.insertBefore(panel, app.firstChild);
  }

  function listNode() { return document.querySelector('[data-crm-media-segments-list]'); }

  function actionCenterUrl(segment) { return segment.action_center_url || (segment.urls && segment.urls.action_center) || '/merchant-crm-segment-action-center.php?segment=' + encodeURIComponent(segment.id || ''); }

  function segmentRow(segment) {
    var active = activeSegmentId && segment.id === activeSegmentId;
    return '<article class="' + (active ? 'is-highlight' : '') + '" data-crm-media-segment-row="' + esc(segment.id) + '"><div><strong>' + esc(segment.name) + '</strong><small>' + esc(segment.campaign_title || 'Media campaign') + ' · ' + esc(segment.behavior_label || 'All contacts') + ' · ' + count(segment.last_count || segment.current_count) + ' contacts</small><small>' + esc(segment.days || 30) + ' days' + (segment.search ? ' · search: ' + esc(segment.search) : '') + ' · refreshed ' + esc(compactDate(segment.last_refreshed_at || segment.updated_at)) + '</small><div class="mg-crm-card-actions" style="justify-content:flex-start;margin-top:8px"><a class="mg-btn mg-btn-primary" href="' + esc(actionCenterUrl(segment)) + '">Action Center</a><a class="mg-btn mg-btn-soft" href="' + esc(segment.crm_url || '#') + '">Open in CRM</a><a class="mg-btn mg-btn-soft" href="' + esc(segment.open_url || '#') + '">Open rules</a><a class="mg-btn mg-btn-soft" href="' + esc(segment.export_url || '#') + '">Export</a><a class="mg-btn mg-btn-ghost" href="' + esc(segment.message_url || '#') + '">Message segment</a><a class="mg-btn mg-btn-ghost" href="' + esc(segment.reward_url || '#') + '">Reward segment</a></div></div><span class="mg-crm-badge ' + (active ? 'is-good' : '') + '">' + (active ? 'active' : 'saved') + '</span></article>';
  }

  function renderSegments() {
    var node = listNode();
    if (!node) return;
    if (!segments.length) {
      node.innerHTML = '<article><div><strong>No saved media segments yet.</strong><small>Open Media Performance, filter a Watch/Listen campaign, then Save Segment.</small></div><a class="mg-btn mg-btn-soft" href="/merchant-campaign-media-performance.php">Create one</a></article>';
      return;
    }
    node.innerHTML = segments.map(segmentRow).join('');
  }

  async function loadSegments() {
    ensurePanel();
    var node = listNode();
    if (node) node.innerHTML = '<article><div><strong>Loading saved media segments...</strong><small>Refreshing dynamic counts.</small></div></article>';
    var query = new URLSearchParams();
    if (activeSegmentId) { query.set('saved_segment', activeSegmentId); query.set('include_contacts', '1'); }
    var response = await Microgifter.get('/api/merchant/crm-media-segments.php' + (query.toString() ? '?' + query.toString() : ''));
    var data = response.data || response;
    if (data.schema_ready === false) {
      if (node) node.innerHTML = '<article><div><strong>Saved media segments SQL is not installed.</strong><small>Import database/merchant_crm_media_segments_v1.sql to enable this panel.</small></div></article>';
      return;
    }
    segments = data.segments || [];
    activeSegment = activeSegmentId ? (segments.find(function (segment) { return segment.id === activeSegmentId; }) || null) : null;
    renderSegments();
    applyActiveSegmentToRows();
  }

  function switchToContactsTab() {
    var button = document.querySelector('[data-crm-tab-target="contacts"]');
    if (activeSegment && button) button.click();
  }

  function applyActiveSegmentToRows() {
    if (!activeSegment || !Array.isArray(activeSegment.contact_ids) || selectedOnce) return;
    var ids = activeSegment.contact_ids;
    if (!ids.length) return;
    var selected = 0;
    ids.forEach(function (id) {
      var row = document.querySelector('tr[data-contact-id="' + (window.CSS && CSS.escape ? CSS.escape(String(id)) : String(id)) + '"]');
      var box = row && row.querySelector('[data-crm-contact-check]');
      if (box && !box.checked) {
        box.checked = true;
        box.dispatchEvent(new Event('change', { bubbles: true }));
        selected++;
      }
    });
    if (selected || ids.length) {
      selectedOnce = true;
      toast('Saved segment loaded: ' + activeSegment.name + ' (' + ids.length + ' contacts).');
      setTimeout(function () {
        if (activeAction === 'message_segment') {
          var msg = document.querySelector('[data-crm-bulk-action="message"]');
          if (msg && !msg.disabled) msg.click();
        }
        if (activeAction === 'reward_segment') {
          var reward = document.querySelector('[data-crm-bulk-action="reward"]');
          if (reward && !reward.disabled) reward.click();
        }
        if (activeAction === 'followup_segment') {
          var followup = document.querySelector('[data-crm-bulk-action="followup"]');
          if (followup && !followup.disabled) followup.click();
        }
      }, 250);
    }
  }

  document.addEventListener('mg:crm-contacts:rendered', function () { switchToContactsTab(); applyActiveSegmentToRows(); });
  document.addEventListener('click', function (event) {
    if (event.target && event.target.matches('[data-crm-media-segments-refresh]')) {
      event.preventDefault();
      selectedOnce = false;
      loadSegments().catch(function () { toast('Unable to refresh saved media segments.'); });
    }
  });

  loadSegments().catch(function () {
    ensurePanel();
    var node = listNode();
    if (node) node.innerHTML = '<article><div><strong>Unable to load saved media segments.</strong><small>Check that the SQL migration has been imported.</small></div></article>';
  });
});

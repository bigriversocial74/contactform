document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  var form = root && root.querySelector('[data-stage12-campaign-builder]');
  if (!root || !form) return;

  var configs = {
    watch_video_reward: { label: 'Watch reward gates', panel: '[data-campaign-type-fields="watch_video_reward"]', required: 'watch_video_required_percent', hidden: 'watch_video_reward_gates_json', staticPrefix: 'watch_video_milestone_', selectMarker: 'data-watch-video-dynamic-reward-select', noun: 'watched gift' },
    listen_music_reward: { label: 'Listen reward gates', panel: '[data-campaign-type-fields="listen_music_reward"]', required: 'listen_required_percent', hidden: 'listen_reward_gates_json', staticPrefix: 'listen_milestone_', selectMarker: 'data-listen-dynamic-reward-select', noun: 'listened gift' }
  };
  var hydratedCampaignId = '';

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function field(name) { return form.elements[name] || null; }
  function value(name) { var el = field(name); return el ? String(el.value || '').trim() : ''; }
  function number(name, fallback) { var n = parseInt(value(name), 10); return Number.isFinite(n) && n > 0 ? Math.max(1, Math.min(100, n)) : fallback; }
  function rewardOptions(selected) { var primary = field('reward_template_id'); var html = '<option value="">Use attached primary reward</option>'; if (primary) Array.prototype.slice.call(primary.options || []).forEach(function (opt) { if (opt.value) html += '<option value="' + esc(opt.value) + '"' + (String(selected || '') === String(opt.value) ? ' selected' : '') + '>' + esc(opt.textContent || 'Reward') + '</option>'; }); return html; }
  function gateRows(cfg) { return Array.prototype.slice.call(root.querySelectorAll('[data-media-reward-gate-row="' + cfg.hidden + '"]')); }
  function defaultGate(cfg) { var percent = number(cfg.required, 80); return { percent: percent, reward_template_id: '', label: percent + '% ' + cfg.noun }; }
  function parseHidden(cfg) { var raw = value(cfg.hidden); if (!raw) return [defaultGate(cfg)]; try { var rows = JSON.parse(raw); if (Array.isArray(rows) && rows.length) return rows; } catch (error) {} return [defaultGate(cfg)]; }
  function ensureHidden(cfg) { if (!field(cfg.hidden)) { var input = document.createElement('input'); input.type = 'hidden'; input.name = cfg.hidden; form.appendChild(input); } }
  function hideStaticMilestones(cfg) { for (var i = 1; i <= 6; i += 1) { [field(cfg.staticPrefix + i + '_percent'), field(cfg.staticPrefix + i + '_reward_template_id')].forEach(function (el) { var box = el && el.closest ? el.closest('.mg-grid-2') : null; if (box) { box.hidden = true; box.setAttribute('data-media-static-gate-hidden', '1'); } }); } }
  function serialize(cfg) { var rows = gateRows(cfg).map(function (row) { var p = Math.max(1, Math.min(100, parseInt((row.querySelector('[data-gate-percent]') || {}).value || '0', 10) || number(cfg.required, 80))); var r = row.querySelector('[data-gate-reward]') ? String(row.querySelector('[data-gate-reward]').value || '') : ''; var l = row.querySelector('[data-gate-label]') ? String(row.querySelector('[data-gate-label]').value || '').trim() : ''; return { percent: p, reward_template_id: r, label: l || (p + '% ' + cfg.noun) }; }); if (!rows.length) rows = [defaultGate(cfg)]; rows.sort(function (a, b) { return a.percent - b.percent; }); var deduped = []; var seen = {}; rows.forEach(function (row) { if (!seen[row.percent]) { seen[row.percent] = true; deduped.push(row); } }); field(cfg.hidden).value = JSON.stringify(deduped); }
  function renderRow(cfg, list, gate) { gate = gate || defaultGate(cfg); var row = document.createElement('div'); row.className = 'mg-media-reward-gate-row'; row.setAttribute('data-media-reward-gate-row', cfg.hidden); row.innerHTML = '<label>Reward level %<input data-gate-percent type="number" min="1" max="100" value="' + esc(gate.percent || number(cfg.required, 80)) + '"></label><label>Reward<select data-gate-reward ' + cfg.selectMarker + '>' + rewardOptions(gate.reward_template_id || '') + '</select></label><label>Label<input data-gate-label maxlength="120" value="' + esc(gate.label || '') + '" placeholder="' + esc((gate.percent || number(cfg.required, 80)) + '% ' + cfg.noun) + '"></label><button class="mg-btn mg-btn-ghost" type="button" data-remove-gate>Remove</button>'; list.appendChild(row); row.querySelectorAll('input,select').forEach(function (el) { el.addEventListener('input', function () { serialize(cfg); }); el.addEventListener('change', function () { serialize(cfg); }); }); var remove = row.querySelector('[data-remove-gate]'); if (remove) remove.addEventListener('click', function () { if (gateRows(cfg).length > 1) row.remove(); else { var d = defaultGate(cfg); row.querySelector('[data-gate-percent]').value = d.percent; row.querySelector('[data-gate-reward]').value = ''; row.querySelector('[data-gate-label]').value = d.label; } serialize(cfg); }); }
  function render(cfg, gates) {
    var panel = root.querySelector(cfg.panel); if (!panel) return;
    ensureHidden(cfg); hideStaticMilestones(cfg);
    var card = panel.querySelector('[data-media-reward-gates="' + cfg.hidden + '"]');
    if (!card) { card = document.createElement('div'); card.className = 'mg-campaign-rule-card mg-media-reward-gates-card'; card.setAttribute('data-media-reward-gates', cfg.hidden); card.innerHTML = '<span class="mg-eyebrow">Dynamic reward levels</span><h3>' + esc(cfg.label) + '</h3><p>Start with one reward gate. Add more percentage levels only when the campaign should issue additional rewards.</p><div class="mg-media-reward-gates-list" data-media-reward-gates-list></div><button class="mg-btn mg-btn-soft" type="button" data-add-media-gate>Add reward level</button><p class="mg-form-hint">Default is one reward at the required percentage. Each added row can use the attached primary reward or another active reward template.</p>'; var insertAfter = field(cfg.required) && field(cfg.required).closest ? field(cfg.required).closest('.mg-grid-2') : null; if (insertAfter && insertAfter.parentNode) insertAfter.parentNode.insertBefore(card, insertAfter.nextSibling); else panel.appendChild(card); card.querySelector('[data-add-media-gate]').addEventListener('click', function () { var next = defaultGate(cfg); var rows = gateRows(cfg); if (rows.length) next.percent = Math.min(100, Math.max(number(cfg.required, 80), parseInt(rows[rows.length - 1].querySelector('[data-gate-percent]').value || '0', 10) + 10)); next.label = next.percent + '% ' + cfg.noun; renderRow(cfg, card.querySelector('[data-media-reward-gates-list]'), next); serialize(cfg); }); }
    var list = card.querySelector('[data-media-reward-gates-list]');
    if (gates && gates.length) { list.innerHTML = ''; gates.forEach(function (gate) { renderRow(cfg, list, gate); }); serialize(cfg); return; }
    if (!gateRows(cfg).length) { parseHidden(cfg).forEach(function (gate) { renderRow(cfg, list, gate); }); }
    serialize(cfg);
  }
  function repopulateRewardSelects() { Object.keys(configs).forEach(function (key) { var cfg = configs[key]; gateRows(cfg).forEach(function (row) { var select = row.querySelector('[data-gate-reward]'); if (!select) return; var current = select.value; select.innerHTML = rewardOptions(current); if (current) select.value = current; }); serialize(cfg); }); }
  async function hydrateExisting() { var id = value('campaign_id'); if (!id || id === hydratedCampaignId || !window.Microgifter) return; hydratedCampaignId = id; try { var response = await Microgifter.get('/api/merchant/campaigns.php'); var campaigns = (response.data || response).campaigns || []; var campaign = campaigns.find(function (item) { return String(item.id) === String(id); }); if (!campaign || !campaign.rules) return; var cfg = configs[String(campaign.campaign_type || '')]; if (!cfg) return; ensureHidden(cfg); field(cfg.hidden).value = JSON.stringify(campaign.rules.milestones || [defaultGate(cfg)]); render(cfg, campaign.rules.milestones || [defaultGate(cfg)]); } catch (error) {} }
  function ensureAll() { Object.keys(configs).forEach(function (key) { render(configs[key], null); }); hydrateExisting(); repopulateRewardSelects(); }

  ensureAll();
  var attempts = 0;
  var timer = window.setInterval(function () { ensureAll(); attempts += 1; if (attempts > 25) window.clearInterval(timer); }, 350);
  form.addEventListener('submit', function () { Object.keys(configs).forEach(function (key) { serialize(configs[key]); }); }, true);
  form.addEventListener('change', function (event) { if (event.target && (event.target.name === 'reward_template_id' || event.target.name === 'campaign_type' || event.target.name === 'watch_video_required_percent' || event.target.name === 'listen_required_percent')) ensureAll(); });
  root.addEventListener('click', function () { window.setTimeout(ensureAll, 120); });
});

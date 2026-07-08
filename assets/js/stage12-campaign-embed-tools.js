document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  if (!root || !window.Microgifter) return;

  var modal = null;
  var activeCampaign = null;
  var activeSettings = null;
  var runtimeHealth = null;

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function absoluteUrl(path) { try { return new URL(path, window.location.origin).toString(); } catch (error) { return window.location.origin + String(path || ''); } }
  function status(message) { var node = modal ? modal.querySelector('[data-campaign-embed-status]') : null; if (node) node.textContent = message || ''; }
  function campaignTypeLabel(value) { return String(value || 'campaign').replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); }); }
  function embedReference(campaign) { if (!campaign) return ''; if (campaign.campaign_type === 'qr_reward_drop') return campaign.id || campaign.slug || ''; return campaign.slug || campaign.id || ''; }
  function embedScriptUrl() { return absoluteUrl('/assets/js/microgifter-campaign-embed.js'); }
  function debugEnabled() { var checkbox = modal ? modal.querySelector('[data-campaign-embed-debug]') : null; return !!(checkbox && checkbox.checked); }
  function selectedMode() { var selected = modal ? modal.querySelector('input[name="mg_campaign_embed_mode"]:checked') : null; return selected ? selected.value : ((activeSettings && activeSettings.default_layout) || 'inline'); }

  function buildInlineCode(campaign, displayMode) {
    var ref = embedReference(campaign);
    var mode = displayMode || 'inline';
    var attrs = 'class="microgifter-campaign-embed" data-microgifter-campaign="' + esc(ref) + '" data-microgifter-display="' + esc(mode) + '" data-microgifter-source="merchant_embed"';
    var buttonText = activeSettings && activeSettings.custom_button_text ? activeSettings.custom_button_text : '';
    if (buttonText) attrs += ' data-microgifter-button-label="' + esc(buttonText) + '"';
    if (debugEnabled()) attrs += ' data-microgifter-debug="1"';
    return '<div ' + attrs + '></div>\n<script async src="' + esc(embedScriptUrl()) + '"></script>';
  }

  function buildIframeCode(campaign) {
    var publicUrl = (((campaign || {}).public_tools || {}).public_url) || '';
    if (!publicUrl) return 'Public campaign URL unavailable for this campaign.';
    return '<iframe src="' + esc(publicUrl) + '" title="' + esc(campaign.title || 'Microgifter campaign') + '" style="width:100%;min-height:640px;border:0;border-radius:18px;" loading="lazy"></iframe>';
  }

  function ensureModal() {
    if (modal) return modal;
    modal = document.createElement('section');
    modal.className = 'mg-campaign-embed-modal';
    modal.setAttribute('hidden', 'hidden');
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '<div class="mg-campaign-embed-backdrop" data-campaign-embed-close></div>' +
      '<div class="mg-campaign-embed-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-campaign-embed-title">' +
        '<div class="mg-campaign-embed-head"><div><span class="mg-eyebrow">Website embed</span><h2 id="mg-campaign-embed-title">Embed campaign</h2><p data-campaign-embed-summary>Select the format, save defaults, copy the code, and paste it into the merchant website.</p></div><button class="mg-campaign-embed-close" type="button" aria-label="Close embed options" data-campaign-embed-close>&times;</button></div>' +
        '<div class="mg-campaign-embed-grid">' +
          '<section class="mg-campaign-embed-card"><h3>Copy website code</h3><p>Use the script embed for the best fit. It renders inline and inherits the host page font, colors, and form styles where possible.</p><div class="mg-campaign-embed-options" aria-label="Embed display mode"><label><input type="radio" name="mg_campaign_embed_mode" value="inline" checked> Inline form card</label><label><input type="radio" name="mg_campaign_embed_mode" value="button"> Button / popup launcher</label><label><input type="radio" name="mg_campaign_embed_mode" value="compact"> Compact form</label><label><input type="checkbox" data-campaign-embed-debug value="1"> Add debug mode attribute</label></div><div class="mg-campaign-embed-code-row"><label>Script embed<textarea readonly data-campaign-embed-code></textarea></label><label>Iframe fallback<textarea readonly data-campaign-iframe-code></textarea></label></div><div class="mg-campaign-embed-actions"><button class="mg-btn mg-btn-primary" type="button" data-copy-campaign-embed>Copy script code</button><button class="mg-btn mg-btn-soft" type="button" data-copy-campaign-iframe>Copy iframe fallback</button><a class="mg-btn mg-btn-ghost" href="#" target="_blank" rel="noopener" data-campaign-public-link>Open public page</a><a class="mg-btn mg-btn-ghost" href="/merchant-campaign-embed-qa.php" target="_blank" rel="noopener" data-campaign-embed-qa-link>Open QA test page</a></div><p class="mg-campaign-embed-status" data-campaign-embed-status>Ready.</p>' +
          '<div class="mg-campaign-embed-settings"><h3>Embed settings</h3><label><input type="checkbox" data-embed-setting="embed_enabled"> Embed enabled</label><label>Default layout<select data-embed-setting="default_layout"><option value="inline">Inline form card</option><option value="button">Button / popup launcher</option><option value="compact">Compact form</option></select></label><label>Custom button text<input type="text" maxlength="120" data-embed-setting="custom_button_text" placeholder="Join and claim reward"></label><label>Custom success message<input type="text" maxlength="255" data-embed-setting="custom_success_message" placeholder="Thanks — your campaign response was submitted."></label><label>Allowed domains<textarea rows="3" data-embed-setting="allowed_domains" placeholder="example.com&#10;shop.example.com"></textarea></label><div class="mg-campaign-embed-actions"><button class="mg-btn mg-btn-primary" type="button" data-save-embed-settings>Save embed settings</button></div></div></section>' +
          '<aside class="mg-campaign-embed-card mg-campaign-embed-preview"><h3>Preview</h3><div class="mg-campaign-embed-preview-box" data-campaign-embed-preview><strong>Campaign preview</strong><p>Select a campaign embed to preview the public-safe copy.</p></div><div class="mg-campaign-embed-health" data-campaign-embed-health><span class="mg-eyebrow">Embed health</span><p>Load a campaign to review embed readiness.</p></div><div class="mg-campaign-embed-analytics" data-campaign-embed-analytics><span class="mg-eyebrow">Embed analytics</span><p>No embed events yet.</p></div><p class="mg-campaign-embed-note">The script does not use an iframe. It places semantic HTML into the merchant page so the website CSS can style fonts, inputs, spacing, and buttons.</p></aside>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);
    modal.addEventListener('click', function (event) {
      if (event.target && event.target.matches('[data-campaign-embed-close]')) closeModal();
      if (event.target && event.target.matches('[data-copy-campaign-embed]')) copyFrom('[data-campaign-embed-code]', 'Script embed code copied.');
      if (event.target && event.target.matches('[data-copy-campaign-iframe]')) copyFrom('[data-campaign-iframe-code]', 'Iframe fallback code copied.');
      if (event.target && event.target.matches('[data-save-embed-settings]')) saveSettings();
      if (event.target && event.target.matches('[data-refresh-embed-activity]')) loadRuntimeHealth(activeCampaign, true);
    });
    modal.addEventListener('change', function (event) {
      if (event.target && (event.target.name === 'mg_campaign_embed_mode' || event.target.matches('[data-campaign-embed-debug]'))) refreshCodes();
      if (event.target && event.target.matches('[data-embed-setting="default_layout"]')) syncModeFromSettings(event.target.value);
    });
    modal.addEventListener('input', function (event) { if (event.target && event.target.matches('[data-embed-setting]')) refreshCodes(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && modal && !modal.hasAttribute('hidden')) closeModal(); });
    return modal;
  }

  function closeModal() { if (!modal) return; modal.setAttribute('hidden', 'hidden'); modal.setAttribute('aria-hidden', 'true'); activeCampaign = null; activeSettings = null; runtimeHealth = null; }
  function syncModeFromSettings(mode) { var radio = modal ? modal.querySelector('input[name="mg_campaign_embed_mode"][value="' + String(mode || 'inline') + '"]') : null; if (radio) radio.checked = true; refreshCodes(); }
  function refreshCodes() { if (!modal || !activeCampaign) return; var mode = selectedMode(); var scriptNode = modal.querySelector('[data-campaign-embed-code]'); var iframeNode = modal.querySelector('[data-campaign-iframe-code]'); if (scriptNode) scriptNode.value = buildInlineCode(activeCampaign, mode); if (iframeNode) iframeNode.value = buildIframeCode(activeCampaign); }

  function renderPreview(campaign) {
    var preview = modal.querySelector('[data-campaign-embed-preview]'); if (!preview) return;
    var reward = campaign.reward_template || {}; var tools = campaign.public_tools || {};
    preview.innerHTML = '<strong>' + esc(campaign.form_headline || campaign.title || 'Campaign') + '</strong><p>' + esc(campaign.form_description || campaign.description || 'Embedded campaign form.') + '</p><p><b>' + esc(reward.title || 'Reward') + '</b></p><span>' + esc(campaignTypeLabel(campaign.campaign_type)) + ' · ' + esc(campaign.status || 'draft') + '</span>' + (tools.public_url ? '<p><a href="' + esc(tools.public_url) + '" target="_blank" rel="noopener">Public campaign page</a></p>' : '');
  }

  function healthItem(ok, label) { return '<li class="' + (ok ? 'is-ready' : 'is-warn') + '"><b></b><span>' + esc(label) + '</span></li>'; }
  function renderHealth(campaign) {
    var health = modal.querySelector('[data-campaign-embed-health]'); if (!health || !campaign) return;
    var tools = campaign.public_tools || {}, active = String(campaign.status || '') === 'active', hasRef = !!embedReference(campaign), hasPublicUrl = !!tools.public_url, enabled = !activeSettings || activeSettings.embed_enabled !== false;
    var runtimeReady = !runtimeHealth || runtimeHealth.migration_ready !== false;
    var sqlLabel = runtimeReady ? 'Embed SQL tables are ready.' : 'SQL migration required: ' + (runtimeHealth.sql_required || 'database/campaign_embed_settings_v2.sql');
    health.innerHTML = '<span class="mg-eyebrow">Embed health</span><ul>' + healthItem(runtimeReady, sqlLabel) + healthItem(enabled, enabled ? 'Embed is enabled.' : 'Embed is disabled.') + healthItem(active, active ? 'Campaign is active for public submissions.' : 'Campaign is not active; public submissions require activation.') + healthItem(hasRef, hasRef ? 'Embed reference is available.' : 'Embed reference is missing.') + healthItem(hasPublicUrl, hasPublicUrl ? 'Public page fallback is available.' : 'Public page fallback is unavailable.') + healthItem(true, 'Host CSS adoption script is available.') + '</ul>';
  }

  function renderAnalytics(stats, recentEvents) {
    var node = modal.querySelector('[data-campaign-embed-analytics]'); if (!node) return;
    stats = stats || {}; recentEvents = recentEvents || [];
    var recentHtml = recentEvents.length ? '<ul class="mg-campaign-embed-recent">' + recentEvents.slice(0, 4).map(function (event) { return '<li><b>' + esc(event.event_type || 'event') + '</b><span>' + esc(event.origin_host || 'unknown origin') + '</span><small>' + esc(event.created_at || '') + '</small></li>'; }).join('') + '</ul>' : '<p>No recent embed events yet.</p>';
    node.innerHTML = '<span class="mg-eyebrow">Embed analytics</span><div class="mg-campaign-embed-stat-grid"><strong>' + esc(stats.loaded || 0) + '<small>Loaded</small></strong><strong>' + esc(stats.opened || 0) + '<small>Opened</small></strong><strong>' + esc(stats.submitted || 0) + '<small>Submitted</small></strong><strong>' + esc(stats.invalid || 0) + '<small>Invalid</small></strong><strong>' + esc(stats.error || 0) + '<small>Errors</small></strong></div><p>Last origin: ' + esc(stats.last_origin_host || '—') + '</p>' + recentHtml + '<button class="mg-btn mg-btn-soft" type="button" data-refresh-embed-activity>Refresh activity</button>';
  }

  function applySettings(settings) {
    activeSettings = settings || { embed_enabled: true, default_layout: 'inline', allowed_domains: [] };
    var enabled = modal.querySelector('[data-embed-setting="embed_enabled"]');
    var layout = modal.querySelector('[data-embed-setting="default_layout"]');
    var button = modal.querySelector('[data-embed-setting="custom_button_text"]');
    var success = modal.querySelector('[data-embed-setting="custom_success_message"]');
    var domains = modal.querySelector('[data-embed-setting="allowed_domains"]');
    if (enabled) enabled.checked = activeSettings.embed_enabled !== false;
    if (layout) layout.value = activeSettings.default_layout || 'inline';
    if (button) button.value = activeSettings.custom_button_text || '';
    if (success) success.value = activeSettings.custom_success_message || '';
    if (domains) domains.value = (activeSettings.allowed_domains || []).join('\n');
    syncModeFromSettings(activeSettings.default_layout || 'inline');
  }

  function collectSettings() {
    var enabled = modal.querySelector('[data-embed-setting="embed_enabled"]');
    var layout = modal.querySelector('[data-embed-setting="default_layout"]');
    var button = modal.querySelector('[data-embed-setting="custom_button_text"]');
    var success = modal.querySelector('[data-embed-setting="custom_success_message"]');
    var domains = modal.querySelector('[data-embed-setting="allowed_domains"]');
    return { campaign_id: activeCampaign ? (activeCampaign.id || activeCampaign.slug || '') : '', embed_enabled: enabled && enabled.checked ? 1 : 0, default_layout: layout ? layout.value : 'inline', custom_button_text: button ? button.value.trim() : '', custom_success_message: success ? success.value.trim() : '', allowed_domains: domains ? domains.value.split(/[\r\n,]+/).map(function (v) { return v.trim(); }).filter(Boolean) : [] };
  }

  async function loadRuntimeHealth(campaign, userRefresh) {
    if (!campaign) return;
    try {
      var response = await Microgifter.get('/api/merchant/campaign-embed-runtime-health.php?campaign_id=' + encodeURIComponent(campaign.id || campaign.slug || ''));
      var data = response.data || response;
      runtimeHealth = data;
      renderAnalytics(data.stats || null, data.recent_events || []);
      renderHealth(campaign);
      if (data.migration_ready === false) status('SQL required before Embed Settings and analytics can fully run: ' + (data.sql_required || 'database/campaign_embed_settings_v2.sql'));
      else if (userRefresh) status('Embed activity refreshed.');
    } catch (error) {
      runtimeHealth = { migration_ready: false, sql_required: 'database/campaign_embed_settings_v2.sql' };
      renderAnalytics(null, []);
      renderHealth(campaign);
      status(error.message || 'Embed runtime health unavailable.');
    }
  }

  async function loadSettings(campaign) {
    try {
      var response = await Microgifter.get('/api/merchant/campaign-embed-settings.php?campaign_id=' + encodeURIComponent(campaign.id || campaign.slug || ''));
      var data = response.data || response;
      applySettings(data.settings || null);
      renderAnalytics(data.stats || null);
      renderHealth(campaign);
      refreshCodes();
    } catch (error) {
      applySettings(null);
      renderAnalytics(null);
      status('Embed settings unavailable. Import the Campaign Embed Settings v2 SQL migration if needed.');
    }
    await loadRuntimeHealth(campaign, false);
  }

  async function saveSettings() {
    if (!activeCampaign) return;
    status('Saving embed settings...');
    try {
      var response = await Microgifter.post('/api/merchant/campaign-embed-settings.php', collectSettings());
      var data = response.data || response;
      applySettings(data.settings || null);
      renderAnalytics(data.stats || null);
      renderHealth(activeCampaign);
      refreshCodes();
      await loadRuntimeHealth(activeCampaign, false);
      status(response.message || 'Campaign embed settings saved.');
    } catch (error) { status(error.message || 'Unable to save embed settings.'); await loadRuntimeHealth(activeCampaign, false); }
  }

  async function copyFrom(selector, successMessage) { var node = modal ? modal.querySelector(selector) : null; if (!node) return; try { node.select(); await navigator.clipboard.writeText(node.value); status(successMessage); } catch (error) { document.execCommand('copy'); status(successMessage || 'Copied.'); } }

  async function openEmbed(campaignId) {
    ensureModal(); modal.removeAttribute('hidden'); modal.setAttribute('aria-hidden', 'false'); status('Loading campaign embed tools...');
    var title = modal.querySelector('#mg-campaign-embed-title'), summary = modal.querySelector('[data-campaign-embed-summary]'), publicLink = modal.querySelector('[data-campaign-public-link]'), qaLink = modal.querySelector('[data-campaign-embed-qa-link]');
    if (title) title.textContent = 'Embed campaign';
    try {
      var response = await Microgifter.get('/api/merchant/campaign-detail.php?campaign_id=' + encodeURIComponent(campaignId));
      var data = response.data || response; activeCampaign = data.campaign || null; if (!activeCampaign) throw new Error('Campaign details unavailable.');
      if (title) title.textContent = 'Embed: ' + (activeCampaign.title || 'Campaign');
      if (summary) summary.textContent = activeCampaign.status === 'active' ? 'This active campaign can be embedded on a merchant website.' : 'This campaign is not active yet. The embed code is ready, but public submissions require active status.';
      if (publicLink) { publicLink.href = (activeCampaign.public_tools && activeCampaign.public_tools.public_url) || '#'; publicLink.hidden = !activeCampaign.public_tools || !activeCampaign.public_tools.public_url; }
      if (qaLink) qaLink.href = '/merchant-campaign-embed-qa.php?campaign=' + encodeURIComponent(embedReference(activeCampaign));
      renderPreview(activeCampaign); renderHealth(activeCampaign); refreshCodes(); await loadSettings(activeCampaign); status('Embed code ready.');
    } catch (error) { status(error.message || 'Unable to load embed code.'); var preview = modal.querySelector('[data-campaign-embed-preview]'); var health = modal.querySelector('[data-campaign-embed-health]'); if (preview) preview.innerHTML = '<div class="mg-empty-state"><p>Unable to load campaign embed details.</p></div>'; if (health) health.innerHTML = '<span class="mg-eyebrow">Embed health</span><p>Embed details could not be loaded.</p>'; }
  }

  function installButtons() {
    root.querySelectorAll('[data-campaign-row]').forEach(function (row) {
      if (row.querySelector('[data-campaign-embed-id]')) return;
      var id = row.getAttribute('data-campaign-row') || '', meta = row.querySelector('.mg-card-meta') || row;
      var button = document.createElement('button'); button.className = 'mg-btn mg-btn-ghost'; button.type = 'button'; button.setAttribute('data-campaign-embed-id', id); button.textContent = 'Embed'; meta.appendChild(button);
    });
  }

  root.addEventListener('click', function (event) { var button = event.target && event.target.closest ? event.target.closest('[data-campaign-embed-id]') : null; if (!button || !root.contains(button)) return; event.preventDefault(); openEmbed(button.getAttribute('data-campaign-embed-id')); });
  installButtons();
  var observer = new MutationObserver(installButtons);
  root.querySelectorAll('[data-stage12-campaign-list]').forEach(function (list) { observer.observe(list, { childList: true, subtree: true }); });
});

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  if (!root || !window.Microgifter) return;

  var modal = null;
  var activeCampaign = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }

  function absoluteUrl(path) {
    try { return new URL(path, window.location.origin).toString(); }
    catch (error) { return window.location.origin + String(path || ''); }
  }

  function status(message) {
    var node = modal ? modal.querySelector('[data-campaign-embed-status]') : null;
    if (node) node.textContent = message || '';
  }

  function campaignTypeLabel(value) {
    return String(value || 'campaign').replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); });
  }

  function embedReference(campaign) {
    if (!campaign) return '';
    if (campaign.campaign_type === 'qr_reward_drop') return campaign.id || campaign.slug || '';
    return campaign.slug || campaign.id || '';
  }

  function embedScriptUrl() {
    return absoluteUrl('/assets/js/microgifter-campaign-embed.js');
  }

  function buildInlineCode(campaign, displayMode) {
    var ref = embedReference(campaign);
    var mode = displayMode || 'inline';
    return '<div class="microgifter-campaign-embed" data-microgifter-campaign="' + esc(ref) + '" data-microgifter-display="' + esc(mode) + '"></div>\n<script async src="' + esc(embedScriptUrl()) + '"></script>';
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
        '<div class="mg-campaign-embed-head"><div><span class="mg-eyebrow">Website embed</span><h2 id="mg-campaign-embed-title">Embed campaign</h2><p data-campaign-embed-summary>Select the format, copy the code, and paste it into the merchant website. The script uses minimal structure so the host page CSS can control typography and buttons.</p></div><button class="mg-campaign-embed-close" type="button" aria-label="Close embed options" data-campaign-embed-close>&times;</button></div>' +
        '<div class="mg-campaign-embed-grid">' +
          '<section class="mg-campaign-embed-card"><h3>Copy website code</h3><p>Use the script embed for the best fit. It renders inline on the host page and inherits the page font, colors, and form styles where possible.</p><div class="mg-campaign-embed-options" aria-label="Embed display mode"><label><input type="radio" name="mg_campaign_embed_mode" value="inline" checked> Inline form card</label><label><input type="radio" name="mg_campaign_embed_mode" value="button"> Button / popup launcher</label></div><div class="mg-campaign-embed-code-row"><label>Script embed<textarea readonly data-campaign-embed-code></textarea></label><label>Iframe fallback<textarea readonly data-campaign-iframe-code></textarea></label></div><div class="mg-campaign-embed-actions"><button class="mg-btn mg-btn-primary" type="button" data-copy-campaign-embed>Copy script code</button><button class="mg-btn mg-btn-soft" type="button" data-copy-campaign-iframe>Copy iframe fallback</button><a class="mg-btn mg-btn-ghost" href="#" target="_blank" rel="noopener" data-campaign-public-link>Open public page</a></div><p class="mg-campaign-embed-status" data-campaign-embed-status>Ready.</p></section>' +
          '<aside class="mg-campaign-embed-card mg-campaign-embed-preview"><h3>Preview</h3><div class="mg-campaign-embed-preview-box" data-campaign-embed-preview><strong>Campaign preview</strong><p>Select a campaign embed to preview the public-safe copy.</p></div><p class="mg-campaign-embed-note">The script does not use an iframe. It places semantic HTML into the merchant page so the website CSS can style fonts, inputs, spacing, and buttons. A small fallback style only protects the structure.</p></aside>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);
    modal.addEventListener('click', function (event) {
      if (event.target && event.target.matches('[data-campaign-embed-close]')) closeModal();
      if (event.target && event.target.matches('[data-copy-campaign-embed]')) copyFrom('[data-campaign-embed-code]', 'Script embed code copied.');
      if (event.target && event.target.matches('[data-copy-campaign-iframe]')) copyFrom('[data-campaign-iframe-code]', 'Iframe fallback code copied.');
    });
    modal.addEventListener('change', function (event) {
      if (event.target && event.target.name === 'mg_campaign_embed_mode') refreshCodes();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal && !modal.hasAttribute('hidden')) closeModal();
    });
    return modal;
  }

  function closeModal() {
    if (!modal) return;
    modal.setAttribute('hidden', 'hidden');
    modal.setAttribute('aria-hidden', 'true');
    activeCampaign = null;
  }

  function refreshCodes() {
    if (!modal || !activeCampaign) return;
    var selected = modal.querySelector('input[name="mg_campaign_embed_mode"]:checked');
    var mode = selected ? selected.value : 'inline';
    var scriptNode = modal.querySelector('[data-campaign-embed-code]');
    var iframeNode = modal.querySelector('[data-campaign-iframe-code]');
    if (scriptNode) scriptNode.value = buildInlineCode(activeCampaign, mode);
    if (iframeNode) iframeNode.value = buildIframeCode(activeCampaign);
  }

  function renderPreview(campaign) {
    var preview = modal.querySelector('[data-campaign-embed-preview]');
    if (!preview) return;
    var reward = campaign.reward_template || {};
    var tools = campaign.public_tools || {};
    preview.innerHTML = '<strong>' + esc(campaign.form_headline || campaign.title || 'Campaign') + '</strong>' +
      '<p>' + esc(campaign.form_description || campaign.description || 'Embedded campaign form.') + '</p>' +
      '<p><b>' + esc(reward.title || 'Reward') + '</b></p>' +
      '<span>' + esc(campaignTypeLabel(campaign.campaign_type)) + ' · ' + esc(campaign.status || 'draft') + '</span>' +
      (tools.public_url ? '<p><a href="' + esc(tools.public_url) + '" target="_blank" rel="noopener">Public campaign page</a></p>' : '');
  }

  async function copyFrom(selector, successMessage) {
    var node = modal ? modal.querySelector(selector) : null;
    if (!node) return;
    try {
      node.select();
      await navigator.clipboard.writeText(node.value);
      status(successMessage);
    } catch (error) {
      document.execCommand('copy');
      status(successMessage || 'Copied.');
    }
  }

  async function openEmbed(campaignId) {
    ensureModal();
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
    status('Loading campaign embed tools...');
    var title = modal.querySelector('#mg-campaign-embed-title');
    var summary = modal.querySelector('[data-campaign-embed-summary]');
    var publicLink = modal.querySelector('[data-campaign-public-link]');
    if (title) title.textContent = 'Embed campaign';
    try {
      var response = await Microgifter.get('/api/merchant/campaign-detail.php?campaign_id=' + encodeURIComponent(campaignId));
      var data = response.data || response;
      activeCampaign = data.campaign || null;
      if (!activeCampaign) throw new Error('Campaign details unavailable.');
      if (title) title.textContent = 'Embed: ' + (activeCampaign.title || 'Campaign');
      if (summary) summary.textContent = (activeCampaign.status === 'active' ? 'This active campaign can be embedded on a merchant website.' : 'This campaign is not active yet. The embed code is ready, but public submissions require active status.');
      if (publicLink) {
        publicLink.href = (activeCampaign.public_tools && activeCampaign.public_tools.public_url) || '#';
        publicLink.hidden = !activeCampaign.public_tools || !activeCampaign.public_tools.public_url;
      }
      renderPreview(activeCampaign);
      refreshCodes();
      status('Embed code ready.');
    } catch (error) {
      status(error.message || 'Unable to load embed code.');
      var preview = modal.querySelector('[data-campaign-embed-preview]');
      if (preview) preview.innerHTML = '<div class="mg-empty-state"><p>Unable to load campaign embed details.</p></div>';
    }
  }

  function installButtons() {
    root.querySelectorAll('[data-campaign-row]').forEach(function (row) {
      if (row.querySelector('[data-campaign-embed-id]')) return;
      var id = row.getAttribute('data-campaign-row') || '';
      var meta = row.querySelector('.mg-card-meta') || row;
      var button = document.createElement('button');
      button.className = 'mg-btn mg-btn-ghost';
      button.type = 'button';
      button.setAttribute('data-campaign-embed-id', id);
      button.textContent = 'Embed';
      meta.appendChild(button);
    });
  }

  root.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-campaign-embed-id]') : null;
    if (!button || !root.contains(button)) return;
    event.preventDefault();
    openEmbed(button.getAttribute('data-campaign-embed-id'));
  });

  installButtons();
  var observer = new MutationObserver(installButtons);
  root.querySelectorAll('[data-stage12-campaign-list]').forEach(function (list) {
    observer.observe(list, { childList: true, subtree: true });
  });
});

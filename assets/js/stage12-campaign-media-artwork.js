document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  var form = root && root.querySelector('[data-stage12-campaign-builder]');
  if (!root || !form || !window.Microgifter) return;

  var configs = {
    watch_video_reward: {
      label: 'Watch campaign artwork',
      hint: 'Upload a JPG, PNG, or WebP poster/thumbnail for the public Watch page.',
      assetField: 'watch_media_image_asset_id',
      urlField: 'watch_media_image_url',
      status: 'watch-media-image-upload-status',
      input: 'watch-media-image-upload-input',
      button: 'watch-media-image-upload-button'
    },
    listen_music_reward: {
      label: 'Listen campaign artwork',
      hint: 'Upload a JPG, PNG, or WebP cover image for the public Listen page.',
      assetField: 'listen_media_image_asset_id',
      urlField: 'listen_media_image_url',
      status: 'listen-media-image-upload-status',
      input: 'listen-media-image-upload-input',
      button: 'listen-media-image-upload-button'
    },
    instant_win_reward: {
      label: 'Scratch-card artwork',
      hint: 'Upload the image used as the scratch-off layer on the public Instant Win page. Spin Wheel mode ignores this artwork.',
      assetField: 'instant_win_scratch_image_asset_id',
      urlField: 'instant_win_scratch_image_url',
      status: 'instant-win-scratch-image-upload-status',
      input: 'instant-win-scratch-image-upload-input',
      button: 'instant-win-scratch-image-upload-button'
    },
    stamp_card_reward: {
      label: 'Loyalty card campaign image',
      hint: 'Upload the main image shown when customers save this Stamp Card on the Loyalty Cards page.',
      assetField: 'stamp_card_image_asset_id',
      urlField: 'stamp_card_image_url',
      status: 'stamp-card-image-upload-status',
      input: 'stamp-card-image-upload-input',
      button: 'stamp-card-image-upload-button'
    }
  };

  function qs(selector, context) { return (context || root).querySelector(selector); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function setField(name, value) { if (form.elements[name]) form.elements[name].value = value == null ? '' : String(value); }
  function getField(name) { return form.elements[name] ? String(form.elements[name].value || '').trim() : ''; }
  function setStatus(cfg, message, type) { var node = qs('[data-' + cfg.status + ']'); if (!node) return; node.textContent = message || ''; node.classList.toggle('is-error', type === 'error'); node.classList.toggle('is-success', type === 'success'); }
  function setPreview(cfg, url) { var node = qs('[data-' + cfg.status + ']'); if (!node) return; var preview = node.parentNode && node.parentNode.querySelector('[data-campaign-media-art-preview]'); if (!preview) return; if (url) preview.innerHTML = '<img src="' + esc(url) + '" alt="Campaign artwork preview">'; else preview.innerHTML = '<span>No artwork uploaded yet.</span>'; }

  function injectFor(type) {
    var cfg = configs[type];
    var card = qs('[data-campaign-type-fields="' + type + '"]');
    if (!cfg || !card || card.querySelector('[data-campaign-media-artwork="' + type + '"]')) return;
    var wrap = document.createElement('div');
    wrap.className = 'mg-campaign-rule-card mg-campaign-media-artwork-card';
    wrap.setAttribute('data-campaign-media-artwork', type);
    wrap.innerHTML = '<span class="mg-eyebrow">Media artwork</span><h3>' + esc(cfg.label) + '</h3><p>' + esc(cfg.hint) + '</p><input type="hidden" name="' + esc(cfg.assetField) + '"><input type="hidden" name="' + esc(cfg.urlField) + '"><div class="mg-campaign-media-art-preview" data-campaign-media-art-preview><span>No artwork uploaded yet.</span></div><label>Upload image<input type="file" accept="image/jpeg,image/png,image/webp" data-' + esc(cfg.input) + '></label><button class="mg-btn mg-btn-soft" type="button" data-' + esc(cfg.button) + '>Upload image</button><div class="mg-form-status" data-' + esc(cfg.status) + '>Upload JPG, PNG, or WebP up to 8MB.</div>';
    var firstGrid = card.querySelector('.mg-grid-2');
    if (firstGrid && firstGrid.parentNode) firstGrid.parentNode.insertBefore(wrap, firstGrid);
    else card.appendChild(wrap);
    var button = qs('[data-' + cfg.button + ']', wrap);
    var input = qs('[data-' + cfg.input + ']', wrap);
    if (button) button.addEventListener('click', function () { upload(cfg, input, button).catch(function (error) { setStatus(cfg, error.message || 'Unable to upload image.', 'error'); }); });
    if (input) input.addEventListener('change', function () { setStatus(cfg, input.files && input.files.length ? 'Ready to upload ' + input.files[0].name + '.' : 'Upload JPG, PNG, or WebP up to 8MB.'); });
  }

  async function upload(cfg, input, button) {
    if (!input || !input.files || !input.files.length) { setStatus(cfg, 'Choose an image first.', 'error'); return; }
    var file = input.files[0];
    var allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (file.type && allowed.indexOf(file.type) === -1) { setStatus(cfg, 'Use JPG, PNG, or WebP images.', 'error'); return; }
    if (file.size > 8388608) { setStatus(cfg, 'Image must be 8MB or smaller.', 'error'); return; }
    var body = new FormData();
    body.append('csrf_token', Microgifter.getCsrfToken ? Microgifter.getCsrfToken() : '');
    body.append('image', file);
    if (button) button.disabled = true;
    setStatus(cfg, 'Uploading image…');
    try {
      var res = await Microgifter.post('/api/merchant/campaign-media-image-upload.php', body);
      var data = res.data || res;
      if (!data.asset_id || !data.url) throw new Error('Upload succeeded but no image URL was returned.');
      setField(cfg.assetField, data.asset_id);
      setField(cfg.urlField, data.url);
      setPreview(cfg, data.url);
      setStatus(cfg, 'Image uploaded: ' + (data.original_filename || 'campaign artwork'), 'success');
      var campaignStatus = qs('[data-stage12-campaign-status]');
      if (Microgifter.setStatus) Microgifter.setStatus(campaignStatus, 'Campaign artwork ready. Save the campaign to persist it.', 'success');
    } catch (error) {
      setStatus(cfg, error.message || 'Unable to upload image.', 'error');
    } finally {
      if (button) button.disabled = false;
    }
  }

  var lastCampaignId = '';
  async function syncExistingArtwork() {
    var id = getField('campaign_id');
    if (!id || id === lastCampaignId) return;
    lastCampaignId = id;
    try {
      var response = await Microgifter.get('/api/merchant/campaigns.php');
      var campaigns = (response.data || response).campaigns || [];
      var campaign = campaigns.find(function (item) { return String(item.id) === String(id); });
      if (!campaign || !campaign.rules) return;
      var type = String(campaign.campaign_type || '');
      var cfg = configs[type];
      if (!cfg) return;
      injectFor(type);
      setField(cfg.assetField, campaign.rules.media_image_asset_id || '');
      setField(cfg.urlField, campaign.rules.media_image_url || '');
      setPreview(cfg, campaign.rules.media_image_url || '');
    } catch (error) {}
  }

  function ensure() {
    injectFor('watch_video_reward');
    injectFor('listen_music_reward');
    injectFor('instant_win_reward');
    injectFor('stamp_card_reward');
    syncExistingArtwork();
  }

  ensure();
  var attempts = 0;
  var timer = window.setInterval(function () {
    ensure();
    attempts += 1;
    if (attempts > 30) window.clearInterval(timer);
  }, 400);
  form.addEventListener('change', ensure);
  root.addEventListener('click', function () { window.setTimeout(ensure, 120); });
});
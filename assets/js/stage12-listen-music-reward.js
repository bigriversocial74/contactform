document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  if (!root || !window.Microgifter) return;

  var form = root.querySelector('[data-stage12-campaign-builder]');
  if (!form) return;

  var hydratedCampaignId = '';
  var hydratedCampaignSnapshot = null;

  function qs(selector, context) {
    return (context || root).querySelector(selector);
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }

  function setField(name, value, force) {
    if (!form.elements[name]) return;
    var element = form.elements[name];
    if (force || !String(element.value || '').trim()) {
      element.value = value == null ? '' : String(value);
      element.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function getField(name) {
    return form.elements[name] ? String(form.elements[name].value || '').trim() : '';
  }

  function firstValue(source, keys) {
    source = source || {};
    for (var i = 0; i < keys.length; i += 1) {
      if (!Object.prototype.hasOwnProperty.call(source, keys[i])) continue;
      var value = source[keys[i]];
      if (value == null) continue;
      value = String(value).trim();
      if (value !== '') return value;
    }
    return '';
  }

  function status(message, type) {
    var node = qs('[data-listen-upload-status]');
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-error', type === 'error');
    node.classList.toggle('is-success', type === 'success');
  }

  function campaignStatus(message, type) {
    var node = qs('[data-stage12-campaign-status]');
    if (window.Microgifter.setStatus) {
      Microgifter.setStatus(node, message, type);
    } else if (node) {
      node.textContent = message || '';
    }
  }

  function levelBlock(number, percent) {
    return '<div class="mg-media-static-level-card" data-listen-static-level="' + number + '"><div class="mg-media-static-level-head"><strong>Fallback reward level ' + number + '</strong><span>' + percent + '%</span></div><div class="mg-grid-2"><label>Milestone ' + number + ' %<input name="listen_milestone_' + number + '_percent" type="number" min="1" max="100" value="' + percent + '"></label><label>Milestone ' + number + ' gift<select name="listen_milestone_' + number + '_reward_template_id" data-listen-reward-template-select><option value="">Use attached primary reward</option></select></label></div></div>';
  }

  function inject() {
    if (qs('[data-campaign-type-fields="listen_music_reward"]')) return;

    var card = document.createElement('div');
    card.className = 'mg-campaign-rule-card';
    card.setAttribute('data-campaign-type-fields', 'listen_music_reward');
    card.hidden = true;
    card.innerHTML = '<span class="mg-eyebrow">Listen Music Reward</span><h3>Reward customers for listening to a Spotify song or uploaded audio.</h3><p>Spotify links are embedded as listen-intent rewards. Uploaded audio uses the native player for true percent-listened milestone gifts.</p><div class="mg-grid-2"><label>Music source<select name="listen_music_provider" data-listen-provider><option value="spotify">Spotify song link</option><option value="uploaded">Uploaded audio</option></select></label><label>Required listen percent<input name="listen_required_percent" type="number" min="1" max="100" value="80"></label></div><div class="mg-grid-2"><label>Track title<input name="listen_track_title" placeholder="Song title"></label><label>Artist name<input name="listen_artist_name" placeholder="Artist"></label></div><label data-listen-spotify-row>Spotify song link<input name="listen_spotify_url" placeholder="https://open.spotify.com/track/..."></label><div class="mg-listen-upload-box" data-listen-upload-row hidden><input type="hidden" name="listen_audio_upload_asset_id"><input type="hidden" name="listen_audio_uploaded_url"><div class="mg-listen-current-audio" data-listen-current-audio hidden></div><label>Upload MP3/audio file<input type="file" accept="audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a" data-listen-audio-upload-input></label><button class="mg-btn mg-btn-soft" type="button" data-listen-audio-upload-button>Upload audio</button><div class="mg-form-status" data-listen-upload-status>Upload MP3, WAV, OGG, or M4A up to 50MB.</div></div><div class="mg-media-static-levels" aria-label="Fallback listen milestone fields"><span class="mg-eyebrow">Fallback milestone fields</span><p>Dynamic reward levels below are the primary setup. These fallback fields stay available for older campaign records.</p>' + levelBlock(1, 25) + levelBlock(2, 50) + levelBlock(3, 80) + '</div>';

    var before = qs('[data-campaign-type-fields="customer_refund"]');
    if (before && before.parentNode) before.parentNode.insertBefore(card, before);
    else qs('[data-stage12-campaign-status]', form).insertAdjacentElement('beforebegin', card);

    populateRewards();
    toggle();
  }

  function populateRewards() {
    var primary = form.elements.reward_template_id;
    if (!primary) return;
    var html = '<option value="">Use attached primary reward</option>';
    Array.prototype.slice.call(primary.options).forEach(function (option) {
      if (option.value) html += '<option value="' + esc(option.value) + '">' + esc(option.textContent) + '</option>';
    });
    root.querySelectorAll('[data-listen-reward-template-select]').forEach(function (select) {
      var value = select.value;
      select.innerHTML = html;
      if (value) select.value = value;
    });
  }

  function currentAudioLabel(url, snapshot) {
    var explicit = firstValue(snapshot || {}, ['uploaded_original_filename', 'original_filename', 'audio_filename', 'filename']);
    if (explicit) return explicit;
    try {
      var parsed = new URL(url, window.location.origin);
      var name = parsed.pathname.split('/').filter(Boolean).pop() || '';
      return name ? decodeURIComponent(name) : 'Uploaded audio file';
    } catch (error) {
      return 'Uploaded audio file';
    }
  }

  function setSavedAudioNotice(snapshot) {
    var node = qs('[data-listen-current-audio]');
    if (!node) return;

    var asset = getField('listen_audio_upload_asset_id');
    var url = getField('listen_audio_uploaded_url');
    var isUploaded = getField('listen_music_provider') === 'uploaded';

    if (isUploaded && (asset || url)) {
      var label = currentAudioLabel(url, snapshot || hydratedCampaignSnapshot);
      node.hidden = false;
      node.innerHTML = '<strong>Saved audio loaded</strong><span>' + esc(label) + '</span><small>You can edit campaign copy, rewards, dates, and limits without uploading the audio again.</small>' + (url ? '<a href="' + esc(url) + '" target="_blank" rel="noopener">Open current audio</a>' : '');
      status('Saved uploaded audio is attached. Upload only if you want to replace it.', 'success');
    } else {
      node.hidden = true;
      node.innerHTML = '';
      if (isUploaded) status('Upload MP3, WAV, OGG, or M4A up to 50MB.');
    }
  }

  function toggle() {
    var provider = form.elements.listen_music_provider ? form.elements.listen_music_provider.value : 'spotify';
    var spotifyRow = qs('[data-listen-spotify-row]');
    var uploadRow = qs('[data-listen-upload-row]');
    if (spotifyRow) spotifyRow.hidden = provider === 'uploaded';
    if (uploadRow) uploadRow.hidden = provider !== 'uploaded';
    setSavedAudioNotice();
  }

  function normalizeRules(rawRules) {
    var rules = rawRules || {};
    var uploadedAssetId = firstValue(rules, ['uploaded_asset_id', 'uploaded_audio_asset_id', 'listen_audio_upload_asset_id', 'audio_asset_id', 'asset_id']);
    var uploadedUrl = firstValue(rules, ['uploaded_audio_url', 'audio_url', 'listen_audio_uploaded_url', 'uploaded_url']);
    var spotifyValue = firstValue(rules, ['spotify_url', 'spotify_track_url', 'listen_spotify_url', 'spotify_track_id']);
    var provider = String(firstValue(rules, ['audio_provider', 'listen_music_provider', 'provider', 'audio_source', 'source']) || '').toLowerCase();

    if (provider !== 'uploaded' && provider !== 'spotify') provider = '';
    if (uploadedAssetId || uploadedUrl) provider = 'uploaded';
    if (!provider) provider = spotifyValue ? 'spotify' : 'uploaded';

    return {
      provider: provider,
      spotifyValue: provider === 'spotify' ? spotifyValue : '',
      uploadedAssetId: uploadedAssetId,
      uploadedUrl: uploadedUrl,
      requiredPercent: firstValue(rules, ['required_percent', 'listen_required_percent']) || 80,
      trackTitle: firstValue(rules, ['track_title', 'listen_track_title', 'title']),
      artistName: firstValue(rules, ['artist_name', 'listen_artist_name', 'artist']),
      milestones: Array.isArray(rules.milestones) ? rules.milestones : [],
      snapshot: rules
    };
  }

  function applyRules(rules) {
    rules = rules || {};
    if (!(rules.campaign_type === 'listen_music_reward' || String(rules.mode || '').indexOf('audio') !== -1 || firstValue(rules, ['uploaded_audio_url', 'uploaded_asset_id', 'audio_provider']))) return;

    var normalized = normalizeRules(rules);
    hydratedCampaignSnapshot = normalized.snapshot;

    setField('listen_music_provider', normalized.provider, true);
    setField('listen_spotify_url', normalized.spotifyValue, true);
    setField('listen_audio_upload_asset_id', normalized.uploadedAssetId, true);
    setField('listen_audio_uploaded_url', normalized.uploadedUrl, true);
    setField('listen_required_percent', normalized.requiredPercent, true);
    setField('listen_track_title', normalized.trackTitle, true);
    setField('listen_artist_name', normalized.artistName, true);

    normalized.milestones.slice(0, 3).forEach(function (milestone, index) {
      var number = index + 1;
      setField('listen_milestone_' + number + '_percent', milestone.percent || '', true);
      setField('listen_milestone_' + number + '_reward_template_id', milestone.reward_template_id || '', true);
    });

    toggle();
    setSavedAudioNotice(normalized.snapshot);
  }

  async function hydrateExisting(force) {
    var id = getField('campaign_id');
    if (!id || (!force && id === hydratedCampaignId) || !window.Microgifter) return;
    hydratedCampaignId = id;

    try {
      var response = await Microgifter.get('/api/merchant/campaigns.php');
      var campaigns = (response.data || response).campaigns || [];
      var campaign = campaigns.find(function (item) { return String(item.id) === String(id); });
      if (!campaign || campaign.campaign_type !== 'listen_music_reward') return;
      applyRules(campaign.rules || {});
    } catch (error) {}
  }

  async function upload() {
    var input = qs('[data-listen-audio-upload-input]');
    var button = qs('[data-listen-audio-upload-button]');
    if (!input || !input.files || !input.files.length) {
      status('Choose an audio file first.', 'error');
      return;
    }

    var file = input.files[0];
    if (file.size > 52428800) {
      status('Audio must be 50MB or smaller.', 'error');
      return;
    }

    var body = new FormData();
    body.append('csrf_token', Microgifter.getCsrfToken ? Microgifter.getCsrfToken() : '');
    body.append('audio', file);

    if (button) button.disabled = true;
    status('Uploading audio…');

    try {
      var result = await Microgifter.post('/api/merchant/listen-audio-upload.php', body);
      var data = result.data || result;
      if (!data.asset_id || !data.url) throw new Error('Upload succeeded but no audio URL was returned.');

      setField('listen_audio_upload_asset_id', data.asset_id, true);
      setField('listen_audio_uploaded_url', data.url, true);
      setField('listen_music_provider', 'uploaded', true);
      hydratedCampaignSnapshot = { original_filename: data.original_filename || '', uploaded_audio_url: data.url, uploaded_asset_id: data.asset_id };
      toggle();
      setSavedAudioNotice(hydratedCampaignSnapshot);
      status('Audio uploaded: ' + (data.original_filename || 'uploaded audio'), 'success');
      campaignStatus('Uploaded audio ready. Save the campaign to persist it.', 'success');
    } catch (error) {
      status(error.message || 'Unable to upload audio.', 'error');
    } finally {
      if (button) button.disabled = false;
    }
  }

  inject();

  form.addEventListener('change', function (event) {
    if (event.target && event.target.name === 'listen_music_provider') toggle();
    if (event.target && event.target.name === 'reward_template_id') populateRewards();
    setTimeout(function () { hydrateExisting(false); }, 80);
  });

  form.addEventListener('submit', function () {
    if (getField('campaign_type') === 'listen_music_reward' && getField('listen_music_provider') === 'uploaded' && getField('listen_audio_upload_asset_id')) {
      setSavedAudioNotice(hydratedCampaignSnapshot);
    }
  }, true);

  var button = qs('[data-listen-audio-upload-button]');
  if (button) button.addEventListener('click', function () { upload(); });

  var input = qs('[data-listen-audio-upload-input]');
  if (input) {
    input.addEventListener('change', function () {
      status(input.files && input.files.length ? 'Ready to upload ' + input.files[0].name + '.' : 'Upload MP3, WAV, OGG, or M4A up to 50MB.');
    });
  }

  root.addEventListener('click', function (event) {
    if (event.target && event.target.closest('[data-campaign-edit-id]')) {
      hydratedCampaignId = '';
      setTimeout(function () { hydrateExisting(true); }, 180);
      setTimeout(function () { hydrateExisting(true); }, 520);
    }
  });

  var primary = form.elements.reward_template_id;
  if (primary && window.MutationObserver) {
    new MutationObserver(populateRewards).observe(primary, { childList: true });
  }

  var attempts = 0;
  var timer = setInterval(function () {
    hydrateExisting(false);
    attempts += 1;
    if (attempts > 20) clearInterval(timer);
  }, 300);
});

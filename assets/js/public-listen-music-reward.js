(function () {
  'use strict';
  var root = document.querySelector('[data-listen-music-reward]');
  if (!root) return;

  var forms = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-form]'));
  var results = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-result]'));
  var provider = root.getAttribute('data-audio-provider') || 'spotify';
  var campaignId = root.getAttribute('data-campaign-id') || '';
  var spotifyTrackId = root.getAttribute('data-spotify-track-id') || '';
  var uploadedUrl = root.getAttribute('data-uploaded-audio-url') || '';
  var uploadedAssetId = root.getAttribute('data-uploaded-asset-id') || '';
  var player = root.querySelector('[data-listen-uploaded-player]');
  var waveform = root.querySelector('.mg-rl-wave');
  var statusNodes = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-status]'));
  var historyLists = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-history]'));
  var rewardHistoryLists = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-issue-history]'));
  var confirmButton = root.querySelector('[data-listen-spotify-confirm]');
  var customer = {};
  var timer = null;
  var maxPercent = 0;
  var lastPost = 0;
  var started = false;
  var blocked = false;
  var audioBound = false;
  var eligibilityCache = {};
  var waveformState = { clipRect: null, progressLine: null, width: 1000, height: 100 };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }
  function timeLabel() {
    try { return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); } catch (error) { return ''; }
  }
  function seededValue(seed) {
    var value = seed || 2147483647;
    return function () {
      value = (value * 48271) % 2147483647;
      return (value & 2147483647) / 2147483647;
    };
  }
  function seedFromString(text) {
    var seed = 0;
    String(text || 'microgifter-waveform').split('').forEach(function (char) { seed = ((seed << 5) - seed + char.charCodeAt(0)) | 0; });
    return Math.abs(seed) || 81421;
  }
  function demoWaveform(count) {
    var random = seededValue(seedFromString(campaignId + spotifyTrackId + uploadedUrl));
    var values = [];
    for (var i = 0; i < count; i += 1) {
      var t = i / Math.max(1, count - 1);
      var intro = Math.min(1, t * 4.2);
      var tail = Math.max(0.26, 1 - Math.max(0, t - 0.78) * 1.3);
      var envelope = (0.20 + Math.sin(Math.PI * t) * 0.42) * intro * tail;
      var transient = Math.pow(Math.abs(Math.sin(i * 0.94 + random())), 1.6) * 0.28;
      var harmonic = Math.abs(Math.sin(i * 2.77 + 0.6)) * 0.18 + Math.abs(Math.sin(i * 5.41)) * 0.08;
      var jitter = (random() - 0.5) * 0.18;
      values.push(Math.max(0.06, Math.min(1, envelope + transient + harmonic + jitter)));
    }
    return smoothWave(values, 1);
  }
  function smoothWave(values, passes) {
    var output = values.slice();
    for (var pass = 0; pass < passes; pass += 1) {
      output = output.map(function (value, index) {
        var left = output[Math.max(0, index - 1)];
        var right = output[Math.min(output.length - 1, index + 1)];
        return value * 0.58 + left * 0.21 + right * 0.21;
      });
    }
    return output;
  }
  function waveformFromBuffer(buffer, count) {
    var channels = [];
    for (var channelIndex = 0; channelIndex < Math.min(2, buffer.numberOfChannels); channelIndex += 1) channels.push(buffer.getChannelData(channelIndex));
    if (!channels.length) return demoWaveform(count);
    var length = channels[0].length;
    var block = Math.max(1, Math.floor(length / count));
    var values = [];
    var max = 0;
    for (var i = 0; i < count; i += 1) {
      var start = i * block;
      var end = Math.min(length, start + block);
      var peak = 0;
      var sum = 0;
      var samples = 0;
      var stride = Math.max(1, Math.floor(block / 420));
      for (var j = start; j < end; j += stride) {
        var sample = 0;
        channels.forEach(function (data) { sample += Math.abs(data[j] || 0); });
        sample = sample / channels.length;
        peak = Math.max(peak, sample);
        sum += sample * sample;
        samples += 1;
      }
      var rms = Math.sqrt(sum / Math.max(1, samples));
      var value = Math.min(1, peak * 0.62 + rms * 1.9);
      values.push(value);
      max = Math.max(max, value);
    }
    max = max || 1;
    return smoothWave(values.map(function (value) { return Math.max(0.045, Math.min(1, value / max)); }), 1);
  }
  function wavePath(values, width, height) {
    var center = height / 2;
    var minAmp = 5;
    var maxAmp = height * 0.48;
    var step = width / Math.max(1, values.length - 1);
    var top = [];
    var bottom = [];
    values.forEach(function (value, index) {
      var x = Math.round(index * step * 100) / 100;
      var amp = minAmp + Math.max(0, Math.min(1, value)) * maxAmp;
      var shape = 0.72 + Math.abs(Math.sin(index * 0.41)) * 0.28;
      top.push([x, Math.round((center - amp * shape) * 100) / 100]);
      bottom.push([x, Math.round((center + amp * shape) * 100) / 100]);
    });
    var d = 'M ' + top[0][0] + ' ' + top[0][1];
    for (var i = 1; i < top.length; i += 1) d += ' L ' + top[i][0] + ' ' + top[i][1];
    for (var j = bottom.length - 1; j >= 0; j -= 1) d += ' L ' + bottom[j][0] + ' ' + bottom[j][1];
    return d + ' Z';
  }
  function renderWaveform(values, activePercent) {
    if (!waveform) return;
    var width = 1000;
    var height = 100;
    var clipId = 'mg-rl-wave-progress-clip-' + Math.abs(seedFromString(campaignId + uploadedUrl + Date.now()));
    var path = wavePath(values, width, height);
    waveform.classList.add('is-svg-wave');
    waveform.innerHTML = '<svg class="mg-rl-wave-svg" viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none" role="img" aria-label="Audio waveform">'
      + '<defs><linearGradient id="mg-rl-wave-active-gradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#35a2ff"/><stop offset="1" stop-color="#1260ff"/></linearGradient><clipPath id="' + clipId + '"><rect data-wave-clip x="0" y="0" width="0" height="' + height + '"></rect></clipPath></defs>'
      + '<line class="mg-rl-wave-centerline" x1="0" y1="50" x2="1000" y2="50"></line>'
      + '<path class="mg-rl-wave-idle" d="' + path + '"></path>'
      + '<path class="mg-rl-wave-active" clip-path="url(#' + clipId + ')" d="' + path + '"></path>'
      + '<line class="mg-rl-wave-progress-line" data-wave-line x1="0" y1="13" x2="0" y2="87"></line>'
      + '</svg>';
    waveformState.clipRect = waveform.querySelector('[data-wave-clip]');
    waveformState.progressLine = waveform.querySelector('[data-wave-line]');
    waveformState.width = width;
    waveformState.height = height;
    setWaveProgress(activePercent == null ? 42 : activePercent);
  }
  function setWaveProgress(percent) {
    if (!waveformState.clipRect) return;
    var active = Math.max(0, Math.min(100, Number(percent || 0)));
    var x = Math.round((active / 100) * waveformState.width * 100) / 100;
    waveformState.clipRect.setAttribute('width', String(x));
    if (waveformState.progressLine) {
      waveformState.progressLine.setAttribute('x1', String(x));
      waveformState.progressLine.setAttribute('x2', String(x));
      waveformState.progressLine.style.opacity = active > 0 && active < 100 ? '.86' : '0';
    }
  }
  async function initWaveform() {
    if (!waveform) return;
    renderWaveform(demoWaveform(240), provider === 'uploaded' ? 0 : 42);
    if (provider !== 'uploaded' || !uploadedUrl || !window.fetch || !(window.AudioContext || window.webkitAudioContext)) return;
    try {
      var response = await fetch(uploadedUrl, { credentials: 'same-origin', cache: 'force-cache' });
      if (!response.ok) throw new Error('Unable to load audio waveform.');
      var buffer = await response.arrayBuffer();
      var AudioContextClass = window.AudioContext || window.webkitAudioContext;
      var context = new AudioContextClass();
      var decoded = await context.decodeAudioData(buffer.slice(0));
      renderWaveform(waveformFromBuffer(decoded, 260), 0);
      if (context.close) context.close();
    } catch (error) {
      renderWaveform(demoWaveform(240), provider === 'uploaded' ? 0 : 42);
    }
  }
  function setSingleLine(lists, message) {
    lists.forEach(function (list) {
      if (!list) return;
      list.innerHTML = '<li><span>' + esc(timeLabel()) + '</span><strong>' + esc(message || '') + '</strong></li>';
    });
  }
  function appendReward(message) {
    rewardHistoryLists.forEach(function (list) {
      if (!list || !message) return;
      if (list.children.length === 1 && /no rewards/i.test(list.children[0].textContent || '')) list.innerHTML = '';
      var row = document.createElement('li');
      row.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>';
      list.insertBefore(row, list.firstChild || null);
      while (list.children.length > 4) list.removeChild(list.lastChild);
    });
  }
  function setStatus(message) {
    statusNodes.forEach(function (node) { node.textContent = message || ''; });
  }
  function setActivity(message) {
    setSingleLine(historyLists, message);
  }
  function setResult(html) {
    results.forEach(function (node) {
      node.innerHTML = html || '';
      node.classList.toggle('is-visible', Boolean(html));
    });
  }
  function rewardCard(item) {
    var image = item.reward_image_url ? '<img class="mg-campaign-issued-reward-image" src="' + esc(item.reward_image_url) + '" alt="">' : '';
    return '<div class="mg-campaign-issued-reward-card ' + (image ? 'has-image' : 'is-text-only') + '">' + image + '<span><strong>Reward sent</strong><b>' + esc(item.reward_title || 'Music reward') + '</b><small>' + esc((item.percent || '') + '% milestone') + '</small></span></div>';
  }
  function inboxResult(issued) {
    return '<strong>Reward sent to your Microgifter Inbox</strong><div class="mg-campaign-issued-reward-list">' + issued.map(rewardCard).join('') + '</div><a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">Open Microgifter Inbox</a>';
  }
  function campaignNotice(message) {
    blocked = true;
    root.classList.add('is-participation-blocked');
    var text = message || 'You have already participated in this campaign.';
    setStatus(text);
    setActivity(text);
    setResult('<strong>Campaign status</strong><p>' + esc(text) + '</p><a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">Open Microgifter Inbox</a>');
  }
  function visibleForm() {
    for (var i = 0; i < forms.length; i += 1) if (forms[i].offsetParent !== null) return forms[i];
    return forms[0] || null;
  }
  function readForm(sourceForm) {
    var form = sourceForm || visibleForm();
    if (!form) return { name: '', email: '', phone: '' };
    var fd = new FormData(form);
    return {
      name: String(fd.get('name') || '').trim(),
      email: String(fd.get('email') || '').trim().toLowerCase(),
      phone: String(fd.get('phone') || '').trim()
    };
  }
  async function checkParticipation(email, silent) {
    if (!email || email.indexOf('@') < 1) return true;
    if (eligibilityCache[email] != null) return eligibilityCache[email];
    if (!window.Microgifter || typeof Microgifter.post !== 'function') return true;
    try {
      var response = await Microgifter.post('/api/public/campaigns/participation-status.php', { campaign_id: campaignId, campaign_type: 'listen_music_reward', email: email });
      var data = response.data || response;
      if (data.participated || data.available === false) {
        eligibilityCache[email] = false;
        campaignNotice(data.message || response.message || 'You have already participated in this campaign.');
        return false;
      }
      eligibilityCache[email] = true;
      if (!silent) { setStatus('Eligible. Press play to start reward tracking.'); setActivity('Eligible for this campaign.'); }
      return true;
    } catch (error) { return true; }
  }
  async function joinFromForm(sourceForm, silent) {
    var nextCustomer = readForm(sourceForm);
    if (!nextCustomer.email || nextCustomer.email.indexOf('@') < 1) {
      setStatus('Enter a valid email before listening.');
      return false;
    }
    if (blocked && customer.email === nextCustomer.email) return false;
    if (customer.email === nextCustomer.email) return true;
    var allowed = await checkParticipation(nextCustomer.email, silent);
    if (!allowed) return false;
    customer = nextCustomer;
    setActivity('Campaign joined for ' + customer.email + '.');
    return true;
  }
  function progress() {
    if (provider === 'uploaded' && player) {
      var duration = Number(player.duration || 0);
      var current = Number(player.currentTime || 0);
      return { duration: duration, current: current, percent: duration > 0 ? Math.max(maxPercent, (current / duration) * 100) : maxPercent };
    }
    return { duration: 0, current: 0, percent: maxPercent };
  }
  function payload(percent) {
    var p = progress();
    return {
      campaign_id: campaignId,
      email: customer.email || '',
      name: customer.name || '',
      phone: customer.phone || '',
      audio_provider: provider,
      spotify_track_id: spotifyTrackId,
      uploaded_asset_id: uploadedAssetId,
      uploaded_audio_url: uploadedUrl,
      progress_percent: Math.max(0, Math.min(100, Math.round(percent))),
      duration_seconds: Math.round(p.duration || 0),
      current_time_seconds: Math.round(p.current || 0)
    };
  }
  async function postProgress(percent, force) {
    if (blocked || !customer.email) return;
    if (!window.Microgifter || typeof Microgifter.post !== 'function') { setStatus('Microgifter reward tracking is still loading.'); return; }
    var now = Date.now();
    if (!force && now - lastPost < 4500) return;
    lastPost = now;
    try {
      var response = await Microgifter.post('/api/public/campaigns/listen-progress.php', payload(percent));
      var data = response.data || response;
      var issued = data.issued_rewards || [];
      issued.forEach(function (item) { appendReward((item.percent || '') + '% reward issued — ' + (item.reward_title || 'Music reward')); });
      if (issued.length) setResult(inboxResult(issued));
      setStatus(data.message || ('Listening… ' + Math.round(percent) + '% complete'));
      setActivity('Listening progress: ' + Math.round(percent) + '% complete.');
      setWaveProgress(percent);
    } catch (error) {
      var message = error.message || 'Unable to record listen progress.';
      if (/already participated|limit reached|account required|required to participate|signed-in|signed in|sign in/i.test(message)) campaignNotice(message);
      else setStatus(message);
    }
  }
  function tick() {
    var p = progress();
    if (p.duration > 0 && customer.email && !blocked) {
      maxPercent = Math.min(100, p.percent);
      setWaveProgress(maxPercent);
      setStatus('Listening… ' + Math.round(maxPercent) + '% complete');
      setActivity('Listening progress: ' + Math.round(maxPercent) + '% complete.');
      postProgress(maxPercent, false);
    }
  }
  function bindAudio() {
    if (provider !== 'uploaded' || !player || audioBound) return;
    audioBound = true;
    player.addEventListener('play', async function () {
      if (!(await joinFromForm(null, true))) { try { player.pause(); } catch (error) {} return; }
      if (!started) { started = true; setActivity('Listening session started.'); postProgress(1, true); }
      clearInterval(timer);
      timer = setInterval(tick, 3000);
    });
    player.addEventListener('pause', function () { tick(); clearInterval(timer); });
    player.addEventListener('timeupdate', tick);
    player.addEventListener('ended', function () {
      if (!customer.email || blocked) return;
      maxPercent = 100;
      setWaveProgress(100);
      postProgress(100, true);
      clearInterval(timer);
      setStatus('Audio complete. Final rewards checked.');
      setActivity('Audio completed. Final rewards checked.');
    });
    setStatus('Enter your info, then press play to start reward tracking.');
  }
  function bindMobileDrawer() {
    var button = root.querySelector('[data-rl-mobile-toggle]');
    var drawer = root.querySelector('[data-rl-mobile-drawer]');
    var dock = root.querySelector('[data-rl-mobile-dock]');
    if (!button || !drawer) return;
    button.addEventListener('click', function () {
      var open = drawer.hasAttribute('hidden');
      if (open) drawer.removeAttribute('hidden'); else drawer.setAttribute('hidden', 'hidden');
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (dock) dock.classList.toggle('is-open', open);
    });
  }

  forms.forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!(await joinFromForm(form, false))) return;
      if (provider === 'uploaded') { bindAudio(); setStatus('Joined. Press play on the audio player to start reward tracking.'); postProgress(0, true); return; }
      setStatus('Spotify track loaded. Listen in the embedded player, then confirm to unlock the reward.');
      setWaveProgress(100);
      postProgress(0, true);
    });
  });
  if (confirmButton) confirmButton.addEventListener('click', async function () {
    if (!(await joinFromForm(null, false))) return;
    maxPercent = 100;
    setWaveProgress(100);
    setActivity('Spotify listen confirmation submitted.');
    postProgress(100, true);
  });
  initWaveform();
  bindAudio();
  bindMobileDrawer();
  var initial = readForm();
  if (initial.email) setTimeout(function () { checkParticipation(initial.email, true); }, 600);
})();

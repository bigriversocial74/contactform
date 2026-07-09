(function () {
  'use strict';
  var root = document.querySelector('[data-listen-music-reward]');
  if (!root) return;

  var form = root.querySelector('[data-listen-reward-form]');
  var result = root.querySelector('[data-listen-reward-result]');
  var shell = root.querySelector('[data-listen-audio-shell]');
  var provider = root.getAttribute('data-audio-provider') || 'spotify';
  var campaignId = root.getAttribute('data-campaign-id') || '';
  var spotifyTrackId = root.getAttribute('data-spotify-track-id') || '';
  var uploadedUrl = root.getAttribute('data-uploaded-audio-url') || '';
  var uploadedAssetId = root.getAttribute('data-uploaded-asset-id') || '';
  var player = root.querySelector('[data-listen-uploaded-player]');
  var statusNodes = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-status]'));
  var notifications = root.querySelector('[data-listen-reward-notifications]');
  var history = root.querySelector('[data-listen-reward-history]');
  var rewardHistory = root.querySelector('[data-listen-reward-issue-history]');
  var confirmButton = root.querySelector('[data-listen-spotify-confirm]');
  var customer = {};
  var timer = null;
  var maxPercent = 0;
  var lastPost = 0;
  var started = false;
  var joined = false;
  var audioBound = false;
  var unlockedStep = 0;
  var steps = { join: 0, media: 1, rewards: 2 };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }
  function timeLabel() {
    try { return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); } catch (error) { return ''; }
  }
  function clearPlaceholder(list) {
    if (!list) return;
    if (list.children.length === 1 && /waiting|no listening|no rewards/i.test(list.children[0].textContent || '')) list.innerHTML = '';
  }
  function addRow(list, message) {
    if (!list || !message) return;
    clearPlaceholder(list);
    var row = document.createElement('li');
    row.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>';
    list.insertBefore(row, list.firstChild || null);
    while (list.children.length > 8) list.removeChild(list.lastChild);
  }
  function setStatus(message) {
    statusNodes.forEach(function (node) { node.textContent = message || ''; });
    addRow(notifications, message);
  }
  function setResult(html) {
    if (!result) return;
    result.innerHTML = html || '';
    result.classList.toggle('is-visible', Boolean(html));
  }
  function syncTabs() {
    root.querySelectorAll('[data-listen-tab-trigger]').forEach(function (button) {
      var name = button.getAttribute('data-listen-tab-trigger') || 'join';
      var index = steps[name] || 0;
      button.setAttribute('aria-disabled', index > unlockedStep ? 'true' : 'false');
      button.classList.toggle('is-complete', index < unlockedStep);
    });
  }
  function showTab(name, force) {
    if (steps[name] == null) name = 'join';
    if (!force && (steps[name] || 0) > unlockedStep) {
      setStatus('Complete the current step before moving forward.');
      return;
    }
    root.querySelectorAll('[data-listen-tab-trigger]').forEach(function (button) {
      var active = button.getAttribute('data-listen-tab-trigger') === name;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    root.querySelectorAll('[data-listen-tab-panel]').forEach(function (panel) {
      var active = panel.getAttribute('data-listen-tab-panel') === name;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
    syncTabs();
  }
  function unlockStep(index) {
    unlockedStep = Math.max(unlockedStep, index);
    syncTabs();
  }
  function rewardCard(item) {
    var image = item.reward_image_url ? '<img class="mg-campaign-issued-reward-image" src="' + esc(item.reward_image_url) + '" alt="">' : '';
    return '<div class="mg-campaign-issued-reward-card ' + (image ? 'has-image' : 'is-text-only') + '">' + image + '<span>' + esc((item.percent || '') + '% — ' + (item.reward_title || 'Music reward')) + '</span></div>';
  }
  function inboxResult(issued) {
    return '<strong>Reward sent to your Microgifter Inbox</strong><div class="mg-campaign-issued-reward-list">' + issued.map(rewardCard).join('') + '</div><p class="mg-public-campaign-note">Open your Microgifter Inbox to view, manage, or redeem the reward.</p><a class="mg-btn mg-btn-primary" href="/inbox.php">Open Microgifter Inbox</a>';
  }
  function campaignNotice(message) {
    var account = /account|required|signed-in|sign in|signed in/i.test(message || '');
    var actions = account ? '<a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a><a class="mg-btn mg-btn-soft" href="/signup.php">Create account</a>' : '<a class="mg-btn mg-btn-primary" href="/inbox.php">Open Microgifter Inbox</a>';
    unlockStep(2);
    setResult('<strong>Campaign notice</strong><p>' + esc(message || 'This campaign is not available for another participation.') + '</p><div class="mg-heading-actions">' + actions + '</div>');
    showTab('rewards', true);
  }
  function readForm() {
    if (!form) return { name: '', email: '', phone: '' };
    var fd = new FormData(form);
    return {
      name: String(fd.get('name') || '').trim(),
      email: String(fd.get('email') || '').trim().toLowerCase(),
      phone: String(fd.get('phone') || '').trim()
    };
  }
  function joinFromForm() {
    var nextCustomer = readForm();
    if (!nextCustomer.email || nextCustomer.email.indexOf('@') < 1) {
      setStatus('Enter a valid email before listening.');
      showTab('join', true);
      return false;
    }
    customer = nextCustomer;
    if (shell) shell.hidden = false;
    unlockStep(1);
    showTab('media', true);
    if (!joined) {
      joined = true;
      addRow(history, 'Campaign joined for ' + customer.email + '.');
    }
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
    if (!window.Microgifter || typeof Microgifter.post !== 'function') {
      setStatus('Microgifter reward tracking is still loading.');
      return;
    }
    var now = Date.now();
    if (!force && now - lastPost < 4500) return;
    lastPost = now;
    try {
      var response = await Microgifter.post('/api/public/campaigns/listen-progress.php', payload(percent));
      var data = response.data || response;
      var issued = data.issued_rewards || [];
      issued.forEach(function (item) { addRow(rewardHistory, (item.percent || '') + '% reward issued — ' + (item.reward_title || 'Music reward')); });
      if (issued.length) {
        unlockStep(2);
        setResult(inboxResult(issued));
        showTab('rewards', true);
      }
      setStatus(data.message || ('Listen progress recorded: ' + Math.round(percent) + '%'));
      addRow(history, 'Progress recorded at ' + Math.round(percent) + '%.');
    } catch (error) {
      var message = error.message || 'Unable to record listen progress.';
      setStatus(message);
      if (/already participated|limit reached|account required|required to participate|signed-in|signed in|sign in/i.test(message)) campaignNotice(message);
    }
  }
  function tick() {
    var p = progress();
    if (p.duration > 0 && customer.email) {
      maxPercent = Math.min(100, p.percent);
      setStatus('Listening… ' + Math.round(maxPercent) + '% complete');
      postProgress(maxPercent, false);
    }
  }
  function bindAudio() {
    if (provider !== 'uploaded' || !player || audioBound) return;
    audioBound = true;
    player.addEventListener('play', function () {
      if (!joinFromForm()) {
        try { player.pause(); } catch (error) {}
        return;
      }
      if (!started) {
        started = true;
        addRow(history, 'Listening session started.');
        postProgress(1, true);
      }
      clearInterval(timer);
      timer = setInterval(tick, 3000);
    });
    player.addEventListener('pause', function () { tick(); clearInterval(timer); });
    player.addEventListener('timeupdate', tick);
    player.addEventListener('ended', function () {
      if (!customer.email) return;
      maxPercent = 100;
      postProgress(100, true);
      clearInterval(timer);
      setStatus('Audio complete. Final rewards checked.');
      addRow(history, 'Audio completed.');
    });
    setStatus('Enter your info, then press play to start reward tracking.');
  }

  root.querySelectorAll('[data-listen-tab-trigger]').forEach(function (button) {
    button.addEventListener('click', function () { showTab(button.getAttribute('data-listen-tab-trigger') || 'join', false); });
  });
  syncTabs();
  bindAudio();

  if (form) form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!joinFromForm()) return;
    if (provider === 'uploaded') {
      bindAudio();
      setStatus('Joined. Press play on the audio player to start reward tracking.');
      return;
    }
    setStatus('Spotify track loaded. Listen in the embedded player, then confirm to unlock the reward.');
    postProgress(0, true);
  });

  if (confirmButton) confirmButton.addEventListener('click', function () {
    if (!joinFromForm()) return;
    maxPercent = 100;
    addRow(history, 'Spotify listen confirmation submitted.');
    postProgress(100, true);
  });
})();

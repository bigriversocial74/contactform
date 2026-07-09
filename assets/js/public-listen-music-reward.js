(function () {
  'use strict';

  var root = document.querySelector('[data-listen-music-reward]');
  if (!root || !window.Microgifter) return;

  var form = root.querySelector('[data-listen-reward-form]');
  var status = root.querySelector('[data-listen-reward-status]');
  var result = root.querySelector('[data-listen-reward-result]');
  var shell = root.querySelector('[data-listen-audio-shell]');
  var provider = root.getAttribute('data-audio-provider') || 'spotify';
  var spotifyTrackId = root.getAttribute('data-spotify-track-id') || '';
  var uploadedUrl = root.getAttribute('data-uploaded-audio-url') || '';
  var uploadedAssetId = root.getAttribute('data-uploaded-asset-id') || '';
  var campaignId = root.getAttribute('data-campaign-id') || '';
  var player = root.querySelector('[data-listen-uploaded-player]');
  var notifications = root.querySelector('[data-listen-reward-notifications]');
  var history = root.querySelector('[data-listen-reward-history]');
  var rewardHistory = root.querySelector('[data-listen-reward-issue-history]');
  var customer = {};
  var timer = null;
  var maxPercent = 0;
  var lastPost = 0;
  var started = false;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }

  function setStatus(message) {
    if (status) status.textContent = message || '';
    if (message) addNotification(message);
  }

  function setResult(html) {
    if (!result) return;
    result.innerHTML = html || '';
    result.classList.toggle('is-visible', !!html);
  }

  function clearPlaceholder(list) {
    if (!list) return;
    if (list.children.length === 1 && /waiting|no listening|no rewards/i.test(list.children[0].textContent || '')) list.innerHTML = '';
  }

  function timeLabel() {
    try { return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); } catch (error) { return ''; }
  }

  function addNotification(message) {
    if (!notifications || !message) return;
    clearPlaceholder(notifications);
    var item = document.createElement('li');
    item.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>';
    notifications.insertBefore(item, notifications.firstChild || null);
    while (notifications.children.length > 8) notifications.removeChild(notifications.lastChild);
  }

  function addHistory(message) {
    if (!history || !message) return;
    clearPlaceholder(history);
    var item = document.createElement('li');
    item.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>';
    history.insertBefore(item, history.firstChild || null);
    while (history.children.length > 10) history.removeChild(history.lastChild);
  }

  function addRewardHistory(message) {
    if (!rewardHistory || !message) return;
    clearPlaceholder(rewardHistory);
    var item = document.createElement('li');
    item.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>';
    rewardHistory.insertBefore(item, rewardHistory.firstChild || null);
    while (rewardHistory.children.length > 10) rewardHistory.removeChild(rewardHistory.lastChild);
  }

  function showTab(name) {
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
  }

  root.querySelectorAll('[data-listen-tab-trigger]').forEach(function (button) {
    button.addEventListener('click', function () { showTab(button.getAttribute('data-listen-tab-trigger') || 'join'); });
  });

  function updateMilestones(issued) {
    (issued || []).forEach(function (item) {
      var percent = String(item.percent || '').replace(/[^0-9]/g, '');
      var row = root.querySelector('[data-listen-milestone="' + percent + '"]');
      if (row) {
        row.classList.remove('is-warn');
        row.classList.add('is-ready');
      }
      addRewardHistory((item.percent || '') + '% reward issued — ' + (item.reward_title || 'Music reward'));
    });
  }

  function inboxResult(issued) {
    var details = issued.map(function (item) {
      return esc((item.percent || '') + '% — ' + (item.reward_title || 'Music reward'));
    }).join('<br>');
    return '<strong>Reward sent to your Microgifter Inbox</strong><p>' + details + '</p><p class="mg-public-campaign-note">Open your Microgifter Inbox to view, manage, or redeem the reward.</p><a class="mg-btn mg-btn-primary" href="/inbox.php">Open Microgifter Inbox</a>';
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
    var now = Date.now();
    if (!force && now - lastPost < 4500) return;
    lastPost = now;
    try {
      var response = await Microgifter.post('/api/public/campaigns/listen-progress.php', payload(percent));
      var data = response.data || response;
      var issued = data.issued_rewards || [];
      updateMilestones(issued);
      if (issued.length) {
        setResult(inboxResult(issued));
        showTab('rewards');
      }
      var message = data.message || ('Listen progress recorded: ' + Math.round(percent) + '%');
      setStatus(message);
      addHistory('Progress recorded at ' + Math.round(percent) + '%');
    } catch (error) {
      setStatus(error.message || 'Unable to record listen progress.');
    }
  }

  function tick() {
    var p = progress();
    if (p.duration > 0) {
      maxPercent = Math.min(100, p.percent);
      setStatus('Listening… ' + Math.round(maxPercent) + '% complete');
      postProgress(maxPercent, false);
    }
  }

  function bindAudio() {
    if (provider !== 'uploaded' || !player) return;
    player.addEventListener('play', function () {
      if (!started) {
        started = true;
        addHistory('Listening session started.');
        postProgress(1, true);
      }
      clearInterval(timer);
      timer = setInterval(tick, 3000);
    });
    player.addEventListener('pause', function () {
      tick();
      clearInterval(timer);
      addHistory('Listening paused at ' + Math.round(maxPercent) + '%.');
    });
    player.addEventListener('timeupdate', tick);
    player.addEventListener('ended', function () {
      maxPercent = 100;
      postProgress(100, true);
      clearInterval(timer);
      setStatus('Audio complete. Final rewards checked.');
      addHistory('Audio completed.');
    });
    setStatus('Audio ready. Press play to start earning rewards.');
  }

  function readForm() {
    var fd = new FormData(form);
    return {
      name: String(fd.get('name') || '').trim(),
      email: String(fd.get('email') || '').trim().toLowerCase(),
      phone: String(fd.get('phone') || '').trim()
    };
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      customer = readForm();
      if (!customer.email || customer.email.indexOf('@') < 1) {
        setStatus('Enter a valid email before listening.');
        return;
      }
      form.classList.add('is-complete');
      if (shell) shell.hidden = false;
      showTab('media');
      addHistory('Campaign joined for ' + customer.email + '.');
      if (provider === 'uploaded') {
        bindAudio();
        postProgress(0, true);
        return;
      }
      setStatus('Spotify track loaded. Listen in the embedded player, then confirm to unlock the reward.');
      postProgress(0, true);
    });
  }

  var confirm = root.querySelector('[data-listen-spotify-confirm]');
  if (confirm) confirm.addEventListener('click', function () {
    if (!customer.email) {
      setStatus('Enter your info first.');
      showTab('join');
      return;
    }
    maxPercent = 100;
    addHistory('Spotify listen confirmation submitted.');
    postProgress(100, true);
  });
})();

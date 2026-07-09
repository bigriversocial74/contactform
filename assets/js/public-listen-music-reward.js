(function () {
  'use strict';
  var root = document.querySelector('[data-listen-music-reward]');
  if (!root) return;

  var forms = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-form]'));
  var result = root.querySelector('[data-listen-reward-result]');
  var provider = root.getAttribute('data-audio-provider') || 'spotify';
  var campaignId = root.getAttribute('data-campaign-id') || '';
  var spotifyTrackId = root.getAttribute('data-spotify-track-id') || '';
  var uploadedUrl = root.getAttribute('data-uploaded-audio-url') || '';
  var uploadedAssetId = root.getAttribute('data-uploaded-asset-id') || '';
  var player = root.querySelector('[data-listen-uploaded-player]');
  var statusNodes = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-status]'));
  var notificationLists = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-notifications]'));
  var historyLists = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-history]'));
  var rewardHistoryLists = Array.prototype.slice.call(root.querySelectorAll('[data-listen-reward-issue-history]'));
  var confirmButton = root.querySelector('[data-listen-spotify-confirm]');
  var customer = {};
  var timer = null;
  var maxPercent = 0;
  var lastPost = 0;
  var started = false;
  var joined = false;
  var blocked = false;
  var audioBound = false;
  var eligibilityCache = {};

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }
  function timeLabel() {
    try { return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); } catch (error) { return ''; }
  }
  function listArray(lists) {
    return Array.isArray(lists) ? lists : (lists ? [lists] : []);
  }
  function setSingleLine(lists, message) {
    listArray(lists).forEach(function (list) {
      if (!list) return;
      list.innerHTML = '<li><span>' + esc(timeLabel()) + '</span><strong>' + esc(message || '') + '</strong></li>';
    });
  }
  function appendRow(lists, message) {
    listArray(lists).forEach(function (list) {
      if (!list || !message) return;
      if (list.children.length === 1 && /waiting|no listening|no rewards/i.test(list.children[0].textContent || '')) list.innerHTML = '';
      var row = document.createElement('li');
      row.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>';
      list.insertBefore(row, list.firstChild || null);
      while (list.children.length > 5) list.removeChild(list.lastChild);
    });
  }
  function setStatus(message) {
    statusNodes.forEach(function (node) { node.textContent = message || ''; });
    setSingleLine(notificationLists, message);
  }
  function setActivity(message) {
    setSingleLine(historyLists, message);
  }
  function setResult(html) {
    if (!result) return;
    result.innerHTML = html || '';
    result.classList.toggle('is-visible', Boolean(html));
  }
  function rewardCard(item) {
    var image = item.reward_image_url ? '<img class="mg-campaign-issued-reward-image" src="' + esc(item.reward_image_url) + '" alt="">' : '';
    return '<div class="mg-campaign-issued-reward-card ' + (image ? 'has-image' : 'is-text-only') + '">' + image + '<span>' + esc((item.percent || '') + '% — ' + (item.reward_title || 'Music reward')) + '</span></div>';
  }
  function inboxResult(issued) {
    return '<strong>Reward sent to your Microgifter Inbox</strong><div class="mg-campaign-issued-reward-list">' + issued.map(rewardCard).join('') + '</div><p class="mg-public-campaign-note">Open your Microgifter Inbox to view, manage, or redeem the reward.</p><a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">Open Microgifter Inbox</a>';
  }
  function campaignNotice(message) {
    blocked = true;
    root.classList.add('is-participation-blocked');
    var text = message || 'You have already participated in this campaign.';
    setStatus(text);
    setActivity(text);
    setResult('<strong>Campaign notice</strong><p>' + esc(text) + '</p><a class="mg-rl-btn mg-rl-btn-soft" href="/inbox.php">Open Microgifter Inbox</a>');
  }
  function visibleForm() {
    for (var i = 0; i < forms.length; i += 1) {
      if (forms[i].offsetParent !== null) return forms[i];
    }
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
      var response = await Microgifter.post('/api/public/campaigns/participation-status.php', {
        campaign_id: campaignId,
        campaign_type: 'listen_music_reward',
        email: email
      });
      var data = response.data || response;
      if (data.participated || data.available === false) {
        eligibilityCache[email] = false;
        campaignNotice(data.message || response.message || 'You have already participated in this campaign.');
        return false;
      }
      eligibilityCache[email] = true;
      if (!silent) setStatus('Campaign available. Press play to start reward tracking.');
      return true;
    } catch (error) {
      return true;
    }
  }
  async function joinFromForm(sourceForm, silent) {
    var nextCustomer = readForm(sourceForm);
    if (!nextCustomer.email || nextCustomer.email.indexOf('@') < 1) {
      setStatus('Enter a valid email before listening.');
      return false;
    }
    if (blocked && customer.email === nextCustomer.email) return false;
    if (customer.email === nextCustomer.email && joined) return true;
    var allowed = await checkParticipation(nextCustomer.email, silent);
    if (!allowed) return false;
    customer = nextCustomer;
    joined = true;
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
      issued.forEach(function (item) { appendRow(rewardHistoryLists, (item.percent || '') + '% reward issued — ' + (item.reward_title || 'Music reward')); });
      if (issued.length) setResult(inboxResult(issued));
      setStatus(data.message || ('Listening… ' + Math.round(percent) + '% complete'));
      setActivity('Listening progress: ' + Math.round(percent) + '% complete.');
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
      setStatus('Listening… ' + Math.round(maxPercent) + '% complete');
      setActivity('Listening progress: ' + Math.round(maxPercent) + '% complete.');
      postProgress(maxPercent, false);
    }
  }
  function bindAudio() {
    if (provider !== 'uploaded' || !player || audioBound) return;
    audioBound = true;
    player.addEventListener('play', async function () {
      if (!(await joinFromForm(null, true))) {
        try { player.pause(); } catch (error) {}
        return;
      }
      if (!started) {
        started = true;
        setActivity('Listening session started.');
        postProgress(1, true);
      }
      clearInterval(timer);
      timer = setInterval(tick, 3000);
    });
    player.addEventListener('pause', function () { tick(); clearInterval(timer); });
    player.addEventListener('timeupdate', tick);
    player.addEventListener('ended', function () {
      if (!customer.email || blocked) return;
      maxPercent = 100;
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
      if (provider === 'uploaded') {
        bindAudio();
        setStatus('Joined. Press play on the audio player to start reward tracking.');
        postProgress(0, true);
        return;
      }
      setStatus('Spotify track loaded. Listen in the embedded player, then confirm to unlock the reward.');
      postProgress(0, true);
    });
  });
  if (confirmButton) confirmButton.addEventListener('click', async function () {
    if (!(await joinFromForm(null, false))) return;
    maxPercent = 100;
    setActivity('Spotify listen confirmation submitted.');
    postProgress(100, true);
  });

  bindAudio();
  bindMobileDrawer();
  var initial = readForm();
  if (initial.email) setTimeout(function () { checkParticipation(initial.email, true); }, 600);
})();

(function () {
  'use strict';
  var root = document.querySelector('[data-watch-video-reward]');
  if (!root) return;

  var forms = Array.prototype.slice.call(root.querySelectorAll('[data-watch-reward-form]'));
  var results = Array.prototype.slice.call(root.querySelectorAll('[data-watch-reward-result]'));
  var provider = root.getAttribute('data-video-provider') || 'youtube';
  var videoId = root.getAttribute('data-video-id') || '';
  var uploadedUrl = root.getAttribute('data-uploaded-video-url') || '';
  var uploadedAssetId = root.getAttribute('data-uploaded-asset-id') || '';
  var campaignId = root.getAttribute('data-campaign-id') || '';
  var statusNodes = Array.prototype.slice.call(root.querySelectorAll('[data-watch-reward-status]'));
  var historyLists = Array.prototype.slice.call(root.querySelectorAll('[data-watch-reward-history]'));
  var rewardHistoryLists = Array.prototype.slice.call(root.querySelectorAll('[data-watch-reward-issue-history]'));
  var uploadedPlayer = root.querySelector('[data-watch-uploaded-player]');
  var customer = {};
  var player = null;
  var timer = null;
  var maxPercent = 0;
  var lastPost = 0;
  var started = false;
  var blocked = false;
  var uploadBound = false;
  var eligibilityCache = {};

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }
  function timeLabel() {
    try { return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); } catch (error) { return ''; }
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
    return '<div class="mg-campaign-issued-reward-card ' + (image ? 'has-image' : 'is-text-only') + '">' + image + '<span><strong>Reward sent</strong><b>' + esc(item.reward_title || 'Video reward') + '</b><small>' + esc((item.percent || '') + '% milestone') + '</small></span></div>';
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
      var response = await Microgifter.post('/api/public/campaigns/participation-status.php', { campaign_id: campaignId, campaign_type: 'watch_video_reward', email: email });
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
      setStatus('Enter a valid email before watching.');
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
    if (provider === 'uploaded' && uploadedPlayer) {
      var duration = Number(uploadedPlayer.duration || 0);
      var current = Number(uploadedPlayer.currentTime || 0);
      return { duration: duration, current: current, percent: duration > 0 ? Math.max(maxPercent, (current / duration) * 100) : maxPercent };
    }
    if (player && player.getDuration && player.getCurrentTime) {
      var ytDuration = Number(player.getDuration() || 0);
      var ytCurrent = Number(player.getCurrentTime() || 0);
      return { duration: ytDuration, current: ytCurrent, percent: ytDuration > 0 ? Math.max(maxPercent, (ytCurrent / ytDuration) * 100) : maxPercent };
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
      video_provider: provider,
      video_id: videoId,
      uploaded_asset_id: uploadedAssetId,
      uploaded_video_url: uploadedUrl,
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
      var response = await Microgifter.post('/api/public/campaigns/watch-progress-v2.php', payload(percent));
      var data = response.data || response;
      var issued = data.issued_rewards || [];
      issued.forEach(function (item) { appendReward((item.percent || '') + '% reward issued — ' + (item.reward_title || 'Video reward')); });
      if (issued.length) setResult(inboxResult(issued));
      setStatus(data.message || ('Watching… ' + Math.round(percent) + '% complete'));
      setActivity('Watch progress: ' + Math.round(percent) + '% complete.');
    } catch (error) {
      var message = error.message || 'Unable to record watch progress.';
      if (/already participated|limit reached|account required|required to participate|signed-in|signed in|sign in/i.test(message)) campaignNotice(message);
      else setStatus(message);
    }
  }
  function tick() {
    var p = progress();
    if (p.duration > 0 && customer.email && !blocked) {
      maxPercent = Math.min(100, p.percent);
      setStatus('Watching… ' + Math.round(maxPercent) + '% complete');
      setActivity('Watch progress: ' + Math.round(maxPercent) + '% complete.');
      postProgress(maxPercent, false);
    }
  }
  async function startSession() {
    if (!(await joinFromForm(null, true))) {
      try { if (player && player.pauseVideo) player.pauseVideo(); } catch (error) {}
      try { if (uploadedPlayer) uploadedPlayer.pause(); } catch (error2) {}
      return;
    }
    if (!started) { started = true; setActivity('Watch session started.'); postProgress(1, true); }
    clearInterval(timer);
    timer = setInterval(tick, 3000);
  }
  function youtubeState(event) {
    if (!window.YT || !YT.PlayerState) return;
    if (event.data === YT.PlayerState.PLAYING) startSession();
    else if (event.data === YT.PlayerState.PAUSED) { tick(); clearInterval(timer); }
    else if (event.data === YT.PlayerState.ENDED) {
      maxPercent = 100;
      postProgress(100, true);
      clearInterval(timer);
      setStatus('Video complete. Final rewards checked.');
      setActivity('Video completed. Final rewards checked.');
    }
  }
  window.onYouTubeIframeAPIReady = function () {
    if (provider !== 'youtube' || !videoId) return;
    player = new YT.Player('mg-watch-video-player', {
      videoId: videoId,
      playerVars: { rel: 0, modestbranding: 1, playsinline: 1, origin: location.origin },
      events: { onStateChange: youtubeState, onReady: function () { setStatus('Video ready. Enter your info, then press play to start rewards.'); } }
    });
  };
  function bindUpload() {
    if (provider !== 'uploaded' || !uploadedPlayer || uploadBound) return;
    uploadBound = true;
    uploadedPlayer.addEventListener('play', startSession);
    uploadedPlayer.addEventListener('pause', function () { tick(); clearInterval(timer); });
    uploadedPlayer.addEventListener('timeupdate', tick);
    uploadedPlayer.addEventListener('ended', function () {
      maxPercent = 100;
      postProgress(100, true);
      clearInterval(timer);
      setStatus('Video complete. Final rewards checked.');
      setActivity('Video completed. Final rewards checked.');
    });
    setStatus('Uploaded video ready. Enter your info, then press play to start rewards.');
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
      if (provider === 'uploaded') { bindUpload(); setStatus('Joined. Press play on the video player to start reward tracking.'); postProgress(0, true); return; }
      setStatus('Joined. Press play on the video player to start reward tracking.');
      if (window.YT && window.YT.Player && !player) window.onYouTubeIframeAPIReady();
      postProgress(0, true);
    });
  });

  bindUpload();
  bindMobileDrawer();
  var initial = readForm();
  if (initial.email) setTimeout(function () { checkParticipation(initial.email, true); }, 600);
})();

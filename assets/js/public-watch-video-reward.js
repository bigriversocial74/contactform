(function () {
  'use strict';

  var root = document.querySelector('[data-watch-video-reward]');
  if (!root) return;

  var form = root.querySelector('[data-watch-reward-form]');
  var statusNodes = Array.prototype.slice.call(root.querySelectorAll('[data-watch-reward-status]'));
  var result = root.querySelector('[data-watch-reward-result]');
  var shell = root.querySelector('[data-watch-video-shell]');
  var provider = root.getAttribute('data-video-provider') || 'youtube';
  var videoId = root.getAttribute('data-video-id') || '';
  var uploadedUrl = root.getAttribute('data-uploaded-video-url') || '';
  var uploadedAssetId = root.getAttribute('data-uploaded-asset-id') || '';
  var campaignId = root.getAttribute('data-campaign-id') || '';
  var notifications = root.querySelector('[data-watch-reward-notifications]');
  var history = root.querySelector('[data-watch-reward-history]');
  var rewardHistory = root.querySelector('[data-watch-reward-issue-history]');
  var customer = {};
  var player = null;
  var uploadedPlayer = root.querySelector('[data-watch-uploaded-player]');
  var timer = null;
  var maxPercent = 0;
  var lastPost = 0;
  var started = false;
  var uploadBound = false;
  var unlockedStep = 0;
  var stepMap = { join: 0, media: 1, rewards: 2 };

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function setStatus(message) { statusNodes.forEach(function (node) { node.textContent = message || ''; }); if (message) addNotification(message); }
  function setResult(html) { if (!result) return; result.innerHTML = html || ''; result.classList.toggle('is-visible', !!html); }
  function clearPlaceholder(list) { if (!list) return; if (list.children.length === 1 && /waiting|no watch|no rewards/i.test(list.children[0].textContent || '')) list.innerHTML = ''; }
  function timeLabel() { try { return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); } catch (error) { return ''; } }
  function addNotification(message) { if (!notifications || !message) return; clearPlaceholder(notifications); var item = document.createElement('li'); item.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>'; notifications.insertBefore(item, notifications.firstChild || null); while (notifications.children.length > 8) notifications.removeChild(notifications.lastChild); }
  function addHistory(message) { if (!history || !message) return; clearPlaceholder(history); var item = document.createElement('li'); item.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>'; history.insertBefore(item, history.firstChild || null); while (history.children.length > 10) history.removeChild(history.lastChild); }
  function addRewardHistory(message) { if (!rewardHistory || !message) return; clearPlaceholder(rewardHistory); var item = document.createElement('li'); item.innerHTML = '<span>' + esc(timeLabel()) + '</span><strong>' + esc(message) + '</strong>'; rewardHistory.insertBefore(item, rewardHistory.firstChild || null); while (rewardHistory.children.length > 10) rewardHistory.removeChild(rewardHistory.lastChild); }
  function syncTabs() { root.querySelectorAll('[data-watch-tab-trigger]').forEach(function (button) { var name = button.getAttribute('data-watch-tab-trigger') || 'join'; var index = stepMap[name] || 0; var disabled = index > unlockedStep; button.setAttribute('aria-disabled', disabled ? 'true' : 'false'); button.classList.toggle('is-complete', index < unlockedStep); }); }
  function showTab(name, force) { name = stepMap[name] == null ? 'join' : name; if (!force && (stepMap[name] || 0) > unlockedStep) { setStatus('Complete the current step before moving forward.'); return; } root.querySelectorAll('[data-watch-tab-trigger]').forEach(function (button) { var active = button.getAttribute('data-watch-tab-trigger') === name; button.classList.toggle('is-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); root.querySelectorAll('[data-watch-tab-panel]').forEach(function (panel) { var active = panel.getAttribute('data-watch-tab-panel') === name; panel.classList.toggle('is-active', active); panel.hidden = !active; }); syncTabs(); }
  function unlockStep(index) { unlockedStep = Math.max(unlockedStep, index); syncTabs(); }
  root.querySelectorAll('[data-watch-tab-trigger]').forEach(function (button) { button.addEventListener('click', function () { showTab(button.getAttribute('data-watch-tab-trigger') || 'join', false); }); });
  syncTabs();
  function updateMilestones(issued) { (issued || []).forEach(function (item) { addRewardHistory((item.percent || '') + '% reward issued — ' + (item.reward_title || 'Video reward')); }); }
  function inboxResult(issued) { var details = issued.map(function (item) { return esc((item.percent || '') + '% — ' + (item.reward_title || 'Video reward')); }).join('<br>'); return '<strong>Reward sent to your Microgifter Inbox</strong><p>' + details + '</p><p class="mg-public-campaign-note">Open your Microgifter Inbox to view, manage, or redeem the reward.</p><a class="mg-btn mg-btn-primary" href="/inbox.php">Open Microgifter Inbox</a>'; }
  function campaignNotice(message) { unlockStep(2); setResult('<strong>Campaign notice</strong><p>' + esc(message || 'This campaign is not available for another participation.') + '</p><a class="mg-btn mg-btn-primary" href="/inbox.php">Open Microgifter Inbox</a>'); showTab('rewards', true); }
  function currentProgress() { if (provider === 'uploaded' && uploadedPlayer) { var duration = Number(uploadedPlayer.duration || 0); var current = Number(uploadedPlayer.currentTime || 0); return { duration: duration, current: current, percent: duration > 0 ? Math.max(maxPercent, (current / duration) * 100) : maxPercent }; } if (player && player.getDuration && player.getCurrentTime) { var d = Number(player.getDuration() || 0); var c = Number(player.getCurrentTime() || 0); return { duration: d, current: c, percent: d > 0 ? Math.max(maxPercent, (c / d) * 100) : maxPercent }; } return { duration: 0, current: 0, percent: maxPercent }; }
  function payload(percent) { var progress = currentProgress(); return { campaign_id: campaignId, email: customer.email || '', name: customer.name || '', phone: customer.phone || '', video_provider: provider, video_id: videoId, uploaded_asset_id: uploadedAssetId, uploaded_video_url: uploadedUrl, progress_percent: Math.max(0, Math.min(100, Math.round(percent))), duration_seconds: Math.round(progress.duration || 0), current_time_seconds: Math.round(progress.current || 0) }; }
  async function postProgress(percent, force) { if (!window.Microgifter || typeof Microgifter.post !== 'function') { setStatus('Microgifter reward tracking is still loading. Try again in a moment.'); return; } var now = Date.now(); if (!force && now - lastPost < 4500) return; lastPost = now; try { var response = await Microgifter.post('/api/public/campaigns/watch-progress.php', payload(percent)); var data = response.data || response; var issued = data.issued_rewards || []; updateMilestones(issued); if (issued.length) { unlockStep(2); setResult(inboxResult(issued)); showTab('rewards', true); } setStatus(data.message || ('Watch progress recorded: ' + Math.round(percent) + '%')); addHistory('Progress recorded at ' + Math.round(percent) + '%.'); } catch (error) { var message = error.message || 'Unable to record watch progress.'; setStatus(message); if (/already participated|limit reached/i.test(message)) campaignNotice(message); } }
  function tick() { var progress = currentProgress(); if (progress.duration > 0) { maxPercent = Math.min(100, progress.percent); setStatus('Watching… ' + Math.round(maxPercent) + '% complete'); postProgress(maxPercent, false); } }
  function onPlayerStateChange(event) { if (event.data === YT.PlayerState.PLAYING) { if (!started) { started = true; addHistory('Watch session started.'); postProgress(1, true); } clearInterval(timer); timer = setInterval(tick, 3000); } else if (event.data === YT.PlayerState.PAUSED) { tick(); clearInterval(timer); addHistory('Watch paused at ' + Math.round(maxPercent) + '%.'); } else if (event.data === YT.PlayerState.ENDED) { maxPercent = 100; tick(); postProgress(100, true); clearInterval(timer); setStatus('Video complete. Final rewards checked.'); addHistory('Video completed.'); } }
  window.onYouTubeIframeAPIReady = function () { if (provider !== 'youtube' || !videoId) return; player = new YT.Player('mg-watch-video-player', { videoId: videoId, playerVars: { rel: 0, modestbranding: 1, playsinline: 1, origin: location.origin }, events: { onStateChange: onPlayerStateChange, onReady: function () { setStatus('Video ready. Press play to start earning rewards.'); } } }); };
  function bindUploadedPlayer() { if (provider !== 'uploaded' || !uploadedPlayer || uploadBound) return; uploadBound = true; uploadedPlayer.addEventListener('play', function () { if (!started) { started = true; addHistory('Watch session started.'); postProgress(1, true); } clearInterval(timer); timer = setInterval(tick, 3000); }); uploadedPlayer.addEventListener('pause', function () { tick(); clearInterval(timer); addHistory('Watch paused at ' + Math.round(maxPercent) + '%.'); }); uploadedPlayer.addEventListener('timeupdate', tick); uploadedPlayer.addEventListener('ended', function () { maxPercent = 100; postProgress(100, true); clearInterval(timer); setStatus('Video complete. Final rewards checked.'); addHistory('Video completed.'); }); setStatus('Uploaded video ready. Press play to start earning rewards.'); }
  function readForm() { var fd = new FormData(form); return { name: String(fd.get('name') || '').trim(), email: String(fd.get('email') || '').trim().toLowerCase(), phone: String(fd.get('phone') || '').trim() }; }
  if (form) { form.addEventListener('submit', function (event) { event.preventDefault(); customer = readForm(); if (!customer.email || customer.email.indexOf('@') < 1) { setStatus('Enter a valid email before watching.'); return; } form.classList.add('is-complete'); if (shell) shell.hidden = false; unlockStep(1); showTab('media', true); addHistory('Campaign joined for ' + customer.email + '.'); if (provider === 'uploaded') { bindUploadedPlayer(); postProgress(0, true); return; } setStatus('Loading YouTube player…'); if (window.YT && window.YT.Player) window.onYouTubeIframeAPIReady(); postProgress(0, true); }); }
})();
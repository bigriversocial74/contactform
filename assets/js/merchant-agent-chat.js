document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  if (!root || !window.Microgifter) return;

  var feed = root.querySelector('[data-agent-chat-feed]');
  var form = root.querySelector('[data-agent-chat-form]');
  var status = root.querySelector('[data-agent-chat-status]');
  var send = root.querySelector('[data-agent-chat-send]');
  var textarea = form ? form.querySelector('[data-agent-chat-textarea],textarea[name="message"]') : null;
  var menu = root.querySelector('[data-agent-context-menu]');
  var menuToggle = root.querySelector('[data-agent-context-toggle]');
  var state = { messages: [], quick_prompts: [], overview: null, agent_profile: null, active_thread: null, threads: [], skills: [] };
  var inFlight = false;

  var fallbackPrompts = [
    'Analyze my product opportunities and show a chart.',
    'Create a social campaign from my best current offer.',
    'Find claim or redemption issues.',
    'Draft a weekend campaign plan.',
    'What should I focus on today?'
  ];

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function payload(response) { return response && response.data ? response.data : response; }
  function time(value) { var parsed = Date.parse(value || ''); return parsed ? new Date(parsed).toLocaleString() : ''; }
  function nowIso() { return new Date().toISOString(); }
  function human(value) { return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); }); }
  function shortText(value, max) { value = String(value || '').trim(); max = max || 900; return value.length > max ? value.slice(0, max - 1) + '…' : value; }
  function arrayish(value) { if (Array.isArray(value)) return value; if (value && typeof value === 'object') return Object.keys(value).map(function (key) { return value[key]; }); return []; }
  function agentName() { return (state.agent_profile && state.agent_profile.agent_name) || 'Merchant Agent'; }
  function activeThreadId() { return (state.active_thread && state.active_thread.id) || ''; }

  function cleanErrorMessage(error) {
    var message = String((error && error.message) || error || '').trim();
    if (!message) return 'Unable to run merchant agent chat.';
    if (/invalid\s*x-api-key|x-api-key|invalid api key|authentication_error/i.test(message)) return 'Anthropic rejected the server API key. Replace the value in api/config.local.php with a valid Anthropic Console API key, save the file, then retry.';
    if (/csrf/i.test(message)) return 'The security token expired. Refresh the page and send the message again.';
    if (/permission|forbidden|unauthorized|merchant\.ai\.plan|merchant\.ai\.review/i.test(message)) return 'Your account needs the merchant AI permission for this action. Re-run the Stage 19C Claude planner migration or update this user role.';
    if (/Run the merchant agent skills SQL migration/i.test(message)) return message;
    if (/not configured|MG_ANTHROPIC_API_KEY|Anthropic API key/i.test(message)) return 'Anthropic is not configured on the server. Check api/config.local.php and reload the page.';
    if (/Claude Sonnet is not enabled|AI provider is not enabled|model catalog/i.test(message)) return 'Claude is not enabled in the AI provider settings. Open admin-ai.php, enable Anthropic, choose a default Claude model, and save.';
    if (/cURL|curl|timeout|timed out|empty response|HTTP 5|overloaded|rate/i.test(message)) return 'Claude did not return a usable response yet. Server detail: ' + message;
    return message;
  }

  function setStatus(message, type) { if (!status) return; status.textContent = message || ''; status.className = 'mg-form-status' + (type ? ' is-' + type : ''); }
  function updateSendState() { if (!send || !textarea) return; send.disabled = inFlight || !textarea.value.trim() || textarea.disabled; }
  function growTextarea() { if (!textarea) return; textarea.style.height = 'auto'; textarea.style.height = Math.min(textarea.scrollHeight, 130) + 'px'; }
  function resetComposer() { if (textarea) { textarea.value = ''; textarea.style.height = ''; } updateSendState(); }
  function busy(on) { inFlight = !!on; if (send) { send.disabled = !!on || !(textarea && textarea.value.trim()); send.textContent = on ? '…' : '↑'; send.classList.toggle('is-loading', !!on); } if (textarea) textarea.disabled = !!on; }
  function contextValue(selector, fallback) { var element = root.querySelector(selector); return element && element.value ? element.value : fallback; }
  function selectedSkills() { var keys = []; root.querySelectorAll('[data-agent-skill]').forEach(function (box) { if (box.checked && box.value) keys.push(box.value); }); return keys; }

  function updateContextSummary() {
    var box = root.querySelector('[data-agent-chat-summary]');
    if (!box) return;
    var scope = contextValue('[data-agent-chat-scope]', 'overview');
    var days = contextValue('[data-agent-chat-days]', '90');
    var output = contextValue('[data-agent-chat-output]', 'action_plan');
    var approval = contextValue('[data-agent-chat-approval]', 'advisory');
    var skills = selectedSkills().map(function (key) { return key === 'merchant_analysis_charts' ? 'Charts' : 'Social'; }).join(' + ') || 'No skills';
    box.textContent = [human(scope), 'Last ' + days + ' days', human(output), human(approval), skills].join(' · ');
  }

  function closeMenu() { if (!menu || !menuToggle) return; menu.hidden = true; menuToggle.setAttribute('aria-expanded', 'false'); }
  function toggleMenu() { if (!menu || !menuToggle) return; var open = menu.hidden; menu.hidden = !open; menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  function promptButtons() { var prompts = state.quick_prompts && state.quick_prompts.length ? state.quick_prompts : fallbackPrompts; return '<div class="mg-agent-chat-prompts" data-agent-chat-prompts>' + prompts.map(function (prompt) { return '<button type="button">' + esc(prompt) + '</button>'; }).join('') + '</div>'; }

  function renderRail() {
    var nameInput = root.querySelector('[data-agent-name-input]');
    if (nameInput && state.agent_profile && document.activeElement !== nameInput) nameInput.value = state.agent_profile.agent_name || 'Merchant Agent';
    var select = root.querySelector('[data-agent-thread-select]');
    if (select) {
      var current = activeThreadId();
      var threads = Array.isArray(state.threads) ? state.threads : [];
      if (current && !threads.some(function (thread) { return thread.id === current; }) && state.active_thread) threads = [state.active_thread].concat(threads);
      select.innerHTML = (threads.length ? threads : [{ id: current, title: 'Current chat', status: 'active' }]).map(function (thread) {
        var label = (thread.title || 'Current chat') + (thread.status && thread.status !== 'active' ? ' · ' + thread.status : '');
        return '<option value="' + esc(thread.id || '') + '"' + ((thread.id || '') === current ? ' selected' : '') + '>' + esc(label) + '</option>';
      }).join('');
    }
    if (Array.isArray(state.skills) && state.skills.length) state.skills.forEach(function (skill) { var box = root.querySelector('[data-agent-skill][value="' + skill.key + '"]'); if (box && typeof skill.enabled === 'boolean') box.checked = !!skill.enabled; });
    updateContextSummary();
  }

  function renderOverview() { renderRail(); }

  function chartBlockHtml(block) {
    var data = Array.isArray(block.data) ? block.data : [];
    if (!data.length) return '';
    var max = data.reduce(function (m, row) { return Math.max(m, Math.abs(Number(row.value) || 0)); }, 0) || 1;
    var prefix = block.value_prefix || '';
    var suffix = block.value_suffix || '';
    if ((block.chart_type || 'bar') === 'line') {
      var width = 460, height = 130;
      var points = data.map(function (row, index) { var x = data.length === 1 ? width / 2 : (index / (data.length - 1)) * width; var y = height - ((Number(row.value) || 0) / max) * (height - 18) - 8; return x.toFixed(1) + ',' + y.toFixed(1); }).join(' ');
      return '<div class="mg-agent-block mg-agent-chart-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Chart') + '</strong><span>' + esc(block.body || '') + '</span></div><svg class="mg-agent-line-chart" viewBox="0 0 ' + width + ' ' + height + '" role="img" aria-label="' + esc(block.title || 'Line chart') + '"><polyline points="' + points + '" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline></svg><div class="mg-agent-chart-labels">' + data.map(function (row) { return '<span>' + esc(row.label) + '</span>'; }).join('') + '</div></div>';
    }
    return '<div class="mg-agent-block mg-agent-chart-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Chart') + '</strong><span>' + esc(block.body || '') + '</span></div><div class="mg-agent-bars">' + data.map(function (row) { var value = Number(row.value) || 0; var barWidth = Math.max(6, Math.round((Math.abs(value) / max) * 100)); return '<div class="mg-agent-bar-row"><span>' + esc(row.label) + '</span><div><i style="width:' + barWidth + '%"></i></div><b>' + esc(prefix + value.toLocaleString() + suffix) + '</b></div>'; }).join('') + '</div></div>';
  }

  function metricGridHtml(block) { var metrics = Array.isArray(block.metrics) ? block.metrics : []; if (!metrics.length) return ''; return '<div class="mg-agent-block mg-agent-metric-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Metrics') + '</strong><span>' + esc(block.body || '') + '</span></div><div class="mg-agent-metrics">' + metrics.map(function (metric) { return '<article><strong>' + esc(metric.value) + '</strong><span>' + esc(metric.label) + '</span></article>'; }).join('') + '</div></div>'; }
  function socialBlockHtml(block) { var posts = Array.isArray(block.posts) ? block.posts : []; return '<div class="mg-agent-block mg-agent-social-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Social campaign') + '</strong><span>' + esc(block.body || '') + '</span></div>' + (block.audience ? '<p><b>Audience:</b> ' + esc(block.audience) + '</p>' : '') + (block.cta ? '<p><b>CTA:</b> ' + esc(block.cta) + '</p>' : '') + (posts.length ? '<div class="mg-agent-social-posts">' + posts.map(function (post) { return '<article><strong>' + esc(post.channel || 'Social') + '</strong><p>' + esc(post.copy || '') + '</p></article>'; }).join('') + '</div>' : '') + '</div>'; }
  function projectBlockHtml(block) { return '<div class="mg-agent-block mg-agent-project-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || 'Project') + '</strong><span>' + esc(block.body || '') + '</span></div><div class="mg-agent-project-meta">' + (block.estimated_impact ? '<span>Impact: ' + esc(block.estimated_impact) + '</span>' : '') + (block.confidence != null ? '<span>Confidence: ' + esc(Math.round(Number(block.confidence) * 100)) + '%</span>' : '') + '<span>Approval required</span></div></div>'; }

  function recipeData(source) {
    var reviewPayload = source && typeof source.review_payload === 'object' && source.review_payload ? source.review_payload : {};
    var merged = Object.assign({}, reviewPayload, source || {});
    var hasRecipe = merged.recipe_engine_used || merged.recommended_campaign_type || merged.recommended_reward_type || merged.recipe_key || merged.channel_package || merged.draft_artifacts || merged.draft_type;
    return hasRecipe ? merged : null;
  }

  function recipeArtifactsHtml(items) {
    items = arrayish(items).slice(0, 8);
    if (!items.length) return '';
    return '<div class="mg-agent-chat-recipe-artifacts">' + items.map(function (item) {
      if (typeof item === 'string') return '<article><strong>Draft artifact</strong><p>' + esc(shortText(item, 520)) + '</p></article>';
      var title = item.title || item.label || item.type || item.channel || 'Draft artifact';
      var body = item.body || item.copy || item.text || item.content || item.instructions || item.description || '';
      return '<article><strong>' + esc(human(title)) + '</strong><p>' + esc(shortText(body || JSON.stringify(item), 520)) + '</p></article>';
    }).join('') + '</div>';
  }

  function recipePanelHtml(recipe, compact) {
    if (!recipe) return '';
    var channels = arrayish(recipe.channel_package).map(function (channel) { if (typeof channel === 'string') return channel; return channel.label || channel.channel || channel.type || channel.name || ''; }).filter(Boolean);
    var title = recipe.draft_label || recipe.title || recipe.package_title || 'Campaign Recipe';
    var subtitle = recipe.draft_title || recipe.campaign_title || recipe.why_this_recipe || recipe.body || '';
    var body = recipe.draft_body || recipe.copy || recipe.instructions || recipe.body || recipe.why_this_recipe || '';
    return '<section class="mg-agent-chat-recipe ' + (compact ? 'is-compact' : '') + '"><div class="mg-agent-chat-recipe-head"><div><span>Recipe card</span><strong>' + esc(title) + '</strong><p>' + esc(shortText(subtitle, 240)) + '</p></div><div class="mg-agent-chat-recipe-badges"><b>' + esc(human(recipe.recommended_campaign_type || recipe.campaign_type || 'campaign')) + '</b><b>' + esc(human(recipe.recommended_reward_type || recipe.reward_type || 'reward')) + '</b><b>' + esc(recipe.recipe_key || 'custom') + '</b></div></div><div class="mg-agent-chat-recipe-grid"><article><span>Campaign</span><strong>' + esc(human(recipe.recommended_campaign_type || recipe.campaign_type || 'Not specified')) + '</strong></article><article><span>Reward</span><strong>' + esc(human(recipe.recommended_reward_type || recipe.reward_type || 'Not specified')) + '</strong></article><article><span>Channels</span><strong>' + esc(channels.length ? channels.map(human).join(', ') : 'Not specified') + '</strong></article><article><span>Recipe</span><strong>' + esc(recipe.recipe_key || 'Custom') + '</strong></article></div>' + (body ? '<div class="mg-agent-chat-recipe-copy"><span>Draft copy / instructions</span><p>' + esc(shortText(body, compact ? 700 : 1300)) + '</p></div>' : '') + recipeArtifactsHtml(recipe.draft_artifacts || recipe.artifacts) + '</section>';
  }

  function recipeBlockHtml(block) { return '<div class="mg-agent-block mg-agent-recipe-block">' + recipePanelHtml(block, false) + '</div>'; }
  function blocksHtml(blocks) { blocks = Array.isArray(blocks) ? blocks : []; if (!blocks.length) return ''; return '<div class="mg-agent-chat-blocks">' + blocks.map(function (block) { var type = block.type || ''; if (type === 'chart' || type === 'forecast') return chartBlockHtml(block); if (type === 'metric_grid') return metricGridHtml(block); if (type === 'social_campaign' || type === 'social_posts') return socialBlockHtml(block); if (type === 'campaign_recipe' || type === 'campaign_package' || type === 'recipe_package' || recipeData(block)) return recipeBlockHtml(block); if (type === 'project' || type === 'product_opportunity') return projectBlockHtml(block); return '<div class="mg-agent-block"><div class="mg-agent-block-head"><strong>' + esc(block.title || human(type || 'Insight')) + '</strong><span>' + esc(block.body || '') + '</span></div></div>'; }).join('') + '</div>'; }

  function cardHtml(card, message, index) {
    var link = card.action_url ? '<a class="mg-btn mg-btn-soft" href="' + esc(card.action_url) + '">' + esc(card.action_label || 'Open') + '</a>' : '';
    var bridge = '';
    if (message.role !== 'user') {
      if (card.review_item_id) bridge = '<a class="mg-btn mg-btn-soft" href="/merchant-agent-approvals.php">In Review Queue</a>';
      else bridge = '<button class="mg-btn mg-btn-secondary" type="button" data-agent-chat-review data-message-id="' + esc(message.id) + '" data-card-index="' + esc(index) + '">Send to Review Queue</button>';
    }
    var recipe = recipeData(card);
    var save = '<button class="mg-btn mg-btn-soft" type="button" data-agent-card-status>Save draft</button>';
    var dismiss = '<button class="mg-btn mg-btn-soft" type="button" data-agent-card-status>Dismiss</button>';
    return '<article class="mg-agent-chat-card ' + (recipe ? 'is-recipe-card' : '') + '"><span>' + esc(card.type || 'recommendation') + '</span><strong>' + esc(card.title || 'Agent note') + '</strong><p>' + esc(card.body || '') + '</p>' + recipePanelHtml(recipe, true) + '<div class="mg-agent-chat-card-actions">' + link + bridge + save + dismiss + '</div></article>';
  }

  function messageHtml(message) { var mine = message.role === 'user'; var cards = Array.isArray(message.cards) ? message.cards : []; var classes = ['mg-agent-chat-message', mine ? 'is-user' : 'is-agent']; if (message.pending) classes.push('is-pending'); if (message.error) classes.push('is-error'); var label = mine ? 'You' : agentName(); return '<article class="' + classes.join(' ') + '"><div class="mg-agent-chat-bubble"><div class="mg-agent-chat-meta"><strong>' + esc(label) + '</strong><time>' + esc(time(message.created_at)) + '</time></div><p>' + esc(message.body || '') + '</p>' + blocksHtml(message.blocks) + (cards.length ? '<div class="mg-agent-chat-cards">' + cards.map(function (card, index) { return cardHtml(card, message, index); }).join('') + '</div>' : '') + '</div></article>'; }

  function render() { renderOverview(); updateContextSummary(); if (!feed) return; if (!state.messages.length) { feed.innerHTML = '<div class="mg-agent-chat-empty"><div class="mg-agent-chat-empty-icon" aria-hidden="true">✦</div><strong>How can I help you today?</strong><p>Ask ' + esc(agentName()) + ' to analyze products, chart performance, draft social campaigns, or prioritize claim flow work.</p>' + promptButtons() + '<small>Conversations stay advisory until you approve an action.</small></div>'; return; } feed.innerHTML = state.messages.map(messageHtml).join(''); feed.scrollTop = feed.scrollHeight; }
  function applyState(data, options) { options = options || {}; if (!data) return; if (data.state) data = data.state; var nextMessages = Array.isArray(data.messages) ? data.messages : null; if (nextMessages && (nextMessages.length || !options.preserveMessages)) state.messages = nextMessages; state.quick_prompts = data.quick_prompts || state.quick_prompts || []; state.overview = data.overview || state.overview || null; state.agent_profile = data.agent_profile || data.agentProfile || state.agent_profile || null; state.active_thread = data.active_thread || data.activeThread || state.active_thread || null; state.threads = data.threads || state.threads || []; state.skills = data.skills || state.skills || []; render(); }
  function replacePendingMessages(tempUserId, tempAssistantId, userMessage, assistantMessage) { var additions = []; if (userMessage) additions.push(userMessage); if (assistantMessage) additions.push(assistantMessage); state.messages = state.messages.filter(function (item) { return item.id !== tempUserId && item.id !== tempAssistantId; }).concat(additions.length ? additions : [{ id: 'agent-' + Date.now(), role: 'assistant', body: 'Claude returned a response, but the chat payload was incomplete.', cards: [], blocks: [], created_at: nowIso(), error: true }]); }

  async function load() { try { setStatus('Loading agent chat…', ''); applyState(payload(await Microgifter.get('/api/ai/merchant-agent-chat.php'))); setStatus('', ''); } catch (error) { setStatus(cleanErrorMessage(error), 'error'); if (!state.messages.length) { state.messages = [{ id: 'load-error-' + Date.now(), role: 'assistant', body: cleanErrorMessage(error), cards: [], blocks: [], created_at: nowIso(), error: true }]; render(); } } }

  async function submit(message) {
    message = String(message || '').trim(); if (!message || inFlight) return;
    var scope = contextValue('[data-agent-chat-scope]', 'overview');
    var days = parseInt(contextValue('[data-agent-chat-days]', '90'), 10) || 90;
    var mode = contextValue('[data-agent-chat-mode]', 'advisor');
    var output = contextValue('[data-agent-chat-output]', 'action_plan');
    var approval = contextValue('[data-agent-chat-approval]', 'advisory');
    var skillKeys = selectedSkills();
    var stamp = Date.now();
    var tempUserId = 'local-user-' + stamp;
    var tempAssistantId = 'local-agent-' + stamp;
    var tempUser = { id: tempUserId, role: 'user', body: message, cards: [], blocks: [], scope: scope, mode: mode, output_type: output, approval_mode: approval, created_at: nowIso() };
    var tempAssistant = { id: tempAssistantId, role: 'assistant', body: 'Reviewing merchant data with ' + skillKeys.map(function (key) { return key === 'merchant_analysis_charts' ? 'Analysis + Charts' : 'Social Campaigns'; }).join(' + ') + '…', cards: [], blocks: [], scope: scope, mode: mode, output_type: output, approval_mode: approval, created_at: nowIso(), pending: true };
    state.messages = state.messages.concat([tempUser, tempAssistant]); render(); resetComposer(); busy(true); closeMenu();
    try { var data = payload(await Microgifter.post('/api/ai/merchant-agent-chat.php', { action: 'send_message', message: message, scope: scope, days: days, mode: mode, output_type: output, approval_mode: approval, skill_keys: skillKeys, thread_id: activeThreadId() })); replacePendingMessages(tempUserId, tempAssistantId, data.user_message || tempUser, data.assistant_message); if (data.state) applyState(data.state, { preserveMessages: true }); else render(); setStatus('Agent reply created.', 'success'); }
    catch (error) { var clean = cleanErrorMessage(error); state.messages = state.messages.map(function (item) { if (item.id !== tempAssistantId) return item; return { id: tempAssistantId, role: 'assistant', body: clean, cards: [{ type: 'diagnostic', title: 'Agent response did not complete', body: 'The message was submitted, but the server did not return a usable agent reply. Check the note above, then retry after fixing the server/API issue.', action_label: 'AI settings', action_url: '/admin-ai.php' }], blocks: [], scope: scope, mode: mode, output_type: output, approval_mode: approval, created_at: nowIso(), error: true }; }); render(); setStatus(clean, 'error'); }
    finally { busy(false); updateSendState(); }
  }

  async function postAction(action, data, success) { setStatus(success || 'Updating agent chat…', ''); try { data = data || {}; data.action = action; var response = payload(await Microgifter.post('/api/ai/merchant-agent-chat.php', data)); applyState(response.state ? response.state : response, { preserveMessages: true }); setStatus(success || 'Agent chat updated.', 'success'); } catch (error) { setStatus(cleanErrorMessage(error), 'error'); } }
  async function saveAgentProfile(button) { var input = root.querySelector('[data-agent-name-input]'); var name = input ? input.value.trim() : ''; if (!name) name = 'Merchant Agent'; state.agent_profile = Object.assign({}, state.agent_profile || {}, { agent_name: name }); renderRail(); setStatus('Saving agent name…', ''); if (button) button.disabled = true; try { var response = payload(await Microgifter.post('/api/ai/merchant-agent-chat.php', { action: 'save_agent_profile', agent_name: name })); if (response.agent_profile) state.agent_profile = response.agent_profile; if (response.state) applyState(response.state, { preserveMessages: true }); else renderRail(); setStatus('Agent name saved.', 'success'); } catch (error) { setStatus(cleanErrorMessage(error), 'error'); } finally { if (button) button.disabled = false; } }
  async function sendCardToReview(button) { var messageId = button.getAttribute('data-message-id') || ''; var cardIndex = parseInt(button.getAttribute('data-card-index') || '-1', 10); if (!messageId || cardIndex < 0) return; button.disabled = true; button.textContent = 'Sending…'; setStatus('Adding card to review queue…', ''); try { var data = payload(await Microgifter.post('/api/ai/merchant-agent-chat-review.php', { message_id: messageId, card_index: cardIndex })); if (data.state) applyState(data.state, { preserveMessages: true }); render(); setStatus('Card added to the Agent Review queue.', 'success'); } catch (error) { button.disabled = false; button.textContent = 'Send to Review Queue'; setStatus(cleanErrorMessage(error), 'error'); } }

  if (form && textarea) { form.addEventListener('submit', function (event) { event.preventDefault(); submit(textarea.value); }); textarea.addEventListener('input', function () { growTextarea(); updateSendState(); }); textarea.addEventListener('keydown', function (event) { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } }); growTextarea(); updateSendState(); }
  root.querySelectorAll('[data-agent-chat-mode],[data-agent-chat-scope],[data-agent-chat-days],[data-agent-chat-output],[data-agent-chat-approval],[data-agent-skill]').forEach(function (element) { element.addEventListener('change', function () { if (element.matches('[data-agent-chat-output]') && element.value === 'social_campaign') { var social = root.querySelector('[data-agent-skill][value="social_campaign_advisor"]'); if (social) social.checked = true; } updateContextSummary(); }); });
  var saveProfile = root.querySelector('[data-agent-save-profile]'); if (saveProfile) saveProfile.addEventListener('click', function () { saveAgentProfile(saveProfile); });
  var threadSelect = root.querySelector('[data-agent-thread-select]'); if (threadSelect) threadSelect.addEventListener('change', function () { postAction('load_thread', { thread_id: threadSelect.value }, 'Thread loaded.'); });
  var newThread = root.querySelector('[data-agent-new-thread]'); if (newThread) newThread.addEventListener('click', function () { postAction('create_thread', { title: 'Current chat' }, 'New chat started.'); });
  var saveThread = root.querySelector('[data-agent-save-thread]'); if (saveThread) saveThread.addEventListener('click', function () { postAction('save_thread', { thread_id: activeThreadId() }, 'Thread saved.'); });
  var archiveThread = root.querySelector('[data-agent-archive-thread]'); if (archiveThread) archiveThread.addEventListener('click', function () { postAction('archive_thread', { thread_id: activeThreadId() }, 'Thread archived.'); });
  var clearThread = root.querySelector('[data-agent-clear-thread]'); if (clearThread) clearThread.addEventListener('click', function () { postAction('clear_thread', { thread_id: activeThreadId() }, 'Chat history cleared.'); });
  if (menuToggle) menuToggle.addEventListener('click', function (event) { event.preventDefault(); toggleMenu(); });
  document.addEventListener('click', function (event) { if (menu && !menu.hidden && !event.target.closest('[data-agent-context-menu]') && !event.target.closest('[data-agent-context-toggle]')) closeMenu(); });
  root.addEventListener('click', function (event) {
    var prompt = event.target.closest && event.target.closest('[data-agent-chat-prompts] button');
    if (prompt && textarea) { textarea.value = prompt.textContent.trim(); textarea.focus(); growTextarea(); updateSendState(); }
    var insert = event.target.closest && event.target.closest('[data-agent-context-insert]');
    if (insert && textarea) { var text = insert.getAttribute('data-agent-context-insert') || insert.textContent.trim(); textarea.value = (textarea.value.trim() ? textarea.value.trim() + '\n\n' : '') + text; textarea.focus(); growTextarea(); updateSendState(); closeMenu(); }
    var refresh = event.target.closest && event.target.closest('[data-agent-chat-refresh]'); if (refresh) load();
    var review = event.target.closest && event.target.closest('[data-agent-chat-review]'); if (review) sendCardToReview(review);
    var cardStatus = event.target.closest && event.target.closest('[data-agent-card-status]'); if (cardStatus) { cardStatus.textContent = 'Saved'; cardStatus.disabled = true; setStatus('Card action saved locally for this session.', 'success'); }
  });

  updateContextSummary();
  load();
});

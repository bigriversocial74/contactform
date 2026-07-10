(() => {
  'use strict';

  const root = document.querySelector('[data-delivery-operations]');
  if (!root) return;

  const endpoint = '/api/admin/delivery-operations.php';
  const canManage = root.dataset.canManage === 'true';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const refreshButton = root.querySelector('[data-delivery-refresh]');
  const banner = root.querySelector('[data-delivery-banner]');
  const scoreNode = root.querySelector('[data-delivery-score]');
  const jobsBody = root.querySelector('[data-delivery-jobs]');
  const runsNode = root.querySelector('[data-delivery-runs]');
  const filters = root.querySelector('[data-delivery-filters]');
  const workerDetails = root.querySelector('[data-delivery-worker-details]');
  const clearPauseForm = root.querySelector('[data-delivery-clear-pause]');
  const modal = document.querySelector('[data-delivery-job-modal]');
  const modalTitle = modal?.querySelector('[data-delivery-modal-title]');
  const modalBody = modal?.querySelector('[data-delivery-modal-body]');
  const modalActions = modal?.querySelector('[data-delivery-modal-actions]');
  let activeJob = null;
  let lastFocus = null;
  let pausePhrase = '';
  let loadSequence = 0;

  const stateLabels = {
    queued: 'Queued', processing: 'Processing', retry_scheduled: 'Retry scheduled',
    provider_accepted: 'Provider accepted', sent: 'Sent', delivered: 'Delivered',
    failed: 'Failed', dead_letter: 'Dead letter', cancelled: 'Cancelled', suppressed: 'Suppressed'
  };

  function el(tag, attrs = {}, text = '') {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
      if (key === 'class') node.className = String(value);
      else if (key === 'dataset' && value && typeof value === 'object') {
        Object.entries(value).forEach(([dataKey, dataValue]) => { node.dataset[dataKey] = String(dataValue); });
      } else if (key === 'hidden') node.hidden = Boolean(value);
      else if (key.startsWith('aria-')) node.setAttribute(key, String(value));
      else if (value !== null && value !== undefined) node.setAttribute(key, String(value));
    });
    if (text !== '') node.textContent = String(text);
    return node;
  }

  function formatNumber(value) { return Number(value || 0).toLocaleString(); }
  function formatAge(seconds) {
    if (seconds === null || seconds === undefined || Number.isNaN(Number(seconds))) return '—';
    const total = Math.max(0, Number(seconds));
    if (total < 60) return `${Math.round(total)}s`;
    if (total < 3600) return `${Math.round(total / 60)}m`;
    if (total < 86400) return `${(total / 3600).toFixed(total < 7200 ? 1 : 0)}h`;
    return `${(total / 86400).toFixed(total < 172800 ? 1 : 0)}d`;
  }
  function formatDate(value) {
    if (!value) return '—';
    const parsed = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    if (Number.isNaN(parsed.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }

  async function request(url, options = {}) {
    const { headers: customHeaders = {}, ...requestOptions } = options;
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...requestOptions,
      headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...customHeaders },
    });
    let payload = null;
    try { payload = await response.json(); } catch { payload = null; }
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || `Delivery request failed (${response.status}).`);
    return payload.data;
  }

  function setBanner(status, title, message) {
    if (!banner) return;
    banner.className = `mg-delivery-banner is-${status}`;
    const strong = banner.querySelector('strong');
    const paragraph = banner.querySelector('p');
    if (strong) strong.textContent = title;
    if (paragraph) paragraph.textContent = message;
  }
  function setKpi(key, value) { const node = root.querySelector(`[data-delivery-kpi="${key}"]`); if (node) node.textContent = String(value); }

  function renderSummary(summary) {
    const schemaReady = Boolean(summary?.schema_ready);
    const score = Number(summary?.score || 0);
    if (scoreNode) scoreNode.textContent = `${score}/100`;
    if (!schemaReady) { setBanner('critical', 'Delivery migration required', 'Install the delivery operations SQL migration before enabling the worker.'); return; }

    const paused = Boolean(summary.state?.paused);
    const dead = Number(summary.queue?.dead_letter || 0);
    if (paused) setBanner('critical', 'Delivery worker paused', summary.state?.pause_reason || 'Operator review is required.');
    else if (dead > 0) setBanner('warning', 'Dead-letter review required', `${formatNumber(dead)} delivery job${dead === 1 ? '' : 's'} require operator review.`);
    else if (!summary.configuration?.worker_enabled) setBanner('notice', 'Delivery worker is disabled', 'Inbox and in-app delivery remain active. External job processing is safely disabled.');
    else setBanner('healthy', 'Delivery operations healthy', 'The queue, capacity controls, retry policy, and worker safety gates are ready.');

    setKpi('due', formatNumber(summary.queue?.due));
    setKpi('processing', formatNumber(summary.queue?.processing));
    setKpi('dead_letter', formatNumber(summary.queue?.dead_letter));
    setKpi('oldest', formatAge(summary.queue?.oldest_pending_age_seconds));
    setKpi('batch_size', formatNumber(summary.configuration?.batch_size));
    setKpi('worker', paused ? 'Paused' : (summary.configuration?.worker_enabled ? 'Enabled' : 'Disabled'));

    const channelCounts = summary.queue?.by_channel_status || {};
    ['in_app', 'email', 'sms', 'push'].forEach((channel) => {
      const card = root.querySelector(`[data-delivery-channel="${channel}"]`);
      if (!card) return;
      const counts = channelCounts[channel] || {};
      const queued = Number(counts.queued || 0) + Number(counts.retry_scheduled || 0) + Number(counts.processing || 0);
      const accepted = Number(counts.provider_accepted || 0) + Number(counts.sent || 0);
      const delivered = Number(counts.delivered || 0);
      const failed = Number(counts.failed || 0) + Number(counts.dead_letter || 0);
      card.querySelector('[data-channel-stat="queued"]').textContent = formatNumber(queued);
      card.querySelector('[data-channel-stat="accepted"]').textContent = formatNumber(accepted);
      card.querySelector('[data-channel-stat="delivered"]').textContent = formatNumber(delivered);
      card.querySelector('[data-channel-stat="failed"]').textContent = formatNumber(failed);
      const headerStatus = card.querySelector('header span');
      const readiness = summary.channel_readiness?.[channel] || {};
      const enabled = channel === 'in_app' ? true : Boolean(readiness.enabled);
      const ready = channel === 'in_app' ? true : Boolean(readiness.ready);
      headerStatus.textContent = failed > 0 ? 'Review' : (!enabled ? 'Disabled' : (ready ? 'Ready' : 'Configure'));
      card.classList.toggle('is-enabled', enabled && ready && failed === 0);
      card.classList.toggle('is-warning', enabled && (!ready || failed > 0));
      card.classList.toggle('is-disabled', !enabled);
    });

    if (workerDetails) {
      workerDetails.replaceChildren();
      const details = [
        ['Worker flag', summary.configuration?.worker_enabled ? 'Enabled' : 'Disabled'],
        ['Runtime budget', `${formatNumber(summary.configuration?.max_runtime_seconds)} seconds`],
        ['Lease window', `${formatNumber(summary.configuration?.lease_seconds)} seconds`],
        ['Maximum attempts', formatNumber(summary.configuration?.max_attempts)],
        ['Per-user fairness', `${formatNumber(summary.configuration?.max_per_user_per_run)} per run`],
        ['Per-merchant fairness', `${formatNumber(summary.configuration?.max_per_merchant_per_run)} per run`],
        ['Failure auto-pause', `${formatNumber(summary.configuration?.failure_pause_percent)}%`],
      ];
      details.forEach(([label, value]) => { const card = el('article'); card.append(el('span', {}, label), el('strong', {}, value)); workerDetails.append(card); });
    }
    if (clearPauseForm) {
      clearPauseForm.hidden = !paused;
      const input = clearPauseForm.querySelector('input[name="acknowledgement"]');
      if (input && pausePhrase) input.placeholder = pausePhrase;
    }
  }

  function statusPill(status) { return el('span', { class: `mg-delivery-status is-${status}` }, stateLabels[status] || status); }
  function renderJobs(jobs) {
    if (!jobsBody) return;
    jobsBody.replaceChildren();
    if (!Array.isArray(jobs) || jobs.length === 0) {
      const row = el('tr'); row.append(el('td', { colspan: '7', class: 'mg-delivery-empty' }, 'No delivery jobs match these filters.')); jobsBody.append(row); return;
    }
    jobs.forEach((job) => {
      const row = el('tr');
      const statusCell = el('td'); statusCell.append(statusPill(job.status));
      const channel = el('td', {}, String(job.channel || '').replace('_', '-').toUpperCase());
      const notification = el('td'); notification.append(el('strong', {}, job.notification?.title || 'Notification')); notification.append(el('small', {}, job.notification?.type || 'system'));
      const recipient = el('td', {}, job.recipient?.label || 'Member');
      const attempts = el('td', {}, `${formatNumber(job.attempt_count)}/${formatNumber(job.max_attempts)}`);
      const updated = el('td', {}, formatDate(job.updated_at));
      const actionCell = el('td');
      const button = el('button', { type: 'button', class: 'mg-btn mg-btn-ghost mg-delivery-row-action' }, 'Review');
      button.addEventListener('click', () => openJob(job, button)); actionCell.append(button);
      row.append(statusCell, channel, notification, recipient, attempts, updated, actionCell); jobsBody.append(row);
    });
  }

  function detailRow(label, value) { const wrapper = el('div', { class: 'mg-delivery-modal-row' }); wrapper.append(el('span', {}, label), el('strong', {}, value || '—')); return wrapper; }
  function actionButton(label, action, className = 'mg-btn mg-btn-soft') {
    const button = el('button', { type: 'button', class: className }, label);
    button.addEventListener('click', async () => {
      if (!activeJob) return; button.disabled = true;
      try { await mutate({ action, job_id: activeJob.id }); closeModal(); await load(); }
      catch (error) { button.disabled = false; setModalError(error.message); }
    });
    return button;
  }
  function setModalError(message) {
    if (!modalBody) return;
    let error = modalBody.querySelector('[data-delivery-modal-error]');
    if (!error) { error = el('p', { class: 'mg-delivery-modal-error', dataset: { deliveryModalError: 'true' } }); modalBody.prepend(error); }
    error.textContent = message;
  }

  function openJob(job, trigger) {
    if (!modal || !modalBody || !modalTitle) return;
    activeJob = job; lastFocus = trigger || document.activeElement; modalTitle.textContent = job.notification?.title || 'Delivery job'; modalBody.replaceChildren();
    const wrapper = el('div', { class: 'mg-delivery-job-detail' });
    const grid = el('div', { class: 'mg-delivery-job-detail-grid' });
    grid.append(detailRow('Status', stateLabels[job.status] || job.status),detailRow('Channel', String(job.channel || '').toUpperCase()),detailRow('Recipient', job.recipient?.label || 'Member'),detailRow('Attempts', `${job.attempt_count}/${job.max_attempts}`),detailRow('Provider', job.provider || 'Not assigned'),detailRow('Created', formatDate(job.created_at)),detailRow('Next attempt', formatDate(job.next_attempt_at)),detailRow('Accepted', formatDate(job.accepted_at)),detailRow('Delivered', formatDate(job.delivered_at)),detailRow('Failure code', job.failure_code || 'None'));
    wrapper.append(grid);
    if (job.failure_message) wrapper.append(el('p', { class: 'mg-delivery-job-error' }, job.failure_message));
    if (job.notification?.action_url) wrapper.append(el('a', { href: job.notification.action_url, class: 'mg-btn mg-btn-ghost' }, 'Open notification destination'));
    modalBody.append(wrapper);
    if (modalActions) {
      modalActions.replaceChildren();
      if (canManage) {
        if (job.status === 'dead_letter') modalActions.append(actionButton('Requeue dead letter', 'requeue_dead_letter', 'mg-btn mg-btn-primary'));
        else if (['failed', 'retry_scheduled', 'suppressed'].includes(job.status)) modalActions.append(actionButton('Retry now', 'retry', 'mg-btn mg-btn-primary'));
        if (!['delivered', 'cancelled'].includes(job.status)) modalActions.append(actionButton('Cancel job', 'cancel', 'mg-btn mg-btn-danger'));
      }
    }
    modal.hidden = false; document.body.classList.add('mg-modal-open'); requestAnimationFrame(() => modal.querySelector('[data-modal-close]')?.focus());
  }
  function closeModal() {
    if (!modal) return;
    modal.hidden = true; document.body.classList.remove('mg-modal-open'); activeJob = null;
    if (lastFocus instanceof HTMLElement) lastFocus.focus(); lastFocus = null;
  }

  function renderRuns(runs) {
    if (!runsNode) return; runsNode.replaceChildren();
    if (!Array.isArray(runs) || runs.length === 0) { runsNode.append(el('p', { class: 'mg-delivery-empty' }, 'No delivery worker runs have been recorded.')); return; }
    runs.forEach((run) => {
      const card = el('article', { class: 'mg-delivery-run' }); const header = el('header');
      header.append(el('strong', {}, `${String(run.mode || 'observe').toUpperCase()} · ${String(run.status || 'unknown').replaceAll('_', ' ')}`), el('span', {}, formatDate(run.started_at)));
      const metrics = el('div');
      [['Processed',run.processed_count],['Delivered',run.delivered_count],['Accepted',run.accepted_count],['Retry',run.retry_count],['Dead letter',run.dead_letter_count],['Failed',run.failed_count]].forEach(([label,value]) => metrics.append(detailRow(label,formatNumber(value))));
      card.append(header, metrics); runsNode.append(card);
    });
  }

  async function mutate(input) { return request(endpoint, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: JSON.stringify({ ...input, csrf_token: csrf }) }); }
  async function load() {
    const sequence = ++loadSequence; if (refreshButton) refreshButton.disabled = true;
    setBanner('loading', 'Loading delivery health', 'Reading the current queue and worker state.');
    const params = new URLSearchParams();
    if (filters) { const data = new FormData(filters); for (const [key,value] of data.entries()) if (String(value).trim() !== '') params.set(key,String(value)); }
    try {
      const data = await request(`${endpoint}${params.toString() ? `?${params}` : ''}`);
      if (sequence !== loadSequence) return;
      pausePhrase = data.pause_acknowledgement || ''; renderSummary(data.summary || {}); renderJobs(data.jobs || []); renderRuns(data.summary?.recent_runs || []);
    } catch (error) {
      if (sequence !== loadSequence) return;
      setBanner('critical', 'Delivery operations unavailable', error.message);
      if (jobsBody) { jobsBody.replaceChildren(); const row = el('tr'); row.append(el('td',{colspan:'7',class:'mg-delivery-empty'},error.message)); jobsBody.append(row); }
    } finally { if (sequence === loadSequence && refreshButton) refreshButton.disabled = false; }
  }

  refreshButton?.addEventListener('click', load);
  filters?.addEventListener('submit', (event) => { event.preventDefault(); load(); });
  clearPauseForm?.addEventListener('submit', async (event) => {
    event.preventDefault(); const button = clearPauseForm.querySelector('button'); const acknowledgement = new FormData(clearPauseForm).get('acknowledgement') || ''; if (!button) return;
    button.disabled = true;
    try { await mutate({ action: 'clear_pause', acknowledgement: String(acknowledgement) }); clearPauseForm.reset(); await load(); }
    catch (error) { setBanner('critical', 'Pause could not be cleared', error.message); }
    finally { button.disabled = false; }
  });
  modal?.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', closeModal));
  document.addEventListener('keydown', (event) => {
    if (!modal || modal.hidden) return;
    if (event.key === 'Escape') { closeModal(); return; }
    if (event.key !== 'Tab') return;
    const focusable = [...modal.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')].filter((node) => node instanceof HTMLElement && !node.hidden && node.offsetParent !== null);
    if (focusable.length === 0) { event.preventDefault(); return; }
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  load();
})();
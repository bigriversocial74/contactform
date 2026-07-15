(() => {
  'use strict';

  const root = document.querySelector('[data-admin-hosted-games]');
  if (!root || root.dataset.canManage !== '1') return;

  const csrf = String(root.dataset.csrf || '');
  const games = new Map();
  let busy = false;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
  const canEnableRuntime = (game) => {
    const readiness = game?.readiness || {};
    return game?.integration_status === 'ready'
      && Boolean(readiness.release_ready)
      && Boolean(readiness.database_ready)
      && Boolean(readiness.api_key_ready)
      && Boolean(readiness.program_ready)
      && Boolean(readiness.campaign_ready)
      && Boolean(readiness.reward_ready)
      && Boolean(readiness.api_credential_ready)
      && Boolean(readiness.webhook_secret_ready)
      && Boolean(readiness.state_secret_ready);
  };

  async function request(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const payload = await response.json().catch(() => ({}));
    const data = payload && typeof payload.data === 'object' ? payload.data : payload;
    if (!response.ok || payload.ok === false) throw new Error(String(payload.message || data.message || 'Hosted Games request failed.'));
    return data;
  }

  function switchMarkup(game, compact = false) {
    const enabled = game?.status === 'active';
    const readiness = game?.readiness || {};
    const canEnable = canEnableRuntime(game);
    const disabled = game?.status === 'archived' || (!enabled && !canEnable);
    const detail = game?.status === 'archived'
      ? 'Archived'
      : enabled
        ? 'Live runtime and reward issuance'
        : canEnable
          ? 'Ready to enable'
          : 'Complete setup before enabling';

    return `<div class="hgm-runtime-control${compact ? ' is-compact' : ''}" data-hgm-runtime-control="${escapeHtml(game?.id || '')}">
      <span class="hgm-runtime-copy"><strong>Game enabled</strong><small>${escapeHtml(detail)}</small></span>
      <button class="hgm-runtime-switch" type="button" role="switch" aria-label="${enabled ? 'Disable' : 'Enable'} ${escapeHtml(game?.name || 'hosted game')}" aria-checked="${enabled ? 'true' : 'false'}" data-hgm-runtime-toggle="${escapeHtml(game?.id || '')}" ${disabled ? 'disabled' : ''}></button>
    </div>`;
  }

  function managedMarkup(game) {
    const readiness = game?.readiness || {};
    const values = [
      ['Configuration', game?.program?.id ? 'Distribution Program' : 'Not configured', Boolean(game?.program?.id)],
      ['API credential', readiness.api_credential_ready ? 'Managed' : 'Required', Boolean(readiness.api_credential_ready)],
      ['Webhook secret', readiness.webhook_secret_ready ? 'Managed' : 'Required', Boolean(readiness.webhook_secret_ready)],
      ['State secret', readiness.state_secret_ready ? 'Managed' : 'Required', Boolean(readiness.state_secret_ready)],
    ];
    return `<div class="hgm-runtime-managed">${values.map(([label, value, ready]) => `<div><span>${escapeHtml(label)}</span><strong class="${ready ? 'is-ready' : ''}">${escapeHtml(value)}</strong></div>`).join('')}</div>
      <p class="hgm-runtime-note">The API key, webhook secret, campaign, and reward template are managed server-side from the selected Distribution Program. Raw credentials are never displayed or sent to the game browser.</p>`;
  }

  function decorateRows() {
    root.querySelectorAll('[data-admin-game-row]').forEach((row) => {
      const manage = row.querySelector('[data-admin-game]');
      const actions = row.querySelector('.hgm-admin-actions');
      const game = manage ? games.get(String(manage.dataset.adminGame || '')) : null;
      if (!actions || !game) return;
      const existing = actions.querySelector('[data-hgm-runtime-control]');
      if (existing?.dataset.hgmRuntimeControl === String(game.id || '')) return;
      existing?.remove();
      actions.insertAdjacentHTML('afterbegin', switchMarkup(game, true));
    });
  }

  function syncModal() {
    const modal = root.querySelector('[data-hgm-admin-modal]');
    if (!modal || modal.hidden) return;
    const gameId = String(root.querySelector('[data-hgm-admin-integration-form] [name="game_id"]')?.value || '');
    const game = games.get(gameId);
    if (!game) return;

    const readiness = root.querySelector('[data-hgm-admin-readiness]');
    if (readiness) {
      const existing = readiness.parentElement?.querySelector('[data-hgm-runtime-modal]');
      if (existing?.dataset.hgmRuntimeModal !== gameId) {
        existing?.remove();
        readiness.insertAdjacentHTML('afterend', `<div data-hgm-runtime-modal="${escapeHtml(gameId)}">${managedMarkup(game)}${switchMarkup(game)}</div>`);
      }
    }

    const publish = root.querySelector('[data-hgm-admin-publish]');
    const pause = root.querySelector('[data-hgm-admin-pause]');
    if (publish) publish.hidden = true;
    if (pause) pause.hidden = true;
  }

  async function load() {
    const data = await request('/api/admin/hosted-games.php', { headers: { Accept: 'application/json' } });
    games.clear();
    (Array.isArray(data.games) ? data.games : []).forEach((game) => games.set(String(game.id || ''), game));
    decorateRows();
    syncModal();
  }

  async function toggle(button) {
    if (busy || button.disabled) return;
    const gameId = String(button.dataset.hgmRuntimeToggle || '');
    const game = games.get(gameId);
    if (!game) return;
    const enabled = game.status !== 'active';

    busy = true;
    button.disabled = true;
    try {
      await request('/api/admin/hosted-game-runtime.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ csrf_token: csrf, game_id: gameId, enabled })
      });
      window.location.reload();
    } catch (error) {
      window.alert(error instanceof Error ? error.message : 'Unable to change the game runtime status.');
      button.disabled = false;
      busy = false;
    }
  }

  root.addEventListener('click', (event) => {
    const button = event.target.closest('[data-hgm-runtime-toggle]');
    if (button) {
      event.preventDefault();
      event.stopPropagation();
      void toggle(button);
      return;
    }
    if (event.target.closest('[data-admin-game]')) window.setTimeout(syncModal, 0);
  }, true);

  const observer = new MutationObserver(() => {
    decorateRows();
    syncModal();
  });
  observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });

  load().catch((error) => console.error('Hosted Games runtime controls:', error));
})();

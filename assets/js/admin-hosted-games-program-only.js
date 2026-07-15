(() => {
  'use strict';

  const root = document.querySelector('[data-admin-hosted-games]');
  const form = root?.querySelector('[data-hgm-admin-integration-form]');
  if (!root || !form) return;

  const statusNode = root.querySelector('[data-hgm-admin-integration-status]');
  const submitButton = form.querySelector('button[type="submit"]');
  const csrf = String(root.dataset.csrf || '');

  function setStatus(message, type = '') {
    if (!statusNode) return;
    statusNode.textContent = String(message || '');
    statusNode.classList.toggle('is-error', type === 'error');
    statusNode.classList.toggle('is-success', type === 'success');
  }

  async function configureProgram() {
    const gameId = String(form.elements.game_id?.value || '').trim();
    const programId = String(form.elements.program_id?.value || '').trim();
    if (!gameId) {
      setStatus('Save the hosted game first.', 'error');
      return;
    }
    if (!programId) {
      setStatus('Select a Distribution Program.', 'error');
      return;
    }

    if (submitButton) submitButton.disabled = true;
    setStatus('Applying the program campaign and reward inventory…');
    try {
      const response = await fetch('/api/admin/hosted-game-integration.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ csrf_token: csrf, game_id: gameId, program_id: programId })
      });
      const payload = await response.json().catch(() => ({}));
      const data = payload && typeof payload.data === 'object' ? payload.data : payload;
      if (!response.ok || payload.ok === false) {
        throw new Error(String(payload.message || data.message || 'Unable to configure the Distribution Program.'));
      }

      const campaign = String(data.campaign?.title || 'connected campaign');
      const reward = String(data.reward?.title || 'active reward inventory');
      setStatus(`${campaign} and ${reward} connected automatically.`, 'success');
      window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Unable to configure the Distribution Program.', 'error');
      if (submitButton) submitButton.disabled = false;
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    event.stopImmediatePropagation();
    void configureProgram();
  }, true);
})();

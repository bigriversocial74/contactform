(() => {
  'use strict';

  const csrfRoot = document.querySelector('[data-investor-pipeline],[data-investor-governance],[data-investment-closing],[data-investment-wizard]');
  if (!csrfRoot) return;
  const csrf = csrfRoot.dataset.csrfToken || '';
  const originalFetch = window.fetch.bind(window);

  const parseBody = (body) => {
    if (typeof body !== 'string') return null;
    try { return JSON.parse(body); } catch { return null; }
  };

  window.fetch = async (input, init = {}) => {
    const url = typeof input === 'string' ? input : String(input?.url || '');
    const payload = parseBody(init.body);
    if (payload && url.includes('/api/admin/investor-pipeline.php') && payload.action === 'save_publication') {
      const field = document.querySelector('[data-publication-form] [name="change_reason"]');
      payload.change_reason = String(field?.value || '').trim() || `Publication ${payload.publication_status || 'settings'} revision`;
      init = { ...init, body: JSON.stringify(payload) };
    }
    if (payload && url.includes('/api/admin/investment-wizard.php') && payload.action === 'save_documents' && Array.isArray(payload.items)) {
      payload.items = payload.items.map((item) => ({
        ...item,
        change_reason: String(item.change_reason || '').trim() || `${item.title || 'Investment document'} changed to ${item.status || 'updated'}`,
      }));
      init = { ...init, body: JSON.stringify(payload) };
    }
    return originalFetch(input, init);
  };

  const hardenPipeline = () => {
    document.querySelectorAll('[data-interest-form]').forEach((form) => {
      ['signed', 'funded'].forEach((name) => {
        const input = form.querySelector(`[name="${name}"]`);
        if (!input || input.dataset.auditLocked === '1') return;
        input.readOnly = true;
        input.dataset.auditLocked = '1';
        input.title = 'Controlled by Closing Command Center maker/checker verification.';
        input.closest('label')?.classList.add('is-audit-readonly');
      });
      if (!form.querySelector('[data-audit-money-note]')) {
        const note = document.createElement('p');
        note.dataset.auditMoneyNote = '1';
        note.className = 'is-wide mg-investment-help';
        note.textContent = 'Signed and funded totals are read-only here. Use Closing Command Center maker/checker verification.';
        form.querySelector('.mg-investment-actions')?.before(note);
      }
    });

    document.querySelectorAll('[data-publication-form]').forEach((form) => {
      if (form.querySelector('[name="change_reason"]')) return;
      const label = document.createElement('label');
      label.className = 'is-wide';
      label.innerHTML = '<span>Revision reason</span><input name="change_reason" maxlength="500" minlength="8" required placeholder="What changed and why?">';
      form.querySelector('.mg-investment-actions')?.before(label);
    });
  };

  let governanceBusy = false;
  const hardenGovernance = async () => {
    const root = document.querySelector('[data-investor-governance]');
    if (!root || root.dataset.canPublish !== '1' || governanceBusy) return;
    const rows = root.querySelectorAll('[data-consent-list] tr');
    if (!rows.length || root.querySelector('[data-audit-consent-visibility]')) return;
    governanceBusy = true;
    try {
      const round = root.querySelector('[data-governance-round]')?.value || '';
      const response = await originalFetch(`/api/admin/investor-governance.php?${new URLSearchParams({ round_id: round })}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok || !payload?.ok) return;
      const consents = new Map((payload.data?.consents || []).map((item) => [String(item.public_id), item]));
      root.querySelectorAll('[data-edit-consent]').forEach((editButton) => {
        const id = String(editButton.dataset.editConsent || '');
        const consent = consents.get(id);
        if (!consent || consent.status !== 'executed' || editButton.parentElement?.querySelector('[data-audit-consent-visibility]')) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'mg-btn mg-btn-soft';
        button.dataset.auditConsentVisibility = id;
        button.textContent = Number(consent.investor_visible) === 1 ? 'Hide from Portal' : 'Show in Portal';
        button.addEventListener('click', async () => {
          button.disabled = true;
          try {
            const result = await originalFetch('/api/admin/investor-governance.php', {
              method: 'POST', credentials: 'same-origin',
              headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
              body: JSON.stringify({ action: 'set_consent_visibility', round_id: round, consent_id: id, investor_visible: Number(consent.investor_visible) !== 1 }),
            });
            const data = await result.json();
            if (!result.ok || !data?.ok) throw new Error(data?.message || 'Unable to change consent visibility.');
            location.reload();
          } catch (error) {
            button.disabled = false;
            window.alert(error.message);
          }
        });
        editButton.parentElement?.appendChild(button);
      });
    } finally {
      governanceBusy = false;
    }
  };

  let closingChecked = false;
  const hardenClosing = async () => {
    const root = document.querySelector('[data-investment-closing]');
    if (!root || closingChecked) return;
    const stats = root.querySelector('[data-closing-stats]');
    if (!stats || !stats.children.length) return;
    closingChecked = true;
    try {
      const round = root.querySelector('[data-closing-round]')?.value || '';
      const response = await originalFetch(`/api/admin/investment-closing.php?${new URLSearchParams({ action: 'dashboard', round_id: round })}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok || !payload?.ok) return;
      const summary = payload.data?.summary || {};
      const unproven = Number(summary.unproven_signed || 0) + Number(summary.unproven_funded || 0);
      if (unproven > 0 && !root.querySelector('[data-audit-provenance-warning]')) {
        const warning = document.createElement('div');
        warning.dataset.auditProvenanceWarning = '1';
        warning.className = 'mg-investment-notice';
        warning.dataset.type = 'error';
        warning.textContent = `${unproven} legacy signed/funded record${unproven === 1 ? '' : 's'} require maker/checker verification before closing or portal access.`;
        stats.after(warning);
      }
    } catch { /* The primary runtime owns error display. */ }
  };

  const run = () => {
    hardenPipeline();
    hardenGovernance();
    hardenClosing();
  };

  const observer = new MutationObserver(run);
  observer.observe(document.body, { childList: true, subtree: true });
  run();
})();

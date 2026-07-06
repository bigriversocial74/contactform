(() => {
  'use strict';

  const root = document.querySelector('[data-admin-users]');
  if (!root) return;

  const styleText = [
    '.mg-admin-user-stamps-section{border:1px solid #dbe3ef;border-radius:16px;background:#fff;overflow:hidden}',
    '.mg-admin-user-stamps-section>header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:16px;border-bottom:1px solid #e5eaf1;background:#f8fafc}',
    '.mg-admin-user-stamps-section h3{margin:0;color:#0f172a;font-size:.95rem}.mg-admin-user-stamps-section p{margin:.25rem 0 0;color:#64748b;font-size:.72rem;line-height:1.45}',
    '.mg-admin-stamps-body{display:grid;gap:14px;padding:16px}',
    '.mg-admin-stamps-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}',
    '.mg-admin-stamp-card{display:grid;gap:6px;padding:12px;border:1px solid #edf1f5;border-radius:13px;background:#fbfdff;min-width:0}',
    '.mg-admin-stamp-card span{color:#64748b;font-size:.61rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.mg-admin-stamp-card strong{color:#0f172a;font-size:1.1rem;letter-spacing:-.02em}',
    '.mg-admin-stamp-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px}',
    '.mg-admin-stamp-notice{min-height:18px;color:#64748b;font-size:.7rem;font-weight:850}.mg-admin-stamp-notice[data-type="success"]{color:#047857}.mg-admin-stamp-notice[data-type="error"]{color:#b42318}',
    '.mg-admin-stamp-form{display:grid;gap:10px;padding:12px;border:1px solid #e5eaf1;border-radius:13px;background:#fbfdff}',
    '.mg-admin-stamp-form-grid{display:grid;grid-template-columns:130px 1fr;gap:10px}.mg-admin-stamp-form label{display:grid;gap:5px;color:#475569;font-size:.62rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em}',
    '.mg-admin-stamp-form input,.mg-admin-stamp-form select,.mg-admin-stamp-form textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#0f172a;font:inherit;font-size:.72rem;padding:9px}.mg-admin-stamp-form textarea{min-height:70px;resize:vertical;text-transform:none;letter-spacing:0}',
    '.mg-admin-stamp-ledger{display:grid;gap:8px}.mg-admin-stamp-entry{display:grid;grid-template-columns:88px 1fr auto;gap:10px;align-items:start;padding:11px;border:1px solid #edf1f5;border-radius:12px;background:#fff}',
    '.mg-admin-stamp-entry strong{color:#0f172a;font-size:.78rem}.mg-admin-stamp-entry p{margin:0;color:#64748b;font-size:.66rem;line-height:1.35}.mg-admin-stamp-entry small{color:#64748b;font-size:.62rem;font-weight:800}.mg-admin-stamp-delta{font-weight:1000;color:#047857}.mg-admin-stamp-delta.is-negative{color:#b42318}',
    '.mg-admin-stamp-empty{padding:12px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;color:#64748b;font-size:.72rem;line-height:1.45}',
    '@media(max-width:760px){.mg-admin-stamps-grid,.mg-admin-stamp-form-grid{grid-template-columns:1fr}.mg-admin-stamp-entry{grid-template-columns:1fr}.mg-admin-stamp-toolbar{align-items:stretch}.mg-admin-stamp-toolbar .mg-btn{width:100%;justify-content:center}}'
  ].join('');

  if (!document.querySelector('[data-admin-user-stamps-style]')) {
    const style = document.createElement('style');
    style.dataset.adminUserStampsStyle = '1';
    style.textContent = styleText;
    document.head.appendChild(style);
  }

  const state = { user: null, drawer: null, section: null, ledger: null, busy: false };
  const make = (tag, cls = '', text = '') => {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== '') node.textContent = text;
    return node;
  };
  const clear = (node) => { while (node && node.firstChild) node.removeChild(node.firstChild); };
  const num = (value) => Number(value || 0).toLocaleString();
  const readable = (value) => String(value || '—').replace(/[_-]+/g, ' ');
  const when = (value) => {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(date);
  };
  const apiGet = async (path) => {
    if (window.Microgifter && typeof Microgifter.get === 'function') return Microgifter.get(path);
    const response = await fetch(path, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Request failed.');
    return payload;
  };
  const apiPost = async (path, body) => {
    if (window.Microgifter && typeof Microgifter.post === 'function') return Microgifter.post(path, body);
    const response = await fetch(path, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Request failed.');
    return payload;
  };

  function setNotice(message, type = '') {
    const notice = state.section?.querySelector('[data-admin-stamp-notice]');
    if (!notice) return;
    notice.textContent = message || '';
    if (type) notice.dataset.type = type;
    else delete notice.dataset.type;
  }

  function setBusy(on) {
    state.busy = !!on;
    state.section?.querySelectorAll('button,input,select,textarea').forEach((control) => { control.disabled = !!on; });
  }

  function card(label, value) {
    const node = make('article', 'mg-admin-stamp-card');
    node.append(make('span', '', label), make('strong', '', num(value)));
    return node;
  }

  function renderBalance(target, ledger) {
    clear(target);
    const balance = ledger?.balance || {};
    target.append(
      card('Available', balance.available),
      card('Included monthly', balance.included_monthly_stamps),
      card('Purchased', balance.purchased_stamps),
      card('Used', balance.used_stamps),
      card('Voided', balance.voided_stamps)
    );
  }

  function entryRow(entry) {
    const row = make('article', 'mg-admin-stamp-entry');
    const delta = Number(entry.delta || 0);
    const deltaNode = make('strong', 'mg-admin-stamp-delta' + (delta < 0 ? ' is-negative' : ''), `${delta >= 0 ? '+' : ''}${num(delta)}`);
    const copy = make('div');
    const meta = [
      readable(entry.entry_type),
      entry.source_type ? `source ${entry.source_type}` : '',
      entry.reason_code ? `reason ${entry.reason_code}` : '',
      entry.reference ? `ref ${entry.reference}` : '',
      `balance ${num(entry.balance_after)}`,
    ].filter(Boolean).join(' · ');
    copy.append(make('strong', '', entry.action_key || entry.source_type || entry.entry_type || 'Stamp ledger entry'), make('p', '', meta));
    if (entry.note) copy.appendChild(make('p', '', entry.note));
    row.append(deltaNode, copy, make('small', '', when(entry.created_at)));
    return row;
  }

  function renderEntries(target, ledger) {
    clear(target);
    const entries = Array.isArray(ledger?.entries) ? ledger.entries : [];
    if (!entries.length) {
      target.appendChild(make('div', 'mg-admin-stamp-empty', 'No Stamp ledger entries yet. Admin credits, package purchases, monthly allowances, debits, voids, and adjustments will appear here.'));
      return;
    }
    entries.slice(0, 20).forEach((entry) => target.appendChild(entryRow(entry)));
  }

  async function loadLedger() {
    if (!state.user?.id || !state.section) return;
    setNotice('Loading Stamp ledger…');
    try {
      const response = await apiGet(`/api/stamps/ledger.php?account_user_id=${encodeURIComponent(state.user.id)}&limit=50`);
      const data = response.data || response;
      state.ledger = data;
      renderBalance(state.section.querySelector('[data-admin-stamp-balance]'), data);
      renderEntries(state.section.querySelector('[data-admin-stamp-ledger]'), data);
      setNotice(`Stamp ledger loaded for period ${data.period || 'current'}.`, 'success');
    } catch (error) {
      setNotice(error.message || 'Unable to load Stamp ledger. Confirm this admin has admin.stamps.view or admin.stamps.manage.', 'error');
      const ledgerTarget = state.section.querySelector('[data-admin-stamp-ledger]');
      clear(ledgerTarget);
      ledgerTarget.appendChild(make('div', 'mg-admin-stamp-empty', 'Stamp ledger is unavailable for this admin session.'));
    }
  }

  function buildAdjustmentForm() {
    const form = make('form', 'mg-admin-stamp-form');
    form.dataset.adminStampAdjustmentForm = '';
    const grid = make('div', 'mg-admin-stamp-form-grid');
    const amountLabel = make('label', '', 'Add Stamps');
    const amount = make('input');
    amount.name = 'stamps';
    amount.type = 'number';
    amount.min = '1';
    amount.max = '1000000';
    amount.step = '1';
    amount.inputMode = 'numeric';
    amount.value = '100';
    amountLabel.appendChild(amount);
    const reasonLabel = make('label', '', 'Reason');
    const reason = make('select');
    reason.name = 'reason_code';
    [['admin_credit','Admin credit'], ['purchase_support_credit','Package purchase support credit'], ['goodwill_credit','Goodwill credit'], ['migration_repair','Migration / balance repair'], ['adjustment_reversal','Adjustment reversal']].forEach(([value, label]) => {
      const option = make('option', '', label);
      option.value = value;
      reason.appendChild(option);
    });
    reasonLabel.appendChild(reason);
    grid.append(amountLabel, reasonLabel);
    const noteLabel = make('label', '', 'Tracking note');
    const note = make('textarea');
    note.name = 'note';
    note.maxLength = 1000;
    note.required = true;
    note.placeholder = 'Required. Example: Manual support credit after confirmed Stamp package purchase.';
    noteLabel.appendChild(note);
    const submit = make('button', 'mg-btn mg-btn-primary', 'Add Stamps');
    submit.type = 'submit';
    submit.dataset.adminStampSubmit = '';
    form.append(grid, noteLabel, submit);
    form.addEventListener('submit', submitAdjustment);
    return form;
  }

  async function submitAdjustment(event) {
    event.preventDefault();
    if (state.busy || !state.user?.id) return;
    const form = event.currentTarget;
    const stamps = Math.max(1, Math.min(1000000, Number.parseInt(form.elements.stamps.value || '0', 10) || 0));
    const reasonCode = String(form.elements.reason_code.value || '').trim();
    const note = String(form.elements.note.value || '').trim();
    if (!stamps || !reasonCode || note.length < 8) {
      setNotice('Enter a Stamp amount, reason, and tracking note of at least 8 characters.', 'error');
      return;
    }
    if (!window.confirm(`Add ${num(stamps)} Stamps to ${state.user.email || `User #${state.user.id}`}?`)) return;
    setBusy(true);
    setNotice('Recording admin Stamp adjustment…');
    try {
      const response = await apiPost('/api/stamps/adjustment.php', {
        account_user_id: state.user.id,
        delta: stamps,
        reason_code: reasonCode,
        note,
        idempotency_key: `admin-user-stamps:${state.user.id}:${Date.now()}`,
      });
      const data = response.data || response;
      state.ledger = data.ledger || data;
      renderBalance(state.section.querySelector('[data-admin-stamp-balance]'), state.ledger);
      renderEntries(state.section.querySelector('[data-admin-stamp-ledger]'), state.ledger);
      form.elements.note.value = '';
      setNotice(response.message || 'Stamps added and ledger entry recorded.', 'success');
    } catch (error) {
      setNotice(error.message || 'Unable to add Stamps. Confirm this admin has admin.stamps.manage.', 'error');
    } finally {
      setBusy(false);
    }
  }

  function render(detail) {
    state.user = detail.user;
    state.drawer = detail.drawer;
    state.section?.remove();
    const content = detail.drawer?.querySelector('.mg-admin-user-detail-content');
    if (!content) return;

    const section = make('section', 'mg-admin-user-stamps-section');
    state.section = section;
    const header = make('header');
    const copy = make('div');
    copy.append(make('h3', '', 'Stamps'), make('p', '', 'View this user’s Stamp balance, package/ledger tracking, debits, credits, and admin adjustments.'));
    const refresh = make('button', 'mg-btn mg-btn-soft', 'Refresh Stamps');
    refresh.type = 'button';
    refresh.addEventListener('click', loadLedger);
    header.append(copy, refresh);
    const body = make('div', 'mg-admin-stamps-body');
    const notice = make('div', 'mg-admin-stamp-notice', 'Preparing Stamp ledger…');
    notice.dataset.adminStampNotice = '';
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    const balance = make('div', 'mg-admin-stamps-grid');
    balance.dataset.adminStampBalance = '';
    const toolbar = make('div', 'mg-admin-stamp-toolbar');
    toolbar.append(make('strong', '', 'Ledger tracking'), make('span', 'mg-admin-users-readonly', 'Permission gated'));
    const ledger = make('div', 'mg-admin-stamp-ledger');
    ledger.dataset.adminStampLedger = '';
    ledger.appendChild(make('div', 'mg-admin-stamp-empty', 'Loading Stamp ledger…'));
    body.append(notice, balance, buildAdjustmentForm(), toolbar, ledger);
    section.append(header, body);
    content.appendChild(section);
    loadLedger();
  }

  document.addEventListener('mg:admin-user-detail-loaded', (event) => {
    if (!event.detail?.user || !event.detail?.drawer) return;
    state.busy = false;
    render(event.detail);
  });

  document.addEventListener('mg:admin-user-detail-closed', () => {
    state.user = null;
    state.drawer = null;
    state.section = null;
    state.ledger = null;
    state.busy = false;
  });
})();

(() => {
  'use strict';

  const root = document.querySelector('[data-admin-users]');
  const list = root?.querySelector('[data-users-list]');
  if (!root || !list || root.dataset.adminUsersCanManageAiLimits !== '1') return;

  if (!document.querySelector('link[data-admin-ai-access-style]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/assets/css/admin-ai-user-access.css?v=1.0.0';
    link.dataset.adminAiAccessStyle = '1';
    document.head.appendChild(link);
  }

  const state = { userId: '', data: null, trigger: null, busy: false };
  const make = (tag, className = '', text = '') => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== '') node.textContent = text;
    return node;
  };
  const payloadOf = (response) => response?.data || response || {};
  const tokens = (value) => value == null ? 'Unlimited' : Number(value || 0).toLocaleString();
  const readable = (value) => String(value || '').replace(/[_-]+/g, ' ');
  const dateText = (value) => {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T') + (/[zZ]|[+-]\d\d:?\d\d$/.test(String(value)) ? '' : 'Z'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  };
  const apiGet = async (url) => {
    if (window.Microgifter?.get) return Microgifter.get(url);
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const body = await response.json().catch(() => null);
    if (!response.ok || !body?.ok) throw new Error(body?.message || 'Request failed.');
    return body;
  };
  const apiPost = async (url, body) => {
    if (window.Microgifter?.post) return Microgifter.post(url, body);
    const response = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const result = await response.json().catch(() => null);
    if (!response.ok || !result?.ok) throw new Error(result?.message || 'Request failed.');
    return result;
  };

  function buildModal() {
    const layer = make('div', 'mg-admin-ai-access-layer mg-hidden');
    layer.dataset.adminAiAccessLayer = '';
    layer.innerHTML = `
      <button class="mg-admin-ai-access-backdrop" type="button" data-ai-access-close aria-label="Close AI access"></button>
      <section class="mg-admin-ai-access-modal" role="dialog" aria-modal="true" aria-labelledby="mg-admin-ai-access-title" tabindex="-1">
        <header>
          <div><span class="mg-eyebrow">Subscription and usage controls</span><h2 id="mg-admin-ai-access-title">Edit user AI access</h2><p data-ai-access-subtitle>Loading account credit details…</p></div>
          <button class="mg-admin-ai-access-close" type="button" data-ai-access-close aria-label="Close AI access">×</button>
        </header>
        <div class="mg-admin-ai-access-body">
          <div class="mg-admin-ai-access-summary" data-ai-access-summary></div>
          <div class="mg-admin-ai-access-grid">
            <section class="mg-admin-ai-access-panel">
              <header><div><h3>Subscription package</h3><p>Assign the main Microgifter package and refill the package AI allowance for its new billing period.</p></div></header>
              <form class="mg-admin-ai-access-form" data-ai-package-form>
                <div class="mg-admin-ai-access-fields">
                  <label>Package<select name="package_id" data-ai-package-select></select></label>
                  <label>Billing period<select name="billing_cycle"><option value="month">Monthly</option><option value="year">Yearly</option></select></label>
                </div>
                <div class="mg-admin-ai-access-package-preview" data-ai-package-preview></div>
                <label>Assignment note<textarea name="note" maxlength="500" placeholder="Reason for this complimentary or administrative assignment"></textarea></label>
                <div class="mg-admin-ai-access-actions"><button class="mg-btn mg-btn-primary" type="submit">Assign package</button></div>
              </form>
            </section>

            <section class="mg-admin-ai-access-panel">
              <header><div><h3>Grant token credits</h3><p>Add one-time bonus tokens without changing the user’s subscription package.</p></div></header>
              <form class="mg-admin-ai-access-form" data-ai-grant-form>
                <label>Tokens to credit<input name="tokens" type="number" min="1" step="1" required placeholder="25000"></label>
                <label>Credit note<textarea name="note" maxlength="500" required placeholder="Support adjustment, promotion, testing allowance, or service credit"></textarea></label>
                <div class="mg-admin-ai-access-help">Manual credits remain available until used. Package refills do not erase the bonus balance.</div>
                <div class="mg-admin-ai-access-actions"><button class="mg-btn mg-btn-primary" type="submit">Credit tokens</button></div>
              </form>
            </section>

            <section class="mg-admin-ai-access-panel is-wide">
              <header><div><h3>User token policy</h3><p>Set custom daily, weekly, and monthly token caps. Leave a field blank to inherit the assigned package. Enter 0 to block that period.</p></div></header>
              <form class="mg-admin-ai-access-form" data-ai-policy-form>
                <label><span><input name="enabled" type="checkbox" value="1"> AI access enabled</span></label>
                <div class="mg-admin-ai-access-fields">
                  <label>Daily token cap<input name="daily_token_limit" type="number" min="0" step="1" placeholder="Use package limit"></label>
                  <label>Weekly token cap<input name="weekly_token_limit" type="number" min="0" step="1" placeholder="Use package limit"></label>
                  <label>Monthly token cap<input name="monthly_token_limit" type="number" min="0" step="1" placeholder="Use package limit"></label>
                  <label>Requests per hour<input name="requests_per_hour" type="number" min="0" step="1" placeholder="No custom cap"></label>
                  <label>Requests per day<input name="requests_per_day" type="number" min="0" step="1" placeholder="No custom cap"></label>
                </div>
                <label>Administrator note<textarea name="note" maxlength="500" placeholder="Internal reason or support context"></textarea></label>
                <div class="mg-admin-ai-access-help">Token limits control consumption. Request limits remain a separate abuse-prevention safeguard.</div>
                <div class="mg-admin-ai-access-actions"><button class="mg-btn mg-btn-primary" type="submit">Save AI policy</button></div>
              </form>
            </section>

            <section class="mg-admin-ai-access-panel is-wide">
              <header><div><h3>Usage and credit history</h3><p>Package refills, manual grants, and actual Claude input/output token debits.</p></div></header>
              <div class="mg-admin-ai-access-usage" data-ai-access-usage></div>
              <div class="mg-admin-ai-access-ledger" data-ai-access-ledger></div>
            </section>
          </div>
          <div class="mg-admin-ai-access-notice" data-ai-access-notice role="status" aria-live="polite"></div>
        </div>
      </section>`;
    document.body.appendChild(layer);
    return {
      layer,
      modal: layer.querySelector('.mg-admin-ai-access-modal'),
      title: layer.querySelector('#mg-admin-ai-access-title'),
      subtitle: layer.querySelector('[data-ai-access-subtitle]'),
      summary: layer.querySelector('[data-ai-access-summary]'),
      packageForm: layer.querySelector('[data-ai-package-form]'),
      packageSelect: layer.querySelector('[data-ai-package-select]'),
      packagePreview: layer.querySelector('[data-ai-package-preview]'),
      grantForm: layer.querySelector('[data-ai-grant-form]'),
      policyForm: layer.querySelector('[data-ai-policy-form]'),
      usage: layer.querySelector('[data-ai-access-usage]'),
      ledger: layer.querySelector('[data-ai-access-ledger]'),
      notice: layer.querySelector('[data-ai-access-notice]'),
    };
  }

  const ui = buildModal();

  function setNotice(message = '', type = '') {
    ui.notice.textContent = message;
    ui.notice.dataset.type = type;
  }

  function setBusy(value) {
    state.busy = value;
    ui.layer.querySelectorAll('button,input,select,textarea').forEach((node) => {
      if (!node.matches('[data-ai-access-close]')) node.disabled = value;
    });
  }

  function metric(label, value, help) {
    const card = make('article', 'mg-admin-ai-access-metric');
    card.append(make('span', '', label), make('strong', '', value), make('small', '', help));
    return card;
  }

  function renderPackagePreview() {
    const selected = (state.data?.packages || []).find((item) => item.id === ui.packageSelect.value);
    ui.packagePreview.innerHTML = '';
    if (!selected) return;
    ui.packagePreview.append(
      make('strong', '', `${selected.name} · ${selected.price_label || '$0'}${selected.billing_label || ''}`),
      make('span', '', `${tokens(selected.monthly_tokens)} AI tokens monthly · ${tokens(selected.daily_limit)} daily · ${tokens(selected.weekly_limit)} weekly`)
    );
  }

  function setNullable(form, name, value) {
    const field = form.elements.namedItem(name);
    if (field) field.value = value == null ? '' : String(value);
  }

  function render(data) {
    state.data = data;
    const user = data.user || {};
    const credit = data.credits || {};
    const packageInfo = credit.package || data.package_context || {};
    ui.title.textContent = `AI access · ${user.display_name || user.email || 'User'}`;
    ui.subtitle.textContent = `${user.email || ''} · User #${user.id || ''}`;

    ui.summary.innerHTML = '';
    ui.summary.append(
      metric('Package', packageInfo.name || 'Free', `${readable(packageInfo.status || '')} access`),
      metric('Available tokens', tokens(credit.available_tokens), credit.can_use ? 'AI requests are available' : `Blocked: ${readable(credit.block_reason || 'credit policy')}`),
      metric('Package balance', tokens(credit.package_tokens_remaining), `${tokens(credit.package_tokens_allocated)} allocated this period`),
      metric('Bonus balance', tokens(credit.manual_tokens_remaining || 0), `Period ends ${dateText(credit.period?.end)}`)
    );

    ui.packageSelect.innerHTML = '';
    (data.packages || []).forEach((item) => {
      const option = make('option', '', `${item.name} · ${tokens(item.monthly_tokens)} AI tokens`);
      option.value = item.id;
      option.selected = item.id === packageInfo.id;
      ui.packageSelect.appendChild(option);
    });
    renderPackagePreview();

    const policy = ui.policyForm;
    policy.elements.enabled.checked = Boolean(credit.enabled);
    setNullable(policy, 'daily_token_limit', credit.custom_limits?.day);
    setNullable(policy, 'weekly_token_limit', credit.custom_limits?.week);
    setNullable(policy, 'monthly_token_limit', credit.custom_limits?.month);
    setNullable(policy, 'requests_per_hour', credit.request_limits?.hour);
    setNullable(policy, 'requests_per_day', credit.request_limits?.day);
    policy.elements.note.value = credit.note || '';

    ui.usage.innerHTML = '';
    [['Today','day'],['This week','week'],['Billing period','month']].forEach(([label, key]) => {
      const item = make('article');
      item.append(make('span', '', label), make('strong', '', `${tokens(credit.usage?.[key] || 0)} / ${tokens(credit.limits?.[key])}`));
      ui.usage.appendChild(item);
    });

    ui.ledger.innerHTML = '';
    const rows = data.ledger || [];
    if (!rows.length) {
      ui.ledger.appendChild(make('div', 'mg-admin-ai-access-empty', 'No AI credit activity has been recorded yet.'));
    } else {
      rows.forEach((entry) => {
        const row = make('article', 'mg-admin-ai-access-ledger-row');
        const copy = make('div');
        copy.append(
          make('strong', '', readable(entry.entry_type)),
          make('small', '', `${readable(entry.source_type)} · ${dateText(entry.created_at)}${entry.input_tokens || entry.output_tokens ? ` · ${tokens(entry.input_tokens)} in / ${tokens(entry.output_tokens)} out` : ''}`)
        );
        const delta = Number(entry.token_delta || 0);
        const value = make('b', delta >= 0 ? 'is-credit' : 'is-debit', `${delta >= 0 ? '+' : ''}${tokens(delta)}`);
        row.append(copy, value);
        ui.ledger.appendChild(row);
      });
    }
  }

  async function load() {
    setNotice('Loading AI package, balances, and usage…');
    setBusy(true);
    try {
      const response = await apiGet(`/api/admin/ai-user-limits.php?user_id=${encodeURIComponent(state.userId)}`);
      render(payloadOf(response));
      setNotice('AI access loaded.', 'success');
    } catch (error) {
      setNotice(error.message || 'Unable to load AI access.', 'error');
    } finally {
      setBusy(false);
    }
  }

  function open(userId, trigger) {
    if (!userId) return;
    state.userId = String(userId);
    state.trigger = trigger || document.activeElement;
    ui.layer.classList.remove('mg-hidden');
    document.body.classList.add('mg-admin-ai-access-open');
    ui.modal.focus();
    load();
  }

  function close() {
    if (state.busy) return;
    ui.layer.classList.add('mg-hidden');
    document.body.classList.remove('mg-admin-ai-access-open');
    if (state.trigger instanceof HTMLElement) state.trigger.focus();
    state.userId = '';
    state.data = null;
    state.trigger = null;
    setNotice('');
  }

  async function submit(action, body, successMessage) {
    if (state.busy || !state.userId) return;
    setBusy(true);
    setNotice('Saving protected AI access changes…');
    try {
      const response = await apiPost('/api/admin/ai-user-limits.php', {
        action, user_id: Number(state.userId), provider_key: 'anthropic', ...body,
      });
      render(payloadOf(response));
      setNotice(response?.message || successMessage, 'success');
      document.dispatchEvent(new CustomEvent('mg:admin-users-refresh'));
      document.dispatchEvent(new CustomEvent('mg:admin-user-detail-refresh', { detail: { userId: Number(state.userId) } }));
    } catch (error) {
      setNotice(error.message || 'Unable to save AI access.', 'error');
    } finally {
      setBusy(false);
    }
  }

  ui.packageSelect.addEventListener('change', renderPackagePreview);
  ui.packageForm.addEventListener('submit', (event) => {
    event.preventDefault();
    submit('assign_package', {
      package_id: ui.packageForm.elements.package_id.value,
      billing_cycle: ui.packageForm.elements.billing_cycle.value,
      note: ui.packageForm.elements.note.value.trim(),
    }, 'Subscription package assigned.');
  });
  ui.grantForm.addEventListener('submit', (event) => {
    event.preventDefault();
    submit('grant_tokens', {
      tokens: Number(ui.grantForm.elements.tokens.value || 0),
      note: ui.grantForm.elements.note.value.trim(),
    }, 'Token credits granted.').then(() => {
      if (ui.notice.dataset.type === 'success') ui.grantForm.reset();
    });
  });
  ui.policyForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const nullable = (name) => {
      const raw = String(ui.policyForm.elements[name].value || '').trim();
      return raw === '' ? null : Number(raw);
    };
    submit('save_policy', {
      enabled: ui.policyForm.elements.enabled.checked ? 1 : 0,
      daily_token_limit: nullable('daily_token_limit'),
      weekly_token_limit: nullable('weekly_token_limit'),
      monthly_token_limit: nullable('monthly_token_limit'),
      requests_per_hour: nullable('requests_per_hour'),
      requests_per_day: nullable('requests_per_day'),
      note: ui.policyForm.elements.note.value.trim(),
    }, 'AI access policy saved.');
  });

  ui.layer.querySelectorAll('[data-ai-access-close]').forEach((button) => button.addEventListener('click', close));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !ui.layer.classList.contains('mg-hidden')) close();
  });

  function rowUserId(row) {
    const explicit = row.getAttribute('data-user-id') || row.querySelector('[data-user-id]')?.getAttribute('data-user-id');
    if (explicit) return explicit;
    const match = row.querySelector('.mg-admin-user-meta')?.textContent?.match(/User #([1-9][0-9]*)/);
    return match ? match[1] : '';
  }

  function enhanceRow(row) {
    if (!(row instanceof HTMLTableRowElement) || row.dataset.aiAccessEnhanced === '1') return;
    const userId = rowUserId(row);
    const host = row.querySelector('.mg-admin-user-identity');
    if (!userId || !host) return;
    const button = make('button', 'mg-admin-user-detail-trigger mg-admin-ai-access-open', 'AI access');
    button.type = 'button';
    button.setAttribute('aria-haspopup', 'dialog');
    button.addEventListener('click', (event) => { event.stopPropagation(); open(userId, button); });
    host.appendChild(button);
    row.dataset.aiAccessEnhanced = '1';
  }

  const enhance = (nodes) => nodes.forEach((node) => {
    if (!(node instanceof Element)) return;
    if (node.matches('tr')) enhanceRow(node);
    node.querySelectorAll('tr').forEach(enhanceRow);
  });
  enhance(Array.from(list.children));
  new MutationObserver((mutations) => mutations.forEach((mutation) => enhance(Array.from(mutation.addedNodes))))
    .observe(list, { childList: true, subtree: true });

  document.addEventListener('mg:admin-user-detail-loaded', (event) => {
    const detail = event.detail || {};
    const content = detail.drawer?.querySelector('.mg-admin-user-detail-content');
    const user = detail.user || {};
    if (!content || !user.id) return;
    content.querySelector('.mg-admin-user-ai-access-section')?.remove();
    const section = make('section', 'mg-admin-user-detail-section mg-admin-user-ai-access-section');
    const header = make('header');
    const copy = make('div');
    copy.append(make('h3', '', 'AI access'), make('p', '', 'Subscription package, token balance, usage caps, and manual credits.'));
    header.append(copy, make('span', 'mg-admin-users-readonly', 'Admin controlled'));
    const inline = make('div', 'mg-admin-ai-access-inline');
    const text = make('div');
    text.append(make('strong', '', 'Manage this user’s AI package and credits'), make('span', '', 'Opens one protected modal with usage history and all credit controls.'));
    const button = make('button', 'mg-btn mg-btn-soft', 'Edit AI access');
    button.type = 'button';
    button.addEventListener('click', () => open(user.id, button));
    inline.append(text, button);
    section.append(header, inline);
    content.appendChild(section);
  });
})();

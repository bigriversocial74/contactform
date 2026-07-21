(() => {
  'use strict';

  const endpoint = '/api/merchant/creator-campaigns.php';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const uuid = () => globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  const money = (cents, currency = 'USD') => new Intl.NumberFormat(undefined, {style:'currency', currency}).format((Number(cents) || 0) / 100);
  const localInput = (value) => {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return '';
    const offset = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
  };
  const query = (params) => new URLSearchParams(Object.entries(params).filter(([,value]) => value !== '' && value !== null && value !== undefined)).toString();

  async function apiGet(params) {
    const response = await fetch(`${endpoint}?${query(params)}`, {credentials:'same-origin', headers:{Accept:'application/json'}});
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
    return payload.data || {};
  }

  async function apiPost(body) {
    const response = await fetch(endpoint, {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json', Accept:'application/json'},
      body:JSON.stringify({...body, csrf_token:csrf}),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
    return payload.data || {};
  }

  function statusClass(status) {
    if (['active','completed'].includes(status)) return 'is-green';
    if (['cancelled','archived'].includes(status)) return 'is-red';
    if (['scheduled','draft'].includes(status)) return 'is-blue';
    return 'is-amber';
  }

  function initBuilder(root) {
    const form = root.querySelector('[data-cc-form]');
    const loading = root.querySelector('[data-cc-builder-loading]');
    const error = root.querySelector('[data-cc-builder-error]');
    const live = root.querySelector('[data-cc-builder-live]');
    const saveState = root.querySelector('[data-cc-save-state]');
    const campaignInput = root.querySelector('[data-cc-campaign-id]');
    const lockInput = root.querySelector('[data-cc-lock-version]');
    const params = new URLSearchParams(location.search);
    const state = {
      options:null,
      campaign:null,
      step:Math.max(1, Math.min(10, Number(params.get('step') || 1))),
      campaignId:params.get('campaign') || '',
      busy:false,
    };

    const setBusy = (busy, text = '') => {
      state.busy = busy;
      form.querySelector('[data-cc-save-step]').disabled = busy;
      root.querySelectorAll('[data-cc-duplicate],[data-cc-review-duplicate]').forEach((button) => { button.disabled = busy || !state.campaign; });
      saveState.textContent = text || (busy ? 'Saving…' : 'Saved');
    };

    const setError = (message) => {
      live.textContent = message;
      live.style.color = '#b42318';
    };
    const setSuccess = (message) => {
      live.textContent = message;
      live.style.color = '#16714a';
    };

    const setOptions = () => {
      const options = state.options || {};
      const manager = form.querySelector('[data-cc-manager-options]');
      manager.innerHTML = (options.managers || []).map((item) => `<option value="${esc(item.key)}">${esc(item.label)} · ${esc(item.role)}</option>`).join('');
      const assets = form.querySelector('[data-cc-asset-options]');
      assets.innerHTML = '<option value="">No cover selected</option>' + (options.assets || []).map((item) => `<option value="${esc(item.public_id)}">${esc(item.original_filename || item.public_id)}</option>`).join('');
      const rewards = form.querySelector('[data-cc-reward-options]');
      rewards.innerHTML = '<option value="">No reward selected</option>' + (options.reward_templates || []).map((item) => `<option value="${esc(item.public_id)}">${esc(item.title)} · ${esc(item.reward_type)}</option>`).join('');
      const timezone = form.querySelector('[data-cc-timezone-options]');
      const localZone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
      const zones = [...new Set([localZone, options.workspace?.timezone || 'UTC', 'UTC', 'America/Phoenix', 'America/Los_Angeles', 'America/Denver', 'America/Chicago', 'America/New_York'])];
      timezone.innerHTML = zones.map((zone) => `<option value="${esc(zone)}">${esc(zone)}</option>`).join('');
    };

    const productOptionHtml = (selected = '') => '<option value="">Select product</option>' + (state.options?.products || []).map((product) => {
      const title = product.title || product.slug || product.public_id;
      const value = product.unit_value_cents ? ` · ${money(product.unit_value_cents, product.currency || 'USD')}` : '';
      return `<option value="${esc(product.public_id)}" data-version="${esc(product.version_public_id || '')}" ${product.public_id === selected ? 'selected' : ''}>${esc(title + value)}</option>`;
    }).join('');

    function addProductRow(product = {}) {
      const row = document.createElement('div');
      row.className = 'mg-cc-repeatable-row';
      row.innerHTML = `<label>Product<select data-product-id>${productOptionHtml(product.product_public_id || '')}</select></label>
        <label>Relationship<select data-product-relationship><option value="primary">Primary</option><option value="featured">Featured</option><option value="commissionable">Commissionable</option><option value="excluded">Excluded</option><option value="creator_compensation">Creator compensation</option></select></label>
        <label>Version<input data-product-version value="${esc(product.version_public_id || '')}" readonly></label>
        <button class="mg-btn mg-btn-ghost" type="button" data-remove-row>Remove</button>`;
      row.querySelector('[data-product-relationship]').value = product.relationship_type || 'featured';
      const select = row.querySelector('[data-product-id]');
      const version = row.querySelector('[data-product-version]');
      select.addEventListener('change', () => { version.value = select.selectedOptions[0]?.dataset.version || ''; updateSummary(); });
      row.querySelector('[data-remove-row]').addEventListener('click', () => { row.remove(); updateSummary(); });
      form.querySelector('[data-cc-products]').append(row);
    }

    function addRuleRow(rule = {}) {
      const row = document.createElement('div');
      row.className = 'mg-cc-repeatable-row is-rule';
      row.innerHTML = `<label>Type<select data-rule-type><option value="specialty">Specialty</option><option value="category">Category</option><option value="platform">Platform</option><option value="verification">Verification</option><option value="location">Location</option><option value="audience">Audience</option><option value="existing_relationship">Existing relationship</option></select></label>
        <label>Operator<select data-rule-operator><option value="equals">Equals</option><option value="not_equals">Not equals</option><option value="contains">Contains</option><option value="in">In list</option><option value="gte">At least</option><option value="lte">At most</option><option value="between">Between</option><option value="exists">Exists</option></select></label>
        <label>Value<input data-rule-value value="${esc(Array.isArray(rule.value) ? rule.value.join(', ') : rule.value ?? '')}" placeholder="Video, Phoenix, Instagram, 5000"></label>
        <label class="mg-cc-toggle"><input type="checkbox" data-rule-required ${rule.is_required === false ? '' : 'checked'}><span>Required</span></label>
        <button class="mg-btn mg-btn-ghost" type="button" data-remove-row>Remove</button>`;
      row.querySelector('[data-rule-type]').value = rule.rule_type || 'specialty';
      row.querySelector('[data-rule-operator]').value = rule.operator_key || rule.operator || 'equals';
      row.querySelector('[data-remove-row]').addEventListener('click', () => { row.remove(); updateSummary(); });
      form.querySelector('[data-cc-rules]').append(row);
    }

    function addQuestionRow(question = {}) {
      const row = document.createElement('div');
      row.className = 'mg-cc-repeatable-row is-question';
      row.innerHTML = `<label>Question<input data-question-prompt maxlength="500" value="${esc(question.prompt || '')}" placeholder="Why are you a strong fit?"></label>
        <label>Type<select data-question-type><option value="short_text">Short text</option><option value="long_text">Long text</option><option value="single_choice">Single choice</option><option value="multiple_choice">Multiple choice</option><option value="boolean">Yes / No</option><option value="number">Number</option><option value="url">URL</option><option value="portfolio_link">Portfolio link</option></select></label>
        <label>Options<input data-question-options value="${esc((question.options || []).join(', '))}" placeholder="Option one, Option two"></label>
        <label class="mg-cc-toggle"><input type="checkbox" data-question-required ${question.is_required ? 'checked' : ''}><span>Required</span></label>
        <button class="mg-btn mg-btn-ghost" type="button" data-remove-row>Remove</button>`;
      row.querySelector('[data-question-type]').value = question.question_type || 'short_text';
      row.querySelector('[data-remove-row]').addEventListener('click', () => { row.remove(); updateSummary(); });
      form.querySelector('[data-cc-questions]').append(row);
    }

    const field = (name) => form.elements.namedItem(name);
    const setValue = (name, value) => { const node = field(name); if (node) node.value = value ?? ''; };
    const setChecked = (name, value) => { const node = field(name); if (node) node.checked = Boolean(value); };

    function hydrateCampaign() {
      const c = state.campaign;
      if (!c) return;
      campaignInput.value = c.public_id || '';
      lockInput.value = c.lock_version || 0;
      root.querySelector('[data-cc-builder-title]').textContent = c.title || 'Create Creator Campaign';
      root.querySelector('[data-cc-status]').textContent = c.status || 'draft';
      root.querySelector('[data-cc-status]').className = `mg-cc-pill ${statusClass(c.status || 'draft')}`;
      setValue('title',c.title); setValue('internal_reference',c.internal_reference); setValue('campaign_manager_key',c.campaign_manager_key || 'owner'); setValue('objective',c.objective); setValue('category',c.category);
      setValue('description',c.description); setValue('access_mode',c.access_mode || 'open'); setValue('eligibility_access_mode',c.access_mode || 'open');
      setValue('timezone',c.timezone || state.options?.workspace?.timezone || 'UTC'); setValue('starts_at',localInput(c.starts_at)); setValue('ends_at',localInput(c.ends_at));
      setValue('application_deadline_at',localInput(c.application_deadline_at)); setValue('eligibility_application_deadline_at',localInput(c.application_deadline_at));
      setValue('geographic_label',c.geographic_scope?.label || ''); setValue('campaign_focus',c.campaign_focus || 'general_brand_campaign');
      setValue('creator_product_access',c.creator_product_access || 'none'); setValue('creator_landing_url',c.creator_landing_url || '');
      setValue('maximum_approved_creators',c.maximum_approved_creators); setValue('maximum_applications',c.maximum_applications); setValue('existing_creator_preference',c.existing_creator_preference || 'none');
      setChecked('automatic_acceptance',c.automatic_acceptance);
      setValue('cover_asset_public_id',c.cover_asset_public_id || '');
      setValue('featured_reward_public_id',c.featured_reward_public_id || '');
      form.querySelector('[data-cc-products]').innerHTML = '';
      (c.products || []).forEach((product) => addProductRow(product));
      form.querySelector('[data-cc-rules]').innerHTML = '';
      (c.eligibility_rules || []).forEach((rule) => addRuleRow(rule));
      form.querySelector('[data-cc-questions]').innerHTML = '';
      (c.application_questions || []).forEach((question) => addQuestionRow(question));
      updateSummary();
    }

    function collectProducts() {
      return [...form.querySelectorAll('[data-cc-products] .mg-cc-repeatable-row')].map((row,index) => ({
        product_public_id:row.querySelector('[data-product-id]').value,
        version_public_id:row.querySelector('[data-product-version]').value,
        relationship_type:row.querySelector('[data-product-relationship]').value,
        sort_order:index,
      })).filter((row) => row.product_public_id);
    }

    function parseRuleValue(operator, raw) {
      if (operator === 'exists') return null;
      if (['in','between'].includes(operator)) return raw.split(',').map((value) => value.trim()).filter(Boolean);
      if (['gte','lte'].includes(operator) && raw !== '' && !Number.isNaN(Number(raw))) return Number(raw);
      return raw.trim();
    }

    function collectRules() {
      return [...form.querySelectorAll('[data-cc-rules] .mg-cc-repeatable-row')].map((row,index) => {
        const operator = row.querySelector('[data-rule-operator]').value;
        return {
          rule_type:row.querySelector('[data-rule-type]').value,
          operator_key:operator,
          value:parseRuleValue(operator,row.querySelector('[data-rule-value]').value),
          is_required:row.querySelector('[data-rule-required]').checked,
          sort_order:index,
        };
      });
    }

    function collectQuestions() {
      return [...form.querySelectorAll('[data-cc-questions] .mg-cc-repeatable-row')].map((row,index) => ({
        prompt:row.querySelector('[data-question-prompt]').value.trim(),
        question_type:row.querySelector('[data-question-type]').value,
        options:row.querySelector('[data-question-options]').value.split(',').map((value) => value.trim()).filter(Boolean),
        is_required:row.querySelector('[data-question-required]').checked,
        sort_order:index,
      })).filter((row) => row.prompt);
    }

    function stepPayload(step) {
      if (step === 1) return {
        title:field('title').value.trim(), internal_reference:field('internal_reference').value.trim(),
        campaign_manager_key:field('campaign_manager_key').value, objective:field('objective').value,
        description:field('description').value.trim(), category:field('category').value.trim(), access_mode:field('access_mode').value,
        starts_at:field('starts_at').value, ends_at:field('ends_at').value, application_deadline_at:field('application_deadline_at').value,
        timezone:field('timezone').value, geographic_scope:field('geographic_label').value.trim() ? {label:field('geographic_label').value.trim()} : null,
        cover_asset_public_id:field('cover_asset_public_id').value,
      };
      if (step === 2) return {
        campaign_focus:field('campaign_focus').value, featured_reward_public_id:field('featured_reward_public_id').value,
        creator_product_access:field('creator_product_access').value, creator_landing_url:field('creator_landing_url').value.trim(),
        products:collectProducts(),
      };
      return {
        access_mode:field('eligibility_access_mode').value, maximum_approved_creators:field('maximum_approved_creators').value,
        maximum_applications:field('maximum_applications').value, automatic_acceptance:field('automatic_acceptance').checked,
        existing_creator_preference:field('existing_creator_preference').value,
        application_deadline_at:field('eligibility_application_deadline_at').value,
        eligibility_rules:collectRules(), application_questions:collectQuestions(),
      };
    }

    function showStep(step) {
      state.step = Math.max(1, Math.min(10, Number(step) || 1));
      root.querySelectorAll('[data-cc-step]').forEach((panel) => panel.classList.toggle('is-active', Number(panel.dataset.ccStep) === state.step));
      root.querySelectorAll('[data-cc-step-button]').forEach((button) => button.classList.toggle('is-active', Number(button.dataset.ccStepButton) === state.step));
      root.querySelector('[data-cc-prev-step]').disabled = state.step === 1;
      const save = root.querySelector('[data-cc-save-step]');
      save.hidden = ![1,2,3].includes(state.step);
      save.textContent = state.step === 3 ? 'Save and Review' : 'Save and Continue';
      history.replaceState(null,'',`${location.pathname}${state.campaignId ? `?campaign=${encodeURIComponent(state.campaignId)}&step=${state.step}` : `?step=${state.step}`}`);
      if (state.step === 10) renderReview();
    }

    function updateSummary() {
      const c = state.campaign || {};
      const title = field('title')?.value.trim() || c.title || 'Untitled campaign';
      root.querySelector('[data-cc-summary-title]').textContent = title;
      root.querySelector('[data-cc-summary-objective]').textContent = field('objective')?.value || c.objective || 'Not selected';
      root.querySelector('[data-cc-summary-products]').textContent = String(collectProducts().length || (c.products || []).length || 0);
      root.querySelector('[data-cc-summary-rules]').textContent = String(collectRules().length || (c.eligibility_rules || []).length || 0);
      root.querySelector('[data-cc-summary-questions]').textContent = String(collectQuestions().length || (c.application_questions || []).length || 0);
      root.querySelector('[data-cc-summary-dates]').textContent = field('starts_at')?.value && field('ends_at')?.value ? `${field('starts_at').value} → ${field('ends_at').value}` : 'Not scheduled';
      const validation = c.builder_validation || {};
      root.querySelector('[data-cc-summary-score]').textContent = String(validation.phase2_score || 0);
      const checks = (validation.checks || []).filter((check) => Number(check.step) <= 3).slice(0,8);
      root.querySelector('[data-cc-summary-checklist]').innerHTML = checks.map((check) => `<div class="is-${esc(check.status)}"><i></i><span>${esc(check.label)}</span></div>`).join('');
    }

    function renderReview() {
      const c = state.campaign;
      const review = root.querySelector('[data-cc-review]');
      if (!c) {
        review.innerHTML = '<article class="mg-cc-gated-card"><h3>Save the campaign first</h3><p>Complete Steps 1 through 3 to generate the Phase 2 readiness report.</p></article>';
        return;
      }
      const validation = c.builder_validation || {};
      const checks = validation.checks || [];
      review.innerHTML = `<section class="mg-cc-review-section"><h3>${esc(c.title)}</h3><p>${esc(c.description || '')}</p><div class="mg-cc-card-meta"><div><strong>${esc(c.objective || '—')}</strong><span>Objective</span></div><div><strong>${(c.products||[]).length}</strong><span>Products</span></div><div><strong>${validation.phase2_score || 0}</strong><span>Phase 2 score</span></div></div></section>
        <section class="mg-cc-review-section"><h3>Validation checklist</h3><div class="mg-cc-review-checks">${checks.map((check) => `<div class="mg-cc-review-check is-${esc(check.status)}"><span class="mg-cc-pill ${check.status==='pass'?'is-green':check.status==='fail'?'is-red':'is-amber'}">${esc(check.status)}</span><div><strong>Step ${esc(check.step)} · ${esc(check.label)}</strong><span>${esc(check.message)}</span></div></div>`).join('')}</div></section>
        <section class="mg-cc-review-section"><h3>Publication boundary</h3><p>${validation.publish_ready ? 'Campaign is ready for lifecycle publication.' : 'Publication remains locked until the Agreement phase creates immutable Agreement Version 1. This prevents incomplete contractual campaigns from becoming active.'}</p></section>`;
      const publishReady = Boolean(validation.publish_ready);
      root.querySelector('[data-cc-schedule]').disabled = !publishReady;
      root.querySelector('[data-cc-publish]').disabled = !publishReady;
      const transitions = {
        draft:[['cancelled','Cancel campaign']], scheduled:[['draft','Return to draft'],['paused','Pause'],['cancelled','Cancel campaign']],
        active:[['paused','Pause'],['completed','Complete'],['cancelled','Cancel campaign']],
        paused:[['active','Resume'],['completed','Complete'],['cancelled','Cancel campaign']],
        completed:[['archived','Archive']], cancelled:[['archived','Archive']], archived:[],
      };
      root.querySelector('[data-cc-lifecycle-actions]').innerHTML = (transitions[c.status] || []).map(([status,label]) => `<button class="mg-btn mg-btn-ghost" type="button" data-cc-transition="${esc(status)}">${esc(label)}</button>`).join('');
    }

    async function saveCurrentStep() {
      if (![1,2,3].includes(state.step) || state.busy) return;
      setBusy(true);
      try {
        const payload = stepPayload(state.step);
        if (!state.campaignId) {
          if (state.step !== 1) throw new Error('Save Campaign Details before continuing.');
          const data = await apiPost({action:'create', idempotency_key:`creator-campaign-${uuid()}`, ...payload});
          state.campaign = data.campaign;
          state.campaignId = state.campaign.public_id;
        } else {
          const data = await apiPost({action:'save_step', campaign_id:state.campaignId, step:state.step, expected_lock_version:Number(lockInput.value), ...payload});
          state.campaign = data.campaign;
        }
        hydrateCampaign();
        setSuccess(`Step ${state.step} saved.`);
        setBusy(false,'Saved');
        showStep(state.step === 3 ? 10 : state.step + 1);
      } catch (err) {
        setBusy(false,'Save failed'); setError(err.message);
      }
    }

    async function duplicateCampaign() {
      if (!state.campaignId || state.busy) return;
      setBusy(true,'Duplicating…');
      try {
        const data = await apiPost({action:'duplicate', campaign_id:state.campaignId, idempotency_key:`duplicate-${uuid()}`});
        location.href = `/merchant-creator-campaign-builder.php?campaign=${encodeURIComponent(data.campaign.public_id)}`;
      } catch (err) { setBusy(false,'Duplicate failed'); setError(err.message); }
    }

    async function transition(toStatus) {
      if (!state.campaignId || state.busy) return;
      setBusy(true,`${toStatus}…`);
      try {
        const data = await apiPost({action:'transition', campaign_id:state.campaignId, to_status:toStatus, expected_lock_version:Number(lockInput.value), idempotency_key:`${toStatus}-${uuid()}`, reason:`Merchant builder ${toStatus} action.`});
        state.campaign = data.campaign; hydrateCampaign(); renderReview(); setBusy(false,'Saved');
      } catch (err) { setBusy(false,'Action blocked'); setError(err.message); }
    }

    async function load() {
      loading.classList.remove('mg-hidden'); error.classList.add('mg-hidden'); form.classList.add('mg-hidden');
      try {
        state.options = await apiGet({action:'options'});
        setOptions();
        if (state.campaignId) {
          const data = await apiGet({action:'detail', campaign_id:state.campaignId});
          state.campaign = data.campaign;
          hydrateCampaign();
        } else {
          setValue('timezone',state.options.workspace?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC');
          updateSummary();
        }
        form.classList.remove('mg-hidden');
        showStep(state.step);
      } catch (err) {
        error.classList.remove('mg-hidden'); error.querySelector('[data-cc-builder-error-message]').textContent = err.message;
      } finally { loading.classList.add('mg-hidden'); }
    }

    form.addEventListener('submit',(event) => { event.preventDefault(); saveCurrentStep(); });
    form.addEventListener('input',() => { saveState.textContent = 'Unsaved changes'; updateSummary(); });
    root.querySelectorAll('[data-cc-step-button]').forEach((button) => button.addEventListener('click',() => showStep(button.dataset.ccStepButton)));
    root.querySelector('[data-cc-prev-step]').addEventListener('click',() => showStep(Math.max(1,state.step-1)));
    root.querySelector('[data-cc-add-product]').addEventListener('click',() => addProductRow());
    root.querySelector('[data-cc-add-rule]').addEventListener('click',() => addRuleRow());
    root.querySelector('[data-cc-add-question]').addEventListener('click',() => addQuestionRow());
    root.querySelectorAll('[data-cc-duplicate],[data-cc-review-duplicate]').forEach((button) => button.addEventListener('click',duplicateCampaign));
    root.querySelector('[data-cc-schedule]').addEventListener('click',() => transition('scheduled'));
    root.querySelector('[data-cc-publish]').addEventListener('click',() => transition('active'));
    root.querySelector('[data-cc-lifecycle-actions]').addEventListener('click',(event) => {
      const button = event.target.closest('[data-cc-transition]');
      if (button) transition(button.dataset.ccTransition);
    });
    root.querySelector('[data-cc-builder-retry]')?.addEventListener('click',load);
    load();
  }

  const builder = document.querySelector('[data-cc-builder]');
  if (builder) initBuilder(builder);
})();

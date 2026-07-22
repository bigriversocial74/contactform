(() => {
  'use strict';

  const endpoint = '/api/merchant/creator-campaigns.php';
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const query = (params) => new URLSearchParams(Object.entries(params).filter(([,value]) => value !== '' && value !== null && value !== undefined)).toString();

  async function apiGet(params) {
    const response = await fetch(`${endpoint}?${query(params)}`, {credentials:'same-origin', headers:{Accept:'application/json'}});
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

  function initOverview(root) {
    const loading = root.querySelector('[data-cc-loading]');
    const error = root.querySelector('[data-cc-error]');
    const empty = root.querySelector('[data-cc-empty]');
    const list = root.querySelector('[data-cc-list]');
    const metrics = root.querySelector('[data-cc-metrics]');
    const live = root.querySelector('[data-cc-live]');
    const form = root.querySelector('[data-cc-filters]');
    const pagination = root.querySelector('[data-cc-pagination]');
    let page = 1;

    const renderMetrics = (summary) => {
      const cards = [
        ['Active Campaigns',summary.active],
        ['Draft Campaigns',summary.drafts],
        ['Scheduled',summary.scheduled],
        ['Paused',summary.paused],
        ['Completed',summary.completed],
        ['Campaign Total',summary.total],
      ];
      metrics.innerHTML = cards.map(([label,value]) => `<article class="mg-cc-metric"><span>${esc(label)}</span><strong>${Number(value)||0}</strong></article>`).join('');
    };

    const renderCampaigns = (campaigns) => {
      if (!campaigns.length) {
        empty.classList.remove('mg-hidden');
        list.classList.add('mg-hidden');
        return;
      }
      empty.classList.add('mg-hidden');
      list.classList.remove('mg-hidden');
      list.innerHTML = campaigns.map((campaign) => {
        const validation = campaign.builder_validation || {};
        const score = Number(validation.phase2_score || 0);
        const campaignUrl = `/merchant-creator-campaign-detail.php?campaign=${encodeURIComponent(campaign.public_id)}`;
        return `<article class="mg-cc-campaign-card">
          <a class="mg-cc-card-cover" href="${campaignUrl}" aria-label="View ${esc(campaign.title)}"></a>
          <div class="mg-cc-card-body">
            <div class="mg-cc-card-top"><div><h2><a href="${campaignUrl}">${esc(campaign.title)}</a></h2><span class="mg-cc-card-ref">${esc(campaign.objective || campaign.internal_reference || '')}</span></div><span class="mg-cc-pill ${statusClass(campaign.status)}">${esc(String(campaign.status || '').replace(/_/g,' '))}</span></div>
            <p>${esc(campaign.description || campaign.objective || 'Creator campaign')}</p>
            <div class="mg-cc-card-meta"><div><strong>${Number(campaign.product_count)||0}</strong><span>Products</span></div><div><strong>${Number(campaign.eligibility_rule_count)||0}</strong><span>Eligibility rules</span></div><div><strong>${score}</strong><span>Readiness</span></div></div>
            <div class="mg-cc-progress" aria-label="Campaign readiness ${score}%"><i style="width:${Math.max(0,Math.min(100,score))}%"></i></div>
            <div class="mg-cc-card-actions"><a class="mg-btn mg-btn-soft" href="${campaignUrl}">View Campaign</a><a class="mg-btn mg-btn-primary" href="/merchant-creator-campaign-builder.php?campaign=${encodeURIComponent(campaign.public_id)}">Edit</a></div>
          </div>
        </article>`;
      }).join('');
    };

    async function load() {
      loading.classList.remove('mg-hidden');
      error.classList.add('mg-hidden');
      empty.classList.add('mg-hidden');
      list.classList.add('mg-hidden');
      const data = Object.fromEntries(new FormData(form).entries());
      try {
        const response = await apiGet({action:'list', page, limit:24, ...data});
        renderMetrics(response.summary || {});
        renderCampaigns(response.campaigns || []);
        const meta = response.pagination || {page:1,pages:1,total:0};
        pagination.classList.toggle('mg-hidden', Number(meta.pages || 1) <= 1);
        pagination.querySelector('[data-cc-page-label]').textContent = `Page ${Number(meta.page)||1} of ${Number(meta.pages)||1} · ${Number(meta.total)||0} total`;
        pagination.querySelector('[data-cc-prev-page]').disabled = Number(meta.page || 1) <= 1;
        pagination.querySelector('[data-cc-next-page]').disabled = Number(meta.page || 1) >= Number(meta.pages || 1);
        live.textContent = `${(response.campaigns || []).length} Creator campaign${(response.campaigns || []).length === 1 ? '' : 's'} loaded.`;
      } catch (err) {
        error.classList.remove('mg-hidden');
        error.querySelector('[data-cc-error-message]').textContent = err.message;
      } finally {
        loading.classList.add('mg-hidden');
      }
    }

    form.addEventListener('submit', (event) => { event.preventDefault(); page = 1; load(); });
    root.querySelector('[data-cc-prev-page]')?.addEventListener('click', () => { page = Math.max(1, page - 1); load(); });
    root.querySelector('[data-cc-next-page]')?.addEventListener('click', () => { page += 1; load(); });
    root.querySelector('[data-cc-retry]')?.addEventListener('click', load);
    load();
  }

  const overview = document.querySelector('[data-cc-overview]');
  if (overview) initOverview(overview);
})();
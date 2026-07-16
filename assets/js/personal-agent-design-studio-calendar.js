(() => {
  'use strict';

  const root = document.querySelector('[data-design-content-calendar]');
  if (!root) return;
  const app = document.querySelector('[data-agent-design-studio]');
  const MG = window.Microgifter || {};
  const endpoint = '/api/merchant/design-content-calendar.php';
  if (!document.querySelector('link[data-design-advertising-v2]')) {
    const link=document.createElement('link');link.rel='stylesheet';link.href='/assets/css/design-studio-advertising-workflow-v2.css?v=2.0.0';link.dataset.designAdvertisingV2='true';document.head.appendChild(link);
  }
  if (!document.querySelector('script[data-design-creative-save]')) {
    const script=document.createElement('script');script.src='/assets/js/design-studio-creative-save.js?v=2.0.0';script.defer=true;script.dataset.designCreativeSave='true';document.head.appendChild(script);
  }
  const form = root.querySelector('[data-calendar-generator]');
  const productList = root.querySelector('[data-calendar-product-list]');
  const productCount = root.querySelector('[data-calendar-product-count]');
  const selectAllProducts = root.querySelector('[data-calendar-select-all]');
  const startInput = root.querySelector('[data-calendar-start-date]');
  const generateButton = root.querySelector('[data-calendar-generate]');
  const grid = root.querySelector('[data-calendar-grid]');
  const stack = root.querySelector('[data-calendar-stack]');
  const empty = root.querySelector('[data-calendar-empty]');
  const setup = root.querySelector('[data-calendar-setup]');
  const loading = root.querySelector('[data-calendar-loading]');
  const errorBox = root.querySelector('[data-calendar-error]');
  const statusNode = root.querySelector('[data-calendar-status]');
  const rangeLabel = root.querySelector('[data-calendar-range-label]');
  const activeFiltersNode = root.querySelector('[data-calendar-active-filters]');
  const selectedCountNode = root.querySelector('[data-calendar-selected-count]');
  const selectVisible = root.querySelector('[data-calendar-select-visible]');
  const filters = Array.from(root.querySelectorAll('[data-calendar-filter]'));
  const productFilter = root.querySelector('[data-calendar-filter="product"]');
  const formats = { square: 'Post · 1:1', portrait: 'Portrait · 4:5', story: 'Story / Reel · 9:16' };
  const layouts = { spotlight: 'Spotlight', split: 'Split Feature', bold: 'Bold Offer' };
  const statuses = { planned: 'Planned', downloaded: 'Downloaded', posted: 'Posted', skipped: 'Skipped' };
  const themes = { product_spotlight: 'Product Spotlight', gift_idea: 'Gift Idea', reward_promotion: 'Reward Promotion', merchant_story: 'Merchant Story', customer_review: 'Customer Review', local_support: 'Local Support' };
  const platformLabels = { general: 'General', facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn' };

  let products = [];
  let items = [];
  let visibleItems = [];
  let selected = new Set();
  let currentView = 'grid';
  let rangeStart = startOfToday();
  let dragId = '';

  function startOfToday() {
    const date = new Date();
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }
  function iso(date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
  }
  function parseDate(value) {
    const [year, month, day] = String(value || '').split('-').map(Number);
    return new Date(year, (month || 1) - 1, day || 1);
  }
  function addDays(date, amount) {
    const copy = new Date(date); copy.setDate(copy.getDate() + amount); return copy;
  }
  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[char]));
  }
  function payload(response) { return response && response.data ? response.data : response; }
  async function request(url, options = {}) {
    if (typeof MG.api === 'function') return payload(await MG.api(url, options));
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', ...(options.headers || {}) }, ...options });
    const json = await response.json().catch(() => ({}));
    const data = payload(json);
    if (!response.ok || json.ok === false || json.success === false) throw new Error(json.message || data.message || 'Request failed.');
    return data;
  }
  async function post(body) {
    if (typeof MG.post === 'function') return payload(await MG.post(endpoint, body));
    return request(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
  }
  function setStatus(message, kind = '') {
    statusNode.textContent = message || '';
    statusNode.className = 'mg-design-calendar-status' + (kind ? ` is-${kind}` : '');
  }
  function setLoading(value) { if (loading) loading.hidden = !value; }
  function setError(message = '') {
    if (!errorBox) return;
    errorBox.hidden = message === '';
    errorBox.textContent = message;
  }
  function setBusy(button, busy, label = 'Working…') {
    if (!button) return;
    if (busy) { button.dataset.previousLabel = button.textContent || ''; button.disabled = true; button.textContent = label; }
    else { button.disabled = false; button.textContent = button.dataset.previousLabel || button.textContent; }
  }
  function rangeEnd() { return addDays(rangeStart, Number(root.dataset.calendarDays || 30) - 1); }
  function updateRangeLabel() {
    rangeLabel.textContent = `${rangeStart.toLocaleDateString(undefined,{month:'short',day:'numeric'})} – ${rangeEnd().toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'})}`;
  }

  function productTitle(product) { return String(product.title || product.slug || 'Untitled product'); }
  function populateProducts() {
    productList.replaceChildren();
    if (!products.length) {
      productList.innerHTML = '<div class="mg-calendar-product-empty">No merchant products are available.</div>';
      productCount.textContent = '0 products';
      return;
    }
    products.forEach((product) => {
      const row = document.createElement('div');
      row.className = 'mg-design-calendar-product-option';
      row.innerHTML = `<label><input type="checkbox" name="product_ids[]" value="${escapeHtml(product.public_id)}"><span><strong>${escapeHtml(productTitle(product))}</strong><small>${escapeHtml(product.slug || product.public_id)}</small></span></label><em>${product.status === 'published' ? 'Published' : 'Draft'}</em>`;
      productList.appendChild(row);
    });
    productCount.textContent = `${products.length} product${products.length === 1 ? '' : 's'}`;
    if (productFilter) {
      const current = productFilter.value;
      productFilter.innerHTML = '<option value="">All products</option>' + products.map((product) => `<option value="${escapeHtml(product.public_id)}">${escapeHtml(productTitle(product))}</option>`).join('');
      productFilter.value = current;
    }
  }
  async function loadProducts() {
    const data = await request('/api/merchant/products.php?sort=updated_desc&limit=100');
    products = Array.isArray(data.products) ? data.products : [];
    populateProducts();
  }

  function currentFilters() {
    const values = {};
    filters.forEach((field) => { values[field.dataset.calendarFilter] = String(field.value || ''); });
    return values;
  }
  function applyFilters() {
    const filter = currentFilters();
    visibleItems = items.filter((item) => {
      if (filter.product && item.product_id !== filter.product) return false;
      if (filter.format && item.post_format !== filter.format) return false;
      if (filter.layout && item.layout_key !== filter.layout) return false;
      if (filter.status && item.status !== filter.status) return false;
      if (filter.date_from && item.scheduled_date < filter.date_from) return false;
      if (filter.date_to && item.scheduled_date > filter.date_to) return false;
      return true;
    });
    const active = Object.entries(filter).filter(([,value]) => value !== '');
    activeFiltersNode.textContent = active.length ? `${active.length} active filter${active.length === 1 ? '' : 's'} · ${visibleItems.length} shown` : 'No active filters';
    selected = new Set([...selected].filter((id) => visibleItems.some((item) => item.public_id === id)));
    render();
  }

  function copyGroup(item, platform) {
    const variants = item.platform_copy?.[platform] || {};
    return `<div class="mg-calendar-platform-copy" data-copy-platform="${platform}"><header><strong>${platformLabels[platform]}</strong></header>${['short','standard','extended'].map((size) => `<div><span>${size[0].toUpperCase()+size.slice(1)}</span><button type="button" data-calendar-copy="${platform}:${size}">Copy</button><textarea data-platform-copy="${platform}:${size}">${escapeHtml(variants[size] || item[`caption_${size}`] || '')}</textarea></div>`).join('')}</div>`;
  }
  function eventMarkup(item, compact = false) {
    const checked = selected.has(item.public_id) ? ' checked' : '';
    const saved = Number(item.saved_asset_count || 0);
    return `<article class="mg-design-calendar-event is-${escapeHtml(item.status)}" data-calendar-event="${escapeHtml(item.public_id)}" draggable="true" tabindex="0">
      <div class="mg-design-calendar-event-head"><label class="mg-calendar-select-item"><input type="checkbox" data-calendar-select-item="${escapeHtml(item.public_id)}"${checked}><span class="sr-only">Select ${escapeHtml(item.title)}</span></label><strong>${escapeHtml(item.title || item.slug || 'Scheduled product')}</strong><span>${escapeHtml(themes[item.campaign_theme] || item.campaign_theme || 'Theme')}</span></div>
      ${item.image_url ? `<img class="mg-calendar-event-image" src="${escapeHtml(item.image_url)}" alt="" loading="lazy">` : ''}
      <div class="mg-calendar-event-meta"><span>${escapeHtml(formats[item.post_format] || item.post_format)}</span><span>${escapeHtml(layouts[item.layout_key] || item.layout_key)}</span>${saved ? `<span>${saved} saved asset${saved===1?'':'s'}</span>` : ''}</div>
      <div class="mg-calendar-event-controls">
        <label><span>Date</span><input type="date" value="${escapeHtml(item.scheduled_date)}" data-calendar-field="scheduled_date"></label>
        <label><span>Format</span><select data-calendar-field="post_format">${Object.entries(formats).map(([key,label])=>`<option value="${key}"${item.post_format===key?' selected':''}>${label}</option>`).join('')}</select></label>
        <label><span>Layout</span><select data-calendar-field="layout_key">${Object.entries(layouts).map(([key,label])=>`<option value="${key}"${item.layout_key===key?' selected':''}>${label}</option>`).join('')}</select></label>
        <label><span>Status</span><select data-calendar-field="status">${Object.entries(statuses).map(([key,label])=>`<option value="${key}"${item.status===key?' selected':''}>${label}</option>`).join('')}</select></label>
      </div>
      <div class="mg-design-calendar-event-actions"><button type="button" data-calendar-open>Creative</button><button type="button" data-calendar-duplicate>Duplicate</button><button type="button" data-calendar-remove>Remove</button></div>
      <details class="mg-calendar-copy-editor"><summary>Captions & posting copy</summary>
        <div class="mg-calendar-copy-basics">
          <label><span>Short caption</span><textarea data-caption-field="caption_short">${escapeHtml(item.caption_short || '')}</textarea><button type="button" data-calendar-copy="base:caption_short">Copy</button></label>
          <label><span>Standard caption</span><textarea data-caption-field="caption_standard">${escapeHtml(item.caption_standard || '')}</textarea><button type="button" data-calendar-copy="base:caption_standard">Copy</button></label>
          <label><span>Extended caption</span><textarea data-caption-field="caption_extended">${escapeHtml(item.caption_extended || '')}</textarea><button type="button" data-calendar-copy="base:caption_extended">Copy</button></label>
          <label><span>Hashtags</span><input data-caption-field="hashtags" value="${escapeHtml(item.hashtags || '')}"><button type="button" data-calendar-copy="base:hashtags">Copy</button></label>
          <label><span>Product link</span><input data-caption-field="product_link" value="${escapeHtml(item.product_link || '')}"><button type="button" data-calendar-copy="base:product_link">Copy</button></label>
          <label><span>Call to action</span><input data-caption-field="call_to_action" value="${escapeHtml(item.call_to_action || '')}"><button type="button" data-calendar-copy="base:call_to_action">Copy</button></label>
        </div>
        <div class="mg-calendar-platform-grid">${Object.keys(platformLabels).map((platform)=>copyGroup(item,platform)).join('')}</div>
        <button type="button" class="mg-btn mg-btn-primary" data-calendar-save-copy>Save posting copy</button>
      </details>
    </article>`;
  }

  function monthGridMarkup() {
    const grouped = new Map();
    visibleItems.forEach((item) => { const key = item.scheduled_date.slice(0,7); if (!grouped.has(key)) grouped.set(key, []); grouped.get(key).push(item); });
    const months = [];
    let cursor = new Date(rangeStart.getFullYear(), rangeStart.getMonth(), 1);
    const endMonth = new Date(rangeEnd().getFullYear(), rangeEnd().getMonth(), 1);
    while (cursor <= endMonth) { months.push(new Date(cursor)); cursor.setMonth(cursor.getMonth()+1); }
    return `<div class="mg-design-calendar-months">${months.map((month) => {
      const key = `${month.getFullYear()}-${String(month.getMonth()+1).padStart(2,'0')}`;
      const first = new Date(month.getFullYear(),month.getMonth(),1);
      const start = addDays(first,-first.getDay());
      const days = Array.from({length:42},(_,index)=>addDays(start,index));
      return `<section class="mg-design-calendar-month"><header><strong>${month.toLocaleDateString(undefined,{month:'long',year:'numeric'})}</strong></header><div class="mg-design-calendar-weekdays">${['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map((day)=>`<span>${day}</span>`).join('')}</div><div class="mg-design-calendar-days">${days.map((date)=>{
        const dateIso=iso(date); const outside=date.getMonth()!==month.getMonth(); const today=dateIso===iso(startOfToday());
        const dayItems=(grouped.get(key)||[]).filter((item)=>item.scheduled_date===dateIso);
        return `<div class="mg-design-calendar-day${outside?' is-outside':''}${today?' is-today':''}" data-calendar-drop-date="${dateIso}"><span class="mg-design-calendar-day-number">${date.getDate()}</span><div class="mg-design-calendar-day-events">${dayItems.map((item)=>eventMarkup(item,true)).join('')}</div></div>`;
      }).join('')}</div></section>`;
    }).join('')}</div>`;
  }
  function renderStack() {
    const groups = new Map();
    visibleItems.forEach((item)=>{ if(!groups.has(item.scheduled_date))groups.set(item.scheduled_date,[]);groups.get(item.scheduled_date).push(item); });
    stack.innerHTML = [...groups.entries()].map(([date,group])=>`<section class="mg-calendar-stack-day"><header><strong>${parseDate(date).toLocaleDateString(undefined,{weekday:'long',month:'long',day:'numeric'})}</strong><span>${group.length} post${group.length===1?'':'s'}</span></header>${group.map((item)=>eventMarkup(item)).join('')}</section>`).join('');
  }
  function updateCounts() {
    root.querySelector('[data-calendar-count="total"]').textContent = String(items.length);
    Object.keys(statuses).forEach((status)=>{ const node=root.querySelector(`[data-calendar-count="${status}"]`);if(node)node.textContent=String(items.filter((item)=>item.status===status).length); });
  }
  function updateSelection() {
    selectedCountNode.textContent = String(selected.size);
    if (selectVisible) selectVisible.checked = visibleItems.length > 0 && visibleItems.every((item)=>selected.has(item.public_id));
  }
  function render() {
    updateCounts(); updateSelection();
    const hasItems = visibleItems.length > 0;
    empty.hidden = hasItems || setup.hidden === false;
    grid.hidden = currentView !== 'grid' || !hasItems;
    stack.hidden = currentView !== 'stack' || !hasItems;
    if (!hasItems) { grid.innerHTML='';stack.innerHTML='';return; }
    grid.innerHTML = monthGridMarkup();
    renderStack();
  }

  async function loadSchedule() {
    setLoading(true); setError(''); setup.hidden = true; empty.hidden = true;
    try {
      const data = await request(`${endpoint}?from=${encodeURIComponent(iso(rangeStart))}&to=${encodeURIComponent(iso(rangeEnd()))}`);
      if (data.setup_required) { items=[];visibleItems=[];setup.hidden=false;render();return; }
      items = Array.isArray(data.items) ? data.items : [];
      applyFilters(); updateRangeLabel();
    } catch (error) { items=[];visibleItems=[];setError(error.message || 'Unable to load the calendar.');render(); }
    finally { setLoading(false); }
  }

  function selectedFormValues(name) { return Array.from(form.querySelectorAll(`[name="${name}[]"]:checked`)).map((field)=>field.value); }
  async function generatePlan(event) {
    event.preventDefault();
    const productIds = selectedFormValues('product_ids');
    if (!productIds.length) { setStatus('Choose at least one merchant product.','error');return; }
    if (items.length && !window.confirm('Regenerate this 30-day plan? Existing schedule rows in the window will be replaced. Saved creative assets will remain available.')) return;
    const body = {
      action:'generate', start_date:startInput.value, product_ids:productIds,
      frequency:form.elements.frequency.value, preferred_time:form.elements.preferred_time.value,
      preferred_weekdays:selectedFormValues('preferred_weekdays'), formats:selectedFormValues('formats'), layouts:selectedFormValues('layouts'), themes:selectedFormValues('themes'),
      timezone:Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC', replace:true,
    };
    setBusy(generateButton,true,'Building plan…');setStatus('Balancing products, formats, layouts, and themes…');
    try { const data=await post(body);rangeStart=parseDate(data.from||startInput.value);items=Array.isArray(data.items)?data.items:[];applyFilters();updateRangeLabel();setStatus(`${data.created_count||items.length} scheduled posts created.`,'success'); }
    catch(error){setStatus(error.message||'Unable to build the plan.','error');}
    finally{setBusy(generateButton,false);}
  }
  async function updateItem(id, changes, message = 'Calendar item updated.') {
    setStatus('Saving calendar change…');
    await post({action:'update',schedule_id:id,...changes});
    const item=items.find((row)=>row.public_id===id);if(item)Object.assign(item,changes);
    applyFilters();setStatus(message,'success');
  }
  function collectCopy(article, item) {
    const changes = {};
    article.querySelectorAll('[data-caption-field]').forEach((field)=>{changes[field.dataset.captionField]=field.value;});
    const platformCopy={};
    article.querySelectorAll('[data-platform-copy]').forEach((field)=>{const [platform,size]=field.dataset.platformCopy.split(':');platformCopy[platform]=platformCopy[platform]||{};platformCopy[platform][size]=field.value;});
    changes.platform_copy=platformCopy;Object.assign(item,changes,{platform_copy:platformCopy});return changes;
  }
  async function copyText(text) {
    if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(text);
    const area=document.createElement('textarea');area.value=text;document.body.appendChild(area);area.select();document.execCommand('copy');area.remove();
  }
  function openCreative(item) {
    document.dispatchEvent(new CustomEvent('design-studio:schedule-context',{detail:{item}}));
    const socialButton=app?.querySelector('[data-design-mode="social"]');socialButton?.click();
    const productSelect=app?.querySelector('[data-social-product-select]');
    const apply=()=>{if(productSelect){productSelect.value=item.product_id;productSelect.dispatchEvent(new Event('change',{bubbles:true}));}app?.querySelector(`[data-social-format="${item.post_format}"]`)?.click();app?.querySelector(`[data-social-layout="${item.layout_key}"]`)?.click();};
    window.setTimeout(apply,80);app?.scrollIntoView({block:'start'});
  }
  async function moveItem(id,date) {
    const item=items.find((row)=>row.public_id===id);if(!item||item.scheduled_date===date)return;
    await updateItem(id,{scheduled_date:date},'Scheduled date updated.');
  }
  async function duplicateItem(item) {
    const target=iso(addDays(parseDate(item.scheduled_date),1));
    const response=await post({action:'duplicate',schedule_id:item.public_id,scheduled_date:target});
    setStatus(`Post duplicated to ${parseDate(response.scheduled_date||target).toLocaleDateString()}.`,'success');await loadSchedule();
  }
  async function removeItem(item) {
    if(!window.confirm(`Remove the scheduled post for ${item.title || 'this product'}? Saved creative assets will not be deleted.`))return;
    await post({action:'delete',schedule_id:item.public_id});items=items.filter((row)=>row.public_id!==item.public_id);selected.delete(item.public_id);applyFilters();setStatus('Scheduled post removed.','success');
  }

  async function handleEventClick(event) {
    const article=event.target.closest('[data-calendar-event]');if(!article)return;
    const id=article.dataset.calendarEvent;const item=items.find((row)=>row.public_id===id);if(!item)return;
    if(event.target.matches('[data-calendar-open]'))openCreative(item);
    if(event.target.matches('[data-calendar-duplicate]')){try{await duplicateItem(item);}catch(error){setStatus(error.message||'Unable to duplicate the post.','error');}}
    if(event.target.matches('[data-calendar-remove]')){try{await removeItem(item);}catch(error){setStatus(error.message||'Unable to remove the post.','error');}}
    if(event.target.matches('[data-calendar-save-copy]')){try{const changes=collectCopy(article,item);await updateItem(id,changes,'Posting copy saved.');}catch(error){setStatus(error.message||'Unable to save posting copy.','error');}}
    const copyButton=event.target.closest('[data-calendar-copy]');
    if(copyButton){const key=copyButton.dataset.calendarCopy;let field;if(key.startsWith('base:'))field=article.querySelector(`[data-caption-field="${key.slice(5)}"]`);else field=article.querySelector(`[data-platform-copy="${key}"]`);try{await copyText(field?.value||'');setStatus('Posting copy copied.','success');}catch(_){setStatus('Copy is unavailable in this browser.','error');}}
  }
  async function handleEventChange(event) {
    const article=event.target.closest('[data-calendar-event]');if(!article)return;
    const id=article.dataset.calendarEvent;
    if(event.target.matches('[data-calendar-select-item]')){event.target.checked?selected.add(id):selected.delete(id);updateSelection();return;}
    const field=event.target.closest('[data-calendar-field]');if(!field)return;
    try{await updateItem(id,{[field.dataset.calendarField]:field.value});}catch(error){setStatus(error.message||'Unable to update the item.','error');await loadSchedule();}
  }
  function bindBoard(container) {
    container.addEventListener('click',handleEventClick);
    container.addEventListener('change',handleEventChange);
    container.addEventListener('dragstart',(event)=>{const article=event.target.closest('[data-calendar-event]');if(!article)return;dragId=article.dataset.calendarEvent;event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',dragId);});
    container.addEventListener('dragover',(event)=>{if(event.target.closest('[data-calendar-drop-date]')){event.preventDefault();event.dataTransfer.dropEffect='move';}});
    container.addEventListener('drop',async(event)=>{const day=event.target.closest('[data-calendar-drop-date]');if(!day)return;event.preventDefault();const id=event.dataTransfer.getData('text/plain')||dragId;try{await moveItem(id,day.dataset.calendarDropDate);}catch(error){setStatus(error.message||'Unable to move the scheduled post.','error');}});
  }

  async function applyBulk() {
    if(!selected.size){setStatus('Select at least one scheduled post.','error');return;}
    const format=root.querySelector('[data-calendar-bulk-format]').value;
    const layout=root.querySelector('[data-calendar-bulk-layout]').value;
    const status=root.querySelector('[data-calendar-bulk-status]').value;
    const changes={};if(format)changes.post_format=format;if(layout)changes.layout_key=layout;if(status)changes.status=status;
    if(!Object.keys(changes).length){setStatus('Choose a bulk format, layout, or status change.','error');return;}
    try{await post({action:'bulk_update',schedule_ids:[...selected],...changes});items.forEach((item)=>{if(selected.has(item.public_id))Object.assign(item,changes);});applyFilters();setStatus(`${selected.size} scheduled posts updated.`,'success');}
    catch(error){setStatus(error.message||'Unable to update selected posts.','error');}
  }
  async function removeBulk() {
    if(!selected.size){setStatus('Select at least one scheduled post.','error');return;}
    if(!window.confirm(`Remove ${selected.size} selected scheduled post${selected.size===1?'':'s'}? Saved creative assets will remain available.`))return;
    try{await post({action:'bulk_delete',schedule_ids:[...selected]});items=items.filter((item)=>!selected.has(item.public_id));selected.clear();applyFilters();setStatus('Selected scheduled posts removed.','success');}
    catch(error){setStatus(error.message||'Unable to remove selected posts.','error');}
  }

  root.querySelectorAll('[data-calendar-view]').forEach((button)=>button.addEventListener('click',()=>{currentView=button.dataset.calendarView==='stack'?'stack':'grid';root.querySelectorAll('[data-calendar-view]').forEach((item)=>{const active=item===button;item.classList.toggle('is-active',active);item.setAttribute('aria-pressed',active?'true':'false');});render();}));
  root.querySelectorAll('[data-calendar-range]').forEach((button)=>button.addEventListener('click',()=>{rangeStart=addDays(rangeStart,Number(button.dataset.calendarRange||0));startInput.value=iso(rangeStart);selected.clear();loadSchedule();}));
  root.querySelector('[data-calendar-today]')?.addEventListener('click',()=>{rangeStart=startOfToday();startInput.value=iso(rangeStart);selected.clear();loadSchedule();});
  root.querySelector('[data-calendar-clear-filters]')?.addEventListener('click',()=>{filters.forEach((field)=>{field.value='';});applyFilters();});
  filters.forEach((field)=>field.addEventListener('change',applyFilters));
  selectAllProducts?.addEventListener('change',()=>{productList.querySelectorAll('input[type="checkbox"]').forEach((box)=>{box.checked=selectAllProducts.checked;});});
  selectVisible?.addEventListener('change',()=>{visibleItems.forEach((item)=>{selectVisible.checked?selected.add(item.public_id):selected.delete(item.public_id);});render();});
  root.querySelector('[data-calendar-bulk-apply]')?.addEventListener('click',applyBulk);
  root.querySelector('[data-calendar-bulk-remove]')?.addEventListener('click',removeBulk);
  form?.addEventListener('submit',generatePlan);
  bindBoard(grid);bindBoard(stack);

  const query=new URLSearchParams(location.search);const requestedStart=query.get('start');
  if(requestedStart && /^\d{4}-\d{2}-\d{2}$/.test(requestedStart))rangeStart=parseDate(requestedStart);
  startInput.value=iso(rangeStart);updateRangeLabel();
  Promise.all([loadProducts(),loadSchedule()]).catch((error)=>{setError(error.message||'Unable to initialize the advertising calendar.');});
})();

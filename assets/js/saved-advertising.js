(() => {
  'use strict';
  const page=document.querySelector('[data-user-saves-page]');
  const panel=page?.querySelector('[data-saved-advertising]');
  if(!page||!panel)return;
  const MG=window.Microgifter||{};
  const endpoint='/api/merchant/design-advertising-assets.php';
  const tabs=Array.from(page.querySelectorAll('[data-saves-tab]'));
  const sections=Array.from(page.querySelectorAll('[data-saves-panel]'));
  const grid=panel.querySelector('[data-advertising-grid]');
  const statusNode=panel.querySelector('[data-advertising-status]');
  const loading=panel.querySelector('[data-advertising-loading]');
  const empty=panel.querySelector('[data-advertising-empty]');
  const errorBox=panel.querySelector('[data-advertising-error]');
  const setup=panel.querySelector('[data-advertising-setup]');
  const filters=Array.from(panel.querySelectorAll('[data-advertising-filter]'));
  let assets=[];

  const payload=(response)=>response&&response.data?response.data:response;
  async function request(url,options={}){
    if(typeof MG.api==='function')return payload(await MG.api(url,options));
    const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.headers||{})},...options});
    const json=await response.json().catch(()=>({}));const data=payload(json);
    if(!response.ok||json.ok===false||json.success===false)throw new Error(json.message||data.message||'Request failed.');
    return data;
  }
  async function post(body){if(typeof MG.post==='function')return payload(await MG.post(endpoint,body));return request(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});}
  function escapeHtml(value){return String(value??'').replace(/[&<>'"]/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));}
  function label(value){return String(value||'').replace(/_/g,' ').replace(/\b\w/g,(char)=>char.toUpperCase());}
  function formatDate(value){if(!value)return '—';const date=new Date(String(value).replace(' ','T')+'Z');return Number.isNaN(date.getTime())?String(value):date.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});}
  function setState({busy=false,message='',kind='',error='',setupRequired=false}={}){
    loading.hidden=!busy;statusNode.textContent=message;statusNode.className='mg-saves-status'+(kind?` is-${kind}`:'');errorBox.hidden=!error;errorBox.textContent=error;setup.hidden=!setupRequired;
  }
  function filterQuery(){const params=new URLSearchParams();filters.forEach((field)=>{if(field.value)params.set(field.dataset.advertisingFilter,field.value);});return params.toString();}
  function productOptions(){const productSelect=panel.querySelector('[data-advertising-filter="product_id"]');if(!productSelect)return;const current=productSelect.value;const map=new Map();assets.forEach((asset)=>{if(asset.product_id&&asset.product_name)map.set(asset.product_id,asset.product_name);});productSelect.innerHTML='<option value="">All products</option>'+[...map.entries()].map(([id,name])=>`<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`).join('');productSelect.value=current;}
  function card(asset){return `<article class="mg-advertising-card" data-advertising-asset="${escapeHtml(asset.id)}">
    <a class="mg-advertising-preview" href="${escapeHtml(asset.image_url||'#')}" target="_blank" rel="noopener">${asset.image_url?`<img src="${escapeHtml(asset.image_url)}" alt="${escapeHtml(asset.title)}" loading="lazy">`:'<span>Preview unavailable</span>'}</a>
    <div class="mg-advertising-card-body"><header><span>${escapeHtml(asset.asset_kind==='print'?'Print creative':'Social creative')}</span><h3>${escapeHtml(asset.title)}</h3></header>
      <dl><div><dt>Product</dt><dd>${escapeHtml(asset.product_name||'No product association')}</dd></div><div><dt>Format</dt><dd>${escapeHtml(label(asset.format_key))}</dd></div><div><dt>Layout</dt><dd>${escapeHtml(label(asset.layout_key))}</dd></div><div><dt>Date saved</dt><dd>${escapeHtml(formatDate(asset.date_saved))}</dd></div><div><dt>Scheduled date</dt><dd>${escapeHtml(asset.scheduled_date?formatDate(asset.scheduled_date):'Not scheduled')}</dd></div><div><dt>Status</dt><dd>${escapeHtml(label(asset.status))}</dd></div></dl>
      <div class="mg-advertising-card-actions"><a class="mg-btn mg-btn-primary" href="${escapeHtml(asset.download_url||'#')}" download>Download</a><a class="mg-btn mg-btn-soft" href="${escapeHtml(asset.design_url)}">Open in Design Studio</a><button type="button" data-advertising-action="rename">Rename</button><button type="button" data-advertising-action="${asset.status==='archived'?'restore':'archive'}">${asset.status==='archived'?'Restore':'Archive'}</button><button type="button" class="is-danger" data-advertising-action="remove">Remove</button></div>
    </div></article>`;}
  function render(){grid.innerHTML=assets.map(card).join('');empty.hidden=assets.length>0;statusNode.textContent=`${assets.length} saved creative${assets.length===1?'':'s'}`;productOptions();}
  async function load(){setState({busy:true,message:'Loading saved advertising…'});try{const query=filterQuery();const data=await request(endpoint+(query?`?${query}`:''));if(data.setup_required){assets=[];grid.innerHTML='';empty.hidden=true;setState({setupRequired:true,message:'Setup required'});return;}assets=Array.isArray(data.assets)?data.assets:[];setState({message:''});render();}catch(error){assets=[];grid.innerHTML='';empty.hidden=true;setState({error:error.message||'Saved advertising could not be loaded.',message:'Unable to load'});}finally{loading.hidden=true;}}
  function activate(tab){tabs.forEach((button)=>{const active=button.dataset.savesTab===tab;button.classList.toggle('is-active',active);button.setAttribute('aria-selected',active?'true':'false');});sections.forEach((section)=>{section.hidden=section.dataset.savesPanel!==tab;});const url=new URL(location.href);if(tab==='advertising')url.searchParams.set('tab','advertising');else url.searchParams.delete('tab');history.replaceState({},'',url);if(tab==='advertising'&&!panel.dataset.loaded){panel.dataset.loaded='true';load();}}
  async function act(asset,action){let body={action,asset_id:asset.id};if(action==='rename'){const title=window.prompt('Name this creative asset:',asset.title);if(title===null)return;if(!title.trim())return;body.title=title.trim();}if(action==='archive'&&!window.confirm('Archive this creative? It will remain available through the Archived filter.'))return;if(action==='remove'&&!window.confirm('Permanently remove this saved creative and its stored image?'))return;setState({message:'Updating creative…'});try{await post(body);if(action==='remove')assets=assets.filter((item)=>item.id!==asset.id);else if(action==='rename')asset.title=body.title;else asset.status=action==='archive'?'archived':'active';render();setState({message:'Saved creative updated.',kind:'success'});}catch(error){setState({message:error.message||'The creative could not be updated.',kind:'error'});}}

  tabs.forEach((button)=>button.addEventListener('click',()=>activate(button.dataset.savesTab)));
  filters.forEach((field)=>field.addEventListener('change',load));
  panel.querySelector('[data-advertising-refresh]')?.addEventListener('click',load);
  grid.addEventListener('click',(event)=>{const button=event.target.closest('[data-advertising-action]');const cardNode=event.target.closest('[data-advertising-asset]');if(!button||!cardNode)return;const asset=assets.find((item)=>item.id===cardNode.dataset.advertisingAsset);if(asset)act(asset,button.dataset.advertisingAction);});
  activate(new URLSearchParams(location.search).get('tab')==='advertising'?'advertising':'saved-items');
})();

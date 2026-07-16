(() => {
  'use strict';
  const app = document.querySelector('[data-agent-design-studio]');
  if (!app) return;
  const MG = window.Microgifter || {};
  const endpoint = '/api/merchant/design-advertising-assets.php';
  const keys = new Map();
  let scheduleContext = null;
  function ensureButtons() {
    const printActions=app.querySelector('.mg-agent-design-actions');
    if(printActions&&!printActions.querySelector('[data-design-save-asset]')){const button=document.createElement('button');button.type='button';button.className='mg-btn mg-btn-soft';button.dataset.designSaveAsset='true';button.textContent='Save Creative Asset';printActions.insertBefore(button,printActions.firstChild);}
    const socialActions=app.querySelector('.mg-agent-social-actions');
    if(socialActions&&!socialActions.querySelector('[data-social-save-asset]')){const button=document.createElement('button');button.type='button';button.className='mg-btn mg-btn-soft';button.dataset.socialSaveAsset='true';button.textContent='Save Creative Asset';socialActions.insertBefore(button,socialActions.firstChild);}
  }
  ensureButtons();

  const payload = (response) => response && response.data ? response.data : response;
  async function request(url, options = {}) {
    if (typeof MG.api === 'function') return payload(await MG.api(url, options));
    const response = await fetch(url, { credentials:'same-origin', headers:{Accept:'application/json',...(options.headers||{})}, ...options });
    const json = await response.json().catch(()=>({}));
    const data = payload(json);
    if (!response.ok || json.ok === false || json.success === false) throw new Error(json.message || data.message || 'Request failed.');
    return data;
  }
  function setButton(button, busy, label) {
    if (!button) return;
    if (busy) { button.dataset.label=button.textContent||'';button.disabled=true;button.textContent=label; }
    else { button.disabled=false;button.textContent=button.dataset.label||button.textContent; }
  }
  function statusNode(kind) { return app.querySelector(kind === 'print' ? '[data-design-status]' : '[data-social-status]'); }
  function setStatus(kind, message, type='') {
    const node=statusNode(kind);if(!node)return;node.textContent=message;node.classList.toggle('is-success',type==='success');node.classList.toggle('is-error',type==='error');
  }
  function loadHtml2Canvas() {
    return new Promise((resolve,reject)=>{
      if(typeof window.html2canvas==='function'){resolve();return;}
      const src='https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
      const existing=document.querySelector(`script[src="${src}"]`);
      if(existing){existing.addEventListener('load',resolve,{once:true});existing.addEventListener('error',reject,{once:true});return;}
      const script=document.createElement('script');script.src=src;script.async=true;script.onload=resolve;script.onerror=reject;document.head.appendChild(script);
    });
  }
  async function waitForImages(canvas) {
    await Promise.all(Array.from(canvas.querySelectorAll('img')).filter((image)=>!image.hidden&&image.src).map((image)=>image.complete?Promise.resolve():new Promise((resolve)=>{image.addEventListener('load',resolve,{once:true});image.addEventListener('error',resolve,{once:true});setTimeout(resolve,2500);}))); 
  }
  function blobFromCanvas(rendered) {
    return new Promise((resolve,reject)=>rendered.toBlob((blob)=>blob?resolve(blob):reject(new Error('The creative image could not be prepared.')),'image/jpeg',0.96));
  }
  function randomKey(signature) {
    if (!keys.has(signature)) keys.set(signature, `design-save:${crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`}`);
    return keys.get(signature);
  }
  function copyBundle() {
    const item=scheduleContext?.item;
    if(item)return {short:item.caption_short||'',standard:item.caption_standard||'',extended:item.caption_extended||'',hashtags:item.hashtags||'',product_link:item.product_link||'',call_to_action:item.call_to_action||'',platforms:item.platform_copy||{}};
    const title=app.querySelector('[data-social-product-title]')?.textContent?.trim()||'Local favorite';
    const description=app.querySelector('[data-social-product-description]')?.textContent?.trim()||'Discover this local product, service, or experience on Microgifter.';
    const link='';const short=`Take a closer look at ${title}.`;const standard=`${short}\n\n${description}\n\n#ShopLocal #SupportLocal #Microgifter`;
    return {short,standard,extended:standard,hashtags:'#ShopLocal #SupportLocal #Microgifter',product_link:link,call_to_action:'Explore this local favorite',platforms:{general:{short,standard,extended:standard},facebook:{short,standard,extended:standard},instagram:{short:`${short}\n#ShopLocal #Microgifter`,standard,extended:standard},linkedin:{short,standard,extended:standard}}};
  }
  async function save(kind) {
    const isPrint=kind==='print';
    const button=app.querySelector(isPrint?'[data-design-save-asset]':'[data-social-save-asset]');
    const canvas=app.querySelector(isPrint?'[data-design-canvas]':'[data-social-canvas]');
    if(!canvas||canvas.hidden)throw new Error(isPrint?'Choose a print template first.':'Choose a product first.');
    const productSelect=app.querySelector('[data-social-product-select]');
    const productId=isPrint?'':String(productSelect?.value||scheduleContext?.item?.product_id||'');
    const format=isPrint?(canvas.classList.contains('is-tent')?'tent':'poster'):(app.querySelector('[data-social-format].is-active')?.dataset.socialFormat||scheduleContext?.item?.post_format||'square');
    const layout=isPrint?(app.querySelector('[data-design-template].is-active')?.dataset.designTemplate||'support-local'):(app.querySelector('[data-social-layout].is-active')?.dataset.socialLayout||scheduleContext?.item?.layout_key||'spotlight');
    const title=isPrint?(app.querySelector('[data-design-template].is-active strong')?.textContent?.trim()||'Microgifter print creative'):(app.querySelector('[data-social-product-title]')?.textContent?.trim()||'Microgifter social creative');
    const scheduleId=scheduleContext?.item?.public_id||new URLSearchParams(location.search).get('schedule')||'';
    const signature=[kind,productId,format,layout,scheduleId,title].join('|');
    setButton(button,true,'Saving…');setStatus(kind,'Rendering and saving your creative…');
    try {
      await loadHtml2Canvas();await waitForImages(canvas);
      const rendered=await window.html2canvas(canvas,{backgroundColor:isPrint?'#eef3f8':'#0b1f3a',scale:isPrint?3:2.5,useCORS:true,allowTaint:false,logging:false});
      const blob=await blobFromCanvas(rendered);
      const filename=`microgifter-${kind}-${format}-${Date.now()}.jpg`;
      const data=new FormData();data.append('action','save');data.append('creative',new File([blob],filename,{type:'image/jpeg'}),filename);data.append('idempotency_key',randomKey(signature));data.append('asset_kind',kind);data.append('format_key',format);data.append('layout_key',layout);data.append('title',title);data.append('product_id',productId);data.append('schedule_id',scheduleId);data.append('caption',JSON.stringify(copyBundle()));data.append('render_metadata',JSON.stringify({source:'design_studio',format,layout,width:rendered.width,height:rendered.height,device_pixel_ratio:window.devicePixelRatio||1}));
      const result=await request(endpoint,{method:'POST',body:data});
      setStatus(kind,result.duplicate?'This creative was already saved.':'Creative asset saved.','success');
      document.dispatchEvent(new CustomEvent('design-studio:creative-saved',{detail:result.asset||null}));
    } catch(error){setStatus(kind,error.message||'The creative could not be saved.','error');}
    finally{setButton(button,false);}
  }

  document.addEventListener('design-studio:schedule-context',(event)=>{scheduleContext=event.detail||null;});
  app.querySelector('[data-social-product-select]')?.addEventListener('change',()=>{if(scheduleContext?.item?.product_id!==app.querySelector('[data-social-product-select]')?.value)scheduleContext=null;});
  app.querySelector('[data-design-save-asset]')?.addEventListener('click',()=>save('print'));
  app.querySelector('[data-social-save-asset]')?.addEventListener('click',()=>save('social'));
})();

(() => {
  'use strict';
  const app=document.querySelector('[data-agent-design-studio]');
  if(!app)return;
  const MG=window.Microgifter||{};
  const endpoint='/api/merchant/design-advertising-assets.php';
  const keys=new Map();
  let scheduleItem=window.MicrogifterDesignStudioScheduleContext||null;

  function addButton(container,attribute){
    if(!container||container.querySelector(`[${attribute}]`))return;
    const button=document.createElement('button');
    button.type='button';button.className='mg-btn mg-btn-soft';button.setAttribute(attribute,'true');button.textContent='Save Creative Asset';
    container.insertBefore(button,container.firstChild);
  }
  addButton(app.querySelector('.mg-agent-design-actions'),'data-design-save-asset');
  addButton(app.querySelector('.mg-agent-social-actions'),'data-social-save-asset');

  const payload=(response)=>response&&response.data?response.data:response;
  async function request(url,options={}){
    if(typeof MG.api==='function')return payload(await MG.api(url,options));
    const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.headers||{})},...options});
    const json=await response.json().catch(()=>({}));const data=payload(json);
    if(!response.ok||json.ok===false||json.success===false)throw new Error(json.message||data.message||'Request failed.');
    return data;
  }
  function status(kind,message,type=''){
    const node=app.querySelector(kind==='print'?'[data-design-status]':'[data-social-status]');
    if(!node)return;node.textContent=message;node.classList.toggle('is-success',type==='success');node.classList.toggle('is-error',type==='error');
  }
  function busy(button,value,label='Saving…'){
    if(!button)return;
    if(value){button.dataset.originalLabel=button.textContent||'';button.disabled=true;button.textContent=label;}
    else{button.disabled=false;button.textContent=button.dataset.originalLabel||'Save Creative Asset';}
  }
  function library(){
    return new Promise((resolve,reject)=>{
      if(typeof window.html2canvas==='function'){resolve();return;}
      const src='https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
      const existing=document.querySelector(`script[src="${src}"]`);
      if(existing){existing.addEventListener('load',resolve,{once:true});existing.addEventListener('error',reject,{once:true});return;}
      const script=document.createElement('script');script.src=src;script.async=true;script.onload=resolve;script.onerror=reject;document.head.appendChild(script);
    });
  }
  async function waitImages(canvas){
    await Promise.all(Array.from(canvas.querySelectorAll('img')).filter((image)=>!image.hidden&&image.src).map((image)=>image.complete?Promise.resolve():new Promise((resolve)=>{image.addEventListener('load',resolve,{once:true});image.addEventListener('error',resolve,{once:true});setTimeout(resolve,2500);})));
  }
  function canvasBlob(canvas){return new Promise((resolve,reject)=>canvas.toBlob((blob)=>blob?resolve(blob):reject(new Error('The creative image could not be prepared.')),'image/jpeg',.96));}
  function idempotency(signature){
    if(!keys.has(signature))keys.set(signature,`design-save:${window.crypto?.randomUUID?.()||`${Date.now()}-${Math.random().toString(16).slice(2)}`}`);
    return keys.get(signature);
  }
  function captions(){
    const item=scheduleItem;
    if(item)return {short:item.caption_short||'',standard:item.caption_standard||'',extended:item.caption_extended||'',hashtags:item.hashtags||'',product_link:item.product_link||'',call_to_action:item.call_to_action||'',platforms:item.platform_copy||{}};
    const title=app.querySelector('[data-social-product-title]')?.textContent?.trim()||'Local favorite';
    const description=app.querySelector('[data-social-product-description]')?.textContent?.trim()||'Discover this local product, service, or experience on Microgifter.';
    const short=`Take a closer look at ${title}.`,hashtags='#ShopLocal #SupportLocal #Microgifter',standard=`${short}\n\n${description}\n\n${hashtags}`;
    return {short,standard,extended:standard,hashtags,product_link:'',call_to_action:'Explore this local favorite',platforms:{general:{short,standard,extended:standard},facebook:{short,standard,extended:standard},instagram:{short:`${short}\n${hashtags}`,standard,extended:standard},linkedin:{short,standard,extended:standard}}};
  }
  async function save(kind){
    const print=kind==='print';
    const button=app.querySelector(print?'[data-design-save-asset]':'[data-social-save-asset]');
    const canvas=app.querySelector(print?'[data-design-canvas]':'[data-social-canvas]');
    if(!canvas||canvas.hidden){status(kind,print?'Choose a print template first.':'Choose a product first.','error');return;}
    const query=new URLSearchParams(location.search);
    const productSelect=app.querySelector('[data-social-product-select]');
    const productId=String(print?(scheduleItem?.product_id||query.get('product')||''):(productSelect?.value||scheduleItem?.product_id||query.get('product')||''));
    const format=print?(canvas.classList.contains('is-tent')?'tent':'poster'):(app.querySelector('[data-social-format].is-active')?.dataset.socialFormat||scheduleItem?.post_format||'square');
    const layout=print?(app.querySelector('[data-design-template].is-active')?.dataset.designTemplate||'support-local'):(app.querySelector('[data-social-layout].is-active')?.dataset.socialLayout||scheduleItem?.layout_key||'spotlight');
    const title=print?(app.querySelector('[data-design-template].is-active strong')?.textContent?.trim()||'Microgifter print creative'):(app.querySelector('[data-social-product-title]')?.textContent?.trim()||'Microgifter social creative');
    const scheduleId=String(scheduleItem?.public_id||query.get('schedule')||'');
    busy(button,true);status(kind,'Rendering and saving your creative…');
    try{
      await library();await waitImages(canvas);
      const rendered=await window.html2canvas(canvas,{backgroundColor:print?'#eef3f8':'#0b1f3a',scale:print?3:2.5,useCORS:true,allowTaint:false,logging:false});
      const blob=await canvasBlob(rendered),filename=`microgifter-${kind}-${format}-${Date.now()}.jpg`;
      const data=new FormData();
      data.append('action','save');data.append('creative',blob,filename);data.append('idempotency_key',idempotency([kind,productId,format,layout,scheduleId,title].join('|')));
      data.append('asset_kind',kind);data.append('format_key',format);data.append('layout_key',layout);data.append('title',title);data.append('product_id',productId);data.append('schedule_id',scheduleId);
      data.append('caption',JSON.stringify(captions()));data.append('render_metadata',JSON.stringify({source:'design_studio',format,layout,width:rendered.width,height:rendered.height,device_pixel_ratio:window.devicePixelRatio||1}));
      const result=await request(endpoint,{method:'POST',body:data});
      status(kind,result.duplicate?'This creative was already saved.':'Creative asset saved.','success');
      document.dispatchEvent(new CustomEvent('design-studio:creative-saved',{detail:result.asset||null}));
    }catch(error){status(kind,error.message||'The creative could not be saved.','error');}
    finally{busy(button,false);}
  }

  function calendarMode(){
    const button=app.querySelector('[data-calendar-mode-button]');
    app.querySelectorAll('[data-design-mode]').forEach((item)=>{item.classList.remove('is-active');item.setAttribute('aria-selected','false');});
    button?.classList.add('is-active');button?.setAttribute('aria-selected','true');
    app.querySelectorAll('[data-design-mode-panel]').forEach((panel)=>{panel.hidden=panel.dataset.designModePanel!=='calendar';});
    app.scrollIntoView({block:'start'});
  }
  function waitProduct(select,id,timeout=5000){
    if(!select||!id)return Promise.resolve(false);const start=Date.now();
    return new Promise((resolve)=>{const check=()=>{if(Array.from(select.options||[]).some((option)=>option.value===id)){resolve(true);return;}if(Date.now()-start>=timeout){resolve(false);return;}setTimeout(check,80);};check();});
  }
  async function socialMode(product,format='square',layout='spotlight'){
    app.querySelector('[data-design-mode="social"]')?.click();
    const select=app.querySelector('[data-social-product-select]');
    if(product&&select){await waitProduct(select,product);select.value=product;select.dispatchEvent(new Event('change',{bubbles:true}));}
    app.querySelector(`[data-social-format="${format}"]`)?.click();app.querySelector(`[data-social-layout="${layout}"]`)?.click();app.scrollIntoView({block:'start'});
  }
  function printMode(format='poster',layout='support-local'){
    app.querySelector('[data-design-mode="print"]')?.click();
    setTimeout(()=>{app.querySelector(`[data-design-object="${format}"]`)?.click();setTimeout(()=>app.querySelector(`[data-design-template="${layout}"]`)?.click(),60);},40);
    app.scrollIntoView({block:'start'});
  }

  document.addEventListener('design-studio:schedule-context',(event)=>{scheduleItem=event.detail?.item||null;window.MicrogifterDesignStudioScheduleContext=scheduleItem;});
  app.querySelector('[data-social-product-select]')?.addEventListener('change',()=>{if(scheduleItem?.product_id!==app.querySelector('[data-social-product-select]')?.value)scheduleItem=null;});
  app.querySelector('[data-design-save-asset]')?.addEventListener('click',()=>save('print'));
  app.querySelector('[data-social-save-asset]')?.addEventListener('click',()=>save('social'));
  app.querySelector('[data-calendar-mode-button]')?.addEventListener('click',calendarMode);
  app.querySelectorAll('[data-design-mode]').forEach((button)=>button.addEventListener('click',()=>{const calendar=app.querySelector('[data-calendar-mode-button]');calendar?.classList.remove('is-active');calendar?.setAttribute('aria-selected','false');}));

  const query=new URLSearchParams(location.search),mode=query.get('mode');
  if(mode==='calendar')requestAnimationFrame(()=>app.querySelector('[data-calendar-mode-button]')?.click());
  else if(mode==='social'&&query.get('product'))requestAnimationFrame(()=>socialMode(String(query.get('product')),String(query.get('format')||'square'),String(query.get('layout')||'spotlight')).catch((error)=>status('social',error.message||'Unable to open the linked creative.','error')));
  else if(mode==='print')requestAnimationFrame(()=>printMode(String(query.get('format')||'poster'),String(query.get('layout')||'support-local')));
})();

(function(){
  'use strict';
  const root=document.querySelector('[data-recording-editor]');
  if(!root)return;
  const id=Number(root.getAttribute('data-recording-id')||0);
  const csrf=root.getAttribute('data-csrf-token')||'';
  const canManage=root.getAttribute('data-can-manage')==='1';
  const video=root.querySelector('[data-editor-video]');
  const titleEl=root.querySelector('[data-editor-title]');
  const statusEl=root.querySelector('[data-editor-status]');
  const stage=root.querySelector('[data-preview-stage]');
  const layer=root.querySelector('[data-overlay-layer]');
  const track=root.querySelector('[data-timeline-track]');
  const playhead=root.querySelector('[data-playhead]');
  const range=root.querySelector('[data-timeline-range]');
  const overlayBars=root.querySelector('[data-overlay-bars]');
  const splitMarkers=root.querySelector('[data-split-markers]');
  const currentTimeEl=root.querySelector('[data-current-time]');
  const totalTimeEl=root.querySelector('[data-total-time]');
  const trimStart=root.querySelector('[data-trim-start]');
  const trimEnd=root.querySelector('[data-trim-end]');
  const segmentList=root.querySelector('[data-segment-list]');
  const overlayList=root.querySelector('[data-overlay-list]');
  const fields={text:root.querySelector('[data-overlay-text]'),start:root.querySelector('[data-overlay-start]'),end:root.querySelector('[data-overlay-end]'),x:root.querySelector('[data-overlay-x]'),y:root.querySelector('[data-overlay-y]'),size:root.querySelector('[data-overlay-size]'),color:root.querySelector('[data-overlay-color]'),background:root.querySelector('[data-overlay-background]'),align:root.querySelector('[data-overlay-align]'),format:root.querySelector('[data-export-format]'),burn:root.querySelector('[data-export-burn-overlays]')};
  let recording=null,manifest={version:1,trim:{start:0,end:null},segments:[],deleted_segments:[],text_overlays:[],export:{format:'webm',renderer:'browser_manifest'}};

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
  function setStatus(msg,type){if(!statusEl)return;statusEl.textContent=msg;statusEl.classList.toggle('is-error',type==='error');statusEl.classList.toggle('is-good',type==='good');}
  function fmt(sec){sec=Math.max(0,Number(sec||0));const m=Math.floor(sec/60),s=Math.floor(sec%60);return m+':'+String(s).padStart(2,'0');}
  function pct(time){const d=video&&video.duration?video.duration:(recording&&recording.duration_seconds)||1;return Math.max(0,Math.min(100,(Number(time||0)/d)*100));}
  async function api(url,options){const res=await fetch(url,options||{});const data=await res.json().catch(()=>({ok:false,message:'Invalid server response.'}));if(!res.ok||!data.ok)throw new Error(data.message||'Request failed.');return data.data||{};}
  async function load(){
    if(!id){setStatus('Recording id is missing.','error');return;}
    try{
      const data=await api('/api/admin/screen-recordings/read.php?id='+encodeURIComponent(id));
      recording=data.recording;manifest=Object.assign(manifest,recording.edit_manifest||{});
      manifest.trim=Object.assign({start:0,end:null},manifest.trim||{});
      manifest.segments=Array.isArray(manifest.segments)?manifest.segments:[];
      manifest.deleted_segments=Array.isArray(manifest.deleted_segments)?manifest.deleted_segments:[];
      manifest.text_overlays=Array.isArray(manifest.text_overlays)?manifest.text_overlays:[];
      if(titleEl)titleEl.textContent=recording.title||'Screen recording editor';
      if(video)video.src='/api/admin/screen-recordings/download.php?id='+encodeURIComponent(id)+'&type=original&stream=1';
      hydrateFields();renderAll();setStatus('Recording loaded.','good');
    }catch(e){setStatus(e.message||'Unable to load recording.','error');}
  }
  function hydrateFields(){if(trimStart)trimStart.value=manifest.trim.start||0;if(trimEnd)trimEnd.value=manifest.trim.end||'';if(fields.format)fields.format.value=(manifest.export&&manifest.export.format)||'webm';}
  function syncManifest(){manifest.trim={start:Number(trimStart&&trimStart.value||0),end:trimEnd&&trimEnd.value!==''?Number(trimEnd.value):null};manifest.export=Object.assign({},manifest.export||{},{format:fields.format?fields.format.value:'webm',burn_overlays:fields.burn?fields.burn.checked:true});}
  function renderAll(){renderTimeline();renderOverlays();renderLists();}
  function renderTimeline(){
    const duration=(video&&video.duration)||recording&&recording.duration_seconds||0;
    if(currentTimeEl)currentTimeEl.textContent=fmt(video?video.currentTime:0);
    if(totalTimeEl)totalTimeEl.textContent=fmt(duration);
    if(playhead)playhead.style.left=pct(video?video.currentTime:0)+'%';
    if(range){const start=pct(manifest.trim.start||0),end=manifest.trim.end!=null?pct(manifest.trim.end):100;range.style.left=start+'%';range.style.right=(100-end)+'%';}
    if(overlayBars)overlayBars.innerHTML=(manifest.text_overlays||[]).map(o=>`<span class="mg-overlay-bar" style="left:${pct(o.start)}%;width:${Math.max(1,pct(o.end)-pct(o.start))}%" title="${esc(o.text)}"></span>`).join('');
    if(splitMarkers)splitMarkers.innerHTML=(manifest.segments||[]).map(s=>`<span class="mg-split-marker" style="left:${pct(s.time)}%"></span>`).join('');
  }
  function renderOverlays(){
    if(!layer||!video)return;
    const t=video.currentTime||0;
    layer.innerHTML=(manifest.text_overlays||[]).filter(o=>t>=Number(o.start||0)&&t<=Number(o.end||0)).map(o=>`<div class="mg-recording-live-overlay" data-overlay-id="${esc(o.id)}" style="left:${Number(o.x||50)}%;top:${Number(o.y||50)}%;font-size:${Number(o.fontSize||28)}px;color:${esc(o.color||'#fff')};background:${esc(o.background||'rgba(17,24,39,.72)')};text-align:${esc(o.align||'center')};font-weight:${esc(o.fontWeight||'700')}">${esc(o.text)}</div>`).join('');
  }
  function renderLists(){
    if(segmentList)segmentList.innerHTML=(manifest.segments||[]).length?(manifest.segments||[]).map(s=>`<div class="mg-editor-mini-item"><strong>Split at ${fmt(s.time)}</strong><span>${esc(s.id)}</span><button class="mg-btn mg-btn-ghost" type="button" data-remove-split="${esc(s.id)}">Remove</button></div>`).join(''):'<div class="mg-editor-mini-item"><span>No split markers yet.</span></div>';
    if(overlayList)overlayList.innerHTML=(manifest.text_overlays||[]).length?(manifest.text_overlays||[]).map(o=>`<div class="mg-editor-mini-item"><strong>${esc(o.text).slice(0,80)}</strong><span>${fmt(o.start)} – ${fmt(o.end)} · x ${Number(o.x||50).toFixed(1)}%, y ${Number(o.y||50).toFixed(1)}%</span><button class="mg-btn mg-btn-ghost" type="button" data-edit-overlay="${esc(o.id)}">Edit</button><button class="mg-btn mg-btn-ghost" type="button" data-remove-overlay="${esc(o.id)}">Remove</button></div>`).join(''):'<div class="mg-editor-mini-item"><span>No text overlays yet.</span></div>';
  }
  function addOverlay(){
    const text=(fields.text&&fields.text.value.trim())||'';
    if(!text){setStatus('Overlay text is required.','error');return;}
    const start=Math.max(0,Number(fields.start&&fields.start.value||0));
    const end=Math.max(start+.1,Number(fields.end&&fields.end.value||start+5));
    const overlay={id:'overlay-'+Date.now().toString(36),text:text,start:start,end:end,x:Math.max(0,Math.min(100,Number(fields.x&&fields.x.value||50))),y:Math.max(0,Math.min(100,Number(fields.y&&fields.y.value||50))),fontSize:Math.max(10,Math.min(120,Number(fields.size&&fields.size.value||28))),color:fields.color?fields.color.value:'#ffffff',background:fields.background?fields.background.value:'rgba(17,24,39,.72)',align:fields.align?fields.align.value:'center',fontWeight:'700'};
    manifest.text_overlays.push(overlay);renderAll();setStatus('Text overlay added. Drag it in the preview while it is visible.','good');
  }
  function editOverlay(id){const o=manifest.text_overlays.find(x=>x.id===id);if(!o)return;if(fields.text)fields.text.value=o.text||'';if(fields.start)fields.start.value=o.start||0;if(fields.end)fields.end.value=o.end||5;if(fields.x)fields.x.value=o.x||50;if(fields.y)fields.y.value=o.y||50;if(fields.size)fields.size.value=o.fontSize||28;if(fields.color)fields.color.value=o.color||'#ffffff';if(fields.background&&/^#[0-9a-f]{6}$/i.test(o.background||''))fields.background.value=o.background;if(fields.align)fields.align.value=o.align||'center';removeOverlay(id,false);}
  function removeOverlay(id,rerender){manifest.text_overlays=manifest.text_overlays.filter(o=>o.id!==id);if(rerender!==false)renderAll();}
  function addSplit(){const time=video?video.currentTime:0;manifest.segments.push({id:'split-'+Date.now().toString(36),time:time});manifest.segments.sort((a,b)=>a.time-b.time);renderAll();}
  function setTab(button){const tab=button.getAttribute('data-tool-tab');root.querySelectorAll('[data-tool-tab]').forEach(b=>b.classList.toggle('is-active',b===button));root.querySelectorAll('[data-tool-panel]').forEach(p=>{const active=p.getAttribute('data-tool-panel')===tab;p.classList.toggle('is-active',active);p.hidden=!active;});}
  async function saveDraft(){if(!canManage)return;syncManifest();try{const data=await api('/api/admin/screen-recordings/save-edit.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({csrf_token:csrf,recording_id:id,edit_manifest:manifest})});recording=data.recording;setStatus('Edit draft saved.','good');}catch(e){setStatus(e.message||'Unable to save edit draft.','error');}}
  async function exportEdit(){if(!canManage)return;syncManifest();try{const data=await api('/api/admin/screen-recordings/export-edit.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({csrf_token:csrf,recording_id:id,edit_manifest:manifest,format:manifest.export.format,burn_overlays:manifest.export.burn_overlays})});recording=data.recording;setStatus(data.message_detail||'Export queued.','good');}catch(e){setStatus(e.message||'Unable to export edit.','error');}}
  function startDrag(e){const target=e.target.closest('[data-overlay-id]');if(!target||!stage)return;const overlay=manifest.text_overlays.find(o=>o.id===target.getAttribute('data-overlay-id'));if(!overlay)return;e.preventDefault();target.classList.add('is-dragging');function move(ev){const rect=stage.getBoundingClientRect();overlay.x=Math.max(0,Math.min(100,((ev.clientX-rect.left)/rect.width)*100));overlay.y=Math.max(0,Math.min(100,((ev.clientY-rect.top)/rect.height)*100));target.style.left=overlay.x+'%';target.style.top=overlay.y+'%';if(fields.x)fields.x.value=overlay.x.toFixed(1);if(fields.y)fields.y.value=overlay.y.toFixed(1);renderTimeline();}function up(){target.classList.remove('is-dragging');document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',up);renderLists();}document.addEventListener('mousemove',move);document.addEventListener('mouseup',up);}
  root.addEventListener('click',e=>{const tab=e.target.closest('[data-tool-tab]');if(tab)setTab(tab);if(e.target.closest('[data-overlay-add]'))addOverlay();if(e.target.closest('[data-overlay-use-playhead]')){const t=video?video.currentTime:0;if(fields.start)fields.start.value=t.toFixed(1);if(fields.end)fields.end.value=(t+5).toFixed(1);}if(e.target.closest('[data-set-trim-start]')&&trimStart)trimStart.value=(video?video.currentTime:0).toFixed(1);if(e.target.closest('[data-set-trim-end]')&&trimEnd)trimEnd.value=(video?video.currentTime:0).toFixed(1);if(e.target.closest('[data-split-at-playhead]'))addSplit();const ro=e.target.closest('[data-remove-overlay]');if(ro)removeOverlay(ro.getAttribute('data-remove-overlay'));const eo=e.target.closest('[data-edit-overlay]');if(eo)editOverlay(eo.getAttribute('data-edit-overlay'));const rs=e.target.closest('[data-remove-split]');if(rs){manifest.segments=manifest.segments.filter(s=>s.id!==rs.getAttribute('data-remove-split'));renderAll();}if(e.target.closest('[data-editor-save-draft]'))saveDraft();if(e.target.closest('[data-editor-export]'))exportEdit();if(e.target.closest('[data-editor-download-original]')&&recording)window.location.href=recording.download_original_url;});
  if(video){video.addEventListener('timeupdate',renderAll);video.addEventListener('loadedmetadata',()=>{if(!trimEnd.value&&video.duration)trimEnd.placeholder=fmt(video.duration);renderAll();});}
  if(track)track.addEventListener('click',e=>{if(!video||!video.duration)return;const rect=track.getBoundingClientRect();video.currentTime=Math.max(0,Math.min(video.duration,((e.clientX-rect.left)/rect.width)*video.duration));});
  if(layer)layer.addEventListener('mousedown',startDrag);
  [trimStart,trimEnd,fields.format,fields.burn].forEach(el=>{if(el)el.addEventListener('change',()=>{syncManifest();renderAll();});});
  load();
})();

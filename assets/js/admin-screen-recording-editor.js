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
  const audioList=root.querySelector('[data-audio-list]');
  const jobList=root.querySelector('[data-export-job-list]');
  const jobStatus=root.querySelector('[data-export-job-status]');
  const tutorialStatus=root.querySelector('[data-tutorial-status-box]');
  const fields={
    text:root.querySelector('[data-overlay-text]'),start:root.querySelector('[data-overlay-start]'),end:root.querySelector('[data-overlay-end]'),x:root.querySelector('[data-overlay-x]'),y:root.querySelector('[data-overlay-y]'),size:root.querySelector('[data-overlay-size]'),color:root.querySelector('[data-overlay-color]'),background:root.querySelector('[data-overlay-background]'),align:root.querySelector('[data-overlay-align]'),
    format:root.querySelector('[data-export-format]'),burn:root.querySelector('[data-export-burn-overlays]'),includeAudio:root.querySelector('[data-include-audio]'),muteOriginal:root.querySelector('[data-mute-original-audio]'),originalVolume:root.querySelector('[data-original-volume]'),voiceoverVolume:root.querySelector('[data-voiceover-volume]'),audioStart:root.querySelector('[data-audio-start]'),audioFile:root.querySelector('[data-audio-file]'),
    tutorialTitle:root.querySelector('[data-tutorial-title]'),tutorialSlug:root.querySelector('[data-tutorial-slug]'),tutorialSummary:root.querySelector('[data-tutorial-summary]'),tutorialCategory:root.querySelector('[data-tutorial-category]'),tutorialDifficulty:root.querySelector('[data-tutorial-difficulty]'),tutorialStatus:root.querySelector('[data-tutorial-status]'),tutorialFeatured:root.querySelector('[data-tutorial-featured]')
  };
  let recording=null,audioTracks=[],exportJobs=[],latestTutorial=null,pollTimer=null,mediaRecorder=null,voiceChunks=[];
  let manifest={version:1,trim:{start:0,end:null},segments:[],deleted_segments:[],text_overlays:[],export:{format:'webm',renderer:'ffmpeg',burn_overlays:true,include_audio:true,mute_original_audio:false,original_audio_volume:1,voiceover_volume:1}};

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
  function setStatus(msg,type){if(!statusEl)return;statusEl.textContent=msg;statusEl.classList.toggle('is-error',type==='error');statusEl.classList.toggle('is-good',type==='good');}
  function fmt(sec){sec=Math.max(0,Number(sec||0));const h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),s=Math.floor(sec%60);return h?`${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`:`${m}:${String(s).padStart(2,'0')}`;}
  function pct(time){const d=video&&video.duration?video.duration:(recording&&recording.duration_seconds)||1;return Math.max(0,Math.min(100,(Number(time||0)/d)*100));}
  async function api(url,options){const res=await fetch(url,options||{});const data=await res.json().catch(()=>({ok:false,message:'Invalid server response.'}));if(!res.ok||!data.ok)throw new Error(data.message||'Request failed.');return data.data||{};}
  function updateCollections(data){if(Array.isArray(data.audio_tracks))audioTracks=data.audio_tracks;if(Array.isArray(data.export_jobs))exportJobs=data.export_jobs;if(data.latest_tutorial!==undefined)latestTutorial=data.latest_tutorial;if(data.recording)recording=data.recording;renderCollections();}

  async function load(){
    if(!id){setStatus('Recording id is missing.','error');return;}
    try{
      const data=await api('/api/admin/screen-recordings/read.php?id='+encodeURIComponent(id));
      recording=data.recording;manifest=Object.assign(manifest,recording.edit_manifest||{});
      manifest.trim=Object.assign({start:0,end:null},manifest.trim||{});
      manifest.segments=Array.isArray(manifest.segments)?manifest.segments:[];
      manifest.deleted_segments=Array.isArray(manifest.deleted_segments)?manifest.deleted_segments:[];
      manifest.text_overlays=Array.isArray(manifest.text_overlays)?manifest.text_overlays:[];
      manifest.export=Object.assign({format:'webm',renderer:'ffmpeg',burn_overlays:true,include_audio:true,mute_original_audio:false,original_audio_volume:1,voiceover_volume:1},manifest.export||{});
      updateCollections(data);
      if(titleEl)titleEl.textContent=recording.title||'Screen recording editor';
      if(video)video.src='/api/admin/screen-recordings/download.php?id='+encodeURIComponent(id)+'&type=original&stream=1';
      hydrateFields();renderAll();setStatus('Recording loaded.','good');
      const activeJob=exportJobs.find(j=>['queued','processing'].includes(j.status));
      if(activeJob)startPolling(activeJob.id);
    }catch(e){setStatus(e.message||'Unable to load recording.','error');}
  }

  function hydrateFields(){
    if(trimStart)trimStart.value=manifest.trim.start||0;
    if(trimEnd)trimEnd.value=manifest.trim.end||'';
    if(fields.format)fields.format.value=(manifest.export&&manifest.export.format)||'webm';
    if(fields.burn)fields.burn.checked=manifest.export.burn_overlays!==false;
    if(fields.includeAudio)fields.includeAudio.value=manifest.export.include_audio===false?'0':'1';
    if(fields.muteOriginal)fields.muteOriginal.checked=!!manifest.export.mute_original_audio;
    if(fields.originalVolume)fields.originalVolume.value=manifest.export.original_audio_volume||1;
    if(fields.voiceoverVolume)fields.voiceoverVolume.value=manifest.export.voiceover_volume||1;
    if(fields.tutorialTitle&&!fields.tutorialTitle.value)fields.tutorialTitle.value=(latestTutorial&&latestTutorial.title)||recording&&recording.title||'';
    if(fields.tutorialSlug&&latestTutorial)fields.tutorialSlug.value=latestTutorial.slug||'';
    if(fields.tutorialSummary&&latestTutorial)fields.tutorialSummary.value=latestTutorial.summary||'';
    if(fields.tutorialCategory&&latestTutorial)fields.tutorialCategory.value=latestTutorial.category||'';
    if(fields.tutorialDifficulty&&latestTutorial)fields.tutorialDifficulty.value=latestTutorial.difficulty||'beginner';
    if(fields.tutorialStatus&&latestTutorial)fields.tutorialStatus.value=latestTutorial.status||'draft';
    if(fields.tutorialFeatured&&latestTutorial)fields.tutorialFeatured.value=latestTutorial.featured?'1':'0';
  }
  function syncManifest(){
    manifest.trim={start:Number(trimStart&&trimStart.value||0),end:trimEnd&&trimEnd.value!==''?Number(trimEnd.value):null};
    manifest.export=Object.assign({},manifest.export||{},{format:fields.format?fields.format.value:'webm',renderer:'ffmpeg',burn_overlays:fields.burn?fields.burn.checked:true,include_audio:fields.includeAudio?fields.includeAudio.value==='1':true,mute_original_audio:fields.muteOriginal?fields.muteOriginal.checked:false,original_audio_volume:Number(fields.originalVolume&&fields.originalVolume.value||1),voiceover_volume:Number(fields.voiceoverVolume&&fields.voiceoverVolume.value||1)});
  }
  function renderAll(){renderTimeline();renderOverlays();renderLists();renderCollections();}
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
  function renderCollections(){
    if(audioList)audioList.innerHTML=audioTracks.length?audioTracks.map(a=>`<div class="mg-editor-mini-item"><strong>${esc(a.title||'Voiceover')}</strong><span>${esc(a.original_filename||a.mime_type||'audio')} · ${fmt(a.duration_seconds||0)} · ${esc(a.status)}</span></div>`).join(''):'<div class="mg-editor-mini-item"><span>No voiceover audio yet.</span></div>';
    if(jobList)jobList.innerHTML=exportJobs.length?exportJobs.map(j=>`<div class="mg-editor-mini-item"><strong>${esc(j.requested_format).toUpperCase()} · ${esc(j.status)}</strong><span>${esc(j.renderer)} · ${esc(j.created_at)}${j.error_message?' · '+esc(j.error_message):''}</span>${j.status==='exported'&&recording&&recording.download_edited_url?`<a class="mg-btn mg-btn-soft" href="${esc(recording.download_edited_url)}">Download edited</a>`:''}</div>`).join(''):'<div class="mg-editor-mini-item"><span>No export jobs yet.</span></div>';
    const latest=exportJobs[0];
    if(jobStatus)jobStatus.textContent=latest?`Latest export: ${latest.status}${latest.error_message?' — '+latest.error_message:''}`:'No export job yet.';
    if(tutorialStatus){
      if(latestTutorial){tutorialStatus.innerHTML=`Tutorial: <strong>${esc(latestTutorial.status)}</strong> · <a href="${esc(latestTutorial.public_url)}" target="_blank" rel="noopener">Open public tutorial</a>`;}
      else tutorialStatus.textContent='No tutorial saved yet.';
    }
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
  async function exportEdit(){
    if(!canManage)return;syncManifest();setStatus('Rendering export. This can take a moment…','good');
    try{
      const data=await api('/api/admin/screen-recordings/export-edit.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({csrf_token:csrf,recording_id:id,edit_manifest:manifest,format:manifest.export.format,burn_overlays:manifest.export.burn_overlays,include_audio:manifest.export.include_audio,mute_original_audio:manifest.export.mute_original_audio,original_audio_volume:manifest.export.original_audio_volume,voiceover_volume:manifest.export.voiceover_volume,process_now:true})});
      updateCollections(data);setStatus(data.message_detail||'Export job updated.','good');if(data.export_job&&['queued','processing'].includes(data.export_job.status))startPolling(data.export_job.id);
    }catch(e){setStatus(e.message||'Unable to render export.','error');}
  }
  async function processLatestJob(){const job=exportJobs[0];if(!job){setStatus('No export job to process.','error');return;}try{setStatus('Processing latest export job…','good');const data=await api('/api/admin/screen-recordings/process-export-job.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({csrf_token:csrf,job_id:job.id})});updateCollections(data);setStatus(data.export_job&&data.export_job.status==='exported'?'Export rendered.':'Export job processed.','good');}catch(e){setStatus(e.message||'Unable to process export job.','error');}}
  async function pollJob(jobId){try{const data=await api('/api/admin/screen-recordings/export-status.php?job_id='+encodeURIComponent(jobId));updateCollections(data);const job=data.export_job;if(!job||!['queued','processing'].includes(job.status)){stopPolling();if(job&&job.status==='exported')setStatus('Rendered export is ready.','good');if(job&&job.status==='failed')setStatus(job.error_message||'Export failed.','error');}}catch(e){stopPolling();}}
  function startPolling(jobId){stopPolling();pollTimer=setInterval(()=>pollJob(jobId),3000);pollJob(jobId);}
  function stopPolling(){if(pollTimer){clearInterval(pollTimer);pollTimer=null;}}
  async function uploadAudioBlob(blob,name,type){const fd=new FormData();fd.append('csrf_token',csrf);fd.append('recording_id',String(id));fd.append('title',type==='uploaded_audio'?'Uploaded audio':'Voiceover');fd.append('track_type',type||'voiceover');fd.append('start_seconds',fields.audioStart?fields.audioStart.value||'0':'0');fd.append('volume',fields.voiceoverVolume?fields.voiceoverVolume.value||'1':'1');fd.append('audio_file',blob,name||'voiceover.webm');const res=await fetch('/api/admin/screen-recordings/audio-upload.php',{method:'POST',headers:{'X-CSRF-Token':csrf},body:fd});const data=await res.json().catch(()=>({ok:false,message:'Invalid server response.'}));if(!res.ok||!data.ok)throw new Error(data.message||'Unable to upload audio.');updateCollections(data.data||{});}
  async function startVoiceover(){if(!canManage||!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){setStatus('Browser microphone recording is unavailable.','error');return;}try{const stream=await navigator.mediaDevices.getUserMedia({audio:true});voiceChunks=[];mediaRecorder=new MediaRecorder(stream,{mimeType:MediaRecorder.isTypeSupported('audio/webm')?'audio/webm':''});mediaRecorder.addEventListener('dataavailable',e=>{if(e.data&&e.data.size)voiceChunks.push(e.data);});mediaRecorder.addEventListener('stop',async()=>{stream.getTracks().forEach(t=>t.stop());try{await uploadAudioBlob(new Blob(voiceChunks,{type:'audio/webm'}),'voiceover.webm','voiceover');setStatus('Voiceover uploaded.','good');}catch(e){setStatus(e.message||'Unable to upload voiceover.','error');}root.querySelector('[data-voiceover-start]').disabled=false;root.querySelector('[data-voiceover-stop]').disabled=true;});mediaRecorder.start();root.querySelector('[data-voiceover-start]').disabled=true;root.querySelector('[data-voiceover-stop]').disabled=false;setStatus('Recording voiceover…','good');}catch(e){setStatus(e.message||'Unable to start microphone.','error');}}
  function stopVoiceover(){if(mediaRecorder&&mediaRecorder.state!=='inactive')mediaRecorder.stop();}
  async function handleAudioFile(){const file=fields.audioFile&&fields.audioFile.files&&fields.audioFile.files[0];if(!file)return;try{await uploadAudioBlob(file,file.name,'uploaded_audio');fields.audioFile.value='';setStatus('Audio file uploaded.','good');}catch(e){setStatus(e.message||'Unable to upload audio file.','error');}}
  async function publishTutorial(){if(!canManage)return;try{const payload={csrf_token:csrf,recording_id:id,title:fields.tutorialTitle&&fields.tutorialTitle.value||recording&&recording.title||'',slug:fields.tutorialSlug&&fields.tutorialSlug.value||'',summary:fields.tutorialSummary&&fields.tutorialSummary.value||'',category:fields.tutorialCategory&&fields.tutorialCategory.value||'',difficulty:fields.tutorialDifficulty&&fields.tutorialDifficulty.value||'beginner',status:fields.tutorialStatus&&fields.tutorialStatus.value||'draft',featured:fields.tutorialFeatured&&fields.tutorialFeatured.value==='1'};const data=await api('/api/admin/screen-recordings/publish-tutorial.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(payload)});latestTutorial=data.tutorial;hydrateFields();renderCollections();setStatus('Tutorial saved.','good');}catch(e){setStatus(e.message||'Unable to save tutorial.','error');}}
  function startDrag(e){const target=e.target.closest('[data-overlay-id]');if(!target||!stage)return;const overlay=manifest.text_overlays.find(o=>o.id===target.getAttribute('data-overlay-id'));if(!overlay)return;e.preventDefault();target.classList.add('is-dragging');function move(ev){const rect=stage.getBoundingClientRect();overlay.x=Math.max(0,Math.min(100,((ev.clientX-rect.left)/rect.width)*100));overlay.y=Math.max(0,Math.min(100,((ev.clientY-rect.top)/rect.height)*100));target.style.left=overlay.x+'%';target.style.top=overlay.y+'%';if(fields.x)fields.x.value=overlay.x.toFixed(1);if(fields.y)fields.y.value=overlay.y.toFixed(1);renderTimeline();}function up(){target.classList.remove('is-dragging');document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',up);renderLists();}document.addEventListener('mousemove',move);document.addEventListener('mouseup',up);}
  root.addEventListener('click',e=>{const tab=e.target.closest('[data-tool-tab]');if(tab)setTab(tab);if(e.target.closest('[data-overlay-add]'))addOverlay();if(e.target.closest('[data-overlay-use-playhead]')){const t=video?video.currentTime:0;if(fields.start)fields.start.value=t.toFixed(1);if(fields.end)fields.end.value=(t+5).toFixed(1);}if(e.target.closest('[data-set-trim-start]')&&trimStart)trimStart.value=(video?video.currentTime:0).toFixed(1);if(e.target.closest('[data-set-trim-end]')&&trimEnd)trimEnd.value=(video?video.currentTime:0).toFixed(1);if(e.target.closest('[data-split-at-playhead]'))addSplit();const ro=e.target.closest('[data-remove-overlay]');if(ro)removeOverlay(ro.getAttribute('data-remove-overlay'));const eo=e.target.closest('[data-edit-overlay]');if(eo)editOverlay(eo.getAttribute('data-edit-overlay'));const rs=e.target.closest('[data-remove-split]');if(rs){manifest.segments=manifest.segments.filter(s=>s.id!==rs.getAttribute('data-remove-split'));renderAll();}if(e.target.closest('[data-editor-save-draft]'))saveDraft();if(e.target.closest('[data-editor-export]')||e.target.closest('[data-editor-export-panel]'))exportEdit();if(e.target.closest('[data-process-export]'))processLatestJob();if(e.target.closest('[data-voiceover-start]'))startVoiceover();if(e.target.closest('[data-voiceover-stop]'))stopVoiceover();if(e.target.closest('[data-publish-tutorial]'))publishTutorial();if(e.target.closest('[data-editor-download-original]')&&recording)window.location.href=recording.download_original_url;});
  if(video){video.addEventListener('timeupdate',renderAll);video.addEventListener('loadedmetadata',()=>{if(trimEnd&&!trimEnd.value&&video.duration)trimEnd.placeholder=fmt(video.duration);renderAll();});}
  if(track)track.addEventListener('click',e=>{if(!video||!video.duration)return;const rect=track.getBoundingClientRect();video.currentTime=Math.max(0,Math.min(video.duration,((e.clientX-rect.left)/rect.width)*video.duration));});
  if(layer)layer.addEventListener('mousedown',startDrag);
  if(fields.audioFile)fields.audioFile.addEventListener('change',handleAudioFile);
  [trimStart,trimEnd,fields.format,fields.burn,fields.includeAudio,fields.muteOriginal,fields.originalVolume,fields.voiceoverVolume].forEach(el=>{if(el)el.addEventListener('change',()=>{syncManifest();renderAll();});});
  window.addEventListener('beforeunload',stopPolling);
  load();
})();

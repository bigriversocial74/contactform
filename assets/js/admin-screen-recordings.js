(function(){
  'use strict';
  const root=document.querySelector('[data-screen-recordings]');
  if(!root)return;
  const grid=root.querySelector('[data-recordings-grid]');
  const loading=root.querySelector('[data-recordings-loading]');
  const errorBox=root.querySelector('[data-recordings-error]');
  const empty=root.querySelector('[data-recordings-empty]');
  const summary=root.querySelector('[data-recordings-summary]');
  const updated=root.querySelector('[data-recordings-updated]');
  const refreshBtn=root.querySelector('[data-recordings-refresh]');
  const searchInput=root.querySelector('[data-recordings-search]');
  const statusInput=root.querySelector('[data-recordings-status]');
  const applyBtn=root.querySelector('[data-recordings-apply]');
  const resetBtn=root.querySelector('[data-recordings-reset]');
  const diagnosticsPanel=root.querySelector('[data-recordings-diagnostics]');
  const diagnosticsBtn=root.querySelector('[data-diagnostics-run]');
  const diagnosticsStatus=root.querySelector('[data-diagnostics-status]');
  const diagnosticsGrid=root.querySelector('[data-diagnostics-grid]');
  const diagnosticsWarnings=root.querySelector('[data-diagnostics-warnings]');
  const diagnosticsUpdated=root.querySelector('[data-diagnostics-updated]');
  const csrf=root.getAttribute('data-csrf-token')||'';
  const canManage=root.getAttribute('data-can-manage')==='1';

  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
  function fmtDate(value){if(!value)return '—';const d=new Date(String(value).replace(' ','T'));return Number.isNaN(d.getTime())?value:d.toLocaleString();}
  function fmtBytes(bytes){bytes=Number(bytes||0);if(bytes<1)return '—';const units=['B','KB','MB','GB'];let i=0;while(bytes>=1024&&i<units.length-1){bytes/=1024;i++;}return bytes.toFixed(bytes>=10||i===0?0:1)+' '+units[i];}
  function fmtDuration(sec){sec=Number(sec||0);if(!sec)return '—';const m=Math.floor(sec/60),s=Math.round(sec%60);return m+':'+String(s).padStart(2,'0');}
  function yesNo(value){return value?'Yes':'No';}
  function readyClass(value){return value?' is-ready':' is-warning';}
  function setState(state,message){
    if(loading)loading.hidden=state!=='loading';
    if(errorBox){errorBox.hidden=state!=='error';errorBox.textContent=message||'';}
    if(grid)grid.hidden=state!=='ready';
    if(empty)empty.hidden=state!=='empty';
  }
  async function load(){
    setState('loading');
    if(refreshBtn)refreshBtn.disabled=true;
    const params=new URLSearchParams();
    if(searchInput&&searchInput.value.trim())params.set('q',searchInput.value.trim());
    if(statusInput&&statusInput.value)params.set('status',statusInput.value);
    try{
      const res=await fetch('/api/admin/screen-recordings/list.php?'+params.toString(),{headers:{'Accept':'application/json'}});
      const json=await res.json();
      if(!res.ok||!json.ok)throw new Error(json.message||'Unable to load recordings.');
      render(json.data.items||[]);
      if(summary)summary.textContent=(json.data.count||0)+' recording'+((json.data.count||0)===1?'':'s')+' in the admin library.';
      if(updated)updated.textContent=new Date().toLocaleTimeString();
    }catch(e){setState('error',e.message||'Unable to load recordings.');}
    if(refreshBtn)refreshBtn.disabled=false;
  }
  function render(items){
    if(!grid)return;
    if(!items.length){grid.innerHTML='';setState('empty');return;}
    grid.innerHTML=items.map(item=>{
      const statusClass=item.status==='failed'?' is-failed':(item.status==='exported'?' is-exported':'');
      const source=item.download_original_url?`<video muted preload="metadata" src="${esc(item.download_original_url)}&stream=1"></video>`:'<span>No video yet</span>';
      return `<article class="mg-recording-card" data-recording-card="${item.id}">
        <div class="mg-recording-card-preview">${source}</div>
        <span class="mg-recording-status${statusClass}">${esc(item.status||'saved')}</span>
        <h3>${esc(item.title||'Untitled recording')}</h3>
        <p>${esc(item.description||'Admin screen recording.')}</p>
        <div class="mg-recording-meta"><div><span>Duration</span><strong>${fmtDuration(item.duration_seconds)}</strong></div><div><span>Size</span><strong>${fmtBytes(item.file_size)}</strong></div><div><span>Created</span><strong>${esc(fmtDate(item.created_at))}</strong></div><div><span>Format</span><strong>${esc(item.mime_type||'video/webm')}</strong></div></div>
        <div class="mg-recording-card-actions">
          <a class="mg-btn mg-btn-primary" href="${esc(item.editor_url)}">Edit</a>
          <a class="mg-btn mg-btn-soft" href="${esc(item.download_original_url)}">Download</a>
          ${canManage?`<button class="mg-btn mg-btn-ghost" type="button" data-recording-delete="${item.id}">Archive</button>`:''}
        </div>
      </article>`;
    }).join('');
    setState('ready');
  }
  function diagnosticsCard(label,value,detail,ready){
    return `<article class="mg-diagnostics-card${readyClass(ready)}">
      <span>${esc(label)}</span>
      <strong>${esc(value)}</strong>
      <p>${esc(detail||'')}</p>
    </article>`;
  }
  function renderDiagnostics(data){
    if(!diagnosticsGrid)return;
    const php=data.php||{};
    const storage=data.storage||{};
    const ffmpeg=data.ffmpeg||{};
    const ffprobe=data.ffprobe||{};
    const functions=php.functions||{};
    const schema=data.schema||{};
    const storageBuckets=storage.buckets||{};
    const bucketSummary=Object.keys(storageBuckets).map(key=>{
      const item=storageBuckets[key]||{};
      return key+': '+(item.writable?'ready':(item.can_create?'can create':'check permissions'));
    }).join(' · ');
    diagnosticsGrid.innerHTML=[
      diagnosticsCard('Renderer',data.recommended_renderer_label||'Unknown',data.renderer_ready?'Server-side exports can run.':'Use browser fallback now or upgrade hosting for FFmpeg.',!!data.renderer_ready),
      diagnosticsCard('FFmpeg',ffmpeg.available?'Detected':'Not detected',ffmpeg.version||ffmpeg.detail||'',!!ffmpeg.available),
      diagnosticsCard('FFprobe',ffprobe.available?'Detected':'Not detected',ffprobe.version||ffprobe.detail||'',!!ffprobe.available),
      diagnosticsCard('PHP execution','shell_exec: '+yesNo(functions.shell_exec)+' · exec: '+yesNo(functions.exec),'proc_open: '+yesNo(functions.proc_open)+' · escapeshellarg: '+yesNo(functions.escapeshellarg),!!(functions.shell_exec&&functions.escapeshellarg)),
      diagnosticsCard('Upload limits',(php.upload_max_filesize_human||php.upload_max_filesize||'Unknown')+' upload','POST '+(php.post_max_size_human||php.post_max_size||'Unknown')+' · Memory '+(php.memory_limit_human||php.memory_limit||'Unknown'),!!php.file_uploads),
      diagnosticsCard('Storage',storage.ready?'Ready':'Needs review',bucketSummary||storage.base_dir||'',!!storage.ready),
      diagnosticsCard('SQL schema',schema.ready?'Ready':'Migration required',schema.migration||'database/admin_screen_recordings.sql',!!schema.ready),
      diagnosticsCard('PHP runtime','PHP '+(php.version||'unknown'),(php.sapi||'')+' · max execution '+(php.max_execution_time||0)+'s',true)
    ].join('');
    const warnings=Array.isArray(data.warnings)?data.warnings:[];
    if(diagnosticsWarnings){
      diagnosticsWarnings.hidden=!warnings.length;
      diagnosticsWarnings.innerHTML=warnings.length?'<strong>Warnings / next actions</strong><ul>'+warnings.map(w=>`<li>${esc(w)}</li>`).join('')+'</ul>':'';
    }
    if(diagnosticsStatus){
      diagnosticsStatus.textContent=data.renderer_ready?'FFmpeg rendering looks available on this server.':'FFmpeg rendering is not fully available yet. Browser fallback or server upgrade is recommended.';
      diagnosticsStatus.classList.toggle('is-ready',!!data.renderer_ready);
      diagnosticsStatus.classList.toggle('is-warning',!data.renderer_ready);
    }
    if(diagnosticsUpdated)diagnosticsUpdated.textContent=new Date().toLocaleTimeString();
  }
  async function runDiagnostics(){
    if(!diagnosticsPanel||!canManage)return;
    if(diagnosticsBtn)diagnosticsBtn.disabled=true;
    if(diagnosticsStatus){
      diagnosticsStatus.textContent='Checking server renderer, PHP limits, and storage…';
      diagnosticsStatus.classList.remove('is-ready','is-warning','is-error');
    }
    try{
      const res=await fetch('/api/admin/screen-recordings/diagnostics.php',{headers:{'Accept':'application/json'}});
      const json=await res.json();
      if(!res.ok||!json.ok)throw new Error(json.message||'Unable to run diagnostics.');
      renderDiagnostics(json.data||{});
    }catch(e){
      if(diagnosticsStatus){
        diagnosticsStatus.textContent=e.message||'Unable to run diagnostics.';
        diagnosticsStatus.classList.add('is-error');
      }
    }
    if(diagnosticsBtn)diagnosticsBtn.disabled=false;
  }
  async function archive(id){
    if(!canManage||!id)return;
    if(!confirm('Archive this recording?'))return;
    try{
      const res=await fetch('/api/admin/screen-recordings/delete.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({csrf_token:csrf,recording_id:id})});
      const json=await res.json();
      if(!res.ok||!json.ok)throw new Error(json.message||'Unable to archive recording.');
      load();
    }catch(e){setState('error',e.message||'Unable to archive recording.');}
  }
  root.addEventListener('click',event=>{const del=event.target.closest('[data-recording-delete]');if(del)archive(Number(del.getAttribute('data-recording-delete'))||0);});
  if(refreshBtn)refreshBtn.addEventListener('click',load);
  if(applyBtn)applyBtn.addEventListener('click',load);
  if(resetBtn)resetBtn.addEventListener('click',()=>{if(searchInput)searchInput.value='';if(statusInput)statusInput.value='';load();});
  if(diagnosticsBtn)diagnosticsBtn.addEventListener('click',runDiagnostics);
  window.addEventListener('mg-screen-recording-saved',load);
  load();
  if(canManage&&diagnosticsPanel)window.setTimeout(runDiagnostics,350);
})();

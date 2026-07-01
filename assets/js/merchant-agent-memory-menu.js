document.addEventListener('DOMContentLoaded',function(){
  'use strict';

  var root=document.querySelector('[data-merchant-agent-chat]');
  if(!root||!window.Microgifter)return;
  var textarea=root.querySelector('[data-agent-chat-textarea],textarea[name="message"]');
  var form=root.querySelector('[data-agent-chat-form]');
  var status=root.querySelector('[data-agent-chat-status]');
  if(!textarea||!form)return;

  var menu=null;
  var actions=null;
  var fileInput=null;
  var progressPanel=null;
  var uploadInFlight=false;
  var active=false;
  var templates={
    full:'MEMORY\nBrand voice: \nCampaign style: \nCustomer tone: \nDefault offer type: \nBusiness goals: \nLocal market notes: ',
    brand_voice:'MEMORY\nBrand voice: ',
    campaign_style:'MEMORY\nCampaign style: ',
    customer_tone:'MEMORY\nCustomer tone: ',
    default_offer_type:'MEMORY\nDefault offer type: ',
    business_goals:'MEMORY\nBusiness goals: ',
    local_market_notes:'MEMORY\nLocal market notes: '
  };

  function setStatus(message,type){
    if(!status)return;
    status.textContent=message||'';
    status.className='mg-form-status'+(type?' is-'+type:'');
  }

  function csrf(){
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')||'';
  }

  function dispatchInput(){
    textarea.dispatchEvent(new Event('input',{bubbles:true}));
  }

  function formatBytes(bytes){
    bytes=Number(bytes||0);
    if(!bytes||bytes<1)return '0 B';
    var units=['B','KB','MB','GB'];
    var index=0;
    while(bytes>=1024&&index<units.length-1){bytes/=1024;index+=1;}
    return (index===0?bytes.toFixed(0):bytes.toFixed(bytes>=10?1:2))+' '+units[index];
  }

  function isDocumentSource(source){
    var type=String(source&&source.source_type||'').toLowerCase();
    return type==='pdf'||type==='doc'||type==='docx';
  }

  function setMenuBusy(busy){
    uploadInFlight=!!busy;
    if(!menu)return;
    var uploadButton=menu.querySelector('[data-memory-upload]');
    var websiteButton=menu.querySelector('[data-memory-website]');
    if(uploadButton)uploadButton.disabled=uploadInFlight;
    if(websiteButton)websiteButton.disabled=uploadInFlight;
  }

  function ensureProgressPanel(){
    if(progressPanel)return progressPanel;
    progressPanel=document.createElement('section');
    progressPanel.className='mg-agent-memory-progress';
    progressPanel.hidden=true;
    progressPanel.innerHTML='<div class="mg-agent-memory-progress-orb" aria-hidden="true"></div><div class="mg-agent-memory-progress-body"><div class="mg-agent-memory-progress-head"><strong data-memory-progress-title>Memory source</strong><span data-memory-progress-percent>0%</span></div><div class="mg-agent-memory-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span data-memory-progress-bar></span></div><p data-memory-progress-meta></p></div>';
    form.parentNode.insertBefore(progressPanel,form);
    return progressPanel;
  }

  function showProgress(state,title,meta,percent){
    var panel=ensureProgressPanel();
    var pct=typeof percent==='number'&&isFinite(percent)?Math.max(0,Math.min(100,percent)):null;
    var bar=panel.querySelector('[data-memory-progress-bar]');
    var label=panel.querySelector('[data-memory-progress-title]');
    var percentLabel=panel.querySelector('[data-memory-progress-percent]');
    var metaLabel=panel.querySelector('[data-memory-progress-meta]');
    var track=panel.querySelector('[role="progressbar"]');
    panel.hidden=false;
    panel.dataset.state=state||'uploading';
    panel.classList.toggle('is-indeterminate',pct===null||state==='processing');
    if(label)label.textContent=title||'Memory source';
    if(metaLabel)metaLabel.textContent=meta||'';
    if(percentLabel)percentLabel.textContent=pct===null?(state==='processing'?'Processing…':'Working…'):Math.round(pct)+'%';
    if(bar)bar.style.width=(pct===null?38:pct)+'%';
    if(track){
      if(pct===null)track.removeAttribute('aria-valuenow');
      else track.setAttribute('aria-valuenow',String(Math.round(pct)));
    }
  }

  function uploadWithProgress(data,onProgress){
    return new Promise(function(resolve,reject){
      var xhr=new XMLHttpRequest();
      xhr.open('POST','/api/ai/merchant-agent-memory-sources.php',true);
      xhr.withCredentials=true;
      xhr.upload.addEventListener('progress',function(event){
        if(!event.lengthComputable){
          onProgress(null,event.loaded||0,event.total||0);
          return;
        }
        onProgress((event.loaded/event.total)*100,event.loaded,event.total);
      });
      xhr.addEventListener('load',function(){
        var json={};
        try{json=JSON.parse(xhr.responseText||'{}');}catch(error){json={};}
        if(xhr.status<200||xhr.status>=300||json.ok===false){
          reject(new Error(json.error||json.message||'Unable to upload memory source.'));
          return;
        }
        resolve(json);
      });
      xhr.addEventListener('error',function(){reject(new Error('Upload failed before the server responded.'));});
      xhr.addEventListener('abort',function(){reject(new Error('Upload was cancelled.'));});
      xhr.send(data);
    });
  }

  async function processUploadedSource(source){
    var data=new FormData();
    data.append('csrf_token',csrf());
    data.append('action','process');
    data.append('source_id',source.id||'');
    data.append('limit','1');
    showProgress('processing',source.title||source.original_filename||'Processing memory source','Extracting document text and creating memory chunks from the stored private file.',null);
    setStatus('Processing uploaded document into merchant memory…','');
    var response=await fetch('/api/ai/merchant-agent-memory-sources.php',{method:'POST',credentials:'same-origin',body:data});
    var json=await response.json().catch(function(){return {};});
    if(!response.ok||json.ok===false)throw new Error(json.error||json.message||'Unable to process memory source.');
    var processed=(json.processed&&json.processed[0])||source;
    if(processed.source_status==='failed'){
      throw new Error(processed.error_message||'Document processing failed.');
    }
    if(processed.source_status!=='ready'){
      showProgress('processing',processed.title||source.title||'Memory source queued','The upload is saved. Processing is still pending on the server.',null);
      setStatus('Memory document uploaded. Processing is still pending.','');
      return processed;
    }
    showProgress('success',processed.title||source.title||'Memory source ready',processed.summary||'Document processed and added to merchant memory.',100);
    setStatus('Memory document processed and added to merchant memory.','success');
    return processed;
  }

  function ensureFileInput(){
    if(fileInput)return fileInput;
    fileInput=document.createElement('input');
    fileInput.type='file';
    fileInput.accept='.pdf,.doc,.docx,.txt,.md,.markdown,.csv,.json,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown,text/csv,application/json';
    fileInput.hidden=true;
    document.body.appendChild(fileInput);
    fileInput.addEventListener('change',uploadSelectedFile);
    return fileInput;
  }

  async function uploadSelectedFile(){
    var file=fileInput&&fileInput.files&&fileInput.files[0];
    if(!file||uploadInFlight)return;
    var data=new FormData();
    data.append('csrf_token',csrf());
    data.append('action','upload');
    data.append('title',file.name.replace(/\.[^.]+$/,''));
    data.append('file',file);
    setMenuBusy(true);
    hideMenu();
    showProgress('uploading',file.name,'Preparing upload: '+formatBytes(file.size),0);
    setStatus('Uploading memory source…','');
    try{
      var json=await uploadWithProgress(data,function(percent,loaded,total){
        var meta=percent===null
          ? 'Uploaded '+formatBytes(loaded)+' of '+(total?formatBytes(total):formatBytes(file.size))
          : 'Uploaded '+formatBytes(loaded)+' of '+formatBytes(total||file.size);
        showProgress('uploading',file.name,meta,percent);
      });
      var source=json.source||{};
      showProgress('uploaded',source.title||file.name,'Upload complete. Server status: '+(source.source_status||'uploaded')+'.',100);
      if(isDocumentSource(source)){
        await processUploadedSource(source);
      }else{
        showProgress('success',source.title||file.name,source.summary||'Text source is ready and chunked into merchant memory.',100);
        setStatus('Memory source uploaded and added to merchant memory.','success');
      }
    }catch(error){
      showProgress('error',file.name,error&&error.message?error.message:'Unable to upload memory source.',100);
      setStatus(error&&error.message?error.message:'Unable to upload memory source.','error');
    }finally{
      setMenuBusy(false);
      fileInput.value='';
    }
  }

  async function queueWebsite(){
    var url=window.prompt('Website URL to add to merchant memory:');
    if(url===null)return;
    url=String(url||'').trim();
    if(!url){setStatus('Enter a website URL.','error');return;}
    var title=window.prompt('Memory title for this website:',url.replace(/^https?:\/\//i,'').replace(/\/.*$/,''))||'';
    var data=new FormData();
    data.append('csrf_token',csrf());
    data.append('action','website');
    data.append('url',url);
    data.append('title',title);
    setStatus('Queueing website memory scan…','');
    try{
      var response=await fetch('/api/ai/merchant-agent-memory-sources.php',{method:'POST',credentials:'same-origin',body:data});
      var json=await response.json().catch(function(){return {};});
      if(!response.ok||json.ok===false)throw new Error(json.error||json.message||'Unable to queue website memory.');
      setStatus('Website queued for memory scan.','success');
      hideMenu();
    }catch(error){
      setStatus(error&&error.message?error.message:'Unable to queue website memory.','error');
    }
  }

  function ensureMenu(){
    if(menu)return menu;
    menu=document.createElement('section');
    menu.className='mg-agent-memory-menu';
    menu.hidden=true;
    menu.innerHTML='<header><span>MEMORY</span><strong>What should the agent remember?</strong><button type="button" data-memory-close aria-label="Close memory menu">×</button></header><div class="mg-agent-memory-options"><button type="button" data-memory-template="full">Full merchant profile</button><button type="button" data-memory-template="brand_voice">Brand voice</button><button type="button" data-memory-template="campaign_style">Campaign style</button><button type="button" data-memory-template="customer_tone">Customer tone</button><button type="button" data-memory-template="default_offer_type">Default offer type</button><button type="button" data-memory-template="business_goals">Business goals</button><button type="button" data-memory-template="local_market_notes">Local market notes</button><button type="button" data-memory-upload>Upload PDF / Word</button><button type="button" data-memory-website>Scan website</button></div><p>Pick a memory type, enter the details in chat, upload documents, or queue a website scan. Save stores manual memory; Draft keeps it editable.</p>';
    form.parentNode.insertBefore(menu,form);
    menu.addEventListener('click',function(event){
      var close=event.target.closest('[data-memory-close]');
      if(close){hideMenu();return;}
      if(event.target.closest('[data-memory-upload]')){if(!uploadInFlight)ensureFileInput().click();return;}
      if(event.target.closest('[data-memory-website]')){if(!uploadInFlight)queueWebsite();return;}
      var button=event.target.closest('[data-memory-template]');
      if(!button)return;
      var key=button.dataset.memoryTemplate||'full';
      textarea.value=templates[key]||templates.full;
      textarea.focus();
      textarea.setSelectionRange(textarea.value.length,textarea.value.length);
      active=true;
      ensureActions();
      hideMenu();
      dispatchInput();
      setStatus('Enter the memory details in chat, then Save Memory or keep it as a draft.','');
    });
    return menu;
  }

  function ensureActions(){
    if(actions){actions.hidden=false;return actions;}
    actions=document.createElement('div');
    actions.className='mg-agent-memory-actions';
    actions.innerHTML='<button type="button" class="mg-btn mg-btn-primary" data-memory-save>Save Memory</button><button type="button" class="mg-btn mg-btn-soft" data-memory-draft>Keep Draft</button><button type="button" class="mg-btn mg-btn-ghost" data-memory-cancel>Cancel</button>';
    form.parentNode.insertBefore(actions,form.nextSibling);
    actions.addEventListener('click',async function(event){
      var save=event.target.closest('[data-memory-save]');
      var draft=event.target.closest('[data-memory-draft]');
      var cancel=event.target.closest('[data-memory-cancel]');
      if(cancel){active=false;actions.hidden=true;setStatus('');return;}
      if(draft){active=true;setStatus('Memory draft is ready in the chat box.','success');return;}
      if(!save)return;
      save.disabled=true;
      setStatus('Saving merchant memory…','');
      try{
        await window.Microgifter.post('/api/ai/merchant-agent-chat.php',{action:'save_memory_profile',memory_text:textarea.value,status:'saved',source:'memory_keyword'});
        active=false;
        actions.hidden=true;
        textarea.value='';
        dispatchInput();
        setStatus('Merchant memory saved.','success');
      }catch(error){
        setStatus(error&&error.message?error.message:'Unable to save merchant memory.','error');
      }finally{
        save.disabled=false;
      }
    });
    return actions;
  }

  function showMenu(){ensureMenu().hidden=false;setMenuBusy(uploadInFlight);}
  function hideMenu(){if(menu)menu.hidden=true;}

  textarea.addEventListener('input',function(){
    var value=String(textarea.value||'').trim();
    if(/^memory$/i.test(value)||/\bmemory$/i.test(value))showMenu();
    if(active||/^memory\b/i.test(value))ensureActions();
    else if(actions)actions.hidden=true;
  });

  document.addEventListener('keydown',function(event){
    if(event.key==='Escape')hideMenu();
  });
});

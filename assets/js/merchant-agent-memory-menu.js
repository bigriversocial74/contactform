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
    if(!file)return;
    var data=new FormData();
    data.append('csrf_token',csrf());
    data.append('action','upload');
    data.append('title',file.name.replace(/\.[^.]+$/,''));
    data.append('file',file);
    setStatus('Uploading memory source…','');
    try{
      var response=await fetch('/api/ai/merchant-agent-memory-sources.php',{method:'POST',credentials:'same-origin',body:data});
      var json=await response.json().catch(function(){return {};});
      if(!response.ok||json.ok===false)throw new Error(json.error||json.message||'Unable to upload memory source.');
      setStatus('Memory document uploaded. Text files are ready now; PDF and Word files are stored for extraction.', 'success');
    }catch(error){
      setStatus(error&&error.message?error.message:'Unable to upload memory source.','error');
    }finally{
      fileInput.value='';
      hideMenu();
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
      if(event.target.closest('[data-memory-upload]')){ensureFileInput().click();return;}
      if(event.target.closest('[data-memory-website]')){queueWebsite();return;}
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

  function showMenu(){ensureMenu().hidden=false;}
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

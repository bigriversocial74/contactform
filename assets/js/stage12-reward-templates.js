document.addEventListener('DOMContentLoaded',function(){
  var root=document.querySelector('[data-reward-library-manager]');
  if(!root){return;}

  var form=root.querySelector('[data-stage12-template-builder]');
  var lists=Array.prototype.slice.call(root.querySelectorAll('[data-stage12-template-list]'));
  var status=form?form.querySelector('[data-stage12-template-status]'):null;
  var tabLinks=Array.prototype.slice.call(root.querySelectorAll('[data-reward-tab-link]'));
  var tabPanels=Array.prototype.slice.call(root.querySelectorAll('[data-reward-tab-panel]'));

  var tabMap={
    'reward-overview':'overview',
    'reward-active':'active',
    'reward-drafts':'drafts',
    'reward-gift-cards':'gift_cards',
    'reward-discounts':'discounts',
    'reward-experiences':'experiences',
    'reward-archived':'archived',
    'reward-builder':'create',
    'reward-create':'create',
    overview:'overview',
    active:'active',
    drafts:'drafts',
    gift_cards:'gift_cards',
    discounts:'discounts',
    experiences:'experiences',
    archived:'archived',
    create:'create'
  };

  function normalizeTab(value){
    var key=String(value||'').replace(/^#/,'');
    return tabMap[key]||'overview';
  }

  function activateTab(tab,options){
    var next=normalizeTab(tab);
    options=options||{};
    tabPanels.forEach(function(panel){
      var active=panel.getAttribute('data-reward-tab-panel')===next;
      panel.classList.toggle('is-active',active);
      if(active){panel.removeAttribute('hidden');}else{panel.setAttribute('hidden','hidden');}
    });
    tabLinks.forEach(function(link){
      var active=normalizeTab(link.getAttribute('data-reward-tab')||link.getAttribute('href'))===next;
      link.classList.toggle('is-active',active);
      if(active){link.setAttribute('aria-current','page');}else{link.removeAttribute('aria-current');}
    });
    if(options.updateHash!==false){
      var panel=tabPanels.find(function(item){return item.getAttribute('data-reward-tab-panel')===next;});
      if(panel&&history.replaceState){history.replaceState(null,'','#'+panel.id);}
    }
    if(options.scroll){
      var activePanel=tabPanels.find(function(item){return item.getAttribute('data-reward-tab-panel')===next;});
      if(activePanel){activePanel.scrollIntoView({behavior:'smooth',block:'start'});}
    }
  }

  function setPreset(type){
    if(form&&type&&form.elements.reward_type){form.elements.reward_type.value=type;}
  }

  function resetForm(message){
    if(!form){return;}
    form.reset();
    if(form.elements.template_id){form.elements.template_id.value='';}
    if(form.elements.per_user_limit){form.elements.per_user_limit.value='1';}
    setStatus(message||'Ready to save a reward template.');
  }

  tabLinks.forEach(function(link){
    link.addEventListener('click',function(e){
      var tab=normalizeTab(link.getAttribute('data-reward-tab')||link.getAttribute('href'));
      e.preventDefault();
      if(tab==='create'){resetForm('Ready to create a new reward template.');}
      setPreset(link.getAttribute('data-reward-type-preset'));
      activateTab(tab,{scroll:true});
    });
  });

  root.querySelectorAll('[data-reward-tab-trigger]').forEach(function(trigger){
    trigger.addEventListener('click',function(e){
      var tab=normalizeTab(trigger.getAttribute('data-reward-tab-trigger')||trigger.getAttribute('href'));
      e.preventDefault();
      if(tab==='create'){resetForm('Ready to create a new reward template.');}
      setPreset(trigger.getAttribute('data-reward-type-preset'));
      activateTab(tab,{scroll:true});
    });
  });

  activateTab(normalizeTab(window.location.hash),{updateHash:false});

  if(!form||!lists.length||!window.Microgifter){return;}

  function txt(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  function n(v){return Number(v||0)||0;}
  function count(v){return n(v).toLocaleString();}
  function set(sel,val){var el=document.querySelector(sel);if(el){el.textContent=val;}}
  function setStatus(message){if(status){status.textContent=message||'';}}
  function typeLabel(value){return txt(value).replace(/_/g,' ');}

  function matchesFilter(item,filter){
    var st=String(item.status||'draft');
    var type=String(item.reward_type||'');
    if(filter==='active'){return st==='active';}
    if(filter==='drafts'){return st==='draft';}
    if(filter==='archived'){return st==='archived';}
    if(filter==='discounts'){return type==='discount'&&st!=='archived';}
    if(filter==='gift_cards'){return (type==='dollar_credit'||type==='free_item')&&st!=='archived';}
    if(filter==='experiences'){return (type==='perk_upgrade'||type==='event_reward'||type==='custom')&&st!=='archived';}
    return true;
  }

  function emptyMessage(filter){
    var labels={
      active:'No active rewards yet. Create or publish a template to make it campaign-ready.',
      drafts:'No draft rewards yet.',
      gift_cards:'No gift card or credit rewards yet.',
      discounts:'No discount rewards yet.',
      experiences:'No experience rewards yet.',
      archived:'No archived rewards yet.'
    };
    return labels[filter]||'No reward templates yet.';
  }

  function updateLibrary(items){
    var totals=items.reduce(function(acc,t){
      var st=String(t.status||'draft');
      if(st==='active')acc.active+=1;
      if(st==='draft')acc.draft+=1;
      if(st==='archived')acc.archived+=1;
      if(!String(t.redemption_instructions||'').trim())acc.missingInstructions+=1;
      if(String(t.expiration_rule||'none')==='none')acc.noExpiration+=1;
      if(!String(t.value_amount||'').trim())acc.noValue+=1;
      return acc;
    },{active:0,draft:0,archived:0,missingInstructions:0,noExpiration:0,noValue:0});
    set('[data-reward-kpi-active]',count(totals.active));
    set('[data-reward-kpi-draft]',count(totals.draft));
    set('[data-reward-kpi-issued]',count(items.length));
    set('[data-reward-kpi-claimed]','—');
    set('[data-reward-kpi-redeemed]','—');
    var score=Math.min(100,Math.round((totals.active?35:0)+(items.length?20:0)+(totals.missingInstructions<items.length?20:0)+(totals.noValue<items.length?10:0)+(totals.noExpiration<items.length?15:0)));
    set('[data-reward-readiness-score]',score?score+'/100':'—');
    set('[data-reward-ready-primary]',totals.active?count(totals.active)+' active reward'+(totals.active===1?' is':'s are')+' ready for campaigns and distribution.':'Create at least one active reward for campaign distribution.');
    set('[data-reward-ready-secondary]',totals.missingInstructions?count(totals.missingInstructions)+' reward'+(totals.missingInstructions===1?' needs':'s need')+' redemption instructions.':'Redemption instructions look complete across the current library.');
    set('[data-reward-ready-tertiary]',totals.noExpiration?count(totals.noExpiration)+' reward'+(totals.noExpiration===1?' has':'s have')+' no expiration control.':'Expiration controls are defined for the current reward library.');
  }

  function editItem(item){
    if(!item){return;}
    Object.keys(item).forEach(function(k){
      var el=form.elements[k];
      if(!el){return;}
      if(el.type==='checkbox'){el.checked=!!item[k];}else{el.value=item[k]||'';}
    });
    if(form.elements.template_id){form.elements.template_id.value=item.id||'';}
    setStatus('Editing '+(item.title||'template'));
    activateTab('create',{scroll:true});
  }

  function renderList(container,items){
    var filter=container.getAttribute('data-reward-list-filter')||'all';
    var filtered=items.filter(function(item){return matchesFilter(item,filter);});
    if(!filtered.length){
      container.innerHTML='<div class="mg-reward-empty">'+txt(emptyMessage(filter))+'</div>';
      return;
    }
    container.innerHTML=filtered.map(function(t){
      var value=t.value_amount?' · $'+txt(t.value_amount):'';
      var exp=t.expiration_rule?txt(t.expiration_rule).replace(/_/g,' '):'no expiration';
      return '<button type="button" class="mg-product-card mg-reward-card" data-id="'+txt(t.id)+'"><span><strong>'+txt(t.title)+'</strong><span>'+typeLabel(t.reward_type)+' · '+txt(t.status)+value+'</span><small>'+exp+' · '+(t.agent_discoverable?'Agent discoverable':'Manual distribution')+'</small></span><span class="mg-card-meta"><em>'+txt(t.status)+'</em></span></button>';
    }).join('');
    container.querySelectorAll('[data-id]').forEach(function(btn){
      btn.addEventListener('click',function(){
        var id=btn.getAttribute('data-id');
        editItem(items.find(function(t){return String(t.id)===String(id);}));
      });
    });
  }

  function render(items){
    updateLibrary(items);
    lists.forEach(function(container){renderList(container,items);});
  }

  async function refresh(){var r=await Microgifter.get('/api/merchant/reward-templates.php');render((r.data||r).templates||[]);}
  form.addEventListener('submit',async function(e){e.preventDefault();var data=Object.fromEntries(new FormData(form).entries());data.agent_discoverable=form.elements.agent_discoverable&&form.elements.agent_discoverable.checked?1:0;try{setStatus('Saving reward template...');var r=await Microgifter.post('/api/merchant/reward-templates.php',data);setStatus(r.message||'Reward template saved.');resetForm(r.message||'Reward template saved.');await refresh();}catch(error){setStatus(error.message||'Unable to save reward template.');}});
  var next=root.querySelector('[data-stage12-template-new]');
  if(next){next.addEventListener('click',function(){resetForm('Ready to save a reward template.');});}
  refresh().catch(function(error){setStatus(error.message||'Unable to load reward templates.');});
});

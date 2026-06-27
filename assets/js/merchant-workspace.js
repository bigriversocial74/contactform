document.addEventListener('DOMContentLoaded',function(){
'use strict';

var root=document.querySelector('[data-merchant-app]');
if(!root||!window.Microgifter)return;

var view=root.dataset.merchantView||'overview';
var overview=null;

function esc(v){
    return String(v==null?'':v).replace(/[&<>'"]/g,function(c){
        return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];
    });
}

function title(key){
    return String(key||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});
}

function setStatus(node,message,type){
    if(window.Microgifter&&typeof Microgifter.setStatus==='function'){
        Microgifter.setStatus(node,message,type);
        return;
    }
    if(node)node.textContent=message||'';
}

function setText(selector,value){
    var node=root.querySelector(selector);
    if(node)node.textContent=value;
}

function setProgress(data){
    var workspace=data.workspace||{};
    var value=Number(workspace.onboarding_percent||0);
    root.querySelectorAll('[data-merchant-progress]').forEach(function(n){n.textContent=value+'%';});
    root.querySelectorAll('[data-merchant-progress-bar]').forEach(function(n){n.style.width=value+'%';});
    var status=root.querySelector('[data-merchant-status]');
    if(status)status.textContent=workspace.status==='active'?'Workspace active':'Complete setup for beta readiness';
    var name=root.querySelector('[data-merchant-name]');
    if(name)name.textContent=workspace.display_name||'Workspace overview';
    var badge=root.querySelector('[data-merchant-eligibility]');
    if(badge)badge.textContent=title(workspace.eligibility_status||'not_started');
}

function stepsHtml(steps,limit){
    return(steps||[]).slice(0,limit||99).map(function(s){
        return'<div class="mg-step-row"><div><strong>'+esc(title(s.step_key))+'</strong><span>Step '+Number(s.step_order)+'</span></div><span class="mg-step-state '+(s.status==='completed'?'is-completed':'')+'">'+esc(title(s.status))+'</span></div>';
    }).join('')||'<div class="mg-empty-state"><p>No onboarding steps found.</p></div>';
}

async function loadOverview(){
    var response=await Microgifter.get('/api/merchant/overview.php');
    var data=response.data||response;
    overview=data;
    setProgress(data);
    var stepList=root.querySelector('[data-merchant-step-list]');
    if(stepList)stepList.innerHTML=stepsHtml(data.steps,5);
    var onboarding=root.querySelector('[data-merchant-onboarding]');
    if(onboarding)onboarding.innerHTML=stepsHtml(data.steps);
    var kpis=root.querySelector('[data-merchant-kpis]');
    if(kpis){
        var values=[
            ['Products',data.products&&data.products.total||0],
            ['Published',data.products&&data.products.published_count||0],
            ['Locations',data.locations&&data.locations.active_count||0],
            ['Team',data.team&&data.team.active_count||0],
            ['Programs',data.programs&&data.programs.active_count||0]
        ];
        kpis.innerHTML=values.map(function(v){
            return'<div class="mg-merchant-kpi"><span>'+v[0]+'</span><strong>'+Number(v[1]).toLocaleString()+'</strong></div>';
        }).join('');
    }
    var ready=root.querySelector('[data-merchant-readiness]');
    var p=data.payments||{};
    if(ready){
        ready.innerHTML=[
            ['Business profile',Number(data.workspace.onboarding_percent)>0],
            ['Primary location',Number(data.locations.primary_count)>0],
            ['Published product',Number(data.products.published_count)>0],
            ['Payment account',Boolean(p.account_connected)],
            ['Live commerce approved',Boolean(p.live_approved)]
        ].map(function(v){
            return'<div class="mg-readiness-row"><strong>'+v[0]+'</strong><span class="mg-step-state '+(v[1]?'is-completed':'')+'">'+(v[1]?'Ready':'Pending')+'</span></div>';
        }).join('');
    }
    var payment=root.querySelector('[data-payment-readiness]');
    if(payment){
        payment.innerHTML=[
            ['Provider connected',p.account_connected],
            ['Identity verified',p.identity_verified],
            ['Charges enabled',p.charges_enabled],
            ['Payouts enabled',p.payouts_enabled],
            ['Tax setup',p.tax_setup_complete],
            ['Test payment',p.test_payment_complete],
            ['Live approved',p.live_approved]
        ].map(function(v){
            return'<div class="mg-payment-item '+(v[1]?'is-ready':'')+'"><strong>'+v[0]+'</strong><span>'+(v[1]?'Ready':'Not configured')+'</span></div>';
        }).join('');
    }
}

async function loadSettings(){
    var form=root.querySelector('[data-merchant-settings-form]');
    if(!form)return;
    var response=await Microgifter.get('/api/merchant/settings.php');
    var w=(response.data||response).workspace||{};
    Object.keys(w).forEach(function(k){
        if(form.elements[k])form.elements[k].value=w[k]||'';
    });
    form.addEventListener('submit',async function(e){
        e.preventDefault();
        var payload=Object.fromEntries(new FormData(form).entries());
        var status=form.querySelector('[data-merchant-form-status]');
        try{
            setStatus(status,'Saving…');
            var r=await Microgifter.post('/api/merchant/settings.php',payload);
            setStatus(status,r.message||'Saved','success');
            await loadOverview();
        }catch(err){
            setStatus(status,err.message||'Unable to save settings.','error');
        }
    });
}

function notificationTime(value){
    var time=Date.parse(value||'');
    if(!time)return '';
    return new Date(time).toLocaleString();
}

function notificationIcon(kind){
    return({tips:'$',messages:'✉',rewards:'★',redemptions:'✓',all:'•'})[kind]||'•';
}

function notificationActionLabel(item){
    if(item.kind==='messages')return 'Open message';
    if(item.kind==='tips')return 'View tip';
    if(item.kind==='redemptions')return 'View claim';
    if(item.kind==='rewards')return 'View reward';
    return 'Open';
}

function notificationCard(item){
    var context=item.context||{};
    var meta=[item.source==='alert'?'Operational alert':'User notification',title(item.kind),notificationTime(item.created_at)].filter(Boolean).join(' · ');
    var tags=[item.severity,item.status].filter(Boolean).map(function(x){return'<em>'+esc(title(x))+'</em>';}).join('');
    var itemAttr=' data-source="'+esc(item.source)+'" data-id="'+esc(item.id)+'"';
    return'<article class="mg-merchant-notification-card '+(item.is_unread?'is-unread':'')+'"'+itemAttr+'>'+
        '<div class="mg-merchant-notification-icon">'+esc(notificationIcon(item.kind))+'</div>'+
        '<div class="mg-merchant-notification-content"><div class="mg-merchant-notification-top"><span>'+esc(meta)+'</span><span class="mg-card-meta">'+tags+'</span></div><h3>'+esc(item.title||'Merchant notification')+'</h3><p>'+esc(item.body||'No message body was provided.')+'</p>'+
        '<div class="mg-merchant-notification-context">'+[
            context.wallet_item_id?'Wallet '+context.wallet_item_id:'',
            context.campaign_id?'Campaign '+context.campaign_id:'',
            context.tip_id?'Tip '+context.tip_id:'',
            context.message_id?'Message '+context.message_id:''
        ].filter(Boolean).map(esc).join(' · ')+'</div>'+
        '<div class="mg-merchant-notification-actions"><a class="mg-btn mg-btn-secondary" href="'+esc(item.action_url||'/merchant-notifications.php')+'">'+esc(notificationActionLabel(item))+'</a>'+
        (item.is_unread?'<button class="mg-btn mg-btn-primary" type="button" data-notification-done>Acknowledge</button>':'')+'</div></div></article>';
}

async function loadNotifications(){
    var feed=root.querySelector('[data-merchant-notification-feed]');
    var tabs=root.querySelector('[data-merchant-notification-tabs]');
    if(!feed||!tabs)return;
    var status=root.querySelector('[data-merchant-notification-status]');
    var kpis=root.querySelector('[data-merchant-notification-kpis]');
    var current='all';
    var params=new URLSearchParams(window.location.search||'');
    if(params.get('filter'))current=params.get('filter');

    async function refresh(){
        setStatus(status,'Loading '+title(current)+'…');
        try{
            var response=await Microgifter.get('/api/merchant/notifications.php?filter='+encodeURIComponent(current)+'&limit=50');
            var data=response.data||response;
            var counts=data.counts||{};
            tabs.querySelectorAll('[data-filter]').forEach(function(btn){
                var f=btn.dataset.filter;
                btn.classList.toggle('is-active',f===current);
                var count=btn.querySelector('[data-count]');
                if(count)count.textContent=Number(counts[f]||0).toLocaleString();
            });
            if(kpis){
                kpis.innerHTML=[['Unread',counts.unread||0],['Tips',counts.tips||0],['Messages',counts.messages||0],['Rewards',counts.rewards||0]].map(function(v){
                    return'<div class="mg-merchant-kpi"><span>'+esc(v[0])+'</span><strong>'+Number(v[1]).toLocaleString()+'</strong></div>';
                }).join('');
            }
            var items=data.items||[];
            feed.innerHTML=items.length?items.map(notificationCard).join(''):'<div class="mg-empty-state"><strong>No merchant notifications</strong><p>This queue will show tips, voucher messages, reward movement, and redemption alerts as customers interact with wallet rewards.</p></div>';
            setStatus(status,Number(items.length).toLocaleString()+' item'+(items.length===1?'':'s'));
        }catch(err){
            feed.innerHTML='<div class="mg-empty-state"><strong>Unable to load notifications</strong><p>'+esc(err.message||'Try again in a moment.')+'</p></div>';
            setStatus(status,'Load failed','error');
        }
    }

    tabs.addEventListener('click',function(event){
        var btn=event.target.closest('[data-filter]');
        if(!btn)return;
        current=btn.dataset.filter||'all';
        refresh();
    });
    var refreshButton=root.querySelector('[data-merchant-notification-refresh]');
    if(refreshButton)refreshButton.addEventListener('click',refresh);
    feed.addEventListener('click',async function(event){
        var btn=event.target.closest('[data-notification-done]');
        if(!btn)return;
        var card=btn.closest('[data-source][data-id]');
        if(!card)return;
        try{
            btn.disabled=true;
            btn.textContent='Saving…';
            await Microgifter.post('/api/merchant/notifications.php',{source:card.dataset.source,id:card.dataset.id,action:'acknowledge'});
            await refresh();
        }catch(err){
            btn.disabled=false;
            btn.textContent='Acknowledge';
            setStatus(status,err.message||'Unable to acknowledge.','error');
        }
    });
    await refresh();
}

function resetLocationForm(form){
    form.reset();
    form.elements.location_id.value='';
    if(form.elements.timezone){
        form.elements.timezone.value=(overview&&overview.workspace&&overview.workspace.timezone)||'America/Phoenix';
    }
    if(form.elements.country_code)form.elements.country_code.value='US';
    if(form.elements.status)form.elements.status.value='active';
    if(form.elements.claim_code){
        form.elements.claim_code.value='';
        form.elements.claim_code.required=true;
        form.elements.claim_code.placeholder='PHX-001';
    }
    var help=form.querySelector('[data-location-code-help]');
    if(help)help.textContent='Required for a new location. Codes are stored securely and cannot be displayed again.';
    setStatus(form.querySelector('[data-location-status]'),'');
}

function editLocationForm(form,item){
    Object.keys(item).forEach(function(k){
        if(k==='claim_code'||k==='claim_code_last4'||k==='has_active_claim_code')return;
        if(!form.elements[k])return;
        if(form.elements[k].type==='checkbox'){
            form.elements[k].checked=Boolean(Number(item[k]));
        }else{
            form.elements[k].value=item[k]||'';
        }
    });
    form.elements.location_id.value=item.public_id||'';
    if(form.elements.claim_code){
        form.elements.claim_code.value='';
        form.elements.claim_code.required=false;
        form.elements.claim_code.placeholder=item.claim_code_last4
            ?'Enter a new code to rotate'
            :'Enter a claim code';
    }
    var help=form.querySelector('[data-location-code-help]');
    if(help){
        help.textContent=item.claim_code_last4
            ?'Current code ends in '+item.claim_code_last4+'. Leave blank to keep it, or enter a new code to rotate it.'
            :'No active claim code is set. Enter one before using this location for redemption.';
    }
    setStatus(form.querySelector('[data-location-status]'),'Editing '+(item.name||'location')+'.');
}

async function loadLocations(){
    var list=root.querySelector('[data-location-list]');
    var form=root.querySelector('[data-location-form]');
    if(!list||!form)return;

    function updateLocationMetrics(items){
        items=items||[];
        var active=items.filter(function(x){return x.status==='active';}).length;
        var archived=items.filter(function(x){return x.status==='archived';}).length;
        var primary=items.filter(function(x){return Number(x.is_primary);}).length;
        var claim=items.filter(function(x){return Boolean(x.claim_code_last4||x.has_active_claim_code);}).length;
        var staff=items.filter(function(x){return x.address_line1&&x.city&&x.phone;}).length;
        setText('[data-location-kpi-active]',active.toLocaleString());
        setText('[data-location-kpi-claim]',claim.toLocaleString());
        setText('[data-location-kpi-primary]',primary?primary.toLocaleString():'—');
        setText('[data-location-kpi-archived]',archived.toLocaleString());
        setText('[data-location-kpi-staff]',staff.toLocaleString());
        setText('[data-location-readiness-score]',items.length?Math.round(((active>0?1:0)+(claim>0?1:0)+(primary>0?1:0)+(staff>0?1:0))/4*100)+'%':'—');
        setText('[data-location-ready-primary]',active>0?active+' active claim location'+(active===1?' is':'s are')+' available.':'Add at least one active claim location.');
        setText('[data-location-ready-secondary]',claim===active&&active>0?'Active claim sites have protected claim codes.':'Each active claim site needs a protected claim code.');
        setText('[data-location-ready-tertiary]',primary>0?'Primary location is set for default routing.':'Set one primary location for storefront and staff routing.');
    }

    async function refresh(){
        var r=await Microgifter.get('/api/merchant/locations.php');
        var payload=r.data||r;
        var items=payload.locations||[];
        updateLocationMetrics(items);
        list.innerHTML=items.map(function(x){
            var address=[x.address_line1,x.city,x.region,x.postal_code].filter(Boolean).join(', ');
            var codeText=x.claim_code_last4?'ending '+x.claim_code_last4:'not set';
            return'<button type="button" class="mg-location-card" data-location="'+esc(x.public_id)+'"><span><strong>'+esc(x.name)+'</strong><span>'+esc(address||x.location_code||'No address saved')+'</span><small>Claim code: '+esc(codeText)+'</small></span><span class="mg-card-meta"><em>'+esc(x.status)+'</em>'+(Number(x.is_primary)?'<em>Primary</em>':'')+'</span></button>';
        }).join('')||'<div class="mg-empty-state"><p>No locations yet. Add the first claim location for this merchant.</p></div>';

        list.querySelectorAll('[data-location]').forEach(function(btn){
            btn.addEventListener('click',function(){
                var item=items.find(function(x){return x.public_id===btn.dataset.location;});
                if(item)editLocationForm(form,item);
            });
        });
    }

    form.addEventListener('submit',async function(e){
        e.preventDefault();
        var data=Object.fromEntries(new FormData(form).entries());
        data.is_primary=form.elements.is_primary&&form.elements.is_primary.checked?1:0;
        data.claim_code=String(data.claim_code||'').trim().toUpperCase();
        var isCreate=!String(data.location_id||'').trim();
        var status=form.querySelector('[data-location-status]');
        var submit=form.querySelector('[data-location-save]')||form.querySelector('[type="submit"]');

        if(isCreate&&!data.claim_code){
            setStatus(status,'Enter a claim code for the new location.','error');
            form.elements.claim_code.focus();
            return;
        }

        try{
            setStatus(status,'Saving location…');
            if(typeof Microgifter.setBusy==='function')Microgifter.setBusy(submit,true,'Saving…');
            var r=await Microgifter.post('/api/merchant/locations.php',data);
            var saved=r.data||r;
            var successMessage=r.message||'Location saved.';
            if(saved.claim_code_last4)successMessage+=' Claim code ends in '+saved.claim_code_last4+'.';
            resetLocationForm(form);
            setStatus(status,successMessage,'success');
            await refresh();
            loadOverview().catch(function(){});
        }catch(err){
            setStatus(status,err.message||'Unable to save location.','error');
        }finally{
            if(typeof Microgifter.setBusy==='function')Microgifter.setBusy(submit,false);
        }
    });

    var newButton=root.querySelector('[data-location-new]');
    if(newButton)newButton.addEventListener('click',function(){resetLocationForm(form);});
    var resetButton=root.querySelector('[data-location-reset]');
    if(resetButton)resetButton.addEventListener('click',function(){resetLocationForm(form);});

    resetLocationForm(form);
    await refresh();
}

async function loadTeam(){
    var list=root.querySelector('[data-team-list]');
    var form=root.querySelector('[data-team-form]');
    if(!list||!form)return;
    async function refresh(){
        var r=await Microgifter.get('/api/merchant/team.php');
        var items=(r.data||r).members||[];
        list.innerHTML=items.map(function(x){
            return'<div class="mg-team-card"><span><strong>'+esc(x.display_name||'Invited member')+'</strong><span>'+esc(title(x.role_key))+'</span></span><span class="mg-card-meta"><em>'+esc(x.status)+'</em></span></div>';
        }).join('')||'<div class="mg-empty-state"><p>No team members found.</p></div>';
    }
    form.addEventListener('submit',async function(e){
        e.preventDefault();
        var status=form.querySelector('[data-team-status]');
        try{
            setStatus(status,'Saving invitation…');
            var r=await Microgifter.post('/api/merchant/team.php',Object.fromEntries(new FormData(form).entries()));
            setStatus(status,r.message||'Invitation recorded','success');
            form.reset();
            await refresh();
        }catch(err){
            setStatus(status,err.message||'Unable to record invitation.','error');
        }
    });
    await refresh();
}

function showLoadError(err){
    var main=root.querySelector('.mg-merchant-main');
    if(main)main.insertAdjacentHTML('afterbegin','<div class="mg-empty-state">'+esc(err.message||'Unable to load merchant workspace.')+'</div>');
}

loadOverview().then(function(){
    if(view==='settings')return loadSettings();
    if(view==='locations')return loadLocations();
    if(view==='team')return loadTeam();
    if(view==='notifications')return loadNotifications();
}).catch(showLoadError);
});

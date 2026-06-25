window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var bound = false;
  function esc(value){return String(value==null?'':value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
  function readInlineAlerts(){
    var node=document.getElementById('mg-market-alerts-json');
    if(!node) return [];
    try{var data=JSON.parse(node.textContent||'[]');return Array.isArray(data)?data:[];}catch(e){return [];}
  }
  function normalizeAlerts(alerts){
    if(!Array.isArray(alerts)) return [];
    return alerts.filter(function(alert){return alert&&alert.title;}).slice(0,8);
  }
  function mountHeaderAlerts(alerts){
    alerts=normalizeAlerts(alerts);
    var signal=document.querySelector('[data-header-signal="notifications"]');
    if(!signal) return;
    signal.style.position='relative';
    var badge=signal.querySelector('[data-notification-badge]');
    var old=signal.querySelector('[data-market-header-alerts]');
    if(old) old.remove();
    if(!alerts.length){
      if(badge){badge.textContent='0';badge.hidden=true;badge.classList.remove('has-unread');}
      return;
    }
    if(badge){badge.textContent=String(alerts.length);badge.hidden=false;badge.classList.add('has-unread');}
    var html='<div class="mg-header-notification-panel" data-market-header-alerts><h4>Market alerts</h4>'+alerts.map(function(alert){
      return '<a class="mg-header-notification-item" href="'+esc(alert.href||'/account-market.php')+'"><span class="mg-header-notification-level">'+esc(alert.level||'info')+'</span><strong>'+esc(alert.title||'Market alert')+'</strong><span>'+esc(alert.body||'Open the Market Dashboard for details.')+'</span></a>';
    }).join('')+'</div>';
    signal.insertAdjacentHTML('beforeend',html);
    if(bound) return;
    bound=true;
    var trigger=signal.querySelector('[data-header-signal-trigger]');
    if(trigger){
      trigger.addEventListener('click',function(event){
        if(!signal.querySelector('[data-market-header-alerts]')) return;
        event.preventDefault();
        var open=signal.classList.toggle('is-open');
        trigger.setAttribute('aria-expanded',open?'true':'false');
      });
      document.addEventListener('click',function(event){
        if(!signal.contains(event.target)){
          signal.classList.remove('is-open');
          trigger.setAttribute('aria-expanded','false');
        }
      });
    }
  }
  async function loadApiAlerts(){
    if(document.body && document.body.getAttribute('data-authenticated')!=='true') return null;
    try{
      var MG=window.Microgifter||{};
      if(MG.get){
        var response=await MG.get('/api/me/market-alerts.php');
        var data=response.data||response;
        return normalizeAlerts(data.alerts||[]);
      }
      var raw=await fetch('/api/me/market-alerts.php',{credentials:'same-origin',headers:{'Accept':'application/json'}});
      if(!raw.ok) return null;
      var json=await raw.json();
      var payload=json.data||json;
      return normalizeAlerts(payload.alerts||[]);
    }catch(error){return null;}
  }
  document.addEventListener('DOMContentLoaded',function(){
    var inline=readInlineAlerts();
    if(inline.length) mountHeaderAlerts(inline);
    loadApiAlerts().then(function(alerts){ if(alerts) mountHeaderAlerts(alerts); });
  });
})(window, document);

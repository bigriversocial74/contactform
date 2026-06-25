window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  function esc(value){return String(value==null?'':value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
  function readAlerts(){
    var node=document.getElementById('mg-market-alerts-json');
    if(!node) return [];
    try{var data=JSON.parse(node.textContent||'[]');return Array.isArray(data)?data:[];}catch(e){return [];}
  }
  function mountHeaderAlerts(alerts){
    if(!alerts.length) return;
    var signal=document.querySelector('[data-header-signal="notifications"]');
    if(!signal) return;
    signal.style.position='relative';
    var badge=signal.querySelector('[data-notification-badge]');
    if(badge){badge.textContent=String(alerts.length);badge.hidden=false;badge.classList.add('has-unread');}
    var old=signal.querySelector('[data-market-header-alerts]');
    if(old) old.remove();
    var html='<div class="mg-header-notification-panel" data-market-header-alerts><h4>Market alerts</h4>'+alerts.map(function(alert){
      return '<a class="mg-header-notification-item" href="'+esc(alert.href||'/account-market.php')+'"><span class="mg-header-notification-level">'+esc(alert.level||'info')+'</span><strong>'+esc(alert.title||'Market alert')+'</strong><span>'+esc(alert.body||'Open the Market Dashboard for details.')+'</span></a>';
    }).join('')+'</div>';
    signal.insertAdjacentHTML('beforeend',html);
    var trigger=signal.querySelector('[data-header-signal-trigger]');
    if(trigger){
      trigger.addEventListener('click',function(event){
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
  document.addEventListener('DOMContentLoaded',function(){mountHeaderAlerts(readAlerts());});
})(window, document);

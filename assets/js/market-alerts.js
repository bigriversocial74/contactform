window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  function readInlineAlerts(){
    var node=document.getElementById('mg-market-alerts-json');
    if(!node) return [];
    try{var data=JSON.parse(node.textContent||'[]');return Array.isArray(data)?data:[];}catch(e){return [];}
  }
  function normalizeAlerts(alerts){
    if(!Array.isArray(alerts)) return [];
    return alerts.filter(function(alert){return alert&&alert.title;}).slice(0,8);
  }
  function announceAlerts(alerts){
    alerts=normalizeAlerts(alerts);
    document.dispatchEvent(new CustomEvent('mg:market-alerts:loaded',{detail:{alerts:alerts,count:alerts.length}}));
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
    if(inline.length) announceAlerts(inline);
    loadApiAlerts().then(function(alerts){ if(alerts) announceAlerts(alerts); });
  });
})(window, document);

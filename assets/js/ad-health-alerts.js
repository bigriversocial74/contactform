(function(window, document){
  'use strict';
  function esc(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function render(root, data){
    var alerts = (data && Array.isArray(data.alerts)) ? data.alerts : [];
    if (!alerts.length) { root.innerHTML = ''; root.hidden = true; return; }
    var top = alerts.find(function(a){return a.level === 'critical';}) || alerts.find(function(a){return a.level === 'warning';}) || alerts[0];
    var counts = data.summary || {};
    root.hidden = false;
    root.innerHTML = '<article class="mg-ad-health-card is-'+esc(top.level||'info')+'">'
      + '<div><span class="mg-ad-health-eyebrow">Campaign Ads health</span><h2>'+esc(top.title||'Campaign Ads alert')+'</h2><p>'+esc(top.message||'Review Campaign Ads health.')+'</p></div>'
      + '<div class="mg-ad-health-counts"><span><strong>'+esc(counts.critical||0)+'</strong> critical</span><span><strong>'+esc(counts.warning||0)+'</strong> warnings</span><span><strong>'+esc(counts.info||0)+'</strong> info</span></div>'
      + '<details><summary>View alerts</summary><ul>'+alerts.map(function(alert){return '<li class="is-'+esc(alert.level||'info')+'"><strong>'+esc(alert.title||'Alert')+'</strong><span>'+esc(alert.message||'')+'</span></li>';}).join('')+'</ul></details>'
      + '</article>';
  }
  async function load(root){
    var scope = root.getAttribute('data-health-scope') || 'merchant';
    var res = await fetch('/api/ads/health-alerts.php?scope=' + encodeURIComponent(scope), {credentials:'same-origin', headers:{Accept:'application/json'}});
    var out = await res.json().catch(function(){return {ok:false,message:'Invalid health response'};});
    if (!out.ok) throw new Error(out.message || 'Unable to load health alerts.');
    render(root, out.data || {});
  }
  document.addEventListener('DOMContentLoaded', function(){
    Array.prototype.slice.call(document.querySelectorAll('[data-ad-health-alerts]')).forEach(function(root){
      load(root).catch(function(error){
        root.hidden = false;
        root.innerHTML = '<article class="mg-ad-health-card is-warning"><div><span class="mg-ad-health-eyebrow">Campaign Ads health</span><h2>Health alerts unavailable</h2><p>'+esc(error.message || 'Unable to load Campaign Ads health alerts.')+'</p></div></article>';
      });
    });
  });
})(window, document);

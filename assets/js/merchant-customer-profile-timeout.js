document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-customer-profile-page]');
if(!root)return;
function qs(s){return root.querySelector(s)}
function setText(sel,value){var n=qs(sel);if(n)n.textContent=value}
function clearLoading(message){
  root.classList.remove('is-loading');
  setText('.mg-cp-header p',message||'Customer profile is taking longer than expected. Refresh or reopen from Merchant CRM.');
  var name=qs('[data-cp-name]');
  if(name&&/loading/i.test(name.textContent||''))name.textContent='Customer profile unavailable';
  var lists=[['[data-cp-rewards]',5],['[data-cp-followups]',4],['[data-cp-followups-full]',5],['[data-cp-redemptions]',6],['[data-cp-tips]',3],['[data-cp-sources]',4]];
  lists.forEach(function(x){var node=qs(x[0]);if(node&&/Loading/i.test(node.textContent||''))node.innerHTML='<tr><td colspan="'+x[1]+'">Unable to load this section yet.</td></tr>'});
  var snap=qs('[data-cp-snapshot]');if(snap&&/Loading/i.test(snap.textContent||''))snap.innerHTML='<li>Customer profile request timed out before data returned.</li>';
}
window.setTimeout(function(){if(root.classList.contains('is-loading'))clearLoading('Customer profile request timed out. The page is no longer locked; try refresh or open the customer again from Merchant CRM.');},12000);
document.addEventListener('mg:customer-profile:timeout-clear',function(event){clearLoading((event.detail||{}).message||'Customer profile loader was reset.');});
});

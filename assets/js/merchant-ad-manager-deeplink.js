window.Microgifter=window.Microgifter||{};
(function(window,document){
'use strict';
var params=new URLSearchParams(window.location.search||'');
var campaignId=params.get('ad_campaign_id')||params.get('campaign')||'';
if(!campaignId)return;
function cssEscape(value){return window.CSS&&window.CSS.escape?window.CSS.escape(value):String(value).replace(/[^a-zA-Z0-9_-]/g,'\\$&');}
function status(message){var node=document.querySelector('[data-ads-status]');if(node)node.textContent=message||'';}
function activateCreate(){
  var create=document.querySelector('[data-ads-tab-button="create"]');
  if(create)create.click();
}
function tryLoad(){
  var row=document.querySelector('[data-campaign-id="'+cssEscape(campaignId)+'"]');
  var edit=row&&row.querySelector('[data-edit]');
  if(!edit)return false;
  edit.click();
  activateCreate();
  status(params.get('source')==='story'?'Story campaign draft loaded. Review and save or submit for review.':'Campaign draft loaded.');
  return true;
}
var started=Date.now();
var timer=window.setInterval(function(){
  if(tryLoad()||Date.now()-started>12000){
    window.clearInterval(timer);
  }
},250);
window.setTimeout(tryLoad,80);
})(window,document);

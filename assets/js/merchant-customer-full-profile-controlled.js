document.addEventListener('DOMContentLoaded',function(){
'use strict';
var root=document.querySelector('[data-customer-profile-page]');
if(!root)return;
function qs(sel,ctx){return(ctx||document).querySelector(sel)}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(ch){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch]})}
function count(v){return Array.isArray(v)?v.length:(v&&typeof v==='object'?Object.keys(v).length:0)}
function path(){var p=new URLSearchParams(location.search||'');p.delete('tab');return '/api/merchant/customer-profile.php'+(p.toString()?('?'+p.toString()):'')}
function hasLookup(){var p=new URLSearchParams(location.search||'');return !!(p.get('campaign_contact_id')||p.get('contact_id')||p.get('crm_contact_id')||p.get('id')||p.get('email'))}
function install(){var host=qs('.mg-cp-center',root)||root,card=qs('[data-cp-full-profile-probe]',root);if(card)return card;card=document.createElement('article');card.className='mg-cp-card';card.setAttribute('data-cp-full-profile-probe','');card.innerHTML='<div class="mg-cp-card-head"><div><h3>Full Customer Profile API Probe</h3><span>Controlled Step 5 · customer-profile.php</span></div></div><div class="mg-cp-card-body" data-cp-full-profile-probe-body><p>Waiting to test the full profile API…</p></div>';host.insertBefore(card,host.firstChild);return card}
function render(data){var body=qs('[data-cp-full-profile-probe-body]',install());var payload=data&&data.data?data.data:data||{};var parts=['customer','metrics','messages','notes','wallets','rewards','redemptions','tips','timeline','campaign_sources','activity_chart'].map(function(k){return '<li><strong>'+esc(k)+'</strong>: '+esc(count(payload[k]))+'</li>'}).join('');body.innerHTML='<p><strong>Full customer-profile.php loaded successfully.</strong></p><ul>'+parts+'</ul><p class="mg-cp-muted">This step tests the heavy bundled API without restoring the original heavy script stack yet.</p>';root.classList.add('is-full-profile-api-test')}
async function load(){var card=install(),body=qs('[data-cp-full-profile-probe-body]',card);if(!hasLookup()){body.innerHTML='<p>Open this page from a customer link to test the full profile API.</p>';return}if(!window.Microgifter||typeof Microgifter.get!=='function'){body.innerHTML='<p>Microgifter API client is not available.</p>';return}try{body.innerHTML='<p>Testing full customer-profile.php…</p>';var response=await Microgifter.get(path());render(response)}catch(error){body.innerHTML='<p><strong>Full customer-profile.php failed.</strong></p><p>'+esc(error&&error.message?error.message:'Unknown error')+'</p>';root.classList.add('is-full-profile-api-failed')}}
setTimeout(load,900);
});

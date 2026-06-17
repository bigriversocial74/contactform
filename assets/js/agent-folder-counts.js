document.addEventListener('DOMContentLoaded',function(){
'use strict';
var badges=document.querySelectorAll('[data-gift-nav-count]');
if(!badges.length||!window.Microgifter||typeof window.Microgifter.get!=='function')return;
function apply(counts){['inbox','sent','claimed'].forEach(function(folder){var source=counts&&counts[folder];var total=source&&typeof source==='object'?Number(source.total||0):Number(source||0);if(folder==='inbox'&&total===0)total=1;document.querySelectorAll('[data-gift-nav-count="'+folder+'"],[data-gift-nav-unread="'+folder+'"]').forEach(function(node){node.textContent=String(total);node.hidden=false;node.classList.toggle('has-unread',total>0);});});}
apply({inbox:1,sent:0,claimed:0});
window.Microgifter.get('/api/account/action-center.php?folder=inbox&limit=1').then(function(response){var data=response&&response.data?response.data:response||{};apply(data.counts||{});}).catch(function(){apply({inbox:1,sent:0,claimed:0});});
});

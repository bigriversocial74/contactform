document.addEventListener('DOMContentLoaded',function(){
  if(!window.Microgifter){return;}
  var campaignList=document.querySelector('[data-stage12-campaign-list]');
  var contactStatus=document.querySelector('[data-stage12-contact-status]');
  if(!campaignList||!contactStatus){return;}
  var toolBox=document.createElement('div');
  toolBox.className='mg-empty-state';
  toolBox.setAttribute('data-stage12-campaign-tools','');
  toolBox.innerHTML='<p>Select a campaign to view public links.</p>';
  contactStatus.parentNode.insertBefore(toolBox,contactStatus);
  function html(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c];});}
  async function load(campaignId){
    var response=await Microgifter.get('/api/merchant/campaign-public-tools.php?campaign_id='+encodeURIComponent(campaignId));
    var tools=(response.data||response).tools;
    if(!tools){return;}
    toolBox.innerHTML='<strong>Public campaign links</strong><p><a href="'+html(tools.public_url)+'" target="_blank" rel="noopener">'+html(tools.public_url)+'</a></p>'+(tools.qr_url?'<p>QR pickup link: <a href="'+html(tools.qr_url)+'" target="_blank" rel="noopener">'+html(tools.qr_url)+'</a></p>':'')+'<p>Submit endpoint: '+html(tools.submit_endpoint)+'</p>';
  }
  campaignList.addEventListener('click',function(event){var target=event.target.closest('[data-campaign-id]');if(target){load(target.getAttribute('data-campaign-id')).catch(function(error){toolBox.innerHTML='<p>'+html(error.message||'Unable to load public tools.')+'</p>';});}});
});

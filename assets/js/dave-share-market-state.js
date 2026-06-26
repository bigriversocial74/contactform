window.Microgifter=window.Microgifter||{};
(function(window,document){
  'use strict';
  function q(sel,root){return (root||document).querySelector(sel)}
  function qa(sel,root){return Array.prototype.slice.call((root||document).querySelectorAll(sel))}
  function text(value){return String(value==null?'':value)}
  function label(value){return text(value||'').replace(/_/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase()})}
  function num(value){return Number(value||0).toLocaleString()}
  function setByLabel(root,cardSel,labelText,value,small){
    var card=qa(cardSel,root).find(function(node){var span=q('span',node);return span&&span.textContent.trim().toLowerCase()===labelText.toLowerCase()});
    if(!card)return;
    var strong=q('strong',card);if(strong)strong.textContent=value;
    var sm=q('small',card);if(sm&&small!=null)sm.textContent=small;
  }
  function setRow(root,labelText,value,tag){
    var rows=qa('.dsm-row',root);
    rows.forEach(function(row){
      var spans=qa('span',row);
      if(!spans.some(function(span){return span.textContent.trim().toLowerCase()===labelText.toLowerCase()}))return;
      var strong=q('strong',row);if(strong)strong.textContent=value;
      var em=q('em',row);if(em&&tag)em.textContent=tag;
    });
  }
  function setStep(root,current){
    var order=['not_enrolled','review_requested','series_submitted','approved_to_launch','live'];
    var index=0;
    if(current==='review_requested'||current==='approved'||current==='credits_purchased'||current==='series_draft')index=1;
    if(current==='series_submitted'||current==='changes_requested')index=2;
    if(current==='approved_to_launch')index=3;
    if(current==='live')index=4;
    qa('.dsm-step',root).forEach(function(step,i){step.classList.toggle('active',i===index)});
  }
  async function getState(){
    if(window.Microgifter&&typeof window.Microgifter.get==='function'){
      var r=await window.Microgifter.get('/api/share-market/program-status.php');
      return r.data||r;
    }
    var res=await fetch('/api/share-market/program-status.php',{credentials:'same-origin',headers:{'Accept':'application/json'}});
    var json=await res.json();
    if(!res.ok)throw new Error(json.message||'Unable to load Share Market state.');
    return json.data||json;
  }
  function apply(root,state){
    var enrollment=state.enrollment||null;
    var treasury=state.treasury||{};
    var workflow=state.workflow||{};
    var latest=state.latest_series||null;
    var enrollmentStatus=workflow.enrollment_status||(enrollment&&enrollment.status)||'not_enrolled';
    var reviewStatus=workflow.review_status||'Not submitted';
    var current=workflow.current||'not_enrolled';
    var programStatus=label(enrollmentStatus);
    var treasuryStatus=label(treasury.status||'not_created');
    var seriesState=label((latest&&latest.state)||workflow.series_state||'none');

    setByLabel(root,'.dsm-kpi','Program Status',programStatus,'Merchant-accessible, activation remains admin-gated.');
    setByLabel(root,'.dsm-kpi','Participation',enrollment?'Opted in':'Opt-in available',enrollment?'Participation request exists.':'No account is enrolled by default.');
    setByLabel(root,'.dsm-kpi','Review Status',reviewStatus,latest?'Latest series: '+seriesState:'Submit when profile, utility, and treasury are ready.');

    setRow(root,'Purchased',num(treasury.credits_allocated)+' credits','Treasury');
    setRow(root,'Available',num(treasury.credits_available)+' credits','Treasury');
    setRow(root,'Assigned',num(treasury.credits_assigned)+' credits','Market');
    setRow(root,'Circulating',num(treasury.credits_circulating)+' shares','Market');
    setRow(root,'Redeemed / Burned',num(treasury.credits_redeemed||treasury.credits_burned)+' shares','Ledger');
    setRow(root,'Status',treasuryStatus,'Opt-in');
    setRow(root,'Admin Approval','Microgifter review','Gate');

    if(latest){
      setRow(root,'Series Name',latest.name||'Draft series','Draft');
      setRow(root,'Total Supply',num(latest.supply)+' shares',seriesState);
      setRow(root,'Launch Price','$'+(Number(latest.launch_price_cents||0)/100).toFixed(2)+' per share',seriesState);
      setRow(root,'Max Per Buyer',num(latest.max_per_buyer||0)+' shares',seriesState);
    }

    setStep(root,current);
    qa('.dsm-badge.red,.dsm-badge.gold',root).forEach(function(badge){
      var t=badge.textContent.trim().toLowerCase();
      if(t==='not enrolled'||t==='not submitted'){badge.textContent=t==='not enrolled'?programStatus:reviewStatus;}
    });
    root.setAttribute('data-dsm-workflow-state',current);
  }
  document.addEventListener('DOMContentLoaded',function(){
    var root=q('.dsm');
    if(!root)return;
    getState().then(function(state){apply(root,state)}).catch(function(err){root.setAttribute('data-dsm-state-error',err.message||'state unavailable')});
  });
})(window,document);

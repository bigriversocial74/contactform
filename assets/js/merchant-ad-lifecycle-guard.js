(function(window, document){
  'use strict';
  var root = document.querySelector('[data-ads-manager]');
  if (!root) return;

  var STATES = {
    draft: {label:'Draft', action:'Submit for Review', allowSubmit:true, note:'Finish the creative and submit for admin review.'},
    rejected: {label:'Needs Revision', action:'Resubmit', allowSubmit:true, note:'Revise the campaign, then resubmit it for review.'},
    paused: {label:'Paused', action:'Submit Update', allowSubmit:true, note:'Revise or resubmit this paused campaign when it is ready.'},
    pending_review: {label:'Under Review', action:'Under Review', allowSubmit:false, note:'Already submitted. Wait for admin review before submitting again.'},
    approved: {label:'Approved', action:'Approved', allowSubmit:false, note:'Approved by admin and eligible for controlled placement rendering.'},
    active: {label:'Live', action:'Live', allowSubmit:false, note:'Live on eligible active placements. Pause/reactivate is controlled by admin.'},
    completed: {label:'Completed', action:'Completed', allowSubmit:false, note:'Campaign has completed its lifecycle.'},
    archived: {label:'Archived', action:'Archived', allowSubmit:false, note:'Archived campaigns cannot be submitted.'}
  };

  function clean(value){return String(value || '').toLowerCase().trim().replace(/\s+/g,'_');}
  function stateConfig(value){return STATES[clean(value)] || {label:String(value||'Status'), action:'Unavailable', allowSubmit:false, note:'Review this campaign status before taking action.'};}
  function statusNode(){return root.querySelector('[data-ads-status]');}
  function setStatus(message, isError){var node=statusNode(); if(node){node.textContent=message||''; node.style.color=isError?'#b91c1c':'#64748b';}}
  function rowStatus(row){var pill=row && row.querySelector('.mg-ads-pill'); return clean(pill ? pill.textContent : '');}
  function currentStatus(){return clean(root.getAttribute('data-current-ad-status') || 'draft');}
  function setCurrentStatus(status){root.setAttribute('data-current-ad-status', clean(status || 'draft')); updateCurrentSubmit();}

  function updateCurrentSubmit(){
    var btn = root.querySelector('[data-submit-current]');
    if (!btn) return;
    var selectedId = root.getAttribute('data-current-ad-id') || '';
    var config = stateConfig(selectedId ? currentStatus() : 'draft');
    btn.disabled = !!selectedId && !config.allowSubmit;
    btn.textContent = selectedId ? config.action : 'Submit for Review';
    btn.title = selectedId && !config.allowSubmit ? config.note : '';
  }

  function enhanceRows(){
    root.querySelectorAll('[data-campaign-id]').forEach(function(row){
      var config = stateConfig(rowStatus(row));
      row.setAttribute('data-lifecycle-state', clean(rowStatus(row)));
      var statusCell = row.querySelector('.mg-ads-pill');
      if (statusCell && !statusCell.nextElementSibling?.classList?.contains('mg-ads-lifecycle-next')) {
        statusCell.insertAdjacentHTML('afterend', '<small class="mg-ads-lifecycle-next">'+config.note+'</small>');
      }
      var submit = row.querySelector('[data-submit]');
      if (submit) {
        submit.disabled = !config.allowSubmit;
        submit.textContent = config.action;
        submit.title = config.note;
        submit.classList.toggle('is-disabled', !config.allowSubmit);
      }
      var edit = row.querySelector('[data-edit]');
      if (edit && !edit.getAttribute('data-lifecycle-bound')) {
        edit.setAttribute('data-lifecycle-bound','true');
        edit.addEventListener('click', function(){
          root.setAttribute('data-current-ad-id', row.getAttribute('data-campaign-id') || '');
          setCurrentStatus(rowStatus(row));
          setStatus('Loaded '+config.label+' campaign. '+config.note, false);
        }, true);
      }
    });
  }

  root.addEventListener('click', function(event){
    var submit = event.target.closest('[data-submit], [data-submit-current]');
    if (!submit) return;
    var row = submit.closest('[data-campaign-id]');
    var config = row ? stateConfig(rowStatus(row)) : stateConfig(currentStatus());
    if (!config.allowSubmit && (row || root.getAttribute('data-current-ad-id'))) {
      event.preventDefault();
      event.stopPropagation();
      setStatus(config.note, true);
    }
  }, true);

  root.addEventListener('click', function(event){
    var jump = event.target.closest('[data-ads-tab-jump]');
    if (jump && /new campaign/i.test(jump.textContent || '')) {
      root.removeAttribute('data-current-ad-id');
      setCurrentStatus('draft');
    }
  }, true);

  var observer = new MutationObserver(function(){enhanceRows(); updateCurrentSubmit();});
  observer.observe(root, {childList:true, subtree:true});
  setCurrentStatus('draft');
  enhanceRows();
})(window, document);

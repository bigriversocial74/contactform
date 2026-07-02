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
  function escapeHtml(value){return String(value == null ? '' : value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function stateConfig(value){return STATES[clean(value)] || {label:String(value||'Status'), action:'Unavailable', allowSubmit:false, note:'Review this campaign status before taking action.'};}
  function statusNode(){return root.querySelector('[data-ads-status]');}
  function setStatus(message, isError){var node=statusNode(); if(node){node.textContent=message||''; node.style.color=isError?'#b91c1c':'#64748b'; node.classList.toggle('is-error', !!isError);}}
  function alertPanel(title, message){return '<div class="mg-ads-alert"><strong>'+escapeHtml(title)+'</strong><br>'+escapeHtml(message)+'</div>';}
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

  function hasLifecycleNote(statusCell){
    var next = statusCell ? statusCell.nextElementSibling : null;
    return !!(next && next.classList && next.classList.contains('mg-ads-lifecycle-next'));
  }

  function enhanceRows(){
    root.querySelectorAll('[data-campaign-id]').forEach(function(row){
      var config = stateConfig(rowStatus(row));
      row.setAttribute('data-lifecycle-state', clean(rowStatus(row)));
      var statusCell = row.querySelector('.mg-ads-pill');
      if (statusCell && !hasLifecycleNote(statusCell)) {
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

  function isStillLoading(node, phrase){
    return !!(node && String(node.textContent || '').toLowerCase().indexOf(String(phrase || '').toLowerCase()) !== -1);
  }

  function ensureLoadFinished(){
    var list = root.querySelector('[data-ads-list]');
    if (isStillLoading(list, 'loading campaigns')) {
      list.innerHTML = alertPanel('Campaigns did not finish loading', 'The editor is still available. Refresh the page, or save a new campaign and try the Campaigns tab again.');
      setStatus('Campaign list did not finish loading. The create form is still available.', true);
    }

    var picker = root.querySelector('[data-product-picker]');
    if (isStillLoading(picker, 'loading')) {
      picker.innerHTML = '<option value="">Product picker did not finish loading</option>';
      setStatus('Product picker did not finish loading. You can still create the campaign manually.', true);
    }

    root.querySelectorAll('[data-ads-preview],[data-ads-preview-secondary],[data-campaign-post-preview],[data-campaign-post-preview-secondary]').forEach(function(node){
      if (node && String(node.innerHTML || '').trim() === '') {
        node.innerHTML = alertPanel('Preview unavailable', 'The campaign form is still usable while the preview recovers.');
      }
    });
  }

  window.addEventListener('error', function(event){
    var source = String(event && event.filename || '');
    if (source.indexOf('merchant-ad') !== -1 || source.indexOf('sponsored-campaign') !== -1) {
      ensureLoadFinished();
      setStatus('Campaign Ads script error: '+String(event.message || 'Unknown error'), true);
    }
  });

  window.addEventListener('unhandledrejection', function(event){
    var reason = event && event.reason;
    var message = reason && reason.message ? reason.message : 'A Campaign Ads request failed.';
    ensureLoadFinished();
    setStatus(message, true);
  });

  root.addEventListener('click', function(event){
    var target = event.target && event.target.closest ? event.target : null;
    if (!target) return;
    var submit = target.closest('[data-submit], [data-submit-current]');
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
    var target = event.target && event.target.closest ? event.target : null;
    if (!target) return;
    var jump = target.closest('[data-ads-tab-jump]');
    if (jump && /new campaign/i.test(jump.textContent || '')) {
      root.removeAttribute('data-current-ad-id');
      setCurrentStatus('draft');
    }
  }, true);

  var observer = new MutationObserver(function(){enhanceRows(); updateCurrentSubmit();});
  observer.observe(root, {childList:true, subtree:true});
  setCurrentStatus('draft');
  enhanceRows();
  window.setTimeout(ensureLoadFinished, 4500);
  window.setTimeout(ensureLoadFinished, 9000);
})(window, document);

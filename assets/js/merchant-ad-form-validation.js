(function(window, document){
  'use strict';

  var root = document.querySelector('[data-ads-manager]');
  if (!root) return;

  function qs(selector, scope){return (scope || root).querySelector(selector);}
  function qsa(selector, scope){return Array.prototype.slice.call((scope || root).querySelectorAll(selector));}
  function field(name){return qs('[name="' + name + '"]');}
  function value(name){var node = field(name); return node ? String(node.value || '').trim() : '';}
  function checkedPlacements(){return qsa('[name="placements[]"]:checked').map(function(input){return input.value;});}
  function statusNode(){return qs('[data-ads-status]');}

  function setStatus(message, isError){
    var node = statusNode();
    if (!node) return;
    node.textContent = message || '';
    node.style.color = isError ? '#b91c1c' : '#64748b';
  }

  function markInvalid(node, invalid){
    if (!node) return;
    if (invalid) {
      node.setAttribute('aria-invalid', 'true');
      node.dataset.adsValidationError = 'true';
    } else {
      node.removeAttribute('aria-invalid');
      delete node.dataset.adsValidationError;
    }
  }

  function clearValidation(){
    qsa('[data-ads-validation-error="true"]').forEach(function(node){markInvalid(node, false);});
    qsa('[name="placements[]"]').forEach(function(input){input.closest('label') && input.closest('label').removeAttribute('data-ads-validation-error');});
  }

  function readableList(items){
    if (items.length <= 1) return items.join('');
    if (items.length === 2) return items[0] + ' and ' + items[1];
    return items.slice(0, -1).join(', ') + ', and ' + items[items.length - 1];
  }

  function unsafeDestination(url){
    var cleaned = String(url || '').trim().toLowerCase();
    return cleaned.indexOf('javascript:') === 0 || cleaned.indexOf('data:') === 0;
  }

  function invalidDateRange(){
    var start = value('starts_at');
    var end = value('ends_at');
    if (!start || !end) return false;
    var startDate = new Date(start);
    var endDate = new Date(end);
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) return false;
    return endDate.getTime() < startDate.getTime();
  }

  function validateCampaignForm(mode){
    clearValidation();
    var errors = [];
    var headline = field('headline');
    var description = field('description');
    var cta = field('cta_label');
    var destination = field('destination_url');
    var end = field('ends_at');

    if (!value('headline')) {
      errors.push('headline');
      markInvalid(headline, true);
    }
    if (!value('description')) {
      errors.push('description');
      markInvalid(description, true);
    }
    if (!value('cta_label')) {
      errors.push('CTA');
      markInvalid(cta, true);
    }
    if (value('cta_label') && !value('destination_url')) {
      errors.push('destination URL');
      markInvalid(destination, true);
    }
    if (value('destination_url') && unsafeDestination(value('destination_url'))) {
      errors.push('safe destination URL');
      markInvalid(destination, true);
    }
    if (!checkedPlacements().length) {
      errors.push('at least one placement');
      qsa('[name="placements[]"]').forEach(function(input){input.closest('label') && input.closest('label').setAttribute('data-ads-validation-error', 'true');});
    }
    if (invalidDateRange()) {
      errors.push('end date after start date');
      markInvalid(end, true);
    }

    if (!errors.length) {
      setStatus(mode === 'submit' ? 'Campaign validation passed. Submitting for review…' : 'Campaign validation passed. Saving…', false);
      return true;
    }

    var firstInvalid = qs('[aria-invalid="true"]') || qs('[data-ads-validation-error="true"] input');
    var prefix = mode === 'submit' ? 'Before submitting, add ' : 'Before saving, add ';
    setStatus(prefix + readableList(errors) + '.', true);
    if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus({preventScroll:false});
    return false;
  }

  function isRowSubmit(button){
    return !!(button && button.closest('[data-campaign-id]'));
  }

  root.addEventListener('click', function(event){
    var save = event.target.closest('[data-save-draft], [data-new-draft]');
    var submit = event.target.closest('[data-submit-current]');
    if (!save && !submit) return;
    if (submit && isRowSubmit(submit)) return;
    var ok = validateCampaignForm(submit ? 'submit' : 'save');
    if (!ok) {
      event.preventDefault();
      event.stopPropagation();
    }
  }, true);

  root.addEventListener('input', function(event){
    if (event.target && event.target.getAttribute('aria-invalid') === 'true') markInvalid(event.target, false);
  }, true);

  root.addEventListener('change', function(event){
    if (event.target && event.target.name === 'placements[]') {
      qsa('[name="placements[]"]').forEach(function(input){input.closest('label') && input.closest('label').removeAttribute('data-ads-validation-error');});
    }
  }, true);
})(window, document);

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-location-redemption-manager]');
  if (!root || !window.Microgifter) return;

  var list = root.querySelector('[data-location-list]');
  var form = root.querySelector('[data-location-form]');
  var codePanel = root.querySelector('[data-claim-code-panel]');
  var codeForm = root.querySelector('[data-claim-code-form]');
  var codeList = root.querySelector('[data-claim-code-list]');
  var selectedLocation = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[char];
    });
  }

  function setStatus(node, message, type) {
    if (window.Microgifter && typeof Microgifter.setStatus === 'function') {
      Microgifter.setStatus(node, message, type);
    } else if (node) {
      node.textContent = message || '';
    }
  }

  function resetLocationForm() {
    form.reset();
    form.elements.location_id.value = '';
    form.elements.country_code.value = 'US';
    form.elements.timezone.value = 'America/Phoenix';
    form.elements.status.value = 'active';
    form.elements.check_in_radius_meters.value = '150';
    toggleArchiveReason();
    setStatus(form.querySelector('[data-location-status]'), '');
  }

  function toggleArchiveReason() {
    var wrap = root.querySelector('[data-location-archive-reason-wrap]');
    var archived = form.elements.status.value === 'archived';
    if (wrap) wrap.hidden = !archived;
    if (form.elements.archive_reason) form.elements.archive_reason.required = archived;
  }

  function populateLocation(item) {
    selectedLocation = item;
    Object.keys(item).forEach(function (key) {
      if (!form.elements[key]) return;
      if (form.elements[key].type === 'checkbox') form.elements[key].checked = Boolean(Number(item[key]));
      else form.elements[key].value = item[key] == null ? '' : item[key];
    });
    form.elements.location_id.value = item.public_id || '';
    toggleArchiveReason();
    openCodePanel(item);
    document.getElementById('location-editor-panel').scrollIntoView({behavior:'smooth', block:'start'});
  }

  function blockersText(item) {
    var blockers = Array.isArray(item.archive_blockers) ? item.archive_blockers : [];
    if (!blockers.length) return 'Archive ready';
    return blockers.map(function (b) { return (b.count || 0) + ' ' + String(b.type || 'blocker').replace(/_/g, ' '); }).join(' · ');
  }

  function renderMetrics(items) {
    var active = items.filter(function (x) { return x.status === 'active'; }).length;
    var archived = items.filter(function (x) { return x.status === 'archived'; }).length;
    var primary = items.filter(function (x) { return Number(x.is_primary); }).length;
    var codes = items.reduce(function (sum, x) { return sum + Number(x.active_claim_code_count || 0); }, 0);
    var staff = items.filter(function (x) { return x.address_line1 && x.city && x.phone; }).length;
    [['[data-location-kpi-active]',active],['[data-location-kpi-claim]',codes],['[data-location-kpi-primary]',primary || '—'],['[data-location-kpi-archived]',archived],['[data-location-kpi-staff]',staff]].forEach(function (row) {
      var node = root.querySelector(row[0]);
      if (node) node.textContent = String(row[1]);
    });
  }

  async function loadLocations() {
    var response = await Microgifter.get('/api/merchant/locations-v2.php');
    var items = (response.data || response).locations || [];
    renderMetrics(items);
    list.innerHTML = items.map(function (item) {
      var address = [item.address_line1,item.city,item.region,item.postal_code].filter(Boolean).join(', ');
      var activeCodes = Number(item.active_claim_code_count || 0);
      return '<button type="button" class="mg-location-card" data-location-id="'+esc(item.public_id)+'">'
        +'<span><strong>'+esc(item.name)+'</strong><span>'+esc(address || item.location_code || 'No address saved')+'</span>'
        +'<small>'+activeCodes+' active claim code'+(activeCodes===1?'':'s')+' · '+esc(blockersText(item))+'</small></span>'
        +'<span class="mg-card-meta"><em>'+esc(item.status)+'</em>'+(Number(item.is_primary)?'<em>Primary</em>':'')+'</span></button>';
    }).join('') || '<div class="mg-empty-state"><p>No locations yet. Add the first merchant location.</p></div>';

    list.querySelectorAll('[data-location-id]').forEach(function (button) {
      button.addEventListener('click', function () {
        var item = items.find(function (row) { return row.public_id === button.dataset.locationId; });
        if (item) populateLocation(item);
      });
    });
  }

  async function openCodePanel(item) {
    selectedLocation = item;
    codePanel.hidden = false;
    codeForm.elements.location_id.value = item.public_id;
    var copy = codePanel.querySelector('[data-claim-code-location-copy]');
    if (copy) copy.textContent = 'Manage independently assigned claim codes for ' + item.name + '.';
    await loadCodes(item.public_id);
  }

  function formatDate(value) {
    if (!value) return 'No schedule';
    try { return new Date(String(value).replace(' ', 'T')).toLocaleString(); }
    catch (error) { return value; }
  }

  async function loadCodes(locationId) {
    var response = await Microgifter.get('/api/merchant/claim-codes.php?location_id=' + encodeURIComponent(locationId));
    var codes = (response.data || response).claim_codes || [];
    codeList.innerHTML = codes.map(function (code) {
      var assignment = code.assignment_type || 'location';
      var ref = code.assignment_reference ? ' · ' + code.assignment_reference : '';
      var schedule = code.valid_until ? 'Until ' + formatDate(code.valid_until) : (code.valid_from ? 'From ' + formatDate(code.valid_from) : 'No expiration');
      var usage = code.usage_limit == null ? 'Unlimited uses' : (Number(code.usage_count || 0) + ' / ' + Number(code.usage_limit) + ' uses');
      var next = code.status === 'active' ? 'inactive' : 'active';
      return '<article class="mg-location-card"><span><strong>'+esc(code.label)+'</strong><span>'+esc(assignment+ref)+' · ending '+esc(code.code_last4)+'</span><small>'+esc(schedule)+' · '+esc(usage)+'</small></span>'
        +'<span class="mg-card-meta"><em>'+esc(code.status)+'</em><button type="button" class="mg-btn mg-btn-soft" data-code-id="'+esc(code.public_id)+'" data-code-status="'+esc(next)+'">'+(next==='active'?'Activate':'Deactivate')+'</button>'
        +(code.status!=='revoked'?'<button type="button" class="mg-btn mg-btn-soft" data-code-id="'+esc(code.public_id)+'" data-code-status="revoked">Revoke</button>':'')+'</span></article>';
    }).join('') || '<div class="mg-empty-state"><p>No claim codes for this location yet.</p></div>';

    codeList.querySelectorAll('[data-code-id]').forEach(function (button) {
      button.addEventListener('click', async function () {
        try {
          await Microgifter.patch('/api/merchant/claim-codes.php', {claim_code_id:button.dataset.codeId,status:button.dataset.codeStatus});
          await loadCodes(locationId);
          await loadLocations();
        } catch (error) {
          setStatus(codeForm.querySelector('[data-claim-code-status]'), error.message || 'Unable to update claim code.', 'error');
        }
      });
    });
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    var data = Object.fromEntries(new FormData(form).entries());
    data.is_primary = form.elements.is_primary.checked ? 1 : 0;
    var status = form.querySelector('[data-location-status]');
    try {
      setStatus(status, 'Saving location…');
      var response = await Microgifter.post('/api/merchant/locations-v2.php', data);
      setStatus(status, response.message || 'Location saved.', 'success');
      resetLocationForm();
      await loadLocations();
    } catch (error) {
      setStatus(status, error.message || 'Unable to save location.', 'error');
    }
  });

  codeForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    var data = Object.fromEntries(new FormData(codeForm).entries());
    var status = codeForm.querySelector('[data-claim-code-status]');
    try {
      setStatus(status, 'Adding claim code…');
      var response = await Microgifter.post('/api/merchant/claim-codes.php', data);
      setStatus(status, response.message || 'Claim code added.', 'success');
      var locationId = codeForm.elements.location_id.value;
      codeForm.reset();
      codeForm.elements.location_id.value = locationId;
      codeForm.elements.assignment_type.value = 'location';
      await loadCodes(locationId);
      await loadLocations();
    } catch (error) {
      setStatus(status, error.message || 'Unable to add claim code.', 'error');
    }
  });

  form.elements.status.addEventListener('change', toggleArchiveReason);
  root.querySelector('[data-location-open-add]').addEventListener('click', function () {
    resetLocationForm();
    document.getElementById('location-editor-panel').scrollIntoView({behavior:'smooth', block:'start'});
  });
  root.querySelector('[data-location-reset]').addEventListener('click', resetLocationForm);

  resetLocationForm();
  loadLocations().catch(function (error) {
    list.innerHTML = '<div class="mg-empty-state"><p>'+esc(error.message || 'Unable to load locations.')+'</p></div>';
  });
});

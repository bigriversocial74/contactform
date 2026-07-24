(() => {
  'use strict';
  const root = document.querySelector('[data-investor-access]');
  if (!root) return;
  const form = root.querySelector('[data-access-form]');
  const status = root.querySelector('[data-access-status]');
  const notice = root.querySelector('[data-access-notice]');
  const submit = root.querySelector('[data-access-submit]');
  const withdraw = root.querySelector('[data-access-withdraw]');
  let current = null;

  const label = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  const setNotice = (message = '', type = 'info') => { notice.textContent = message; notice.dataset.type = type; };
  const request = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', ...(options.body ? {'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrfToken || ''} : {}) }, ...options });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Request failed.');
    return payload.data;
  };
  const fill = (item) => {
    if (!item) return;
    ['firm_name','job_title','website_url','primary_social_url','linkedin_url','additional_social_url','investor_type','expected_investment_range','referral_source','phone','request_reason'].forEach((name) => {
      const field = form.elements.namedItem(name);
      if (field) field.value = item[name] || '';
    });
  };
  const render = () => {
    const state = current?.status || 'not submitted';
    status.innerHTML = `<strong>${label(state)}</strong><span>${current ? `Last updated ${new Date(current.updated_at.replace(' ','T')).toLocaleString()}` : 'Complete the application to request access.'}</span>`;
    const locked = current && ['pending','approved'].includes(current.status);
    Array.from(form.elements).forEach((field) => { if (field.name !== 'acknowledgement') field.disabled = Boolean(locked); });
    submit.hidden = Boolean(locked);
    submit.textContent = current?.status === 'more_information_requested' ? 'Resubmit Investor Access Request' : 'Submit Investor Access Request';
    withdraw.hidden = !(current && ['pending','more_information_requested'].includes(current.status));
    if (current?.more_information_message) setNotice(`More information requested: ${current.more_information_message}`,'error');
  };
  const load = async () => {
    try { current = (await request('/api/investment/access-request.php')).request; fill(current); render(); }
    catch (error) { setNotice(error.message,'error'); status.innerHTML='<strong>Unable to load status</strong><span>Try refreshing the page.</span>'; }
  };
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    data.action = current?.status === 'more_information_requested' ? 'resubmit' : 'submit';
    data.acknowledgement = form.elements.acknowledgement.checked;
    submit.disabled = true; setNotice('Submitting protected investor-access request…');
    try { current = (await request('/api/investment/access-request.php',{method:'POST',body:JSON.stringify(data)})).request; render(); setNotice('Investor-access request submitted for Super Admin review.','success'); }
    catch (error) { setNotice(error.message,'error'); }
    finally { submit.disabled = false; }
  });
  withdraw.addEventListener('click', async () => {
    if (!confirm('Withdraw this investor-access request?')) return;
    withdraw.disabled = true;
    try { current = (await request('/api/investment/access-request.php',{method:'POST',body:JSON.stringify({action:'withdraw'})})).request; render(); setNotice('Request withdrawn.','success'); }
    catch (error) { setNotice(error.message,'error'); }
    finally { withdraw.disabled = false; }
  });
  load();
})();

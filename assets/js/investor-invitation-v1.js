(() => {
  'use strict';
  const root = document.querySelector('[data-investor-invitation]');
  const form = root?.querySelector('[data-invitation-form]');
  if (!root || !form) return;
  const notice = form.querySelector('[data-invitation-notice]');
  const submit = form.querySelector('[data-invitation-submit]');
  const setNotice = (message = '', type = 'info') => { notice.textContent = message; notice.dataset.type = type; };
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.action = 'accept';
    payload.token = root.dataset.token || '';
    ['identity_acknowledgement','confidentiality_acknowledgement','acknowledgement'].forEach((name) => {
      payload[name] = Boolean(form.elements.namedItem(name)?.checked);
    });
    submit.disabled = true;
    setNotice('Submitting protected Investor onboarding…');
    try {
      const response = await fetch('/api/investment/invitation.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': root.dataset.csrfToken || '',
        },
        body: JSON.stringify(payload),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok || !data?.ok) throw new Error(data?.message || 'Unable to submit Investor onboarding.');
      setNotice(data.message || 'Investor onboarding submitted for review.', 'success');
      window.setTimeout(() => { window.location.href = data.data?.redirect || '/investor-access.php?source=invitation'; }, 500);
    } catch (error) {
      setNotice(error.message || 'Unable to submit Investor onboarding.', 'error');
    } finally {
      submit.disabled = false;
    }
  });
})();

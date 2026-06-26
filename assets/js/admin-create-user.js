(() => {
  'use strict';

  const root = document.querySelector('[data-admin-users]');
  if (!root) return;

  const openButton = root.querySelector('[data-user-create-open]');
  const layer = root.querySelector('[data-user-create-layer]');
  const form = root.querySelector('[data-user-create-form]');
  const notice = root.querySelector('[data-user-create-notice]');
  if (!openButton || !layer || !form) return;

  function show(visible) {
    layer.classList.toggle('mg-hidden', !visible);
    document.body.classList.toggle('mg-admin-user-create-open', visible);
    if (visible) {
      window.setTimeout(() => form.querySelector('input[name="full_name"]')?.focus(), 50);
    }
  }

  function setNotice(message, type = 'info') {
    if (!notice) return;
    notice.textContent = message || '';
    notice.dataset.type = type;
  }

  function setBusy(busy) {
    form.querySelectorAll('input,select,textarea,button').forEach((field) => {
      field.disabled = busy;
    });
  }

  function roles() {
    return Array.from(form.querySelectorAll('input[name="roles[]"]:checked')).map((input) => input.value);
  }

  function values() {
    const data = new FormData(form);
    return {
      email: String(data.get('email') || '').trim(),
      full_name: String(data.get('full_name') || '').trim(),
      display_name: String(data.get('display_name') || '').trim(),
      password: String(data.get('password') || ''),
      status: String(data.get('status') || 'active'),
      email_verified: String(data.get('email_verified') || '0') === '1',
      reason: String(data.get('reason') || '').trim(),
      roles: roles(),
    };
  }

  function validate(payload) {
    if (!payload.full_name) return 'Full name is required.';
    if (!payload.email || !payload.email.includes('@')) return 'A valid email is required.';
    if (payload.password && payload.password.length < 12) return 'Temporary password must be at least 12 characters.';
    if (!payload.roles.length) return 'Select at least one role.';
    if (payload.reason.length < 8 || payload.reason.length > 240) return 'Enter an action reason between 8 and 240 characters.';
    return '';
  }

  openButton.addEventListener('click', () => {
    form.reset();
    form.querySelector('input[value="customer"]')?.setAttribute('checked', 'checked');
    setNotice('');
    show(true);
  });

  root.querySelectorAll('[data-user-create-close]').forEach((button) => {
    button.addEventListener('click', () => show(false));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !layer.classList.contains('mg-hidden')) show(false);
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = values();
    const problem = validate(payload);
    if (problem) {
      setNotice(problem, 'error');
      return;
    }
    if (!window.confirm(`Create ${payload.email} with ${payload.roles.join(', ')} role access?`)) return;

    setBusy(true);
    setNotice('Creating protected user record…');
    try {
      const response = await Microgifter.post('/api/admin/user-create.php', payload);
      if (!response?.ok) throw new Error(response?.message || 'Unable to create user.');
      const temp = response?.data?.temporary_password;
      if (temp) {
        setNotice(`User created. Temporary password: ${temp}`, 'success');
      } else {
        setNotice(response.message || 'User created.', 'success');
      }
      document.dispatchEvent(new CustomEvent('mg:admin-users-refresh'));
      window.setTimeout(() => show(false), temp ? 6000 : 1500);
    } catch (error) {
      setNotice(error.message || 'Unable to create user.', 'error');
    } finally {
      setBusy(false);
    }
  });
})();

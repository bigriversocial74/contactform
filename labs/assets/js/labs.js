(() => {
  const toggles = document.querySelectorAll('[data-labs-toggle]');
  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const target = document.querySelector(toggle.getAttribute('data-labs-toggle'));
      if (target) target.classList.toggle('is-open');
    });
  });

  const storageKey = 'trainingLabDemoStateV1';
  const defaultState = {
    proofStatus: 'not_submitted',
    reviewStatus: 'not_submitted',
    completedActions: 4,
    streakDays: 4,
    rewardStatus: 'pending',
    walletItems: [
      { label: 'Consistency Badge', status: 'Available' },
      { label: 'Movement Milestone', status: 'Pending' },
      { label: 'Safety Readiness', status: 'Locked' }
    ],
    updatedAt: ''
  };

  const readState = () => {
    try {
      return { ...defaultState, ...(JSON.parse(localStorage.getItem(storageKey)) || {}) };
    } catch (error) {
      return { ...defaultState };
    }
  };

  const writeState = (nextState) => {
    const state = { ...readState(), ...nextState, updatedAt: new Date().toLocaleString() };
    localStorage.setItem(storageKey, JSON.stringify(state));
    renderState(state);
  };

  const labelMap = {
    not_submitted: 'Not submitted',
    submitted: 'Submitted',
    in_review: 'In review',
    approved: 'Approved',
    pending: 'Pending',
    unlocked: 'Unlocked'
  };

  const pretty = (value) => labelMap[value] || value;

  const setText = (selector, value) => {
    document.querySelectorAll(selector).forEach((node) => { node.textContent = value; });
  };

  const renderState = (state = readState()) => {
    const progress = Math.min(100, Math.max(0, Number(state.completedActions || 0) * 20));
    setText('[data-demo-proof-status]', pretty(state.proofStatus));
    setText('[data-demo-review-status]', pretty(state.reviewStatus));
    setText('[data-demo-reward-status]', pretty(state.rewardStatus));
    setText('[data-demo-streak-days]', String(state.streakDays || 0));
    setText('[data-demo-completed-actions]', String(state.completedActions || 0));
    setText('[data-demo-progress-label]', `${progress}% complete`);
    setText('[data-demo-updated-at]', state.updatedAt || 'Not updated yet');

    document.querySelectorAll('[data-demo-progress-fill]').forEach((node) => {
      node.style.width = `${progress}%`;
    });
  };

  document.querySelectorAll('[data-demo-action]').forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.getAttribute('data-demo-action');
      if (action === 'submit-proof') {
        writeState({ proofStatus: 'submitted', reviewStatus: 'in_review', completedActions: 5, rewardStatus: 'pending' });
      }
      if (action === 'approve-proof') {
        writeState({ proofStatus: 'approved', reviewStatus: 'approved', completedActions: 5, rewardStatus: 'unlocked' });
      }
      if (action === 'reset-demo') {
        localStorage.removeItem(storageKey);
        renderState({ ...defaultState });
      }
    });
  });

  renderState();
})();

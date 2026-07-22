(() => {
  'use strict';
  const root = document.querySelector('[data-cc-builder]');
  if (!root) return;

  const checkbox = root.querySelector('input[name="automatic_acceptance"]');
  if (checkbox) {
    checkbox.disabled = false;
    const label = checkbox.closest('label');
    const help = label?.querySelector('small');
    if (help) {
      help.textContent = 'Applications that satisfy every required eligibility rule and the campaign capacity check may be approved automatically. Agreement Version 1 is then offered for creator acceptance.';
    }
  }

  const headerActions = root.querySelector('.mg-cc-head-actions');
  if (headerActions && !headerActions.querySelector('[data-cc-participation-link]')) {
    const link = document.createElement('a');
    link.className = 'mg-btn mg-btn-soft';
    link.href = '/merchant-creator-participation.php';
    link.dataset.ccParticipationLink = '';
    link.textContent = 'Participation';
    headerActions.prepend(link);
  }

  const reviewActions = root.querySelector('.mg-cc-review-actions');
  if (reviewActions && !reviewActions.querySelector('[data-cc-participation-link]')) {
    const link = document.createElement('a');
    link.className = 'mg-btn mg-btn-soft';
    link.href = '/merchant-creator-participation.php';
    link.dataset.ccParticipationLink = '';
    link.textContent = 'Manage Participation';
    reviewActions.insertBefore(link, reviewActions.firstChild);
  }
})();

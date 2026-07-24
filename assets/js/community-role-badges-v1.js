(() => {
  'use strict';

  const COMMUNITY = Object.freeze({
    key: 'community',
    label: 'Community',
    icon: 'star',
    renderedLabel: '★ Community',
    disclaimer: 'Role status only; not identity, nonprofit, charity, campaign, financial, government, or Microgifter verification or endorsement.',
  });

  function normalized(value) {
    return String(value ?? '').trim().toLowerCase().replace(/[_-]+/g, ' ');
  }

  function makeBadge(extraClass = '') {
    const badge = document.createElement('span');
    badge.className = `mg-community-role-badge${extraClass ? ` ${extraClass}` : ''}`;
    badge.textContent = COMMUNITY.renderedLabel;
    badge.title = COMMUNITY.disclaimer;
    badge.setAttribute('aria-label', `${COMMUNITY.renderedLabel}. ${COMMUNITY.disclaimer}`);
    badge.dataset.roleBadge = COMMUNITY.key;
    return badge;
  }

  function decorateAdminRoleNodes(scope = document) {
    scope.querySelectorAll('.mg-admin-user-role').forEach((node) => {
      if (normalized(node.textContent) !== COMMUNITY.key || node.dataset.roleBadge === COMMUNITY.key) return;
      node.textContent = COMMUNITY.renderedLabel;
      node.classList.add('mg-community-role-badge');
      node.title = COMMUNITY.disclaimer;
      node.setAttribute('aria-label', `${COMMUNITY.renderedLabel}. ${COMMUNITY.disclaimer}`);
      node.dataset.roleBadge = COMMUNITY.key;
    });
  }

  function injectRemovalWarning(detail) {
    const user = detail?.user;
    const drawer = detail?.drawer;
    if (!user || !drawer) return;

    const roles = Array.isArray(user.roles) ? user.roles : [];
    if (!roles.some((role) => normalized(role?.slug) === COMMUNITY.key)) return;

    const section = drawer.querySelector('.mg-admin-user-management-section');
    if (!section || section.querySelector('[data-community-role-removal-warning]')) return;

    const warning = document.createElement('div');
    warning.className = 'mg-community-role-warning';
    warning.dataset.communityRoleRemovalWarning = '1';
    warning.setAttribute('role', 'note');

    const title = document.createElement('strong');
    title.textContent = 'Community role removal';
    const copy = document.createElement('span');
    copy.textContent = 'Removing Community preserves every unrelated role and existing account record. Future Community campaign relationships may require review or may prevent removal. The badge is role status only and is not verification or endorsement.';
    warning.append(title, copy);

    const stack = section.querySelector('.mg-admin-management-stack');
    section.insertBefore(warning, stack || null);
  }

  async function renderPublicBadge(data) {
    const profile = data?.profile || {};
    const root = document.querySelector('[data-public-profile-page]');
    const row = root?.querySelector('[data-profile-status-row]');
    const slug = String(profile.slug || root?.dataset.profileSlug || '').trim();
    if (!row || !slug || row.querySelector('[data-role-badge="community"]')) return;

    try {
      const response = await fetch(`/api/public/profile-role-badges.php?slug=${encodeURIComponent(slug)}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json().catch(() => null);
      const badges = payload?.data?.badges;
      if (!response.ok || !payload?.ok || !Array.isArray(badges)) return;
      if (badges.some((badge) => normalized(badge?.key) === COMMUNITY.key)) {
        row.appendChild(makeBadge('mg-profile-pill'));
      }
    } catch (_) {
      // Badge enrichment must never block the public profile.
    }
  }

  document.addEventListener('mg:admin-user-detail-loaded', (event) => {
    window.setTimeout(() => {
      injectRemovalWarning(event.detail);
      decorateAdminRoleNodes(event.detail?.drawer || document);
    }, 0);
  });

  document.addEventListener('mg:admin-users-refresh', () => window.setTimeout(() => decorateAdminRoleNodes(), 0));
  document.addEventListener('mg:public-profile:data', (event) => renderPublicBadge(event.detail));

  const observer = new MutationObserver((records) => {
    records.forEach((record) => record.addedNodes.forEach((node) => {
      if (!(node instanceof Element)) return;
      if (node.matches('.mg-admin-user-role')) decorateAdminRoleNodes(node.parentElement || node);
      else decorateAdminRoleNodes(node);
    }));
  });

  function init() {
    decorateAdminRoleNodes();
    observer.observe(document.body, { childList: true, subtree: true });
    if (window.Microgifter?.publicProfileData) {
      renderPublicBadge(window.Microgifter.publicProfileData);
    }
  }

  window.MicrogifterRoleBadges = Object.freeze({
    community: COMMUNITY,
    makeCommunityBadge: makeBadge,
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();

window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;

  var labels = {
    use_cases: {
      gift_certificates: 'Gift certificates',
      customer_engagement: 'Customer engagement',
      workplace_rewards: 'Workplace rewards',
      group_gifting: 'Group gifting',
      loyalty_crm: 'Loyalty & CRM',
      events_community_rewards: 'Events & community rewards'
    },
    audiences: {
      customers: 'Customers',
      event_attendees: 'Event attendees',
      employees: 'Employees',
      community_groups: 'Community groups',
      members_supporters: 'Members or supporters'
    },
    organization_type: {
      restaurant_cafe: 'Restaurant or café',
      wellness_hospitality: 'Wellness or hospitality',
      retail_store: 'Retail store',
      organization_nonprofit: 'Organization or nonprofit',
      service_business: 'Service business',
      other: 'Other'
    },
    location_count: {
      '1': '1 location',
      '2_5': '2–5 locations',
      '6_plus': '6+ locations'
    },
    start_preference: {
      create_account: 'Create an account',
      plan_help: 'Help choosing a plan',
      guided_demo: 'Guided demo'
    },
    team_size: {
      '1': 'Just me',
      '2_10': '2–10',
      '11_50': '11–50',
      '51_200': '51–200',
      '201_plus': '201+'
    }
  };

  function visitorCountry() {
    var language = navigator.language || (navigator.languages && navigator.languages[0]) || '';
    var parts = String(language).split('-');
    return parts.length > 1 ? parts.pop().toUpperCase() : '';
  }

  function applyTrackingFields(form) {
    var params = new URLSearchParams(window.location.search || '');
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function (key) {
      if (form.elements[key]) form.elements[key].value = params.get(key) || '';
    });
    if (form.elements.source_url) form.elements.source_url.value = window.location.href;
    if (form.elements.timezone_label) {
      form.elements.timezone_label.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    }
  }

  function checkedValues(form, name) {
    return Array.prototype.slice.call(form.querySelectorAll('input[name="' + name + '[]"]:checked')).map(function (input) {
      return input.value;
    });
  }

  function selectedValue(form, name) {
    var field = form.elements[name];
    if (!field) return '';
    if (typeof field.value === 'string') return field.value;
    var selected = Array.prototype.slice.call(field).find(function (item) { return item.checked; });
    return selected ? selected.value : '';
  }

  function humanList(group, values) {
    return values.map(function (value) {
      return (labels[group] && labels[group][value]) || value;
    }).join(', ');
  }

  function humanValue(group, value) {
    return (labels[group] && labels[group][value]) || value || 'Not provided';
  }

  function leadTypeFor(useCases) {
    if (useCases.indexOf('workplace_rewards') !== -1) return 'workplace';
    if (
      useCases.indexOf('gift_certificates') !== -1 ||
      useCases.indexOf('customer_engagement') !== -1 ||
      useCases.indexOf('loyalty_crm') !== -1 ||
      useCases.indexOf('group_gifting') !== -1
    ) return 'merchant';
    return 'general';
  }

  function validateRequiredGroups(form) {
    var firstInvalid = null;
    Array.prototype.slice.call(form.querySelectorAll('[data-lm-required-group]')).forEach(function (group) {
      var name = group.dataset.lmRequiredGroup;
      var values = checkedValues(form, name);
      var error = form.querySelector('[data-lm-group-error="' + name + '"]');
      var invalid = values.length === 0;
      if (error) error.classList.toggle('is-visible', invalid);
      group.classList.toggle('has-error', invalid);
      if (invalid && !firstInvalid) firstInvalid = group;
    });
    if (firstInvalid) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      var firstChoice = firstInvalid.querySelector('input');
      if (firstChoice) window.setTimeout(function () { firstChoice.focus({ preventScroll: true }); }, 350);
      return false;
    }
    return true;
  }

  function prepareCrmFields(form) {
    var useCases = checkedValues(form, 'use_cases');
    var audiences = checkedValues(form, 'audiences');
    var organizationType = selectedValue(form, 'organization_type');
    var locationCount = selectedValue(form, 'location_count');
    var startPreference = selectedValue(form, 'start_preference');
    var teamSize = selectedValue(form, 'team_size');
    var firstName = String(form.elements.first_name.value || '').trim();
    var lastName = String(form.elements.last_name.value || '').trim();
    var goals = String(form.elements.goals.value || '').trim();

    form.elements.name.value = (firstName + ' ' + lastName).trim();
    form.elements.email.value = String(form.elements.work_email.value || '').trim();
    form.elements.business_name.value = String(form.elements.company_name.value || '').trim();
    form.elements.website_url.value = String(form.elements.website.value || '').trim();
    form.elements.category.value = humanValue('organization_type', organizationType);
    form.elements.lead_type.value = leadTypeFor(useCases);

    var summary = [
      goals ? 'Goals: ' + goals : '',
      'Use cases: ' + humanList('use_cases', useCases),
      'Audience: ' + humanList('audiences', audiences),
      'Organization: ' + humanValue('organization_type', organizationType),
      'Locations: ' + humanValue('location_count', locationCount),
      'Preferred next step: ' + humanValue('start_preference', startPreference),
      'Team size: ' + humanValue('team_size', teamSize)
    ].filter(Boolean).join(' · ');

    form.elements.message.value = summary;
  }

  async function recordPageView() {
    try {
      var params = new URLSearchParams(window.location.search || '');
      await MG.post('/api/crm/analytics/page-view.php', {
        event_type: 'page_view',
        source_page: 'learn-more',
        path: window.location.pathname,
        referrer: document.referrer || '',
        timezone_label: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        region_country: visitorCountry(),
        utm_source: params.get('utm_source') || '',
        utm_medium: params.get('utm_medium') || '',
        utm_campaign: params.get('utm_campaign') || '',
        utm_term: params.get('utm_term') || '',
        utm_content: params.get('utm_content') || '',
        screen: { width: window.screen.width, height: window.screen.height }
      });
    } catch (error) {
      // Analytics should never block the qualification form.
    }
  }

  function initLearnMoreForm() {
    var form = document.querySelector('[data-learn-more-form]');
    if (!form) return;

    applyTrackingFields(form);

    form.addEventListener('change', function (event) {
      var group = event.target.closest('[data-lm-required-group]');
      if (!group) return;
      var name = group.dataset.lmRequiredGroup;
      if (!checkedValues(form, name).length) return;
      var error = form.querySelector('[data-lm-group-error="' + name + '"]');
      if (error) error.classList.remove('is-visible');
      group.classList.remove('has-error');
    });

    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      MG.setStatus('[data-learn-more-status]', '', '');
      if (!validateRequiredGroups(form)) return;
      if (!form.reportValidity()) return;

      prepareCrmFields(form);
      applyTrackingFields(form);

      var button = form.querySelector('[type="submit"]');
      var submitted = false;
      MG.setBusy(button, true, 'Submitting…');

      try {
        var payload = MG.readForm(form);
        payload.region_country = visitorCountry();
        await MG.post('/api/crm/leads/create.php', payload);

        submitted = true;
        MG.setBusy(button, false);
        button.textContent = 'Request received';
        button.disabled = true;
        MG.setStatus('[data-learn-more-status]', 'Thanks — your request was received.', 'success');
        MG.toast('Request submitted.', 'success');

        var complete = form.querySelector('[data-lm-complete]');
        if (complete) {
          complete.classList.add('is-visible');
          complete.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      } catch (error) {
        var message = error.message || 'Unable to submit your request right now.';
        MG.setStatus('[data-learn-more-status]', message, 'error');
        MG.toast(message, 'error');
      } finally {
        if (!submitted) MG.setBusy(button, false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initLearnMoreForm();
    recordPageView();
  });
})(window, document);

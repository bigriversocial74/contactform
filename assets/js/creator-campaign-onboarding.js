document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-creator-campaign-onboarding]');
  if (!root) return;

  var financialForm = root.querySelector('[data-onboarding-financial-form]');
  if (financialForm) {
    var preview = financialForm.querySelector('[data-financial-preview] strong');
    var currency = financialForm.querySelector('[name="currency"]');
    var budget = financialForm.querySelector('[data-financial-budget]');
    var perCreator = financialForm.querySelector('[data-financial-per-creator]');
    var flat = financialForm.querySelector('[data-financial-flat]');
    var creators = financialForm.querySelector('[data-financial-creators]');
    var money = function (value) {
      var parsed = Number.parseFloat(String(value || '0'));
      return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    };
    var update = function () {
      if (!preview) return;
      var count = Math.max(1, Number.parseInt(creators && creators.value ? creators.value : '1', 10) || 1);
      var calculated = Math.max(money(flat && flat.value), money(perCreator && perCreator.value)) * count;
      var ceiling = money(budget && budget.value) || calculated;
      preview.textContent = String(currency && currency.value ? currency.value : 'USD').toUpperCase() + ' ' + ceiling.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };
    [currency, budget, perCreator, flat, creators].forEach(function (field) {
      if (field) field.addEventListener('input', update);
    });
    update();
  }

  root.querySelectorAll('a[href^="#onboarding-step-"]').forEach(function (link) {
    link.addEventListener('click', function () {
      var target = document.querySelector(link.getAttribute('href'));
      if (!target) return;
      window.setTimeout(function () {
        var first = target.querySelector('input:not([type="hidden"]),select,textarea,button');
        if (first && typeof first.focus === 'function') first.focus({preventScroll: true});
      }, 450);
    });
  });
});

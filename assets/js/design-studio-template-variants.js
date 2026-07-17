document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-agent-design-studio]');
  if (!app) return;

  var canvas = app.querySelector('[data-design-template-canvas]');
  var headline = app.querySelector('[data-design-template-headline]');
  var copy = app.querySelector('[data-design-template-copy]');
  var qrCopy = app.querySelector('[data-design-template-qr-copy]');
  var backCopy = app.querySelector('[data-design-template-back-copy]');
  if (!canvas || !headline || !copy || !qrCopy || !backCopy) return;

  var merchantName = String(app.getAttribute('data-merchant-name') || 'your local business');
  var templates = {
    'support-local': {
      headline: 'Reward yourself.<br><em>Support local.</em>',
      copy: 'Scan to earn rewards and discover local gifts.',
      qr: 'Scan to visit our Microgifter profile.',
      back: 'Discover local gifts, rewards, and experiences from ' + merchantName + '.'
    },
    'gift-better': {
      headline: 'Give local.<br><em>Gift better.</em>',
      copy: 'Send products, services, and experiences from a business you know.',
      qr: 'Scan to explore local gifts from ' + merchantName + '.',
      back: 'Make local the easy choice with a gift from ' + merchantName + '.'
    },
    'reward-visit': {
      headline: 'Scan. Save.<br><em>Come back soon.</em>',
      copy: 'Unlock rewards, offers, and another reason to visit.',
      qr: 'Scan to view rewards and current offers.',
      back: 'Your next reward from ' + merchantName + ' starts with one scan.'
    },
    'local-favorite': {
      headline: 'Your next favorite<br><em>is right here.</em>',
      copy: 'Discover something local worth sharing, gifting, and coming back for.',
      qr: 'Scan to discover our Microgifter profile.',
      back: 'Find your next local favorite at ' + merchantName + '.'
    }
  };

  function applyTemplate(key) {
    var template = templates[key] || templates['support-local'];
    Object.keys(templates).forEach(function (name) {
      canvas.classList.remove('template-' + name);
    });
    canvas.classList.add('template-' + (templates[key] ? key : 'support-local'));
    headline.innerHTML = template.headline;
    copy.textContent = template.copy;
    qrCopy.textContent = template.qr;
    backCopy.textContent = template.back;
  }

  app.querySelectorAll('[data-design-template]').forEach(function (button) {
    button.addEventListener('click', function () {
      applyTemplate(String(button.getAttribute('data-design-template') || 'support-local'));
    });
  });

  applyTemplate('support-local');
});
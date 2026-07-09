document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var form = document.querySelector('[data-stage12-template-builder]');
  if (!form || form.querySelector('[data-reward-image-card]')) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' })[char];
    });
  }

  var card = document.createElement('section');
  card.className = 'mg-reward-media-fields mg-reward-image-card';
  card.setAttribute('data-reward-image-card', '');
  card.innerHTML = '' +
    '<span class="mg-eyebrow">Reward image</span>' +
    '<h3>Campaign display image</h3>' +
    '<p>Add an image for this reward. It appears on reward cards and in Watch / Listen campaign reward results.</p>' +
    '<label>Reward image URL<input name="reward_image_url" placeholder="https://... or /uploads/..." data-reward-image-url></label>' +
    '<label>Upload reward image<input name="reward_image_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-reward-image-file></label>' +
    '<div class="mg-reward-media-preview" data-reward-image-preview><div><strong>No image yet</strong><span>Upload or paste an image URL.</span></div></div>';

  var anchor = form.querySelector('[data-reward-media-fields]') || form.querySelector('.mg-grid-2:nth-of-type(2)');
  if (anchor && anchor.parentNode) {
    anchor.parentNode.insertBefore(card, anchor);
  } else {
    form.insertBefore(card, form.firstChild || null);
  }

  var urlInput = form.elements.reward_image_url;
  var fileInput = form.elements.reward_image_file;
  var preview = form.querySelector('[data-reward-image-preview]');

  function imageFromForm() {
    if (fileInput && fileInput.files && fileInput.files[0]) return URL.createObjectURL(fileInput.files[0]);
    return urlInput ? String(urlInput.value || '').trim() : '';
  }

  function updatePreview() {
    if (!preview) return;
    var url = imageFromForm();
    preview.innerHTML = url ? '<img src="' + esc(url) + '" alt="Reward image preview">' : '<div><strong>No image yet</strong><span>Upload or paste an image URL.</span></div>';
  }

  if (urlInput) urlInput.addEventListener('input', updatePreview);
  if (fileInput) fileInput.addEventListener('change', updatePreview);

  document.addEventListener('click', function (event) {
    var cardButton = event.target && event.target.closest ? event.target.closest('.mg-reward-card[data-id]') : null;
    if (!cardButton || !urlInput) return;
    setTimeout(function () {
      var cover = form.elements.cover_image_url ? String(form.elements.cover_image_url.value || '').trim() : '';
      if (!urlInput.value && cover) urlInput.value = cover;
      updatePreview();
    }, 80);
  });



  function csrfToken() {
    return window.Microgifter && typeof Microgifter.getCsrfToken === 'function' ? Microgifter.getCsrfToken() : '';
  }

  async function syncRewardImage(templateId) {
    var url = urlInput ? String(urlInput.value || '').trim() : '';
    var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if (!templateId || (!url && !file) || !window.Microgifter || typeof Microgifter.post !== 'function') return;
    var body = new FormData();
    body.append('csrf_token', csrfToken());
    body.append('template_id', templateId);
    if (url) body.append('reward_image_url', url);
    if (file) body.append('reward_image_file', file);
    try {
      var response = await window.Microgifter.post('/api/merchant/reward-template-image.php', body);
      var data = response.data || response;
      if (data.reward_image_url && urlInput) urlInput.value = data.reward_image_url;
      updatePreview();
    } catch (error) {
      var status = form.querySelector('[data-stage12-template-status]');
      if (status) status.textContent = error.message || 'Reward saved, but image could not be saved.';
    }
  }

  function installPostBridge() {
    if (!window.Microgifter || typeof window.Microgifter.post !== 'function' || window.Microgifter.__rewardImageBridge) return;
    var originalPost = window.Microgifter.post.bind(window.Microgifter);
    window.Microgifter.__rewardImageBridge = true;
    window.Microgifter.post = async function (url, payload) {
      var response = await originalPost.apply(window.Microgifter, arguments);
      if (String(url || '').indexOf('/api/merchant/reward-templates.php') !== -1) {
        var data = response.data || response || {};
        var template = data.template || (data.data && data.data.template) || {};
        await syncRewardImage(template.id || template.public_id || (form.elements.template_id ? form.elements.template_id.value : ''));
      }
      return response;
    };
  }

  installPostBridge();

  var newButton = form.querySelector('[data-stage12-template-new]');
  if (newButton) newButton.addEventListener('click', function () { setTimeout(updatePreview, 40); });
  updatePreview();
});

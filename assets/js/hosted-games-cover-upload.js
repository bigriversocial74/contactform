(() => {
  'use strict';

  const root = document.querySelector('[data-admin-hosted-games], [data-merchant-hosted-games]');
  if (!root) return;

  const isAdmin = root.matches('[data-admin-hosted-games]');
  const form = root.querySelector(isAdmin ? '[data-hgm-admin-game-form]' : '[data-hgm-identity-form]');
  const modal = root.querySelector(isAdmin ? '[data-hgm-admin-modal]' : '[data-hgm-modal]');
  const uploader = root.querySelector('[data-hgm-cover-uploader]');
  const fileInput = uploader?.querySelector('[data-hgm-cover-file]');
  const uploadButton = uploader?.querySelector('[data-hgm-cover-upload]');
  const preview = uploader?.querySelector('[data-hgm-cover-preview]');
  const previewImage = preview?.querySelector('img');
  const previewEmpty = preview?.querySelector('span');
  const progress = uploader?.querySelector('[data-hgm-cover-progress]');
  const status = uploader?.querySelector('[data-hgm-cover-status]');
  if (!form || !uploader || !fileInput || !uploadButton || !previewImage || !previewEmpty || !progress || !status) return;

  const csrf = String(root.dataset.csrf || '');
  const endpoint = isAdmin ? '/api/admin/hosted-game-cover-upload.php' : '/api/merchant/hosted-game-cover-upload.php';
  let selectedFile = null;
  let localPreviewUrl = '';
  let uploading = false;

  function setStatus(message, type = '') {
    status.textContent = String(message || '');
    status.classList.toggle('is-error', type === 'error');
    status.classList.toggle('is-success', type === 'success');
  }

  function currentGameId() {
    return String(form.elements.game_id?.value || '').trim();
  }

  function currentCoverUrl() {
    return String(form.elements.cover_url?.value || '').trim();
  }

  function revokeLocalPreview() {
    if (localPreviewUrl) URL.revokeObjectURL(localPreviewUrl);
    localPreviewUrl = '';
  }

  function showPreview(url) {
    const value = String(url || '').trim();
    if (!value) {
      previewImage.hidden = true;
      previewImage.removeAttribute('src');
      previewEmpty.hidden = false;
      return;
    }
    previewImage.src = value;
    previewImage.hidden = false;
    previewEmpty.hidden = true;
  }

  function syncState(resetSelection = false) {
    if (resetSelection) {
      selectedFile = null;
      fileInput.value = '';
      revokeLocalPreview();
      progress.style.width = '0%';
    }
    if (!selectedFile) showPreview(currentCoverUrl());
    const gameId = currentGameId();
    uploadButton.disabled = uploading || !gameId || !selectedFile;
    if (!gameId && !selectedFile) setStatus('Save the game identity before uploading its cover image.');
    else if (!selectedFile && !uploading) setStatus('JPEG, PNG, or WebP · minimum 640 × 360 · maximum 10 MB.');
  }

  function selectFile(file) {
    selectedFile = file || null;
    revokeLocalPreview();
    progress.style.width = '0%';
    if (!selectedFile) {
      syncState();
      return;
    }
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(String(selectedFile.type || '').toLowerCase())) {
      selectedFile = null;
      fileInput.value = '';
      setStatus('Use a JPEG, PNG, or WebP cover image.', 'error');
      syncState();
      return;
    }
    if (Number(selectedFile.size || 0) < 1 || Number(selectedFile.size || 0) > 10485760) {
      selectedFile = null;
      fileInput.value = '';
      setStatus('The cover image must be 10 MB or smaller.', 'error');
      syncState();
      return;
    }
    localPreviewUrl = URL.createObjectURL(selectedFile);
    showPreview(localPreviewUrl);
    const sizeMb = (Number(selectedFile.size || 0) / 1048576).toFixed(1);
    setStatus(`${selectedFile.name} · ${sizeMb} MB · ready to upload.`);
    uploadButton.disabled = !currentGameId();
  }

  function upload() {
    const gameId = currentGameId();
    if (uploading) return;
    if (!gameId) {
      setStatus('Save the game identity before uploading its cover image.', 'error');
      return;
    }
    if (!selectedFile) {
      setStatus('Choose a cover image first.', 'error');
      return;
    }

    const data = new FormData();
    data.set('csrf_token', csrf);
    data.set('game_id', gameId);
    data.set('cover_image', selectedFile, selectedFile.name);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', endpoint);
    xhr.responseType = 'json';
    xhr.upload.addEventListener('progress', (event) => {
      if (!event.lengthComputable) return;
      progress.style.width = `${Math.round((event.loaded / event.total) * 100)}%`;
    });
    xhr.addEventListener('load', () => {
      const payload = xhr.response || {};
      const responseData = payload && typeof payload.data === 'object' ? payload.data : payload;
      if (xhr.status < 200 || xhr.status >= 300 || payload.ok === false) {
        uploading = false;
        uploadButton.disabled = false;
        setStatus(String(payload.message || responseData.message || 'Unable to upload the cover image.'), 'error');
        return;
      }
      const cover = responseData.cover || {};
      const coverUrl = String(cover.cover_url || '');
      if (form.elements.cover_url && coverUrl) form.elements.cover_url.value = coverUrl;
      revokeLocalPreview();
      selectedFile = null;
      fileInput.value = '';
      progress.style.width = '100%';
      showPreview(coverUrl);
      setStatus(`Cover uploaded · ${Number(cover.width || 0)} × ${Number(cover.height || 0)}.`, 'success');
      window.setTimeout(() => window.location.reload(), 650);
    });
    xhr.addEventListener('error', () => {
      uploading = false;
      uploadButton.disabled = false;
      setStatus('Unable to upload the cover image.', 'error');
    });
    xhr.addEventListener('abort', () => {
      uploading = false;
      uploadButton.disabled = false;
      setStatus('Cover image upload cancelled.', 'error');
    });

    uploading = true;
    uploadButton.disabled = true;
    progress.style.width = '0%';
    setStatus('Uploading and validating cover image…');
    xhr.send(data);
  }

  fileInput.addEventListener('change', () => selectFile(fileInput.files?.[0] || null));
  uploadButton.addEventListener('click', upload);
  form.elements.cover_url?.addEventListener('input', () => {
    if (!selectedFile) showPreview(currentCoverUrl());
  });

  root.addEventListener('click', (event) => {
    const opensAdmin = event.target.closest('[data-hgm-admin-create], [data-admin-game]');
    const opensMerchant = event.target.closest('[data-hgm-create], [data-hgm-action="edit"]');
    if (opensAdmin || opensMerchant) window.setTimeout(() => syncState(true), 0);
  }, true);

  const observer = new MutationObserver(() => {
    if (modal && !modal.hidden) syncState(false);
  });
  if (modal) observer.observe(modal, { attributes: true, attributeFilter: ['hidden'] });

  window.addEventListener('beforeunload', revokeLocalPreview);
  syncState(true);
})();

(() => {
  const app = document.querySelector('[data-design-studio-app]');
  if (!app) return;

  const qs = (selector, root = app) => root.querySelector(selector);
  const qsa = (selector, root = app) => Array.from(root.querySelectorAll(selector));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const qrApi = app.dataset.qrApi || '/api/merchant/qr-library.php';

  const template = qs('[data-design-template]');
  const formatLabel = qs('[data-preview-format-label]');
  const previewSize = qs('[data-preview-size]');
  const exportFormat = qs('[data-export-format]');
  const exportQr = qs('[data-export-qr]');
  const templateHeadline = qs('[data-template-headline]');
  const templateOffer = qs('[data-template-offer]');
  const templateCta = qs('[data-template-cta]');
  const templateQrLabel = qs('[data-template-qr-label]');
  const templateQrKind = qs('[data-template-qr-kind]');
  const templateQrPayload = qs('[data-template-qr-payload]');
  const templateMedia = qs('[data-template-media]');
  const proofEstimate = qs('[data-proof-estimate]');
  const qrLibrary = qs('[data-qr-library]');

  const setActive = (button, selector) => {
    qsa(selector).forEach((item) => {
      const active = item === button;
      item.classList.toggle('is-active', active);
      if (item.hasAttribute('aria-selected')) {
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      }
    });
  };

  const qrFallback = () => ({
    id: 'fallback-featured-gift',
    label: 'Featured Gift',
    kind_label: 'Claim QR',
    qr_type: 'claim',
    status: 'draft',
    destination_url: '/store.php',
    qr_payload_url: '/qr.php?c=preview',
    scan_count: 0,
  });

  const selectQr = (button, item) => {
    if (button) setActive(button, '[data-qr-label]');
    if (templateQrLabel) templateQrLabel.textContent = item.label || 'Featured Gift';
    if (templateQrKind) templateQrKind.textContent = item.kind_label || 'Claim QR';
    if (templateQrPayload) templateQrPayload.textContent = item.qr_payload_url || 'QR payload pending';
    if (exportQr) exportQr.textContent = `${item.label || 'Featured Gift'} · ${item.kind_label || 'QR Code'}`;
    app.dataset.selectedQrId = item.id || '';
  };

  const renderQrLibrary = (items) => {
    if (!qrLibrary) return;
    const list = Array.isArray(items) && items.length ? items : [qrFallback()];
    qrLibrary.innerHTML = '';
    list.forEach((item, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.qrLabel = item.label || 'Untitled QR';
      button.dataset.qrKind = item.kind_label || 'QR Code';
      button.dataset.qrId = item.id || '';
      button.dataset.qrPayload = item.qr_payload_url || '';
      if (index === 0) button.classList.add('is-active');
      const status = item.status || 'draft';
      const scans = Number(item.scan_count || 0);
      button.innerHTML = `<strong></strong><span></span>`;
      button.querySelector('strong').textContent = item.label || 'Untitled QR';
      button.querySelector('span').textContent = `${item.kind_label || 'QR Code'} · ${status}${scans ? ` · ${scans} scans` : ''}`;
      button.addEventListener('click', () => selectQr(button, item));
      qrLibrary.appendChild(button);
      if (index === 0) selectQr(button, item);
    });
  };

  const loadQrLibrary = async () => {
    if (!qrLibrary || app.dataset.merchantOnly !== 'true') return;
    qrLibrary.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(`${qrApi}?status=open`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to load QR library.');
      renderQrLibrary(payload.data?.items || []);
    } catch (error) {
      renderQrLibrary([]);
      if (proofEstimate) proofEstimate.textContent = 'QR library offline';
    } finally {
      qrLibrary.removeAttribute('aria-busy');
    }
  };

  const currentDefaultDestination = () => {
    const current = templateQrPayload?.textContent?.trim();
    if (current && current !== 'QR payload pending') return current;
    return app.dataset.defaultQrDestination || '/store.php';
  };

  const createQr = async () => {
    const activeFormat = qs('[data-format].is-active');
    const label = `${activeFormat?.dataset.title || 'Design Studio'} QR`;
    const destination = app.dataset.defaultQrDestination || '/store.php';
    const body = {
      action: 'create',
      csrf_token: csrf,
      label,
      qr_type: 'claim',
      status: 'active',
      destination_url: destination,
      metadata: {
        source: 'design_studio',
        format: activeFormat?.dataset.format || 'table-tent',
        preview_destination: currentDefaultDestination(),
      },
    };
    const response = await fetch(qrApi, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(body),
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'Unable to create QR code.');
    await loadQrLibrary();
    return payload.data?.item;
  };

  qsa('[data-format]').forEach((button) => {
    button.addEventListener('click', () => {
      const title = button.dataset.title || button.textContent.trim();
      const size = button.dataset.size || '';
      const ratio = button.dataset.ratio || 'portrait';

      setActive(button, '[data-format]');
      if (formatLabel) formatLabel.textContent = title;
      if (previewSize) previewSize.textContent = size;
      if (exportFormat) exportFormat.textContent = `${title} · ${size}`;
      if (template) {
        template.dataset.ratio = ratio;
        template.className = `mg-design-template is-${button.dataset.format || 'custom'}`;
      }
      if (proofEstimate) proofEstimate.textContent = `${title} proof ready`;
    });
  });

  qsa('[data-design-field]').forEach((field) => {
    const sync = () => {
      const value = field.value.trim();
      if (field.dataset.designField === 'headline' && templateHeadline) {
        templateHeadline.textContent = value || 'Give local. Claim instantly.';
      }
      if (field.dataset.designField === 'offer' && templateOffer) {
        templateOffer.textContent = value || 'Scan to unlock today’s featured microgift, local reward, or pre-sale offer.';
      }
      if (field.dataset.designField === 'cta' && templateCta) {
        templateCta.textContent = value || 'Scan to claim your reward';
      }
    };
    field.addEventListener('input', sync);
    sync();
  });

  qsa('[data-media-swatch]').forEach((button) => {
    button.addEventListener('click', () => {
      setActive(button, '[data-media-swatch]');
      if (templateMedia) {
        templateMedia.dataset.swatch = button.dataset.mediaSwatch || 'gradient';
      }
    });
  });

  qsa('[data-qr-label]').forEach((button) => {
    button.addEventListener('click', () => {
      selectQr(button, {
        id: button.dataset.qrId || '',
        label: button.dataset.qrLabel || 'Featured Gift',
        kind_label: button.dataset.qrKind || 'Claim QR',
        qr_payload_url: button.dataset.qrPayload || '',
      });
    });
  });

  qsa('[data-preview-side]').forEach((button) => {
    button.addEventListener('click', () => {
      setActive(button, '[data-preview-side]');
      const side = button.dataset.previewSide || 'front';
      app.dataset.previewSide = side;
      if (proofEstimate) proofEstimate.textContent = `${side === 'back' ? 'Back' : 'Front'} side preview`;
    });
  });

  const saveButton = qs('[data-design-save]');
  if (saveButton) {
    saveButton.addEventListener('click', () => {
      saveButton.textContent = 'Draft saved';
      saveButton.classList.add('is-saved');
      setTimeout(() => {
        saveButton.textContent = 'Save draft';
        saveButton.classList.remove('is-saved');
      }, 1400);
    });
  }

  const exportButton = qs('[data-design-export]');
  if (exportButton) {
    exportButton.addEventListener('click', () => {
      if (proofEstimate) {
        proofEstimate.textContent = app.dataset.selectedQrId ? 'Export queued with live QR' : 'Choose or create a QR first';
      }
      exportButton.textContent = app.dataset.selectedQrId ? 'Package queued' : 'QR required';
      setTimeout(() => {
        exportButton.textContent = 'Export print package';
      }, 1600);
    });
  }

  const proofButton = qs('[data-design-proof]');
  if (proofButton) {
    proofButton.addEventListener('click', () => {
      if (proofEstimate) {
        proofEstimate.textContent = app.dataset.selectedQrId ? 'Proof generated with live QR' : 'QR required for proof';
      }
      proofButton.textContent = app.dataset.selectedQrId ? 'Proof refreshed' : 'Create/select QR';
      setTimeout(() => {
        proofButton.textContent = 'Generate proof';
      }, 1500);
    });
  }

  const importButton = qs('[data-design-import-media]');
  if (importButton) {
    importButton.addEventListener('click', () => {
      importButton.textContent = 'Media library sync pending';
      setTimeout(() => {
        importButton.textContent = 'Import from media library';
      }, 1600);
    });
  }

  const qrButton = qs('[data-design-create-qr]');
  if (qrButton) {
    qrButton.addEventListener('click', async () => {
      if (app.dataset.merchantOnly !== 'true') return;
      qrButton.disabled = true;
      qrButton.textContent = 'Creating QR code…';
      try {
        const item = await createQr();
        if (item && proofEstimate) proofEstimate.textContent = 'Live QR code created';
      } catch (error) {
        if (proofEstimate) proofEstimate.textContent = 'Unable to create QR';
      } finally {
        qrButton.disabled = false;
        qrButton.textContent = 'Create new QR code';
      }
    });
  }

  const fitButton = qs('[data-preview-fit]');
  if (fitButton) {
    fitButton.addEventListener('click', () => {
      const stage = qs('.mg-design-canvas-stage');
      if (stage) {
        stage.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
      }
    });
  }

  loadQrLibrary();
})();

(() => {
  const app = document.querySelector('[data-design-studio-app]');
  if (!app) return;

  const qs = (selector, root = app) => root.querySelector(selector);
  const qsa = (selector, root = app) => Array.from(root.querySelectorAll(selector));

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
  const templateMedia = qs('[data-template-media]');
  const proofEstimate = qs('[data-proof-estimate]');

  const setActive = (button, selector) => {
    qsa(selector).forEach((item) => {
      const active = item === button;
      item.classList.toggle('is-active', active);
      if (item.hasAttribute('aria-selected')) {
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      }
    });
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
      const label = button.dataset.qrLabel || 'Featured Gift';
      const kind = button.dataset.qrKind || 'Claim QR';

      setActive(button, '[data-qr-label]');
      if (templateQrLabel) templateQrLabel.textContent = label;
      if (templateQrKind) templateQrKind.textContent = kind;
      if (exportQr) exportQr.textContent = `${label} · ${kind}`;
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
        proofEstimate.textContent = 'Export queued';
      }
      exportButton.textContent = 'Package queued';
      setTimeout(() => {
        exportButton.textContent = 'Export print package';
      }, 1600);
    });
  }

  const proofButton = qs('[data-design-proof]');
  if (proofButton) {
    proofButton.addEventListener('click', () => {
      if (proofEstimate) {
        proofEstimate.textContent = 'Proof generated';
      }
      proofButton.textContent = 'Proof refreshed';
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
    qrButton.addEventListener('click', () => {
      qrButton.textContent = 'QR builder pending';
      setTimeout(() => {
        qrButton.textContent = 'Create new QR code';
      }, 1600);
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
})();

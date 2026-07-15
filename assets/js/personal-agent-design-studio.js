(() => {
  const app = document.querySelector('[data-agent-design-studio]');
  if (!app) return;

  const canvas = app.querySelector('[data-design-canvas]');
  const qrTarget = app.querySelector('[data-design-qr]');
  const formatLabel = app.querySelector('[data-design-format-label]');
  const destinationInput = app.querySelector('[data-design-field="destination"]');
  const buttons = Array.from(app.querySelectorAll('[data-design-format]'));
  const downloadButtons = Array.from(app.querySelectorAll('[data-design-download]'));

  const loadScript = (src, test) => new Promise((resolve, reject) => {
    if (test()) return resolve();
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      existing.addEventListener('load', resolve, { once: true });
      existing.addEventListener('error', reject, { once: true });
      return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = resolve;
    script.onerror = reject;
    document.head.appendChild(script);
  });

  const ensureLibraries = async () => {
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', () => typeof window.QRCode === 'function');
    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', () => typeof window.html2canvas === 'function');
  };

  const safeDestination = () => {
    const raw = (destinationInput?.value || app.dataset.defaultDestination || '/profile.php').trim();
    try {
      return new URL(raw, window.location.origin).href;
    } catch (_) {
      return new URL('/profile.php', window.location.origin).href;
    }
  };

  const renderQr = async () => {
    if (!qrTarget) return;
    await ensureLibraries();
    qrTarget.innerHTML = '';
    new window.QRCode(qrTarget, {
      text: safeDestination(),
      width: 220,
      height: 220,
      colorDark: '#10213b',
      colorLight: '#ffffff',
      correctLevel: window.QRCode.CorrectLevel.H,
    });
  };

  const syncPreview = (field) => {
    const source = app.querySelector(`[data-design-field="${field}"]`);
    const value = source?.value?.trim() || '';
    app.querySelectorAll(`[data-design-preview="${field}"]`).forEach((node) => {
      if (field === 'headline') {
        const parts = value.split(/\s+(?=Support local\.?$)/i);
        node.innerHTML = parts.length > 1
          ? `${parts[0]}<br><em>${parts.slice(1).join(' ')}</em>`
          : value.replace(/Support local\.?/i, '<em>$&</em>');
      } else {
        node.textContent = value;
      }
    });
  };

  ['merchant', 'headline', 'support'].forEach((field) => {
    const input = app.querySelector(`[data-design-field="${field}"]`);
    input?.addEventListener('input', () => syncPreview(field));
  });

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      buttons.forEach((item) => item.classList.toggle('is-active', item === button));
      const format = button.dataset.designFormat || 'poster';
      canvas?.classList.toggle('is-poster', format === 'poster');
      canvas?.classList.toggle('is-tent', format === 'tent');
      if (formatLabel) formatLabel.textContent = format === 'tent' ? 'Table Tent' : '5 × 5 Poster Card';
    });
  });

  app.querySelector('[data-design-generate-qr]')?.addEventListener('click', async () => {
    try {
      await renderQr();
    } catch (error) {
      window.alert('The QR code could not be generated. Please check your connection and try again.');
    }
  });

  destinationInput?.addEventListener('change', () => {
    renderQr().catch(() => {});
  });

  const downloadJpg = async () => {
    if (!canvas) return;
    await ensureLibraries();
    await renderQr();
    const result = await window.html2canvas(canvas, {
      backgroundColor: '#eef3f8',
      scale: 3,
      useCORS: true,
      logging: false,
    });
    const link = document.createElement('a');
    const format = canvas.classList.contains('is-tent') ? 'table-tent' : '5x5-poster';
    link.download = `microgifter-${format}.jpg`;
    link.href = result.toDataURL('image/jpeg', 0.96);
    link.click();
  };

  downloadButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const original = button.textContent;
      button.disabled = true;
      button.textContent = 'Preparing JPG…';
      try {
        await downloadJpg();
      } catch (error) {
        window.alert('The JPG export could not be completed. Please try again.');
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    });
  });

  renderQr().catch(() => {});
})();
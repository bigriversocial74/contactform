(() => {
  const root = document.querySelector('.mg-home-page');
  if (!root) return;

  const money = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(value);

  document.querySelectorAll('.mg-header-phone, .mg-public-mobile-phone').forEach((phone) => {
    phone.textContent = '(480) 269-7433';
    phone.href = 'tel:14802697433';
    phone.setAttribute('aria-label', 'Call Microgifter at (480) 269-7433');
  });
  document.querySelectorAll('.mg-phone-equation, .mg-public-mobile-equation').forEach((node) => node.remove());
  document.querySelectorAll('.mg-header-social-link, .mg-public-mobile-socials a').forEach((link) => {
    if (link.textContent.trim().toUpperCase() === 'IN') link.remove();
  });

  const panelByTitle = (title) => Array.from(root.querySelectorAll('.mg-panel h3')).find((node) => node.textContent.trim().toLowerCase() === title.toLowerCase())?.closest('.mg-panel') || null;
  const row = (name, value) => {
    const item = document.createElement('div');
    item.className = 'mg-signal-row';
    const left = document.createElement('span');
    const right = document.createElement('span');
    left.textContent = name;
    right.textContent = value;
    item.append(left, right);
    return item;
  };
  const replaceGraphic = (title, label, rows) => {
    const panel = panelByTitle(title);
    if (!panel) return;
    const graphic = panel.querySelector('.mg-codebox, .mg-value-score, .mg-mini-path, .mg-signal-list');
    if (!graphic) return;
    graphic.className = 'mg-signal-list';
    graphic.setAttribute('aria-label', label);
    graphic.replaceChildren(...rows.map((item) => row(item[0], item[1])));
  };

  replaceGraphic('Ecommerce', 'Ecommerce storefront preview', [
    ['Featured product', 'Coffee for Two'],
    ['Checkout option', 'Cash or card'],
    ['Delivery', 'Wallet item'],
    ['Next action', 'Buy · Save · Send'],
  ]);
  replaceGraphic('Customer CRM', 'Customer CRM workflow preview', [
    ['New customer', 'Profile created'],
    ['First action', 'Wallet claim'],
    ['Campaign source', 'Tracked'],
    ['Follow-up', 'Message ready'],
  ]);
  replaceGraphic('Workplace Rewards', 'Workplace rewards workflow preview', [
    ['Sponsor', 'Company wallet'],
    ['Audience', 'Team members'],
    ['Reward type', 'Local gift'],
    ['Redemption', 'Merchant verified'],
  ]);

  const bottomDemo = root.querySelector('.mg-public-bottom-demo');
  if (bottomDemo && !root.querySelector('[data-sales-estimator]')) {
    const section = document.createElement('section');
    section.className = 'mg-section mg-sales-estimator';
    section.setAttribute('data-sales-estimator', '');
    section.setAttribute('aria-labelledby', 'mgSalesEstimatorTitle');

    const mesh = document.createElement('div');
    mesh.className = 'mg-bg-mesh';
    mesh.setAttribute('aria-hidden', 'true');

    const container = document.createElement('div');
    container.className = 'mg-container';

    const grid = document.createElement('div');
    grid.className = 'mg-sales-estimator-grid';

    const copy = document.createElement('div');
    copy.className = 'mg-story-copy';
    copy.setAttribute('data-reveal', 'left');
    const kicker = document.createElement('span');
    kicker.className = 'mg-story-kicker';
    kicker.textContent = 'Estimated sales lift';
    const title = document.createElement('h2');
    title.id = 'mgSalesEstimatorTitle';
    title.textContent = 'Estimated Sales Increase with Microgifter';
    const desc = document.createElement('p');
    desc.textContent = 'Adjust average product price and estimated monthly products sold to preview the additional monthly sales volume a Microgifter campaign can create.';
    copy.append(kicker, title, desc);

    const calc = document.createElement('div');
    calc.className = 'mg-sales-calculator';
    calc.setAttribute('data-reveal', 'up');

    const total = document.createElement('div');
    total.className = 'mg-sales-total';
    const totalLabel = document.createElement('span');
    totalLabel.textContent = 'Estimated monthly sales increase';
    const totalValue = document.createElement('strong');
    total.append(totalLabel, totalValue);

    const priceLabel = document.createElement('label');
    priceLabel.className = 'mg-sales-slider';
    const priceText = document.createElement('span');
    const priceOut = document.createElement('b');
    priceText.append('Average product price ', priceOut);
    const price = document.createElement('input');
    price.type = 'range'; price.min = '5'; price.max = '250'; price.step = '5'; price.value = '25';
    priceLabel.append(priceText, price);

    const volumeLabel = document.createElement('label');
    volumeLabel.className = 'mg-sales-slider';
    const volumeText = document.createElement('span');
    const volumeOut = document.createElement('b');
    volumeText.append('Estimated products sold ', volumeOut);
    const volume = document.createElement('input');
    volume.type = 'range'; volume.min = '10'; volume.max = '2500'; volume.step = '10'; volume.value = '100';
    volumeLabel.append(volumeText, volume);

    const formula = document.createElement('div');
    formula.className = 'mg-sales-formula';
    const update = () => {
      const priceValue = Number(price.value || 0);
      const volumeValue = Number(volume.value || 0);
      const monthly = priceValue * volumeValue;
      priceOut.textContent = money(priceValue);
      volumeOut.textContent = volumeValue.toLocaleString('en-US');
      totalValue.textContent = money(monthly);
      formula.textContent = money(priceValue) + ' × ' + volumeValue.toLocaleString('en-US') + ' products = ' + money(monthly) + '/mo';
    };
    price.addEventListener('input', update);
    volume.addEventListener('input', update);
    update();

    calc.append(total, priceLabel, volumeLabel, formula);
    grid.append(copy, calc);
    container.append(grid);
    section.append(mesh, container);
    bottomDemo.parentNode.insertBefore(section, bottomDemo);
  }
})();

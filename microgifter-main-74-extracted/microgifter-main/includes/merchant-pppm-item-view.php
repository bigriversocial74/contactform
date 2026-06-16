<?php declare(strict_types=1); $itemId = trim((string)($_GET['id'] ?? '')); ?>
<section class="mg-merchant-heading">
  <div>
    <span class="mg-eyebrow">Loaded PPPM item</span>
    <h1 data-item-title>Loading item</h1>
    <p data-item-subtitle><?= mg_e($itemId) ?></p>
  </div>
  <a class="mg-btn mg-btn-soft" href="/merchant-pppm.php">Back to operations</a>
</section>
<div class="mg-pppm-detail" data-pppm-detail data-item-id="<?= mg_e($itemId) ?>"></div>

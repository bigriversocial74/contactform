<?php
declare(strict_types=1);
$ledger = is_array($ledger ?? null) ? $ledger : [];
?>
<div class="mg-stamp-ledger-table-wrap">
  <table class="mg-stamp-table mg-stamp-ledger-table">
    <thead>
      <tr>
        <th>Posted</th>
        <th>Type</th>
        <th>Ledger item</th>
        <th>Reference</th>
        <th>Delta</th>
        <th>Balance</th>
        <th>Actor</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ledger as $entry): ?>
        <?php $delta = (int)($entry['delta'] ?? 0); ?>
        <tr>
          <td><?= mg_e((string)($entry['posted_at'] ?? '')) ?></td>
          <td><span class="mg-stamp-ledger-type is-<?= $delta >= 0 ? 'credit' : 'debit' ?>"><?= mg_e((string)($entry['type'] ?? 'entry')) ?></span></td>
          <td><strong><?= mg_e((string)($entry['label'] ?? 'Stamp ledger entry')) ?></strong></td>
          <td><?= mg_e((string)($entry['reference'] ?? '')) ?></td>
          <td class="mg-stamp-delta <?= $delta >= 0 ? 'is-credit' : 'is-debit' ?>"><?= $delta >= 0 ? '+' : '' ?><?= number_format($delta) ?></td>
          <td><?= number_format((int)($entry['balance'] ?? 0)) ?></td>
          <td><?= mg_e((string)($entry['actor'] ?? 'System')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

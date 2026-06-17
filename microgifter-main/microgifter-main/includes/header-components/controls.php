<?php
declare(strict_types=1);
$header_controls = is_array($header_controls ?? null) ? $header_controls : [];
?>
<?php if ($header_controls): ?>
<div class="mg-header-page-controls" data-header-page-controls>
  <?php foreach ($header_controls as $control): ?>
    <?php
      if (!is_array($control)) { continue; }
      $type = (string) ($control['type'] ?? 'link');
      $label = (string) ($control['label'] ?? 'Action');
      $href = (string) ($control['href'] ?? '#');
      $class = 'mg-header-page-control' . (!empty($control['primary']) ? ' is-primary' : '');
    ?>
    <?php if ($type === 'presentation'): ?>
      <button class="<?= mg_e($class) ?>" type="button" data-agent-presentation-control data-state="idle" aria-label="Play agent presentation"><span data-agent-control-icon>▶</span><span data-agent-control-label><?= mg_e($label) ?></span></button>
    <?php else: ?>
      <a class="<?= mg_e($class) ?>" href="<?= mg_e($href) ?>"><?= mg_e($label) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

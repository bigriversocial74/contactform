<?php
declare(strict_types=1);
?>
<link rel="stylesheet" href="/assets/css/create-center-inline.css">
<div class="mg-post-composer-modal" id="mg-post-composer-modal" data-global-post-composer hidden aria-hidden="true">
  <button class="mg-post-composer-backdrop" type="button" data-global-post-composer-close aria-label="Close post composer"></button>
  <section class="mg-post-composer-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-feed-composer-title-global" tabindex="-1">
    <button class="mg-post-composer-x" type="button" data-global-post-composer-close aria-label="Close post composer">×</button>
    <?php
    $post_composer_id_suffix = 'global';
    $post_composer_hidden = false;
    require dirname(__DIR__) . '/social-feed-composer.php';
    ?>
  </section>
</div>
<script src="/assets/js/create-center-inline.js" defer></script>
<script src="/assets/js/create-center-storefront-preserve.js" defer></script>
<script src="/assets/js/create-center-post-success.js" defer></script>

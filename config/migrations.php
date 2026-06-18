<?php
declare(strict_types=1);

/**
 * Canonical migration manifest assembled from the stable baseline and ordered
 * post-baseline extensions.
 */
$manifest = require __DIR__ . '/migrations.base.php';
$extensions = require __DIR__ . '/migrations.extensions.php';

if (!is_array($manifest) || !is_array($extensions)) {
    throw new RuntimeException('Invalid migration manifest configuration.');
}
foreach ($extensions as $file) {
    $file = trim((string)$file);
    if ($file !== '' && !in_array($file, $manifest['ordered_files'], true)) {
        $manifest['ordered_files'][] = $file;
    }
}

return $manifest;

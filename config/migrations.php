<?php
declare(strict_types=1);

/**
 * Canonical Microgifter migration manifest.
 *
 * The base snapshot preserves the latest integration manifest while this
 * scoped migration is appended without overwriting concurrently merged entries.
 */
$manifest = require __DIR__ . '/migrations-base-20260719.php';
$manifest['ordered_files'][] = '20260718_locations_multi_claim_code_safeguards.sql';
return $manifest;

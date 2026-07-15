<?php
declare(strict_types=1);

/**
 * Canonical Microgifter migration manifest with scoped ordered extensions.
 */
$manifest = require __DIR__ . '/migrations-base.php';
$ordered = array_values($manifest['ordered_files'] ?? []);
$after = array_search('20260714_personal_gifting_agent_phase2.sql', $ordered, true);
if ($after === false) {
    throw new RuntimeException('Personal Gifting Agent Phase 2 migration is missing from the canonical manifest.');
}
array_splice($ordered, $after + 1, 0, ['stage_19d_customer_haiku_merchant_sonnet_defaults.sql']);
$manifest['ordered_files'] = $ordered;
return $manifest;

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/location-data.php';

mg_require_method('GET');

try {
    $counts = mg_location_merchant_state_counts(mg_db());
    mg_ok([
        'counts' => $counts,
        'total' => array_sum($counts),
        'states' => mg_location_states(),
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'locations.counts_failed', 'Unable to load public location counts.', [
        'exception_class' => $error::class,
    ]);
    mg_fail('Location counts unavailable.', 500);
}

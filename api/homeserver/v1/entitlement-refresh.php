<?php
declare(strict_types=1);
require_once __DIR__ . '/_contract.php';
mg_hs_v1_require_route('/api/homeserver/v1/entitlements/refresh');
mg_require_method('POST');
mg_hs_v1_refresh_entitlement();

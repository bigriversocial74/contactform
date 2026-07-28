<?php
declare(strict_types=1);
require_once __DIR__ . '/_contract.php';
mg_hs_v1_require_route('/api/homeserver/v1/devices/replacements/start');
mg_require_method('POST');
mg_hs_v1_replacement_start();

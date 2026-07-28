<?php
declare(strict_types=1);
require_once __DIR__ . '/_contract.php';
mg_hs_v1_require_route('/api/homeserver/v1/pairing/exchange');
mg_hs_v1_pairing_exchange(mg_homeserver_input());

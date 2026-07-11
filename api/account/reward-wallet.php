<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
mg_require_api_user();
mg_ok([
    'deprecated_wallet_ui'=>true,
    'redirect_url'=>'/inbox.php',
    'rewards'=>[],
    'totals'=>[],
    'pagination'=>['page'=>1,'limit'=>0,'total'=>0,'has_more'=>false],
], 'Rewards are delivered to and managed from your Microgifter Inbox.');

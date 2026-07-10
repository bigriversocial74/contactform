<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};
$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

$page = $read('loyalty-cards.php');
$stamp = $read('stamp-card.php');
$api = $read('api/account/loyalty-cards.php');
$campaigns = $read('api/merchant/campaigns.php');
$artwork = $read('assets/js/stage12-campaign-media-artwork.js');
$client = $read('assets/js/loyalty-cards.js');
$sidebar = $read('includes/app-sidebar.php');
$footer = $read('includes/footer.php');
$sql = $read('database/customer_saved_campaign_cards_20260709.sql');

$assert('Loyalty Cards page exists', $page !== '' && str_contains($page, 'data-loyalty-cards-page'));
$assert('Stamp page renders save control', str_contains($stamp, 'data-loyalty-save-toggle') && str_contains($stamp, 'data-campaign-id'));
$assert('Loyalty assets are page scoped',
    str_contains($stamp, '/assets/css/loyalty-cards.css')
    && str_contains($stamp, '/assets/js/loyalty-cards.js')
    && !str_contains($footer, "'/assets/css/loyalty-cards.css'")
    && !str_contains($footer, "'/assets/js/loyalty-cards.js'")
);
$assert('Utility sidebar renders Loyalty Cards', str_contains($sidebar, "'loyalty-cards'") && str_contains($sidebar, '/loyalty-cards.php'));
$assert('Client binds existing save control', str_contains($client, "document.querySelector('[data-loyalty-save-toggle]')") && !str_contains($client, 'injectSidebarLink'));
$assert('Saved card API supports authenticated list and toggle',
    str_contains($api, 'mg_require_api_user')
    && str_contains($api, "['save', 'unsave', 'toggle']")
    && str_contains($api, 'mg_require_csrf_for_write')
);
$assert('Saved card list avoids duplicate contact joins',
    !str_contains($api, 'LEFT JOIN campaign_contacts cc ON')
    && str_contains($api, 'INNER JOIN campaign_contacts ccx')
);
$assert('Campaign image is preferred before fallbacks',
    str_contains($api, "'stamp_card_image_url'")
    && str_contains($api, "'media_image_url'")
    && str_contains($api, 'merchant_profile_cover_url')
);
$assert('Stamp campaign artwork can be uploaded',
    str_contains($artwork, 'stamp_card_image_asset_id')
    && str_contains($artwork, 'stamp_card_image_url')
);
$assert('Stamp campaign artwork is persisted',
    str_contains($campaigns, "'stamp_card_image_asset_id'")
    && str_contains($campaigns, "'stamp_card_image_url'")
    && str_contains($campaigns, 'mg_campaign_media_image_rules')
);
$assert('Saved cards migration is unique per user and campaign',
    str_contains($sql, 'customer_saved_campaign_cards')
    && str_contains($sql, 'UNIQUE KEY uq_customer_saved_campaign_cards_user_campaign (user_id,campaign_id)')
);

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}
if ($failures) {
    echo PHP_EOL . 'Loyalty Saved Cards v1 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}
echo PHP_EOL . 'Loyalty Saved Cards v1 validation passed.' . PHP_EOL;

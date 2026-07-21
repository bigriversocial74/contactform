<?php
declare(strict_types=1);
require_once __DIR__ . '/change-gift.php';
require_once __DIR__ . '/change-campaign.php';
require_once __DIR__ . '/change-template.php';
require_once __DIR__ . '/change-message.php';
function phase3c_change_fixtures(array $state): void
{
    phase3c_change_gift($state);
    phase3c_change_campaign($state);
    phase3c_change_template($state);
    phase3c_change_message($state);
}

<?php
declare(strict_types=1);

/**
 * Public Loyalty Quest route.
 *
 * Campaign rendering remains centralized in campaign.php so lifecycle,
 * merchant ownership, reward, preview, and availability rules stay consistent.
 */
$ref = trim((string)(
    $_GET['campaign']
    ?? $_GET['c']
    ?? $_GET['id']
    ?? $_GET['slug']
    ?? ''
));
if ($ref !== '') {
    $_GET['c'] = $ref;
}
require __DIR__ . '/campaign.php';

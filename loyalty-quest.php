<?php
declare(strict_types=1);

/**
 * Public Loyalty Quest route.
 *
 * Campaign rendering remains centralized in campaign.php so all campaign
 * lifecycle, merchant ownership, reward, preview, and availability rules stay
 * consistent. This route provides the first-class public URL used by the
 * Loyalty Quest campaign contract.
 */
$ref = trim((string)($_GET['c'] ?? $_GET['id'] ?? $_GET['slug'] ?? ''));
if ($ref !== '') {
    $_GET['c'] = $ref;
}
require __DIR__ . '/campaign.php';

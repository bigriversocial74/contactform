<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$walletItem = strtolower(trim((string)($_GET['wallet_item_id'] ?? $_GET['wallet'] ?? '')));
$actionItem = trim((string)($_GET['item'] ?? $_GET['gift'] ?? ''));

if ($actionItem !== '') {
    header('Location: /inbox.php?item=' . rawurlencode($actionItem), true, 302);
    exit;
}

if ($walletItem !== '') {
    header('Location: /wallet.php?wallet_item_id=' . rawurlencode($walletItem), true, 302);
    exit;
}

header('Location: /wallet.php', true, 302);
exit;

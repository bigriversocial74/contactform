<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

if (!mg_current_user()) {
    header('Location: /signin.php?redirect=' . rawurlencode('/agent.php?view=design'), true, 302);
    exit;
}

header('Cache-Control: no-store, private');
header('Location: /agent.php?view=design', true, 302);
exit;

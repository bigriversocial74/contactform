<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
mg_require_auth();
header('Location: /inbox.php', true, 302);
exit;

<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/page.php';
$page_manifest = mg_page_manifest($page_manifest ?? []);
require __DIR__ . '/header.php';

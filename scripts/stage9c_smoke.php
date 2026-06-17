<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/db.php';
$pdo = mg_db();
$tables = ['microgift_claims','microgift_redemptions','microgift_lifecycle_actions'];
foreach ($tables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    if ((int) $stmt->fetchColumn() !== 1) { exit(1); }
}
echo "Stage 9C Microgift lifecycle smoke checks passed.\n";

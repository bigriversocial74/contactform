<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){exit(1);}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
$required=['entitlement_transfers','entitlement_policy_actions'];
foreach($required as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn() !== 1){fwrite(STDERR,"Missing Stage 8C table.\n");exit(1);}
}
echo "Stage 8C schema checks passed.\n";

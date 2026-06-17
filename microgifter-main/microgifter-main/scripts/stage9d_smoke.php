<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
require_once dirname(__DIR__).'/api/db.php';
$pdo=mg_db();
foreach(['microgift_review_items','microgift_daily_metrics'] as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1){fwrite(STDERR,"Missing Stage 9D table.\n");exit(1);}
}
echo "Stage 9D Microgift operations smoke checks passed.\n";

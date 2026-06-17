<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
$lifecycleSql=file_get_contents(dirname(__DIR__).'/database/stage_9c_microgift_lifecycle.sql');
if(!is_string($lifecycleSql)){exit(1);}
$pdo->exec($lifecycleSql);
$tables=['microgift_templates','microgift_template_versions','microgift_instances','microgift_credentials','microgift_events','microgift_claims','microgift_redemptions','microgift_lifecycle_actions'];
foreach($tables as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1){exit(1);}
}
echo "Stage 9B Microgift Engine smoke checks passed.\n";
echo "Stage 9C Microgift lifecycle smoke checks passed.\n";

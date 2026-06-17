<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
require_once dirname(__DIR__).'/api/db.php';
$pdo=mg_db();
$lifecycleSql=file_get_contents(dirname(__DIR__).'/database/stage_8c_entitlement_lifecycle.sql');
if(!is_string($lifecycleSql))exit(1);
$pdo->exec($lifecycleSql);
$tables=['entitlements','entitlement_events','entitlement_access_events','asset_delivery_grants','entitlement_review_items','entitlement_transfers','entitlement_policy_actions'];
try{
 foreach($tables as $table){$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$stmt->execute([$table]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing table');}
 foreach(['uq_entitlements_idempotency','uq_entitlements_active_grant','uq_asset_delivery_grants_token','uq_entitlement_transfers_idempotency','uq_entitlement_policy_actions_idempotency'] as $key){$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND index_name=?');$stmt->execute([$key]);if((int)$stmt->fetchColumn()<1)throw new RuntimeException('Missing index');}
 echo "Stage 8 lifecycle smoke checks passed.\n";
}catch(Throwable $e){fwrite(STDERR,"Stage 8 lifecycle checks failed.\n");exit(1);}

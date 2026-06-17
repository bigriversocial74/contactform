<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';

$pdo=mg_db();
$requiredColumns=['initial_payment_required','funded_at','activated_at'];
foreach($requiredColumns as $column){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'subscriptions\' AND COLUMN_NAME=?');
    $stmt->execute([$column]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 13B subscriptions column: {$column}");
}
$stmt=$pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'status'");
$status=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$status||!str_contains((string)$status['Type'],"'pending_payment'"))throw new RuntimeException('Stage 13B pending_payment status is unavailable.');
$stmt=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?');
$stmt->execute(['stage_13b_initial_subscription_funding']);
if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Stage 13B migration record is missing.');
fwrite(STDOUT,"Stage 13B initial funding smoke validation passed.\n");

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/finance/_money.php';
mg_require_method('POST');
$user=mg_require_permission('financial.reversals.manage');
$input=mg_input();mg_require_csrf_for_write($input);
$groupId=trim((string)($input['group_id']??''));$idempotency=trim((string)($input['idempotency_key']??''));$reason=trim((string)($input['reason']??''));
if($groupId===''||$idempotency===''||$reason==='')mg_fail('Group, idempotency key and reason are required.',422);
$pdo=mg_db();$pdo->beginTransaction();
try{$group=mg_ledger_reverse($pdo,$groupId,$idempotency,$reason,(int)$user['id']);$pdo->commit();mg_audit('ledger.reversed','ledger_transaction_group',['original_group_id'=>$groupId,'reversal_group_id'=>$group['public_id']??null,'reason'=>$reason],(int)$user['id']);mg_ok(['reversal_group_id'=>$group['public_id']??null],'Ledger group reversed.',201);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}

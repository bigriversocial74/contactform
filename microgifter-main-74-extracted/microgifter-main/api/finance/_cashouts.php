<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_payments.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once __DIR__ . '/_money.php';

final class MgCashoutWorkflowException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_wallet_require_owner(PDO $pdo,string $walletPublicId,int $userId): array
{
    $stmt=$pdo->prepare('SELECT * FROM wallets WHERE public_id=? AND owner_user_id=? LIMIT 1');
    $stmt->execute([$walletPublicId,$userId]);
    $wallet=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$wallet)throw new MgCashoutWorkflowException('Wallet not found.',404);
    if((string)$wallet['status']!=='active')throw new MgCashoutWorkflowException('Wallet is not active.',409);
    return $wallet;
}

function mg_cashout_assert_idempotent_request(array $existing,array $wallet,int $userId,int $amountCents): void
{
    $sameRequest=(int)$existing['wallet_id']===(int)$wallet['id']
        && (int)$existing['requested_by_user_id']===$userId
        && (int)$existing['amount_cents']===$amountCents
        && hash_equals((string)$existing['currency'],(string)$wallet['currency']);
    if(!$sameRequest)throw new MgCashoutWorkflowException('Cashout idempotency key is already bound to a different request.',409);
}

function mg_cashout_active_hold_count(PDO $pdo,int $walletId): int
{
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM payout_holds WHERE wallet_id=? AND status='active' AND (expires_at IS NULL OR expires_at>NOW())");
    $stmt->execute([$walletId]);
    return (int)$stmt->fetchColumn();
}

function mg_cashout_notify(PDO $pdo,int $userId,string $type,string $severity,string $title,string $body,array $context=[]): void
{
    mg_create_operational_alert($pdo,$userId,$type,$severity,$title,$body,'/account-wallet.php',$context);
}

function mg_cashout_request(PDO $pdo,array $wallet,int $userId,int $amountCents,string $idempotencyKey,?callable $failureHook=null): array
{
    if($amountCents<1)throw new MgCashoutWorkflowException('Cashout amount must be positive.',422);
    $idempotencyKey=trim($idempotencyKey);
    if($idempotencyKey==='')throw new MgCashoutWorkflowException('Idempotency key is required.',422);

    $ownsTransaction=!$pdo->inTransaction();
    if($ownsTransaction)$pdo->beginTransaction();
    try{
        $locked=$pdo->prepare("SELECT * FROM wallets WHERE id=? AND status='active' FOR UPDATE");
        $locked->execute([(int)$wallet['id']]);$wallet=$locked->fetch(PDO::FETCH_ASSOC);
        if(!$wallet)throw new MgCashoutWorkflowException('Wallet is not active.',409);

        $existing=$pdo->prepare('SELECT * FROM cashout_requests WHERE wallet_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
        $existing->execute([(int)$wallet['id'],$idempotencyKey]);
        if($row=$existing->fetch(PDO::FETCH_ASSOC)){
            mg_cashout_assert_idempotent_request($row,$wallet,$userId,$amountCents);
            if($ownsTransaction)$pdo->commit();
            return $row+['duplicate'=>true];
        }

        if(mg_cashout_active_hold_count($pdo,(int)$wallet['id'])>0)throw new MgCashoutWorkflowException('Wallet has an active payout hold.',409);
        $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
        if((int)$balances['available_cents']<$amountCents)throw new MgCashoutWorkflowException('Insufficient available balance.',422);

        $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
        $reserved=mg_wallet_account_id($pdo,(int)$wallet['id'],'cashout_pending',(string)$wallet['currency']);
        $group=mg_ledger_post($pdo,[
            'transaction_type'=>'cashout_reservation','source_type'=>'cashout_request','source_reference'=>$idempotencyKey,
            'idempotency_key'=>'cashout:reserve:'.$wallet['public_id'].':'.$idempotencyKey,
            'currency'=>$wallet['currency'],'description'=>'Reserve funds for cashout',
        ],[
            ['ledger_account_id'=>$available,'entry_type'=>'debit','amount_cents'=>$amountCents,'description'=>'Reduce available balance'],
            ['ledger_account_id'=>$reserved,'entry_type'=>'credit','amount_cents'=>$amountCents,'description'=>'Reserve cashout balance'],
        ],$userId);
        if($failureHook)$failureHook('after_reservation',['wallet'=>$wallet,'group'=>$group]);

        $public=mg_public_uuid();
        $pdo->prepare("INSERT INTO cashout_requests (public_id,wallet_id,requested_by_user_id,amount_cents,currency,status,idempotency_key,reservation_group_id,created_at,updated_at) VALUES (?,?,?,?,?,'requested',?,?,NOW(),NOW())")
            ->execute([$public,(int)$wallet['id'],$userId,$amountCents,$wallet['currency'],$idempotencyKey,(int)$group['id']]);
        mg_audit('cashout.requested','cashout_request',['cashout_id'=>$public,'amount_cents'=>$amountCents,'currency'=>$wallet['currency']],$userId);
        mg_event('cashout.requested',['cashout_id'=>$public,'wallet_id'=>$wallet['public_id'],'amount_cents'=>$amountCents,'currency'=>$wallet['currency']],$userId);
        mg_cashout_notify($pdo,$userId,'cashout_requested','info','Cashout requested','Your cashout request has been created.',['cashout_id'=>$public,'amount_cents'=>$amountCents]);
        if($failureHook)$failureHook('before_complete',['cashout_id'=>$public]);

        $existing=$pdo->prepare('SELECT * FROM cashout_requests WHERE public_id=? LIMIT 1');
        $existing->execute([$public]);$result=$existing->fetch(PDO::FETCH_ASSOC)+['duplicate'=>false];
        if($ownsTransaction)$pdo->commit();
        return $result;
    }catch(Throwable $e){
        if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function mg_cashout_cancel(PDO $pdo,array $cashout,int $actorUserId): array
{
    $ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
    try{
        $lock=$pdo->prepare('SELECT * FROM cashout_requests WHERE id=? FOR UPDATE');$lock->execute([(int)$cashout['id']]);$cashout=$lock->fetch(PDO::FETCH_ASSOC);
        if(!$cashout||$cashout['status']!=='requested')throw new MgCashoutWorkflowException('Cashout can only be cancelled before approval.',409);
        $walletStmt=$pdo->prepare('SELECT * FROM wallets WHERE id=? LIMIT 1');$walletStmt->execute([(int)$cashout['wallet_id']]);$wallet=$walletStmt->fetch(PDO::FETCH_ASSOC);
        $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
        $reserved=mg_wallet_account_id($pdo,(int)$wallet['id'],'cashout_pending',(string)$wallet['currency']);
        $group=mg_ledger_post($pdo,[
            'transaction_type'=>'cashout_release','source_type'=>'cashout_request','source_reference'=>$cashout['public_id'],
            'idempotency_key'=>'cashout:cancel:'.$cashout['public_id'],'currency'=>$cashout['currency'],'description'=>'Release cancelled cashout reservation',
        ],[
            ['ledger_account_id'=>$reserved,'entry_type'=>'debit','amount_cents'=>(int)$cashout['amount_cents']],
            ['ledger_account_id'=>$available,'entry_type'=>'credit','amount_cents'=>(int)$cashout['amount_cents']],
        ],$actorUserId);
        $pdo->prepare("UPDATE cashout_requests SET status='cancelled',release_group_id=?,cancelled_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int)$group['id'],(int)$cashout['id']]);
        mg_audit('cashout.cancelled','cashout_request',['cashout_id'=>$cashout['public_id']],$actorUserId);
        mg_event('cashout.cancelled',['cashout_id'=>$cashout['public_id']],$actorUserId);
        if($ownsTransaction)$pdo->commit();
        return $cashout+['status'=>'cancelled'];
    }catch(Throwable $e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_cashout_approve(PDO $pdo,array $cashout,int $actorUserId,?callable $failureHook=null): array
{
    $ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
    try{
        $lock=$pdo->prepare('SELECT * FROM cashout_requests WHERE id=? FOR UPDATE');$lock->execute([(int)$cashout['id']]);$cashout=$lock->fetch(PDO::FETCH_ASSOC);
        if(!$cashout)throw new MgCashoutWorkflowException('Cashout not found.',404);
        $linked=$pdo->prepare('SELECT p.* FROM cashout_payout_links l INNER JOIN merchant_payouts p ON p.id=l.payout_id WHERE l.cashout_request_id=? LIMIT 1 FOR UPDATE');
        $linked->execute([(int)$cashout['id']]);$existingPayout=$linked->fetch(PDO::FETCH_ASSOC);
        if(in_array((string)$cashout['status'],['queued','paid','failed'],true)&&$existingPayout){
            if($ownsTransaction)$pdo->commit();
            return ['cashout_id'=>$cashout['public_id'],'payout_id'=>$existingPayout['public_id'],'status'=>$cashout['status'],'duplicate'=>true];
        }
        if((string)$cashout['status']!=='requested')throw new MgCashoutWorkflowException('Cashout is not awaiting approval.',409);

        $walletStmt=$pdo->prepare('SELECT * FROM wallets WHERE id=? LIMIT 1');$walletStmt->execute([(int)$cashout['wallet_id']]);$wallet=$walletStmt->fetch(PDO::FETCH_ASSOC);
        $provider=mg_payment_provider_key();
        $account=$pdo->prepare("SELECT * FROM payment_provider_accounts WHERE merchant_user_id=? AND provider_key=? AND mode=? AND status='active' AND payouts_enabled=1 LIMIT 1");
        $account->execute([(int)$wallet['owner_user_id'],$provider,mg_payment_is_live()?'live':'test']);
        if(!$account->fetch()&&$provider!=='sandbox')throw new MgCashoutWorkflowException('Payout provider account is not enabled.',409);

        $pdo->prepare("UPDATE cashout_requests SET status='approved',approved_by_user_id=?,approved_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([$actorUserId,(int)$cashout['id']]);
        $payoutPublic=mg_public_uuid();
        $pdo->prepare("INSERT INTO merchant_payouts (public_id,merchant_user_id,provider_key,currency,gross_cents,fee_cents,adjustment_cents,net_cents,status,arrival_date,created_at,updated_at) VALUES (?,?,?,?,?,0,0,?,'pending',NULL,NOW(),NOW())")
            ->execute([$payoutPublic,(int)$wallet['owner_user_id'],$provider,$cashout['currency'],(int)$cashout['amount_cents'],(int)$cashout['amount_cents']]);
        $payoutId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cashout_payout_links (cashout_request_id,payout_id,idempotency_key,created_at) VALUES (?,?,?,NOW())')
            ->execute([(int)$cashout['id'],$payoutId,'payout:'.$cashout['public_id']]);
        if($failureHook)$failureHook('after_payout_create',['cashout'=>$cashout,'payout_id'=>$payoutPublic]);
        $pdo->prepare("UPDATE cashout_requests SET status='queued',updated_at=NOW() WHERE id=?")->execute([(int)$cashout['id']]);
        mg_audit('cashout.approved','cashout_request',['cashout_id'=>$cashout['public_id'],'payout_id'=>$payoutPublic],$actorUserId);
        mg_event('cashout.approved',['cashout_id'=>$cashout['public_id'],'payout_id'=>$payoutPublic],$actorUserId);
        mg_event('payout.created',['cashout_id'=>$cashout['public_id'],'payout_id'=>$payoutPublic],$actorUserId);
        mg_cashout_notify($pdo,(int)$wallet['owner_user_id'],'cashout_approved','info','Cashout approved','Your cashout has been queued for payout.',['cashout_id'=>$cashout['public_id'],'payout_id'=>$payoutPublic]);
        if($ownsTransaction)$pdo->commit();
        return ['cashout_id'=>$cashout['public_id'],'payout_id'=>$payoutPublic,'status'=>'queued','duplicate'=>false];
    }catch(Throwable $e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_payout_finalize(PDO $pdo,string $payoutPublicId,string $status,string $providerReference,?string $failureMessage=null,?callable $failureHook=null): array
{
    if(!in_array($status,['paid','failed'],true))throw new MgCashoutWorkflowException('Invalid payout terminal status.',422);
    $ownsTransaction=!$pdo->inTransaction();if($ownsTransaction)$pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT p.*,c.id cashout_id,c.public_id cashout_public_id,c.wallet_id,c.amount_cents FROM merchant_payouts p INNER JOIN cashout_payout_links l ON l.payout_id=p.id INNER JOIN cashout_requests c ON c.id=l.cashout_request_id WHERE p.public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$payoutPublicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new MgCashoutWorkflowException('Payout not found.',404);
        if(in_array((string)$row['status'],['paid','failed'],true)){
            if((string)$row['status']!==$status)throw new MgCashoutWorkflowException('Payout terminal event conflicts with the recorded outcome.',409);
            if($ownsTransaction)$pdo->commit();
            return ['payout_id'=>$payoutPublicId,'cashout_id'=>$row['cashout_public_id'],'status'=>$status,'duplicate'=>true];
        }
        $walletStmt=$pdo->prepare('SELECT * FROM wallets WHERE id=?');$walletStmt->execute([(int)$row['wallet_id']]);$wallet=$walletStmt->fetch(PDO::FETCH_ASSOC);
        $reserved=mg_wallet_account_id($pdo,(int)$wallet['id'],'cashout_pending',(string)$wallet['currency']);
        if($status==='paid'){
            $paid=mg_wallet_account_id($pdo,(int)$wallet['id'],'paid',(string)$wallet['currency']);
            $group=mg_ledger_post($pdo,['transaction_type'=>'payout_paid','source_type'=>'payout','source_reference'=>$payoutPublicId,'idempotency_key'=>'payout:paid:'.$payoutPublicId,'currency'=>$wallet['currency'],'description'=>'Finalize paid cashout'],[
                ['ledger_account_id'=>$reserved,'entry_type'=>'debit','amount_cents'=>(int)$row['amount_cents']],
                ['ledger_account_id'=>$paid,'entry_type'=>'credit','amount_cents'=>(int)$row['amount_cents']],
            ]);
            if($failureHook)$failureHook('after_ledger',['payout'=>$row,'group'=>$group]);
            $pdo->prepare("UPDATE merchant_payouts SET status='paid',provider_payout_reference=?,paid_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$providerReference,(int)$row['id']]);
            $pdo->prepare("UPDATE cashout_requests SET status='paid',paid_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$row['cashout_id']]);
            mg_event('payout.paid',['payout_id'=>$payoutPublicId,'cashout_id'=>$row['cashout_public_id']]);
            mg_cashout_notify($pdo,(int)$wallet['owner_user_id'],'payout_paid','info','Payout completed','Your cashout payout completed successfully.',['cashout_id'=>$row['cashout_public_id'],'payout_id'=>$payoutPublicId]);
        }else{
            $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
            $group=mg_ledger_post($pdo,['transaction_type'=>'payout_failed_release','source_type'=>'payout','source_reference'=>$payoutPublicId,'idempotency_key'=>'payout:failed:'.$payoutPublicId,'currency'=>$wallet['currency'],'description'=>'Release failed payout'],[
                ['ledger_account_id'=>$reserved,'entry_type'=>'debit','amount_cents'=>(int)$row['amount_cents']],
                ['ledger_account_id'=>$available,'entry_type'=>'credit','amount_cents'=>(int)$row['amount_cents']],
            ]);
            if($failureHook)$failureHook('after_ledger',['payout'=>$row,'group'=>$group]);
            $pdo->prepare("UPDATE merchant_payouts SET status='failed',provider_payout_reference=?,updated_at=NOW() WHERE id=?")->execute([$providerReference,(int)$row['id']]);
            $pdo->prepare("UPDATE cashout_requests SET status='failed',failure_message=?,updated_at=NOW() WHERE id=?")->execute([mb_substr((string)$failureMessage,0,500),(int)$row['cashout_id']]);
            mg_event('payout.failed',['payout_id'=>$payoutPublicId,'cashout_id'=>$row['cashout_public_id'],'failure_message'=>$failureMessage]);
            mg_cashout_notify($pdo,(int)$wallet['owner_user_id'],'payout_failed','warning','Payout failed','Your cashout payout failed and the reserved balance was restored.',['cashout_id'=>$row['cashout_public_id'],'payout_id'=>$payoutPublicId]);
        }
        if($failureHook)$failureHook('before_complete',['payout'=>$row]);
        if($ownsTransaction)$pdo->commit();
        return ['payout_id'=>$payoutPublicId,'cashout_id'=>$row['cashout_public_id'],'status'=>$status,'duplicate'=>false];
    }catch(Throwable $e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mg_payout_hold_create(PDO $pdo,array $wallet,int $amountCents,string $reason,int $actorUserId,?string $expiresAt=null): array
{
    if($amountCents<1||trim($reason)==='')throw new MgCashoutWorkflowException('Amount and reason are required.',422);
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    if((int)$balances['available_cents']<$amountCents)throw new MgCashoutWorkflowException('Insufficient available balance.',422);
    $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
    $held=mg_wallet_account_id($pdo,(int)$wallet['id'],'held',(string)$wallet['currency']);
    $public=mg_public_uuid();
    $group=mg_ledger_post($pdo,['transaction_type'=>'payout_hold','source_type'=>'payout_hold','source_reference'=>$public,'idempotency_key'=>'hold:create:'.$public,'currency'=>$wallet['currency'],'description'=>$reason],[
        ['ledger_account_id'=>$available,'entry_type'=>'debit','amount_cents'=>$amountCents],
        ['ledger_account_id'=>$held,'entry_type'=>'credit','amount_cents'=>$amountCents],
    ],$actorUserId);
    $pdo->prepare("INSERT INTO payout_holds (public_id,wallet_id,amount_cents,currency,reason,status,hold_group_id,created_by_user_id,expires_at,created_at) VALUES (?,?,?,?,?,'active',?,?,?,NOW())")
        ->execute([$public,(int)$wallet['id'],$amountCents,$wallet['currency'],trim($reason),(int)$group['id'],$actorUserId,$expiresAt]);
    mg_audit('payout_hold.created','payout_hold',['hold_id'=>$public,'wallet_id'=>$wallet['public_id'],'amount_cents'=>$amountCents],$actorUserId);
    mg_event('payout_hold.created',['hold_id'=>$public,'wallet_id'=>$wallet['public_id'],'amount_cents'=>$amountCents],$actorUserId);
    mg_cashout_notify($pdo,(int)$wallet['owner_user_id'],'payout_hold_created','high','Payout hold created','A payout hold is active on your wallet.',['hold_id'=>$public,'amount_cents'=>$amountCents]);
    return ['hold_id'=>$public,'status'=>'active','hold_group_id'=>(int)$group['id']];
}

function mg_payout_hold_release(PDO $pdo,string $holdPublicId,int $actorUserId): array
{
    $stmt=$pdo->prepare("SELECT h.*,w.public_id wallet_public_id,w.owner_user_id FROM payout_holds h INNER JOIN wallets w ON w.id=h.wallet_id WHERE h.public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$holdPublicId]);$hold=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$hold)throw new MgCashoutWorkflowException('Payout hold not found.',404);
    if((string)$hold['status']==='released')return ['hold_id'=>$holdPublicId,'status'=>'released','duplicate'=>true];
    if((string)$hold['status']!=='active')throw new MgCashoutWorkflowException('Payout hold is not active.',409);
    $available=mg_wallet_account_id($pdo,(int)$hold['wallet_id'],'available',(string)$hold['currency']);
    $held=mg_wallet_account_id($pdo,(int)$hold['wallet_id'],'held',(string)$hold['currency']);
    $group=mg_ledger_post($pdo,['transaction_type'=>'payout_hold_release','source_type'=>'payout_hold','source_reference'=>$holdPublicId,'idempotency_key'=>'hold:release:'.$holdPublicId,'currency'=>$hold['currency'],'description'=>'Release payout hold'],[
        ['ledger_account_id'=>$held,'entry_type'=>'debit','amount_cents'=>(int)$hold['amount_cents']],
        ['ledger_account_id'=>$available,'entry_type'=>'credit','amount_cents'=>(int)$hold['amount_cents']],
    ],$actorUserId);
    $pdo->prepare("UPDATE payout_holds SET status='released',release_group_id=?,released_by_user_id=?,released_at=NOW() WHERE id=?")
        ->execute([(int)$group['id'],$actorUserId,(int)$hold['id']]);
    mg_audit('payout_hold.released','payout_hold',['hold_id'=>$holdPublicId],$actorUserId);
    mg_event('payout_hold.released',['hold_id'=>$holdPublicId],$actorUserId);
    mg_cashout_notify($pdo,(int)$hold['owner_user_id'],'payout_hold_released','info','Payout hold released','The payout hold on your wallet was released.',['hold_id'=>$holdPublicId]);
    return ['hold_id'=>$holdPublicId,'status'=>'released','duplicate'=>false];
}

function mg_payout_process_event(PDO $pdo,string $provider,array $event,string $payload,?callable $failureHook=null): array
{
    $eventId=trim((string)($event['id']??''));$type=trim((string)($event['type']??''));$data=is_array($event['data']??null)?$event['data']:[];
    $payoutId=trim((string)($data['payout_id']??$data['metadata']['payout_id']??''));
    $providerReference=trim((string)($data['provider_reference']??$data['id']??$eventId));
    if($eventId===''||$type===''||$payoutId==='')throw new MgCashoutWorkflowException('Invalid payout event.',422);
    $hash=hash('sha256',$payload);
    $existing=$pdo->prepare('SELECT * FROM payment_webhook_events WHERE provider_key=? AND provider_event_id=? LIMIT 1 FOR UPDATE');
    $existing->execute([$provider,$eventId]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        if(!hash_equals((string)$row['payload_hash'],$hash)||(string)$row['event_type']!==$type)throw new MgCashoutWorkflowException('Payout webhook event conflicts with the recorded payload.',409);
        return ['duplicate'=>true,'event_id'=>$eventId,'status'=>$row['status']];
    }
    $pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'received',?,?,NOW())")
        ->execute([mg_public_uuid(),$provider,$eventId,$type,$hash,$payload]);
    if(in_array($type,['payout.paid','transfer.paid'],true))$result=mg_payout_finalize($pdo,$payoutId,'paid',$providerReference,null,$failureHook);
    elseif(in_array($type,['payout.failed','transfer.failed'],true))$result=mg_payout_finalize($pdo,$payoutId,'failed',$providerReference,(string)($data['failure_message']??'Provider reported payout failure.'),$failureHook);
    else $result=['ignored'=>true];
    $pdo->prepare("UPDATE payment_webhook_events SET status=?,processed_at=NOW() WHERE provider_key=? AND provider_event_id=?")
        ->execute([isset($result['ignored'])?'ignored':'processed',$provider,$eventId]);
    return ['duplicate'=>false,'event_id'=>$eventId,'result'=>$result];
}

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

const MG_WALLET_ACCOUNT_DEFINITIONS = [
    'available' => ['liability','credit'],
    'pending' => ['liability','credit'],
    'held' => ['liability','credit'],
    'cashout_pending' => ['liability','credit'],
    'paid' => ['liability','credit'],
];

function mg_money_currency(string $currency): string
{
    $currency = strtoupper(trim($currency));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) mg_fail('Invalid currency.', 422);
    return $currency;
}

function mg_wallet_resolve(PDO $pdo, string $ownerType, int $ownerUserId, string $currency = 'USD'): array
{
    $allowed = ['user','merchant','creator','organization','enterprise'];
    if (!in_array($ownerType, $allowed, true) || $ownerUserId < 1) throw new InvalidArgumentException('Invalid wallet owner.');
    $currency = mg_money_currency($currency);
    $stmt = $pdo->prepare('SELECT * FROM wallets WHERE owner_type=? AND owner_user_id=? AND currency=? LIMIT 1');
    $stmt->execute([$ownerType,$ownerUserId,$currency]);
    $wallet = $stmt->fetch();
    if (!$wallet) {
        $public = mg_public_uuid();
        try {
            $pdo->prepare("INSERT INTO wallets (public_id,owner_type,owner_user_id,currency,status,created_at,updated_at) VALUES (?,?,?,?,'active',NOW(),NOW())")
                ->execute([$public,$ownerType,$ownerUserId,$currency]);
            mg_event('wallet.created',['wallet_id'=>$public,'owner_type'=>$ownerType,'currency'=>$currency],$ownerUserId);
        } catch (Throwable $e) {
            if (!str_contains($e->getMessage(),'Duplicate')) throw $e;
        }
        $stmt->execute([$ownerType,$ownerUserId,$currency]);
        $wallet = $stmt->fetch();
    }
    if (!$wallet) throw new RuntimeException('Unable to resolve wallet.');
    mg_wallet_ensure_accounts($pdo,(int)$wallet['id'],$currency);
    return $wallet;
}

function mg_wallet_ensure_accounts(PDO $pdo, int $walletId, string $currency): void
{
    $stmt = $pdo->prepare("INSERT IGNORE INTO ledger_accounts (public_id,wallet_id,account_code,account_class,normal_side,currency,status,created_at) VALUES (?,?,?,?,?,?,'active',NOW())");
    foreach (MG_WALLET_ACCOUNT_DEFINITIONS as $code => [$class,$normal]) {
        $stmt->execute([mg_public_uuid(),$walletId,$code,$class,$normal,$currency]);
    }
}

function mg_ledger_platform_account(PDO $pdo, string $code, string $class, string $normal, string $currency): int
{
    $stmt = $pdo->prepare('SELECT id FROM ledger_accounts WHERE wallet_id IS NULL AND account_code=? AND currency=? LIMIT 1');
    $stmt->execute([$code,$currency]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    try {
        $pdo->prepare("INSERT INTO ledger_accounts (public_id,wallet_id,account_code,account_class,normal_side,currency,status,created_at) VALUES (?,NULL,?,?,?,?,'active',NOW())")
            ->execute([mg_public_uuid(),$code,$class,$normal,$currency]);
    } catch (Throwable $e) {
        if (!str_contains($e->getMessage(),'Duplicate')) throw $e;
    }
    $stmt->execute([$code,$currency]);
    $id = $stmt->fetchColumn();
    if (!$id) throw new RuntimeException('Unable to resolve platform ledger account.');
    return (int)$id;
}

function mg_wallet_account_id(PDO $pdo, int $walletId, string $code, string $currency): int
{
    $stmt = $pdo->prepare("SELECT id FROM ledger_accounts WHERE wallet_id=? AND account_code=? AND currency=? AND status='active' LIMIT 1");
    $stmt->execute([$walletId,$code,$currency]);
    $id = $stmt->fetchColumn();
    if (!$id) throw new RuntimeException('Wallet ledger account not found: '.$code);
    return (int)$id;
}

function mg_ledger_entry_fingerprint(array $entry): string
{
    return implode(':', [
        (int)($entry['ledger_account_id'] ?? 0),
        (string)($entry['entry_type'] ?? ''),
        (int)($entry['amount_cents'] ?? 0),
    ]);
}

function mg_ledger_assert_idempotent_request(PDO $pdo,array $existing,array $group,array $entries,string $currency): void
{
    $sameHeader = hash_equals((string)$existing['transaction_type'], trim((string)($group['transaction_type'] ?? '')))
        && hash_equals((string)$existing['source_type'], trim((string)($group['source_type'] ?? '')))
        && hash_equals((string)($existing['source_reference'] ?? ''), (string)($group['source_reference'] ?? ''))
        && hash_equals((string)$existing['currency'], $currency);
    if (!$sameHeader) {
        throw new RuntimeException('Ledger idempotency key is already bound to a different transaction request.');
    }

    $stmt=$pdo->prepare('SELECT ledger_account_id,entry_type,amount_cents FROM ledger_entries WHERE transaction_group_id=? ORDER BY ledger_account_id,entry_type,amount_cents,id');
    $stmt->execute([(int)$existing['id']]);
    $stored=array_map('mg_ledger_entry_fingerprint',$stmt->fetchAll());
    $requested=array_map('mg_ledger_entry_fingerprint',$entries);
    sort($stored,SORT_STRING);
    sort($requested,SORT_STRING);
    if ($stored !== $requested) {
        throw new RuntimeException('Ledger idempotency key is already bound to different ledger entries.');
    }
}

function mg_ledger_post(PDO $pdo, array $group, array $entries, ?int $actorUserId = null): array
{
    $currency = mg_money_currency((string)($group['currency'] ?? 'USD'));
    $idempotency = trim((string)($group['idempotency_key'] ?? ''));
    $transactionType = trim((string)($group['transaction_type'] ?? ''));
    $sourceType = trim((string)($group['source_type'] ?? ''));
    if ($idempotency === '') throw new InvalidArgumentException('Ledger idempotency key is required.');
    if ($transactionType === '' || $sourceType === '') throw new InvalidArgumentException('Ledger transaction and source types are required.');

    $debits = 0; $credits = 0;
    foreach ($entries as $entry) {
        $amount = (int)($entry['amount_cents'] ?? 0);
        $side = (string)($entry['entry_type'] ?? '');
        if ($amount < 1 || !in_array($side,['debit','credit'],true) || (int)($entry['ledger_account_id'] ?? 0) < 1) throw new InvalidArgumentException('Invalid ledger entry.');
        if ($side === 'debit') $debits += $amount; else $credits += $amount;
    }
    if ($debits !== $credits || $debits < 1) throw new InvalidArgumentException('Ledger transaction is not balanced.');

    $existing = $pdo->prepare('SELECT * FROM ledger_transaction_groups WHERE idempotency_key=? LIMIT 1');
    $existing->execute([$idempotency]);
    if ($row = $existing->fetch()) {
        mg_ledger_assert_idempotent_request($pdo,$row,$group,$entries,$currency);
        return $row;
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $public = mg_public_uuid();
        $pdo->prepare("INSERT INTO ledger_transaction_groups (public_id,transaction_type,source_type,source_reference,idempotency_key,currency,status,description,metadata_json,posted_at,created_by_user_id,created_at) VALUES (?,?,?,?,?,?,'posted',?,?,NOW(),?,NOW())")
            ->execute([$public,$transactionType,$sourceType,$group['source_reference']??null,$idempotency,$currency,$group['description']??null,json_encode($group['metadata']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$actorUserId]);
        $groupId = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO ledger_entries (public_id,transaction_group_id,ledger_account_id,entry_type,amount_cents,currency,description,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
        foreach ($entries as $entry) {
            $insert->execute([mg_public_uuid(),$groupId,(int)$entry['ledger_account_id'],(string)$entry['entry_type'],(int)$entry['amount_cents'],$currency,$entry['description']??null,json_encode($entry['metadata']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        }
        mg_audit('ledger.transaction_posted','ledger_transaction_group',['group_id'=>$public,'transaction_type'=>$transactionType,'amount_cents'=>$debits,'currency'=>$currency],$actorUserId);
        mg_event('ledger.transaction_posted',['group_id'=>$public,'transaction_type'=>$transactionType,'amount_cents'=>$debits,'currency'=>$currency],$actorUserId);
        if ($ownsTransaction) $pdo->commit();
        $existing->execute([$idempotency]);
        return $existing->fetch() ?: ['id'=>$groupId,'public_id'=>$public];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        if (str_contains($e->getMessage(),'Duplicate')) {
            $existing->execute([$idempotency]);
            if ($row=$existing->fetch()) {
                mg_ledger_assert_idempotent_request($pdo,$row,$group,$entries,$currency);
                return $row;
            }
        }
        throw $e;
    }
}

function mg_wallet_balances(PDO $pdo, int $walletId): array
{
    $stmt = $pdo->prepare("SELECT a.account_code,a.normal_side,COALESCE(SUM(CASE WHEN e.entry_type=a.normal_side THEN e.amount_cents ELSE -e.amount_cents END),0) balance_cents,a.currency
                           FROM ledger_accounts a LEFT JOIN ledger_entries e ON e.ledger_account_id=a.id
                           WHERE a.wallet_id=? GROUP BY a.id ORDER BY a.account_code");
    $stmt->execute([$walletId]);
    $result = ['available_cents'=>0,'pending_cents'=>0,'held_cents'=>0,'cashout_pending_cents'=>0,'paid_cents'=>0];
    $currency = 'USD';
    foreach ($stmt->fetchAll() as $row) { $result[(string)$row['account_code'].'_cents']=(int)$row['balance_cents']; $currency=(string)$row['currency']; }
    $result['currency']=$currency;
    $result['calculated_at']=gmdate('c');
    return $result;
}

function mg_ledger_reverse(PDO $pdo, string $originalPublicId, string $idempotencyKey, string $reason, ?int $actorUserId = null): array
{
    $idempotencyKey = trim($idempotencyKey);
    $reason = trim($reason);
    if ($idempotencyKey === '' || $reason === '') throw new InvalidArgumentException('Reversal idempotency key and reason are required.');

    $existing = $pdo->prepare("SELECT rg.* FROM ledger_transaction_groups rg INNER JOIN ledger_reversal_links l ON l.reversal_group_id=rg.id INNER JOIN ledger_transaction_groups og ON og.id=l.original_group_id WHERE og.public_id=? AND rg.idempotency_key=? LIMIT 1");
    $existing->execute([$originalPublicId,$idempotencyKey]);
    if ($row=$existing->fetch()) return $row;

    $stmt=$pdo->prepare("SELECT * FROM ledger_transaction_groups WHERE public_id=? AND status='posted' LIMIT 1 FOR UPDATE");
    $stmt->execute([$originalPublicId]);
    $original=$stmt->fetch();
    if(!$original) throw new RuntimeException('Posted ledger group not found or already reversed.');

    $linked=$pdo->prepare('SELECT reversal_group_id FROM ledger_reversal_links WHERE original_group_id=? LIMIT 1');
    $linked->execute([(int)$original['id']]);
    if($linked->fetchColumn()) throw new RuntimeException('Ledger group is already reversed.');

    $entries=$pdo->prepare('SELECT ledger_account_id,entry_type,amount_cents,description FROM ledger_entries WHERE transaction_group_id=? ORDER BY id');
    $entries->execute([(int)$original['id']]);
    $reversed=[];
    foreach($entries->fetchAll() as $entry){$reversed[]=['ledger_account_id'=>(int)$entry['ledger_account_id'],'entry_type'=>$entry['entry_type']==='debit'?'credit':'debit','amount_cents'=>(int)$entry['amount_cents'],'description'=>'Reversal: '.$reason];}
    if(!$reversed) throw new RuntimeException('Ledger group has no entries to reverse.');

    $group=mg_ledger_post($pdo,['transaction_type'=>'reversal','source_type'=>'ledger_group','source_reference'=>$originalPublicId,'idempotency_key'=>$idempotencyKey,'currency'=>$original['currency'],'description'=>$reason],$reversed,$actorUserId);
    $pdo->prepare('INSERT INTO ledger_reversal_links (original_group_id,reversal_group_id,reason,created_by_user_id,created_at) VALUES (?,?,?,?,NOW())')->execute([(int)$original['id'],(int)$group['id'],$reason,$actorUserId]);
    $pdo->prepare("UPDATE ledger_transaction_groups SET status='reversed' WHERE id=? AND status='posted'")->execute([(int)$original['id']]);
    return $group;
}

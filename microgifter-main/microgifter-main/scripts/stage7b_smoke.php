<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/db.php';
$pdo=mg_db();
$tables=['wallets','ledger_accounts','ledger_transaction_groups','ledger_entries','ledger_reversal_links','wallet_balance_snapshots','cashout_requests','cashout_payout_links','payout_holds'];
try{
    foreach($tables as $table){$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$stmt->execute([$table]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing table '.$table);}
    foreach(['ledger_entries'=>'chk_ledger_entries_positive','cashout_requests'=>'chk_cashout_positive'] as $table=>$constraint){$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name=? AND constraint_name=?');$stmt->execute([$table,$constraint]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing constraint '.$constraint);}
    echo "Stage 7B smoke checks passed.\n";
}catch(Throwable $e){fwrite(STDERR,'FAILED: '.$e->getMessage()."\n");exit(1);}

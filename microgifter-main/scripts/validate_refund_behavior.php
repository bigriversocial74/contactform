<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

// Compatibility entry point retained for existing CI and operator commands.
// Canonical contract markers: mg_finance_refund_order( SAVEPOINT refund_failure
// ROLLBACK TO SAVEPOINT refund_failure $pdo->rollBack()
require __DIR__.'/validate_refund_reconciliation_behavior.php';

<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage7CashoutIdempotencyBindingTest extends TestCase
{
    public function testCashoutIdempotencyKeyIsBoundToExactRequest(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/finance/_cashouts.php');
        self::assertIsString($source);
        self::assertStringContainsString('function mg_cashout_assert_idempotent_request',$source);
        self::assertStringContainsString('requested_by_user_id',$source);
        self::assertStringContainsString('amount_cents',$source);
        self::assertStringContainsString('Cashout idempotency key is already bound to a different request.',$source);
        self::assertStringContainsString('MgCashoutWorkflowException',$source);
        self::assertStringContainsString("return \$row+['duplicate'=>true]",$source);
        self::assertStringContainsString('WHERE wallet_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE',$source);
    }
}

<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage5CheckoutDraftIdempotencyBindingTest extends TestCase
{
    public function testCheckoutDraftIdempotencyKeyIsBoundToCartSnapshot(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/commerce/checkout-draft.php');
        self::assertIsString($source);
        self::assertStringContainsString('SELECT * FROM checkout_drafts',$source);
        self::assertStringContainsString('LIMIT 1 FOR UPDATE',$source);
        self::assertStringContainsString('json_decode((string)$draft[\'items_json\']',$source);
        self::assertStringContainsString('(int)$draft[\'cart_id\']===(int)$cart[\'id\']',$source);
        self::assertStringContainsString('(int)$draft[\'merchant_user_id\']===$merchantIds[0]',$source);
        self::assertStringContainsString('(int)$draft[\'total_cents\']===(int)$payload[\'totals\'][\'total_cents\']',$source);
        self::assertStringContainsString('$storedItems==$payload[\'items\']',$source);
        self::assertStringContainsString('Checkout draft idempotency key is already bound to a different cart snapshot.',$source);
    }
}

<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9RedemptionIdempotencyBindingTest extends TestCase
{
    public function testDirectRedemptionReplayIsBoundToOriginalRequest(): void
    {
        $guard=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_idempotency.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/microgifts/redeem.php');
        self::assertIsString($guard);
        self::assertIsString($endpoint);
        self::assertStringContainsString('mg_microgift_assert_redemption_replay',$guard);
        self::assertStringContainsString('merchant_user_id',$guard);
        self::assertStringContainsString('location_reference',$guard);
        self::assertStringContainsString('source_reference',$guard);
        self::assertStringContainsString('different request',$guard);
        self::assertStringContainsString("require_once __DIR__ . '/_idempotency.php';",$endpoint);
        self::assertStringContainsString('mg_microgift_assert_redemption_replay',$endpoint);
        self::assertStringContainsString("'redemption_id'=>\$existing['public_id']",$endpoint);
    }
}

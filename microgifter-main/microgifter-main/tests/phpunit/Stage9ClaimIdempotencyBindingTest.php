<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9ClaimIdempotencyBindingTest extends TestCase
{
    public function testDirectClaimReplayIsBoundToInstanceAndClaimant(): void
    {
        $guard=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_idempotency.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/microgifts/claim.php');
        self::assertIsString($guard);
        self::assertIsString($endpoint);
        self::assertStringContainsString('mg_microgift_assert_claim_replay',$guard);
        self::assertStringContainsString('claimant_user_id',$guard);
        self::assertStringContainsString('instance_public_id',$guard);
        self::assertStringContainsString('source_reference',$guard);
        self::assertStringContainsString('different request',$guard);
        self::assertStringContainsString("require_once __DIR__ . '/_idempotency.php';",$endpoint);
        self::assertStringContainsString('mg_microgift_assert_claim_replay',$endpoint);
        self::assertStringContainsString("'instance_id'=>\$instanceId",$endpoint);
    }
}

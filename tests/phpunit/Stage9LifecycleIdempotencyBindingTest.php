<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9LifecycleIdempotencyBindingTest extends TestCase
{
    public function testAdminLifecycleReplayIsBoundToOriginalRequest(): void
    {
        $guard=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_idempotency.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/admin/microgift-lifecycle.php');
        self::assertIsString($guard);
        self::assertIsString($endpoint);
        self::assertStringContainsString('mg_microgift_assert_lifecycle_replay',$guard);
        self::assertStringContainsString('action_type',$guard);
        self::assertStringContainsString('source_type',$guard);
        self::assertStringContainsString('source_reference',$guard);
        self::assertStringContainsString('instance_public_id',$guard);
        self::assertStringContainsString('different request',$guard);
        self::assertStringContainsString("require_once dirname(__DIR__) . '/microgifts/_idempotency.php';",$endpoint);
        self::assertStringContainsString('mg_microgift_assert_lifecycle_replay',$endpoint);
        self::assertStringContainsString("'duplicate'=>true",$endpoint);
    }
}

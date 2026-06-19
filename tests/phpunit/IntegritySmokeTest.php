<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IntegritySmokeTest extends TestCase
{
    public function testLifecycleEntryPointsUseSharedAuthority(): void
    {
        $root=dirname(__DIR__,2);
        $first=file_get_contents($root.'/api/account/action-center-claim.php');
        $second=file_get_contents($root.'/api/account/action-center-redeem.php');
        self::assertIsString($first);
        self::assertIsString($second);
        self::assertStringContainsString('mg_microgift_integrity_claim',$first);
        self::assertStringContainsString('mg_microgift_integrity_location_allowed',$second);
        self::assertStringContainsString('mg_microgift_redeem',$second);
        self::assertStringContainsString('mg_require_csrf_for_write',$first.$second);
    }
}

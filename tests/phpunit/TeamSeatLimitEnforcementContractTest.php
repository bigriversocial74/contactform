<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TeamSeatLimitEnforcementContractTest extends TestCase
{
    public function testTeamInviteChecksPackageSeatLimitBeforeNewInvite(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/team.php');

        self::assertIsString($source);
        self::assertStringContainsString('max_team_seats', $source);
        self::assertStringContainsString('mg_package_require_limit_available', $source);
        self::assertStringContainsString('Team seat limit reached.', $source);
        self::assertStringContainsString("status<>'removed'", $source);
        self::assertStringContainsString('invited_email_hash', $source);
    }

    public function testPackageLimitEndpointStillReportsTeamSeatUsage(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/account/package-limits.php');

        self::assertIsString($source);
        self::assertStringContainsString('max_team_seats', $source);
        self::assertStringContainsString('merchant_team_members', $source);
        self::assertStringContainsString('merchant_workspaces', $source);
    }
}

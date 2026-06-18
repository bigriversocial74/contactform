<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SystemStatusContractTest extends TestCase
{
    public function testDashboardRouteExists(): void
    {
        self::assertFileExists(dirname(__DIR__,2).'/system-health.php');
    }
}

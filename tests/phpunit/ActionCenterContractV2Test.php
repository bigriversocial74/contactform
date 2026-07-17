<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterContractV2Test extends TestCase
{
    public function testStaticContractPasses(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/validate_action_center_contract_v2.php';
        self::assertFileExists($script);

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
        exec($command, $output, $status);

        self::assertSame(0, $status, implode(PHP_EOL, $output));
        self::assertStringContainsString('Action Center Contract v2 validation passed', implode(PHP_EOL, $output));
    }
}

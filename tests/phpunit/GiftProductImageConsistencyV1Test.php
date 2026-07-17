<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftProductImageConsistencyV1Test extends TestCase
{
    public function testStaticContractPasses(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/validate_gift_product_image_consistency_v1.php';
        self::assertFileExists($script);

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
        exec($command, $output, $status);

        self::assertSame(0, $status, implode(PHP_EOL, $output));
        self::assertStringContainsString('Gift product image consistency contract passed', implode(PHP_EOL, $output));
    }
}

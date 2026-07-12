<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmDrawerStackingTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testMerchantCrmLoadsDrawerStackingStylesheet(): void
    {
        $source = file_get_contents($this->root() . '/merchant-crm.php');
        self::assertIsString($source);
        self::assertStringContainsString('/assets/css/merchant-crm-drawer-stack.css', $source);
    }

    public function testDrawerStackingCssKeepsTimelineAboveFixedHeader(): void
    {
        $source = file_get_contents($this->root() . '/assets/css/merchant-crm-drawer-stack.css');
        self::assertIsString($source);
        self::assertStringContainsString('.mg-crm-drawer', $source);
        self::assertStringContainsString('.mg-crm-modal', $source);
        self::assertStringContainsString('z-index: 12000 !important', $source);
        self::assertStringContainsString('overflow: hidden', $source);
    }
}

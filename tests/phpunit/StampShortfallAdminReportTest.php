<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampShortfallAdminReportTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root() . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testStampShortfallAdminPageExists(): void
    {
        $page = $this->read('admin/stamp-shortfalls.php');

        foreach ([
            'Stamp Shortfalls',
            'data-stamp-shortfalls-page',
            'Stamp shortfalls',
            'merchant did not have enough Stamps',
            'data-shortfall-count',
            'data-shortfall-total',
            'data-shortfall-required',
            'data-shortfall-merchants',
            'data-shortfall-list',
            '/assets/js/admin-stamp-shortfalls.js',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
    }

    public function testStampShortfallReportUsesSecurityLogEventSource(): void
    {
        $js = $this->read('assets/js/admin-stamp-shortfalls.js');
        $securityApi = $this->read('api/admin/security-logs.php');

        foreach ([
            '/api/admin/security-logs.php?event_type=stamps.merchant_sponsored_regift_shortfall',
            'merchant_sponsored_regift_shortfall',
            'sponsor_user_id',
            'actor_user_id',
            'source_type',
            'source_id',
            'action_key',
            'required',
            'available',
            'shortfall',
            '/admin/users.php?q=',
            '#stamps',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }

        foreach ([
            'event_type',
            'context_json',
            'security_logs',
        ] as $marker) {
            self::assertStringContainsString($marker, $securityApi);
        }
    }

    public function testStampHealthLinksToShortfallReport(): void
    {
        $page = $this->read('admin/stamp-health.php');
        self::assertStringContainsString('/admin/stamp-shortfalls.php', $page);
        self::assertStringContainsString('Shortfall report', $page);
    }
}

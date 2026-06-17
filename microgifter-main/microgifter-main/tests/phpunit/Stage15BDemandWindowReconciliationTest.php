<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/api/demand/_window.php';

final class Stage15BDemandWindowReconciliationTest extends TestCase
{
    public function testSnapshotWindowNormalizesToUtcMidnight(): void
    {
        [$start,$end,$days]=mg_demand_snapshot_window(new DateTimeImmutable('2026-06-10 18:45:00-07:00'),30);
        self::assertSame('2026-06-11 00:00:00',$start->format('Y-m-d H:i:s'));
        self::assertSame('UTC',$start->getTimezone()->getName());
        self::assertSame('2026-07-11 00:00:00',$end->format('Y-m-d H:i:s'));
        self::assertSame(30,$days);
    }

    public function testPointSignalsUseHalfOpenWindowBoundaries(): void
    {
        [$start,$end]=mg_demand_snapshot_window(new DateTimeImmutable('2026-06-10 12:00:00 UTC'),7);
        self::assertFalse(mg_demand_window_overlaps(new DateTimeImmutable('2026-06-09 23:59:59 UTC'),null,$start,$end));
        self::assertTrue(mg_demand_window_overlaps(new DateTimeImmutable('2026-06-10 00:00:00 UTC'),null,$start,$end));
        self::assertTrue(mg_demand_window_overlaps(new DateTimeImmutable('2026-06-16 23:59:59 UTC'),null,$start,$end));
        self::assertFalse(mg_demand_window_overlaps(new DateTimeImmutable('2026-06-17 00:00:00 UTC'),null,$start,$end));
    }

    public function testRangesMustActuallyOverlapTheWindow(): void
    {
        [$start,$end]=mg_demand_snapshot_window(new DateTimeImmutable('2026-06-10 UTC'),7);
        self::assertFalse(mg_demand_window_overlaps(new DateTimeImmutable('2026-05-01 UTC'),new DateTimeImmutable('2026-06-10 00:00:00 UTC'),$start,$end));
        self::assertTrue(mg_demand_window_overlaps(new DateTimeImmutable('2026-05-01 UTC'),new DateTimeImmutable('2026-06-10 00:00:01 UTC'),$start,$end));
        self::assertTrue(mg_demand_window_overlaps(new DateTimeImmutable('2026-06-16 UTC'),new DateTimeImmutable('2026-06-18 UTC'),$start,$end));
        self::assertFalse(mg_demand_window_overlaps(new DateTimeImmutable('2026-06-17 UTC'),new DateTimeImmutable('2026-06-18 UTC'),$start,$end));
    }

    public function testSqlPredicateMatchesPointAndRangeSemantics(): void
    {
        $predicate=mg_demand_window_predicate('psr');
        self::assertStringContainsString("psr.status IN ('outstanding','redeemed')",$predicate);
        self::assertStringContainsString('psr.expected_from<?',$predicate);
        self::assertStringContainsString('psr.expected_to IS NULL AND psr.expected_from>=?',$predicate);
        self::assertStringContainsString('psr.expected_to IS NOT NULL AND psr.expected_to>?',$predicate);
    }
}

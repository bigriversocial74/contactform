<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/deployment-readiness.php';

final class ShareMarketDeploymentReadinessTest extends TestCase
{
    public function testDeployScoreCalculatesPercentage(): void
    {
        $score = mg_share_market_deploy_score([
            ['passed' => true],
            ['passed' => true],
            ['passed' => false],
            ['passed' => false],
        ]);

        self::assertSame(50, $score);
    }

    public function testDeployCheckShape(): void
    {
        $check = mg_share_market_deploy_check('file:test', 'Test file', true, 'File is present.', ['type' => 'file']);

        self::assertSame('file:test', $check['key']);
        self::assertTrue($check['passed']);
        self::assertSame('file', $check['type']);
    }

    public function testFileChecksDetectPresentAndMissingFiles(): void
    {
        $root = dirname(__DIR__, 2);
        $checks = mg_share_market_deploy_file_checks($root, ['account-share-market-admin.php', 'missing-buy-in-file.php']);

        self::assertTrue($checks[0]['passed']);
        self::assertFalse($checks[1]['passed']);
    }

    public function testDeploymentReadinessApiIsGetOnlyAndPermissioned(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/deployment-readiness.php');

        self::assertStringContainsString("mg_require_method('GET')", $api);
        self::assertStringContainsString('Share Market Admin permission is required', $api);
        self::assertStringContainsString('mg_share_market_deployment_readiness', $api);
    }

    public function testDeploymentReadinessPageAndScriptExist(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/account-share-market-deployment-readiness.php');
        $script = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-deployment-readiness.js');

        self::assertStringContainsString('data-share-deploy-root', $page);
        self::assertStringContainsString('/api/admin/share-market/deployment-readiness.php', $script);
        self::assertStringContainsString('Deployment Readiness', $page);
    }

    public function testNoSqlMigrationWasAddedForPhase23(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . '/database/stage_25_buy_in_deployment_readiness.sql');
    }
}

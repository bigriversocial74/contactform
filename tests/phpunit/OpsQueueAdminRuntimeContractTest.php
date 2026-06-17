<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class OpsQueueAdminRuntimeContractTest extends TestCase
{
    public function testAdminPageOverridesApiCspWithNonceRuntimePolicy(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/admin/ops-queue.php');
        self::assertIsString($source);

        foreach([
            'require_once dirname(__DIR__).\'/api/bootstrap.php\'',
            '$cspNonce = bin2hex(random_bytes(16));',
            'header_remove(\'Content-Security-Policy\')',
            'Content-Security-Policy: default-src \'none\'; style-src \'nonce-{$cspNonce}\'; script-src \'nonce-{$cspNonce}\'; connect-src \'self\'; frame-ancestors \'none\'; base-uri \'none\'; form-action \'self\'',
            'style nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, \'UTF-8\'); ?>"',
            'script nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, \'UTF-8\'); ?>"',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAdminRuntimeKeepsOpsEndpointCsrfAndNoClientActor(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/admin/ops-queue.php');
        self::assertIsString($source);

        foreach([
            '$csrfToken = mg_csrf_token();',
            'const api=\'/api/ops/queue.php\'',
            '\'X-CSRF-TOKEN\':csrfToken',
            'csrf_token:csrfToken',
            'await call(\'assign\'',
            'await call(\'resolve\'',
            'await call(\'detail\'',
            'await call(\'list\'',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('actor_user_id', $source);
    }

    public function testAdminAccessGateRemainsPermissioned(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/admin/ops-queue.php');
        self::assertIsString($source);

        foreach([
            '$user = mg_require_api_user();',
            'mg_api_user_has_permission($user, \'ops.alerts.assign\')',
            'mg_api_user_has_permission($user, \'ops.alerts.resolve\')',
            'mg_fail(\'Permission denied.\', 403)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}

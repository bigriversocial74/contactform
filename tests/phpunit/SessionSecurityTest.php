<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/session.php';

final class SessionSecurityTest extends TestCase
{
    private function config(array $security = [], bool $trustProxy = false, string $baseUrl = ''): array
    {
        return [
            'app' => [
                'trust_proxy' => $trustProxy,
                'base_url' => $baseUrl,
            ],
            'security' => array_merge([
                'session_name' => 'mg_session',
                'session_days' => 30,
                'session_cookie_secure' => 'auto',
                'session_cookie_samesite' => 'Lax',
                'session_cookie_path' => '/',
                'session_cookie_domain' => '',
            ], $security),
        ];
    }

    public function testHttpsRequestEnablesSecureCookieInAutoMode(): void
    {
        $options = mg_session_cookie_options(
            $this->config(),
            ['HTTPS' => 'on', 'SERVER_PORT' => '443']
        );

        self::assertTrue($options['secure']);
        self::assertTrue($options['httponly']);
        self::assertSame('Lax', $options['samesite']);
        self::assertSame(30 * 86400, $options['lifetime']);
    }

    public function testHttpRequestDoesNotForceSecureCookieInAutoMode(): void
    {
        $options = mg_session_cookie_options(
            $this->config(),
            ['HTTPS' => 'off', 'SERVER_PORT' => '80']
        );

        self::assertFalse($options['secure']);
    }

    public function testForwardedHttpsIsIgnoredUnlessProxyTrustIsEnabled(): void
    {
        $server = ['HTTP_X_FORWARDED_PROTO' => 'https', 'SERVER_PORT' => '80'];

        self::assertFalse(mg_request_is_https($server, $this->config([], false)));
        self::assertTrue(mg_request_is_https($server, $this->config([], true)));
    }

    public function testHttpsBaseUrlEnablesSecureCookieOutsideRequestContext(): void
    {
        self::assertTrue(mg_request_is_https([], $this->config([], false, 'https://microgifter.com')));
    }

    public function testSameSiteNoneRequiresSecureCookie(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SameSite=None requires a secure session cookie.');

        mg_session_cookie_options(
            $this->config([
                'session_cookie_secure' => 'false',
                'session_cookie_samesite' => 'None',
            ]),
            ['SERVER_PORT' => '80']
        );
    }

    public function testInvalidCookiePathIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        mg_session_cookie_options($this->config(['session_cookie_path' => 'account']));
    }

    public function testInvalidSessionNameIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        mg_session_name($this->config(['session_name' => '123 invalid']));
    }

    public function testApplicationBootstrapStartsSessionThroughCentralPolicy(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/app-core.php');

        self::assertIsString($source);
        self::assertStringContainsString("require_once __DIR__.'/session.php';", $source);
        self::assertStringContainsString('mg_start_session();', $source);
        self::assertStringNotContainsString('session_start();', $source);
    }

    public function testAuthHelperDoesNotBypassCentralSessionPolicy(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/auth.php');

        self::assertIsString($source);
        self::assertStringContainsString('mg_start_session();', $source);
        self::assertStringNotContainsString('session_start();', $source);
    }

    public function testLogoutRevokesServerSessionAndDestroysCookie(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/auth/logout.php');

        self::assertIsString($source);
        self::assertStringContainsString('mg_revoke_current_session($userId);', $source);
        self::assertStringContainsString('mg_destroy_session();', $source);
        self::assertStringNotContainsString('session_regenerate_id(true);', $source);
    }

    public function testSessionBootstrapEnforcesCorePhpSettings(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/session.php');

        self::assertIsString($source);
        self::assertStringContainsString("ini_set('session.use_strict_mode', '1');", $source);
        self::assertStringContainsString("ini_set('session.use_only_cookies', '1');", $source);
        self::assertStringContainsString("ini_set('session.cookie_httponly', '1');", $source);
        self::assertStringContainsString('session_set_cookie_params($options);', $source);
        self::assertStringContainsString('session_destroy()', $source);
        self::assertStringContainsString('setcookie(session_name()', $source);
    }
}

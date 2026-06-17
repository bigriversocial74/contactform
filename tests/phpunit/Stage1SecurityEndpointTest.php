<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage1SecurityEndpointTest extends TestCase
{
    private string $baseUrl;
    private string $cookieFile;

    protected function setUp(): void
    {
        $this->baseUrl = mg_test_base_url();
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'mg_cookie_') ?: sys_get_temp_dir() . '/mg_cookie_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function testPublicHealthIsShallow(): void
    {
        [$status, $body] = $this->request('GET', '/api/health.php');
        $this->assertSame(200, $status);
        $this->assertArrayHasKey('ok', $body);
        $encoded = json_encode($body);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('php_version', $encoded);
        $this->assertStringNotContainsString('database', $encoded);
    }

    public function testProfileRequiresAuthentication(): void
    {
        [$status] = $this->request('GET', '/api/me/profile.php');
        $this->assertSame(401, $status);
    }

    public function testUserSessionsRequireAuthentication(): void
    {
        [$status] = $this->request('GET', '/api/me/sessions.php');
        $this->assertSame(401, $status);
    }

    public function testAdminSessionsRequireAuthentication(): void
    {
        [$status] = $this->request('GET', '/api/admin/sessions.php');
        $this->assertSame(401, $status);
    }

    public function testLoginRejectsMissingCsrf(): void
    {
        [$status] = $this->request('POST', '/api/auth/login.php', [
            'email' => 'security-test@example.test',
            'password' => 'not-a-real-password',
        ]);
        $this->assertContains($status, [419, 422, 429]);
    }

    public function testAuthenticatedUserCanLoadProfileAndSessions(): void
    {
        if (mg_test_skip_authenticated()) {
            $this->markTestSkipped('Authenticated tests skipped for CI without a running app/database.');
        }

        mg_test_register_user($this->cookieFile);

        [$profileStatus, $profileBody] = $this->request('GET', '/api/me/profile.php');
        $this->assertSame(200, $profileStatus);
        $this->assertArrayHasKey('profile', $profileBody['data'] ?? $profileBody);

        [$sessionsStatus, $sessionsBody] = $this->request('GET', '/api/me/sessions.php');
        $this->assertSame(200, $sessionsStatus);
        $this->assertArrayHasKey('sessions', $sessionsBody['data'] ?? $sessionsBody);
    }

    public function testAuthenticatedUserCanUpdateProfileWithCsrf(): void
    {
        if (mg_test_skip_authenticated()) {
            $this->markTestSkipped('Authenticated tests skipped for CI without a running app/database.');
        }

        mg_test_register_user($this->cookieFile);
        $csrf = mg_test_csrf('/account.php', $this->cookieFile);

        [$status, $body] = $this->request('PATCH', '/api/me/profile.php', [
            'display_name' => 'Stage One Updated User',
            'headline' => 'Security test fixture',
            'csrf_token' => $csrf,
        ], ['X-CSRF-Token: ' . $csrf]);

        $this->assertSame(200, $status, json_encode($body));
    }

    public function testAuthenticatedUserCanRevokeOtherSessions(): void
    {
        if (mg_test_skip_authenticated()) {
            $this->markTestSkipped('Authenticated tests skipped for CI without a running app/database.');
        }

        mg_test_register_user($this->cookieFile);
        $csrf = mg_test_csrf('/account.php', $this->cookieFile);

        [$status, $body] = $this->request('DELETE', '/api/me/sessions.php', [
            'scope' => 'all_except_current',
            'csrf_token' => $csrf,
        ], ['X-CSRF-Token: ' . $csrf]);

        $this->assertSame(200, $status, json_encode($body));
    }

    private function request(string $method, string $path, ?array $payload = null, array $headers = []): array
    {
        return mg_test_request($method, $path, $payload, $this->cookieFile, $headers);
    }
}

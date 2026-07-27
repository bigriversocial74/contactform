<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomeServerReleaseDistributionContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testReleaseDistributionFilesExist(): void
    {
        foreach ([
            '/database/20260727_homeserver_release_distribution_v1.sql',
            '/includes/homeserver-releases.php',
            '/api/admin/homeserver-releases.php',
            '/api/homeserver/latest-release.php',
            '/api/homeserver/download.php',
            '/admin/homeserver-releases.php',
            '/assets/js/admin-homeserver-releases.js',
            '/assets/css/admin-homeserver-releases.css',
        ] as $path) {
            self::assertFileExists($this->root . $path, $path);
        }
    }

    public function testMigrationCreatesReleaseAndDownloadLedgers(): void
    {
        $sql = file_get_contents($this->root . '/database/20260727_homeserver_release_distribution_v1.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS homeserver_releases', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS homeserver_release_downloads', $sql);
        self::assertStringContainsString('checksum_sha256', $sql);
        self::assertStringContainsString('minimum_supported_version', $sql);
        self::assertStringContainsString('mandatory_update', $sql);
        self::assertStringContainsString('download_count', $sql);
        $manifest = file_get_contents($this->root . '/config/migrations.php');
        self::assertIsString($manifest);
        self::assertStringContainsString("'20260727_homeserver_release_distribution_v1.sql'", $manifest);
    }

    public function testInstallerUploadsAreProtectedAndVerified(): void
    {
        $helper = file_get_contents($this->root . '/includes/homeserver-releases.php');
        $admin = file_get_contents($this->root . '/api/admin/homeserver-releases.php');
        self::assertIsString($helper);
        self::assertIsString($admin);
        self::assertStringContainsString("\$signature !== 'MZ'", $helper);
        self::assertStringContainsString("pathinfo((string)(\$file['name'] ?? ''), PATHINFO_EXTENSION)", $helper);
        self::assertStringContainsString("hash_file('sha256'", $helper);
        self::assertStringContainsString('mg_storage_store_uploaded_file', $admin);
        self::assertStringContainsString("'persistent_local'", $admin);
        self::assertStringContainsString("mg_require_permission('admin.settings.manage')", $admin);
        self::assertStringContainsString('mg_require_csrf_for_write', $admin);
    }

    public function testLatestMetadataAndDownloadsRequireAuthentication(): void
    {
        $latest = file_get_contents($this->root . '/api/homeserver/latest-release.php');
        $download = file_get_contents($this->root . '/api/homeserver/download.php');
        self::assertIsString($latest);
        self::assertIsString($download);
        self::assertStringContainsString('mg_require_api_user()', $latest);
        self::assertStringContainsString('mg_require_api_user()', $download);
        self::assertStringContainsString('mg_homeserver_release_record_download', $download);
        self::assertStringContainsString('Content-Disposition: attachment', $download);
        self::assertStringContainsString('X-Microgifter-HomeServer-Version', $download);
        self::assertStringContainsString('X-Microgifter-Download-ID', $download);
    }

    public function testStatusModalIncludesDownloadAndRightAlignedIndicator(): void
    {
        $script = file_get_contents($this->root . '/assets/js/homeserver-status-indicator.js');
        $styles = file_get_contents($this->root . '/assets/css/homeserver-status-indicator.css');
        self::assertIsString($script);
        self::assertIsString($styles);
        self::assertStringContainsString('/api/homeserver/latest-release.php', $script);
        self::assertStringContainsString('Download .exe', $script);
        self::assertStringContainsString('Update available', $script);
        self::assertStringContainsString('/admin/homeserver-releases.php', $script);
        self::assertStringContainsString('margin-left:auto', $styles);
        self::assertStringContainsString('float:right', $styles);
        self::assertStringContainsString('.mg-homeserver-release-download', $styles);
    }
}

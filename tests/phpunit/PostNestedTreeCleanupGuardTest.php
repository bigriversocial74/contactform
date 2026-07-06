<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PostNestedTreeCleanupGuardTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function source(string $path): string
    {
        $fullPath = $this->root() . '/' . ltrim($path, '/');
        self::assertFileExists($fullPath, $path);
        $source = file_get_contents($fullPath);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testRemovedNestedTreeAndObsoleteWorkflowStayAbsent(): void
    {
        self::assertDirectoryDoesNotExist($this->root() . '/microgifter-main');
        self::assertFileDoesNotExist($this->root() . '/.github/workflows/unzip.yml');
    }

    public function testReleasePackagingNoLongerNamesRemovedNestedTree(): void
    {
        foreach ([
            'scripts/build_release_artifact.sh',
            'scripts/validate_release_rollback.sh',
            'tests/phpunit/V1ReleasePackagingContractTest.php',
        ] as $path) {
            self::assertStringNotContainsString('microgifter-main', $this->source($path), $path);
        }
    }

    public function testFileReferenceDiagnosticsCatalogTargetsCurrentReviewPaths(): void
    {
        $endpoint = $this->source('api/admin/legacy-file-diagnostics.php');

        foreach (['index-content.php', 'includes/landing/index-v3', 'index.php', 'tests/phpunit/AgenticIndexOnboardingTest.php'] as $path) {
            self::assertStringContainsString($path, $endpoint);
        }

        foreach (['microgifter_main_index', 'microgifter_main_index_content', 'nested_agentic_index_onboarding_test', 'microgifter-main'] as $removedNeedle) {
            self::assertStringNotContainsString($removedNeedle, $endpoint);
        }

        self::assertStringContainsString("'read_only' => true", $endpoint);
        self::assertStringContainsString('catalog_version', $endpoint);
        self::assertStringContainsString("'delete' . '_ready'", $endpoint);
    }

    public function testSystemHealthFileReferenceTabStillHasRequiredClientWiring(): void
    {
        $page = $this->source('admin/system-health.php');
        $script = $this->source('assets/js/admin-file-reference-diagnostics.js');

        foreach (['data-health-tab="file-reference"', 'data-file-reference-diagnostics', 'data-file-reference-refresh', 'data-file-reference-summary', 'data-file-reference-metrics', 'data-file-reference-items', 'data-file-reference-findings'] as $marker) {
            self::assertStringContainsString($marker, $page);
        }

        foreach (['/api/admin/legacy-file-diagnostics.php', 'renderFileReferences', 'counts.delete_ready', 'catalog_version', 'Action-ready'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
    }

    public function testActiveRootDocsDoNotReintroduceRemovedTreeName(): void
    {
        foreach ([
            'README.md',
            'docs/architecture/current_active_file_map.md',
            'docs/stages/v1_release_hardening.md',
            'docs/audits/claim_voucher_qr_scanner_audit.md',
        ] as $path) {
            self::assertStringNotContainsString('microgifter-main', $this->source($path), $path);
        }
    }
}

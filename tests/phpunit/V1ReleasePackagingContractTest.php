<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class V1ReleasePackagingContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testReleaseArtifactIsCommitBoundProductionOnlyAndSecretFree(): void
    {
        $script = $this->source('scripts/build_release_artifact.sh');
        foreach ([
            'git rev-parse "${REF}^{commit}"',
            'git archive --format=tar',
            '--no-dev',
            '--optimize-autoloader',
            'gzip -n',
            'RELEASE.json',
            'migration_manifest_sha256',
            'vendor/autoload.php',
            'api/config.local.php',
            'microgifter-main',
            'sha256sum --check',
        ] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
    }

    public function testBackupValidationUsesConsistentDumpAndIsolatedRestore(): void
    {
        $script = $this->source('scripts/validate_database_backup_restore.sh');
        foreach ([
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            'RESTORE_DB',
            'backup_restore_canary_',
            'schema_migrations',
            'validate_migration_manifest.php',
            '--keep-backup',
            'canonical_migration_manifest_verified',
        ] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
        self::assertStringContainsString('Restore validation database must differ from the source database.', $script);
    }

    public function testRollbackValidationRequiresChecksummedDistinctArtifacts(): void
    {
        $script = $this->source('scripts/validate_release_rollback.sh');
        foreach ([
            'verify_checksum "${CURRENT_ARTIFACT}"',
            'verify_checksum "${ROLLBACK_ARTIFACT}"',
            'Rollback artifact must reference a different commit.',
            'php -l',
            'restore_predeployment_database_backup',
            'Do not run reverse migrations.',
        ] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
    }

    public function testReleaseWorkflowBuildsReproducibleCandidateRollbackAndEvidence(): void
    {
        $workflow = $this->source('.github/workflows/release-package-validation.yml');
        foreach ([
            'name: Release Package Validation',
            'fetch-depth: 0',
            'composer validate --strict',
            'validate_database_backup_restore.sh',
            'Build candidate artifact twice',
            'Build rollback artifact from base commit',
            'validate_release_rollback.sh',
            'build_release_evidence.php',
            'microgifter-candidate-release-',
            'microgifter-rollback-release-',
            'microgifter-release-evidence-',
        ] as $needle) {
            self::assertStringContainsString($needle, $workflow);
        }
    }

    public function testCombinedEvidenceBlocksApprovalUntilLiveGatesPass(): void
    {
        $builder = $this->source('scripts/build_release_evidence.php');
        foreach ([
            "'candidate_uploaded_to_staging'",
            "'stripe_test_provider_boundary'",
            "'hosted_checkout_and_signed_webhook'",
            "'end_to_end_fulfillment_transfer_and_redemption'",
            "'deployed_launch_readiness'",
            "'status' => 'blocked'",
        ] as $needle) {
            self::assertStringContainsString($needle, $builder);
        }
    }

    public function testOperatorRunbookPreservesConfigurationMediaAndDatabaseRollback(): void
    {
        $runbook = $this->source('docs/deployment/v1_staging_release_runbook.md');
        foreach ([
            'Do not extract over the active release.',
            'api/config.local.php',
            'MG_MEDIA_STORAGE_ROOT',
            'validate_database_backup_restore.sh',
            'php scripts/run_migrations.php',
            'php scripts/validate_launch_readiness.php',
            'Do not attempt reverse migrations.',
            'Hosted Checkout and signed webhook',
        ] as $needle) {
            self::assertStringContainsString($needle, $runbook);
        }
    }

    public function testComposerExposesReleaseCommands(): void
    {
        $composer = json_decode($this->source('composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('bash scripts/build_release_artifact.sh', $composer['scripts']['build-release'] ?? null);
        self::assertSame('bash scripts/validate_database_backup_restore.sh', $composer['scripts']['validate-backup-restore'] ?? null);
        self::assertSame('bash scripts/validate_release_rollback.sh', $composer['scripts']['validate-release-rollback'] ?? null);
    }
}

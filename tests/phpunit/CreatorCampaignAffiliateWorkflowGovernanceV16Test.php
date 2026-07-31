<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignAffiliateWorkflowGovernanceV16Test extends TestCase
{
    private function repositoryPath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }

    public function testCreatorAffiliateCertificationUsesConsolidatedGovernance(): void
    {
        self::assertFileExists(
            $this->repositoryPath('.github/workflows/creator-campaign-phases-1-15-production-audit-v1.yml'),
            'The consolidated Creator Campaign production audit must remain available.'
        );

        self::assertFileDoesNotExist(
            $this->repositoryPath('.github/workflows/creator-affiliate-emergency-queue-cleanup.yml'),
            'The temporary queue-cancellation workflow must never be retained.'
        );

        self::assertFileDoesNotExist(
            $this->repositoryPath('.github/workflows/creator-affiliate-operations-v16.yml'),
            'Creator affiliate v16 must use the consolidated Creator Campaign audit instead of a duplicate workflow.'
        );
    }
}

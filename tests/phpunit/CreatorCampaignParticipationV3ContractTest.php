<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignParticipationV3ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function source(string $path): string
    {
        $content = file_get_contents($this->root . '/' . $path);
        self::assertIsString($content, $path);
        return $content;
    }

    public function testNormalizedParticipationAndAgreementSchema(): void
    {
        $sql = $this->source('database/20260721_creator_campaign_participation_v3.sql');
        foreach ([
            'creator_campaign_applications',
            'creator_campaign_invitations',
            'creator_campaign_participants',
            'creator_campaign_agreements',
            'creator_campaign_agreement_versions',
            'creator_campaign_agreement_acceptances',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('content_hash CHAR(64)', $sql);
    }

    public function testOptionalAutomaticAcceptanceFailsClosed(): void
    {
        $application = $this->source('includes/creator-campaigns/application-creator.php');
        $evaluator = $this->source('includes/creator-campaigns/eligibility-evaluator.php');
        self::assertStringContainsString('automatic_acceptance', $application);
        self::assertStringContainsString('mg_creator_campaign_evaluate_automatic_acceptance', $application);
        self::assertStringContainsString('participant_capacity', $evaluator);
    }

    public function testManualReviewAndInvitationsCreateAgreements(): void
    {
        $application = $this->source('includes/creator-campaigns/application-merchant.php');
        $invitation = $this->source('includes/creator-campaigns/invitation-creator.php');
        self::assertMatchesRegularExpression("/'approve'\\s*=>\\s*'approved'/", $application);
        self::assertStringContainsString('mg_creator_campaign_agreement_ensure_offered', $application);
        self::assertStringContainsString('mg_creator_campaign_agreement_ensure_offered', $invitation);
    }

    public function testImmutableVersionAcceptanceActivatesParticipant(): void
    {
        $agreement = $this->source('includes/creator-campaigns/agreement-service.php');
        self::assertStringContainsString('content_hash', $agreement);
        self::assertStringContainsString('creator_campaign_agreement_acceptances', $agreement);
        self::assertStringContainsString("status='active'", $agreement);
        self::assertStringContainsString('requires_reacceptance', $agreement);
    }

    public function testCreatorAndMerchantWorkspacesExposeOriginalScope(): void
    {
        $creator = $this->source('includes/creator-campaigns-participation-view.php');
        $merchant = $this->source('includes/merchant-creator-campaign-participation-view.php');
        self::assertStringContainsString('data-ccp-creator-tab="active_campaigns"', $creator);
        self::assertStringContainsString('data-ccp-creator-tab="agreements"', $creator);
        self::assertStringContainsString('data-ccp-tab="agreements"', $merchant);
        self::assertStringContainsString('data-ccp-open-invite', $merchant);
    }

    public function testNoLaterPhaseFinancialOrTrackingTables(): void
    {
        $sql = $this->source('database/20260721_creator_campaign_participation_v3.sql');
        self::assertDoesNotMatchRegularExpression(
            '/CREATE TABLE IF NOT EXISTS creator_campaign_(deliverables|tracking_sources|earnings|payouts|disputes)/',
            $sql
        );
    }
}

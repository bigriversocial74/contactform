<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantSnapshotActionCenterV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testMerchantAgentLoadsSnapshotActionCenterAssetsOnlyInsideAuthorizedRuntime(): void
    {
        $source=$this->source('merchant-agent-chat.php');
        self::assertStringContainsString("\$merchantAgentAllowed ? ' data-merchant-agent-chat' : ''",$source);
        self::assertStringContainsString('merchant-agent-snapshot-action-center.css?v=1.0.0',$source);
        self::assertStringContainsString('merchant-agent-snapshot-action-center.js?v=1.0.0',$source);
    }

    public function testSnapshotActionCenterProvidesOperationalConversationTools(): void
    {
        $source=$this->source('assets/js/merchant-agent-snapshot-action-center.js');
        foreach([
            '[7, 30, 90]',
            "document.createElement('details')",
            'mg-agent-snapshot-table',
            'data-agent-snapshot-prompt',
            'data-agent-snapshot-export',
            'window.print()',
            'Retry snapshot',
            'No stored activity was found for this window.',
            'new MutationObserver(enhance)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('Microgifter.post(',$source);
    }

    public function testSnapshotActionCenterKeepsDatabaseOnlyPrivacyContract(): void
    {
        $source=$this->source('includes/ai/merchant-agent-snapshot.php');
        self::assertStringContainsString("'database_only' => true",$source);
        self::assertStringContainsString("'external_ai_called' => false",$source);
        self::assertStringContainsString("'customer_details_included' => false",$source);
        self::assertStringContainsString("'payment_credentials_included' => false",$source);
        self::assertStringContainsString("'claim_codes_included' => false",$source);
    }

    public function testSnapshotActionCenterHasResponsiveAndPrintLayouts(): void
    {
        $source=$this->source('assets/css/merchant-agent-snapshot-action-center.css');
        self::assertStringContainsString('.mg-agent-snapshot-actions{display:grid;grid-template-columns:repeat(2',$source);
        self::assertStringContainsString('@media(max-width:900px)',$source);
        self::assertStringContainsString('.mg-agent-snapshot-actions{grid-template-columns:1fr}',$source);
        self::assertStringContainsString('@media(max-width:620px)',$source);
        self::assertStringContainsString('@media print',$source);
    }
}

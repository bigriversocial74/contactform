<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampFreeMessagesContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testActionCenterFollowUpMessagesDoNotDebitStamps(): void
    {
        $source = $this->read('api/account/action-center-follow-up.php');

        self::assertStringContainsString("'follow_up'", $source);
        self::assertStringContainsString('$stampLedger=null;', $source);
        self::assertStringNotContainsString('mg_stamp_debit_send', $source);
        self::assertStringNotContainsString("'action_center_follow_up'", $source);
    }

    public function testCampaignStampUiShowsBuyStampsPromptOnStampFailure(): void
    {
        $source = $this->read('assets/js/stage12-campaign-send.js');

        self::assertStringContainsString('/merchant-stamps.php#stamp-purchases', $source);
        self::assertStringContainsString('Buy Stamps', $source);
        self::assertStringContainsString('continue campaign distribution', $source);
    }
}

<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11EActionCenterActionWiringTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $content = file_get_contents($this->root . '/' . $path);
        self::assertIsString($content, $path);
        return $content;
    }

    public function testSendUsesCanonicalOwnershipProjectionAndTimestampAuthorities(): void
    {
        $source = $this->read('api/account/action-center-send.php');
        self::assertStringContainsString('mg_pppm_transfer_owner_canonical(', $source);
        self::assertStringContainsString('mg_microgift_delivery_event(', $source);
        self::assertStringContainsString('mg_action_center_sent(', $source);
        self::assertStringContainsString('mg_require_csrf_for_write(', $source);
        self::assertStringContainsString("['issued','delivered']", $source);
        self::assertStringNotContainsString("['issued','delivered','claim_pending']", $source);
        self::assertStringContainsString("'sent_at'=>\$deliveryEvent['occurred_at']", $source);
        self::assertStringContainsString('recipientUserId', $source);
        self::assertStringNotContainsString('INSERT INTO pppm_items', $source);
    }

    public function testResendUsesSameOwnerAndImmutableDeliveryEvent(): void
    {
        $source = $this->read('api/account/action-center-resend.php');
        self::assertStringContainsString("folder']!=='sent'", $source);
        self::assertStringContainsString("owner_user_id']!==\$recipientUserId", $source);
        self::assertStringContainsString("\$pdo,\$instance,'resent'", preg_replace('/\s+/', '', $source));
        self::assertStringContainsString("'resent_at'=>\$deliveryEvent['occurred_at']", $source);
        self::assertStringNotContainsString('mg_pppm_transfer_owner_canonical(', $source);
        self::assertStringNotContainsString('UPDATE pppm_items', $source);
    }

    public function testClaimUsesCanonicalClaimReplayAndLifecycleProjection(): void
    {
        $source = $this->read('api/account/action-center-claim.php');
        self::assertStringContainsString('mg_microgift_assert_claim_replay(', $source);
        self::assertStringContainsString('mg_microgift_claim(', $source);
        self::assertStringContainsString('mg_action_center_project_lifecycle(', $source);
        self::assertStringContainsString('recipient_user_id', $source);
        self::assertStringContainsString("['issued','delivered','claim_pending']", $source);
        self::assertLessThan(strpos($source, '$pdo->commit()'), strpos($source, 'mg_action_center_project_lifecycle('));
    }

    public function testMessageUsesDurableMessagingAuthority(): void
    {
        $source = $this->read('api/account/action-center-message.php');
        self::assertStringContainsString('messages/_messaging.php', $source);
        self::assertStringContainsString('mg_message_microgift_participants(', $source);
        self::assertStringContainsString('mg_message_send_microgift(', $source);
        self::assertStringContainsString('idempotency_key', $source);
        self::assertStringNotContainsString('INSERT INTO events', $source);
        self::assertStringNotContainsString('UPDATE microgift_instances', $source);
        self::assertStringNotContainsString('mg_action_center_project_lifecycle(', $source);
    }

    public function testFrontendPostsToTimestampedActionCenterEndpoints(): void
    {
        $source = $this->read('assets/js/gift-action-center-actions.js');
        self::assertStringContainsString("'/api/account/action-center-'", $source);
        foreach (['send', 'resend', 'claim', 'message'] as $action) {
            self::assertStringContainsString("'{$action}'", $source);
        }
        self::assertStringContainsString('Microgifter.post(', $source);
        self::assertStringContainsString('idempotency_key', $source);
        $layout = $this->read('includes/gift-action-center.php');
        self::assertStringContainsString('/assets/js/gift-action-center-actions.js', $layout);
    }
}

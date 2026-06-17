<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterMutationEndpointContractTest extends TestCase
{
    public function testSendEndpointUsesSchemaCompatibleRecipientLookupAndCanonicalTransfer(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-send.php');
        self::assertIsString($source);

        foreach([
            "require_once dirname(__DIR__) . '/microgifts/_lifecycle.php'",
            "require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php'",
            "require_once dirname(__DIR__) . '/pppm/_ownership.php'",
            'mg_action_center_users_have_public_id(PDO $pdo): bool',
            "SHOW COLUMNS FROM users LIKE 'public_id'",
            'mg_pppm_transfer_owner_canonical(',
            "'action_center_send'",
            "SET issuer_user_id=?,owner_user_id=?,recipient_user_id=?,status='delivered'",
            'mg_action_center_sent($pdo,(int)$instance[\'id\'],(int)$user[\'id\'],$recipientUserId)',
            "mg_event('microgift.sent'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringContainsString("SELECT id FROM users WHERE (public_id=? OR email=?) AND status='active' LIMIT 1",$source);
        self::assertStringContainsString("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1",$source);
        self::assertStringNotContainsString('users.public_id',$source);
    }

    public function testClaimEndpointRequiresIdempotencyAndUsesCanonicalClaim(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-claim.php');
        self::assertIsString($source);

        foreach([
            "require_once dirname(__DIR__) . '/microgifts/_lifecycle.php'",
            "require_once dirname(__DIR__) . '/microgifts/_idempotency.php'",
            "require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php'",
            "Action Center item id and idempotency key are required.",
            'mg_microgift_assert_claim_replay($pdo,$idempotencyKey,(string)$instance[\'public_id\'],(int)$user[\'id\'])',
            'mg_microgift_claim($pdo,(int)$user[\'id\'],$input)',
            'mg_action_center_project_lifecycle($pdo,$instance)',
            "mg_audit('action_center.microgift_claimed'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('$idempotencyKey!==\'\'', $source);
    }

    public function testMessageEndpointUsesCanonicalMicrogiftMessaging(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-message.php');
        self::assertIsString($source);

        foreach([
            "require_once dirname(__DIR__) . '/messages/_messaging.php'",
            'mg_message_microgift_participants($instance)',
            'mg_message_send_microgift(',
            'recipient_user_id',
            "mg_audit('action_center.message_sent'",
            "'status'=>'accepted'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}

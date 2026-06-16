<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class ActionCenterSendRecipientAutocompleteContractTest extends TestCase
{
    public function testRecipientSearchEndpointUsesFollowersWhenAvailableAndFallsBackToUsers(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-recipient-search.php');
        self::assertIsString($source);

        foreach([
            'mg_require_method(\'GET\')',
            '$q=mb_substr(trim((string)($_GET[\'q\']??\'\')),0,80)',
            'mg_ac_table_exists(PDO $pdo,string $table)',
            "mg_ac_table_exists(\$pdo,'user_followers')",
            "mg_ac_table_exists(\$pdo,'followers')",
            "INNER JOIN users u ON u.id=f.follower_user_id",
            "FROM users",
            "status='active'",
            "'recipient_user_id'=>(string)\$row['recipient_user_id']",
            "'display_name'=>(string)(\$row['display_name']??'Recipient')",
            "'recipients'=>array_map",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testSendEndpointAcceptsCanonicalRecipientUserId(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-send.php');
        self::assertIsString($source);

        foreach([
            '$recipientReference=trim((string)($input[\'recipient_user_id\']??$input[\'recipient\']??\'\'))',
            'SELECT id FROM users WHERE id=? AND status=\'active\' LIMIT 1',
            'SELECT id FROM users WHERE (public_id=? OR email=?) AND status=\'active\' LIMIT 1',
            'mg_pppm_transfer_owner_canonical',
            'mg_action_center_sent($pdo,(int)$instance[\'id\'],(int)$user[\'id\'],$recipientUserId)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testActionCenterActionsEnhancesSendModalWithTypeahead(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/gift-action-center-actions.js');
        self::assertIsString($source);

        foreach([
            'function enhanceSendAutocomplete()',
            '[data-action-form="send"]',
            'data-recipient-autocomplete',
            'data-recipient-search',
            'name="recipient_user_id"',
            '/api/account/action-center-recipient-search.php?q=',
            'data-recipient-option',
            'request.recipient_user_id=data.recipient_user_id||\'\'',
            'request.recipient=data.recipient_user_id||data.recipient||\'\'',
            "if(type==='send'&&!data.recipient_user_id)",
            'Start typing and choose a follower or user from the recipient list.',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}

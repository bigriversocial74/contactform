<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterSendRecipientAutocompleteContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testRecipientSearchKeepsCanonicalPublicIdentity(): void
    {
        $source=$this->source('api/account/action-center-recipient-search.php');
        foreach([
            "mg_require_method('GET')",
            "mg_ac_table_exists(\$pdo,'user_followers')",
            "mg_ac_table_exists(\$pdo,'followers')",
            'recipient_user_id',
            'display_name',
            'email_hint',
            'recipients',
        ] as $needle) self::assertStringContainsString($needle,$source);
        self::assertStringNotContainsString("'email'=>(string)",$source);
    }

    public function testSendAcceptsUserProfileOrSlugReference(): void
    {
        $source=$this->source('api/account/action-center-send.php');
        foreach([
            "\$input['recipient_user_id']",
            "\$input['recipient']",
            "\$input['recipient_slug']",
            "public_id=? OR email=?",
            'mg_pppm_transfer_owner_canonical',
            'mg_action_center_sent(',
        ] as $needle) self::assertStringContainsString($needle,$source);
        self::assertStringNotContainsString('ctype_digit($reference)',$source);
    }

    public function testMutationClientOwnsRecipientTypeahead(): void
    {
        $source=$this->source('assets/js/gift-action-center-actions.js');
        foreach([
            'function enhanceSendAutocomplete()',
            'data-recipient-autocomplete',
            'data-recipient-search',
            'name="recipient_user_id"',
            '/api/account/action-center-recipient-search.php?q=',
            'data-recipient-option',
            'request.recipient_user_id=data.recipient_user_id',
            'request.recipient=request.recipient_user_id',
            'Start typing and choose a follower or user from the recipient list.',
            'selectedContract()',
        ] as $needle) self::assertStringContainsString($needle,$source);
        self::assertStringNotContainsString('function actionItemFromRow',$source);
    }
}

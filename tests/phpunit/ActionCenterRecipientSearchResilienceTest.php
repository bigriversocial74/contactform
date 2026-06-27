<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterRecipientSearchResilienceTest extends TestCase
{
    public function testRecipientSearchHandlesOptionalIdentityAndRelationshipSchema(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-recipient-search.php');
        self::assertIsString($source);

        foreach([
            'function mg_ac_column_exists(PDO $pdo,string $table,string $column): bool',
            "mg_ac_column_exists(\$pdo,'users','public_id')",
            "return mg_ac_column_exists(\$pdo,'users','public_id') ? \"{\$alias}.public_id\" : \"{\$alias}.email\";",
            "'table'=>'social_follows'",
            "mg_security_log('warning','action_center.recipient_relationship_search_failed'",
            "mg_security_log('error','action_center.recipient_user_search_failed'",
            "mg_ok(['recipients'=>[]]);",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}

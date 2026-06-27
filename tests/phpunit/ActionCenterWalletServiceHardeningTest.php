<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterWalletServiceHardeningTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source);
        return $source;
    }

    public function testSharedWalletServiceExistsAndOwnsWalletStateRules(): void
    {
        $source=$this->source('api/account/_action_center_wallet.php');
        foreach([
            'function mg_ac_wallet_action_id(string $actionItemId): ?string',
            'function mg_ac_wallet_load_for_user(PDO $pdo,string $walletId,int $userId,string $userEmail,bool $forUpdate=true): ?array',
            'function mg_ac_wallet_can_claim(array $row,int $userId): bool',
            'function mg_ac_wallet_can_regift(array $row,int $userId): bool',
            'function mg_ac_wallet_can_message(array $row,int $userId): bool',
            'function mg_ac_wallet_can_tip(array $row,int $userId): bool',
            'function mg_ac_wallet_event(PDO $pdo,array $item,string $eventType,array $context=[]): string',
            'function mg_ac_wallet_merchant_target(PDO $pdo,array $item): array',
            'function mg_ac_wallet_public_item(array $row): array',
            'function mg_ac_wallet_page_merge(PDO $pdo,int $userId,string $email,string $folder,array $page,int $limit=50,string $search=\'\',?array $cursor=null): array',
            "wi.status<>'cancelled'",
            "'can_tip'=>",
            "'can_message'=>",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testWalletListEndpointDelegatesToSharedService(): void
    {
        $source=$this->source('api/account/action-center.php');
        foreach([
            "require_once __DIR__ . '/_action_center_wallet.php';",
            'mg_ac_wallet_counts_merge(',
            'mg_ac_wallet_page_merge(',
            'mg_ac_wallet_user_email($user)',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('function mg_action_center_wallet_public_item',$source);
        self::assertStringNotContainsString('function mg_action_center_wallet_items',$source);
        self::assertStringNotContainsString('function mg_action_center_wallet_counts',$source);
    }

    public function testClaimAndTipUseSharedWalletService(): void
    {
        $claim=$this->source('api/account/action-center-claim.php');
        $tip=$this->source('api/account/action-center-tip.php');
        foreach([$claim,$tip] as $source){
            self::assertStringContainsString("require_once __DIR__ . '/_action_center_wallet.php';",$source);
            self::assertStringContainsString('mg_ac_wallet_action_id($actionItemId)',$source);
            self::assertStringContainsString('mg_ac_wallet_load_for_user($pdo,$walletId,(int)$user[\'id\'],mg_ac_wallet_user_email($user))',$source);
        }
        self::assertStringContainsString('mg_ac_wallet_can_claim($item,$userId)',$claim);
        self::assertStringContainsString('mg_ac_wallet_can_tip($walletItem,(int)$user[\'id\'])',$tip);
        self::assertStringContainsString('mg_ac_wallet_merchant_target($pdo,$walletItem)',$tip);
        self::assertStringContainsString('merchant_workspace_id',$tip);
    }
}

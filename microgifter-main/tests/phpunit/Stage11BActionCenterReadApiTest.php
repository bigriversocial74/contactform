<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11BActionCenterReadApiTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    public function testListEndpointSupportsStableFilteringAndCursorPagination(): void
    {
        $endpoint=file_get_contents($this->root.'/api/account/action-center.php');
        $query=file_get_contents($this->root.'/api/account/_action_center.php');
        self::assertIsString($endpoint);
        self::assertIsString($query);
        self::assertStringContainsString('mg_action_center_search',$endpoint);
        self::assertStringContainsString('mg_action_center_decode_cursor',$endpoint);
        self::assertStringContainsString("'page'=>\$page['page']",$endpoint);
        self::assertStringContainsString('ORDER BY ac.updated_at DESC,ac.id DESC',$query);
        self::assertStringContainsString('ac.updated_at < ? OR (ac.updated_at = ? AND ac.id < ?)',$query);
        self::assertStringContainsString('next_cursor',$query);
        self::assertStringContainsString('has_more',$query);
        self::assertStringContainsString('LIKE ?',$query);
    }

    public function testCountsAndDetailEndpointsRemainUserScoped(): void
    {
        $counts=file_get_contents($this->root.'/api/account/action-center-counts.php');
        $detail=file_get_contents($this->root.'/api/account/action-center-detail.php');
        $query=file_get_contents($this->root.'/api/account/_action_center.php');
        self::assertIsString($counts);
        self::assertIsString($detail);
        self::assertIsString($query);
        self::assertStringContainsString('mg_require_api_user()',$counts);
        self::assertStringContainsString('mg_require_api_user()',$detail);
        self::assertStringContainsString('ac.user_id=? AND ac.public_id=?',$query);
        self::assertStringContainsString('archived_at IS NULL',$query);
        self::assertStringNotContainsString('claim_code_hash',$query);
        self::assertStringNotContainsString('redeem_code_hash',$query);
    }

    public function testReadApisDoNotCreateASecondLifecycleAuthority(): void
    {
        foreach([
            'api/account/action-center.php',
            'api/account/action-center-counts.php',
            'api/account/action-center-detail.php',
            'api/account/_action_center.php',
        ] as $path){
            $source=file_get_contents($this->root.'/'.$path);
            self::assertIsString($source);
            self::assertStringNotContainsString('INSERT INTO',$source,$path);
            self::assertStringNotContainsString('UPDATE microgift_instances',$source,$path);
            self::assertStringNotContainsString('DELETE FROM',$source,$path);
        }
    }
}

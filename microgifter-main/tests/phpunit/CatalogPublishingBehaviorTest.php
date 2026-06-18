<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/api/catalog/_asset_access.php';

final class CatalogPublishingBehaviorTest extends TestCase
{
    private function scalar(PDO $pdo,string $sql,array $params=[]): mixed
    {
        $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
    }

    private function makeUser(PDO $pdo,string $email): int
    {
        $pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES (?,?,?,?, 'active',NOW(),NOW())")
            ->execute([$email,password_hash('CatalogPass!123',PASSWORD_DEFAULT),$email,$email]);
        return (int)$pdo->lastInsertId();
    }

    private function giveRole(PDO $pdo,int $userId,string $role,array $perms): void
    {
        $pdo->prepare('INSERT IGNORE INTO roles (slug,name,created_at) VALUES (?,?,NOW())')->execute([$role,$role]);
        foreach($perms as $perm){
            $pdo->prepare('INSERT IGNORE INTO permissions (slug,name,description,created_at) VALUES (?,?,?,NOW())')->execute([$perm,$perm,$perm]);
            $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at) SELECT r.id,p.id,NOW() FROM roles r, permissions p WHERE r.slug=? AND p.slug=?')->execute([$role,$perm]);
        }
        $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug=?')->execute([$userId,$role]);
    }

    public function testPublishModerationAndAssetPolicyAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed catalog validation requires MG_DB_HOST.');
        $pdo=mg_db();mg_catalog_publish_install($pdo);mg_catalog_asset_access_install($pdo);
        $run='catalog_'.bin2hex(random_bytes(6));
        $pdo->beginTransaction();
        try{
            $merchant=$this->makeUser($pdo,$run.'-merchant@example.test');
            $moderator=$this->makeUser($pdo,$run.'-moderator@example.test');
            $viewer=$this->makeUser($pdo,$run.'-viewer@example.test');
            $this->giveRole($pdo,$merchant,'catalog_merchant',['catalog.products.publish','catalog.assets.manage']);
            $this->giveRole($pdo,$moderator,'catalog_moderator',['catalog.products.moderate']);

            $productPublic=mg_catalog_uuid();$versionPublic=mg_catalog_uuid();$assetPublic=mg_catalog_uuid();
            $pdo->prepare("INSERT INTO catalog_products (public_id,merchant_user_id,product_type,slug,status,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?, 'draft',?,NOW(),NOW())")
                ->execute([$productPublic,$merchant,'digital_product',$run,$merchant]);
            $productId=(int)$pdo->lastInsertId();
            $payload=['title'=>'Catalog Test','description'=>'Test product','unit_value_cents'=>1200,'currency'=>'USD'];
            $checksum=mg_catalog_version_checksum($payload);
            $pdo->prepare("INSERT INTO catalog_product_versions (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,checksum,created_by_user_id,created_at) VALUES (?,?,1,'draft',?,?,?,?,?, ?,NOW())")
                ->execute([$versionPublic,$productId,$payload['title'],$payload['description'],$payload['unit_value_cents'],$payload['currency'],$checksum,$merchant]);
            $versionId=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?, 'ready',NOW(),NOW())")
                ->execute([$assetPublic,$merchant,'download','local',$run.'/asset.pdf','asset.pdf','application/pdf',100]);
            $assetId=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO catalog_product_version_assets (product_version_id,asset_id,role,sort_order,created_at) VALUES (?,?,'download',0,NOW())")
                ->execute([$versionId,$assetId]);
            $versionAssetId=(int)$pdo->lastInsertId();
            mg_catalog_set_asset_policy($pdo,$versionAssetId,'entitled');

            $published=mg_catalog_publish_version($pdo,['actor_user_id'=>$merchant,'merchant_user_id'=>$merchant,'product_public_id'=>$productPublic,'version_public_id'=>$versionPublic,'idempotency_key'=>'catalog:'.$run.':publish']);
            self::assertSame('review',$published['status']);
            self::assertSame('published',(string)$this->scalar($pdo,'SELECT version_status FROM catalog_product_versions WHERE id=?',[$versionId]));
            self::assertSame('review',(string)$this->scalar($pdo,'SELECT status FROM catalog_products WHERE id=?',[$productId]));

            $replay=mg_catalog_publish_version($pdo,['actor_user_id'=>$merchant,'merchant_user_id'=>$merchant,'product_public_id'=>$productPublic,'version_public_id'=>$versionPublic,'idempotency_key'=>'catalog:'.$run.':publish']);
            self::assertTrue($replay['duplicate']);
            self::assertSame($published['event_id'],$replay['event_id']);

            $conflict=false;
            try{mg_catalog_publish_version($pdo,['actor_user_id'=>$merchant,'merchant_user_id'=>$merchant,'product_public_id'=>$productPublic,'version_public_id'=>$versionPublic,'idempotency_key'=>'catalog:'.$run.':publish','reason'=>'changed']);}catch(MgCatalogPublishException $e){$conflict=$e->httpStatus===409;}
            self::assertTrue($conflict);

            $preAccess=mg_catalog_can_view_asset($pdo,$viewer,$productPublic,$assetPublic);
            self::assertFalse($preAccess['allowed']);
            self::assertSame('not_published',$preAccess['reason']);

            $moderated=mg_catalog_moderate_product($pdo,['actor_user_id'=>$moderator,'product_public_id'=>$productPublic,'state'=>'approved','reason'=>'ok','idempotency_key'=>'catalog:'.$run.':moderate']);
            self::assertSame('published',$moderated['status']);
            self::assertSame('approved',(string)$this->scalar($pdo,'SELECT state FROM catalog_moderation_states WHERE product_id=?',[$productId]));

            $privateDecision=mg_catalog_can_view_asset($pdo,$viewer,$productPublic,$assetPublic);
            self::assertFalse($privateDecision['allowed']);
            self::assertSame('private',$privateDecision['reason']);
            mg_catalog_grant_asset_policy($pdo,$viewer,$productId,$assetId);
            $entitledDecision=mg_catalog_can_view_asset($pdo,$viewer,$productPublic,$assetPublic);
            self::assertTrue($entitledDecision['allowed']);
            self::assertSame('entitled',$entitledDecision['reason']);
            mg_catalog_set_asset_policy($pdo,$versionAssetId,'public');
            $publicDecision=mg_catalog_can_view_asset($pdo,null,$productPublic,$assetPublic);
            self::assertTrue($publicDecision['allowed']);
            self::assertSame('public',$publicDecision['reason']);
            self::assertGreaterThanOrEqual(3,(int)$this->scalar($pdo,'SELECT COUNT(*) FROM catalog_asset_access_events WHERE product_id=? AND asset_id=?',[$productId,$assetId]));
            self::assertGreaterThanOrEqual(2,(int)$this->scalar($pdo,"SELECT COUNT(*) FROM audit_logs WHERE user_id IN (?,?) AND action IN ('catalog.version_published','catalog.product_moderated')",[$merchant,$moderator]));
        }finally{
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->prepare('DELETE FROM catalog_asset_access_events WHERE product_id IN (SELECT id FROM catalog_products WHERE slug=?)')->execute([$run]);
            $pdo->prepare('DELETE FROM catalog_asset_access_grants WHERE product_id IN (SELECT id FROM catalog_products WHERE slug=?)')->execute([$run]);
            $pdo->prepare('DELETE FROM catalog_asset_access_policies WHERE product_version_asset_id IN (SELECT pva.id FROM catalog_product_version_assets pva INNER JOIN catalog_product_versions v ON v.id=pva.product_version_id INNER JOIN catalog_products p ON p.id=v.product_id WHERE p.slug=?)')->execute([$run]);
            $pdo->prepare('DELETE FROM catalog_moderation_states WHERE product_id IN (SELECT id FROM catalog_products WHERE slug=?)')->execute([$run]);
            $pdo->prepare('DELETE FROM catalog_publish_events WHERE idempotency_key LIKE ?')->execute(['catalog:'.$run.'%']);
            $pdo->prepare('DELETE pva FROM catalog_product_version_assets pva INNER JOIN catalog_product_versions v ON v.id=pva.product_version_id INNER JOIN catalog_products p ON p.id=v.product_id WHERE p.slug=?')->execute([$run]);
            $pdo->prepare('DELETE a FROM catalog_assets a WHERE a.storage_key LIKE ?')->execute([$run.'/%']);
            $pdo->prepare('DELETE v FROM catalog_product_versions v INNER JOIN catalog_products p ON p.id=v.product_id WHERE p.slug=?')->execute([$run]);
            $pdo->prepare('DELETE FROM catalog_products WHERE slug=?')->execute([$run]);
            $pdo->prepare('DELETE FROM users WHERE email LIKE ?')->execute([$run.'%']);
        }
    }
}

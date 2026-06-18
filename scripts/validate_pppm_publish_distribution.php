<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/catalog/_publish_distribution.php';
require_once dirname(__DIR__) . '/api/profiles/_product_discovery.php';
require_once dirname(__DIR__) . '/api/payments/_fulfillment.php';
require_once dirname(__DIR__) . '/api/microgifts/_atomic_merchant_redemption.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function mg_ppd_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = mg_db();
$runId = 'ppd' . bin2hex(random_bytes(5));
$result = array_fill_keys([
    'category_mapping','canonical_definition','store_distribution','feed_distribution',
    'location_distribution','location_discovery','checkout_uses_canonical_definition',
    'no_issued_units','idempotent_replay','rollback_clean',
], false);

$pdo->beginTransaction();
try {
    $merchantId = mg_it_user($pdo,$runId . '-merchant@example.test','Publish Distribution Merchant');
    $now = gmdate('Y-m-d H:i:s');
    mg_it_insert($pdo,'public_profiles',[
        'public_id'=>mg_public_uuid(),'user_id'=>$merchantId,'slug'=>$runId . '-merchant',
        'display_name'=>'Publish Distribution Merchant','headline'=>'Local Phoenix vouchers',
        'bio'=>'Behavior fixture merchant','location_label'=>'Phoenix, AZ','profile_type'=>'merchant',
        'visibility'=>'public','status'=>'active','completion_score'=>100,'published_at'=>$now,
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    $location = mg_it_location($pdo,$merchantId,$runId);
    $pdo->prepare("UPDATE merchant_locations SET city='Phoenix',region='AZ',postal_code='85001' WHERE id=?")
        ->execute([(int)$location['id']]);

    $payload = [
        'title'=>'Behavior Coffee Voucher','merchant_name'=>'Publish Distribution Merchant',
        'product_category'=>'Voucher','value_cents'=>2500,'currency'=>'USD',
        'headline'=>'Coffee is waiting','message'=>'Enjoy this local coffee voucher.',
        'claim_code_label'=>'Merchant claim code','slug'=>$runId . '-coffee-voucher',
        'visibility'=>'published','location_ids'=>[(string)$location['public_id']],
        'all_locations'=>false,'terms'=>['note'=>'Valid at the selected location.'],
        'expiration_policy'=>['label'=>'No expiration until issued'],'demo'=>false,
    ];
    $productType = mg_catalog_product_type_from_payload('simple_product',$payload);
    mg_ppd_assert($productType === 'voucher','Voucher category was not mapped to catalog voucher type.');
    $result['category_mapping'] = true;

    $productPublicId = mg_catalog_uuid();
    $productId = mg_it_insert($pdo,'catalog_products',[
        'public_id'=>$productPublicId,'merchant_user_id'=>$merchantId,'product_type'=>$productType,
        'slug'=>$payload['slug'],'status'=>'published','created_by_user_id'=>$merchantId,
        'published_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
    ]);
    $versionPublicId = mg_catalog_uuid();
    $versionId = mg_it_insert($pdo,'catalog_product_versions',[
        'public_id'=>$versionPublicId,'product_id'=>$productId,'version_number'=>1,
        'version_status'=>'published','title'=>$payload['title'],'description'=>$payload['message'],
        'unit_value_cents'=>$payload['value_cents'],'currency'=>$payload['currency'],
        'expiration_policy_json'=>json_encode($payload['expiration_policy'],JSON_UNESCAPED_SLASHES),
        'terms_json'=>json_encode($payload['terms'],JSON_UNESCAPED_SLASHES),
        'fulfillment_json'=>json_encode(['builder_type'=>'simple_product'],JSON_UNESCAPED_SLASHES),
        'metadata_json'=>json_encode($payload,JSON_UNESCAPED_SLASHES),
        'checksum'=>hash('sha256',$runId),'created_by_user_id'=>$merchantId,
        'published_at'=>$now,'created_at'=>$now,
    ]);
    $pdo->prepare('UPDATE catalog_products SET current_version_id=? WHERE id=?')->execute([$versionId,$productId]);

    $product = ['id'=>$productId,'public_id'=>$productPublicId,'product_type'=>$productType,'slug'=>$payload['slug']];
    $version = [
        'id'=>$versionId,'public_id'=>$versionPublicId,'title'=>$payload['title'],
        'description'=>$payload['message'],'unit_value_cents'=>$payload['value_cents'],
        'currency'=>$payload['currency'],
        'expiration_policy_json'=>json_encode($payload['expiration_policy'],JSON_UNESCAPED_SLASHES),
        'terms_json'=>json_encode($payload['terms'],JSON_UNESCAPED_SLASHES),
    ];

    $distribution = mg_catalog_publish_distribution($pdo,$merchantId,$product,$version,'simple_product',$payload);

    $definition = $pdo->prepare(
        'SELECT cpt.public_id,cpt.microgift_template_version_id,mv.public_id AS microgift_version_id
         FROM catalog_pppm_templates cpt
         INNER JOIN microgift_template_versions mv ON mv.id=cpt.microgift_template_version_id
         WHERE cpt.product_version_id=? AND cpt.status=\'active\' LIMIT 1'
    );
    $definition->execute([$versionId]);
    $definitionRow = $definition->fetch(PDO::FETCH_ASSOC);
    mg_ppd_assert((bool)$definitionRow,'Published PPPM definition was not created.');
    mg_ppd_assert((string)$definitionRow['microgift_version_id'] === (string)$distribution['definition']['microgift_template_version_id'],'PPPM definition did not link the canonical Microgift version.');
    $result['canonical_definition'] = true;

    $storeCount = (int)mg_it_scalar($pdo,
        "SELECT COUNT(*) FROM merchant_storefront_revision_products rp
         INNER JOIN merchant_storefront_states s ON s.published_revision_id=rp.storefront_revision_id
         INNER JOIN merchant_storefronts ms ON ms.id=s.storefront_id
         WHERE ms.merchant_user_id=? AND rp.catalog_product_id=? AND rp.visibility='visible'",
        [$merchantId,$productId]
    );
    mg_ppd_assert($storeCount === 1,'Published voucher was not added exactly once to the merchant storefront.');
    $result['store_distribution'] = true;

    $feedCount = (int)mg_it_scalar($pdo,
        "SELECT COUNT(*) FROM feed_posts fp
         INNER JOIN feed_post_versions fpv ON fpv.id=fp.current_version_id
         WHERE fp.merchant_user_id=? AND fp.catalog_product_id=? AND fp.status='published'
           AND fp.visibility='public' AND fpv.catalog_product_version_id=? AND fpv.version_status='published'",
        [$merchantId,$productId,$versionId]
    );
    mg_ppd_assert($feedCount === 1,'Published voucher feed post was not created exactly once.');
    $result['feed_distribution'] = true;

    $locationCount = (int)mg_it_scalar($pdo,
        "SELECT COUNT(*) FROM catalog_product_version_locations
         WHERE product_version_id=? AND merchant_location_id=? AND availability_status='available'",
        [$versionId,(int)$location['id']]
    );
    mg_ppd_assert($locationCount === 1,'Published voucher was not associated with the selected merchant location.');
    $result['location_distribution'] = true;

    $search = mg_product_discovery_search($pdo,[
        'q'=>'Behavior Coffee','location'=>'Phoenix','category'=>'voucher','product_limit'=>10,
    ],null);
    $searchIds = array_column($search['items'],'id');
    mg_ppd_assert(in_array($productPublicId,$searchIds,true),'Published voucher was not returned by product-level location discovery.');
    $result['location_discovery'] = true;

    $canonicalVersion = mg_payment_microgift_template_version_for_line($pdo,
        ['merchant_user_id'=>$merchantId],
        ['product_version_id'=>$versionId,'product_id'=>$productId,'public_id'=>mg_catalog_uuid(),
         'title_snapshot'=>$payload['title'],'currency'=>'USD','unit_amount_cents'=>2500]
    );
    mg_ppd_assert($canonicalVersion === (string)$definitionRow['microgift_version_id'],'Checkout did not resolve the canonical published PPPM voucher definition.');
    $result['checkout_uses_canonical_definition'] = true;

    mg_ppd_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE merchant_user_id=?',[$merchantId]) === 0,'Publishing created an issued PPPM unit.');
    mg_ppd_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances WHERE issuer_user_id=?',[$merchantId]) === 0,'Publishing created an issued Microgift instance.');
    $result['no_issued_units'] = true;

    $replay = mg_catalog_publish_distribution($pdo,$merchantId,$product,$version,'simple_product',$payload);
    mg_ppd_assert(!empty($replay['definition']['duplicate']),'Exact publish replay did not reuse the canonical definition.');
    mg_ppd_assert(!empty($replay['storefront']['duplicate']),'Exact publish replay did not reuse storefront placement.');
    mg_ppd_assert(!empty($replay['feed']['duplicate']),'Exact publish replay did not reuse the feed post.');
    mg_ppd_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM catalog_pppm_templates WHERE product_version_id=?',[$versionId]) === 1,'Publish replay duplicated the PPPM definition.');
    mg_ppd_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM feed_posts WHERE merchant_user_id=? AND catalog_product_id=?',[$merchantId,$productId]) === 1,'Publish replay duplicated the feed post.');
    mg_ppd_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM catalog_product_version_locations WHERE product_version_id=?',[$versionId]) === 1,'Publish replay duplicated location associations.');
    $result['idempotent_replay'] = true;

    $pdo->rollBack();
    $result['rollback_clean'] = true;
    echo json_encode($result + ['suite'=>'pppm_publish_distribution'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$error->getMessage() . PHP_EOL);
    exit(1);
}

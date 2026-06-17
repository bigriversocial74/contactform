<?php
declare(strict_types=1);

require_once __DIR__ . '/_storefront.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('storefront.manage');
$userId = (int)$user['id'];
$pdo = mg_db();

if ($method === 'GET') {
    $store = mg_storefront_owned($pdo,$userId);
    $available = mg_storefront_available_products($pdo,$userId);
    if (!$store) {
        mg_ok([
            'storefront'=>null,
            'draft'=>null,
            'published'=>null,
            'products'=>[],
            'available_products'=>$available,
            'readiness'=>mg_storefront_readiness(null,null,[]),
            'public_url'=>null,
            'preview_url'=>'/merchant-storefront-preview.php',
        ]);
    }
    $draft = mg_storefront_revision($pdo,(int)$store['id'],'draft');
    $published = mg_storefront_revision($pdo,(int)$store['id'],'published');
    if ($draft) $draft = mg_storefront_revision_management($pdo,$draft,$userId);
    if ($published) $published = mg_storefront_revision_management($pdo,$published,$userId);
    $active = $draft ?: $published;
    $products = $active ? mg_storefront_revision_products($pdo,(int)$active['id']) : [];
    mg_ok([
        'storefront'=>$store,
        'draft'=>$draft,
        'published'=>$published,
        'products'=>$products,
        'available_products'=>$available,
        'readiness'=>mg_storefront_readiness($store,$active,$products),
        'public_url'=>'/store.php?s=' . rawurlencode((string)$store['slug']),
        'preview_url'=>'/merchant-storefront-preview.php',
    ]);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string)($input['action'] ?? 'save'));

$pdo->beginTransaction();
try {
    $store = mg_storefront_owned($pdo,$userId,true);

    if ($action === 'archive') {
        if (!$store) mg_fail('Storefront not found.',404);
        $pdo->prepare("UPDATE merchant_storefronts SET status='archived',updated_at=NOW() WHERE id=?")->execute([(int)$store['id']]);
        $pdo->commit();
        mg_audit('storefront.archived','merchant_storefront',['storefront_id'=>$store['public_id']],$userId);
        mg_ok(['status'=>'archived'],'Storefront archived.');
    }

    if ($action === 'publish') {
        if (!$store) mg_fail('Storefront not found.',404);
        if ((string)$store['status'] === 'suspended') mg_fail('Suspended storefronts cannot be published.',409);
        $draft = mg_storefront_revision($pdo,(int)$store['id'],'draft');
        if (!$draft) mg_fail('Save a storefront draft before publishing.',422);
        $draftManagement = mg_storefront_revision_management($pdo,$draft,$userId);
        $draftProducts = mg_storefront_revision_products($pdo,(int)$draft['id']);
        $readiness = mg_storefront_readiness($store,$draftManagement,$draftProducts);
        if (empty($readiness['can_publish'])) mg_fail('Complete the required storefront fields before publishing.',422);

        $old = mg_storefront_revision($pdo,(int)$store['id'],'published');
        if ($old) {
            $pdo->prepare("UPDATE merchant_storefront_revisions SET revision_status='retired',updated_at=NOW() WHERE id=?")->execute([(int)$old['id']]);
        }
        $pdo->prepare("UPDATE merchant_storefront_revisions SET revision_status='published',published_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$draft['id']]);
        $pdo->prepare("INSERT INTO merchant_storefront_states (storefront_id,draft_revision_id,published_revision_id,updated_at) VALUES (?,NULL,?,NOW()) ON DUPLICATE KEY UPDATE draft_revision_id=NULL,published_revision_id=VALUES(published_revision_id),updated_at=NOW()")
            ->execute([(int)$store['id'],(int)$draft['id']]);
        $pdo->prepare("UPDATE merchant_storefronts SET display_name=?,headline=?,description=?,logo_asset_id=?,cover_asset_id=?,status='published',published_at=COALESCE(published_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$draft['display_name'],$draft['headline'],$draft['description'],$draft['logo_asset_id'],$draft['cover_asset_id'],(int)$store['id']]);

        $workspace = mg_merchant_workspace($pdo,$userId);
        $pdo->prepare("UPDATE merchant_onboarding_steps SET status='completed',completed_at=NOW(),completed_by_user_id=?,updated_at=NOW() WHERE workspace_id=? AND step_key='storefront'")
            ->execute([$userId,(int)$workspace['id']]);
        $pdo->prepare("UPDATE merchant_onboarding_steps SET status='available',updated_at=NOW() WHERE workspace_id=? AND step_key='payment_readiness' AND status='locked'")
            ->execute([(int)$workspace['id']]);
        $percent = mg_merchant_recalculate_onboarding($pdo,(int)$workspace['id']);
        $pdo->commit();
        mg_audit('storefront.published','merchant_storefront',['storefront_id'=>$store['public_id'],'revision_id'=>$draft['public_id']],$userId);
        mg_ok([
            'status'=>'published',
            'slug'=>$store['slug'],
            'public_url'=>'/store.php?s=' . rawurlencode((string)$store['slug']),
            'onboarding_percent'=>$percent,
        ],'Storefront published.');
    }

    if ($action !== 'save') mg_fail('Invalid storefront action.',422);

    $slug = mg_catalog_slug((string)($input['slug'] ?? ''));
    $displayName = trim((string)($input['display_name'] ?? ''));
    $headline = trim((string)($input['headline'] ?? '')) ?: null;
    $description = trim((string)($input['description'] ?? '')) ?: null;
    if ($displayName === '' || mb_strlen($displayName) > 160) mg_fail('Invalid storefront name.',422);
    if ($headline !== null && mb_strlen($headline) > 240) mg_fail('Storefront headline is too long.',422);
    if ($description !== null && mb_strlen($description) > 5000) mg_fail('Storefront description is too long.',422);

    $slugCheck = $pdo->prepare('SELECT id FROM merchant_storefronts WHERE slug=? AND merchant_user_id<>? LIMIT 1');
    $slugCheck->execute([$slug,$userId]);
    if ($slugCheck->fetchColumn()) mg_fail('Storefront slug is unavailable.',409);

    $logoId = mg_storefront_asset_id($pdo,$userId,trim((string)($input['logo_asset_id'] ?? '')) ?: null);
    $coverId = mg_storefront_asset_id($pdo,$userId,trim((string)($input['cover_asset_id'] ?? '')) ?: null);

    $contactInput = is_array($input['contact'] ?? null) ? $input['contact'] : [];
    $email = trim((string)($contactInput['email'] ?? ''));
    $phone = trim((string)($contactInput['phone'] ?? ''));
    $website = trim((string)($contactInput['website'] ?? ''));
    if ($email !== '' && filter_var($email,FILTER_VALIDATE_EMAIL) === false) mg_fail('Invalid storefront contact email.',422);
    if (mb_strlen($phone) > 80) mg_fail('Storefront contact phone is too long.',422);
    if ($website !== '' && filter_var($website,FILTER_VALIDATE_URL) === false) mg_fail('Invalid storefront website.',422);
    $contact = mg_storefront_json(array_filter(['email'=>$email,'phone'=>$phone,'website'=>$website],static fn(string $v):bool=>$v!==''));

    $themeInput = is_array($input['theme'] ?? null) ? $input['theme'] : [];
    $accent = strtolower(trim((string)($themeInput['accent'] ?? '')));
    if ($accent !== '' && preg_match('/^#[0-9a-f]{6}$/',$accent) !== 1) mg_fail('Accent color must be a six-digit hex value.',422);
    $theme = mg_storefront_json($accent !== '' ? ['accent'=>$accent] : []);
    $products = mg_storefront_normalize_products($pdo,$userId,$input['products'] ?? []);

    if (!$store) {
        $publicId = mg_merchant_uuid();
        $pdo->prepare("INSERT INTO merchant_storefronts (public_id,merchant_user_id,slug,display_name,headline,description,logo_asset_id,cover_asset_id,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'draft',NOW(),NOW())")
            ->execute([$publicId,$userId,$slug,$displayName,$headline,$description,$logoId,$coverId]);
        $store = ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId,'slug'=>$slug,'status'=>'draft'];
    } else {
        $pdo->prepare("UPDATE merchant_storefronts SET slug=?,status=CASE WHEN status='archived' THEN 'draft' ELSE status END,updated_at=NOW() WHERE id=?")
            ->execute([$slug,(int)$store['id']]);
        $store['slug'] = $slug;
    }

    $draft = mg_storefront_revision($pdo,(int)$store['id'],'draft');
    $checksum = mg_storefront_checksum([$displayName,$headline,$description,$logoId,$coverId,$contact,$theme,$products]);
    if (!$draft) {
        $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM merchant_storefront_revisions WHERE storefront_id=?');
        $versionStmt->execute([(int)$store['id']]);
        $revisionPublic = mg_merchant_uuid();
        $pdo->prepare("INSERT INTO merchant_storefront_revisions (public_id,storefront_id,version_number,revision_status,display_name,headline,description,logo_asset_id,cover_asset_id,contact_json,theme_json,checksum,created_by_user_id,created_at,updated_at) VALUES (?,?,?,'draft',?,?,?,?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$revisionPublic,(int)$store['id'],(int)$versionStmt->fetchColumn(),$displayName,$headline,$description,$logoId,$coverId,$contact,$theme,$checksum,$userId]);
        $draft = ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$revisionPublic];
        $pdo->prepare("INSERT INTO merchant_storefront_states (storefront_id,draft_revision_id,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE draft_revision_id=VALUES(draft_revision_id),updated_at=NOW()")
            ->execute([(int)$store['id'],(int)$draft['id']]);
    } else {
        $pdo->prepare("UPDATE merchant_storefront_revisions SET display_name=?,headline=?,description=?,logo_asset_id=?,cover_asset_id=?,contact_json=?,theme_json=?,checksum=?,updated_at=NOW() WHERE id=? AND revision_status='draft'")
            ->execute([$displayName,$headline,$description,$logoId,$coverId,$contact,$theme,$checksum,(int)$draft['id']]);
    }

    $pdo->prepare('DELETE FROM merchant_storefront_revision_products WHERE storefront_revision_id=?')->execute([(int)$draft['id']]);
    $insert = $pdo->prepare('INSERT INTO merchant_storefront_revision_products (storefront_revision_id,catalog_product_id,sort_order,is_featured,visibility,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())');
    foreach ($products as $item) {
        $insert->execute([(int)$draft['id'],$item['catalog_product_id'],$item['sort_order'],$item['is_featured'],$item['visibility']]);
    }
    $pdo->commit();
    mg_audit('storefront.draft_saved','merchant_storefront',['storefront_id'=>$store['public_id'],'revision_id'=>$draft['public_id']],$userId);
    mg_ok([
        'storefront_id'=>$store['public_id'],
        'revision_id'=>$draft['public_id'],
        'slug'=>$slug,
        'status'=>'draft',
        'readiness'=>mg_storefront_readiness($store,array_merge(['display_name'=>$displayName,'headline'=>$headline,'description'=>$description,'logo_asset_id'=>$logoId,'cover_asset_id'=>$coverId,'contact'=>json_decode((string)($contact ?? '[]'),true) ?: []]),array_map(static fn(array $item):array=>['visibility'=>$item['visibility'],'status'=>'published'],$products)),
    ],'Storefront draft saved.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','storefront.save_failed','Storefront save failed.',['action'=>$action,'exception_type'=>get_class($e)],$userId);
    if ($e instanceof RuntimeException) throw $e;
    mg_fail('Unable to save storefront.',500);
}

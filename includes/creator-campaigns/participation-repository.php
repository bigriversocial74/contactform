<?php
declare(strict_types=1);

function mg_creator_campaign_participation_decode_json(mixed $value): mixed
{
    if ($value === null || $value === '') return null;
    if (is_array($value)) return $value;
    $decoded = json_decode((string) $value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
}

function mg_creator_campaign_participation_campaign_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $workspaceId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('campaign_id is required.');
    $sql = 'SELECT cc.*,mw.display_name merchant_name,mw.merchant_user_id
            FROM creator_campaigns cc
            INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
            WHERE cc.public_id=?';
    $params = [$publicId];
    if ($workspaceId !== null) {
        $sql .= ' AND cc.workspace_id=?';
        $params[] = $workspaceId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) throw new RuntimeException('Creator campaign not found.');
    $campaign['geographic_scope'] = mg_creator_campaign_participation_decode_json($campaign['geographic_scope_json'] ?? null);
    $campaign['builder_validation'] = mg_creator_campaign_participation_decode_json($campaign['builder_validation_json'] ?? null);
    return $campaign;
}

function mg_creator_campaign_participation_application_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $campaignId = null,
    ?int $creatorUserId = null,
    bool $forUpdate = false
): array {
    $sql = 'SELECT a.*,cp.display_name creator_display_name,cp.slug creator_slug,
                   u.email creator_email,u.full_name creator_full_name
            FROM creator_campaign_applications a
            INNER JOIN creator_profiles cp ON cp.id=a.creator_profile_id
            INNER JOIN users u ON u.id=a.creator_user_id
            WHERE a.public_id=?';
    $params = [trim($publicId)];
    if ($campaignId !== null) {
        $sql .= ' AND a.campaign_id=?';
        $params[] = $campaignId;
    }
    if ($creatorUserId !== null) {
        $sql .= ' AND a.creator_user_id=?';
        $params[] = $creatorUserId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign application not found.');
    $row['creator_snapshot'] = mg_creator_campaign_participation_decode_json($row['creator_snapshot_json'] ?? null);
    return $row;
}

function mg_creator_campaign_participation_invitation_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $campaignId = null,
    ?int $creatorUserId = null,
    bool $forUpdate = false
): array {
    $sql = 'SELECT i.*,cp.display_name creator_display_name,cp.slug creator_slug,
                   u.email creator_email,u.full_name creator_full_name
            FROM creator_campaign_invitations i
            INNER JOIN creator_profiles cp ON cp.id=i.creator_profile_id
            INNER JOIN users u ON u.id=i.creator_user_id
            WHERE i.public_id=?';
    $params = [trim($publicId)];
    if ($campaignId !== null) {
        $sql .= ' AND i.campaign_id=?';
        $params[] = $campaignId;
    }
    if ($creatorUserId !== null) {
        $sql .= ' AND i.creator_user_id=?';
        $params[] = $creatorUserId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign invitation not found.');
    return $row;
}

function mg_creator_campaign_participation_participant_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $campaignId = null,
    ?int $creatorUserId = null,
    bool $forUpdate = false
): array {
    $sql = 'SELECT p.*,cp.display_name creator_display_name,cp.slug creator_slug,
                   u.email creator_email,u.full_name creator_full_name
            FROM creator_campaign_participants p
            INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
            INNER JOIN users u ON u.id=p.creator_user_id
            WHERE p.public_id=?';
    $params = [trim($publicId)];
    if ($campaignId !== null) {
        $sql .= ' AND p.campaign_id=?';
        $params[] = $campaignId;
    }
    if ($creatorUserId !== null) {
        $sql .= ' AND p.creator_user_id=?';
        $params[] = $creatorUserId;
    }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign participant not found.');
    return $row;
}

function mg_creator_campaign_participation_answer_rows(PDO $pdo, int $applicationId): array
{
    $stmt = $pdo->prepare(
        'SELECT aa.public_id,aa.answer_json,q.public_id question_public_id,q.prompt,q.helper_text,
                q.question_type,q.options_json,q.is_required,q.sort_order
         FROM creator_campaign_application_answers aa
         INNER JOIN creator_campaign_application_questions q ON q.id=aa.question_id
         WHERE aa.application_id=?
         ORDER BY q.sort_order,q.id'
    );
    $stmt->execute([$applicationId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['answer'] = mg_creator_campaign_participation_decode_json($row['answer_json'] ?? null);
        $row['options'] = mg_creator_campaign_participation_decode_json($row['options_json'] ?? null) ?: [];
        unset($row['answer_json'], $row['options_json']);
    }
    unset($row);
    return $rows;
}

function mg_creator_campaign_participation_questions(PDO $pdo, int $campaignId): array
{
    $stmt = $pdo->prepare(
        'SELECT public_id,prompt,helper_text,question_type,options_json,is_required,sort_order
         FROM creator_campaign_application_questions WHERE campaign_id=? ORDER BY sort_order,id'
    );
    $stmt->execute([$campaignId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['options'] = mg_creator_campaign_participation_decode_json($row['options_json'] ?? null) ?: [];
        unset($row['options_json']);
    }
    unset($row);
    return $rows;
}

function mg_creator_campaign_participation_products(PDO $pdo, int $campaignId): array
{
    $stmt = $pdo->prepare(
        "SELECT ccp.relationship_type,ccp.sort_order,ccp.value_snapshot_cents,ccp.currency,
                p.public_id product_public_id,p.slug,p.product_type,
                v.public_id version_public_id,v.title,v.description,v.unit_value_cents,v.currency version_currency
         FROM creator_campaign_products ccp
         INNER JOIN catalog_products p ON p.id=ccp.product_id
         LEFT JOIN catalog_product_versions v ON v.id=ccp.selected_product_version_id
         WHERE ccp.campaign_id=? AND ccp.relationship_type<>'excluded'
         ORDER BY FIELD(ccp.relationship_type,'primary','featured','creator_compensation','commissionable'),ccp.sort_order,ccp.id"
    );
    $stmt->execute([$campaignId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_creator_campaign_participation_summary(PDO $pdo, int $campaignId): array
{
    $queries = [
        'applications' => 'SELECT COUNT(*) FROM creator_campaign_applications WHERE campaign_id=?',
        'pending_applications' => "SELECT COUNT(*) FROM creator_campaign_applications WHERE campaign_id=? AND status IN ('submitted','under_review','information_requested')",
        'invitations' => 'SELECT COUNT(*) FROM creator_campaign_invitations WHERE campaign_id=?',
        'pending_invitations' => "SELECT COUNT(*) FROM creator_campaign_invitations WHERE campaign_id=? AND status='pending'",
        'participants' => "SELECT COUNT(*) FROM creator_campaign_participants WHERE campaign_id=? AND status IN ('approved','agreement_pending','active','completed','suspended')",
        'agreement_pending' => "SELQPХУХS•

ЉH”“УHЬ™X]Ь—ШШ[\ZYЫ—Ь\ќXЪ\[ќИТT‘HШ[\ZYЫ—ЪYOИS‘Э]\ПIШYЬ™Y[Y[ќЬ[™[™ЙИ‹€NВ€	Э[[X\ћHHЧNВ€›Ь™XXЪ
	]Y\љY\И\И	Щ^HO€	Ь[
HВ€	Э]H	ЛOњ™\\™J	Ь[
NВ€	Э]O™^XЭ]JЙШ[\ZYЫ’YJNВ€	Э[[X\ћVЙЩ^WHH
[ќ
H	Э]O™™]ЪЫЫ[[Љ
NВ€B€™]\›€	Э[[X\ћNВџB‚™ќ[Э[Ы€YЧШЬ™X]Ь—ШШ[\ZYЫ—Ь\ќXЪ\][Ы—Щ]™[ќ
€И	Л€\њ^H	]BЉN€[ќВ€	Y[\Э[ЮR\ЪHќ[В€Y€
Y[\J	]VЙЪY[\Э[ЮWЪЩ^IЧJJHВ€	Y[\Э[ЮR\ЪHYЧШЬ™X]Ь—ШШ[\ZYЫ—ЪY[\Э[ЮWЪ\Ъ
€YЧШЬ™X]Ь—ШШ[\ZYЫ—Э[Y]WЪY[\Э[ЮWЪЩ^J	]VЙЪY[\Э[ЮWЪЩ^IЧJB€
NВ€B€	Э]H	ЛOњ™\\™J€	Ъ[”СT•S•ИЬ™X]Ь—ШШ[\ZYЫ—Ь\ќXЪ\][Ы—Щ]™[ќВ€
X›XЧЪYШ[\ZYЫ—ЪY\XШ][Ы—ЪY[ќљ]][Ы—ЪY\ќXЪ\[ќЪYXЭЬ—Э\Щ\—ЪY]™[ќЭ\K€њ›ЫWЬЭ]\ЛЧЬЭ]\Л™X\ЫЫ‹ЫЫќ^ЪњЫЫ‹Y[\Э[ЮWЪ\ЪЬ™X]YШ]
B€ђSQTИ
ЛЛЛЛЛЛЛЛЛЛЛЛ“ХК
JIВ€
NВ€	Э]O™^XЭ]JВ€YЧШЬ™X]Ь—ШШ[\ZYЫ—ЬX›XЧЪY
	ШШЬIКK€
[ќ
H	]VЙШШ[\ZYЫ—ЪY	ЧK€	]VЙШ\XШ][Ы—ЪY	ЧHПИќ[€	]VЙЪ[ќљ]][Ы—ЪY	ЧHПИќ[€	]VЙЬ\ќXЪ\[ќЪY	ЧHПИќ[€
[ќ
H	]VЙШXЭЬ—Э\Щ\—ЪY	ЧK€YЧШЬ™X]Ь—ШШ[\ZYЫ—ЬЭљ[™К	]VЙЩ]™[ќЭ\IЧHПИќ[	Щ]™[ќЭ\IЛLќYJK€	]VЙЩњ›ЫWЬЭ]\ЙЧHПИќ[€	]VЙЭЧЬЭ]\ЙЧHПИќ[€YЧШЬ™X]Ь—ШШ[\ZYЫ—ЬЭљ[™К	]VЙЬ™X\ЫЫ‰ЧHПИќ[	Ь™X\ЫЫ‰ЛL
K€YЧШЬ™X]Ь—ШШ[\ZYЫ—ЪњЫЫ—Щ[ЫЩJ	]VЙШЫЫќ^	ЧHПИќ[
K€	Y[\Э[ЮR\Ъ€JNВ€™]\›€
[ќ
H	ЛO›\Э[њЩ\ќY

NВџB‚™ќ[Э[Ы€YЧШЬ™X]Ь—ШШ[\ZYЫ—Ь\ќXЪ\][Ы—ШЬ™X]Ь—ЬЫ\ЪЭ
И	Л[ќ	Ь™X]Ь•\Щ\’Y
N€\њ^BћВ€	Э]H	ЛOњ™\\™J€”СSPХЬњX›XЧЪYЬ™X]Ь—Ь›Щљ[WЬX›XЧЪYЬ™\Ь^WЫ[YKЬњЫYЛЬљ[ЛЬ›Y]Y]WЪњЫЫ‹€љXY[™K]]\—Э\›ЫЭ™\—Э\››ШШ][Ы—ЫX™[ќЩXњЪ]WЭ\›ЫЫ\][Ы—ЬШЫЬ™B€”“УHЬ™X]Ь—Ь›Щљ[\ИЬ€Q•“ТS€X›XЧЬ›Щљ[\ИУ€ќ\Щ\—ЪYXЬќ\Щ\—ЪY€ТT‘HЬќ\Щ\—ЪYOИSRUH‚€
NВ€	Э]O™^XЭ]JЙЬ™X]Ь•\Щ\’YJNВ€	›ЭИH	Э]O™™]Ъ
ОЋ‘‘UТРTФУРКHО€ЧNВ€	›ЭЦЙЫY]Y]IЧHHYЧШЬ™X]Ь—ШШ[\ZYЫ—Ь\ќXЪ\][Ы—ЩXЫЩWЪњЫЫЉ	›ЭЦЙЫY]Y]WЪњЫЫ‰ЧHПИќ[
NВ€[њЩ]
	›ЭЦЙЫY]Y]WЪњЫЫ‰ЧJNВ€™]\›€	›ЭОВџB‚™ќ[Э[Ы€YЧШЬ™X]Ь—ШШ[\ZYЫ—Ь\ќXЪ\][Ы—ШЬ™X]Ь—ШћWЬX›XЧЪY
И	ЛЭљ[™И	Ь™X]Ь”X›XТY
N€\њ^BћВ€	Э]H	ЛOњ™\\™J€”СSPХЬљYЬ™X]Ь—Ь›Щљ[WЪYЬњX›XЧЪYЬ™X]Ь—Ь›Щљ[WЬX›XЧЪYЬќ\Щ\—ЪY€Ь™\Ь^WЫ[YKЬњЫYЛЬљ[ЛЬњЭ]\ИЬ™X]Ь—Ь›Щљ[WЬЭ]\Л€KњЭ]\И\Щ\—ЬЭ]\ЛK™ќ[Ы[YKK™\Ь^WЫ[YH\Щ\—Щ\Ь^WЫ[YKK™[XZ[€[XKњЭ]\ИЬ™X]Ь—Ш\ЬЪYЫ›Y[ќЬЭ]\Л€љXY[™K]]\—Э\››ШШ][Ы—ЫX™[ќЩXњЪ]WЭ\›ЫЫ\][Ы—ЬШЫЬ™B€”“УHЬ™X]Ь—Ь›Щљ[\ИЬ€S“‘T€“ТS€\Щ\њИHУ€KљYXЬќ\Щ\—ЪY€S“‘T€“ТS€\Щ\—Ы[Щ[И[HУ€[KЫЩOIШЬ™X]Ь‰В€S“‘T€“ТS€\Щ\—Ы[Щ[Ш\ЬЪYЫ›Y[ќИ[XHУ€[XKќ\Щ\—ЪYXЬќ\Щ\—ЪYS‘[XKќ\Щ\—Ы[Щ[ЪY][KљY€Q•“ТS€X›XЧЬ›Щљ[\ИУ€ќ\Щ\—ЪYXЬќ\Щ\—ЪY€ТT‘HЬњX›XЧЪYOИSRUH‚€
NВ€	Э]O™^XЭ]JЭљ[J	Ь™X]Ь”X›XТY
WJNВ€	›ЭИH	Э]O™™]Ъ
ОЋ‘‘UТРTФУРКNВ€Y€
I›ЭКH›ЭИ™]Иќ[ќ[YQ^Щ\[ЫЉ	РЬ™X]Ь€XШЫЭ[ќ›Э›Э[™‰КNВ€Y€
€
Эљ[™КH	›ЭЦЙЭ\Щ\—ЬЭ]\ЙЧHOOH	ШXЭ]™IВ€
Эљ[™КH	›ЭЦЙШЬ™X]Ь—Ш\ЬЪYЫ›Y[ќЬЭ]\ЙЧHOOH	ШXЭ]™IВ€
Эљ[™КH	›ЭЦЙШЬ™X]Ь—Ь›Щљ[WЬЭ]\ЙЧHOOH	ШXЭ]™IВ€
HВ€›ЭИ™]ИЫXZ[‘^Щ\[ЫЉ	УЫ›HXЭ]™H\›Э™YЬ™X]Ь€XШЫЭ[ќИX^H\ќXЪ\]K‰КNВ€B€™]\›€	›ЭОВџB
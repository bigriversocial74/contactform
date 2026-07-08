<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_embed_leads_days(mixed $value): int
{
    $days = (int)$value;
    return in_array($days, [7, 30, 90], true) ? $days : 30;
}

function mg_embed_leads_token(mixed $value, int $max = 120): string
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9_:\-.]+/', '_', $value) ?: '';
    return substr(trim($value, '_:-.'), 0, $max);
}

function mg_embed_leads_table_ready(PDO $pdo, string $table, array $columns): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $found = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach ($columns as $column) if (!in_array($column, $found, true)) return false;
        return true;
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.campaign_embed_leads.table_check_failed', 'Unable to inspect embed leads table.', ['table' => $table, 'exception_class' => $error::class]);
        return false;
    }
}

function mg_embed_leads_json(mixed $json): array
{
    if (is_array($json)) return $json;
    $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function mg_embed_leads_attr(array $metadata): array
{
    if (isset($metadata['embed_attribution']) && is_array($metadata['embed_attribution'])) return $metadata['embed_attribution'];
    if (empty($metadata['website_embed']) && empty($metadata['origin_host']) && empty($metadata['page_url']) && empty($metadata['embed_mode'])) return [];
    return array_filter([
        'website_embed' => !empty($metadata['website_embed']),
        'embed_source' => (string)($metadata['embed_source'] ?? 'website_embed'),
        'origin_host' => (string)($metadata['origin_host'] ?? ''),
        'page_url' => (string)($metadata['page_url'] ?? ''),
        'embed_mode' => (string)($metadata['embed_mode'] ?? ''),
    ], static fn($value) => $value !== '' && $value !== null);
}

function mg_embed_leads_campaign(PDO $pdo, int $merchantId, string $campaignRef): ?array
{
    if ($campaignRef === '') return null;
    if (strlen($campaignRef) > 180) mg_fail('Campaign reference is invalid.', 422);
    $numericId = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
    $stmt = $pdo->prepare('SELECT id, public_id, public_slug, title FROM campaigns WHERE merchant_user_id = ? AND ((? > 0 AND id = ?) OR public_id = ? OR public_slug = ?) LIMIT 1');
    $stmt->execute([$merchantId, $numericId, $numericId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Campaign not found.', 404);
    return $campaign;
}

function mg_embed_leads_campaigns(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare('SELECT public_id, public_slug, title, campaign_type, status FROM campaigns WHERE merchant_user_id = ? ORDER BY id DESC LIMIT 200');
    $stmt->execute([$merchantId]);
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'slug' => $row['public_slug'] ?? null,
        'title' => (string)$row['title'],
        'campaign_type' => (string)$row['campaign_type'],
        'status' => (string)$row['status'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_embed_leads_page_path(string $pageUrl): string
{
    if ($pageUrl === '') return '';
    $path = parse_url($pageUrl, PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : $pageUrl;
}

function mg_embed_leads_page_label(string $pagePath): string
{
    $path = strtolower(trim($pagePath));
    if ($path === '' || $path === '/' || str_contains($path, 'home')) return 'homepage';
    if (str_contains($path, 'menu')) return 'menu page';
    if (str_contains($path, 'special') || str_contains($path, 'deal') || str_contains($path, 'offer')) return 'specials page';
    if (str_contains($path, 'book') || str_contains($path, 'reservation')) return 'booking page';
    if (str_contains($path, 'event')) return 'events page';
    if (str_contains($path, 'gift')) return 'gift page';
    return trim($pagePath) !== '' ? trim($pagePath) : 'website page';
}

function mg_embed_leads_next_page_suggestion(string $topPage): string
{
    $path = strtolower(trim($topPage));
    if ($path === '' || $path === '/' || str_contains($path, 'home')) return 'specials, menu, booking, or events page';
    if (str_contains($path, 'menu')) return 'homepage or specials page';
    if (str_contains($path, 'special') || str_contains($path, 'deal') || str_contains($path, 'offer')) return 'homepage or menu page';
    if (str_contains($path, 'book') || str_contains($path, 'reservation')) return 'homepage or specials page';
    if (str_contains($path, 'event')) return 'homepage or booking page';
    return 'homepage or specials page';
}

function mg_embed_leads_pick_top(array $counts): array
{
    arsort($counts);
    $top = [];
    foreach (array_slice($counts, 0, 8, true) as $value => $count) {
        $top[] = ['value' => (string)$value, 'total' => (int)$count];
    }
    return $top;
}

function mg_embed_leads_quality(array $input): array
{
    $score = 0;
    $signals = [];
    $missing = [];
    $add = static function (bool $condition, int $points, string $signal, string $gap) use (&$score, &$signals, &$missing): void {
        if ($condition) { $score += $points; $signals[] = $signal; }
        else { $missing[] = $gap; }
    };
    $add(!empty($input['is_new_contact']), 20, 'New contact', 'New contact');
    $add(trim((string)($input['email'] ?? '')) !== '', 20, 'Email captured', 'Email');
    $add(trim((string)($input['phone'] ?? '')) !== '', 10, 'Phone captured', 'Phone');
    $add(trim((string)($input['campaign_id'] ?? '')) !== '', 15, 'Campaign context', 'Campaign context');
    $add(trim((string)($input['origin_host'] ?? '')) !== '' || trim((string)($input['page_url'] ?? '')) !== '', 20, 'Website attribution', 'Website attribution');
    $add(trim((string)($input['crm_contact_id'] ?? '')) !== '', 15, 'CRM follow-up link', 'CRM link');
    $ready = trim((string)($input['crm_contact_id'] ?? '')) !== ''
        && trim((string)($input['campaign_id'] ?? '')) !== ''
        && trim((string)($input['source'] ?? '')) !== ''
        && (trim((string)($input['origin_host'] ?? '')) !== '' || trim((string)($input['page_url'] ?? '')) !== '');
    $label = $score >= 85 ? 'High quality' : ($score >= 65 ? 'Ready' : ($score >= 45 ? 'Needs follow-up' : 'Needs context'));
    return [
        'score' => min(100, $score),
        'label' => $label,
        'ready_for_follow_up' => $ready,
        'signals' => array_values($signals),
        'missing' => array_values($missing),
    ];
}

function mg_embed_leads_performance_recommendations(array $context): array
{
    $total = (int)($context['total'] ?? 0);
    if ($total < 1) return ['Run Embed QA or publish a campaign embed to start measuring conversion quality.'];
    $recommendations = [];
    $topPage = (string)($context['top_page']['page_path'] ?? $context['top_page']['page_url'] ?? '');
    $topDomain = (string)($context['top_domain']['origin_host'] ?? '');
    $topSource = (string)($context['top_source']['value'] ?? '');
    $topMode = (string)($context['top_mode']['value'] ?? '');
    $readyRate = (int)($context['ready_rate'] ?? 0);
    $newRate = (int)($context['new_rate'] ?? 0);
    if ($topPage !== '' && (trim($topPage, '/') === '' || str_contains($topPage, 'home') || $topPage === '/')) {
        $recommendations[] = 'Homepage embeds are producing demand. Test this campaign on your specials, menu, booking, or event page too.';
    }
    if ($topDomain !== '') $recommendations[] = 'Keep the embed visible on ' . $topDomain . ' because it is currently the strongest lead domain.';
    if ($topSource !== '') $recommendations[] = ucfirst(str_replace('_', ' ', $topSource)) . ' is the strongest conversion source. Consider reusing that campaign format in your next promotion.';
    if ($topMode !== '') $recommendations[] = ucfirst(str_replace('_', ' ', $topMode)) . ' mode is producing the most leads. Keep that placement style consistent while testing one alternate placement.';
    if ($readyRate < 60) $recommendations[] = 'Improve follow-up readiness by making sure embed forms collect email and keep campaign/contact links connected.';
    if ($newRate >= 50) $recommendations[] = 'New-contact rate is strong. Route these leads into a first-time customer follow-up campaign.';
    return array_values(array_slice(array_unique($recommendations), 0, 5));
}

function mg_embed_leads_placement_action(array $summary): array
{
    $campaign = $summary['campaign'] ?? [];
    $topPage = (string)($summary['top_page']['page_path'] ?? $summary['top_page']['page_url'] ?? '');
    $topDomain = (string)($summary['top_domain']['value'] ?? $summary['top_domain']['origin_host'] ?? '');
    $topSource = (string)($summary['top_source']['value'] ?? '');
    $readyRate = (int)($summary['ready_rate'] ?? 0);
    $qualityScore = (int)($summary['average_quality_score'] ?? 0);
    $leadCount = (int)($summary['total_embed_leads'] ?? 0);
    $currentLabel = mg_embed_leads_page_label($topPage);
    $nextPlacement = mg_embed_leads_next_page_suggestion($topPage);
    $priority = 'Monitor';
    $action = 'Keep collecting embed data before changing placement.';
    $reason = 'This campaign needs more attributed leads before placement recommendations become reliable.';
    if ($leadCount >= 3 && $qualityScore >= 70 && $readyRate >= 60) {
        $priority = 'Scale';
        $action = 'Scale the winning embed placement and test one adjacent page.';
        $reason = 'The current placement is producing ready, higher-quality leads.';
    } elseif ($leadCount >= 2 && $readyRate < 60) {
        $priority = 'Fix follow-up';
        $action = 'Keep placement steady, but improve the form or CRM follow-up path before expanding.';
        $reason = 'Traffic is converting, but too few leads are ready for merchant follow-up.';
    } elseif ($leadCount >= 1) {
        $priority = 'Test';
        $action = 'A/B test this embed on the ' . $nextPlacement . '.';
        $reason = 'You have an early winning placement. Test a nearby purchase-intent page next.';
    }
    return [
        'campaign' => $campaign,
        'priority' => $priority,
        'recommended_action' => $action,
        'reason' => $reason,
        'current_winner' => trim(($topDomain !== '' ? $topDomain : 'website') . ($currentLabel !== '' ? ' · ' . $currentLabel : '')),
        'next_test' => $nextPlacement,
        'source_signal' => $topSource !== '' ? $topSource : 'website_embed',
        'ready_rate' => $readyRate,
        'average_quality_score' => $qualityScore,
        'total_embed_leads' => $leadCount,
    ];
}

function mg_embed_leads_placement_intelligence(array $context): array
{
    $total = (int)($context['total'] ?? 0);
    $topDomain = $context['top_domain'] ?? null;
    $topPage = $context['top_page'] ?? null;
    $topSource = $context['top_source'] ?? null;
    $topMode = $context['top_mode'] ?? null;
    $campaignActions = $context['campaign_actions'] ?? [];
    if ($total < 1) {
        return [
            'recommended_next_action' => 'Publish or QA a website embed, then return here after the first attributed lead.',
            'summary_cards' => [],
            'campaign_actions' => [],
            'experiments' => [['title' => 'First attribution test', 'detail' => 'Run Embed QA or submit a live website embed to create the first placement signal.', 'priority' => 'Start']],
        ];
    }
    $topPagePath = (string)($topPage['page_path'] ?? $topPage['page_url'] ?? '');
    $nextPlacement = mg_embed_leads_next_page_suggestion($topPagePath);
    $domain = (string)($topDomain['origin_host'] ?? 'website');
    $pageLabel = mg_embed_leads_page_label($topPagePath);
    $mode = (string)($topMode['value'] ?? 'embed');
    $source = (string)($topSource['value'] ?? 'website_embed');
    $recommended = 'Keep the best current placement on ' . $domain . ' and test the same campaign on the ' . $nextPlacement . '.';
    $cards = [
        ['label' => 'Recommended next action', 'value' => 'Test adjacent page', 'detail' => $recommended],
        ['label' => 'Best current placement', 'value' => $pageLabel, 'detail' => isset($topPage['total']) ? $topPage['total'] . ' attributed leads from this page.' : 'No page winner yet.'],
        ['label' => 'Winning domain', 'value' => $domain, 'detail' => isset($topDomain['total']) ? $topDomain['total'] . ' attributed leads from this domain.' : 'No domain winner yet.'],
        ['label' => 'Winning mode', 'value' => $mode, 'detail' => isset($topMode['total']) ? $topMode['total'] . ' attributed leads from this embed mode.' : 'No embed-mode winner yet.'],
        ['label' => 'Winning source', 'value' => $source, 'detail' => isset($topSource['total']) ? $topSource['total'] . ' attributed leads from this source.' : 'No source winner yet.'],
    ];
    $experiments = [
        ['title' => 'Placement A/B test', 'detail' => 'Keep the current winning page live and add the same campaign embed to the ' . $nextPlacement . '.', 'priority' => 'High'],
        ['title' => 'Mode test', 'detail' => 'Use ' . $mode . ' as the control placement and test one alternate embed mode for seven days.', 'priority' => 'Medium'],
        ['title' => 'Source test', 'detail' => 'Reuse the ' . str_replace('_', ' ', $source) . ' campaign format for the next merchant promotion.', 'priority' => 'Medium'],
    ];
    return [
        'recommended_next_action' => $recommended,
        'summary_cards' => $cards,
        'campaign_actions' => array_values(array_slice($campaignActions, 0, 8)),
        'experiments' => $experiments,
    ];
}

function mg_embed_leads_csv(array $rows, array $totals): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="campaign-embed-leads-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Created', 'Lead Name', 'Lead Email', 'Lead Quality', 'Quality Score', 'Ready For Follow-Up', 'Campaign', 'Campaign Type', 'Source', 'Origin Host', 'Page URL', 'Embed Mode', 'CRM Contact URL', 'Campaign Contact URL', 'Campaign URL']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['created_at'] ?? '',
            $row['crm_contact']['name'] ?? '',
            $row['crm_contact']['email'] ?? '',
            $row['lead_quality']['label'] ?? '',
            $row['lead_quality']['score'] ?? '',
            !empty($row['lead_quality']['ready_for_follow_up']) ? 'yes' : 'no',
            $row['campaign']['title'] ?? '',
            $row['campaign']['campaign_type'] ?? '',
            $row['source'] ?? '',
            $row['origin_host'] ?? '',
            $row['page_url'] ?? '',
            $row['embed_mode'] ?? '',
            $row['crm_contact']['url'] ?? '',
            $row['campaign_contact']['url'] ?? '',
            $row['campaign']['url'] ?? '',
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Total Embed Leads', (string)($totals['total_embed_leads'] ?? 0)]);
    fputcsv($out, ['Ready For Follow-Up', (string)($totals['ready_for_follow_up'] ?? 0)]);
    fputcsv($out, ['Average Quality Score', (string)($totals['average_quality_score'] ?? 0)]);
    fputcsv($out, ['New Contacts', (string)($totals['new_contacts'] ?? 0)]);
    fputcsv($out, ['Returning Contacts', (string)($totals['returning_contacts'] ?? 0)]);
    fclose($out);
    exit;
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)($user['id'] ?? 0);
$pdo = mg_db();
$days = mg_embed_leads_days($_GET['days'] ?? 30);
$campaignRef = mg_embed_leads_token($_GET['campaign'] ?? $_GET['campaign_id'] ?? '', 180);
$originFilter = mg_embed_leads_token($_GET['origin_host'] ?? '', 190);
$sourceFilter = mg_embed_leads_token($_GET['source'] ?? '', 80);
$format = mg_embed_leads_token($_GET['format'] ?? '', 20);
$campaign = mg_embed_leads_campaign($pdo, $merchantId, $campaignRef);
$cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

$ready = mg_embed_leads_table_ready($pdo, 'merchant_crm_contact_events', ['id','public_id','merchant_user_id','crm_contact_id','campaign_id','source_type','source_public_id','metadata_json','created_at'])
    && mg_embed_leads_table_ready($pdo, 'merchant_crm_contacts', ['id','public_id','merchant_user_id','primary_email','primary_phone','display_name','first_seen_at'])
    && mg_embed_leads_table_ready($pdo, 'campaign_contacts', ['id','public_id','merchant_user_id','campaign_id','email','phone','name','metadata_json'])
    && mg_embed_leads_table_ready($pdo, 'campaigns', ['id','public_id','public_slug','merchant_user_id','title','campaign_type']);

if (!$ready) {
    mg_ok([
        'schema_ready' => false,
        'sql_required' => null,
        'filters' => ['days' => $days, 'campaign' => null, 'origin_host' => $originFilter, 'source' => $sourceFilter],
        'campaigns' => [],
        'totals' => ['total_embed_leads' => 0, 'new_contacts' => 0, 'returning_contacts' => 0, 'campaigns' => 0, 'ready_for_follow_up' => 0, 'average_quality_score' => 0, 'top_domains' => [], 'top_pages' => []],
        'performance' => ['insight_cards' => [], 'quality_breakdown' => [], 'recommendations' => []],
        'placement_intelligence' => ['recommended_next_action' => '', 'summary_cards' => [], 'campaign_actions' => [], 'experiments' => []],
        'campaign_summaries' => [],
        'rows' => [],
    ], 'Campaign embed leads tables are not available yet.');
}

$where = ['e.merchant_user_id = ?', 'e.created_at >= ?'];
$params = [$merchantId, $cutoff];
if ($campaign) { $where[] = 'e.campaign_id = ?'; $params[] = (int)$campaign['id']; }
if ($sourceFilter !== '') { $where[] = 'e.source_type = ?'; $params[] = $sourceFilter; }

$sql = 'SELECT e.public_id event_public_id, e.event_type, e.source_type, e.source_public_id, e.metadata_json event_metadata_json, e.created_at,
               mcc.public_id crm_public_id, mcc.primary_email, mcc.primary_phone, mcc.display_name, mcc.first_seen_at,
               cc.public_id campaign_contact_public_id, cc.email campaign_contact_email, cc.phone campaign_contact_phone, cc.name campaign_contact_name, cc.metadata_json contact_metadata_json,
               c.public_id campaign_public_id, c.public_slug, c.title campaign_title, c.campaign_type
        FROM merchant_crm_contact_events e
        LEFT JOIN merchant_crm_contacts mcc ON mcc.id = e.crm_contact_id AND mcc.merchant_user_id = e.merchant_user_id
        LEFT JOIN campaign_contacts cc ON cc.public_id = e.source_public_id AND cc.merchant_user_id = e.merchant_user_id
        LEFT JOIN campaigns c ON c.id = e.campaign_id AND c.merchant_user_id = e.merchant_user_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY e.id DESC LIMIT 1200';

$rows = [];
$domainCounts = [];
$pageCounts = [];
$sourceCounts = [];
$modeCounts = [];
$qualityBuckets = ['High quality' => 0, 'Ready' => 0, 'Needs follow-up' => 0, 'Needs context' => 0];
$contactIds = [];
$newContactIds = [];
$campaignIds = [];
$campaignSummaries = [];
$totalLeads = 0;
$readyForFollowUp = 0;
$qualityScoreTotal = 0;
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $eventMeta = mg_embed_leads_json($row['event_metadata_json'] ?? null);
        $contactMeta = mg_embed_leads_json($row['contact_metadata_json'] ?? null);
        $attr = mg_embed_leads_attr($eventMeta) ?: mg_embed_leads_attr($contactMeta);
        if (!$attr) continue;
        $originHost = mg_embed_leads_token($attr['origin_host'] ?? '', 190);
        if ($originFilter !== '' && $originHost !== $originFilter) continue;

        $totalLeads++;
        $crmId = (string)($row['crm_public_id'] ?? '');
        $campaignId = (string)($row['campaign_public_id'] ?? '');
        $pageUrl = (string)($attr['page_url'] ?? '');
        $pagePath = mg_embed_leads_page_path($pageUrl);
        $campaignRefOut = (string)($row['public_slug'] ?: $row['campaign_public_id']);
        $campaignUrl = '/merchant-campaigns.php' . ($campaignRefOut !== '' ? '?campaign=' . rawurlencode($campaignRefOut) : '');
        $campaignKey = $campaignId !== '' ? $campaignId : 'unknown';
        $source = (string)($row['source_type'] ?? '');
        $embedMode = (string)($attr['embed_mode'] ?? '');
        $contactEmail = (string)($row['primary_email'] ?: $row['campaign_contact_email'] ?: '');
        $contactPhone = (string)($row['primary_phone'] ?: $row['campaign_contact_phone'] ?: '');
        $contactName = (string)($row['display_name'] ?: $row['campaign_contact_name'] ?: 'Lead');
        $isNewContact = false;

        if ($crmId !== '') {
            $contactIds[$crmId] = true;
            $isNewContact = !empty($row['first_seen_at']) && strtotime((string)$row['first_seen_at']) >= strtotime($cutoff);
            if ($isNewContact) $newContactIds[$crmId] = true;
        }
        if ($campaignId !== '') $campaignIds[$campaignId] = true;
        if ($originHost !== '') $domainCounts[$originHost] = ($domainCounts[$originHost] ?? 0) + 1;
        if ($pageUrl !== '') $pageCounts[$pageUrl] = ($pageCounts[$pageUrl] ?? 0) + 1;
        if ($source !== '') $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
        if ($embedMode !== '') $modeCounts[$embedMode] = ($modeCounts[$embedMode] ?? 0) + 1;

        $campaignContactPublicId = (string)($row['campaign_contact_public_id'] ?: $row['source_public_id']);
        $quality = mg_embed_leads_quality([
            'is_new_contact' => $isNewContact,
            'email' => $contactEmail,
            'phone' => $contactPhone,
            'campaign_id' => $campaignId,
            'origin_host' => $originHost,
            'page_url' => $pageUrl,
            'crm_contact_id' => $crmId,
            'source' => $source,
        ]);
        $qualityBuckets[$quality['label']] = ($qualityBuckets[$quality['label']] ?? 0) + 1;
        $qualityScoreTotal += (int)$quality['score'];
        if (!empty($quality['ready_for_follow_up'])) $readyForFollowUp++;

        if (!isset($campaignSummaries[$campaignKey])) {
            $campaignSummaries[$campaignKey] = [
                'campaign' => ['id' => $campaignId, 'slug' => $row['public_slug'] ?? null, 'title' => (string)($row['campaign_title'] ?? 'Campaign'), 'campaign_type' => (string)($row['campaign_type'] ?? ''), 'url' => $campaignUrl],
                'total_embed_leads' => 0,
                'ready_for_follow_up' => 0,
                'quality_score_total' => 0,
                'latest_lead_at' => null,
                'domains' => [],
                'pages' => [],
                'sources' => [],
            ];
        }
        $campaignSummaries[$campaignKey]['total_embed_leads']++;
        $campaignSummaries[$campaignKey]['quality_score_total'] += (int)$quality['score'];
        if (!empty($quality['ready_for_follow_up'])) $campaignSummaries[$campaignKey]['ready_for_follow_up']++;
        if ($campaignSummaries[$campaignKey]['latest_lead_at'] === null) $campaignSummaries[$campaignKey]['latest_lead_at'] = $row['created_at'] ?? null;
        if ($originHost !== '') $campaignSummaries[$campaignKey]['domains'][$originHost] = ($campaignSummaries[$campaignKey]['domains'][$originHost] ?? 0) + 1;
        if ($pageUrl !== '') $campaignSummaries[$campaignKey]['pages'][$pageUrl] = ($campaignSummaries[$campaignKey]['pages'][$pageUrl] ?? 0) + 1;
        if ($source !== '') $campaignSummaries[$campaignKey]['sources'][$source] = ($campaignSummaries[$campaignKey]['sources'][$source] ?? 0) + 1;

        if ($format !== 'csv' && count($rows) >= 300) continue;
        $contactPublicId = $crmId;
        $rowOut = [
            'lead_event_id' => (string)$row['event_public_id'],
            'created_at' => $row['created_at'] ?? null,
            'event_type' => (string)($row['event_type'] ?? ''),
            'source' => $source,
            'origin_host' => $originHost,
            'page_url' => $pageUrl,
            'page_path' => $pagePath,
            'embed_mode' => $embedMode,
            'embed_source' => (string)($attr['embed_source'] ?? 'website_embed'),
            'value_summary' => trim(($originHost !== '' ? $originHost : 'Website embed') . ($pagePath !== '' ? ' · ' . $pagePath : '') . ($embedMode !== '' ? ' · ' . $embedMode : '')),
            'lead_quality' => $quality,
            'campaign' => ['id' => $campaignId, 'slug' => $row['public_slug'] ?? null, 'title' => (string)($row['campaign_title'] ?? 'Campaign'), 'campaign_type' => (string)($row['campaign_type'] ?? ''), 'url' => $campaignUrl],
            'crm_contact' => ['id' => $contactPublicId, 'name' => $contactName, 'email' => $contactEmail, 'phone' => $contactPhone, 'url' => $contactPublicId !== '' ? '/merchant-customer.php?contact_id=' . rawurlencode($contactPublicId) : ''],
            'campaign_contact' => ['id' => $campaignContactPublicId, 'url' => $campaignContactPublicId !== '' ? '/merchant-customer.php?campaign_contact_id=' . rawurlencode($campaignContactPublicId) : ''],
            'timeline' => [
                ['label' => 'Lead captured', 'value' => (string)($row['created_at'] ?? '')],
                ['label' => 'Lead quality', 'value' => $quality['label'] . ' · ' . $quality['score'] . '/100'],
                ['label' => 'Source', 'value' => $source !== '' ? $source : 'website_embed'],
                ['label' => 'Host domain', 'value' => $originHost !== '' ? $originHost : 'Unknown domain'],
                ['label' => 'Page', 'value' => $pageUrl !== '' ? $pageUrl : 'No page URL captured'],
                ['label' => 'Embed mode', 'value' => $embedMode !== '' ? $embedMode : 'Not specified'],
                ['label' => 'CRM contact', 'value' => $contactPublicId !== '' ? $contactPublicId : 'Not linked'],
            ],
        ];
        $rows[] = $rowOut;
    }
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_embed_leads.query_failed', 'Unable to load campaign embed leads.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to load campaign embed leads.', 500);
}

arsort($domainCounts);
$topDomains = [];
foreach (array_slice($domainCounts, 0, 8, true) as $host => $count) $topDomains[] = ['origin_host' => (string)$host, 'total' => (int)$count];
$topPages = [];
foreach (array_slice($pageCounts, 0, 8, true) as $page => $count) $topPages[] = ['page_url' => (string)$page, 'page_path' => mg_embed_leads_page_path((string)$page), 'total' => (int)$count];
$topSources = mg_embed_leads_pick_top($sourceCounts);
$topModes = mg_embed_leads_pick_top($modeCounts);

$campaignSummaryRows = [];
$placementCampaignActions = [];
foreach ($campaignSummaries as $summary) {
    $topDomain = mg_embed_leads_pick_top($summary['domains']);
    $topPage = mg_embed_leads_pick_top($summary['pages']);
    $topSource = mg_embed_leads_pick_top($summary['sources']);
    $total = max(1, (int)$summary['total_embed_leads']);
    $summaryRow = [
        'campaign' => $summary['campaign'],
        'total_embed_leads' => (int)$summary['total_embed_leads'],
        'ready_for_follow_up' => (int)$summary['ready_for_follow_up'],
        'ready_rate' => (int)round(((int)$summary['ready_for_follow_up'] / $total) * 100),
        'average_quality_score' => (int)round(((int)$summary['quality_score_total']) / $total),
        'latest_lead_at' => $summary['latest_lead_at'],
        'top_domain' => $topDomain[0] ?? null,
        'top_page' => $topPage[0] ?? null,
        'top_source' => $topSource[0] ?? null,
    ];
    $summaryRow['placement_action'] = mg_embed_leads_placement_action($summaryRow);
    $campaignSummaryRows[] = $summaryRow;
    $placementCampaignActions[] = $summaryRow['placement_action'];
}
usort($campaignSummaryRows, static fn(array $a, array $b): int => ($b['total_embed_leads'] <=> $a['total_embed_leads']));
usort($placementCampaignActions, static fn(array $a, array $b): int => ($b['total_embed_leads'] <=> $a['total_embed_leads']));

$totalContacts = count($contactIds);
$newContacts = count($newContactIds);
$readyRate = $totalLeads > 0 ? (int)round(($readyForFollowUp / $totalLeads) * 100) : 0;
$newRate = $totalLeads > 0 ? (int)round(($newContacts / $totalLeads) * 100) : 0;
$averageQuality = $totalLeads > 0 ? (int)round($qualityScoreTotal / $totalLeads) : 0;
$totals = [
    'total_embed_leads' => $totalLeads,
    'new_contacts' => $newContacts,
    'returning_contacts' => max(0, $totalContacts - $newContacts),
    'campaigns' => count($campaignIds),
    'ready_for_follow_up' => $readyForFollowUp,
    'ready_rate' => $readyRate,
    'new_contact_rate' => $newRate,
    'average_quality_score' => $averageQuality,
    'top_domains' => $topDomains,
    'top_pages' => $topPages,
    'top_sources' => $topSources,
    'top_modes' => $topModes,
];
$performance = [
    'insight_cards' => [
        ['label' => 'Ready for follow-up', 'value' => (string)$readyForFollowUp, 'detail' => $readyRate . '% of attributed leads have CRM, campaign, source, and page/domain context.'],
        ['label' => 'Avg lead quality', 'value' => $averageQuality . '/100', 'detail' => 'Quality blends new contact, email, phone, campaign context, attribution, and CRM follow-up link.'],
        ['label' => 'Best domain', 'value' => (string)($topDomains[0]['origin_host'] ?? '—'), 'detail' => isset($topDomains[0]) ? $topDomains[0]['total'] . ' attributed leads' : 'No domain winner yet.'],
        ['label' => 'Best source', 'value' => (string)($topSources[0]['value'] ?? '—'), 'detail' => isset($topSources[0]) ? $topSources[0]['total'] . ' attributed leads' : 'No source winner yet.'],
        ['label' => 'Best mode', 'value' => (string)($topModes[0]['value'] ?? '—'), 'detail' => isset($topModes[0]) ? $topModes[0]['total'] . ' attributed leads' : 'No embed-mode winner yet.'],
    ],
    'quality_breakdown' => array_map(static fn(string $label, int $count): array => ['label' => $label, 'total' => $count], array_keys($qualityBuckets), array_values($qualityBuckets)),
    'recommendations' => mg_embed_leads_performance_recommendations([
        'total' => $totalLeads,
        'top_page' => $topPages[0] ?? null,
        'top_domain' => $topDomains[0] ?? null,
        'top_source' => $topSources[0] ?? null,
        'top_mode' => $topModes[0] ?? null,
        'ready_rate' => $readyRate,
        'new_rate' => $newRate,
    ]),
];
$placementIntelligence = mg_embed_leads_placement_intelligence([
    'total' => $totalLeads,
    'top_page' => $topPages[0] ?? null,
    'top_domain' => $topDomains[0] ?? null,
    'top_source' => $topSources[0] ?? null,
    'top_mode' => $topModes[0] ?? null,
    'campaign_actions' => $placementCampaignActions,
]);

if ($format === 'csv') mg_embed_leads_csv($rows, $totals);

mg_ok([
    'schema_ready' => true,
    'sql_required' => null,
    'filters' => ['days' => $days, 'campaign' => $campaign ? ['id' => (string)$campaign['public_id'], 'slug' => $campaign['public_slug'] ?? null, 'title' => (string)$campaign['title']] : null, 'origin_host' => $originFilter, 'source' => $sourceFilter],
    'campaigns' => mg_embed_leads_campaigns($pdo, $merchantId),
    'totals' => $totals,
    'performance' => $performance,
    'placement_intelligence' => $placementIntelligence,
    'campaign_summaries' => array_slice($campaignSummaryRows, 0, 12),
    'rows' => $rows,
], 'Campaign embed leads loaded.');

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

function mg_embed_leads_pick_top(array $counts): array
{
    arsort($counts);
    $top = [];
    foreach (array_slice($counts, 0, 8, true) as $value => $count) {
        $top[] = ['value' => (string)$value, 'total' => (int)$count];
    }
    return $top;
}

function mg_embed_leads_csv(array $rows, array $totals): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="campaign-embed-leads-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Created', 'Lead Name', 'Lead Email', 'Campaign', 'Campaign Type', 'Source', 'Origin Host', 'Page URL', 'Embed Mode', 'CRM Contact URL', 'Campaign Contact URL', 'Campaign URL']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['created_at'] ?? '',
            $row['crm_contact']['name'] ?? '',
            $row['crm_contact']['email'] ?? '',
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
    && mg_embed_leads_table_ready($pdo, 'merchant_crm_contacts', ['id','public_id','merchant_user_id','primary_email','display_name','first_seen_at'])
    && mg_embed_leads_table_ready($pdo, 'campaign_contacts', ['id','public_id','merchant_user_id','campaign_id','email','name','metadata_json'])
    && mg_embed_leads_table_ready($pdo, 'campaigns', ['id','public_id','public_slug','merchant_user_id','title','campaign_type']);

if (!$ready) {
    mg_ok([
        'schema_ready' => false,
        'sql_required' => null,
        'filters' => ['days' => $days, 'campaign' => null, 'origin_host' => $originFilter, 'source' => $sourceFilter],
        'campaigns' => [],
        'totals' => ['total_embed_leads' => 0, 'new_contacts' => 0, 'returning_contacts' => 0, 'campaigns' => 0, 'top_domains' => [], 'top_pages' => []],
        'campaign_summaries' => [],
        'rows' => [],
    ], 'Campaign embed leads tables are not available yet.');
}

$where = ['e.merchant_user_id = ?', 'e.created_at >= ?'];
$params = [$merchantId, $cutoff];
if ($campaign) { $where[] = 'e.campaign_id = ?'; $params[] = (int)$campaign['id']; }
if ($sourceFilter !== '') { $where[] = 'e.source_type = ?'; $params[] = $sourceFilter; }

$sql = 'SELECT e.public_id event_public_id, e.event_type, e.source_type, e.source_public_id, e.metadata_json event_metadata_json, e.created_at,
               mcc.public_id crm_public_id, mcc.primary_email, mcc.display_name, mcc.first_seen_at,
               cc.public_id campaign_contact_public_id, cc.email campaign_contact_email, cc.name campaign_contact_name, cc.metadata_json contact_metadata_json,
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
$contactIds = [];
$newContactIds = [];
$campaignIds = [];
$campaignSummaries = [];
$totalLeads = 0;
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

        if ($crmId !== '') {
            $contactIds[$crmId] = true;
            if (!empty($row['first_seen_at']) && strtotime((string)$row['first_seen_at']) >= strtotime($cutoff)) $newContactIds[$crmId] = true;
        }
        if ($campaignId !== '') $campaignIds[$campaignId] = true;
        if ($originHost !== '') $domainCounts[$originHost] = ($domainCounts[$originHost] ?? 0) + 1;
        if ($pageUrl !== '') $pageCounts[$pageUrl] = ($pageCounts[$pageUrl] ?? 0) + 1;

        if (!isset($campaignSummaries[$campaignKey])) {
            $campaignSummaries[$campaignKey] = [
                'campaign' => ['id' => $campaignId, 'slug' => $row['public_slug'] ?? null, 'title' => (string)($row['campaign_title'] ?? 'Campaign'), 'campaign_type' => (string)($row['campaign_type'] ?? ''), 'url' => $campaignUrl],
                'total_embed_leads' => 0,
                'latest_lead_at' => null,
                'domains' => [],
                'pages' => [],
            ];
        }
        $campaignSummaries[$campaignKey]['total_embed_leads']++;
        if ($campaignSummaries[$campaignKey]['latest_lead_at'] === null) $campaignSummaries[$campaignKey]['latest_lead_at'] = $row['created_at'] ?? null;
        if ($originHost !== '') $campaignSummaries[$campaignKey]['domains'][$originHost] = ($campaignSummaries[$campaignKey]['domains'][$originHost] ?? 0) + 1;
        if ($pageUrl !== '') $campaignSummaries[$campaignKey]['pages'][$pageUrl] = ($campaignSummaries[$campaignKey]['pages'][$pageUrl] ?? 0) + 1;

        if ($format !== 'csv' && count($rows) >= 300) continue;
        $contactPublicId = $crmId;
        $campaignContactPublicId = (string)($row['campaign_contact_public_id'] ?: $row['source_public_id']);
        $contactName = (string)($row['display_name'] ?: $row['campaign_contact_name'] ?: 'Lead');
        $contactEmail = (string)($row['primary_email'] ?: $row['campaign_contact_email'] ?: '');
        $source = (string)($row['source_type'] ?? '');
        $embedMode = (string)($attr['embed_mode'] ?? '');
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
            'campaign' => ['id' => $campaignId, 'slug' => $row['public_slug'] ?? null, 'title' => (string)($row['campaign_title'] ?? 'Campaign'), 'campaign_type' => (string)($row['campaign_type'] ?? ''), 'url' => $campaignUrl],
            'crm_contact' => ['id' => $contactPublicId, 'name' => $contactName, 'email' => $contactEmail, 'url' => $contactPublicId !== '' ? '/merchant-customer.php?contact_id=' . rawurlencode($contactPublicId) : ''],
            'campaign_contact' => ['id' => $campaignContactPublicId, 'url' => $campaignContactPublicId !== '' ? '/merchant-customer.php?campaign_contact_id=' . rawurlencode($campaignContactPublicId) : ''],
            'timeline' => [
                ['label' => 'Lead captured', 'value' => (string)($row['created_at'] ?? '')],
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

$campaignSummaryRows = [];
foreach ($campaignSummaries as $summary) {
    $topDomain = mg_embed_leads_pick_top($summary['domains']);
    $topPage = mg_embed_leads_pick_top($summary['pages']);
    $campaignSummaryRows[] = [
        'campaign' => $summary['campaign'],
        'total_embed_leads' => (int)$summary['total_embed_leads'],
        'latest_lead_at' => $summary['latest_lead_at'],
        'top_domain' => $topDomain[0] ?? null,
        'top_page' => $topPage[0] ?? null,
    ];
}
usort($campaignSummaryRows, static fn(array $a, array $b): int => ($b['total_embed_leads'] <=> $a['total_embed_leads']));

$totalContacts = count($contactIds);
$newContacts = count($newContactIds);
$totals = [
    'total_embed_leads' => $totalLeads,
    'new_contacts' => $newContacts,
    'returning_contacts' => max(0, $totalContacts - $newContacts),
    'campaigns' => count($campaignIds),
    'top_domains' => $topDomains,
    'top_pages' => $topPages,
];

if ($format === 'csv') mg_embed_leads_csv($rows, $totals);

mg_ok([
    'schema_ready' => true,
    'sql_required' => null,
    'filters' => ['days' => $days, 'campaign' => $campaign ? ['id' => (string)$campaign['public_id'], 'slug' => $campaign['public_slug'] ?? null, 'title' => (string)$campaign['title']] : null, 'origin_host' => $originFilter, 'source' => $sourceFilter],
    'campaigns' => mg_embed_leads_campaigns($pdo, $merchantId),
    'totals' => $totals,
    'campaign_summaries' => array_slice($campaignSummaryRows, 0, 12),
    'rows' => $rows,
], 'Campaign embed leads loaded.');

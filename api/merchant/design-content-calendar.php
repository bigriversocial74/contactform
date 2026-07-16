<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

const MG_DESIGN_CALENDAR_TABLE = 'design_content_schedule';
const MG_DESIGN_CALENDAR_MIGRATION = 'database/20260716_design_studio_content_calendar.sql';
const MG_DESIGN_CALENDAR_DAYS = 30;

function mg_design_calendar_table_ready(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([MG_DESIGN_CALENDAR_TABLE]);
    return (bool) $stmt->fetchColumn();
}

function mg_design_calendar_require_table(PDO $pdo): void
{
    if (!mg_design_calendar_table_ready($pdo)) {
        mg_fail(
            'Content calendar setup is incomplete. Import ' . MG_DESIGN_CALENDAR_MIGRATION . ' before using this feature.',
            503
        );
    }
}

function mg_design_calendar_date(mixed $value, ?string $fallback = null): string
{
    $raw = trim((string) $value);
    if ($raw === '' && $fallback !== null) {
        $raw = $fallback;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (
        !$date
        || ($errors !== false && (((int) ($errors['warning_count'] ?? 0)) > 0 || ((int) ($errors['error_count'] ?? 0)) > 0))
        || $date->format('Y-m-d') !== $raw
    ) {
        mg_fail('Choose a valid calendar date.', 422);
    }
    return $date->format('Y-m-d');
}

function mg_design_calendar_uuid(mixed $value): string
{
    $id = strtolower(trim((string) $value));
    if (preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) {
        mg_fail('Calendar item not found.', 404);
    }
    return $id;
}

function mg_design_calendar_format(mixed $value, string $fallback = 'square'): string
{
    $format = strtolower(trim((string) $value));
    return in_array($format, ['square', 'portrait', 'story'], true) ? $format : $fallback;
}

function mg_design_calendar_layout(mixed $value, string $fallback = 'spotlight'): string
{
    $layout = strtolower(trim((string) $value));
    return in_array($layout, ['spotlight', 'split', 'bold'], true) ? $layout : $fallback;
}

function mg_design_calendar_status(mixed $value, string $fallback = 'planned'): string
{
    $status = strtolower(trim((string) $value));
    return in_array($status, ['planned', 'downloaded', 'posted', 'skipped'], true) ? $status : $fallback;
}

function mg_design_calendar_owned_item(PDO $pdo, int $userId, string $publicId, bool $forUpdate = false): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM design_content_schedule WHERE public_id = ? AND merchant_user_id = ? LIMIT 1'
        . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$publicId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        mg_fail('Calendar item not found.', 404);
    }
    return $row;
}

function mg_design_calendar_rows(PDO $pdo, int $userId, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        "SELECT s.public_id,s.scheduled_date,s.scheduled_time,s.timezone,s.post_format,s.layout_key,s.status,s.notes,
                s.created_at,s.updated_at,
                p.public_id product_id,p.slug,p.status product_status,
                v.title,v.description,v.unit_value_cents,v.currency,
                (
                    SELECT a.public_id
                    FROM catalog_product_version_assets pva
                    INNER JOIN catalog_assets a ON a.id = pva.asset_id
                    WHERE pva.product_version_id = p.current_version_id
                      AND a.asset_type = 'image'
                      AND a.status = 'ready'
                    ORDER BY
                      CASE LOWER(COALESCE(pva.role,'')) WHEN 'primary' THEN 0 WHEN 'cover' THEN 1 WHEN 'hero' THEN 2 WHEN 'product' THEN 3 ELSE 9 END,
                      pva.sort_order,pva.id
                    LIMIT 1
                ) image_asset_id
         FROM design_content_schedule s
         INNER JOIN catalog_products p ON p.id = s.catalog_product_id
         LEFT JOIN catalog_product_versions v ON v.id = p.current_version_id
         WHERE s.merchant_user_id = ? AND s.scheduled_date BETWEEN ? AND ?
         ORDER BY s.scheduled_date ASC,COALESCE(s.scheduled_time,'23:59:59') ASC,s.id ASC"
    );
    $stmt->execute([$userId, $from, $to]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $assetId = trim((string) ($item['image_asset_id'] ?? ''));
        $item['image_url'] = $assetId !== ''
            ? '/api/catalog/asset-file.php?id=' . rawurlencode($assetId)
            : null;
        $item['creative_url'] = '/agent.php?view=design&mode=social'
            . '&product=' . rawurlencode((string) $item['product_id'])
            . '&format=' . rawurlencode((string) $item['post_format'])
            . '&layout=' . rawurlencode((string) $item['layout_key'])
            . '&schedule=' . rawurlencode((string) $item['public_id']);
        unset($item['image_asset_id']);
    }
    unset($item);

    return $items;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'catalog.products.view' : 'catalog.products.manage');
$userId = (int) ($user['id'] ?? 0);
$pdo = mg_db();

if ($method === 'GET') {
    mg_rate_limit('merchant.design_calendar.read', 'user:' . $userId, 180, 60);

    if (!mg_design_calendar_table_ready($pdo)) {
        mg_ok([
            'items' => [],
            'setup_required' => true,
            'migration' => MG_DESIGN_CALENDAR_MIGRATION,
            'days' => MG_DESIGN_CALENDAR_DAYS,
        ]);
    }

    $from = mg_design_calendar_date($_GET['from'] ?? '', date('Y-m-d'));
    $defaultTo = (new DateTimeImmutable($from))->modify('+' . (MG_DESIGN_CALENDAR_DAYS - 1) . ' days')->format('Y-m-d');
    $to = mg_design_calendar_date($_GET['to'] ?? '', $defaultTo);
    $span = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days;
    if ($to < $from || $span === false || $span > 92) {
        mg_fail('Calendar ranges may contain up to 93 days.', 422);
    }

    mg_ok([
        'items' => mg_design_calendar_rows($pdo, $userId, $from, $to),
        'setup_required' => false,
        'from' => $from,
        'to' => $to,
        'days' => MG_DESIGN_CALENDAR_DAYS,
    ]);
}

if ($method !== 'POST') {
    mg_fail('Method not allowed.', 405);
}

mg_rate_limit('merchant.design_calendar.write', 'user:' . $userId, 80, 60);
$input = mg_input();
mg_require_csrf_for_write($input);
mg_design_calendar_require_table($pdo);

$action = strtolower(trim((string) ($input['action'] ?? '')));
if (!in_array($action, ['generate', 'update', 'delete'], true)) {
    mg_fail('Invalid calendar action.', 422);
}

try {
    if ($action === 'generate') {
        $productIds = is_array($input['product_ids'] ?? null) ? $input['product_ids'] : [];
        $productIds = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => strtolower(trim((string) $value)),
            $productIds
        ))));
        if ($productIds === [] || count($productIds) > 50) {
            mg_fail('Choose between 1 and 50 merchant products.', 422);
        }

        $formats = is_array($input['formats'] ?? null) ? $input['formats'] : [];
        $formats = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => mg_design_calendar_format($value, ''),
            $formats
        ))));
        if ($formats === []) {
            $formats = ['square'];
        }

        $layouts = is_array($input['layouts'] ?? null) ? $input['layouts'] : [];
        $layouts = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => mg_design_calendar_layout($value, ''),
            $layouts
        ))));
        if ($layouts === []) {
            $layouts = ['spotlight', 'split', 'bold'];
        }

        $start = mg_design_calendar_date($input['start_date'] ?? '', date('Y-m-d'));
        $startDate = new DateTimeImmutable($start);
        $end = $startDate->modify('+' . (MG_DESIGN_CALENDAR_DAYS - 1) . ' days')->format('Y-m-d');
        $replace = !array_key_exists('replace', $input) || !empty($input['replace']);

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $productStmt = $pdo->prepare(
            "SELECT id,public_id FROM catalog_products
             WHERE merchant_user_id = ? AND status <> 'archived' AND public_id IN ({$placeholders})"
        );
        $productStmt->execute(array_merge([$userId], $productIds));
        $productMap = [];
        foreach ($productStmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
            $productMap[(string) $product['public_id']] = (int) $product['id'];
        }
        foreach ($productIds as $productId) {
            if (!isset($productMap[$productId])) {
                mg_fail('One or more selected products are unavailable.', 422);
            }
        }

        $pdo->beginTransaction();
        if ($replace) {
            $delete = $pdo->prepare(
                'DELETE FROM design_content_schedule WHERE merchant_user_id = ? AND scheduled_date BETWEEN ? AND ?'
            );
            $delete->execute([$userId, $start, $end]);
        }

        $insert = $pdo->prepare(
            "INSERT INTO design_content_schedule
             (public_id,merchant_user_id,catalog_product_id,scheduled_date,scheduled_time,timezone,
              post_format,layout_key,status,notes,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,NULL,'UTC',?,?, 'planned',NULL,?,NOW(),NOW())"
        );

        $created = [];
        for ($day = 0; $day < MG_DESIGN_CALENDAR_DAYS; $day++) {
            $productPublicId = $productIds[$day % count($productIds)];
            $format = $formats[$day % count($formats)];
            $layout = $layouts[$day % count($layouts)];
            $scheduledDate = $startDate->modify("+{$day} days")->format('Y-m-d');
            $publicId = mg_merchant_uuid();

            $insert->execute([
                $publicId,
                $userId,
                $productMap[$productPublicId],
                $scheduledDate,
                $format,
                $layout,
                $userId,
            ]);
            $created[] = $publicId;
        }

        $pdo->commit();
        mg_audit('merchant.design_calendar_generated', 'design_content_schedule', [
            'start_date' => $start,
            'end_date' => $end,
            'product_count' => count($productIds),
            'item_count' => count($created),
            'replace' => $replace,
        ], $userId);

        mg_ok([
            'items' => mg_design_calendar_rows($pdo, $userId, $start, $end),
            'from' => $start,
            'to' => $end,
            'created_count' => count($created),
        ], '30-day content plan created.', 201);
    }

    if ($action === 'update') {
        $publicId = mg_design_calendar_uuid($input['schedule_id'] ?? '');
        $pdo->beginTransaction();
        $item = mg_design_calendar_owned_item($pdo, $userId, $publicId, true);

        $scheduledDate = array_key_exists('scheduled_date', $input)
            ? mg_design_calendar_date($input['scheduled_date'])
            : (string) $item['scheduled_date'];
        $format = array_key_exists('post_format', $input)
            ? mg_design_calendar_format($input['post_format'])
            : (string) $item['post_format'];
        $layout = array_key_exists('layout_key', $input)
            ? mg_design_calendar_layout($input['layout_key'])
            : (string) $item['layout_key'];
        $status = array_key_exists('status', $input)
            ? mg_design_calendar_status($input['status'])
            : (string) $item['status'];
        $notes = array_key_exists('notes', $input)
            ? mb_substr(trim((string) $input['notes']), 0, 500)
            : (string) ($item['notes'] ?? '');
        $notes = $notes !== '' ? $notes : null;

        $update = $pdo->prepare(
            'UPDATE design_content_schedule
             SET scheduled_date = ?,post_format = ?,layout_key = ?,status = ?,notes = ?,updated_at = NOW()
             WHERE id = ?'
        );
        $update->execute([$scheduledDate, $format, $layout, $status, $notes, (int) $item['id']]);
        $pdo->commit();

        mg_audit('merchant.design_calendar_updated', 'design_content_schedule', [
            'schedule_id' => $publicId,
            'scheduled_date' => $scheduledDate,
            'post_format' => $format,
            'layout_key' => $layout,
            'status' => $status,
        ], $userId);

        mg_ok(['schedule_id' => $publicId], 'Calendar item updated.');
    }

    $publicId = mg_design_calendar_uuid($input['schedule_id'] ?? '');
    $pdo->beginTransaction();
    $item = mg_design_calendar_owned_item($pdo, $userId, $publicId, true);
    $pdo->prepare('DELETE FROM design_content_schedule WHERE id = ?')->execute([(int) $item['id']]);
    $pdo->commit();

    mg_audit('merchant.design_calendar_removed', 'design_content_schedule', [
        'schedule_id' => $publicId,
        'scheduled_date' => (string) $item['scheduled_date'],
    ], $userId);

    mg_ok(['schedule_id' => $publicId], 'Calendar item removed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'merchant.design_calendar_failed', 'Design calendar action failed.', [
        'action' => $action,
        'exception_class' => $error::class,
    ], $userId);
    mg_fail('Unable to update the content calendar.', 500);
}

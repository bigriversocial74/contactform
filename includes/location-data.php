<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';

function mg_location_states(): array
{
    return [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ];
}

function mg_location_state_lookup(): array
{
    $lookup = [];
    foreach (mg_location_states() as $code => $name) {
        $lookup[strtoupper($code)] = $code;
        $lookup[strtoupper($name)] = $code;
    }
    return $lookup;
}

function mg_location_normalize_state(mixed $value): ?string
{
    $raw = strtoupper(trim((string) $value));
    $raw = preg_replace('/\s+/u', ' ', $raw) ?? '';
    if ($raw === '') return null;

    $states = mg_location_states();
    if (isset($states[$raw])) return $raw;

    $lookup = mg_location_state_lookup();
    return $lookup[$raw] ?? null;
}

function mg_location_published_product_exists_sql(): string
{
    return "EXISTS (
        SELECT 1
        FROM merchant_storefront_revision_products rp_exists
        INNER JOIN catalog_products cp_exists ON cp_exists.id = rp_exists.catalog_product_id AND cp_exists.status = 'published'
        INNER JOIN catalog_product_versions cpv_exists ON cpv_exists.id = cp_exists.current_version_id AND cpv_exists.version_status = 'published'
        WHERE rp_exists.storefront_revision_id = msr.id AND rp_exists.visibility = 'visible'
        LIMIT 1
    )";
}

function mg_location_merchant_state_counts(PDO $pdo): array
{
    $states = mg_location_states();
    $lookup = mg_location_state_lookup();
    $counts = array_fill_keys(array_keys($states), 0);

    $sql = "SELECT UPPER(TRIM(ml.region)) AS region_key, COUNT(DISTINCT ms.id) AS merchant_count
        FROM merchant_locations ml
        INNER JOIN merchant_workspaces mw ON mw.id = ml.workspace_id AND mw.status NOT IN ('suspended','archived')
        INNER JOIN merchant_storefronts ms ON ms.merchant_user_id = mw.merchant_user_id AND ms.status = 'published'
        INNER JOIN users u ON u.id = ms.merchant_user_id AND u.status = 'active'
        INNER JOIN merchant_storefront_states mss ON mss.storefront_id = ms.id AND mss.published_revision_id IS NOT NULL
        INNER JOIN merchant_storefront_revisions msr ON msr.id = mss.published_revision_id AND msr.revision_status = 'published'
        WHERE ml.status = 'active'
          AND UPPER(TRIM(ml.country_code)) = 'US'
          AND NULLIF(TRIM(COALESCE(ml.address_line1,'')), '') IS NOT NULL
          AND NULLIF(TRIM(COALESCE(ml.city,'')), '') IS NOT NULL
          AND NULLIF(TRIM(COALESCE(ml.region,'')), '') IS NOT NULL
          AND " . mg_location_published_product_exists_sql() . "
        GROUP BY UPPER(TRIM(ml.region))";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $key = preg_replace('/\s+/u', ' ', strtoupper(trim((string) ($row['region_key'] ?? '')))) ?? '';
        $code = $lookup[$key] ?? null;
        if ($code === null) continue;
        $counts[$code] = ($counts[$code] ?? 0) + (int) ($row['merchant_count'] ?? 0);
    }

    return $counts;
}

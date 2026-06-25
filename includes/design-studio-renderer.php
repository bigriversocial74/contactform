<?php
declare(strict_types=1);

/**
 * Design Studio renderer helpers.
 *
 * This renderer intentionally starts with concrete, testable outputs:
 * - QR SVG assets
 * - Proof HTML assets
 *
 * PDF/PNG/ZIP export types are left as explicit not-yet-implemented renderer cases.
 */

const MG_DESIGN_RENDERER_VERSION = 'design-renderer-0.1.0';
const MG_DESIGN_QR_VERSION = 10;
const MG_DESIGN_QR_DATA_CODEWORDS = 274;
const MG_DESIGN_QR_ECC_CODEWORDS = 18;
const MG_DESIGN_QR_BLOCKS = 4;

function mg_design_renderer_uuid(): string
{
    if (function_exists('mg_intelligence_uuid')) return mg_intelligence_uuid();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
}

function mg_design_renderer_root(): string
{
    return dirname(__DIR__);
}

function mg_design_renderer_safe_slug(string $value, string $fallback = 'asset'): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: $fallback;
    $value = trim($value, '-');
    return substr($value !== '' ? $value : $fallback, 0, 80);
}

function mg_design_renderer_storage(int $workspaceId, string $extension): array
{
    $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'dat');
    $year = date('Y');
    $month = date('m');
    $relativeDir = '/uploads/design-studio/' . $workspaceId . '/' . $year . '/' . $month;
    $absoluteDir = mg_design_renderer_root() . $relativeDir;
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException('Unable to create Design Studio upload directory.');
    }
    $name = bin2hex(random_bytes(8)) . '.' . $extension;
    return [
        'absolute_path' => $absoluteDir . '/' . $name,
        'storage_key' => ltrim($relativeDir . '/' . $name, '/'),
        'public_url' => $relativeDir . '/' . $name,
    ];
}

function mg_design_renderer_write_file(int $workspaceId, string $extension, string $content): array
{
    $storage = mg_design_renderer_storage($workspaceId, $extension);
    $bytes = file_put_contents($storage['absolute_path'], $content, LOCK_EX);
    if ($bytes === false) throw new RuntimeException('Unable to write rendered Design Studio asset.');
    $storage['byte_size'] = (int) $bytes;
    $storage['checksum'] = hash('sha256', $content);
    return $storage;
}

function mg_design_renderer_html_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mg_design_renderer_json_decode(mixed $value): array
{
    if ($value === null || $value === '') return [];
    if (is_array($value)) return $value;
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_design_renderer_public_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('#^https?://#i', $url)) return $url;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return $url;
    return $scheme . '://' . $host . ($url[0] === '/' ? $url : '/' . $url);
}

function mg_design_qr_append_bits(array &$bits, int $value, int $length): void
{
    for ($i = $length - 1; $i >= 0; $i--) $bits[] = (($value >> $i) & 1) !== 0;
}

function mg_design_qr_gf_multiply(int $x, int $y): int
{
    $z = 0;
    while ($y > 0) {
        if (($y & 1) !== 0) $z ^= $x;
        $y >>= 1;
        $x <<= 1;
        if (($x & 0x100) !== 0) $x ^= 0x11D;
    }
    return $z & 0xFF;
}

function mg_design_qr_generator(int $degree): array
{
    $result = [1];
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        $result[] = 0;
        for ($j = 0; $j < count($result) - 1; $j++) {
            $result[$j] = mg_design_qr_gf_multiply($result[$j], $root) ^ $result[$j + 1];
        }
        $last = count($result) - 1;
        $result[$last] = mg_design_qr_gf_multiply($result[$last], $root);
        $root = mg_design_qr_gf_multiply($root, 2);
    }
    return $result;
}

function mg_design_qr_remainder(array $data, int $degree): array
{
    $generator = mg_design_qr_generator($degree);
    $result = array_fill(0, $degree, 0);
    foreach ($data as $byte) {
        $factor = ((int) $byte) ^ $result[0];
        array_shift($result);
        $result[] = 0;
        foreach ($generator as $i => $coef) {
            $result[$i] ^= mg_design_qr_gf_multiply((int) $coef, $factor);
        }
    }
    return $result;
}

function mg_design_qr_data_codewords(string $text): array
{
    $bytes = array_values(unpack('C*', $text) ?: []);
    if (count($bytes) > 271) {
        throw new RuntimeException('QR payload is too long for the built-in SVG renderer.');
    }

    $bits = [];
    mg_design_qr_append_bits($bits, 0x4, 4); // byte mode
    mg_design_qr_append_bits($bits, count($bytes), 16); // version 10 uses 16-bit byte count
    foreach ($bytes as $byte) mg_design_qr_append_bits($bits, (int) $byte, 8);

    $capacityBits = MG_DESIGN_QR_DATA_CODEWORDS * 8;
    for ($i = 0; $i < 4 && count($bits) < $capacityBits; $i++) $bits[] = false;
    while ((count($bits) % 8) !== 0) $bits[] = false;

    $data = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) $byte = ($byte << 1) | (!empty($bits[$i + $j]) ? 1 : 0);
        $data[] = $byte;
    }

    for ($pad = 0; count($data) < MG_DESIGN_QR_DATA_CODEWORDS; $pad ^= 1) $data[] = $pad === 0 ? 0xEC : 0x11;
    return $data;
}

function mg_design_qr_interleave(array $data): array
{
    $blocks = [];
    $offset = 0;
    for ($block = 0; $block < MG_DESIGN_QR_BLOCKS; $block++) {
        $dataLen = $block < 2 ? 68 : 69;
        $chunk = array_slice($data, $offset, $dataLen);
        $offset += $dataLen;
        $blocks[] = ['data' => $chunk, 'ecc' => mg_design_qr_remainder($chunk, MG_DESIGN_QR_ECC_CODEWORDS)];
    }

    $result = [];
    for ($i = 0; $i < 69; $i++) {
        foreach ($blocks as $block) if ($i < count($block['data'])) $result[] = $block['data'][$i];
    }
    for ($i = 0; $i < MG_DESIGN_QR_ECC_CODEWORDS; $i++) {
        foreach ($blocks as $block) $result[] = $block['ecc'][$i];
    }
    return $result;
}

function mg_design_qr_blank_matrix(int $size): array
{
    return array_fill(0, $size, array_fill(0, $size, false));
}

function mg_design_qr_set(array &$modules, array &$isFunction, int $x, int $y, bool $dark, bool $function = true): void
{
    $size = count($modules);
    if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) return;
    $modules[$y][$x] = $dark;
    if ($function) $isFunction[$y][$x] = true;
}

function mg_design_qr_draw_finder(array &$modules, array &$isFunction, int $cx, int $cy): void
{
    for ($dy = -4; $dy <= 4; $dy++) {
        for ($dx = -4; $dx <= 4; $dx++) {
            $dist = max(abs($dx), abs($dy));
            $dark = $dist !== 4 && ($dist === 3 || $dist <= 1);
            mg_design_qr_set($modules, $isFunction, $cx + $dx, $cy + $dy, $dark, true);
        }
    }
}

function mg_design_qr_draw_alignment(array &$modules, array &$isFunction, int $cx, int $cy): void
{
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $dist = max(abs($dx), abs($dy));
            $dark = $dist === 2 || $dist === 0;
            mg_design_qr_set($modules, $isFunction, $cx + $dx, $cy + $dy, $dark, true);
        }
    }
}

function mg_design_qr_bit(int $value, int $index): bool
{
    return (($value >> $index) & 1) !== 0;
}

function mg_design_qr_format_bits(int $mask): int
{
    $data = (1 << 3) | $mask; // ECC low format bits = 01
    $rem = $data;
    for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ ((($rem >> 9) & 1) !== 0 ? 0x537 : 0);
    return (($data << 10) | ($rem & 0x3FF)) ^ 0x5412;
}

function mg_design_qr_draw_format(array &$modules, array &$isFunction, int $mask): void
{
    $size = count($modules);
    $bits = mg_design_qr_format_bits($mask);
    for ($i = 0; $i <= 5; $i++) mg_design_qr_set($modules, $isFunction, 8, $i, mg_design_qr_bit($bits, $i), true);
    mg_design_qr_set($modules, $isFunction, 8, 7, mg_design_qr_bit($bits, 6), true);
    mg_design_qr_set($modules, $isFunction, 8, 8, mg_design_qr_bit($bits, 7), true);
    mg_design_qr_set($modules, $isFunction, 7, 8, mg_design_qr_bit($bits, 8), true);
    for ($i = 9; $i < 15; $i++) mg_design_qr_set($modules, $isFunction, 14 - $i, 8, mg_design_qr_bit($bits, $i), true);
    for ($i = 0; $i < 8; $i++) mg_design_qr_set($modules, $isFunction, $size - 1 - $i, 8, mg_design_qr_bit($bits, $i), true);
    for ($i = 8; $i < 15; $i++) mg_design_qr_set($modules, $isFunction, 8, $size - 15 + $i, mg_design_qr_bit($bits, $i), true);
    mg_design_qr_set($modules, $isFunction, 8, $size - 8, true, true);
}

function mg_design_qr_draw_version(array &$modules, array &$isFunction): void
{
    $size = count($modules);
    $rem = MG_DESIGN_QR_VERSION;
    for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ ((($rem >> 11) & 1) !== 0 ? 0x1F25 : 0);
    $bits = (MG_DESIGN_QR_VERSION << 12) | ($rem & 0xFFF);
    for ($i = 0; $i < 18; $i++) {
        $bit = mg_design_qr_bit($bits, $i);
        $a = $size - 11 + ($i % 3);
        $b = intdiv($i, 3);
        mg_design_qr_set($modules, $isFunction, $a, $b, $bit, true);
        mg_design_qr_set($modules, $isFunction, $b, $a, $bit, true);
    }
}

function mg_design_qr_draw_function_patterns(array &$modules, array &$isFunction): void
{
    $size = count($modules);
    mg_design_qr_draw_finder($modules, $isFunction, 3, 3);
    mg_design_qr_draw_finder($modules, $isFunction, $size - 4, 3);
    mg_design_qr_draw_finder($modules, $isFunction, 3, $size - 4);

    for ($i = 0; $i < $size; $i++) {
        if (!$isFunction[6][$i]) mg_design_qr_set($modules, $isFunction, $i, 6, $i % 2 === 0, true);
        if (!$isFunction[$i][6]) mg_design_qr_set($modules, $isFunction, 6, $i, $i % 2 === 0, true);
    }

    foreach ([6, 28, 50] as $x) {
        foreach ([6, 28, 50] as $y) {
            if ($isFunction[$y][$x]) continue;
            mg_design_qr_draw_alignment($modules, $isFunction, $x, $y);
        }
    }

    mg_design_qr_draw_format($modules, $isFunction, 0);
    mg_design_qr_draw_version($modules, $isFunction);
}

function mg_design_qr_draw_codewords(array &$modules, array $isFunction, array $codewords): void
{
    $size = count($modules);
    $bitIndex = 0;
    $totalBits = count($codewords) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        for ($vert = 0; $vert < $size; $vert++) {
            $upward = (($right + 1) & 2) === 0;
            $y = $upward ? $size - 1 - $vert : $vert;
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                if ($isFunction[$y][$x]) continue;
                $dark = false;
                if ($bitIndex < $totalBits) {
                    $dark = mg_design_qr_bit($codewords[intdiv($bitIndex, 8)], 7 - ($bitIndex % 8));
                    $bitIndex++;
                }
                $modules[$y][$x] = $dark;
            }
        }
    }
}

function mg_design_qr_apply_mask(array &$modules, array $isFunction, int $mask): void
{
    $size = count($modules);
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($isFunction[$y][$x]) continue;
            $invert = match ($mask) {
                0 => (($x + $y) % 2) === 0,
                default => false,
            };
            if ($invert) $modules[$y][$x] = !$modules[$y][$x];
        }
    }
}

function mg_design_qr_matrix(string $text): array
{
    $size = MG_DESIGN_QR_VERSION * 4 + 17;
    $modules = mg_design_qr_blank_matrix($size);
    $isFunction = mg_design_qr_blank_matrix($size);
    mg_design_qr_draw_function_patterns($modules, $isFunction);
    $data = mg_design_qr_data_codewords($text);
    $codewords = mg_design_qr_interleave($data);
    mg_design_qr_draw_codewords($modules, $isFunction, $codewords);
    mg_design_qr_apply_mask($modules, $isFunction, 0);
    mg_design_qr_draw_format($modules, $isFunction, 0);
    mg_design_qr_draw_version($modules, $isFunction);
    return $modules;
}

function mg_design_renderer_qr_svg(string $text, int $scale = 8): string
{
    $text = trim($text);
    if ($text === '') throw new RuntimeException('QR payload is empty.');
    $modules = mg_design_qr_matrix($text);
    $size = count($modules);
    $quiet = 4;
    $viewSize = ($size + ($quiet * 2)) * $scale;
    $rects = [];
    for ($y = 0; $y < $size; $y++) {
        $runStart = null;
        for ($x = 0; $x <= $size; $x++) {
            $dark = $x < $size && !empty($modules[$y][$x]);
            if ($dark && $runStart === null) $runStart = $x;
            if ((!$dark || $x === $size) && $runStart !== null) {
                $w = $x - $runStart;
                $rects[] = '<rect x="' . (($runStart + $quiet) * $scale) . '" y="' . (($y + $quiet) * $scale) . '" width="' . ($w * $scale) . '" height="' . $scale . '"/>';
                $runStart = null;
            }
        }
    }
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<svg xmlns="http://www.w3.org/2000/svg" width="' . $viewSize . '" height="' . $viewSize . '" viewBox="0 0 ' . $viewSize . ' ' . $viewSize . '" role="img" aria-label="Microgifter QR code">' . "\n" .
        '<rect width="100%" height="100%" fill="#fff"/>' . "\n" .
        '<g fill="#000">' . implode('', $rects) . '</g>' . "\n" .
        '</svg>' . "\n";
}

function mg_design_renderer_find_payload(PDO $pdo, array $job): string
{
    $manifest = mg_design_renderer_json_decode($job['manifest_json'] ?? null);
    foreach (['payload_url', 'qr_payload_url', 'source_url', 'destination_url'] as $key) {
        if (!empty($manifest[$key]) && is_string($manifest[$key])) return trim($manifest[$key]);
    }

    if (!empty($job['output_asset_id'])) {
        $stmt = $pdo->prepare('SELECT source_url,public_url,metadata_json FROM merchant_design_assets WHERE id=? LIMIT 1');
        $stmt->execute([(int) $job['output_asset_id']]);
        $asset = $stmt->fetch();
        if ($asset) {
            $meta = mg_design_renderer_json_decode($asset['metadata_json'] ?? null);
            if (!empty($meta['payload_url']) && is_string($meta['payload_url'])) return trim($meta['payload_url']);
            if (!empty($asset['source_url'])) return trim((string) $asset['source_url']);
            if (!empty($asset['public_url'])) return trim((string) $asset['public_url']);
        }
    }

    throw new RuntimeException('QR payload could not be found for this export job.');
}

function mg_design_renderer_upsert_asset(PDO $pdo, array $job, string $assetType, string $name, string $mime, string $extension, string $content, array $metadata = []): int
{
    $workspaceId = (int) $job['workspace_id'];
    $storage = mg_design_renderer_write_file($workspaceId, $extension, $content);
    $metadata['renderer_version'] = MG_DESIGN_RENDERER_VERSION;
    $metadata['rendered_at'] = date(DATE_ATOM);

    if (!empty($job['output_asset_id'])) {
        $assetId = (int) $job['output_asset_id'];
        $stmt = $pdo->prepare("UPDATE merchant_design_assets SET asset_type=?,name=?,status='active',lifecycle_status='generated',storage_driver='local',storage_key=?,public_url=?,file_extension=?,mime_type=?,byte_size=?,checksum=?,renderer_version=?,metadata_json=?,generated_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=? AND workspace_id=? LIMIT 1");
        $stmt->execute([$assetType, $name, $storage['storage_key'], $storage['public_url'], $extension, $mime, $storage['byte_size'], $storage['checksum'], MG_DESIGN_RENDERER_VERSION, json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $job['merchant_user_id'], $assetId, $workspaceId]);
        if ($stmt->rowCount() > 0) return $assetId;
    }

    $publicId = mg_design_renderer_uuid();
    $stmt = $pdo->prepare("INSERT INTO merchant_design_assets (public_id,workspace_id,merchant_user_id,project_id,asset_type,name,status,lifecycle_status,storage_driver,storage_key,public_url,file_extension,mime_type,byte_size,checksum,renderer_version,metadata_json,created_by_user_id,updated_by_user_id,generated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,'active','generated','local',?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())");
    $stmt->execute([
        $publicId,
        $workspaceId,
        (int) $job['merchant_user_id'],
        !empty($job['project_id']) ? (int) $job['project_id'] : null,
        $assetType,
        $name,
        $storage['storage_key'],
        $storage['public_url'],
        $extension,
        $mime,
        $storage['byte_size'],
        $storage['checksum'],
        MG_DESIGN_RENDERER_VERSION,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        (int) $job['merchant_user_id'],
        (int) $job['merchant_user_id'],
    ]);
    return (int) $pdo->lastInsertId();
}

function mg_design_renderer_project(PDO $pdo, array $job): ?array
{
    if (empty($job['project_id'])) return null;
    $stmt = $pdo->prepare('SELECT * FROM merchant_design_projects WHERE id=? AND workspace_id=? LIMIT 1');
    $stmt->execute([(int) $job['project_id'], (int) $job['workspace_id']]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function mg_design_renderer_proof_html(PDO $pdo, array $job): string
{
    $project = mg_design_renderer_project($pdo, $job);
    $manifest = mg_design_renderer_json_decode($job['manifest_json'] ?? null);
    $options = mg_design_renderer_json_decode($job['options_json'] ?? null);
    $canvas = $project ? mg_design_renderer_json_decode($project['canvas_json'] ?? null) : [];
    $copy = $project ? mg_design_renderer_json_decode($project['copy_json'] ?? null) : [];
    $media = $project ? mg_design_renderer_json_decode($project['media_json'] ?? null) : [];
    $title = $project ? (string) $project['name'] : 'Design proof';
    $format = $project ? (string) $project['format_key'] : (string) ($manifest['format_key'] ?? 'proof');
    $headline = (string) ($copy['headline'] ?? $canvas['headline'] ?? $manifest['headline'] ?? $title);
    $offer = (string) ($copy['offer'] ?? $canvas['offer'] ?? $manifest['offer'] ?? '');
    $cta = (string) ($copy['cta'] ?? $canvas['cta'] ?? $manifest['cta'] ?? 'Scan to redeem');
    $payload = '';
    foreach (['payload_url', 'qr_payload_url', 'destination_url'] as $key) {
        if (!empty($manifest[$key]) && is_string($manifest[$key])) { $payload = $manifest[$key]; break; }
    }

    $qrBlock = '';
    if ($payload !== '') {
        try {
            $qr = mg_design_renderer_qr_svg(mg_design_renderer_public_url($payload), 4);
            $qrBlock = '<div class="qr">' . $qr . '</div>';
        } catch (Throwable $e) {
            $qrBlock = '<div class="qr missing">QR unavailable: ' . mg_design_renderer_html_escape($e->getMessage()) . '</div>';
        }
    }

    $manifestJson = mg_design_renderer_html_escape(json_encode(['manifest' => $manifest, 'options' => $options, 'canvas' => $canvas, 'copy' => $copy, 'media' => $media], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . mg_design_renderer_html_escape($title) . ' Proof</title><style>body{margin:0;background:#0b1020;color:#eef4ff;font-family:Inter,Arial,sans-serif}.wrap{max-width:1040px;margin:0 auto;padding:40px}.proof{background:linear-gradient(135deg,#111b3a,#1c2f68);border:1px solid rgba(255,255,255,.16);border-radius:28px;padding:36px;box-shadow:0 20px 80px rgba(0,0,0,.35)}.eyebrow{font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:#9fb4ff}.headline{font-size:48px;line-height:1.02;margin:18px 0 12px;font-weight:900}.offer{font-size:24px;color:#dbe7ff;max-width:760px}.cta{display:inline-block;margin-top:24px;background:#fff;color:#101828;border-radius:999px;padding:14px 22px;font-weight:800}.grid{display:grid;grid-template-columns:1fr 220px;gap:28px;align-items:end}.qr{background:#fff;border-radius:20px;padding:14px}.qr svg{display:block;width:100%;height:auto}.missing{color:#111;font-size:13px}pre{white-space:pre-wrap;background:#050815;color:#b9c8ff;border-radius:16px;padding:18px;overflow:auto}.meta{margin-top:28px;color:#92a4d4;font-size:13px}@media(max-width:760px){.grid{grid-template-columns:1fr}.headline{font-size:34px}.wrap{padding:18px}}</style></head><body><main class="wrap"><section class="proof"><div class="eyebrow">Microgifter Design Proof · ' . mg_design_renderer_html_escape($format) . '</div><div class="grid"><div><h1 class="headline">' . mg_design_renderer_html_escape($headline) . '</h1><p class="offer">' . mg_design_renderer_html_escape($offer) . '</p><div class="cta">' . mg_design_renderer_html_escape($cta) . '</div></div>' . $qrBlock . '</div><div class="meta">Generated by ' . mg_design_renderer_html_escape(MG_DESIGN_RENDERER_VERSION) . ' at ' . mg_design_renderer_html_escape(date(DATE_ATOM)) . '</div></section><h2>Render manifest</h2><pre>' . $manifestJson . '</pre></main></body></html>';
}

function mg_design_renderer_complete_job(PDO $pdo, array $job, int $assetId, array $extraManifest = []): array
{
    $manifest = mg_design_renderer_json_decode($job['manifest_json'] ?? null);
    $manifest['renderer_version'] = MG_DESIGN_RENDERER_VERSION;
    $manifest['rendered_at'] = date(DATE_ATOM);
    foreach ($extraManifest as $key => $value) $manifest[$key] = $value;

    $stmt = $pdo->prepare("UPDATE merchant_design_export_jobs SET output_asset_id=?,status='completed',completed_at=NOW(),locked_at=NULL,locked_by=NULL,renderer_version=?,manifest_json=?,error_message=NULL,failure_code=NULL,updated_at=NOW() WHERE id=? LIMIT 1");
    $stmt->execute([$assetId, MG_DESIGN_RENDERER_VERSION, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $job['id']]);

    $reload = $pdo->prepare('SELECT * FROM merchant_design_export_jobs WHERE id=? LIMIT 1');
    $reload->execute([(int) $job['id']]);
    $row = $reload->fetch();
    return is_array($row) ? $row : $job;
}

function mg_design_renderer_fail_job(PDO $pdo, array $job, string $code, string $message): array
{
    $code = substr(preg_replace('/[^a-z0-9_.-]/i', '_', $code) ?: 'renderer_failed', 0, 80);
    $message = mb_substr($message, 0, 500);
    $stmt = $pdo->prepare("UPDATE merchant_design_export_jobs SET status='failed',failed_at=NOW(),locked_at=NULL,locked_by=NULL,failure_code=?,error_message=?,renderer_version=?,updated_at=NOW() WHERE id=? LIMIT 1");
    $stmt->execute([$code, $message, MG_DESIGN_RENDERER_VERSION, (int) $job['id']]);
    $reload = $pdo->prepare('SELECT * FROM merchant_design_export_jobs WHERE id=? LIMIT 1');
    $reload->execute([(int) $job['id']]);
    $row = $reload->fetch();
    return is_array($row) ? $row : $job;
}

function mg_design_renderer_render_job(PDO $pdo, array $job): array
{
    $type = (string) $job['export_type'];
    try {
        if ($type === 'qr_svg') {
            $payload = mg_design_renderer_public_url(mg_design_renderer_find_payload($pdo, $job));
            $svg = mg_design_renderer_qr_svg($payload, 8);
            $assetId = mg_design_renderer_upsert_asset($pdo, $job, 'qr_svg', 'QR Code SVG', 'image/svg+xml', 'svg', $svg, ['payload_url' => $payload, 'export_type' => $type]);
            return mg_design_renderer_complete_job($pdo, $job, $assetId, ['output_asset_id' => $assetId, 'output_format' => 'svg']);
        }

        if ($type === 'proof') {
            $html = mg_design_renderer_proof_html($pdo, $job);
            $assetId = mg_design_renderer_upsert_asset($pdo, $job, 'other', 'Design Proof HTML', 'text/html; charset=utf-8', 'html', $html, ['export_type' => $type, 'render_format' => 'proof_html']);
            return mg_design_renderer_complete_job($pdo, $job, $assetId, ['output_asset_id' => $assetId, 'output_format' => 'html']);
        }

        return mg_design_renderer_fail_job($pdo, $job, 'renderer_not_implemented', 'Renderer for ' . $type . ' is not implemented yet.');
    } catch (Throwable $e) {
        return mg_design_renderer_fail_job($pdo, $job, 'renderer_exception', $e->getMessage());
    }
}

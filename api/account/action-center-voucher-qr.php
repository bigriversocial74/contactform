<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once __DIR__ . '/_action_center_wallet.php';
require_once dirname(__DIR__) . '/merchant/_claims.php';
require_once __DIR__ . '/_claim_voucher_token.php';

function mg_qr_gf_tables(): array
{
    static $tables = null;
    if ($tables !== null) return $tables;
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if (($x & 0x100) !== 0) $x ^= 0x11d;
    }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    return $tables = [$exp, $log];
}

function mg_qr_gf_mul(int $a, int $b): int
{
    if ($a === 0 || $b === 0) return 0;
    [$exp, $log] = mg_qr_gf_tables();
    return $exp[$log[$a] + $log[$b]];
}

function mg_qr_rs_divisor(int $degree): array
{
    [$exp] = mg_qr_gf_tables();
    $result = array_fill(0, $degree, 0);
    $result[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $result[$j] = mg_qr_gf_mul($result[$j], $root);
            if ($j + 1 < $degree) $result[$j] ^= $result[$j + 1];
        }
        $root = mg_qr_gf_mul($root, 2);
    }
    return $result;
}

function mg_qr_rs_remainder(array $data, int $degree): array
{
    $divisor = mg_qr_rs_divisor($degree);
    $result = array_fill(0, $degree, 0);
    foreach ($data as $byte) {
        $factor = $byte ^ $result[0];
        array_shift($result);
        $result[] = 0;
        foreach ($divisor as $i => $coef) $result[$i] ^= mg_qr_gf_mul($coef, $factor);
    }
    return $result;
}

function mg_qr_format_bits(int $mask): int
{
    $data = (1 << 3) | $mask; // ECL L = 01
    $bits = $data << 10;
    $generator = 0x537;
    for ($i = 14; $i >= 10; $i--) {
        if ((($bits >> $i) & 1) !== 0) $bits ^= $generator << ($i - 10);
    }
    return (($data << 10) | $bits) ^ 0x5412;
}

function mg_qr_set(array &$matrix, array &$reserved, int $x, int $y, bool $dark, bool $reserve = true): void
{
    $size = count($matrix);
    if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) return;
    $matrix[$y][$x] = $dark;
    if ($reserve) $reserved[$y][$x] = true;
}

function mg_qr_finder(array &$matrix, array &$reserved, int $x, int $y): void
{
    for ($dy = -1; $dy <= 7; $dy++) {
        for ($dx = -1; $dx <= 7; $dx++) {
            $xx = $x + $dx;
            $yy = $y + $dy;
            $dark = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6 && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
            mg_qr_set($matrix, $reserved, $xx, $yy, $dark, true);
        }
    }
}

function mg_qr_alignment(array &$matrix, array &$reserved, int $cx, int $cy): void
{
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $dist = max(abs($dx), abs($dy));
            mg_qr_set($matrix, $reserved, $cx + $dx, $cy + $dy, $dist !== 1, true);
        }
    }
}

function mg_qr_codewords(string $payload): array
{
    $bytes = array_values(unpack('C*', $payload));
    if (count($bytes) > 106) throw new RuntimeException('Voucher QR payload is too large.');
    $bits = [];
    $push = static function (int $value, int $length) use (&$bits): void {
        for ($i = $length - 1; $i >= 0; $i--) $bits[] = (($value >> $i) & 1) !== 0;
    };
    $push(0x4, 4); // byte mode
    $push(count($bytes), 8);
    foreach ($bytes as $byte) $push($byte, 8);
    $capacityBits = 108 * 8;
    for ($i = 0, $n = min(4, $capacityBits - count($bits)); $i < $n; $i++) $bits[] = false;
    while (count($bits) % 8 !== 0) $bits[] = false;
    $codewords = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $value = 0;
        for ($j = 0; $j < 8; $j++) $value = ($value << 1) | ($bits[$i + $j] ? 1 : 0);
        $codewords[] = $value;
    }
    for ($pad = 0; count($codewords) < 108; $pad++) $codewords[] = ($pad % 2 === 0) ? 0xEC : 0x11;
    return array_merge($codewords, mg_qr_rs_remainder($codewords, 26));
}

function mg_qr_svg(string $payload): string
{
    $version = 5;
    $size = 21 + 4 * ($version - 1);
    $matrix = array_fill(0, $size, array_fill(0, $size, false));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));
    mg_qr_finder($matrix, $reserved, 0, 0);
    mg_qr_finder($matrix, $reserved, $size - 7, 0);
    mg_qr_finder($matrix, $reserved, 0, $size - 7);
    for ($i = 8; $i <= $size - 9; $i++) {
        mg_qr_set($matrix, $reserved, $i, 6, $i % 2 === 0, true);
        mg_qr_set($matrix, $reserved, 6, $i, $i % 2 === 0, true);
    }
    mg_qr_alignment($matrix, $reserved, 30, 30);
    mg_qr_set($matrix, $reserved, 8, 4 * $version + 9, true, true);

    $format = mg_qr_format_bits(0);
    for ($i = 0; $i <= 5; $i++) mg_qr_set($matrix, $reserved, 8, $i, (($format >> $i) & 1) !== 0, true);
    mg_qr_set($matrix, $reserved, 8, 7, (($format >> 6) & 1) !== 0, true);
    mg_qr_set($matrix, $reserved, 8, 8, (($format >> 7) & 1) !== 0, true);
    mg_qr_set($matrix, $reserved, 7, 8, (($format >> 8) & 1) !== 0, true);
    for ($i = 9; $i < 15; $i++) mg_qr_set($matrix, $reserved, 14 - $i, 8, (($format >> $i) & 1) !== 0, true);
    for ($i = 0; $i < 8; $i++) mg_qr_set($matrix, $reserved, $size - 1 - $i, 8, (($format >> $i) & 1) !== 0, true);
    for ($i = 8; $i < 15; $i++) mg_qr_set($matrix, $reserved, 8, $size - 15 + $i, (($format >> $i) & 1) !== 0, true);

    $dataBits = [];
    foreach (mg_qr_codewords($payload) as $cw) for ($i = 7; $i >= 0; $i--) $dataBits[] = (($cw >> $i) & 1) !== 0;
    $bitIndex = 0;
    $upward = true;
    for ($x = $size - 1; $x > 0; $x -= 2) {
        if ($x === 6) $x--;
        for ($yi = 0; $yi < $size; $yi++) {
            $y = $upward ? $size - 1 - $yi : $yi;
            for ($dx = 0; $dx < 2; $dx++) {
                $xx = $x - $dx;
                if ($reserved[$y][$xx]) continue;
                $dark = $dataBits[$bitIndex++] ?? false;
                if ((($xx + $y) % 2) === 0) $dark = !$dark;
                mg_qr_set($matrix, $reserved, $xx, $y, $dark, false);
            }
        }
        $upward = !$upward;
    }

    $quiet = 4;
    $svgSize = $size + ($quiet * 2);
    $rects = '';
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($matrix[$y][$x]) $rects .= '<rect x="' . ($x + $quiet) . '" y="' . ($y + $quiet) . '" width="1" height="1"/>';
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $svgSize . ' ' . $svgSize . '" shape-rendering="crispEdges" role="img" aria-label="Microgifter voucher QR"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">' . $rects . '</g></svg>';
}

mg_require_method('GET');
$user = mg_require_api_user();
$token = trim((string)($_GET['t'] ?? $_GET['token'] ?? ''));
$actionItemId = trim((string)($_GET['action_item_id'] ?? ''));

try {
    $pdo = mg_db();
    $payload = '';
    $walletId = mg_ac_wallet_action_id($actionItemId);
    if ($walletId !== null) {
        $wallet = mg_ac_wallet_load_for_user($pdo, $walletId, (int)$user['id'], mg_ac_wallet_user_email($user), false);
        if (!$wallet) mg_fail('Voucher token not found.', 404);
        if (mg_ac_wallet_expired($wallet)) mg_fail('This wallet reward has expired.', 410);
        if ((string)($wallet['status'] ?? '') === 'redeemed') mg_fail('This gift has already been claimed. A refund must be issued before it can be claimed again.', 409);
        $payload = 'MGFT-WALLET-CLAIM|' . $walletId;
    } else {
        if ($token === '') mg_fail('Voucher token is required.', 422);
        $row = mg_claim_voucher_require_active($pdo, $token, false);
        if ((int)$row['user_id'] !== (int)$user['id']) mg_fail('Voucher token not found.', 404);
        $payload = mg_claim_voucher_scan_payload($token);
    }
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: private, no-store, max-age=0');
    echo mg_qr_svg($payload);
    exit;
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    mg_security_log('error', 'action_center.voucher_qr_failed', 'Unable to render first-party voucher QR.', ['exception_class' => $error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to render voucher QR.', 500);
}
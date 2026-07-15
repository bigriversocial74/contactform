<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-games.php';

const MG_HOSTED_GAME_COVER_MAX_BYTES = 10485760;
const MG_HOSTED_GAME_COVER_MIN_WIDTH = 640;
const MG_HOSTED_GAME_COVER_MIN_HEIGHT = 360;
const MG_HOSTED_GAME_COVER_MAX_DIMENSION = 12000;
const MG_HOSTED_GAME_COVER_MAX_PIXELS = 60000000;

function mg_hosted_game_cover_upload_error(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The cover image is too large.',
        UPLOAD_ERR_PARTIAL => 'The cover image upload was incomplete. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose a cover image to upload.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not store the cover image.',
        default => 'The cover image upload failed.',
    };
}

function mg_hosted_game_cover_public_url(string $gamePublicId, string $assetPublicId): string
{
    return mg_hosted_game_base_url()
        . '/api/public/hosted-game-cover.php?game=' . rawurlencode(strtolower($gamePublicId))
        . '&asset=' . rawurlencode(strtolower($assetPublicId));
}

function mg_hosted_game_cover_asset_id_from_url(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '') return null;
    $parts = parse_url($url);
    if (!is_array($parts) || (string)($parts['path'] ?? '') !== '/api/public/hosted-game-cover.php') return null;
    parse_str((string)($parts['query'] ?? ''), $query);
    $assetId = strtolower(trim((string)($query['asset'] ?? '')));
    return preg_match('/^[a-f0-9-]{36}$/', $assetId) === 1 ? $assetId : null;
}

function mg_hosted_game_cover_reference_matches(string $coverUrl, string $gamePublicId, string $assetPublicId): bool
{
    $parts = parse_url(trim($coverUrl));
    if (!is_array($parts) || (string)($parts['path'] ?? '') !== '/api/public/hosted-game-cover.php') return false;
    parse_str((string)($parts['query'] ?? ''), $query);
    return hash_equals(strtolower($gamePublicId), strtolower(trim((string)($query['game'] ?? ''))))
        && hash_equals(strtolower($assetPublicId), strtolower(trim((string)($query['asset'] ?? ''))));
}

function mg_hosted_game_cover_safe_original_name(string $name, string $extension): string
{
    $name = trim(basename($name));
    $name = preg_replace('/[\x00-\x1F\x7F]+/', '', $name) ?? '';
    $name = preg_replace('/[^A-Za-z0-9._() -]+/', '-', $name) ?? '';
    $name = trim($name, " .-\t\n\r\0\x0B");
    if ($name === '') $name = 'hosted-game-cover.' . $extension;
    if (strlen($name) > 180) {
        $base = pathinfo($name, PATHINFO_FILENAME) ?: 'hosted-game-cover';
        $name = substr($base, 0, 140) . '.' . $extension;
    }
    return $name;
}

/**
 * Validate, store, register, and attach one hosted-game cover image.
 * The caller owns the surrounding database transaction.
 */
function mg_hosted_game_store_cover_image(PDO $pdo, array $game, array $file, int $actorUserId): array
{
    $gameId = (int)($game['id'] ?? 0);
    $merchantUserId = (int)($game['merchant_user_id'] ?? 0);
    $gamePublicId = strtolower(trim((string)($game['public_id'] ?? '')));
    if ($gameId < 1 || $merchantUserId < 1 || preg_match('/^[a-f0-9-]{36}$/', $gamePublicId) !== 1) {
        throw new MgHostedGameException('Hosted game cover record is invalid.');
    }
    if ((string)($game['status'] ?? '') === 'archived') {
        throw new MgHostedGameException('Archived games cannot receive a new cover image.');
    }

    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(mg_hosted_game_cover_upload_error($uploadError));
    }
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName) || !is_file($tmpName)) {
        throw new InvalidArgumentException('Invalid uploaded cover image.');
    }

    $reportedSize = (int)($file['size'] ?? 0);
    $actualSize = filesize($tmpName);
    if ($actualSize === false) throw new InvalidArgumentException('The cover image could not be measured.');
    $size = (int)$actualSize;
    if ($size < 1 || $size > MG_HOSTED_GAME_COVER_MAX_BYTES || ($reportedSize > 0 && $reportedSize !== $size)) {
        throw new InvalidArgumentException('The cover image must be 10 MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);
    $formats = [
        'image/jpeg' => ['jpg', IMAGETYPE_JPEG],
        'image/png' => ['png', IMAGETYPE_PNG],
        'image/webp' => ['webp', IMAGETYPE_WEBP],
    ];
    if (!isset($formats[$mime])) {
        throw new InvalidArgumentException('Use a JPEG, PNG, or WebP cover image.');
    }
    [$extension, $expectedImageType] = $formats[$mime];

    $dimensions = @getimagesize($tmpName);
    if (!is_array($dimensions)) throw new InvalidArgumentException('The uploaded file is not a valid image.');
    $width = (int)($dimensions[0] ?? 0);
    $height = (int)($dimensions[1] ?? 0);
    $imageType = (int)($dimensions[2] ?? 0);
    if ($imageType !== $expectedImageType) throw new InvalidArgumentException('The cover image format could not be verified.');
    if ($width < MG_HOSTED_GAME_COVER_MIN_WIDTH || $height < MG_HOSTED_GAME_COVER_MIN_HEIGHT) {
        throw new InvalidArgumentException('The cover image must be at least 640 × 360 pixels.');
    }
    if ($width > MG_HOSTED_GAME_COVER_MAX_DIMENSION || $height > MG_HOSTED_GAME_COVER_MAX_DIMENSION || ($width * $height) > MG_HOSTED_GAME_COVER_MAX_PIXELS) {
        throw new InvalidArgumentException('The cover image dimensions are too large.');
    }

    $assetPublicId = mg_hosted_game_uuid();
    $storageKey = 'hosted-games/' . $merchantUserId . '/' . $gamePublicId . '/covers/' . $assetPublicId . '.' . $extension;
    $directory = mg_hosted_game_storage_root()
        . DIRECTORY_SEPARATOR . $merchantUserId
        . DIRECTORY_SEPARATOR . $gamePublicId
        . DIRECTORY_SEPARATOR . 'covers';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new MgHostedGameException('Unable to prepare hosted game cover storage.');
    }
    $directoryReal = realpath($directory);
    $storageRoot = mg_hosted_game_storage_root();
    if ($directoryReal === false || !str_starts_with($directoryReal, $storageRoot . DIRECTORY_SEPARATOR)) {
        throw new MgHostedGameException('Unable to validate hosted game cover storage.');
    }
    $destination = $directoryReal . DIRECTORY_SEPARATOR . $assetPublicId . '.' . $extension;
    if (!move_uploaded_file($tmpName, $destination)) {
        throw new MgHostedGameException('Unable to store the hosted game cover image.');
    }
    @chmod($destination, 0600);

    try {
        $checksum = hash_file('sha256', $destination);
        if (!is_string($checksum) || strlen($checksum) !== 64) {
            throw new MgHostedGameException('Unable to verify the hosted game cover image.');
        }
        $originalName = mg_hosted_game_cover_safe_original_name((string)($file['name'] ?? ''), $extension);
        $metadata = mg_hosted_game_json_encode([
            'role' => 'hosted_game_cover',
            'game_id' => $gamePublicId,
            'merchant_user_id' => $merchantUserId,
            'validation' => 'hosted_game_cover_v1',
        ], 8192);

        $pdo->prepare(
            "INSERT INTO catalog_assets
             (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,
              checksum_sha256,width_px,height_px,status,metadata_json,created_at,updated_at)
             VALUES (?,?,'image','private_local',?,?,?,?,?,?,?,'ready',?,NOW(),NOW())"
        )->execute([
            $assetPublicId,
            $merchantUserId,
            $storageKey,
            $originalName,
            $mime,
            $size,
            $checksum,
            $width,
            $height,
            $metadata,
        ]);

        $coverUrl = mg_hosted_game_cover_public_url($gamePublicId, $assetPublicId);
        $oldAssetPublicId = mg_hosted_game_cover_asset_id_from_url((string)($game['cover_url'] ?? ''));
        $pdo->prepare('UPDATE hosted_games SET cover_url=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$coverUrl, $actorUserId, $gameId]);
        if ($oldAssetPublicId !== null && !hash_equals($oldAssetPublicId, $assetPublicId)) {
            $pdo->prepare("UPDATE catalog_assets SET status='archived',updated_at=NOW() WHERE public_id=? AND owner_user_id=? AND status='ready'")
                ->execute([$oldAssetPublicId, $merchantUserId]);
        }
    } catch (Throwable $error) {
        @unlink($destination);
        throw $error;
    }

    return [
        'asset_id' => $assetPublicId,
        'cover_url' => $coverUrl,
        'mime_type' => $mime,
        'byte_size' => $size,
        'width' => $width,
        'height' => $height,
        'filename' => $originalName,
    ];
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-game-standard-core.php';

function mg_hosted_game_standard_preflight_upload(array $file, array $game): array
{
    if (!class_exists('ZipArchive')) {
        throw new MgHostedGameException('The PHP Zip extension is required before game packages can be uploaded.');
    }
    $temporaryPath = (string)($file['tmp_name'] ?? '');
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || !is_file($temporaryPath)) {
        throw new InvalidArgumentException('Invalid uploaded game ZIP.');
    }

    $zip = new ZipArchive();
    if ($zip->open($temporaryPath, ZipArchive::RDONLY) !== true) {
        throw new InvalidArgumentException('The game ZIP could not be opened for Standard v1 validation.');
    }

    try {
        $paths = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if (!is_array($stat)) continue;
            $rawPath = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            if ($rawPath === '' || str_ends_with($rawPath, '/')) continue;
            $safePath = mg_hosted_game_standard_safe_path($rawPath);
            if ($safePath !== '') $paths[$safePath] = ['index' => $index];
        }
        if ($paths === []) throw new InvalidArgumentException('The game ZIP does not contain supported files.');

        $wrapper = '';
        if (!isset($paths['index.html']) && !isset($paths['index.htm']) && !isset($paths['game.json'])) {
            $roots = [];
            foreach (array_keys($paths) as $path) {
                $parts = explode('/', $path, 2);
                if (count($parts) < 2) {
                    $roots = [];
                    break;
                }
                $roots[$parts[0]] = true;
            }
            if (count($roots) === 1) $wrapper = (string)array_key_first($roots) . '/';
        }
        if ($wrapper !== '') {
            $normalized = [];
            foreach ($paths as $path => $metadata) {
                $normalized[substr($path, strlen($wrapper))] = $metadata;
            }
            $paths = $normalized;
        }

        $manifest = [];
        if (isset($paths['game.json'])) {
            $rawManifest = $zip->getFromIndex(
                (int)$paths['game.json']['index'],
                MG_HOSTED_GAME_STANDARD_MAX_MANIFEST_BYTES + 1,
                ZipArchive::FL_UNCHANGED
            );
            if (!is_string($rawManifest) || strlen($rawManifest) > MG_HOSTED_GAME_STANDARD_MAX_MANIFEST_BYTES) {
                throw new InvalidArgumentException('game.json is invalid or exceeds 64 KB.');
            }
            $decoded = json_decode($rawManifest, true);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('game.json must contain valid JSON.');
            }
            $manifest = $decoded;
        }

        $entry = mg_hosted_game_standard_safe_path($manifest['entry'] ?? 'index.html', 'index.html');
        if (!isset($paths[$entry])) {
            if (isset($paths['index.html'])) $entry = 'index.html';
            elseif (isset($paths['index.htm'])) $entry = 'index.htm';
            else throw new InvalidArgumentException('The package must include index.html or declare a valid HTML entry in game.json.');
        }
        return mg_hosted_game_standard_normalize_manifest($manifest, $game, $paths, $entry);
    } finally {
        $zip->close();
    }
}

function mg_hosted_game_standard_finalize_release(PDO $pdo, array $game, array $result, array $manifest, int $actorUserId): array
{
    $releaseId = strtolower(trim((string)($result['release']['id'] ?? '')));
    if (preg_match('/^[a-f0-9-]{36}$/', $releaseId) !== 1) {
        throw new MgHostedGameException('The uploaded release could not be standardized.');
    }

    $manifest['entry'] = (string)($result['release']['entry_file'] ?? $manifest['entry'] ?? 'index.html');
    $manifestJson = mg_hosted_game_json_encode($manifest, MG_HOSTED_GAME_STANDARD_MAX_MANIFEST_BYTES);
    $update = $pdo->prepare(
        "UPDATE hosted_game_releases SET manifest_json=?,updated_at=NOW()
         WHERE public_id=? AND game_id=? AND status='active'"
    );
    $update->execute([$manifestJson, $releaseId, (int)$game['id']]);

    $verify = $pdo->prepare(
        "SELECT manifest_json FROM hosted_game_releases
         WHERE public_id=? AND game_id=? AND status='active' LIMIT 1"
    );
    $verify->execute([$releaseId, (int)$game['id']]);
    $savedManifest = $verify->fetchColumn();
    if (!is_string($savedManifest) || mg_hosted_game_json_decode($savedManifest) !== $manifest) {
        throw new MgHostedGameException('The active release manifest could not be saved.');
    }

    try {
        mg_audit('hosted_game.standard_v1.release_validated', 'hosted_game_release', [
            'game_id' => (string)$game['public_id'],
            'release_id' => $releaseId,
            'schema' => MG_HOSTED_GAME_STANDARD_SCHEMA,
            'compliance' => (string)$manifest['standard']['compliance'],
            'capabilities' => $manifest['capabilities'],
            'events' => $manifest['events'],
        ], $actorUserId);
    } catch (Throwable) {
        // Audit logging does not invalidate a successfully committed release.
    }

    $result['release']['manifest'] = $manifest;
    $result['release']['standard'] = $manifest['standard'];
    return $result;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-games.php';

if (!defined('MG_HOSTED_GAME_MAX_ZIP_BYTES')) define('MG_HOSTED_GAME_MAX_ZIP_BYTES', 104857600);
if (!defined('MG_HOSTED_GAME_MAX_FILES')) define('MG_HOSTED_GAME_MAX_FILES', 5000);
if (!defined('MG_HOSTED_GAME_MAX_EXTRACTED_BYTES')) define('MG_HOSTED_GAME_MAX_EXTRACTED_BYTES', 536870912);
if (!defined('MG_HOSTED_GAME_MAX_SINGLE_FILE_BYTES')) define('MG_HOSTED_GAME_MAX_SINGLE_FILE_BYTES', 157286400);

function mg_hosted_game_upload_error(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The game ZIP exceeds the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'The game ZIP upload was incomplete.',
        UPLOAD_ERR_NO_FILE => 'Select a game ZIP to upload.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not store the uploaded game ZIP.',
        default => 'The game ZIP upload failed.',
    };
}

function mg_hosted_game_remove_tree(string $path): void
{
    if (!file_exists($path)) return;
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if (is_array($items)) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            mg_hosted_game_remove_tree($path . DIRECTORY_SEPARATOR . $item);
        }
    }
    @rmdir($path);
}

function mg_hosted_game_zip_path(string $name): string
{
    $name = str_replace('\\', '/', $name);
    $name = preg_replace('#/+#', '/', $name) ?? '';
    $name = ltrim($name, '/');
    if ($name === '' || str_contains($name, "\0")) return '';
    $parts = [];
    foreach (explode('/', $name) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') throw new MgHostedGameException('The game ZIP contains an unsafe parent-directory path.');
        if (strlen($part) > 180) throw new MgHostedGameException('The game ZIP contains an excessively long path segment.');
        $parts[] = $part;
    }
    $normalized = implode('/', $parts);
    if (strlen($normalized) > 700) throw new MgHostedGameException('The game ZIP contains an excessively long file path.');
    return $normalized;
}

function mg_hosted_game_extension_allowed(string $path): bool
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, [
        'html','htm','css','js','mjs','json','map','txt','xml','csv',
        'jpg','jpeg','png','gif','webp','svg','ico','avif',
        'mp3','m4a','aac','wav','ogg','oga','flac',
        'mp4','webm','mov','ogv',
        'woff','woff2','ttf','otf','eot',
        'wasm','data','mem','bin','dat','unityweb','bundle','br','gz',
        'glb','gltf','obj','mtl','fbx','dae','3ds',
        'vtt','srt','lrc','pdf',
    ], true);
}

function mg_hosted_game_file_allowed(string $path): bool
{
    $base = strtolower(basename($path));
    if ($base === '' || str_starts_with($base, '.')) return false;
    if (in_array($base, ['thumbs.db','desktop.ini','composer.json','composer.lock','package.json','package-lock.json','yarn.lock'], true)) return false;
    return mg_hosted_game_extension_allowed($path);
}

function mg_hosted_game_zip_symlink(ZipArchive $zip, int $index): bool
{
    if (!method_exists($zip, 'getExternalAttributesIndex')) return false;
    $opsys = 0;
    $attributes = 0;
    if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) return false;
    if ($opsys !== ZipArchive::OPSYS_UNIX) return false;
    $mode = ($attributes >> 16) & 0170000;
    return $mode === 0120000;
}

function mg_hosted_game_common_wrapper(array $paths): string
{
    if (in_array('index.html', $paths, true) || in_array('index.htm', $paths, true) || in_array('game.json', $paths, true)) return '';
    $roots = [];
    foreach ($paths as $path) {
        $parts = explode('/', $path, 2);
        if (count($parts) < 2) return '';
        $roots[$parts[0]] = true;
    }
    return count($roots) === 1 ? (string)array_key_first($roots) . '/' : '';
}

function mg_hosted_game_strip_wrapper(string $path, string $wrapper): string
{
    if ($wrapper === '') return $path;
    return str_starts_with($path, $wrapper) ? substr($path, strlen($wrapper)) : $path;
}

function mg_hosted_game_process_upload(PDO $pdo, array $game, int $actorUserId, string $auditEvent): array
{
    if (!mg_hosted_game_schema_ready($pdo)) throw new MgHostedGameException('Hosted Games setup is incomplete. Import database/hosted_games_management_v1.sql.');
    if (!class_exists('ZipArchive')) throw new MgHostedGameException('The PHP Zip extension is required before game packages can be uploaded.');

    $gamePublicId = trim((string)($game['public_id'] ?? ''));
    $gameId = (int)($game['id'] ?? 0);
    $merchantUserId = (int)($game['merchant_user_id'] ?? 0);
    if ($gamePublicId === '' || $gameId < 1 || $merchantUserId < 1) throw new MgHostedGameException('Hosted game ownership is invalid.');
    if ((string)($game['status'] ?? '') === 'archived') throw new MgHostedGameException('Archived games cannot receive new releases.');

    if (!isset($_FILES['game_zip']) || !is_array($_FILES['game_zip'])) throw new MgHostedGameException('Select a game ZIP to upload.');
    $file = $_FILES['game_zip'];
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) throw new MgHostedGameException(mg_hosted_game_upload_error($uploadError));

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName) || !is_file($tmpName)) throw new MgHostedGameException('Invalid uploaded game ZIP.');
    $actualSize = filesize($tmpName);
    $reportedSize = (int)($file['size'] ?? 0);
    if ($actualSize === false || $actualSize < 1 || $actualSize > MG_HOSTED_GAME_MAX_ZIP_BYTES || ($reportedSize > 0 && $reportedSize !== (int)$actualSize)) {
        throw new MgHostedGameException('The game ZIP is too large or invalid.');
    }

    $originalName = trim(basename((string)($file['name'] ?? 'game.zip')));
    if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') throw new MgHostedGameException('Hosted games must be uploaded as a ZIP file.');
    $signature = file_get_contents($tmpName, false, null, 0, 4);
    if (!is_string($signature) || !in_array($signature, ["PK\x03\x04","PK\x05\x06","PK\x07\x08"], true)) throw new MgHostedGameException('The uploaded file is not a valid ZIP package.');
    $checksum = hash_file('sha256', $tmpName);
    if (!is_string($checksum) || strlen($checksum) !== 64) throw new MgHostedGameException('Unable to verify the uploaded game ZIP.');

    $zip = new ZipArchive();
    $open = $zip->open($tmpName, ZipArchive::RDONLY);
    if ($open !== true) throw new MgHostedGameException('The game ZIP could not be opened.');

    $destination = '';
    try {
        $entries = [];
        $totalBytes = 0;
        if ($zip->numFiles < 1 || $zip->numFiles > MG_HOSTED_GAME_MAX_FILES) throw new MgHostedGameException('The game ZIP contains too many files.');
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if (!is_array($stat)) throw new MgHostedGameException('The game ZIP contains an unreadable entry.');
            $rawName = (string)($stat['name'] ?? '');
            $isDirectory = str_ends_with(str_replace('\\', '/', $rawName), '/');
            $path = mg_hosted_game_zip_path($rawName);
            if ($path === '' || $isDirectory) continue;
            if (mg_hosted_game_zip_symlink($zip, $index)) throw new MgHostedGameException('Symbolic links are not allowed in hosted game ZIP files.');
            if (!mg_hosted_game_file_allowed($path)) throw new MgHostedGameException('Unsupported or executable file in game ZIP: ' . basename($path));
            $size = (int)($stat['size'] ?? 0);
            $compressed = max(1, (int)($stat['comp_size'] ?? 1));
            if ($size < 0 || $size > MG_HOSTED_GAME_MAX_SINGLE_FILE_BYTES) throw new MgHostedGameException('A file in the game ZIP exceeds the per-file limit.');
            if ($size > 1048576 && ($size / $compressed) > 250) throw new MgHostedGameException('The game ZIP contains an unsafe compression ratio.');
            $totalBytes += $size;
            if ($totalBytes > MG_HOSTED_GAME_MAX_EXTRACTED_BYTES) throw new MgHostedGameException('The extracted game exceeds the hosted-game size limit.');
            if (isset($entries[$path])) throw new MgHostedGameException('The game ZIP contains duplicate file paths.');
            $entries[$path] = ['index'=>$index,'size'=>$size];
        }
        if ($entries === []) throw new MgHostedGameException('The game ZIP does not contain any supported files.');

        $wrapper = mg_hosted_game_common_wrapper(array_keys($entries));
        $normalizedEntries = [];
        foreach ($entries as $path => $entry) {
            $normalized = mg_hosted_game_strip_wrapper($path, $wrapper);
            if ($normalized === '') continue;
            if (isset($normalizedEntries[$normalized])) throw new MgHostedGameException('The game ZIP resolves to duplicate release paths.');
            $normalizedEntries[$normalized] = $entry + ['source_path'=>$path];
        }

        $manifest = [];
        if (isset($normalizedEntries['game.json'])) {
            $manifestRaw = $zip->getFromIndex((int)$normalizedEntries['game.json']['index'], 65537, ZipArchive::FL_UNCHANGED);
            if (!is_string($manifestRaw) || strlen($manifestRaw) > 65536) throw new MgHostedGameException('game.json is invalid or too large.');
            $decoded = json_decode($manifestRaw, true);
            if (!is_array($decoded)) throw new MgHostedGameException('game.json must contain valid JSON.');
            $manifest = $decoded;
        }

        $entryFile = mg_hosted_game_zip_path((string)($manifest['entry'] ?? 'index.html'));
        $entryFile = mg_hosted_game_strip_wrapper($entryFile, $wrapper);
        if ($entryFile === '' || !isset($normalizedEntries[$entryFile]) || !in_array(strtolower(pathinfo($entryFile, PATHINFO_EXTENSION)), ['html','htm'], true)) {
            if (isset($normalizedEntries['index.html'])) $entryFile = 'index.html';
            elseif (isset($normalizedEntries['index.htm'])) $entryFile = 'index.htm';
            else throw new MgHostedGameException('The game ZIP must contain index.html or declare a valid HTML entry in game.json.');
        }

        $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM hosted_game_releases WHERE game_id=?');
        $versionStmt->execute([$gameId]);
        $version = max(1, (int)$versionStmt->fetchColumn());
        $releasePublicId = mg_hosted_game_uuid();
        $destination = mg_hosted_game_release_directory($merchantUserId, $gamePublicId, $version);
        if (file_exists($destination)) throw new MgHostedGameException('The release directory already exists.');
        if (!mkdir($destination, 0700, true) && !is_dir($destination)) throw new MgHostedGameException('Unable to prepare the game release directory.');

        foreach ($normalizedEntries as $path => $entry) {
            $target = $destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new MgHostedGameException('Unable to create a game release directory.');
            $stream = $zip->getStream((string)$entry['source_path']);
            if (!is_resource($stream)) throw new MgHostedGameException('Unable to read a file from the game ZIP.');
            $output = fopen($target, 'xb');
            if (!is_resource($output)) {
                fclose($stream);
                throw new MgHostedGameException('Unable to create a game release file.');
            }
            $written = stream_copy_to_stream($stream, $output, MG_HOSTED_GAME_MAX_SINGLE_FILE_BYTES + 1);
            fclose($stream);
            fclose($output);
            if ($written === false || $written !== (int)$entry['size']) throw new MgHostedGameException('A game release file failed checksum-length validation.');
            @chmod($target, 0640);
        }

        $entryPath = realpath($destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryFile));
        $destinationReal = realpath($destination);
        if ($entryPath === false || $destinationReal === false || !str_starts_with($entryPath, $destinationReal . DIRECTORY_SEPARATOR)) throw new MgHostedGameException('The game entry file could not be verified.');

        $storageKey = mg_hosted_game_storage_key($merchantUserId, $gamePublicId, $version);
        $pdo->beginTransaction();
        $lockedGame = mg_hosted_game_by_public_id($pdo, $gamePublicId, true);
        if (!$lockedGame || (int)$lockedGame['merchant_user_id'] !== $merchantUserId) throw new MgHostedGameException('Hosted game ownership changed during upload.');
        if ((string)$lockedGame['status'] === 'archived') throw new MgHostedGameException('Archived games cannot receive new releases.');
        $pdo->prepare("UPDATE hosted_game_releases SET status='previous',updated_at=NOW() WHERE game_id=? AND status='active'")->execute([$gameId]);
        $pdo->prepare("INSERT INTO hosted_game_releases (public_id,game_id,version_number,original_filename,storage_key,package_checksum,manifest_json,file_count,extracted_bytes,status,uploaded_by_user_id,activated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'active',?,NOW(),NOW(),NOW())")
            ->execute([$releasePublicId,$gameId,$version,mb_substr($originalName,0,255),$storageKey,$checksum,$manifest === [] ? null : mg_hosted_game_json_encode($manifest),count($normalizedEntries),$totalBytes,$actorUserId]);
        $pdo->prepare('UPDATE hosted_games SET current_release_public_id=?,entry_file=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$releasePublicId,$entryFile,$actorUserId,$gameId]);
        $pdo->commit();

        mg_audit($auditEvent,'hosted_game_release',[
            'game_id'=>$gamePublicId,
            'merchant_user_id'=>$merchantUserId,
            'release_id'=>$releasePublicId,
            'version'=>$version,
            'file_count'=>count($normalizedEntries),
            'extracted_bytes'=>$totalBytes,
            'package_checksum'=>$checksum,
        ],$actorUserId);

        return [
            'game_id'=>$gamePublicId,
            'release'=>[
                'id'=>$releasePublicId,
                'version'=>$version,
                'entry_file'=>$entryFile,
                'file_count'=>count($normalizedEntries),
                'extracted_bytes'=>$totalBytes,
                'package_checksum'=>$checksum,
                'manifest'=>$manifest,
            ],
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($destination !== '') mg_hosted_game_remove_tree($destination);
        throw $error;
    } finally {
        $zip->close();
    }
}

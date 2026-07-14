<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/hosted-games.php';

const MG_HOSTED_GAME_MAX_ZIP_BYTES = 104857600;
const MG_HOSTED_GAME_MAX_FILES = 5000;
const MG_HOSTED_GAME_MAX_EXTRACTED_BYTES = 536870912;
const MG_HOSTED_GAME_MAX_SINGLE_FILE_BYTES = 157286400;

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

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.hosted_games.manage');
mg_require_csrf_for_write($_POST);
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];
if (!mg_hosted_game_schema_ready($pdo)) mg_fail('Hosted Games setup is incomplete. Import database/hosted_games_management_v1.sql.', 503);
if (function_exists('mg_rate_limit')) mg_rate_limit('merchant.hosted_game.upload', 'user:' . $merchantUserId, 8, 600);
if (!class_exists('ZipArchive')) mg_fail('The PHP Zip extension is required before game packages can be uploaded.', 503);

$gamePublicId = trim((string)($_POST['game_id'] ?? ''));
if ($gamePublicId === '') mg_fail('Save the hosted game record before uploading a ZIP.', 422);
try {
    $game = mg_hosted_game_for_merchant($pdo, $merchantUserId, $gamePublicId, false);
} catch (Throwable $error) {
    mg_fail($error->getMessage(), 404);
}
if ((string)$game['status'] === 'archived') mg_fail('Archived games cannot receive new releases.', 409);

if (!isset($_FILES['game_zip']) || !is_array($_FILES['game_zip'])) mg_fail('Select a game ZIP to upload.', 422);
$file = $_FILES['game_zip'];
$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error !== UPLOAD_ERR_OK) mg_fail(mg_hosted_game_upload_error($error), $error === UPLOAD_ERR_NO_FILE ? 422 : 413);
$tmpName = (string)($file['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName) || !is_file($tmpName)) mg_fail('Invalid uploaded game ZIP.', 422);
$actualSize = filesize($tmpName);
$reportedSize = (int)($file['size'] ?? 0);
if ($actualSize === false || $actualSize < 1 || $actualSize > MG_HOSTED_GAME_MAX_ZIP_BYTES || ($reportedSize > 0 && $reportedSize !== (int)$actualSize)) {
    mg_fail('The game ZIP is too large or invalid.', 413);
}
$originalName = trim(basename((string)($file['name'] ?? 'game.zip')));
if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') mg_fail('Hosted games must be uploaded as a ZIP file.', 422);
$signature = file_get_contents($tmpName, false, null, 0, 4);
if (!is_string($signature) || !in_array($signature, ["PK\x03\x04","PK\x05\x06","PK\x07\x08"], true)) mg_fail('The uploaded file is not a valid ZIP package.', 422);
$checksum = hash_file('sha256', $tmpName);
if (!is_string($checksum) || strlen($checksum) !== 64) mg_fail('Unable to verify the uploaded game ZIP.', 500);

$zip = new ZipArchive();
$open = $zip->open($tmpName, ZipArchive::RDONLY);
if ($open !== true) mg_fail('The game ZIP could not be opened.', 422);

$entries = [];
$totalBytes = 0;
try {
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
    $versionStmt->execute([(int)$game['id']]);
    $version = max(1, (int)$versionStmt->fetchColumn());
    $releasePublicId = mg_hosted_game_uuid();
    $destination = mg_hosted_game_release_directory($merchantUserId, $gamePublicId, $version);
    if (file_exists($destination)) throw new MgHostedGameException('The release directory already exists.');
    if (!mkdir($destination, 0700, true) && !is_dir($destination)) throw new MgHostedGameException('Unable to prepare the game release directory.');

    try {
        foreach ($normalizedEntries as $path => $entry) {
            $target = $destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new MgHostedGameException('Unable to create a game release directory.');
            }
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
        if ($entryPath === false || $destinationReal === false || !str_starts_with($entryPath, $destinationReal . DIRECTORY_SEPARATOR)) {
            throw new MgHostedGameException('The game entry file could not be verified.');
        }

        $storageKey = mg_hosted_game_storage_key($merchantUserId, $gamePublicId, $version);
        $pdo->beginTransaction();
        $lockedGame = mg_hosted_game_for_merchant($pdo, $merchantUserId, $gamePublicId, true);
        $pdo->prepare("UPDATE hosted_game_releases SET status='previous',updated_at=NOW() WHERE game_id=? AND status='active'")
            ->execute([(int)$lockedGame['id']]);
        $pdo->prepare("INSERT INTO hosted_game_releases (public_id,game_id,version_number,original_filename,storage_key,package_checksum,manifest_json,file_count,extracted_bytes,status,uploaded_by_user_id,activated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'active',?,NOW(),NOW(),NOW())")
            ->execute([$releasePublicId,(int)$lockedGame['id'],$version,mb_substr($originalName,0,255),$storageKey,$checksum,$manifest === [] ? null : mg_hosted_game_json_encode($manifest),count($normalizedEntries),$totalBytes,$merchantUserId]);
        $pdo->prepare('UPDATE hosted_games SET current_release_public_id=?,entry_file=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$releasePublicId,$entryFile,$merchantUserId,(int)$lockedGame['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_hosted_game_remove_tree($destination);
        throw $error;
    }

    mg_audit('merchant.hosted_game.release_uploaded','hosted_game_release',[
        'game_id'=>$gamePublicId,
        'release_id'=>$releasePublicId,
        'version'=>$version,
        'file_count'=>count($normalizedEntries),
        'extracted_bytes'=>$totalBytes,
        'package_checksum'=>$checksum,
    ],$merchantUserId);
    mg_ok([
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
    ],'Game ZIP uploaded and activated.',201);
} catch (InvalidArgumentException|MgHostedGameException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error','merchant.hosted_game.upload_failed','Hosted game ZIP upload failed.',[
        'game_id'=>$gamePublicId,
        'filename'=>$originalName,
        'byte_size'=>(int)$actualSize,
        'message'=>$error->getMessage(),
    ],$merchantUserId);
    mg_fail('Unable to upload the game ZIP.',500);
} finally {
    $zip->close();
}

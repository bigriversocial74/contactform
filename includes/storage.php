<?php
declare(strict_types=1);

/**
 * Persistent media storage helpers.
 *
 * The persistent_local driver stores user media outside the application release
 * directory. Files are delivered through /api/public/media.php so the storage
 * directory remains outside the public web root and access rules stay enforceable.
 */

function mg_storage_app_root(): string
{
    $root=realpath(dirname(__DIR__));
    return $root!==false?rtrim($root,DIRECTORY_SEPARATOR):rtrim(dirname(__DIR__),DIRECTORY_SEPARATOR);
}

function mg_storage_is_absolute_path(string $path): bool
{
    if($path==='')return false;
    if($path[0]==='/'||$path[0]==='\\')return true;
    return preg_match('/^[A-Za-z]:[\\\\\/]/',$path)===1;
}

function mg_storage_path_is_within(string $path,string $parent): bool
{
    $path=rtrim(str_replace('\\','/',$path),'/').'/';
    $parent=rtrim(str_replace('\\','/',$parent),'/').'/';
    return str_starts_with($path,$parent);
}

function mg_storage_public_roots(): array
{
    $roots=[mg_storage_app_root()=>mg_storage_app_root()];
    $documentRoot=trim((string)($_SERVER['DOCUMENT_ROOT']??''));
    if($documentRoot!==''&&mg_storage_is_absolute_path($documentRoot)){
        $resolved=realpath($documentRoot);
        if($resolved!==false)$roots[$resolved]=$resolved;
    }
    $normalized=str_replace('\\','/',mg_storage_app_root());
    if(preg_match('#^(.*)/(public_html|www|htdocs)(?:/|$)#',$normalized,$match)===1){
        $webRoot=rtrim((string)$match[1],'/').'/'.$match[2];
        $resolved=realpath($webRoot);
        if($resolved!==false)$roots[$resolved]=$resolved;
    }
    return array_values($roots);
}

function mg_storage_config(): array
{
    $driver=strtolower(trim((string)mg_config_value('storage','driver','persistent_local')));
    if(!in_array($driver,['persistent_local'],true))$driver='persistent_local';
    return [
        'driver'=>$driver,
        'root'=>trim((string)mg_config_value('storage','root','')),
        'public_endpoint'=>trim((string)mg_config_value('storage','public_endpoint','/api/public/media.php')),
        'require_persistent'=>(bool)mg_config_value('storage','require_persistent',true),
        'legacy_root'=>trim((string)mg_config_value('storage','legacy_root',mg_storage_app_root())),
    ];
}

function mg_storage_normalize_key(string $key): string
{
    $key=ltrim(str_replace('\\','/',trim($key)),'/');
    if($key===''||strlen($key)>700||str_contains($key,"\0")||preg_match('#(^|/)\.\.(/|$)#',$key)===1){
        throw new InvalidArgumentException('Invalid storage key.');
    }
    if(preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#',$key)!==1){
        throw new InvalidArgumentException('Invalid storage key.');
    }
    return $key;
}

function mg_storage_root(bool $create=true): string
{
    $config=mg_storage_config();
    $configured=$config['root'];
    if(!mg_storage_is_absolute_path($configured)){
        throw new RuntimeException('Persistent media storage root must be an absolute path.');
    }
    $configured=rtrim($configured,"/\\");
    if($create&&!is_dir($configured)&&!mkdir($configured,0750,true)&&!is_dir($configured)){
        throw new RuntimeException('Persistent media storage directory could not be created.');
    }
    $resolved=realpath($configured);
    if($resolved===false||!is_dir($resolved)){
        throw new RuntimeException('Persistent media storage directory is unavailable.');
    }
    $resolved=rtrim($resolved,DIRECTORY_SEPARATOR);
    if($config['require_persistent']){
        foreach(mg_storage_public_roots() as $publicRoot){
            if(mg_storage_path_is_within($resolved,$publicRoot)){
                throw new RuntimeException('Persistent media storage must be outside application and public web directories.');
            }
        }
    }
    if(!is_readable($resolved)||!is_writable($resolved)){
        throw new RuntimeException('Persistent media storage must be readable and writable by PHP.');
    }
    return $resolved;
}

function mg_storage_assert_ready(bool $initialize=false,bool $writeProbe=false): array
{
    $root=mg_storage_root($initialize);
    $sentinel=$root.'/.microgifter-storage';
    if($initialize&&!is_file($sentinel)){
        $payload=json_encode([
            'service'=>'microgifter',
            'purpose'=>'persistent-user-media',
            'created_at'=>gmdate('c'),
        ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        if(file_put_contents($sentinel,$payload,LOCK_EX)===false){
            throw new RuntimeException('Persistent media storage sentinel could not be created.');
        }
        @chmod($sentinel,0640);
    }
    if(!is_file($sentinel)||!is_readable($sentinel)){
        throw new RuntimeException('Persistent media storage is not initialized. Run the storage readiness command.');
    }
    if($writeProbe){
        $probeDirectory=$root.'/.probes';
        if(!is_dir($probeDirectory)&&!mkdir($probeDirectory,0750,true)&&!is_dir($probeDirectory)){
            throw new RuntimeException('Persistent media storage probe directory could not be created.');
        }
        $probe=$probeDirectory.'/'.bin2hex(random_bytes(12)).'.probe';
        $expected=random_bytes(48);
        if(file_put_contents($probe,$expected,LOCK_EX)===false){
            throw new RuntimeException('Persistent media storage write probe failed.');
        }
        $actual=file_get_contents($probe);
        @unlink($probe);
        if(!is_string($actual)||!hash_equals($expected,$actual)){
            throw new RuntimeException('Persistent media storage read verification failed.');
        }
    }
    $config=mg_storage_config();
    return [
        'driver'=>$config['driver'],
        'root'=>$root,
        'persistent'=>array_reduce(
            mg_storage_public_roots(),
            static fn(bool $outside,string $publicRoot): bool=>$outside&&!mg_storage_path_is_within($root,$publicRoot),
            true
        ),
        'initialized'=>true,
        'writable'=>is_writable($root),
        'free_bytes'=>@disk_free_space($root)?:null,
    ];
}

function mg_storage_feed_key(int $userId,string $publicId,string $extension): string
{
    $extension=strtolower(trim($extension));
    if($userId<1||preg_match('/^[a-f0-9-]{36}$/i',$publicId)!==1||preg_match('/^[a-z0-9]{2,8}$/',$extension)!==1){
        throw new InvalidArgumentException('Invalid feed media storage parameters.');
    }
    return mg_storage_normalize_key(
        'feed/'.gmdate('Y/m').'/user-'.$userId.'/'.str_replace('-','',strtolower($publicId)).'.'.$extension
    );
}

function mg_storage_absolute_path(string $key,bool $createParent=false): string
{
    $key=mg_storage_normalize_key($key);
    $root=mg_storage_root(true);
    $path=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$key);
    $parent=dirname($path);
    if($createParent&&!is_dir($parent)&&!mkdir($parent,0750,true)&&!is_dir($parent)){
        throw new RuntimeException('Persistent media storage subdirectory could not be created.');
    }
    $resolvedParent=realpath($parent);
    if($resolvedParent===false||!mg_storage_path_is_within($resolvedParent,$root)){
        throw new RuntimeException('Persistent media storage path escaped its configured root.');
    }
    return $path;
}

function mg_storage_store_uploaded_file(string $temporaryPath,string $key): string
{
    if($temporaryPath===''||!is_uploaded_file($temporaryPath)){
        throw new InvalidArgumentException('Invalid uploaded file.');
    }
    mg_storage_assert_ready(false,false);
    $path=mg_storage_absolute_path($key,true);
    if(file_exists($path))throw new RuntimeException('Persistent media destination already exists.');
    if(!move_uploaded_file($temporaryPath,$path)){
        throw new RuntimeException('Unable to move the upload into persistent media storage.');
    }
    @chmod($path,0640);
    return $path;
}

function mg_storage_copy_file(string $sourcePath,string $key): string
{
    if(!is_file($sourcePath)||!is_readable($sourcePath))throw new RuntimeException('Source media file is unavailable.');
    mg_storage_assert_ready(false,false);
    $destination=mg_storage_absolute_path($key,true);
    if(is_file($destination))return $destination;
    $temporary=$destination.'.tmp-'.bin2hex(random_bytes(6));
    if(!copy($sourcePath,$temporary))throw new RuntimeException('Unable to copy media into persistent storage.');
    @chmod($temporary,0640);
    if(!rename($temporary,$destination)){
        @unlink($temporary);
        throw new RuntimeException('Unable to finalize the persistent media copy.');
    }
    return $destination;
}

function mg_storage_legacy_path(string $key): string
{
    $key=mg_storage_normalize_key($key);
    $legacyRoot=realpath((string)mg_storage_config()['legacy_root']);
    if($legacyRoot===false)throw new RuntimeException('Legacy media root is unavailable.');
    $path=rtrim($legacyRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$key);
    $parent=realpath(dirname($path));
    if($parent===false||!mg_storage_path_is_within($parent,$legacyRoot))throw new RuntimeException('Legacy media path is invalid.');
    return $path;
}

function mg_storage_private_legacy_path(string $key): string
{
    $key=mg_storage_normalize_key($key);
    $root=realpath(mg_storage_app_root().'/storage/private');
    if($root===false)throw new RuntimeException('Private legacy media root is unavailable.');
    $path=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$key);
    $parent=realpath(dirname($path));
    if($parent===false||!mg_storage_path_is_within($parent,$root))throw new RuntimeException('Private legacy media path is invalid.');
    return $path;
}

function mg_storage_resolve_asset_path(string $provider,string $key): string
{
    return match(strtolower(trim($provider))){
        'persistent_local'=>mg_storage_absolute_path($key,false),
        'private_local'=>mg_storage_private_legacy_path($key),
        'local'=>mg_storage_legacy_path($key),
        default=>throw new RuntimeException('Unsupported media storage provider.'),
    };
}

function mg_storage_delete_asset_file(string $provider,string $key): bool
{
    $path=mg_storage_resolve_asset_path($provider,$key);
    if(!is_file($path))return true;
    return @unlink($path);
}

function mg_storage_asset_public_url(string $publicId): string
{
    if(preg_match('/^[a-f0-9-]{36}$/i',$publicId)!==1)throw new InvalidArgumentException('Invalid media asset identifier.');
    $endpoint=(string)mg_storage_config()['public_endpoint'];
    if($endpoint===''||$endpoint[0]!=='/'||str_starts_with($endpoint,'//'))$endpoint='/api/public/media.php';
    return $endpoint.(str_contains($endpoint,'?')?'&':'?').'asset='.rawurlencode(strtolower($publicId));
}

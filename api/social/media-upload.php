<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/bootstrap.php';

mg_require_method('POST');
$user=mg_require_permission('social.posts.create');
$userId=(int)$user['id'];
$input=mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('social.media.upload','user:'.$userId,30,60);

$file=$_FILES['media']??null;
$kind=strtolower(trim((string)($input['media_type']??'')));
if(!is_array($file)||!in_array($kind,['image','audio','video'],true))mg_fail('Choose a valid media file.',422);

$error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
if($error!==UPLOAD_ERR_OK){
    $messages=[
        UPLOAD_ERR_INI_SIZE=>'The file exceeds the server upload limit.',
        UPLOAD_ERR_FORM_SIZE=>'The file exceeds the allowed upload size.',
        UPLOAD_ERR_PARTIAL=>'The upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE=>'Choose a media file to upload.',
        UPLOAD_ERR_NO_TMP_DIR=>'The upload service is temporarily unavailable.',
        UPLOAD_ERR_CANT_WRITE=>'The upload could not be stored.',
        UPLOAD_ERR_EXTENSION=>'The upload was blocked by the server.',
    ];
    mg_fail($messages[$error]??'The media upload did not complete.',422);
}

$tmp=(string)($file['tmp_name']??'');
$size=(int)($file['size']??0);
$limits=['image'=>12582912,'audio'=>52428800,'video'=>209715200];
if($tmp===''||!is_uploaded_file($tmp)||$size<1||$size>$limits[$kind])mg_fail('The selected media file is not allowed.',422);

$finfo=new finfo(FILEINFO_MIME_TYPE);
$mime=strtolower((string)$finfo->file($tmp));
$types=[
    'image'=>['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/avif'=>'avif'],
    'audio'=>['audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/ogg'=>'ogg','application/ogg'=>'ogg','audio/mp4'=>'m4a','audio/x-m4a'=>'m4a'],
    'video'=>['video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov'],
];
if(!isset($types[$kind][$mime]))mg_fail('That media format is not supported.',422);

$width=null;$height=null;
if($kind==='image'){
    $dimensions=@getimagesize($tmp);
    if(is_array($dimensions)){
        $width=(int)($dimensions[0]??0);
        $height=(int)($dimensions[1]??0);
        if($width<1||$height<1||$width>12000||$height>12000||($width*$height)>40000000){
            mg_fail('Image dimensions are not allowed.',422);
        }
    }elseif($mime!=='image/avif'){
        mg_fail('The image could not be verified.',422);
    }
}

$pdo=mg_db();
$quota=$pdo->prepare(
    "SELECT COUNT(*) asset_count,COALESCE(SUM(a.byte_size),0) total_bytes
     FROM catalog_assets a
     LEFT JOIN feed_post_assets fpa ON fpa.asset_id=a.id
     WHERE a.owner_user_id=? AND a.status='ready' AND fpa.id IS NULL
       AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.source'))='social_feed'
       AND a.updated_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)"
);
$quota->execute([$userId]);
$usage=$quota->fetch(PDO::FETCH_ASSOC)?:[];
if((int)($usage['asset_count']??0)>=40||((int)($usage['total_bytes']??0)+$size)>1073741824){
    mg_fail('Too many unattached uploads. Save a post or wait for abandoned uploads to be cleaned up.',429);
}

$publicId=mg_public_uuid();
$storageKey=mg_storage_feed_key($userId,$publicId,$types[$kind][$mime]);
try{
    $absolutePath=mg_storage_store_uploaded_file($tmp,$storageKey);
}catch(InvalidArgumentException $error){
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    mg_security_log('error','social.media_storage_unavailable','Persistent feed media storage is unavailable.',[
        'exception_class'=>$error::class,
        'message'=>$error->getMessage(),
    ],$userId);
    mg_fail('Persistent media storage is unavailable. The upload was not saved.',503);
}

$checksum=hash_file('sha256',$absolutePath)?:null;
$original=preg_replace('/[\x00-\x1F\x7F]+/u','',basename((string)($file['name']??'media')))??'media';
$original=mb_substr($original!==''?$original:'media',0,255);
$metadata=json_encode([
    'source'=>'social_feed',
    'feed_state'=>'unattached',
    'storage_class'=>'persistent',
    'uploaded_at'=>gmdate('c'),
],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);

try{
    $pdo->beginTransaction();
    $pdo->prepare(
        "INSERT INTO catalog_assets
         (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,
          byte_size,checksum_sha256,width_px,height_px,duration_ms,status,metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,'ready',?,NOW(),NOW())"
    )->execute([$publicId,$userId,$kind,'persistent_local',$storageKey,$original,$mime,$size,$checksum,$width,$height,$metadata]);
    $pdo->commit();
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_storage_delete_asset_file('persistent_local',$storageKey);
    mg_security_log('error','social.media_upload_failed','Feed media registration failed.',[
        'exception_class'=>$error::class,
        'media_type'=>$kind,
    ],$userId);
    mg_fail('Unable to register the uploaded media.',500);
}

mg_audit('social.media_uploaded','catalog_asset',[
    'asset_id'=>$publicId,
    'asset_type'=>$kind,
    'byte_size'=>$size,
    'storage_provider'=>'persistent_local',
],$userId);
mg_ok([
    'asset_id'=>$publicId,
    'type'=>$kind,
    'url'=>mg_storage_asset_public_url($publicId),
    'original_filename'=>$original,
    'mime_type'=>$mime,
    'byte_size'=>$size,
    'width_px'=>$width,
    'height_px'=>$height,
],'Media uploaded.',201);

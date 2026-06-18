<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
mg_require_method('POST');
$user=mg_require_permission('social.posts.create');
$input=mg_input();mg_require_csrf_for_write($input);
mg_rate_limit('social.media.upload','user:'.(int)$user['id'],30,60);
$file=$_FILES['media']??null;$kind=strtolower(trim((string)($input['media_type']??'')));
if(!is_array($file)||!in_array($kind,['image','audio','video'],true))mg_fail('Choose a valid media file.',422);
if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)mg_fail('The media upload did not complete.',422);
$tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);
$limits=['image'=>12582912,'audio'=>52428800,'video'=>209715200];
if($tmp===''||!is_uploaded_file($tmp)||$size<1||$size>$limits[$kind])mg_fail('The selected media file is not allowed.',422);
$mime=strtolower((string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp));
$types=['image'=>['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/avif'=>'avif'],'audio'=>['audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/ogg'=>'ogg','application/ogg'=>'ogg','audio/mp4'=>'m4a','audio/x-m4a'=>'m4a'],'video'=>['video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov']];
if(!isset($types[$kind][$mime]))mg_fail('That media format is not supported.',422);
$publicId=mg_public_uuid();$dir='uploads/feed/'.gmdate('Y/m').'/user-'.(int)$user['id'];$root=dirname(__DIR__,2);$absoluteDir=$root.'/'.$dir;
if(!is_dir($absoluteDir)&&!mkdir($absoluteDir,0755,true)&&!is_dir($absoluteDir))mg_fail('Media storage is unavailable.',503);
$key=$dir.'/'.str_replace('-','',$publicId).'.'.$types[$kind][$mime];$path=$root.'/'.$key;
if(!move_uploaded_file($tmp,$path))mg_fail('Unable to store the uploaded media.',500);
@chmod($path,0644);$checksum=hash_file('sha256',$path)?:null;$original=mb_substr(basename((string)($file['name']??'media')),0,255);
$width=null;$height=null;if($kind==='image'){$d=@getimagesize($path);if(is_array($d)){$width=(int)($d[0]??0)?:null;$height=(int)($d[1]??0)?:null;}}
$pdo=mg_db();try{$pdo->beginTransaction();$pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,duration_ms,status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,'ready',?,NOW(),NOW())")->execute([$publicId,(int)$user['id'],$kind,'local',$key,$original,$mime,$size,$checksum,$width,$height,json_encode(['source'=>'social_feed'],JSON_UNESCAPED_SLASHES)]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();@unlink($path);mg_fail('Unable to register the uploaded media.',500);}
mg_audit('social.media_uploaded','catalog_asset',['asset_id'=>$publicId,'asset_type'=>$kind,'byte_size'=>$size],(int)$user['id']);
mg_ok(['asset_id'=>$publicId,'type'=>$kind,'url'=>'/'.$key,'original_filename'=>$original,'mime_type'=>$mime,'byte_size'=>$size],'Media uploaded.',201);

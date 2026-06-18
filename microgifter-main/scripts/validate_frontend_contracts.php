<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$contracts=require $root.'/config/frontend-contracts.php';
$errors=[];

foreach($contracts['stable_entrypoints'] as $name=>$contract){
    $path=$root.'/'.$contract['path'];
    if(!is_file($path)){
        $errors[]=$name.': missing entrypoint '.$contract['path'];
        continue;
    }

    $source=(string)file_get_contents($path);

    foreach($contract['required_tokens']??[] as $token){
        if(!str_contains($source,$token)){
            $errors[]=$name.': missing required contract token '.$token;
        }
    }

    foreach($contract['forbidden_tokens']??[] as $token){
        if(str_contains($source,$token)){
            $errors[]=$name.': forbidden delegation or contract token present '.$token;
        }
    }

    foreach($contract['ordered_tokens']??[] as $pair){
        if(!is_array($pair)||count($pair)!==2){
            $errors[]=$name.': invalid ordered token declaration';
            continue;
        }
        [$first,$second]=$pair;
        $firstPosition=strpos($source,(string)$first);
        $secondPosition=strpos($source,(string)$second);
        if($firstPosition===false||$secondPosition===false||$firstPosition>=$secondPosition){
            $errors[]=$name.': expected '.$first.' before '.$second;
        }
    }
}

if(is_file($root.'/assets/js/cart-core.js')){
    $errors[]='cart: assets/js/cart-core.js must not exist; cart.js is the stable implementation entrypoint.';
}

if($errors){
    fwrite(STDERR,"Frontend contract validation failed:\n");
    foreach($errors as $error){
        fwrite(STDERR,'- '.$error."\n");
    }
    exit(1);
}

echo 'Frontend contracts valid: '.count($contracts['stable_entrypoints'])." stable entrypoints checked.\n";

<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = [
    'examples/local-quest-rewards/cover.php',
    'examples/local-quest-rewards/how-it-works.php',
    'examples/local-quest-rewards/for-businesses.php',
    'examples/local-quest-rewards/assets/public-site.css',
    'examples/local-quest-rewards/assets/public-site.js',
    'examples/local-quest-rewards/assets/public/brand-mark.svg',
    'examples/local-quest-rewards/assets/public/hero-phone.svg',
    'examples/local-quest-rewards/assets/public/quest-placeholder.svg',
    'examples/local-quest-rewards/assets/public/quest-coffee.svg',
    'examples/local-quest-rewards/assets/public/quest-riverwalk.svg',
    'examples/local-quest-rewards/assets/public/quest-bookstore.svg',
    'examples/local-quest-rewards/assets/public/quest-burger.svg',
    'examples/local-quest-rewards/assets/public/quest-city.svg',
];
$checks = [];
foreach ($required as $path) $checks[] = ['name'=>'file:'.$path,'ok'=>is_file($root.'/'.$path)];
$read = static fn(string $path): string => is_file($root.'/'.$path) ? (string)file_get_contents($root.'/'.$path) : '';
$cover = $read('examples/local-quest-rewards/cover.php');
$how = $read('examples/local-quest-rewards/how-it-works.php');
$business = $read('examples/local-quest-rewards/for-businesses.php');
$css = $read('examples/local-quest-rewards/assets/public-site.css');
$js = $read('examples/local-quest-rewards/assets/public-site.js');
$checks[] = ['name'=>'Microgifter brand system','ok'=>str_contains($cover,'MICROGIFTER') && str_contains($cover,'brand-mark.svg') && str_contains($how,'brand-mark.svg') && str_contains($business,'brand-mark.svg')];
$checks[] = ['name'=>'approved homepage headline','ok'=>str_contains($cover,'Explore. Gift.') && str_contains($cover,'Earn Rewards.')];
$checks[] = ['name'=>'dynamic quest rendering','ok'=>str_contains($cover,'lqr_visible_quests') && str_contains($cover,'lq_public_image') && str_contains($cover,'foreach($featured')];
$checks[] = ['name'=>'separate public pages','ok'=>str_contains($how,'How It Works') && str_contains($business,'Drive Traffic.') && str_contains($business,'Reward Customers.')];
$checks[] = ['name'=>'real repository asset references','ok'=>!str_contains($cover,'.webp') && !str_contains($how,'.webp') && !str_contains($business,'.webp') && str_contains($cover,'hero-phone.svg') && str_contains($cover,'quest-placeholder.svg')];
$checks[] = ['name'=>'responsive layout','ok'=>str_contains($css,'@media(max-width:1120px)') && str_contains($css,'@media(max-width:720px)') && str_contains($css,'.menu-toggle')];
$checks[] = ['name'=>'accessible mobile nav','ok'=>str_contains($cover,'aria-expanded="false"') && str_contains($cover,'aria-controls="primary-nav"') && str_contains($js,"setAttribute('aria-expanded'")];
$checks[] = ['name'=>'motion preference','ok'=>str_contains($css,'prefers-reduced-motion')];
$checks[] = ['name'=>'install-state routing','ok'=>str_contains($cover,"header('Location: install.php')") && str_contains($how,"header('Location: install.php')") && str_contains($business,"header('Location: install.php')")];
$checks[] = ['name'=>'participant routing','ok'=>str_contains($cover,"$isAuthed?'index.php':'signin.php?mode=signup'") && str_contains($cover,'wallet.php')];
$failed = array_values(array_filter($checks, static fn(array $c): bool => !$c['ok']));
$result = ['ok'=>$failed===[],'score'=>$failed===[]?'10/10':sprintf('%.1f/10',10-(count($failed)*.5)),'checks'=>$checks,'failed'=>$failed,'generated_at'=>gmdate('c')];
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($result['ok']?0:1);

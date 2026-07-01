<?php
declare(strict_types=1);

function mg_page_definition(string $pageId): array
{
    $presentation = ['type'=>'presentation','label'=>'Play','primary'=>true];
    $home = ['type'=>'link','label'=>'Home','href'=>'/index.php'];
    $learn = ['type'=>'link','label'=>'Learn More','href'=>'/learn-more.php'];
    $authAssets = ['universal-header','auth-pages','auth-forms'];
    $authPublicHeader = [
        'presentation' => false,
        'links' => [
            ['label' => 'Learn More', 'href' => '/learn-more.php'],
        ],
    ];

    $definitions = [
        'learn-more' => [
            'assets'=>['universal-header','agent-presentation','learn-more-questionnaire'],
            'header_controls'=>[$presentation,$home],
            'public_header'=>['presentation'=>true,'links'=>[['label'=>'Home','href'=>'/index.php']]],
        ],
        'signin' => [
            'assets'=>$authAssets,
            'body_class'=>'mg-auth-page',
            'public_header'=>$authPublicHeader,
        ],
        'signup' => [
            'assets'=>$authAssets,
            'body_class'=>'mg-auth-page',
            'public_header'=>$authPublicHeader,
        ],
        'forgot-password' => [
            'assets'=>$authAssets,
            'body_class'=>'mg-auth-page',
            'public_header'=>$authPublicHeader,
        ],
        'reset-password' => [
            'assets'=>$authAssets,
            'body_class'=>'mg-auth-page',
            'public_header'=>$authPublicHeader,
        ],
        'public' => [
            'assets'=>['universal-header'],
            'header_controls'=>[$home,$learn],
        ],
    ];

    return $definitions[$pageId] ?? $definitions['public'];
}

function mg_page_manifest(array $overrides = []): array
{
    $pageId = (string) ($overrides['id'] ?? 'public');
    $defaults = array_replace_recursive([
        'id'=>$pageId,
        'title'=>'Microgifter',
        'section'=>'public',
        'access'=>'public',
        'header_mode'=>'public',
        'header_controls'=>[],
        'assets'=>[],
        'styles'=>[],
        'scripts'=>[],
        'onboarding'=>null,
        'body_class'=>'',
    ], mg_page_definition($pageId));
    $manifest = array_replace_recursive($defaults, $overrides);
    foreach (['assets','styles','scripts'] as $key) {
        $manifest[$key] = array_values(array_unique(array_filter((array) $manifest[$key])));
    }
    return $manifest;
}

function mg_asset_registry(): array
{
    return [
        'universal-header'=>[
            'styles'=>['/assets/css/universal-header.css','/assets/css/account-dropdown-tabs.css','/assets/css/account-menu.css','/assets/css/cart.css','/assets/css/layout-fixes.css','/assets/css/public-header-initial-width.css'],
            'scripts'=>['/assets/js/universal-header.js','/assets/js/header-signals.js','/assets/js/cart.js','/assets/js/auth.js','/assets/js/auth-state.js'],
        ],
        'agent-presentation'=>[
            'styles'=>['/assets/css/agent-presentation.css','/assets/css/agent-presentation-layout.css'],
            'scripts'=>['/assets/js/agent-presentation.js'],
        ],
        'learn-more-questionnaire'=>['scripts'=>['/assets/js/learn-more.js']],
        'auth-pages'=>['styles'=>['/assets/css/auth-page.css']],
        'auth-forms'=>['scripts'=>['/assets/js/auth.js']],
    ];
}

function mg_resolve_page_assets(array $manifest): array
{
    $registry = mg_asset_registry();
    $styles = $manifest['styles'];
    $scripts = $manifest['scripts'];
    foreach ($manifest['assets'] as $key) {
        $asset = $registry[$key] ?? null;
        if (!$asset) { continue; }
        $styles = array_merge($styles, $asset['styles'] ?? []);
        $scripts = array_merge($scripts, $asset['scripts'] ?? []);
    }
    return ['styles'=>array_values(array_unique($styles)),'scripts'=>array_values(array_unique($scripts))];
}

function mg_onboarding_config(string $pageId): array
{
    $path = dirname(__DIR__) . '/config/onboarding/' . basename($pageId) . '.php';
    if (!is_file($path)) { return ['enabled'=>false,'page'=>$pageId,'sections'=>[]]; }
    $config = require $path;
    return is_array($config) ? $config : ['enabled'=>false,'page'=>$pageId,'sections'=>[]];
}

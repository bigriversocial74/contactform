<?php
declare(strict_types=1);

function mg_builder_supported_types(): array
{
    return ['simple_product','greeting_card','multimedia_greeting_card','simple_collab'];
}

function mg_builder_type(string $value): string
{
    if (!in_array($value,mg_builder_supported_types(),true)) {
        throw new InvalidArgumentException('Invalid builder type.');
    }
    return $value;
}

function mg_builder_allowed_asset_roles(string $builderType): array
{
    return match ($builderType) {
        'simple_product' => ['thumbnail','cover'],
        'greeting_card' => ['thumbnail','cover','inside_cover'],
        'multimedia_greeting_card' => ['thumbnail','cover','inside_cover','audio','video'],
        'simple_collab' => ['thumbnail','cover'],
        default => [],
    };
}

function mg_builder_validate_asset_roles(string $builderType,array $assetMap): void
{
    $allowed=mg_builder_allowed_asset_roles($builderType);
    foreach(array_keys($assetMap) as $role){
        if(!in_array((string)$role,$allowed,true)){
            throw new InvalidArgumentException('The selected media is not supported by this product type.');
        }
    }
}

function mg_builder_validate_publish_type(string $builderType,array $payload,array $assetMap): void
{
    mg_builder_validate_asset_roles($builderType,$assetMap);

    if($builderType==='greeting_card'||$builderType==='multimedia_greeting_card'){
        $message=trim((string)($payload['message']??''));
        if($message==='')throw new InvalidArgumentException('Enter the inside greeting-card message before publishing.');
    }
}

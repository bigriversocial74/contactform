<?php
declare(strict_types=1);

/**
 * Canonical role badge definitions.
 *
 * Role badges communicate account role status only. They never communicate
 * identity, nonprofit, charity, campaign, financial, government, or platform
 * verification.
 *
 * @return array<string,array{key:string,label:string,icon:string,rendered_label:string,disclaimer:string}>
 */
function mg_role_badge_catalog(): array
{
    return [
        'community' => [
            'key' => 'community',
            'label' => 'Community',
            'icon' => 'star',
            'rendered_label' => '★ Community',
            'disclaimer' => 'Role status only; not verification, certification, campaign approval, donation eligibility, financial review, government status, or Microgifter endorsement.',
        ],
    ];
}

/**
 * @param list<string> $roleSlugs
 * @return list<array{key:string,label:string,icon:string,rendered_label:string,disclaimer:string}>
 */
function mg_role_badges_for_slugs(array $roleSlugs): array
{
    $catalog = mg_role_badge_catalog();
    $badges = [];
    $seen = [];

    foreach ($roleSlugs as $roleSlug) {
        $slug = strtolower(trim((string) $roleSlug));
        if ($slug === '' || isset($seen[$slug]) || !isset($catalog[$slug])) {
            continue;
        }
        $badges[] = $catalog[$slug];
        $seen[$slug] = true;
    }

    return $badges;
}

@php
    $payload = is_array($block->data) ? $block->data : [];
    $type = app(\Filagora\Cms\BlockTypes\BlockTypeRegistry::class)->normalize($block->type);
@endphp

@switch($type)
    @case('raw_html')
        <div class="cms-rich-content mt-4">
            {!! (string) ($payload['html'] ?? '') !!}
        </div>
        @break

    @case('hero')
        @include('cms.partials.blocks.hero', ['payload' => $payload])
        @break

    @case('image')
        @include('cms.partials.blocks.image', ['payload' => $payload])
        @break

    @case('gallery')
        @include('cms.partials.blocks.gallery', ['payload' => $payload])
        @break

    @case('card_grid')
        @include('cms.partials.blocks.card_grid', ['payload' => $payload])
        @break

    @case('feature_list')
        @include('cms.partials.blocks.feature_list', ['payload' => $payload])
        @break

    @case('embed')
        @include('cms.partials.blocks.embed', ['payload' => $payload])
        @break

    @case('divider')
        @include('cms.partials.blocks.divider', ['payload' => $payload])
        @break

    @case('promo_hero')
        @include('cms.partials.blocks.promo_hero', ['payload' => $payload])
        @break

    @case('checklist_callout')
        @include('cms.partials.blocks.checklist_callout', ['payload' => $payload])
        @break

    @case('split_info_panel')
        @include('cms.partials.blocks.split_info_panel', ['payload' => $payload])
        @break

    @case('icon_callout')
        @include('cms.partials.blocks.icon_callout', ['payload' => $payload])
        @break

    @case('comparison_matrix')
        @include('cms.partials.blocks.comparison_matrix', ['payload' => $payload])
        @break

    @case('comparison_panel')
        @include('cms.partials.blocks.comparison_panel', ['payload' => $payload])
        @break

    @case('cta_band')
        @include('cms.partials.blocks.cta_band', ['payload' => $payload])
        @break

    @case('stat_strip')
        @include('cms.partials.blocks.stat_strip', ['payload' => $payload])
        @break

    @case('donation_hero')
        @include('cms.partials.blocks.donation_hero', ['payload' => $payload])
        @break

    @case('donation_method')
        @include('cms.partials.blocks.donation_method', ['payload' => $payload])
        @break

    @case('services_hero')
        @include('cms.partials.blocks.services_hero', ['payload' => $payload])
        @break

    @case('link_grid')
        @include('cms.partials.blocks.link_grid', ['payload' => $payload])
        @break

    @case('provider_grid')
        @include('cms.partials.blocks.provider_grid', ['payload' => $payload])
        @break

    @case('logo_grid')
        @include('cms.partials.blocks.logo_grid', ['payload' => $payload])
        @break

    @case('about_hero')
        @include('cms.partials.blocks.about_hero', ['payload' => $payload])
        @break

    @case('icon_card_grid')
        @include('cms.partials.blocks.icon_card_grid', ['payload' => $payload])
        @break

    @case('accordion_list')
        @include('cms.partials.blocks.accordion_list', ['payload' => $payload])
        @break

    @case('home_hero')
        @include('cms.partials.blocks.home_hero', ['payload' => $payload])
        @break

    @case('home_value_grid')
        @include('cms.partials.blocks.home_value_grid', ['payload' => $payload])
        @break

    @case('home_included_grid')
        @include('cms.partials.blocks.home_included_grid', ['payload' => $payload])
        @break

    @case('home_deploy_steps')
        @include('cms.partials.blocks.home_deploy_steps', ['payload' => $payload])
        @break

    @case('home_feature_columns')
        @include('cms.partials.blocks.home_feature_columns', ['payload' => $payload])
        @break

    @case('home_compare_section')
        @include('cms.partials.blocks.home_compare_section', ['payload' => $payload])
        @break

    @default
        <pre class="mt-4 whitespace-pre-wrap rounded-lg border border-[#d8d1c8] bg-[#ececed] p-4 text-sm text-[#0f2133]">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
@endswitch

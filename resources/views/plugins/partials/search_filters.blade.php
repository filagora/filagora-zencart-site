@php
    $zcVersionOptions = $zcVersionOptions ?? [];
    $typeOptions = $typeOptions ?? collect();
    $sortOptions = $sortOptions ?? [
        'relevance' => 'Best match',
        'downloads_desc' => 'Most downloaded',
        'updated_desc' => 'Recently updated',
        'name_asc' => 'Name (A-Z)',
    ];
    $selectedQ = $selectedQ ?? (string) request('q', '');
    $selectedZcVersion = $selectedZcVersion ?? (string) request('zc_version', '');
    $selectedType = $selectedType ?? (string) request('type', '');
    $selectedEncapsulated = $selectedEncapsulated ?? (string) request('encapsulated', 'all');
    $selectedSort = $selectedSort ?? (string) request('sort', 'relevance');
    $submitLabel = $submitLabel ?? 'Search';
    $filterSelectClass = 'rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus:border-[#8f3f1f] focus:outline-none focus:ring-2 focus:ring-[#8f3f1f]/25';
    $filterSubmitClass = 'inline-flex items-center justify-center rounded-control border border-[#0b3d67] bg-[#0f4c81] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#0b3d67] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c81]';
@endphp

<form method="get" action="{{ route('plugins.search') }}" class="grid gap-3" role="search">
    <div class="flex flex-wrap gap-2">
        <label for="plugin-filter-q" class="sr-only">Search plugins</label>
        <input
            type="search"
            id="plugin-filter-q"
            name="q"
            value="{{ $selectedQ }}"
            placeholder="Search plugins by name, description or changelog"
            class="min-w-[16rem] flex-1 rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] placeholder:text-[#5c6570] focus:border-[#8f3f1f] focus:outline-none focus:ring-2 focus:ring-[#8f3f1f]/25"
        >
        <button type="submit" class="{{ $filterSubmitClass }}">{{ $submitLabel }}</button>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1 text-xs font-semibold text-[#304458]">
            Zen Cart version
            <select name="zc_version" class="{{ $filterSelectClass }}">
                <option value="">Any version</option>
                @foreach($zcVersionOptions as $option)
                    <option value="{{ $option }}" @selected($selectedZcVersion === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-xs font-semibold text-[#304458]">
            Category
            <select name="type" class="{{ $filterSelectClass }}">
                <option value="">All categories</option>
                @foreach($typeOptions as $typeOption)
                    <option value="{{ $typeOption->slug }}" @selected($selectedType === $typeOption->slug)>{{ $typeOption->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-xs font-semibold text-[#304458]">
            Packaging
            <select name="encapsulated" class="{{ $filterSelectClass }}">
                <option value="all" @selected($selectedEncapsulated === 'all')>Any packaging</option>
                <option value="yes" @selected($selectedEncapsulated === 'yes')>Encapsulated only</option>
                <option value="no" @selected($selectedEncapsulated === 'no')>Not encapsulated</option>
            </select>
        </label>

        <label class="grid gap-1 text-xs font-semibold text-[#304458]">
            Sort by
            <select name="sort" class="{{ $filterSelectClass }}">
                @foreach($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedSort === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="{{ $filterSubmitClass }}">Apply filters</button>
    </div>
</form>

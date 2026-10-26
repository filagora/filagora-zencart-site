@extends('layouts.main')

@section('title')
Add Release - {{ $plugin->name }}
@endsection

@section('main_class', 'bg-white')

@section('content')
    @php
        $pluginPrimaryBtnClass = 'inline-flex items-center justify-center rounded-control border border-[#933c1f] bg-[#933c1f] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $fieldClass = 'grid gap-1.5';
        $labelClass = 'text-sm font-semibold text-[#5c6570]';
        $inputClass = 'rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $fileInputClass = $inputClass . ' file:mr-3 file:rounded file:border-0 file:bg-[#f2f5fa] file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-[#304458]';
        $errorClass = 'text-sm text-[#dc3545]';
    @endphp
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        <a class="inline-flex items-center text-sm font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" href="{{ route('plugins.show', $plugin) }}">&larr; Back to plugin details</a>

        <header class="mt-3 mb-5">
            <h1 class="ui-page-title">Add New Release</h1>
            <p class="ui-page-subtitle mt-2">{{ $plugin->name }}</p>
            <p class="mt-1 text-sm text-[#5c6570]">
                @if($submissionsBypassModeration ?? false)
                    This release will be published immediately.
                @else
                    New releases are pending review before they go live.
                @endif
            </p>
        </header>

        @if($errors->any())
            <div class="mb-4 rounded-surface border border-[#efc2c6] bg-[#fff2f3] px-3 py-2 text-sm text-[#a63a45]" role="alert" aria-live="assertive">
                <strong>Please fix the highlighted fields.</strong>
            </div>
        @endif

        <form method="post" action="{{ route('plugins.release.store', $plugin) }}" enctype="multipart/form-data" class="grid gap-5">
            @csrf
            @if(($pendingReleases ?? collect())->isNotEmpty())
                @php
                    $pendingCount = $pendingReleases->count();
                    $pendingVersions = $pendingReleases->map(fn ($pending) => 'v' . $pending->displayVersion())->implode(', ');
                @endphp
                <section class="grid gap-3 rounded-surface border-2 border-notice-warning-border bg-notice-warning-bg p-4 text-notice-warning-text" role="alert" aria-labelledby="plugin-release-supersede-title">
                    <h2 id="plugin-release-supersede-title" class="text-base font-bold">{{ trans_choice('plugin.release_supersede.warning_title', $pendingCount, ['versions' => $pendingVersions]) }}</h2>
                    <ul class="grid gap-1 text-sm">
                        @foreach($pendingReleases as $pending)
                            <li><strong class="font-semibold">v{{ $pending->displayVersion() }}</strong> · uploaded {{ \App\Support\LocalTime::format($pending->created_at) }}</li>
                        @endforeach
                    </ul>
                    <p class="text-sm">{{ trans_choice('plugin.release_supersede.warning_body', $pendingCount) }}</p>
                    <label class="flex items-start gap-2 text-sm font-semibold" for="plugin-release-supersede">
                        <input
                            type="checkbox"
                            id="plugin-release-supersede"
                            name="supersede_pending_releases"
                            value="1"
                            class="mt-0.5"
                            required
                            @checked(old('supersede_pending_releases'))
                            @if($errors->has('supersede_pending_releases')) aria-invalid="true" aria-describedby="plugin-release-supersede-error" @endif
                        >
                        <span>{{ trans_choice('plugin.release_supersede.confirm_label', $pendingCount, ['versions' => $pendingVersions]) }}</span>
                    </label>
                    @if($errors->has('supersede_pending_releases'))
                        <p id="plugin-release-supersede-error" class="{{ $errorClass }}">{{ $errors->first('supersede_pending_releases') }}</p>
                    @endif
                </section>
            @endif
            <p id="plugin-release-required-help" class="text-sm text-[#5c6570]">Fields marked “(required)” must be completed.</p>

            <section class="border border-[#d8dce2] bg-white p-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Release Version (required)</span>
                        <input
                            class="{{ $inputClass }}"
                            type="text"
                            name="version"
                            value="{{ old('version') }}"
                            required
                            aria-required="true"
                            aria-describedby="plugin-release-required-help{{ $errors->has('version') ? ' plugin-release-version-error' : '' }}"
                            @if($errors->has('version')) aria-invalid="true" @endif
                        >
                        @if($errors->has('version'))
                            <p id="plugin-release-version-error" class="{{ $errorClass }}">{{ $errors->first('version') }}</p>
                        @endif
                    </label>

                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">ZIP File (required)</span>
                        <input
                            class="{{ $fileInputClass }}"
                            type="file"
                            name="zip_file"
                            accept=".zip,application/zip,application/x-zip-compressed"
                            required
                            aria-required="true"
                            aria-describedby="plugin-release-required-help{{ $errors->has('zip_file') ? ' plugin-release-zip-error' : '' }}"
                            @if($errors->has('zip_file')) aria-invalid="true" @endif
                        >
                        @if($errors->has('zip_file'))
                            <p id="plugin-release-zip-error" class="{{ $errorClass }}">{{ $errors->first('zip_file') }}</p>
                        @endif
                    </label>

                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Encapsulated Plugin? (required)</span>
                        <select
                            class="{{ $inputClass }}"
                            name="encapsulated"
                            required
                            aria-required="true"
                            aria-describedby="plugin-release-required-help{{ $errors->has('encapsulated') ? ' plugin-release-encapsulated-error' : '' }}"
                            @if($errors->has('encapsulated')) aria-invalid="true" @endif
                        >
                            <option value="unknown" @selected(old('encapsulated', 'unknown') === 'unknown')>Unknown</option>
                            <option value="yes" @selected(old('encapsulated') === 'yes')>Yes</option>
                            <option value="no" @selected(old('encapsulated') === 'no')>No</option>
                        </select>
                        <p class="mt-1 text-xs text-[#5c6570]">If “Yes”, this plugin’s numeric ID (shown on the plugin page) is written into <code>manifest.php</code> (<code>pluginId</code>) automatically when the release is published.</p>
                        @if($errors->has('encapsulated'))
                            <p id="plugin-release-encapsulated-error" class="{{ $errorClass }}">{{ $errors->first('encapsulated') }}</p>
                        @endif
                    </label>

                    <fieldset
                        class="grid gap-1.5 rounded-control border border-[#d8dce2] bg-[#f8f6f1] p-3 md:col-span-2"
                        @if($errors->has('zc_versions') || $errors->has('zc_versions.*')) aria-invalid="true" aria-describedby="plugin-release-zc-versions-error" @endif
                    >
                        <legend class="{{ $labelClass }} px-0">Compatible Zen Cart Versions</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach($zcVersions as $version)
                                @php
                                    $selectedZcVersions = old('zc_versions', $previousReleaseZcVersionIds ?? []);
                                    $selected = collect($selectedZcVersions)->contains((string) $version->id) || collect($selectedZcVersions)->contains($version->id);
                                @endphp
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        name="zc_versions[]"
                                        value="{{ $version->id }}"
                                        class="peer sr-only"
                                        @checked($selected)
                                    >
                                    <span class="inline-flex items-center rounded-pill border border-[#cabca8] bg-white px-3 py-1 text-sm text-[#304458] transition peer-checked:border-[#933c1f] peer-checked:bg-[#f7e8df] peer-checked:text-[#933c1f] peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#933c1f]">{{ $version->version }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @if($errors->has('zc_versions') || $errors->has('zc_versions.*'))
                        <p id="plugin-release-zc-versions-error" class="{{ $errorClass }} md:col-span-2">{{ $errors->first('zc_versions') ?: $errors->first('zc_versions.*') }}</p>
                    @endif

                    <label class="{{ $fieldClass }} md:col-span-2">
                        <span class="{{ $labelClass }}">Release Changelog</span>
                        <textarea
                            class="{{ $inputClass }}"
                            name="changelog"
                            rows="7"
                            @if($errors->has('changelog')) aria-invalid="true" aria-describedby="plugin-release-changelog-error" @endif
                        >{{ old('changelog') }}</textarea>
                        @if($errors->has('changelog'))
                            <p id="plugin-release-changelog-error" class="{{ $errorClass }}">{{ $errors->first('changelog') }}</p>
                        @endif
                    </label>
                </div>
            </section>

            <div>
                <button type="submit" class="{{ $pluginPrimaryBtnClass }}">Submit Release</button>
            </div>
        </form>
    </div>
@endsection

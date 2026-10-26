@extends('layouts.main')

@section('title')
Submit Plugin
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
        <a class="inline-flex items-center text-sm font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" href="{{ route('plugins.index') }}">&larr; Back to plugin library</a>

        <header class="mt-3 mb-5">
            <h1 class="ui-page-title">Submit Plugin</h1>
            <p class="ui-page-subtitle mt-2">
                @if($submissionsBypassModeration ?? false)
                    Submit a plugin with its initial release. Your submissions go live immediately.
                @else
                    Submit a plugin with its initial release. Submissions are pending review.
                @endif
            </p>
        </header>

        @if($errors->any())
            <div class="mb-4 rounded-surface border border-[#efc2c6] bg-[#fff2f3] px-3 py-2 text-sm text-[#a63a45]" role="alert" aria-live="assertive">
                <strong>Please fix the highlighted fields.</strong>
            </div>
        @endif

        <form method="post" action="{{ route('plugins.store') }}" enctype="multipart/form-data" class="grid gap-5" data-lock-on-submit>
            @csrf
            <p id="plugin-submit-required-help" class="text-sm text-[#5c6570]">Fields marked “(required)” must be completed.</p>

            <section class="border border-[#d8dce2] bg-white p-4">
                <h2 class="ui-content-title">Plugin Details</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Name (required)</span>
                        <input
                            class="{{ $inputClass }}"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            aria-required="true"
                            aria-describedby="plugin-submit-required-help{{ $errors->has('name') ? ' plugin-submit-name-error' : '' }}"
                            @if($errors->has('name')) aria-invalid="true" @endif
                        >
                        @if($errors->has('name'))
                            <p id="plugin-submit-name-error" class="{{ $errorClass }}">{{ $errors->first('name') }}</p>
                        @endif
                    </label>

                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Type (required)</span>
                        <select
                            class="{{ $inputClass }}"
                            name="plugin_type_id"
                            required
                            aria-required="true"
                            aria-describedby="plugin-submit-required-help{{ $errors->has('plugin_type_id') ? ' plugin-submit-type-error' : '' }}"
                            @if($errors->has('plugin_type_id')) aria-invalid="true" @endif
                        >
                            <option value="">Select type</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" @selected((string) old('plugin_type_id') === (string) $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @if($errors->has('plugin_type_id'))
                            <p id="plugin-submit-type-error" class="{{ $errorClass }}">{{ $errors->first('plugin_type_id') }}</p>
                        @endif
                    </label>

                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">GitHub Repository URL</span>
                        <input
                            class="{{ $inputClass }}"
                            type="url"
                            name="github_repository_url"
                            value="{{ old('github_repository_url') }}"
                            placeholder="https://github.com/owner/repo"
                            @if($errors->has('github_repository_url')) aria-invalid="true" aria-describedby="plugin-submit-github-error" @endif
                        >
                        @if($errors->has('github_repository_url'))
                            <p id="plugin-submit-github-error" class="{{ $errorClass }}">{{ $errors->first('github_repository_url') }}</p>
                        @endif
                    </label>

                    @php
                        $supportThreadMode = old('support_thread_mode', old('create_support_thread') ? 'create' : 'existing');
                    @endphp
                    <div
                        class="{{ $fieldClass }} md:col-span-2"
                        data-support-thread-field
                        data-support-thread-search-url="{{ route('plugins.support-threads.search') }}"
                    >
                        <span class="{{ $labelClass }}">Forum Support Thread</span>

                        <div class="flex flex-col gap-1.5 text-sm text-[#304458]" role="radiogroup" aria-label="Forum support thread">
                            <label class="inline-flex w-fit cursor-pointer items-center gap-2">
                                <input type="radio" name="support_thread_mode" value="existing" data-support-thread-mode @checked($supportThreadMode === 'existing')>
                                Link an existing support thread
                            </label>
                            <label class="inline-flex w-fit cursor-pointer items-center gap-2">
                                <input type="radio" name="support_thread_mode" value="url" data-support-thread-mode @checked($supportThreadMode === 'url')>
                                Paste a link to a support thread
                            </label>
                            <label class="inline-flex w-fit cursor-pointer items-center gap-2">
                                <input type="radio" name="support_thread_mode" value="create" data-support-thread-mode @checked($supportThreadMode === 'create')>
                                Create a new support thread
                            </label>
                        </div>

                        <div data-support-thread-existing>
                            <div class="relative">
                                <input
                                    type="text"
                                    class="{{ $inputClass }} w-full"
                                    data-support-thread-search
                                    placeholder="Search support threads by title&hellip;"
                                    autocomplete="off"
                                    aria-describedby="plugin-submit-thread-help{{ $errors->has('forum_thread_id') ? ' plugin-submit-thread-error' : '' }}"
                                >
                                <div
                                    data-support-thread-results
                                    class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 max-h-[220px] overflow-auto border border-[#d8dce2] bg-white shadow-[0_8px_18px_rgba(0,0,0,0.14)]"
                                    hidden
                                ></div>
                            </div>
                            <input type="hidden" name="forum_thread_id" value="{{ old('forum_thread_id') }}" data-support-thread-id>
                            <p data-support-thread-chosen @unless($selectedSupportThread) hidden @endunless class="mt-1.5 text-sm text-[#304458]">
                                Selected: <strong data-support-thread-chosen-title>{{ $selectedSupportThread ? \App\Support\LegacyTextDecoder::decode($selectedSupportThread->title) : '' }}</strong>
                                <button type="button" class="ml-2 text-[#8f3f1f] underline" data-support-thread-clear>Clear</button>
                            </p>
                            <p id="plugin-submit-thread-help" class="mt-1 text-xs text-[#5c6570]">Leave blank for no support thread.</p>
                            @if($errors->has('forum_thread_id'))
                                <p id="plugin-submit-thread-error" class="{{ $errorClass }}">{{ $errors->first('forum_thread_id') }}</p>
                            @endif
                        </div>

                        <div data-support-thread-url hidden>
                            <input
                                type="url"
                                class="{{ $inputClass }} w-full"
                                name="support_thread_url"
                                value="{{ old('support_thread_url') }}"
                                placeholder="{{ url('/threads/12345') }}"
                                @if($errors->has('support_thread_url')) aria-invalid="true" aria-describedby="plugin-submit-support-thread-url-error" @endif
                            >
                            <p class="mt-1 text-xs text-[#5c6570]">Must be a link to a thread on this forum that you can view.</p>
                            @if($errors->has('support_thread_url'))
                                <p id="plugin-submit-support-thread-url-error" class="{{ $errorClass }}">{{ $errors->first('support_thread_url') }}</p>
                            @endif
                        </div>

                        <div data-support-thread-new hidden class="grid gap-1.5">
                            <select
                                class="{{ $inputClass }}"
                                name="support_thread_forum_id"
                                @if($errors->has('support_thread_forum_id')) aria-invalid="true" aria-describedby="plugin-submit-support-thread-forum-error" @endif
                            >
                                <option value="">Select a forum</option>
                                @foreach($supportForums as $supportForum)
                                    <option value="{{ $supportForum->id }}" @selected((string) old('support_thread_forum_id') === (string) $supportForum->id)>{{ $supportForum->name }}</option>
                                @endforeach
                            </select>
                            @if($errors->has('support_thread_forum_id'))
                                <p id="plugin-submit-support-thread-forum-error" class="{{ $errorClass }}">{{ $errors->first('support_thread_forum_id') }}</p>
                            @endif

                            <input
                                type="text"
                                class="{{ $inputClass }} w-full"
                                name="support_thread_title"
                                value="{{ old('support_thread_title') }}"
                                maxlength="180"
                                data-support-thread-title-input
                                placeholder="Support thread title (defaults to the plugin name)"
                                @if($errors->has('support_thread_title')) aria-invalid="true" aria-describedby="plugin-submit-support-thread-title-error" @endif
                            >
                            @if($errors->has('support_thread_title'))
                                <p id="plugin-submit-support-thread-title-error" class="{{ $errorClass }}">{{ $errors->first('support_thread_title') }}</p>
                            @endif
                        </div>
                    </div>

                    <label class="{{ $fieldClass }} md:col-span-2">
                        <span class="{{ $labelClass }}">Description</span>
                        <textarea
                            class="{{ $inputClass }}"
                            name="description"
                            rows="5"
                            @if($errors->has('description')) aria-invalid="true" aria-describedby="plugin-submit-description-error" @endif
                        >{{ old('description') }}</textarea>
                        @if($errors->has('description'))
                            <p id="plugin-submit-description-error" class="{{ $errorClass }}">{{ $errors->first('description') }}</p>
                        @endif
                    </label>
                </div>
            </section>

            <section class="border border-[#d8dce2] bg-white p-4">
                <h2 class="ui-content-title">Initial Release</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Release Version (required)</span>
                        <input
                            class="{{ $inputClass }}"
                            type="text"
                            name="release_version"
                            value="{{ old('release_version') }}"
                            required
                            aria-required="true"
                            aria-describedby="plugin-submit-required-help{{ $errors->has('release_version') ? ' plugin-submit-release-version-error' : '' }}"
                            @if($errors->has('release_version')) aria-invalid="true" @endif
                        >
                        @if($errors->has('release_version'))
                            <p id="plugin-submit-release-version-error" class="{{ $errorClass }}">{{ $errors->first('release_version') }}</p>
                        @endif
                    </label>

                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">ZIP File (required)</span>
                        <input
                            class="{{ $fileInputClass }}"
                            type="file"
                            name="release_zip_file"
                            accept=".zip,application/zip,application/x-zip-compressed"
                            required
                            aria-required="true"
                            aria-describedby="plugin-submit-required-help{{ $errors->has('release_zip_file') ? ' plugin-submit-release-zip-error' : '' }}"
                            @if($errors->has('release_zip_file')) aria-invalid="true" @endif
                        >
                        @if($errors->has('release_zip_file'))
                            <p id="plugin-submit-release-zip-error" class="{{ $errorClass }}">{{ $errors->first('release_zip_file') }}</p>
                        @endif
                    </label>

                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Encapsulated Plugin? (required)</span>
                        <select
                            class="{{ $inputClass }}"
                            name="release_encapsulated"
                            required
                            aria-required="true"
                            aria-describedby="plugin-submit-required-help{{ $errors->has('release_encapsulated') ? ' plugin-submit-encapsulated-error' : '' }}"
                            @if($errors->has('release_encapsulated')) aria-invalid="true" @endif
                        >
                            <option value="unknown" @selected(old('release_encapsulated', 'unknown') === 'unknown')>Unknown</option>
                            <option value="yes" @selected(old('release_encapsulated') === 'yes')>Yes</option>
                            <option value="no" @selected(old('release_encapsulated') === 'no')>No</option>
                        </select>
                        <p class="mt-1 text-xs text-[#5c6570]">If “Yes”, we write your plugin’s numeric ID into <code>manifest.php</code> (<code>pluginId</code>) automatically the first time a release is published. The ID is also shown on the plugin’s page.</p>
                        @if($errors->has('release_encapsulated'))
                            <p id="plugin-submit-encapsulated-error" class="{{ $errorClass }}">{{ $errors->first('release_encapsulated') }}</p>
                        @endif
                    </label>

                    <fieldset
                        class="grid gap-1.5 rounded-control border border-[#d8dce2] bg-[#f8f6f1] p-3 md:col-span-2"
                        @if($errors->has('release_zc_versions') || $errors->has('release_zc_versions.*')) aria-invalid="true" aria-describedby="plugin-submit-zc-versions-error" @endif
                    >
                        <legend class="{{ $labelClass }} px-0">Compatible Zen Cart Versions</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach($zcVersions as $version)
                                @php
                                    $selected = collect(old('release_zc_versions', []))->contains((string) $version->id) || collect(old('release_zc_versions', []))->contains($version->id);
                                @endphp
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        name="release_zc_versions[]"
                                        value="{{ $version->id }}"
                                        class="peer sr-only"
                                        @checked($selected)
                                    >
                                    <span class="inline-flex items-center rounded-pill border border-[#cabca8] bg-white px-3 py-1 text-sm text-[#304458] transition peer-checked:border-[#933c1f] peer-checked:bg-[#f7e8df] peer-checked:text-[#933c1f] peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#933c1f]">{{ $version->version }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @if($errors->has('release_zc_versions') || $errors->has('release_zc_versions.*'))
                        <p id="plugin-submit-zc-versions-error" class="{{ $errorClass }} md:col-span-2">{{ $errors->first('release_zc_versions') ?: $errors->first('release_zc_versions.*') }}</p>
                    @endif

                    <label class="{{ $fieldClass }} md:col-span-2">
                        <span class="{{ $labelClass }}">Release Changelog</span>
                        <textarea
                            class="{{ $inputClass }}"
                            name="release_changelog"
                            rows="6"
                            @if($errors->has('release_changelog')) aria-invalid="true" aria-describedby="plugin-submit-release-changelog-error" @endif
                        >{{ old('release_changelog') }}</textarea>
                        @if($errors->has('release_changelog'))
                            <p id="plugin-submit-release-changelog-error" class="{{ $errorClass }}">{{ $errors->first('release_changelog') }}</p>
                        @endif
                    </label>
                </div>
            </section>

            <div>
                <button type="submit" class="{{ $pluginPrimaryBtnClass }}">Submit Plugin</button>
            </div>
        </form>
    </div>
@endsection

@extends('layouts.main')

@section('title')
Profile
@endsection

@section('main_class', 'bg-white')

@section('content')
    @php
        /** @var \App\Models\User|null $authUser */
        $authUser = auth()->user();
        $activeTab = $activeTab ?? 'details';
        $newsletterSubscriber = $newsletterSubscriber ?? null;
        $newsletterSubscribed = $newsletterSubscriber?->status === \App\NewsletterSubscriberStatus::Subscribed;
        $newsletterStatusLabel = $newsletterSubscriber?->status?->value ?? 'not subscribed';
        $detailsBag = $errors->getBag('details');
        $passwordBag = $errors->getBag('password');
        $apiTokenBag = $errors->getBag('apiToken');
        $settingsBag = $errors->getBag('settings');
        $privacyRequestBag = $errors->getBag('privacyRequest');
        $entitlementRequestBag = $errors->getBag('entitlementRequest');
        $avatarBag = $errors->getBag('avatar');
        $twoFactorBag = $errors->getBag('twoFactor');
        $allowPrivateMessages = (bool) old('allow_private_messages', $authUser?->allow_private_messages ?? true);
        $searchIconEnabled = (bool) old('search_icon_enabled', $authUser?->search_icon_enabled ?? false);
        $showArchivedPluginReleases = (bool) old('show_archived_plugin_releases', $authUser?->show_archived_plugin_releases ?? false);
        $prefersRichEditor = (bool) old('prefers_rich_editor', $authUser?->prefers_rich_editor ?? true);
        $autoSubscribeOnPost = (bool) old('auto_subscribe_on_post', $authUser?->auto_subscribe_on_post ?? false);
        $currentTimezone = old('timezone', $authUser?->timezone ?? '');
        $currentTimeFormat = old('time_format', $authUser?->time_format?->value ?? \App\TimeFormat::TWELVE_HOUR->value);
        $storedSignature = (string) ($authUser?->signature ?? '');
        $currentSignature = old('signature', $storedSignature);
        $gdprEnforcementEnabled = (bool) ($gdprEnforcementEnabled ?? true);
        $privacyAckRequired = $gdprEnforcementEnabled && $activeTab === 'privacy' && (bool) session('privacy_ack_required');
        $tabBaseClass = 'inline-flex items-center gap-1.5 rounded-pill border border-[#cabca8] px-3 py-1.5 text-xs font-semibold text-[#5c6570] transition hover:border-[#b9a68d] hover:text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $tabActiveClass = 'inline-flex items-center gap-1.5 rounded-pill border border-[#933c1f] bg-[#f7e8df] px-3 py-1.5 text-xs font-semibold text-[#933c1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $panelBaseClass = 'p-4';
        $formClass = 'grid max-w-[760px] gap-2.5';
        $fieldClass = 'grid gap-1.5';
        $labelClass = 'text-sm font-semibold text-[#5c6570]';
        $inputClass = 'rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $primaryButtonClass = 'w-fit rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $secondaryButtonClass = 'w-fit rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $dangerButtonClass = 'w-fit rounded-pill border border-[#bf1f2f] bg-[#dc3545] px-3.5 py-2 text-sm font-semibold text-white transition hover:border-[#9d1826] hover:bg-[#bf1f2f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bf1f2f]';
        $errorClass = 'text-sm text-[#dc3545]';
        $metaClass = 'text-sm text-[#5c6570]';
        $apiTokens = $apiTokens ?? collect();
        $newApiToken = $newApiToken ?? null;
        $newRecoveryCodes = $newRecoveryCodes ?? null;
        $recoveryCodesRemainingCount = $recoveryCodesRemainingCount ?? 0;
    @endphp

    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        <header class="mb-5">
            <h1 class="text-3xl font-semibold text-[#0f2133]">Profile Settings</h1>
            <p class="mt-1 text-sm text-[#5c6570]">Manage your account details, security, messaging, and avatar.</p>
        </header>

        <section class="mt-[18px] grid gap-3" aria-label="Profile settings">

        @if(session('profile_status'))
            <div class="border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]" role="status" aria-live="polite">{{ session('profile_status') }}</div>
        @endif

        <nav class="inline-flex flex-wrap gap-2" aria-label="Profile tabs">
            <a href="{{ route('profile.show', ['tab' => 'details']) }}" class="{{ $activeTab === 'details' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'details') aria-current="page" @endif>
                User Details
            </a>
            <a href="{{ route('profile.show', ['tab' => 'password']) }}" class="{{ $activeTab === 'password' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'password') aria-current="page" @endif>
                Change Password
            </a>
            <a href="{{ route('profile.show', ['tab' => 'settings']) }}" class="{{ $activeTab === 'settings' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'settings') aria-current="page" @endif>
                Settings
            </a>
            <a href="{{ route('profile.show', ['tab' => 'avatar']) }}" class="{{ $activeTab === 'avatar' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'avatar') aria-current="page" @endif>
                Avatar
            </a>
            <a href="{{ route('profile.show', ['tab' => 'two-factor']) }}" class="{{ $activeTab === 'two-factor' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'two-factor') aria-current="page" @endif>
                2FA
            </a>
            <a href="{{ route('profile.show', ['tab' => 'api-keys']) }}" class="{{ $activeTab === 'api-keys' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'api-keys') aria-current="page" @endif>
                API Keys
            </a>
            <a href="{{ route('profile.show', ['tab' => 'subscriptions']) }}" class="{{ $activeTab === 'subscriptions' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'subscriptions') aria-current="page" @endif>
                Subscriptions
            </a>
            <a href="{{ route('profile.show', ['tab' => 'billing']) }}" class="{{ $activeTab === 'billing' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'billing') aria-current="page" @endif>
                Memberships & Billing
            </a>
            <a href="{{ route('profile.show', ['tab' => 'entitlements']) }}" class="{{ $activeTab === 'entitlements' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'entitlements') aria-current="page" @endif>
                Entitlements
            </a>
            @if($gdprEnforcementEnabled)
                <a href="{{ route('profile.show', ['tab' => 'privacy']) }}" class="{{ $activeTab === 'privacy' ? $tabActiveClass : $tabBaseClass }}" @if($activeTab === 'privacy') aria-current="page" @endif>
                    Privacy Requests
                </a>
            @endif
        </nav>

        <div class="{{ $activeTab === 'details' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            <form method="post" action="{{ route('profile.details.update') }}" class="{{ $formClass }}">
                @csrf
                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Name</span>
                    <input
                        class="{{ $inputClass }}"
                        type="text"
                        name="name"
                        value="{{ old('name', $authUser?->name) }}"
                        required
                        @if($detailsBag->has('name')) aria-invalid="true" aria-describedby="profile-details-name-error" @endif
                    >
                </label>
                @if($detailsBag->has('name'))
                    <p id="profile-details-name-error" class="{{ $errorClass }}">{{ $detailsBag->first('name') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Username</span>
                    <input
                        class="{{ $inputClass }}"
                        type="text"
                        name="username"
                        value="{{ old('username', $authUser?->username) }}"
                        required
                        @if($detailsBag->has('username')) aria-invalid="true" aria-describedby="profile-details-username-error" @endif
                    >
                </label>
                @if($detailsBag->has('username'))
                    <p id="profile-details-username-error" class="{{ $errorClass }}">{{ $detailsBag->first('username') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Email</span>
                    <input
                        class="{{ $inputClass }}"
                        type="email"
                        name="email"
                        value="{{ old('email', $authUser?->email) }}"
                        required
                        @if($detailsBag->has('email')) aria-invalid="true" aria-describedby="profile-details-email-error" @endif
                    >
                </label>
                @if($detailsBag->has('email'))
                    <p id="profile-details-email-error" class="{{ $errorClass }}">{{ $detailsBag->first('email') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">User Title</span>
                    <input
                        class="{{ $inputClass }}"
                        type="text"
                        name="user_title"
                        value="{{ old('user_title', $authUser?->user_title) }}"
                        @if($detailsBag->has('user_title')) aria-invalid="true" aria-describedby="profile-details-user-title-error" @endif
                    >
                </label>
                @if($detailsBag->has('user_title'))
                    <p id="profile-details-user-title-error" class="{{ $errorClass }}">{{ $detailsBag->first('user_title') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Location</span>
                    <input
                        class="{{ $inputClass }}"
                        type="text"
                        name="location"
                        value="{{ old('location', $authUser?->location) }}"
                        @if($detailsBag->has('location')) aria-invalid="true" aria-describedby="profile-details-location-error" @endif
                    >
                </label>
                @if($detailsBag->has('location'))
                    <p id="profile-details-location-error" class="{{ $errorClass }}">{{ $detailsBag->first('location') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Occupation</span>
                    <input
                        class="{{ $inputClass }}"
                        type="text"
                        name="occupation"
                        value="{{ old('occupation', $authUser?->occupation) }}"
                        @if($detailsBag->has('occupation')) aria-invalid="true" aria-describedby="profile-details-occupation-error" @endif
                    >
                </label>
                @if($detailsBag->has('occupation'))
                    <p id="profile-details-occupation-error" class="{{ $errorClass }}">{{ $detailsBag->first('occupation') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Interests</span>
                    <textarea
                        class="{{ $inputClass }}"
                        name="interests"
                        rows="3"
                        maxlength="5000"
                        @if($detailsBag->has('interests')) aria-invalid="true" aria-describedby="profile-details-interests-error" @endif
                    >{{ old('interests', $authUser?->interests) }}</textarea>
                </label>
                @if($detailsBag->has('interests'))
                    <p id="profile-details-interests-error" class="{{ $errorClass }}">{{ $detailsBag->first('interests') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Biography</span>
                    <textarea
                        class="{{ $inputClass }}"
                        name="biography"
                        rows="6"
                        maxlength="20000"
                        @if($detailsBag->has('biography')) aria-invalid="true" aria-describedby="profile-details-biography-error" @endif
                    >{{ old('biography', $authUser?->biography) }}</textarea>
                </label>
                @if($detailsBag->has('biography'))
                    <p id="profile-details-biography-error" class="{{ $errorClass }}">{{ $detailsBag->first('biography') }}</p>
                @endif

                <button type="submit" class="{{ $primaryButtonClass }}">Save Details</button>
            </form>
        </div>

        <div class="{{ $activeTab === 'password' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            <form method="post" action="{{ route('profile.password.update') }}" class="{{ $formClass }}">
                @csrf
                <p id="profile-password-required-help" class="{{ $metaClass }}">Fields marked “(required)” must be completed.</p>
                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Current Password (required)</span>
                    <input
                        class="{{ $inputClass }}"
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                        aria-required="true"
                        aria-describedby="profile-password-required-help{{ $passwordBag->has('current_password') ? ' profile-password-current-error' : '' }}"
                        @if($passwordBag->has('current_password')) aria-invalid="true" @endif
                    >
                </label>
                @if($passwordBag->has('current_password'))
                    <p id="profile-password-current-error" class="{{ $errorClass }}">{{ $passwordBag->first('current_password') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">New Password (required)</span>
                    <input
                        class="{{ $inputClass }}"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        required
                        aria-required="true"
                        aria-describedby="profile-password-required-help{{ $passwordBag->has('password') ? ' profile-password-new-error' : '' }}"
                        @if($passwordBag->has('password')) aria-invalid="true" @endif
                    >
                </label>
                @if($passwordBag->has('password'))
                    <p id="profile-password-new-error" class="{{ $errorClass }}">{{ $passwordBag->first('password') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Confirm New Password (required)</span>
                    <input class="{{ $inputClass }}" type="password" name="password_confirmation" autocomplete="new-password" required aria-required="true" aria-describedby="profile-password-required-help">
                </label>

                <button type="submit" class="{{ $primaryButtonClass }}">Change Password</button>
            </form>
        </div>

        <div class="{{ $activeTab === 'settings' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            <form method="post" action="{{ route('profile.settings.update') }}" class="{{ $formClass }}">
                @csrf
                <p id="profile-settings-required-help" class="{{ $metaClass }}">Fields marked “(required)” must be completed.</p>

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Private Messages (required)</span>
                    <select
                        class="{{ $inputClass }}"
                        name="allow_private_messages"
                        required
                        aria-required="true"
                        aria-describedby="profile-settings-required-help{{ $settingsBag->has('allow_private_messages') ? ' profile-settings-pm-error' : '' }}"
                        @if($settingsBag->has('allow_private_messages')) aria-invalid="true" @endif
                    >
                        <option value="1" @selected($allowPrivateMessages)>Allow private messages</option>
                        <option value="0" @selected(!$allowPrivateMessages)>Do not allow private messages</option>
                    </select>
                </label>
                @if($settingsBag->has('allow_private_messages'))
                    <p id="profile-settings-pm-error" class="{{ $errorClass }}">{{ $settingsBag->first('allow_private_messages') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Header Search Icon (required)</span>
                    <select
                        class="{{ $inputClass }}"
                        name="search_icon_enabled"
                        required
                        aria-required="true"
                        aria-describedby="profile-settings-required-help{{ $settingsBag->has('search_icon_enabled') ? ' profile-settings-search-icon-error' : '' }}"
                        @if($settingsBag->has('search_icon_enabled')) aria-invalid="true" @endif
                    >
                        <option value="0" @selected(!$searchIconEnabled)>Show the search bar in the header</option>
                        <option value="1" @selected($searchIconEnabled)>Collapse search behind an icon instead</option>
                    </select>
                </label>
                @if($settingsBag->has('search_icon_enabled'))
                    <p id="profile-settings-search-icon-error" class="{{ $errorClass }}">{{ $settingsBag->first('search_icon_enabled') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Archived Plugin Releases (required)</span>
                    <select
                        class="{{ $inputClass }}"
                        name="show_archived_plugin_releases"
                        required
                        aria-required="true"
                        aria-describedby="profile-settings-required-help{{ $settingsBag->has('show_archived_plugin_releases') ? ' profile-settings-archived-releases-error' : '' }}"
                        @if($settingsBag->has('show_archived_plugin_releases')) aria-invalid="true" @endif
                    >
                        <option value="0" @selected(!$showArchivedPluginReleases)>Hide archived releases on a plugin's page</option>
                        <option value="1" @selected($showArchivedPluginReleases)>Show archived releases on a plugin's page</option>
                    </select>
                </label>
                @if($settingsBag->has('show_archived_plugin_releases'))
                    <p id="profile-settings-archived-releases-error" class="{{ $errorClass }}">{{ $settingsBag->first('show_archived_plugin_releases') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Post Editor (required)</span>
                    <select
                        class="{{ $inputClass }}"
                        name="prefers_rich_editor"
                        required
                        aria-required="true"
                        aria-describedby="profile-settings-required-help{{ $settingsBag->has('prefers_rich_editor') ? ' profile-settings-rich-editor-error' : '' }}"
                        @if($settingsBag->has('prefers_rich_editor')) aria-invalid="true" @endif
                    >
                        <option value="1" @selected($prefersRichEditor)>Rich text editor for forum posts and messages</option>
                        <option value="0" @selected(!$prefersRichEditor)>Plain Markdown editor</option>
                    </select>
                </label>
                @if($settingsBag->has('prefers_rich_editor'))
                    <p id="profile-settings-rich-editor-error" class="{{ $errorClass }}">{{ $settingsBag->first('prefers_rich_editor') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Subscribe When I Reply</span>
                    <select
                        class="{{ $inputClass }}"
                        name="auto_subscribe_on_post"
                        aria-describedby="profile-settings-auto-subscribe-help{{ $settingsBag->has('auto_subscribe_on_post') ? ' profile-settings-auto-subscribe-error' : '' }}"
                        @if($settingsBag->has('auto_subscribe_on_post')) aria-invalid="true" @endif
                    >
                        <option value="0" @selected(!$autoSubscribeOnPost)>Do not subscribe me automatically</option>
                        <option value="1" @selected($autoSubscribeOnPost)>Subscribe me to threads I reply to</option>
                    </select>
                </label>
                <p id="profile-settings-auto-subscribe-help" class="{{ $metaClass }}">Sets the starting choice for the "Subscribe to this thread" box on the reply form. New threads you start are subscribed unless you untick the box on the new thread form. You can change either box on each post.</p>
                @if($settingsBag->has('auto_subscribe_on_post'))
                    <p id="profile-settings-auto-subscribe-error" class="{{ $errorClass }}">{{ $settingsBag->first('auto_subscribe_on_post') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Timezone</span>
                    <select
                        class="{{ $inputClass }}"
                        name="timezone"
                        aria-describedby="profile-settings-required-help{{ $settingsBag->has('timezone') ? ' profile-settings-timezone-error' : '' }}"
                        @if($settingsBag->has('timezone')) aria-invalid="true" @endif
                    >
                        <option value="">Use server default</option>
                        @foreach(($timezoneOptions ?? []) as $timezoneValue => $timezoneLabel)
                            <option value="{{ $timezoneValue }}" @selected($currentTimezone === $timezoneValue)>{{ $timezoneLabel }}</option>
                        @endforeach
                    </select>
                </label>
                @if($settingsBag->has('timezone'))
                    <p id="profile-settings-timezone-error" class="{{ $errorClass }}">{{ $settingsBag->first('timezone') }}</p>
                @endif

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Clock Format (required)</span>
                    <select
                        class="{{ $inputClass }}"
                        name="time_format"
                        required
                        aria-required="true"
                        aria-describedby="profile-settings-required-help{{ $settingsBag->has('time_format') ? ' profile-settings-time-format-error' : '' }}"
                        @if($settingsBag->has('time_format')) aria-invalid="true" @endif
                    >
                        @foreach(\App\TimeFormat::cases() as $timeFormatOption)
                            <option value="{{ $timeFormatOption->value }}" @selected($currentTimeFormat === $timeFormatOption->value)>{{ $timeFormatOption->label() }}</option>
                        @endforeach
                    </select>
                </label>
                @if($settingsBag->has('time_format'))
                    <p id="profile-settings-time-format-error" class="{{ $errorClass }}">{{ $settingsBag->first('time_format') }}</p>
                @endif

                <div class="{{ $fieldClass }}">
                    <label for="profile-signature" class="{{ $labelClass }}">Signature (Markdown supported)</label>
                    <div
                        class="overflow-hidden border border-[#d8dce2] bg-white"
                        data-rich-editor data-rich-editor-target="profile-signature" data-rich-editor-default="{{ $richComposerDefault ?? 'rich' }}"
                    >
                        <x-markdown-toolbar target="profile-signature" :classic="true" />
                        <div class="grid md:grid-cols-[minmax(0,1fr)_136px]">
                            <textarea
                                id="profile-signature"
                                class="min-h-[180px] w-full resize-y border-0 bg-transparent px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#8f3f1f] md:border-r md:border-[#d8dce2]"
                                name="signature"
                                rows="6"
                                maxlength="500"
                                aria-describedby="profile-settings-required-help{{ $settingsBag->has('signature') ? ' profile-settings-signature-error' : '' }}"
                                @if($settingsBag->has('signature')) aria-invalid="true" @endif
                            >{{ $currentSignature }}</textarea>
                            <div class="grid grid-cols-3 content-start gap-1 border-t border-[#d8dce2] p-2 md:border-l md:border-t-0 md:border-[#d8dce2]">
                                <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="profile-signature" data-smiley="🙂" title=":)">🙂</button>
                                <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="profile-signature" data-smiley="🙁" title=":(">🙁</button>
                                <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="profile-signature" data-smiley="😉" title=";)">😉</button>
                                <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="profile-signature" data-smiley="😄" title=":D">😄</button>
                                <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="profile-signature" data-smiley="😛" title=":P">😛</button>
                                <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="profile-signature" data-smiley="😮" title=":o">😮</button>
                            </div>
                        </div>
                    </div>
                </div>
                @if($settingsBag->has('signature'))
                    <p id="profile-settings-signature-error" class="{{ $errorClass }}">{{ $settingsBag->first('signature') }}</p>
                @endif

                <button class="w-fit rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-markdown-preview data-markdown-source="profile-signature" data-markdown-preview-panel="profile-signature-preview">Preview signature</button>
                <section id="profile-signature-preview" class="rounded-surface border border-[#d8dce2] bg-[#f8f6f1] p-3" hidden>
                    <h3 class="text-sm font-semibold text-[#304458]">Signature preview</h3>
                    <div class="mt-2 text-sm text-[#304458]" data-reply-preview-content></div>
                </section>

                <button type="submit" class="{{ $primaryButtonClass }}">Save Settings</button>
            </form>

            <form method="post" action="{{ route('profile.newsletter.update') }}" class="{{ $formClass }} mt-8 border-t border-[#d8dce2] pt-6">
                @csrf
                <div>
                    <h2 class="text-lg font-semibold text-[#0f2133]">Newsletter Consent</h2>
                    <p class="mt-1 {{ $metaClass }}">Manage the Zen Cart newsletter separately from forum and plugin notification emails.</p>
                    <p class="mt-1 {{ $metaClass }}">Current status: <strong>{{ ucfirst(str_replace('_', ' ', $newsletterStatusLabel)) }}</strong></p>
                </div>

                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Newsletter Subscription (required)</span>
                    <select
                        class="{{ $inputClass }}"
                        name="newsletter_subscribed"
                        required
                        aria-required="true"
                    >
                        <option value="1" @selected($newsletterSubscribed)>Subscribe me to the Zen Cart newsletter</option>
                        <option value="0" @selected(! $newsletterSubscribed)>Do not send me the Zen Cart newsletter</option>
                    </select>
                </label>

                <label class="flex items-start gap-3 text-sm text-[#5c6570]">
                    <input
                        type="checkbox"
                        name="newsletter_consent"
                        value="1"
                        class="mt-1"
                        @checked($newsletterSubscribed)
                    >
                    <span>I agree to receive the Zen Cart newsletter by email.</span>
                </label>

                @if($errors->getBag('newsletter')->any())
                    <p class="{{ $errorClass }}">{{ $errors->getBag('newsletter')->first() }}</p>
                @endif

                <button type="submit" class="{{ $primaryButtonClass }}">Save Newsletter Consent</button>
            </form>

            <section
                class="{{ $formClass }} mt-8 border-t border-[#d8dce2] pt-6"
                data-push-settings
                data-push-text-unsupported="{{ __('profile.push_preferences.state_unsupported') }}"
                data-push-text-denied="{{ __('profile.push_preferences.state_denied') }}"
                data-push-text-off="{{ __('profile.push_preferences.state_off') }}"
                data-push-text-on="{{ __('profile.push_preferences.state_on') }}"
                aria-label="{{ __('profile.push_preferences.heading') }}"
            >
                <div>
                    <h2 class="text-lg font-semibold text-[#0f2133]">{{ __('profile.push_preferences.heading') }}</h2>
                    <p class="mt-1 {{ $metaClass }}">{{ __('profile.push_preferences.intro') }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-surface border border-[#d8dce2] bg-[#faf7f2] px-3 py-3">
                    <p class="flex items-center gap-2 text-sm text-[#0f2133]">
                        <span class="inline-block h-2 w-2 shrink-0 rounded-pill bg-[#c2b8a6]" data-push-state-dot aria-hidden="true"></span>
                        <span data-push-state role="status" aria-live="polite">{{ __('profile.push_preferences.state_off') }}</span>
                    </p>
                    <button type="button" class="{{ $primaryButtonClass }}" data-push-enable hidden>{{ __('profile.push_preferences.enable') }}</button>
                    <button type="button" class="{{ $secondaryButtonClass }}" data-push-disable hidden>{{ __('profile.push_preferences.disable') }}</button>
                </div>
                <p class="{{ $metaClass }}">Choose which alerts reach this browser in the notification preferences below (the Push column).</p>
            </section>

            @if(! empty($notificationMatrix))
                <form method="post" action="{{ route('profile.notifications.update') }}" class="{{ $formClass }} mt-8 border-t border-[#d8dce2] pt-6">
                    @csrf
                    <div>
                        <h2 class="text-lg font-semibold text-[#0f2133]">Notifications</h2>
                        <p class="mt-1 {{ $metaClass }}">Choose how you are told about forum, plugin and message activity.</p>
                    </div>
                    <div class="divide-y divide-[#e6e0d6] overflow-hidden rounded-surface border border-[#d8dce2] bg-white">
                        @foreach($notificationMatrix as $row)
                            @php($category = $row['category'])
                            <div class="grid gap-2 px-3 py-2.5 text-sm text-[#0f2133] md:grid-cols-[1fr_auto] md:items-center">
                                <span>
                                    {{ $category->label() }}
                                </span>
                                @if($row['mode'] !== null)
                                    <select class="{{ $inputClass }}" name="mode[{{ $category->id }}]" aria-label="{{ $category->label() }}">
                                        @foreach($category->modes as $modeValue)
                                            <option value="{{ $modeValue }}" @selected($row['mode'] === $modeValue)>{{ ucfirst(str_replace('_', ' ', $modeValue)) }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="flex flex-wrap gap-4">
                                        @foreach($row['channels'] as $channel => $allowed)
                                            <label class="inline-flex items-center gap-1.5">
                                                <input type="checkbox" name="channel[{{ $category->id }}][{{ $channel }}]" value="1" @checked($allowed)>
                                                <span>{{ ucfirst(str_replace('_', ' ', $channel)) }}</span>
                                            </label>
                                        @endforeach
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($errors->getBag('notifications')->any())
                        <p class="{{ $errorClass }}">{{ $errors->getBag('notifications')->first() }}</p>
                    @endif
                    <button type="submit" class="{{ $primaryButtonClass }}">Save Notification Preferences</button>
                </form>
            @endif
        </div>

        <div class="{{ $activeTab === 'avatar' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            <div>
                <img src="{{ $authUser?->avatarUrl(160) }}" alt="Current avatar" class="mb-3 h-24 w-24 rounded-pill border border-[#cabca8] object-cover">
            </div>

            <form method="post" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="{{ $formClass }}">
                @csrf
                <label class="{{ $fieldClass }}">
                    <span class="{{ $labelClass }}">Upload Avatar</span>
                    <input
                        class="{{ $inputClass }}"
                        type="file"
                        name="avatar"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        required
                        @if($avatarBag->has('avatar')) aria-invalid="true" aria-describedby="profile-avatar-error" @endif
                    >
                </label>
                @if($avatarBag->has('avatar'))
                    <p id="profile-avatar-error" class="{{ $errorClass }}">{{ $avatarBag->first('avatar') }}</p>
                @endif

                <div class="mt-2 flex items-center gap-2">
                    <button type="submit" class="{{ $primaryButtonClass }}">Upload Avatar</button>
                </div>
            </form>

            @if(($authUser?->avatar_path ?? null) !== null)
                <form method="post" action="{{ route('profile.avatar.remove') }}" class="mt-2 inline-block">
                    @csrf
                    <button type="submit" class="{{ $dangerButtonClass }}">Remove Uploaded Avatar</button>
                </form>
            @endif
        </div>

        <div class="{{ $activeTab === 'two-factor' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            @if($authUser?->hasTwoFactorEnabled())
                <div class="border border-[#cabca8] bg-[#f3f5f7] px-3 py-2 text-sm text-[#0f2133]" role="status" aria-live="polite">Two-factor authentication is enabled for your account.</div>

                @if(is_array($newRecoveryCodes) && $newRecoveryCodes !== [])
                    <div class="mt-3 border border-[#d8dce2] bg-white p-3">
                        <p class="text-sm font-semibold text-[#0f2133]">Save these recovery codes now — each one works once, and this is the only time they'll be shown in full.</p>
                        <p class="mt-1 {{ $metaClass }}">Use a recovery code to log in if you lose access to your authenticator app.</p>
                        <ul id="new-recovery-codes" class="mt-2 grid gap-1 border border-[#d8dce2] bg-white px-3 py-2 sm:grid-cols-2">
                            @foreach($newRecoveryCodes as $recoveryCode)
                                <li class="font-mono text-xs text-[#0f2133]">{{ $recoveryCode }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="{{ $primaryButtonClass }}"
                                data-copy-recovery-codes
                                data-copy-target="new-recovery-codes"
                            >
                                Copy Recovery Codes
                            </button>
                            <span class="{{ $metaClass }}" data-copy-recovery-codes-status aria-live="polite"></span>
                        </div>
                    </div>
                @else
                    <p class="mt-2 {{ $metaClass }}">
                        {{ $recoveryCodesRemainingCount }} recovery {{ \Illuminate\Support\Str::plural('code', $recoveryCodesRemainingCount) }} remaining.
                    </p>
                @endif

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <form method="post" action="{{ route('profile.2fa.recovery-codes.regenerate') }}" class="inline-block">
                        @csrf
                        <button type="submit" class="{{ $primaryButtonClass }}">Regenerate Recovery Codes</button>
                    </form>
                    <form method="post" action="{{ route('profile.2fa.disable') }}" class="inline-block">
                        @csrf
                        <button type="submit" class="{{ $dangerButtonClass }}">Disable 2FA</button>
                    </form>
                </div>
            @else
                @if(!$twoFactorSetup)
                    <p class="{{ $metaClass }}">Protect your account with a one-time code from an authenticator app.</p>
                    <form method="post" action="{{ route('profile.2fa.setup') }}" class="inline-block">
                        @csrf
                        <button type="submit" class="{{ $primaryButtonClass }}">Allow 2FA</button>
                    </form>
                @else
                    <div class="mb-3 grid items-center gap-4 md:grid-cols-[auto_1fr]">
                        <div class="[&_svg]:block [&_svg]:h-[180px] [&_svg]:w-[180px] [&_svg]:border [&_svg]:border-[#cabca8] [&_svg]:bg-white [&_svg]:p-2">{!! $twoFactorSetup['svg'] !!}</div>
                        <div>
                            <strong>Scan QR Code</strong>
                            <p class="mt-1 {{ $metaClass }}">Use Google Authenticator, 1Password, or another TOTP app.</p>
                            <strong>Manual secret</strong>
                            <code class="mt-1 inline-block">{{ $twoFactorSetup['secret'] }}</code>
                        </div>
                    </div>

                    <form method="post" action="{{ route('profile.2fa.confirm') }}" class="{{ $formClass }}">
                        @csrf
                        <label class="{{ $fieldClass }}">
                            <span class="{{ $labelClass }}">Enter 6-digit code from your authenticator app</span>
                            <input
                                class="{{ $inputClass }}"
                                type="text"
                                name="code"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                required
                                @if($twoFactorBag->has('code')) aria-invalid="true" aria-describedby="profile-two-factor-code-error" @endif
                            >
                        </label>
                        @if($twoFactorBag->has('code'))
                            <p id="profile-two-factor-code-error" class="{{ $errorClass }}">{{ $twoFactorBag->first('code') }}</p>
                        @endif

                        <button type="submit" class="{{ $primaryButtonClass }}">Enable 2FA</button>
                    </form>
                @endif
            @endif
        </div>

        <div class="{{ $activeTab === 'api-keys' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            <div class="grid gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-[#0f2133]">Personal API Keys</h2>
                    <p class="mt-1 {{ $metaClass }}">Create bearer tokens for API clients. New keys are shown only once after creation.</p>
                </div>

                @if(is_string($newApiToken) && $newApiToken !== '')
                    <div class="border border-[#d8dce2] bg-white p-3">
                        <p class="text-sm font-semibold text-[#0f2133]">Copy your new API key now.</p>
                        <code id="new-api-token" class="mt-2 block overflow-x-auto border border-[#d8dce2] bg-white px-3 py-2 text-xs text-[#0f2133]">{{ $newApiToken }}</code>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="{{ $primaryButtonClass }}"
                                data-copy-api-token
                                data-copy-target="new-api-token"
                            >
                                Copy API Key
                            </button>
                            <span class="{{ $metaClass }}" data-copy-api-token-status aria-live="polite"></span>
                        </div>
                    </div>
                @endif

                <form method="post" action="{{ route('profile.api-tokens.store') }}" class="{{ $formClass }}">
                    @csrf
                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Key Name</span>
                        <input
                            class="{{ $inputClass }}"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            maxlength="255"
                            required
                            @if($apiTokenBag->has('name')) aria-invalid="true" aria-describedby="profile-api-token-name-error" @endif
                        >
                    </label>
                    @if($apiTokenBag->has('name'))
                        <p id="profile-api-token-name-error" class="{{ $errorClass }}">{{ $apiTokenBag->first('name') }}</p>
                    @endif

                    <fieldset class="{{ $fieldClass }}">
                        <legend class="{{ $labelClass }}">Scopes</legend>
                        @if(empty($availableApiScopes))
                            <p class="{{ $metaClass }}">No API scopes are currently available to your account.</p>
                        @else
                            <div class="mt-2 grid gap-2.5">
                                @foreach($availableApiScopes as $scope)
                                    <label class="flex items-start gap-3 border border-[#d8dce2] bg-white p-3">
                                        <input
                                            type="checkbox"
                                            name="abilities[]"
                                            value="{{ $scope['ability'] }}"
                                            class="mt-1"
                                            @checked(in_array($scope['ability'], old('abilities', []), true))
                                        >
                                        <span>
                                            <span class="block text-sm font-semibold text-[#0f2133]">{{ $scope['label'] }}</span>
                                            <span class="mt-1 block {{ $metaClass }}">{{ $scope['description'] }}</span>
                                            <span class="mt-1 block {{ $metaClass }}">Requires entitlement: {{ $scope['entitlement'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>
                    @if($apiTokenBag->has('abilities') || $apiTokenBag->has('abilities.0'))
                        <p class="{{ $errorClass }}">{{ $apiTokenBag->first('abilities') ?: $apiTokenBag->first('abilities.0') }}</p>
                    @endif

                    <button type="submit" class="{{ $primaryButtonClass }}" @disabled(empty($availableApiScopes))>Create API Key</button>
                </form>

                <div>
                    <h3 class="text-base font-semibold text-[#0f2133]">Existing Keys</h3>
                    @if($apiTokens->isEmpty())
                        <p class="mt-2 {{ $metaClass }}">No API keys created yet.</p>
                    @else
                        <div class="mt-2 grid gap-2.5">
                            @foreach($apiTokens as $apiToken)
                                <article class="flex flex-col items-start justify-between gap-3 border border-[#d8dce2] bg-white p-3 md:flex-row">
                                    <div>
                                        <h4 class="m-0 text-base font-semibold text-[#0f2133]">{{ $apiToken->name }}</h4>
                                        <p class="mt-1 {{ $metaClass }}">Created: {{ $apiToken->created_at ? \App\Support\LocalTime::format($apiToken->created_at) : '-' }}</p>
                                        <p class="mt-1 {{ $metaClass }}">Last used: {{ $apiToken->last_used_at ? \App\Support\LocalTime::format($apiToken->last_used_at) : 'Never' }}</p>
                                        <p class="mt-1 {{ $metaClass }}">Access: {{ implode(', ', $apiTokenAbilityLabels[$apiToken->id] ?? []) }}</p>
                                    </div>
                                    <form method="post" action="{{ route('profile.api-tokens.revoke', $apiToken) }}" class="md:self-center">
                                        @csrf
                                        <button type="submit" class="{{ $dangerButtonClass }}">Revoke</button>
                                    </form>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="{{ $activeTab === 'subscriptions' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            @if($threadSubscriptions->isEmpty() && $pluginSubscriptions->isEmpty())
                <p class="{{ $metaClass }}">You are not subscribed to any threads or plugins yet.</p>
            @else
                @if($threadSubscriptions->isNotEmpty())
                <div class="grid gap-2.5">
                    <h2 class="text-lg font-semibold text-[#0f2133]">Thread Subscriptions</h2>
                    <form id="thread-subscriptions-bulk" method="post" action="{{ route('profile.subscriptions.bulk-remove') }}" class="flex flex-wrap items-center gap-3 border border-[#d8dce2] bg-[#f8f6f1] p-3">
                        @csrf
                        <label class="flex items-center gap-2 text-sm font-semibold text-[#304458]">
                            <input type="checkbox" class="h-4 w-4 accent-[#8f3f1f]" onchange="document.querySelectorAll('input[name=&quot;thread_ids[]&quot;]').forEach((box) => box.checked = this.checked)">
                            Select all on this page
                        </label>
                        <button type="submit" name="scope" value="selected" class="{{ $secondaryButtonClass }}">Unsubscribe selected</button>
                        <button type="submit" name="scope" value="all" class="{{ $dangerButtonClass }}" onclick="return confirm('Unsubscribe from all {{ $threadSubscriptions->total() }} {{ \Illuminate\Support\Str::plural('thread', $threadSubscriptions->total()) }}? This cannot be undone.');">Unsubscribe from all {{ $threadSubscriptions->total() }} {{ \Illuminate\Support\Str::plural('thread', $threadSubscriptions->total()) }}</button>
                        @error('thread_ids')
                            <p class="{{ $errorClass }} m-0 w-full">Select at least one thread first.</p>
                        @enderror
                    </form>
                    @foreach($threadSubscriptions as $thread)
                        <article class="flex flex-col items-start justify-between gap-3 border border-[#d8dce2] bg-white p-3 md:flex-row">
                            <input type="checkbox" form="thread-subscriptions-bulk" name="thread_ids[]" value="{{ $thread->id }}" class="mt-1 h-4 w-4 shrink-0 accent-[#8f3f1f]" aria-label="Select {{ \App\Support\LegacyTextDecoder::decode($thread->title) }}">
                            <div class="flex-1">
                                <h3 class="m-0 text-base">
                                    <a href="{{ route('threads.show', $thread) }}">{{ \App\Support\LegacyTextDecoder::decode($thread->title) }}</a>
                                </h3>
                                <p class="mt-1 text-sm text-[#5c6570]">
                                    Thread date: {{ $thread->created_at ? \App\Support\LocalTime::format($thread->created_at, 'date') : 'Unknown' }}
                                </p>
                                <p class="mt-1 text-sm text-[#5c6570]">
                                    Last post: {{ ($thread->last_post_at ?? $thread->created_at) ? \App\Support\LocalTime::format($thread->last_post_at ?? $thread->created_at, 'date') : 'Unknown' }}
                                </p>
                                <p class="mt-1 text-sm text-[#5c6570]">
                                    Forum:
                                    @if($thread->forum)
                                        <a href="{{ route('forums.show', $thread->forum) }}">{{ \App\Support\LegacyTextDecoder::decode($thread->forum->name) }}</a>
                                    @else
                                        Unknown
                                    @endif
                                </p>
                            </div>
                            <form method="post" action="{{ route('profile.subscriptions.remove', $thread) }}" class="md:self-center">
                                @csrf
                                <button type="submit" class="{{ $dangerButtonClass }}">Unsubscribe</button>
                            </form>
                        </article>
                    @endforeach
                </div>
                @if($threadSubscriptions->hasPages())
                    @include('profile.partials.subscription_pagination', [
                        'paginator' => $threadSubscriptions,
                        'ariaLabel' => 'Thread subscriptions pagination',
                    ])
                @endif
                @endif

                @if($pluginSubscriptions->isNotEmpty())
                <div class="mt-6 grid gap-2.5">
                    <h2 class="text-lg font-semibold text-[#0f2133]">Plugin Subscriptions</h2>
                    @foreach($pluginSubscriptions as $plugin)
                        <article class="flex flex-col items-start justify-between gap-3 border border-[#d8dce2] bg-white p-3 md:flex-row">
                            <div>
                                <h3 class="m-0 text-base">
                                    <a href="{{ route('plugins.show', $plugin) }}">{{ $plugin->name }}</a>
                                </h3>
                                <p class="mt-1 text-sm text-[#5c6570]">
                                    Type:
                                    @if($plugin->type)
                                        <a href="{{ route('plugins.type', ['slug' => $plugin->type->slug]) }}">{{ $plugin->type->name }}</a>
                                    @else
                                        Unknown
                                    @endif
                                </p>
                            </div>
                            <form method="post" action="{{ route('profile.plugin-subscriptions.remove', $plugin) }}" class="md:self-center">
                                @csrf
                                <button type="submit" class="{{ $dangerButtonClass }}">Unsubscribe</button>
                            </form>
                        </article>
                    @endforeach
                </div>
                @if($pluginSubscriptions->hasPages())
                    @include('profile.partials.subscription_pagination', [
                        'paginator' => $pluginSubscriptions,
                        'ariaLabel' => 'Plugin subscriptions pagination',
                    ])
                @endif
                @endif
            @endif
        </div>

        <div class="{{ $activeTab === 'billing' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            @if($activeTab === 'billing')
                @include('profile.partials.billing', [
                    'plans' => $plans ?? collect(),
                    'memberships' => $memberships ?? collect(),
                    'entitlements' => $effectiveEntitlements ?? [],
                ])
            @endif
        </div>

        <div class="{{ $activeTab === 'entitlements' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            @if($activeTab === 'entitlements')
                @include('profile.partials.entitlements', [
                    'effectiveEntitlements' => $effectiveEntitlements ?? [],
                    'requestableEntitlementDefinitions' => $requestableEntitlementDefinitions ?? collect(),
                    'entitlementRequests' => $entitlementRequests,
                    'formClass' => $formClass,
                    'fieldClass' => $fieldClass,
                    'labelClass' => $labelClass,
                    'inputClass' => $inputClass,
                    'primaryButtonClass' => $primaryButtonClass,
                    'errorClass' => $errorClass,
                    'metaClass' => $metaClass,
                    'entitlementRequestBag' => $entitlementRequestBag,
                ])
            @endif
        </div>

        @if($gdprEnforcementEnabled)
        <div class="{{ $activeTab === 'privacy' ? 'block' : 'hidden' }} {{ $panelBaseClass }}">
            <div class="grid gap-4">
                @if($privacyAckRequired)
                    <div class="border border-[#bf1f2f] bg-[#fff4f4] p-3 text-sm text-[#7f121f]" role="status" aria-live="polite">
                        <p class="font-semibold">Action required: acknowledge the active privacy policy.</p>
                        <p class="mt-1">Please review the highlighted section below to continue using protected areas.</p>
                        <a href="#privacy-policy-acknowledgement" class="mt-2 inline-flex items-center rounded-surface border border-[#bf1f2f] bg-white px-3 py-1.5 text-xs font-semibold text-[#7f121f] transition hover:bg-[#ffe7eb] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bf1f2f]">
                            Jump to acknowledgement
                        </a>
                    </div>
                @endif

                <div class="border border-[#d8dce2] bg-white p-3">
                    <h2 class="text-lg font-semibold text-[#0f2133]">Data Use Summary</h2>
                    @if(is_iterable($privacyTransparencySummary ?? null) && count($privacyTransparencySummary) > 0)
                        <div class="mt-2 grid gap-2">
                            @foreach($privacyTransparencySummary as $summaryItem)
                                <article class="border border-[#d8dce2] bg-white p-3">
                                    <h3 class="text-sm font-semibold text-[#0f2133]">{{ $summaryItem['title'] ?? '' }}</h3>
                                    <p class="mt-1 text-sm text-[#5c6570]">{{ $summaryItem['description'] ?? '' }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div
                    id="privacy-policy-acknowledgement"
                    class="border p-3 {{ $privacyAckRequired ? 'border-[#bf1f2f] bg-[#fff4f4] ring-2 ring-[#f5b8c0]' : 'border-[#d8dce2] bg-white' }}"
                    @if($privacyAckRequired) tabindex="-1" @endif
                >
                    <h2 class="text-lg font-semibold text-[#0f2133]">Privacy Policy Acknowledgement</h2>
                    <p class="mt-1 {{ $metaClass }}">
                        Active version:
                        <strong>{{ $activePrivacyPolicyVersion?->version ?? 'Not set' }}</strong>
                        @if($activePrivacyPolicyVersion?->published_at)
                            (published {{ \App\Support\LocalTime::format($activePrivacyPolicyVersion->published_at, 'date') }})
                        @endif
                    </p>
                    @if($latestPrivacyPolicyAcceptance)
                        <p class="mt-1 {{ $metaClass }}">
                            Last acknowledged:
                            <strong>{{ $latestPrivacyPolicyAcceptance->policyVersion?->version ?? '-' }}</strong>
                            on {{ $latestPrivacyPolicyAcceptance->accepted_at ? \App\Support\LocalTime::format($latestPrivacyPolicyAcceptance->accepted_at) : '-' }}
                        </p>
                    @endif
                    @if(!($hasAcknowledgedActivePrivacyPolicy ?? false))
                        <form method="post" action="{{ route('profile.privacy-policy.accept') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="{{ $primaryButtonClass }}">Acknowledge Active Privacy Policy</button>
                        </form>
                    @else
                        <p class="mt-2 text-sm text-[#0f5132]">
                            You already acknowledged the active privacy policy.
                        </p>
                    @endif
                </div>

                <div>
                    <h2 class="text-lg font-semibold text-[#0f2133]">Submit a Privacy Request</h2>
                    <p class="mt-1 {{ $metaClass }}">Request access, export, correction, or erasure of your account data.</p>
                </div>

                <form method="post" action="{{ route('profile.privacy-requests.store') }}" class="{{ $formClass }}">
                    @csrf
                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Request Type</span>
                        <select
                            class="{{ $inputClass }}"
                            name="type"
                            required
                            @if($privacyRequestBag->has('type')) aria-invalid="true" aria-describedby="profile-privacy-type-error" @endif
                        >
                            @foreach(($privacyRequestTypes ?? []) as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}" @selected(old('type') === $typeKey)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($privacyRequestBag->has('type'))
                        <p id="profile-privacy-type-error" class="{{ $errorClass }}">{{ $privacyRequestBag->first('type') }}</p>
                    @endif

                    <label class="{{ $fieldClass }}">
                        <span class="{{ $labelClass }}">Details</span>
                        <textarea
                            class="{{ $inputClass }}"
                            name="request_details"
                            rows="4"
                            maxlength="5000"
                            required
                            @if($privacyRequestBag->has('request_details')) aria-invalid="true" aria-describedby="profile-privacy-details-error" @endif
                        >{{ old('request_details') }}</textarea>
                    </label>
                    @if($privacyRequestBag->has('request_details'))
                        <p id="profile-privacy-details-error" class="{{ $errorClass }}">{{ $privacyRequestBag->first('request_details') }}</p>
                    @endif

                    <button type="submit" class="{{ $primaryButtonClass }}">Submit Privacy Request</button>
                </form>

                <div>
                    <h3 class="text-base font-semibold text-[#0f2133]">Request History</h3>
                    @if(($privacyRequests ?? null) && $privacyRequests->isNotEmpty())
                        <div class="mt-2 grid gap-2.5">
                            @foreach($privacyRequests as $privacyRequest)
                                <article class="border border-[#d8dce2] bg-white p-3">
                                    <div class="flex flex-wrap items-center gap-2 text-sm">
                                        <strong>{{ ($privacyRequest->type?->label() ?? strtoupper((string) $privacyRequest->type)) }}</strong>
                                        <span class="rounded-pill border border-[#cabca8] px-2 py-0.5 text-xs">
                                            {{ ($privacyRequest->status?->label() ?? (string) $privacyRequest->status) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 {{ $metaClass }}">
                                        Submitted: {{ $privacyRequest->requested_at ? \App\Support\LocalTime::format($privacyRequest->requested_at) : '-' }}
                                    </p>
                                    @if(is_string($privacyRequest->request_details) && trim($privacyRequest->request_details) !== '')
                                        <p class="mt-2 text-sm text-[#0f2133] whitespace-pre-wrap">{{ $privacyRequest->request_details }}</p>
                                    @endif
                                    @foreach($privacyRequest->replies as $reply)
                                        <div class="mt-2 border-l-2 border-[#cabca8] pl-3">
                                            <p class="{{ $metaClass }}">Reply from staff: {{ \App\Support\LocalTime::format($reply->created_at) }}</p>
                                            <p class="mt-1 text-sm text-[#0f2133] whitespace-pre-wrap">{{ (string) ($reply->meta['message'] ?? '') }}</p>
                                        </div>
                                    @endforeach
                                    @if(isset($privacyExportDownloadUrls[$privacyRequest->id]))
                                        <a href="{{ $privacyExportDownloadUrls[$privacyRequest->id] }}" class="mt-2 inline-block {{ $secondaryButtonClass }}">Download export</a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                        @if($privacyRequests->hasPages())
                            @include('profile.partials.subscription_pagination', [
                                'paginator' => $privacyRequests,
                                'ariaLabel' => 'Privacy requests pagination',
                            ])
                        @endif
                    @else
                        <p class="mt-2 {{ $metaClass }}">No privacy requests submitted yet.</p>
                    @endif
                </div>
            </div>
        </div>
        @endif
        </section>
    </div>
@endsection

@if($privacyAckRequired)
    @push('scripts')
        <script>
            (() => {
                const target = document.getElementById('privacy-policy-acknowledgement');
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.focus({ preventScroll: true });
            })();
        </script>
    @endpush
@endif

@if(is_string($newApiToken) && $newApiToken !== '')
    @push('scripts')
        <script>
            (() => {
                const button = document.querySelector('[data-copy-api-token]');
                const status = document.querySelector('[data-copy-api-token-status]');

                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }

                button.addEventListener('click', async () => {
                    const targetId = button.getAttribute('data-copy-target');
                    const target = targetId ? document.getElementById(targetId) : null;
                    const text = target?.textContent?.trim() ?? '';

                    if (text === '') {
                        if (status instanceof HTMLElement) {
                            status.textContent = 'API key unavailable.';
                        }

                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(text);

                        if (status instanceof HTMLElement) {
                            status.textContent = 'API key copied.';
                        }
                    } catch (error) {
                        if (status instanceof HTMLElement) {
                            status.textContent = 'Copy failed. Copy the key manually.';
                        }
                    }
                });
            })();
        </script>
    @endpush
@endif

@if(is_array($newRecoveryCodes) && $newRecoveryCodes !== [])
    @push('scripts')
        <script>
            (() => {
                const button = document.querySelector('[data-copy-recovery-codes]');
                const status = document.querySelector('[data-copy-recovery-codes-status]');

                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }

                button.addEventListener('click', async () => {
                    const targetId = button.getAttribute('data-copy-target');
                    const target = targetId ? document.getElementById(targetId) : null;
                    const text = target
                        ? Array.from(target.querySelectorAll('li')).map((item) => item.textContent?.trim() ?? '').join('\n')
                        : '';

                    if (text === '') {
                        if (status instanceof HTMLElement) {
                            status.textContent = 'Recovery codes unavailable.';
                        }

                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(text);

                        if (status instanceof HTMLElement) {
                            status.textContent = 'Recovery codes copied.';
                        }
                    } catch (error) {
                        if (status instanceof HTMLElement) {
                            status.textContent = 'Copy failed. Copy the codes manually.';
                        }
                    }
                });
            })();
        </script>
    @endpush
@endif

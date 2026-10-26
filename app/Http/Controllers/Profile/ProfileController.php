<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Profile\ProfilePage;
use App\TimeFormat;
use Closure;
use Filagora\Notifications\Services\Preferences;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The member's own account page and the forms on its first tabs: details, password, settings,
 * notification preferences and avatar. The other tabs have controllers of their own.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfilePage $page,
        private readonly Preferences $preferences,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        return view('profile.show', $this->page->for($user, (string) $request->query('tab', 'details')));
    }

    public function updateDetails(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $validated = $request->validateWithBag('details', [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'user_title' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'interests' => ['nullable', 'string', 'max:5000'],
            'biography' => ['nullable', 'string', 'max:20000'],
        ]);

        $emailChanged = ! hash_equals(mb_strtolower((string) $user->email), mb_strtolower((string) $validated['email']));

        $user->fill($validated);

        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        if ($emailChanged) {
            $this->sendVerification($user);

            $intended = '/profile?tab=details';
            $request->session()->put('url.intended', $intended);

            return redirect()->route('verification.notice', ['intended' => $intended]);
        }

        return $this->back('details', __('profile.flash.details_updated'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], (string) $user->password)) {
            return redirect()->route('profile.show', ['tab' => 'password'])
                ->withErrors(['current_password' => __('profile.errors.current_password_incorrect')], 'password');
        }

        $user->password = $validated['password'];
        $user->save();

        return $this->back('password', __('profile.flash.password_changed'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $validated = $request->validateWithBag('settings', [
            'allow_private_messages' => ['required', 'boolean'],
            'search_icon_enabled' => ['required', 'boolean'],
            'show_archived_plugin_releases' => ['required', 'boolean'],
            'prefers_rich_editor' => ['required', 'boolean'],
            'auto_subscribe_on_post' => ['sometimes', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:64', $this->validTimezone()],
            'time_format' => ['required', Rule::enum(TimeFormat::class)],
            'signature' => ['nullable', 'string', 'max:500'],
        ]);

        $user->allow_private_messages = (bool) $validated['allow_private_messages'];
        $user->search_icon_enabled = (bool) $validated['search_icon_enabled'];
        $user->show_archived_plugin_releases = (bool) $validated['show_archived_plugin_releases'];
        $user->prefers_rich_editor = (bool) $validated['prefers_rich_editor'];

        if (array_key_exists('auto_subscribe_on_post', $validated)) {
            $user->auto_subscribe_on_post = (bool) $validated['auto_subscribe_on_post'];
        }

        $timezone = trim((string) ($validated['timezone'] ?? ''));
        $user->timezone = $timezone !== '' ? $timezone : null;
        $user->time_format = TimeFormat::from($validated['time_format']);

        $signature = (string) ($validated['signature'] ?? '');
        $user->signature = trim($signature) !== '' ? $signature : null;
        $user->signature_format = $user->signature === null ? null : 'markdown';
        $user->save();

        return $this->back('settings', __('profile.flash.settings_updated'));
    }

    /**
     * Saves the notification matrix: for each category a tick per channel it supports, or the
     * chosen mode.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $request->validateWithBag('notifications', [
            'channel' => ['nullable', 'array'],
            'channel.*' => ['nullable', 'array'],
            'mode' => ['nullable', 'array'],
            'mode.*' => ['nullable', 'string', 'max:40'],
        ]);

        // Category ids contain dots ("forum.thread_updates_instant"), so the arrays are read whole
        // rather than through dot notation.
        $channels = (array) $request->input('channel', []);
        $modes = (array) $request->input('mode', []);

        foreach ($this->preferences->matrix($user) as $row) {
            $category = $row['category'];

            if ($category->isMode()) {
                $mode = $modes[$category->id] ?? null;

                if (is_string($mode) && in_array($mode, $category->modes ?? [], true)) {
                    $this->preferences->setMode($user, $category, $mode, $category->channels[0], $user);
                }

                continue;
            }

            foreach ($category->channels as $channel) {
                $this->preferences->set($user, $category, $channel, filter_var($channels[$category->id][$channel->value] ?? false, FILTER_VALIDATE_BOOLEAN), $user);
            }
        }

        return $this->back('settings', __('profile.flash.notification_preferences_updated'));
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $validated = $request->validateWithBag('avatar', [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $validated['avatar']->storePublicly(sprintf('avatars/%d', $user->id), 'public');

        if (is_string($user->avatar_path) && $user->avatar_path !== '') {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = $path;
        $user->save();

        return $this->back('avatar', __('profile.flash.avatar_updated'));
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        if (is_string($user->avatar_path) && $user->avatar_path !== '') {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = null;
        $user->save();

        return $this->back('avatar', __('profile.flash.avatar_removed'));
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function back(string $tab, string $status): RedirectResponse
    {
        return redirect()->route('profile.show', ['tab' => $tab])->with('profile_status', $status);
    }

    private function toLogin(Request $request): RedirectResponse
    {
        return redirect()->route('home', ['auth' => 'login', 'intended' => $request->getRequestUri()]);
    }

    private function validTimezone(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            try {
                new \DateTimeZone((string) $value);
            } catch (\Throwable) {
                $fail(__('profile.errors.invalid_timezone'));
            }
        };
    }

    /**
     * The trait's own link would need a `verification.verify` route this site does not register
     * (verification runs through the panel's routes), so the notification is built the way sign-up
     * builds it.
     */
    private function sendVerification(User $user): void
    {
        $notification = app(VerifyEmail::class);
        $notification->url = Filament::getVerifyEmailUrl($user);

        $user->notifyNow($notification);
    }
}

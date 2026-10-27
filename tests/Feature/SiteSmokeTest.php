<?php

use App\Models\User;
use Database\Seeders\LocalSeeder;
use Filagora\Messages\Contracts\MessagingPolicy;
use Filagora\Notifications\Bridges\MessagesBridge;
use Filagora\Notifications\Contracts\EmailSuppression;
use Filagora\Plugins\Contracts\Compatibility;
use Filagora\Search\Support\SearchEngines;
use Filagora\ZenCart\Models\Plugin;
use Filagora\ZenCart\Plugins\ZcVersionCompatibility;
use Filagora\ZenCart\Support\EntitlementEmailSuppression;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(LocalSeeder::class);
    $this->admin = User::where('email', 'admin@example.test')->sole();
});

it('passes the Filagora doctor on a migrated install', function () {
    // The suite searches in memory; the doctor checks the engine the installed app really uses.
    config()->set('filagora-search.engine', 'database');
    app()->forgetInstance(SearchEngines::class);

    artisan('filagora:doctor')->expectsOutputToContain('All checks passed.')->assertExitCode(0);
});

it('sends guests to the sign-in page', function () {
    get('/dashboard')->assertRedirect('/dashboard/login');
    get('/dashboard/login')->assertOk();
});

it('lets the local administrator open the dashboard and the index page of every resource', function () {
    actingAs($this->admin);
    $panel = Filament::getPanel('dashboard');
    $opened = 0;

    get('/dashboard')->assertOk();

    foreach ($panel->getResources() as $resource) {
        if (! array_key_exists('index', $resource::getPages())) {
            continue;
        }

        get($resource::getUrl('index'))->assertOk();
        $opened++;
    }

    expect($opened)->toBeGreaterThan(20);
});

it('keeps the dashboard closed to someone without the staff entitlement', function () {
    $member = User::factory()->create();

    // As on the old site: a member without the entitlement is sent home, not shown a bare 403.
    actingAs($member)->get('/dashboard')->assertRedirect(route('home'));
});

it('wires the Zen Cart profile over the packages\' defaults', function () {
    expect(app(Compatibility::class))->toBeInstanceOf(ZcVersionCompatibility::class)
        ->and(app(EmailSuppression::class))->toBeInstanceOf(EntitlementEmailSuppression::class)
        ->and(app(MessagingPolicy::class))->toBeInstanceOf(MessagesBridge::class)
        ->and(config('filagora.models.plugin'))->toBe(Plugin::class);
});

it('refuses to seed anything outside a local or test environment', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new LocalSeeder)->run())->toThrow(RuntimeException::class, 'only runs in a local or test environment');
});

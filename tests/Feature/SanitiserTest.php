<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Sanitiser\Sanitiser;
use App\Sanitiser\SanitiserPolicy;
use App\Sanitiser\UnclassifiedTablesException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SanitiserTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_table_the_application_creates_is_named_by_the_policy(): void
    {
        $unclassified = array_values(array_filter(
            array_column(Schema::getTables(), 'name'),
            fn (string $table): bool => SanitiserPolicy::treatmentFor($table) === null,
        ));

        $this->assertSame([], $unclassified, 'Add these tables to App\Sanitiser\SanitiserPolicy, or a production copy would carry their data out unscrubbed.');
    }

    public function test_the_policy_names_no_table_twice(): void
    {
        $names = [...SanitiserPolicy::KEEP, ...SanitiserPolicy::PURGE, ...array_keys(SanitiserPolicy::SCRUB), 'system_settings'];

        $this->assertSame([], array_keys(array_filter(array_count_values($names), fn (int $count): bool => $count > 1)));
    }

    public function test_old_forum_tables_the_site_does_not_read_are_purged(): void
    {
        $this->assertSame('purge', SanitiserPolicy::treatmentFor('vb_pmtext'));
        $this->assertSame('purge', SanitiserPolicy::treatmentFor('vb_session'));
        $this->assertSame('scrub', SanitiserPolicy::treatmentFor('vb_user'));
        $this->assertNull(SanitiserPolicy::treatmentFor('some_new_table'));
    }

    public function test_accounts_lose_their_identity_and_secrets_but_keep_ids_and_entitlements(): void
    {
        $staff = User::factory()->create([
            'name' => 'Real Person',
            'username' => 'realperson',
            'email' => 'real.person@mail.test',
            'biography' => 'Lives somewhere.',
            'location' => 'Somewhere',
            'stripe_id' => 'cus_123',
            'legacy_password_hash' => 'abc',
            'legacy_password_salt' => 'def',
            'two_factor_secret' => 'SECRET',
            'two_factor_recovery_codes' => '["x"]',
            'two_factor_enabled_at' => now(),
            'remember_token' => 'remember',
        ]);
        $other = User::factory()->create(['email' => 'someone.else@mail.test']);
        $this->grantEntitlement($staff, 'operator.dashboard.access');

        $this->sanitiser()->run();

        $staff->refresh();
        $this->assertSame('user'.$staff->id.'@example.test', $staff->email);
        $this->assertSame('user'.$staff->id, $staff->username);
        $this->assertSame('Member '.$staff->id, $staff->name);
        $this->assertNull($staff->biography);
        $this->assertNull($staff->location);
        $this->assertNull($staff->stripe_id);
        $this->assertNull($staff->legacy_password_hash);
        $this->assertNull($staff->two_factor_secret);
        $this->assertNull($staff->two_factor_recovery_codes);
        $this->assertNull($staff->two_factor_enabled_at);
        $this->assertNull($staff->remember_token);
        $this->assertTrue(Hash::check('password', $staff->password));
        $this->assertTrue(Hash::check('password', $other->fresh()->password));
        $this->assertDatabaseHas('user_entitlement_grants', ['user_id' => $staff->id, 'entitlement_key' => 'operator.dashboard.access']);
        $this->assertSame([], $this->sanitiser()->violations());
    }

    public function test_a_real_username_that_looks_like_a_generated_one_does_not_collide(): void
    {
        $first = User::factory()->create(['username' => 'placeholder-a']);
        $second = User::factory()->create(['username' => 'user'.$first->id]);

        $this->sanitiser()->run();

        $this->assertSame('user'.$first->id, $first->fresh()->username);
        $this->assertSame('user'.$second->id, $second->fresh()->username);
    }

    public function test_the_password_is_the_one_given(): void
    {
        $user = User::factory()->create();

        (new Sanitiser(DB::connection(), 'staging-pass', useTruncate: false))->run();

        $this->assertTrue(Hash::check('staging-pass', $user->fresh()->password));
    }

    public function test_newsletter_subscribers_are_scrubbed(): void
    {
        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'Reader@Mail.test',
            'normalized_email' => 'reader@mail.test',
            'name' => 'A Reader',
            'consent_ip' => '198.51.100.7',
            'consent_user_agent' => 'Mozilla/5.0',
            'unsubscribe_reason' => 'too many',
        ]);

        $this->sanitiser()->run();

        $subscriber->refresh();
        $this->assertSame('user'.$subscriber->id.'@example.test', $subscriber->email);
        $this->assertSame('user'.$subscriber->id.'@example.test', $subscriber->normalized_email);
        $this->assertNull($subscriber->name);
        $this->assertSame('203.0.113.1', $subscriber->consent_ip);
        $this->assertNull($subscriber->consent_user_agent);
        $this->assertNull($subscriber->unsubscribe_reason);
    }

    public function test_sessions_and_tokens_are_removed(): void
    {
        DB::table('sessions')->insert(['id' => 'abc', 'ip_address' => '198.51.100.9', 'user_agent' => 'UA', 'payload' => 'x', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => 'a@mail.test', 'token' => 'tok', 'created_at' => now()]);

        $this->sanitiser()->run();

        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_only_secret_looking_settings_lose_their_value(): void
    {
        foreach (['mail.api_token' => 'tok', 'paypal.webhook_id' => 'wh', 'forum_support_email' => 'forum@mail.test', 'forum.page_size' => '25'] as $key => $value) {
            DB::table('system_settings')->insert(['key' => $key, 'value' => $value, 'value_type' => 'string', 'is_public' => false]);
        }

        $this->sanitiser()->run();

        $this->assertEmpty(DB::table('system_settings')->where('key', 'mail.api_token')->value('value'));
        $this->assertEmpty(DB::table('system_settings')->where('key', 'paypal.webhook_id')->value('value'));
        $this->assertEmpty(DB::table('system_settings')->where('key', 'forum_support_email')->value('value'));
        $this->assertSame('25', DB::table('system_settings')->where('key', 'forum.page_size')->value('value'));
    }

    public function test_violations_name_what_a_dirty_copy_still_holds(): void
    {
        User::factory()->create(['email' => 'real@mail.test']);

        $violations = $this->sanitiser()->violations();

        $this->assertContains('users.email: 1 row(s) not sanitised', $violations);
    }

    public function test_an_unnamed_table_stops_the_run(): void
    {
        Schema::create('brand_new_table', function ($table): void {
            $table->id();
        });

        try {
            $this->sanitiser()->run();
            $this->fail('The sanitiser ran with an unnamed table present.');
        } catch (UnclassifiedTablesException $exception) {
            $this->assertContains('brand_new_table', $exception->tables);
        } finally {
            Schema::dropIfExists('brand_new_table');
        }
    }

    public function test_the_command_refuses_the_database_the_application_uses(): void
    {
        $database = config('database.connections.'.config('database.default').'.database');

        $this->artisan('data:sanitise', ['database' => $database, '--yes' => true])
            ->expectsOutputToContain('Refusing to touch')
            ->assertFailed();
    }

    private function sanitiser(): Sanitiser
    {
        return new Sanitiser(DB::connection(), useTruncate: false);
    }
}

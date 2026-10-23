<?php

namespace Database\Seeders;

use App\Models\User;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\ZenCart\Seeders\OperatorEntitlementSeeder;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A staff user for a local database: the definitions of the site's `operator.*` entitlements, and one
 * administrator holding all of them through a wildcard grant. The password is public, so this
 * refuses to run anywhere that is not a local or test environment.
 */
class LocalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('LocalSeeder only runs in a local or test environment.');
        }

        $this->call(OperatorEntitlementSeeder::class);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Local Admin', 'username' => 'admin', 'password' => 'password'],
        );
        $admin->forceFill(['email_verified_at' => now()])->save();

        UserEntitlementGrant::query()->updateOrCreate(
            ['user_id' => $admin->id, 'entitlement_key' => 'operator.*', 'grant_type' => 'system'],
            ['reason' => 'Local administrator'],
        );
    }
}

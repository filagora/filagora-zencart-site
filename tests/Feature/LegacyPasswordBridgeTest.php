<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyPasswordBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifies_standard_vbulletin_double_md5_password(): void
    {
        $plainPassword = 'my-secret-password';
        $salt = 'abcd';
        $legacyHash = md5(md5($plainPassword).$salt);

        $user = User::query()->create([
            'name' => 'legacy',
            'username' => 'legacy_user',
            'email' => 'legacy@example.com',
            'password' => bcrypt('placeholder'),
            'legacy_password_hash' => $legacyHash,
            'legacy_password_salt' => $salt,
            'legacy_password_algo' => 'vb3_md5_double',
        ]);

        $this->assertTrue($user->verifyLegacyPassword($plainPassword));
    }

    public function test_verifies_legacy_hash_when_password_bytes_were_non_utf8_encoded(): void
    {
        $plainPassword = 'Café123';
        $salt = 'abcd';
        $legacyEncoded = iconv('UTF-8', 'ISO-8859-1//IGNORE', $plainPassword);
        $this->assertNotFalse($legacyEncoded);
        $legacyHash = md5(md5((string) $legacyEncoded).$salt);

        $user = User::query()->create([
            'name' => 'legacy',
            'username' => 'legacy_user_encoded',
            'email' => 'legacy-encoded@example.com',
            'password' => bcrypt('placeholder'),
            'legacy_password_hash' => $legacyHash,
            'legacy_password_salt' => $salt,
            'legacy_password_algo' => 'vb3_md5_double',
        ]);

        $this->assertTrue($user->verifyLegacyPassword($plainPassword));
    }

    public function test_verifies_legacy_hash_when_imported_salt_contains_escaped_quotes(): void
    {
        $plainPassword = 'TestBridge!42';
        $salt = 'AbC123|xYz"q{"R9[]';
        $legacyHash = md5(md5($plainPassword).$salt);

        $user = User::query()->create([
            'name' => 'legacy',
            'username' => 'legacy_user_escaped_salt',
            'email' => 'legacy-escaped-salt@example.com',
            'password' => bcrypt('placeholder'),
            'legacy_password_hash' => $legacyHash,
            'legacy_password_salt' => 'AbC123|xYz\\"q{\\"R9[]',
            'legacy_password_algo' => 'vb3_md5_double',
        ]);

        $this->assertTrue($user->verifyLegacyPassword($plainPassword));
    }
}

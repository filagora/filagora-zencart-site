<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Roles held by name, read from the `roles` and `model_has_roles` tables the live site already has.
 * The profile package's super-user check asks `hasRole()`; the Users screen assigns and removes the
 * super admin role. Nothing else about roles (permissions, guards) is used.
 */
trait HasSiteRoles
{
    /** @var array<string, bool> */
    private array $heldRoles = [];

    public function hasRole(string $name): bool
    {
        return $this->heldRoles[$name] ??= DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', static::class)
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('roles.name', $name)
            ->exists();
    }

    public function assignRole(string $name): void
    {
        $roleId = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->value('id')
            ?? DB::table('roles')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('model_has_roles')->insertOrIgnore(['role_id' => $roleId, 'model_type' => static::class, 'model_id' => $this->getKey()]);
        unset($this->heldRoles[$name]);
    }

    public function removeRole(string $name): void
    {
        DB::table('model_has_roles')
            ->where('model_type', static::class)
            ->where('model_id', $this->getKey())
            ->whereIn('role_id', DB::table('roles')->where('name', $name)->select('id'))
            ->delete();
        unset($this->heldRoles[$name]);
    }
}

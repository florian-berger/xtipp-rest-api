<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role "API-Benutzer" for the setting "allowed_users" = "role". The administrator assigns it in the user management of
 * xTIPP, which lists all roles. The name must be the same as ApiAccess::ROLE.
 */
return new class extends Migration
{
    private const ROLE = 'API-Benutzer';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only create the role if none exists (should be the case)
        $exists = DB::table('roles')
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(self::ROLE)])
            ->exists();

        if (!$exists) {
            DB::table('roles')->insert([
                'name'         => self::ROLE,
                'display_name' => self::ROLE,
                'description'  => 'Darf die REST-API verwenden',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleIds = DB::table('roles')->where('name', self::ROLE)->pluck('id');

        // First the assignments: User::hasRole() fails for an assignment whose role is missing
        DB::table('role_users')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();
    }
};

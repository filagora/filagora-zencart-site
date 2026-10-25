<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Production already has this table, so it is created only if it is missing (see the users migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legacy_import_user_maps')) {
            return;
        }

        Schema::create('legacy_import_user_maps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_user_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('legacy_username')->nullable();
            $table->string('legacy_email')->nullable();
            $table->unsignedInteger('legacy_primary_group_id')->nullable();
            $table->string('legacy_member_group_ids')->nullable();
            $table->boolean('legacy_is_banned')->default(false);
            $table->text('legacy_ban_reason')->nullable();
            $table->timestamp('legacy_banned_until')->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Tables production owns are never dropped from here.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The columns of the site's users table that the packages and the Zen Cart profile read and never
 * add, in the shape production has them. Production already has every one of them, so each is added
 * only if it is missing: this migration builds a fresh local database and does nothing on the real
 * one. Any host migration that touches a table production already has must be written the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        $add = function (string $column, callable $definition): void {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $definition($table));
            }
        };

        $add('username', fn (Blueprint $table) => $table->string('username')->nullable()->unique()->after('name'));
        $add('membership_expires_at', fn (Blueprint $table) => $table->timestamp('membership_expires_at')->nullable());
        $add('signature', fn (Blueprint $table) => $table->text('signature')->nullable());
        $add('thread_subscription_email_frequency', fn (Blueprint $table) => $table->string('thread_subscription_email_frequency', 16)->default('instant')->index());
        $add('allow_private_messages', fn (Blueprint $table) => $table->boolean('allow_private_messages')->default(true));
        $add('always_moderate_forum_content', fn (Blueprint $table) => $table->boolean('always_moderate_forum_content')->default(false));
        $add('moderate_first_forum_post', fn (Blueprint $table) => $table->boolean('moderate_first_forum_post')->default(false));
        $add('email_status', fn (Blueprint $table) => $table->string('email_status')->nullable()->index());
        $add('receives_email', fn (Blueprint $table) => $table->boolean('receives_email')->default(true));
        $add('last_seen_at', fn (Blueprint $table) => $table->timestamp('last_seen_at')->nullable()->index());
        $add('forum_read_baseline_at', fn (Blueprint $table) => $table->timestamp('forum_read_baseline_at')->nullable());
    }

    public function down(): void
    {
        // Columns production owns are never dropped from here.
    }
};

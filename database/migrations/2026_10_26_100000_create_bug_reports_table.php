<?php

use App\BugReportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Production already has this table, so it is created only if it is missing (see the users migration).
 * Dated after the packages' migrations because the table points at their threads table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bug_reports')) {
            return;
        }

        Schema::create('bug_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->foreignId('reported_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default(BugReportStatus::REPORTED->value)->index();
            $table->text('description')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'status']);
            $table->index(['assigned_to_id', 'status']);
        });
    }

    public function down(): void
    {
        // Tables production owns are never dropped from here.
    }
};

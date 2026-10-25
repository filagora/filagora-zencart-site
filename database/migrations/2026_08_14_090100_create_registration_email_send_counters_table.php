<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production already has this table; only a fresh local or test database needs it built.
        if (Schema::hasTable('registration_email_send_counters')) {
            return;
        }

        Schema::create('registration_email_send_counters', function (Blueprint $table): void {
            $table->id();
            $table->date('send_date')->unique();
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // A table production owns is never dropped from here.
    }
};

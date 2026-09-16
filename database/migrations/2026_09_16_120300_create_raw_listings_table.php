<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('raw_listings', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->text('external_url');
            $table->string('title');
            $table->jsonb('payload');
            $table->char('content_hash', 64)->unique();
            $table->foreignId('contest_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_listings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->jsonb('states')->default('[]')->after('locations');
            $table->boolean('nationwide')->default(false)->after('states');
        });

        // GIN em `states` — só faz sentido/existe em Postgres.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX contests_states_gin_index ON contests USING GIN (states)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->dropColumn(['states', 'nationwide']);
        });
    }
};

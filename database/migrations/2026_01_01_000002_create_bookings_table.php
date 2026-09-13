<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        }

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status');
            $table->timestamps();

            $table->index(['service_id', 'status', 'starts_at']);
        });

        // PostgreSQL-only safety net: two confirmed bookings for the same
        // service cannot have intersecting half-open ranges [starts_at, ends_at).
        // SQLite has no EXCLUDE / GiST support, so tests rely on lockForUpdate
        // plus the application overlap check.
        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
                EXCLUDE USING gist (
                    service_id WITH =,
                    tstzrange(starts_at, ends_at, '[)') WITH &&
                ) WHERE (status = 'confirmed')
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

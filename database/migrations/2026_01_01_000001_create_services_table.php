<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('duration_minutes');
            $table->time('daily_start_time');
            $table->time('daily_end_time');
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_minutes_positive CHECK (duration_minutes > 0)');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_daily_hours_ordered CHECK (daily_end_time > daily_start_time)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Service::query()->firstOrCreate(
            ['name' => 'Haircut'],
            [
                'duration_minutes' => 30,
                'daily_start_time' => '09:00:00',
                'daily_end_time' => '17:00:00',
            ],
        );

        Service::query()->firstOrCreate(
            ['name' => 'Consultation'],
            [
                'duration_minutes' => 60,
                'daily_start_time' => '10:00:00',
                'daily_end_time' => '16:00:00',
            ],
        );
    }
}

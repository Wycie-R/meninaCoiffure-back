<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Los datos de volumen para el benchmark se cargan aparte: db:seed --class=VolumenReservasSeeder
     */
    public function run(): void
    {
        $this->call([
            SprintUnoDosSeeder::class,
            ReservasDemoSeeder::class,
        ]);
    }
}

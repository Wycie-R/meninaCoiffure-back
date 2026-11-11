<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo de desarrollo: los datos reales del salón (ProduccionSeeder) más un equipo, clientas,
     * reservas y pedidos de ejemplo. Los datos de volumen para el benchmark se cargan aparte:
     * db:seed --class=VolumenReservasSeeder
     */
    public function run(): void
    {
        // Carga cuentas con password123 y clientas ficticias: en producción se usa ProduccionSeeder
        if (app()->environment('production')) {
            throw new RuntimeException('DatabaseSeeder es solo para desarrollo. En producción usa: php artisan db:seed --class=ProduccionSeeder --force');
        }

        $this->call([
            ProduccionSeeder::class,
            EquipoDemoSeeder::class,
            ReservasDemoSeeder::class,
            ClientaDemoSeeder::class,
        ]);
    }
}

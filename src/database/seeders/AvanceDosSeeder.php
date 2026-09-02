<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AvanceDosSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clientes
        $cliente1Id = DB::table('clientes')->insertGetId([
            'nombre' => 'Ana',
            'apellido' => 'Gómez',
            'telefono' => '0981111222',
            'email' => 'ana.gomez@example.com',
            'fecha_registro' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cliente2Id = DB::table('clientes')->insertGetId([
            'nombre' => 'Beatriz',
            'apellido' => 'López',
            'telefono' => '0982333444',
            'email' => 'beatriz.lopez@example.com',
            'fecha_registro' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Personal
        $personal1Id = DB::table('personal')->insertGetId([
            'nombre' => 'Carla',
            'apellido' => 'Estilista',
            'telefono' => '0971555666',
            'email' => 'carla@meninacoiffure.com',
            'fecha_ingreso' => '2025-01-15',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $personal2Id = DB::table('personal')->insertGetId([
            'nombre' => 'Diana',
            'apellido' => 'Colorista',
            'telefono' => '0972777888',
            'email' => 'diana@meninacoiffure.com',
            'fecha_ingreso' => '2025-03-01',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Turnos del personal (1=Lunes a 5=Viernes, de 08:00 a 18:00)
        foreach (range(1, 5) as $dia) {
            DB::table('turnos_personal')->insert([
                [
                    'personal_id' => $personal1Id,
                    'dia_semana' => $dia,
                    'hora_inicio' => '08:00:00',
                    'hora_fin' => '18:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'personal_id' => $personal2Id,
                    'dia_semana' => $dia,
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '17:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // 4. Categorías
        $catCorteId = DB::table('categorias_servicio')->insertGetId([
            'nombre' => 'Corte y Peinado',
            'descripcion' => 'Servicios de estilismo capilar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catColorId = DB::table('categorias_servicio')->insertGetId([
            'nombre' => 'Coloración',
            'descripcion' => 'Tintes, mechas y tratamientos de color',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Servicios (3 activos y 1 inactivo para probar Regla 3)
        $srvCorteId = DB::table('servicios')->insertGetId([
            'categoria_id' => $catCorteId,
            'nombre' => 'Corte Femenino',
            'duracion_minutos' => 45,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $srvNutricionId = DB::table('servicios')->insertGetId([
            'categoria_id' => $catCorteId,
            'nombre' => 'Baño de Crema / Nutrición',
            'duracion_minutos' => 30,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $srvColorId = DB::table('servicios')->insertGetId([
            'categoria_id' => $catColorId,
            'nombre' => 'Coloración Completa',
            'duracion_minutos' => 90,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $srvInactivoId = DB::table('servicios')->insertGetId([
            'categoria_id' => $catColorId,
            'nombre' => 'Decoloración Extrema (Descontinuado)',
            'duracion_minutos' => 120,
            'activo' => false, // Inactivo a propósito
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. Precios vigentes (para probar Regla 4: precio congelado)
        DB::table('precios_servicio')->insert([
            [
                'servicio_id' => $srvCorteId,
                'precio' => 70000.00,
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'servicio_id' => $srvNutricionId,
                'precio' => 50000.00,
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'servicio_id' => $srvColorId,
                'precio' => 180000.00,
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 7. Descuentos opcionales
        DB::table('descuentos')->insert([
            'nombre' => 'Promo Primavera',
            'tipo' => 'porcentaje',
            'valor' => 10.00,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
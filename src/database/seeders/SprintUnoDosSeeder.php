<?php

namespace Database\Seeders;

use App\Models\Personal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SprintUnoDosSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles del sistema
        $rolAdmin = Role::firstOrCreate(['nombre' => 'admin']);
        $rolRecepcion = Role::firstOrCreate(['nombre' => 'recepcionista']);
        $rolProfesional = Role::firstOrCreate(['nombre' => 'profesional']);
        $rolCliente = Role::firstOrCreate(['nombre' => 'cliente']);

        // 2. Asegurar que existan datos de AvanceDosSeeder si aún no se poblaron
        if (\Illuminate\Support\Facades\DB::table('categorias_servicio')->count() === 0) {
            $this->call(AvanceDosSeeder::class);
        }

        // 3. Obtener profesional Carla (si existe)
        $personalCarla = Personal::where('email', 'carla@meninacoiffure.com')->first();

        // 4. Usuarios de prueba para HU-25
        User::updateOrCreate(
            ['email' => 'admin@meninacoiffure.com'],
            [
                'name' => 'Administradora General',
                'password' => Hash::make('password123'),
                'rol_id' => $rolAdmin->id,
            ]
        );

        User::updateOrCreate(
            ['email' => 'recepcion@meninacoiffure.com'],
            [
                'name' => 'Recepcionista Turnos',
                'password' => Hash::make('password123'),
                'rol_id' => $rolRecepcion->id,
            ]
        );

        User::updateOrCreate(
            ['email' => 'carla@meninacoiffure.com'],
            [
                'name' => 'Carla Estilista',
                'password' => Hash::make('password123'),
                'rol_id' => $rolProfesional->id,
                'personal_id' => $personalCarla ? $personalCarla->id : null,
            ]
        );
    }
}

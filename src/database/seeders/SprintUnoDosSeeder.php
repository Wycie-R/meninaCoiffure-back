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
        // 1. Roles del sistema. El salón no tiene recepcionista: la propietaria (admin) agenda las citas.
        // El rol cliente queda reservado: la clientela todavía no usa el sistema.
        $rolAdmin = Role::firstOrCreate(['nombre' => 'admin']);
        $rolProfesional = Role::firstOrCreate(['nombre' => 'profesional']);
        Role::firstOrCreate(['nombre' => 'cliente']);

        // Bases creadas antes de alinear los datos con el salón real traen cuentas que no corresponden
        User::whereIn('email', ['recepcion@meninacoiffure.com', 'diana@meninacoiffure.com'])->delete();
        Role::where('nombre', 'recepcionista')->delete();

        // 2. Datos base del salón (personal, catálogo, insumos); el seeder es idempotente
        $this->call(AvanceDosSeeder::class);

        // 3. Usuarios de prueba para HU-25. La propietaria administra y además atiende,
        // así que su cuenta admin está vinculada a su ficha de personal
        $cuentas = [
            ['admin@meninacoiffure.com', 'Mónica (propietaria)', $rolAdmin, 'monica@meninacoiffure.com'],
            ['carla@meninacoiffure.com', 'Carla Estilista', $rolProfesional, 'carla@meninacoiffure.com'],
        ];

        foreach ($cuentas as [$email, $nombre, $rol, $emailPersonal]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $nombre,
                    'password' => Hash::make('password123'),
                    'rol_id' => $rol->id,
                    'personal_id' => Personal::where('email', $emailPersonal)->value('id'),
                ]
            );
        }
    }
}

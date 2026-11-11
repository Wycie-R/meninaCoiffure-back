<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use App\Models\Personal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Equipo de la demo de desarrollo: la propietaria (admin, que también atiende) y una estilista,
 * con sus fichas de personal, especialidades y cuentas con la contraseña password123.
 * Usa el catálogo de ProduccionSeeder. En producción el equipo se carga desde el panel.
 */
class EquipoDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Cuentas con password123: nunca en producción
        if (app()->environment('production')) {
            throw new RuntimeException('EquipoDemoSeeder es solo para desarrollo.');
        }

        // [clave, nombre, apellido, correo de la ficha, teléfono, ingreso, especialidades]
        $equipo = [
            ['monica', 'Mónica', 'Propietaria', 'monica@meninacoiffure.com', '0984000001', '2018-03-01', ['Capilar', 'Maquillaje', 'Uñas']],
            ['carla', 'Carla', 'Estilista', 'carla@meninacoiffure.com', '0971555666', '2025-01-15', ['Peinados', 'Maquillaje', 'Uñas']],
        ];

        $fichas = [];
        foreach ($equipo as [$clave, $nombre, $apellido, $email, $telefono, $ingreso, $nombresEspecialidad]) {
            $ficha = Personal::firstOrCreate(['email' => $email], [
                'nombre' => $nombre, 'apellido' => $apellido, 'telefono' => $telefono,
                'fecha_ingreso' => $ingreso, 'activo' => true,
            ]);

            $idsEspecialidad = Especialidad::whereIn('nombre', $nombresEspecialidad)->pluck('id')->all();
            $ficha->especialidades()->syncWithoutDetaching($idsEspecialidad);

            $fichas[$clave] = $ficha;
        }

        // La propietaria administra y además atiende: su cuenta admin queda vinculada a su ficha
        $cuentas = [
            ['admin@meninacoiffure.com', 'Mónica (propietaria)', 'admin', 'monica'],
            ['carla@meninacoiffure.com', 'Carla Estilista', 'profesional', 'carla'],
        ];

        foreach ($cuentas as [$email, $nombre, $rol, $claveFicha]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $nombre,
                'password' => Hash::make('password123'),
                'rol_id' => Role::firstOrCreate(['nombre' => $rol])->id,
                'personal_id' => $fichas[$claveFicha]->id,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\SolicitudCita;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Clienta con cuenta para la demo de "Mis citas": su ficha vinculada y dos pedidos pendientes
 * (de dos servicios cada uno) para la bandeja de la dueña. Es la misma persona que existe en el
 * realm de Keycloak (clienta@meninacoiffure.com); con login local entra con password123.
 * Es idempotente: se puede correr sobre una base ya cargada.
 */
class ClientaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $rolCliente = Role::firstOrCreate(['nombre' => 'cliente']);

        $cuenta = User::updateOrCreate(
            ['email' => 'clienta@meninacoiffure.com'],
            [
                'name' => 'Clienta Demo',
                'password' => Hash::make('password123'),
                'rol_id' => $rolCliente->id,
            ]
        );

        $ficha = Cliente::firstOrCreate(
            ['email' => 'clienta@meninacoiffure.com'],
            [
                'nombre' => 'Clienta',
                'apellido' => 'Demo',
                'telefono' => '0981000111',
                'fecha_registro' => now()->toDateString(),
            ]
        );
        $ficha->user_id = $cuenta->id;
        // Dirección y ubicación guardadas: su próximo pedido a domicilio se completa solo
        $ficha->direccion_domicilio = 'Calle Las Residentas 456, Villa Elisa (portón verde)';
        $ficha->latitud = -25.367600;
        $ficha->longitud = -57.592700;
        $ficha->save();

        // Si ya tiene pedidos, la demo ya estaba cargada
        if ($ficha->solicitudes()->exists()) {
            return;
        }

        $corte = Servicio::where('nombre', 'Corte de dama')->where('activo', true)->first();
        $nutricion = Servicio::where('nombre', 'Nutrición e hidratación capilar')->where('activo', true)->first();
        $peinado = Servicio::where('nombre', 'Peinado para eventos')->where('activo', true)->first();
        $maquillaje = Servicio::where('nombre', 'Maquillaje social')->where('activo', true)->first();
        if (!$corte || !$nutricion || !$peinado || !$maquillaje) {
            $this->command?->warn('ClientaDemoSeeder necesita el catálogo de AvanceDosSeeder.');
            return;
        }

        $pedidoDeCorte = SolicitudCita::create([
            'cliente_id' => $ficha->id,
            'fecha_preferida' => Carbon::parse('next tuesday')->toDateString(),
            'hora_preferida' => '09:00',
            'franja' => 'manana',
            'a_domicilio' => false,
            'comentario' => 'Quisiera emparejar las puntas, nada muy corto.',
            'estado' => 'pendiente',
        ]);
        $pedidoDeCorte->servicios()->attach([$corte->id, $nutricion->id]);

        $pedidoDeEvento = SolicitudCita::create([
            'cliente_id' => $ficha->id,
            // El salón no abre los sábados: lo pide fuera de horario, a confirmar por la dueña
            'fecha_preferida' => Carbon::parse('next saturday')->toDateString(),
            'hora_preferida' => '15:00',
            'franja' => 'tarde',
            'fuera_de_horario' => true,
            'a_domicilio' => true,
            'direccion_domicilio' => 'Calle Las Residentas 456, Villa Elisa (portón verde)',
            'comentario' => 'Es para un casamiento a las 19 h.',
            'estado' => 'pendiente',
        ]);
        $pedidoDeEvento->servicios()->attach([$peinado->id, $maquillaje->id]);
    }
}

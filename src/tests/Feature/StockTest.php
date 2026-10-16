<?php

namespace Tests\Feature;

use App\Models\ConsumoRecurso;
use App\Models\DetalleReserva;
use App\Models\MovimientoStock;
use App\Models\Servicio;
use App\Models\StockRecurso;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SprintUnoDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU-15 a HU-18: inventario de insumos, movimientos, consumo estándar por servicio
 * y consumo real por atención con descuento al completar.
 */
class StockTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $carla;
    protected string $lunes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SprintUnoDosSeeder::class);

        $this->admin = User::where('email', 'admin@meninacoiffure.com')->first();
        $this->carla = User::where('email', 'carla@meninacoiffure.com')->first();
        $this->lunes = Carbon::parse('next monday')->toDateString();

        $this->actingAs($this->admin);
    }

    protected function recurso(string $nombre): StockRecurso
    {
        return StockRecurso::where('nombre', $nombre)->firstOrFail();
    }

    protected function servicio(string $nombre): Servicio
    {
        return Servicio::where('nombre', $nombre)->firstOrFail();
    }

    protected function existencia(string $nombre): float
    {
        return (float) $this->recurso($nombre)->fresh()->cantidad_actual;
    }

    /** Crea la reserva por la API para que se disparen los eventos de modelo (precarga HU-18). */
    protected function reservar(array $servicios, int $personalId = 1, string $inicio = '10:00', string $fin = '11:30'): int
    {
        return $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => $personalId,
            'fecha' => $this->lunes,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'servicios' => $servicios,
        ])->assertStatus(201)->json('data.id');
    }

    protected function completar(int $reservaId): void
    {
        $this->travelTo(Carbon::parse("{$this->lunes} 12:00"));

        $this->putJson("/api/reservas/{$reservaId}", ['estado' => 'completada'])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'completada');
    }

    protected function lineaDe(int $reservaId, string $servicio): DetalleReserva
    {
        return DetalleReserva::where('reserva_id', $reservaId)
            ->where('servicio_id', $this->servicio($servicio)->id)
            ->firstOrFail();
    }

    // ---------------------------------------------------------------- HU-15: recursos

    public function test_listado_de_recursos_con_busqueda_y_filtro_bajo_minimo(): void
    {
        $listado = $this->getJson('/api/recursos')->assertStatus(200);
        $this->assertCount(6, $listado->json('data'));
        $this->assertSame('Cera depilatoria', $listado->json('data.0.nombre')); // orden por nombre
        $this->assertFalse($listado->json('data.0.bajo_minimo'));

        // Nadie está bajo el mínimo con la existencia sembrada
        $this->getJson('/api/recursos?bajo_minimo=1')->assertStatus(200)->assertJsonCount(0, 'data');

        // El tinte baja de 1200 a 250 ml (mínimo 300): entra en la alerta de reposición
        $this->postJson('/api/movimientos-stock', [
            'recurso_id' => $this->recurso('Tinte en crema')->id, 'tipo' => 'salida', 'cantidad' => 950, 'motivo' => 'Vencimiento',
        ])->assertStatus(201);

        $this->getJson('/api/recursos?bajo_minimo=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Tinte en crema')
            ->assertJsonPath('data.0.bajo_minimo', true);

        $this->getJson('/api/recursos?buscar=OXID')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Oxidante 20 vol.');

        // Cualquier rol consulta los insumos
        $this->actingAs($this->carla)->getJson('/api/recursos')->assertStatus(200)->assertJsonCount(6, 'data');
    }

    public function test_alta_de_recurso_registra_la_existencia_inicial_como_movimiento(): void
    {
        $respuesta = $this->postJson('/api/recursos', [
            'nombre' => 'Laca fijadora', 'unidad_medida' => 'ml', 'cantidad_actual' => 500, 'stock_minimo' => 100,
        ])->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Laca fijadora')
            ->assertJsonPath('data.bajo_minimo', false);

        $id = $respuesta->json('data.id');
        $this->assertSame(500.0, (float) $respuesta->json('data.cantidad_actual'));
        $this->assertDatabaseHas('movimientos_stock', [
            'recurso_id' => $id, 'tipo' => 'entrada', 'cantidad' => 500, 'motivo' => 'Existencia inicial',
        ]);

        // Sin cantidad inicial arranca en 0, sin movimientos, y queda bajo el mínimo
        $sinExistencia = $this->postJson('/api/recursos', ['nombre' => 'Guantes de nitrilo', 'unidad_medida' => 'u', 'stock_minimo' => 20])
            ->assertStatus(201)
            ->assertJsonPath('data.bajo_minimo', true);
        $this->assertSame(0.0, (float) $sinExistencia->json('data.cantidad_actual'));
        $this->assertSame(0, MovimientoStock::where('recurso_id', $sinExistencia->json('data.id'))->count());

        $this->getJson("/api/recursos/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('data.nombre', 'Laca fijadora')
            ->assertJsonCount(1, 'data.movimientos');

        $this->postJson('/api/recursos', ['unidad_medida' => 'ml', 'cantidad_actual' => -5, 'stock_minimo' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'cantidad_actual', 'stock_minimo']);
    }

    public function test_edicion_de_recurso_no_permite_cambiar_la_existencia(): void
    {
        $tinte = $this->recurso('Tinte en crema');

        $this->putJson("/api/recursos/{$tinte->id}", ['cantidad_actual' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cantidad_actual']);
        $this->assertSame(1200.0, $this->existencia('Tinte en crema'));

        // Subir el mínimo por encima de la existencia activa la alerta
        $this->putJson("/api/recursos/{$tinte->id}", [
            'nombre' => 'Tinte en crema 6.0', 'descripcion' => 'Tubo de 60 ml', 'unidad_medida' => 'ml', 'stock_minimo' => 1500,
        ])->assertStatus(200)
            ->assertJsonPath('data.nombre', 'Tinte en crema 6.0')
            ->assertJsonPath('data.bajo_minimo', true);

        $this->assertSame(1200.0, (float) $tinte->fresh()->cantidad_actual);
    }

    public function test_eliminacion_de_recurso_bloqueada_si_se_uso_en_atenciones(): void
    {
        // Un insumo nuevo con movimientos y plantilla se puede borrar: todo cae en cascada
        $laca = $this->postJson('/api/recursos', ['nombre' => 'Laca', 'cantidad_actual' => 10, 'stock_minimo' => 1])->json('data.id');
        $peinado = $this->servicio('Peinado para eventos');
        $this->putJson("/api/servicios/{$peinado->id}/recursos", ['recursos' => [['recurso_id' => $laca, 'cantidad_utilizada' => 5]]])
            ->assertStatus(200);

        $this->deleteJson("/api/recursos/{$laca}")->assertStatus(200);
        $this->assertDatabaseMissing('stock_recursos', ['id' => $laca]);
        $this->assertDatabaseMissing('movimientos_stock', ['recurso_id' => $laca]);
        $this->assertDatabaseMissing('servicio_recurso', ['recurso_id' => $laca]);

        // El tinte ya figura en el consumo de una atención
        $this->reservar([$this->servicio('Coloración completa')->id]);
        $tinte = $this->recurso('Tinte en crema');

        $this->deleteJson("/api/recursos/{$tinte->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recurso']);
        $this->assertDatabaseHas('stock_recursos', ['id' => $tinte->id]);
    }

    // ---------------------------------------------------------------- HU-16: movimientos

    public function test_entradas_y_salidas_actualizan_la_existencia(): void
    {
        $tinte = $this->recurso('Tinte en crema');

        $this->postJson('/api/movimientos-stock', [
            'recurso_id' => $tinte->id, 'tipo' => 'entrada', 'cantidad' => 300, 'motivo' => 'Compra a proveedor',
        ])->assertStatus(201)
            ->assertJsonPath('data.tipo', 'entrada')
            ->assertJsonPath('data.recurso.id', $tinte->id)
            ->assertJsonPath('data.recurso.bajo_minimo', false);
        $this->assertSame(1500.0, $this->existencia('Tinte en crema'));

        $salida = $this->postJson('/api/movimientos-stock', [
            'recurso_id' => $tinte->id, 'tipo' => 'salida', 'cantidad' => 200.5, 'motivo' => 'Tubo dañado',
            'fecha' => now()->subDay()->toDateString(),
        ])->assertStatus(201);
        $this->assertSame(1299.5, (float) $salida->json('data.recurso.cantidad_actual'));
        $this->assertSame(1299.5, $this->existencia('Tinte en crema'));

        // Una salida mayor a la existencia se rechaza y no toca el stock
        $rechazo = $this->postJson('/api/movimientos-stock', [
            'recurso_id' => $tinte->id, 'tipo' => 'salida', 'cantidad' => 5000,
        ])->assertStatus(422)->assertJsonValidationErrors(['cantidad']);
        $this->assertStringContainsString('quedan 1.299,5 ml', $rechazo->json('errors.cantidad.0'));
        $this->assertSame(1299.5, $this->existencia('Tinte en crema'));

        $this->postJson('/api/movimientos-stock', [
            'recurso_id' => $tinte->id, 'tipo' => 'ajuste', 'cantidad' => 0, 'fecha' => now()->addDays(2)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['tipo', 'cantidad', 'fecha']);

        $this->postJson('/api/movimientos-stock', ['recurso_id' => 9999, 'tipo' => 'entrada', 'cantidad' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['recurso_id']);

        // Historial filtrado y ordenado del más reciente al más antiguo
        $historial = $this->getJson("/api/movimientos-stock?recurso_id={$tinte->id}&per_page=10")->assertStatus(200);
        $this->assertSame(2, $historial->json('total'));
        $this->assertSame('entrada', $historial->json('data.0.tipo')); // la salida se fechó ayer
        $this->assertSame('Tinte en crema', $historial->json('data.0.recurso.nombre'));

        $this->getJson("/api/movimientos-stock?recurso_id={$tinte->id}&tipo=salida")
            ->assertStatus(200)->assertJsonPath('total', 1)->assertJsonPath('data.0.motivo', 'Tubo dañado');

        $this->getJson('/api/movimientos-stock?fecha_desde=' . now()->toDateString() . '&fecha_hasta=' . now()->toDateString())
            ->assertStatus(200)->assertJsonPath('total', 1);

        $this->getJson('/api/movimientos-stock?per_page=500')->assertStatus(422)->assertJsonValidationErrors(['per_page']);
    }

    // ---------------------------------------------------------------- HU-17: plantilla por servicio

    public function test_plantilla_de_consumo_por_servicio(): void
    {
        $coloracion = $this->servicio('Coloración completa');

        $this->getJson("/api/servicios/{$coloracion->id}/recursos")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.nombre', 'Champú profesional')
            ->assertJsonPath('data.0.cantidad_utilizada', 20)
            ->assertJsonPath('data.2.nombre', 'Tinte en crema')
            ->assertJsonPath('data.2.unidad_medida', 'ml')
            ->assertJsonPath('data.2.cantidad_utilizada', 60);

        $tinte = $this->recurso('Tinte en crema');
        $crema = $this->recurso('Crema de tratamiento');

        $this->putJson("/api/servicios/{$coloracion->id}/recursos", ['recursos' => [
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => 70],
            ['recurso_id' => $crema->id, 'cantidad_utilizada' => 15.5],
        ]])->assertStatus(200)->assertJsonCount(2, 'data');

        $this->assertDatabaseMissing('servicio_recurso', ['servicio_id' => $coloracion->id, 'recurso_id' => $this->recurso('Oxidante 20 vol.')->id]);
        $this->assertDatabaseHas('servicio_recurso', ['servicio_id' => $coloracion->id, 'recurso_id' => $tinte->id, 'cantidad_utilizada' => 70]);

        // Insumo repetido o cantidad no positiva
        $this->putJson("/api/servicios/{$coloracion->id}/recursos", ['recursos' => [
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => 10],
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => 20],
        ]])->assertStatus(422)->assertJsonValidationErrors(['recursos.1.recurso_id']);

        $this->putJson("/api/servicios/{$coloracion->id}/recursos", ['recursos' => [
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => 0],
        ]])->assertStatus(422)->assertJsonValidationErrors(['recursos.0.cantidad_utilizada']);

        $this->putJson("/api/servicios/{$coloracion->id}/recursos", [])
            ->assertStatus(422)->assertJsonValidationErrors(['recursos']);

        // Lista vacía: el servicio queda sin consumo estándar
        $this->putJson("/api/servicios/{$coloracion->id}/recursos", ['recursos' => []])
            ->assertStatus(200)->assertJsonCount(0, 'data');
        $this->getJson("/api/servicios/{$coloracion->id}/recursos")->assertStatus(200)->assertJsonCount(0, 'data');
    }

    // ---------------------------------------------------------------- HU-18: consumo real

    public function test_precarga_del_consumo_estandar_al_reservar_y_al_agregar_un_servicio(): void
    {
        $reservaId = $this->reservar([$this->servicio('Coloración completa')->id]);
        $linea = $this->lineaDe($reservaId, 'Coloración completa');

        $this->assertDatabaseHas('detalle_reserva_recurso', ['detalle_reserva_id' => $linea->id, 'recurso_id' => $this->recurso('Tinte en crema')->id, 'cantidad_utilizada' => 60]);
        $this->assertDatabaseHas('detalle_reserva_recurso', ['detalle_reserva_id' => $linea->id, 'recurso_id' => $this->recurso('Oxidante 20 vol.')->id, 'cantidad_utilizada' => 90]);
        $this->assertDatabaseHas('detalle_reserva_recurso', ['detalle_reserva_id' => $linea->id, 'recurso_id' => $this->recurso('Champú profesional')->id, 'cantidad_utilizada' => 20]);

        // Servicio sumado en el momento con cantidad 2: la plantilla se multiplica
        $brushing = $this->servicio('Lavado, secado y modelado');
        $this->postJson("/api/reservas/{$reservaId}/servicios", ['servicio_id' => $brushing->id, 'cantidad' => 2])
            ->assertStatus(201);

        $lineaBrushing = $this->lineaDe($reservaId, 'Lavado, secado y modelado');
        $this->assertDatabaseHas('detalle_reserva_recurso', [
            'detalle_reserva_id' => $lineaBrushing->id, 'recurso_id' => $this->recurso('Champú profesional')->id, 'cantidad_utilizada' => 40,
        ]);

        // Reservar no descuenta stock: eso ocurre al completar
        $this->assertSame(1200.0, $this->existencia('Tinte en crema'));

        $this->getJson("/api/reservas/{$reservaId}/consumos")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.detalle_reserva_id', $linea->id)
            ->assertJsonPath('data.0.servicio.nombre', 'Coloración completa')
            ->assertJsonPath('data.0.cantidad', 1)
            ->assertJsonCount(3, 'data.0.consumos')
            ->assertJsonPath('data.0.consumos.2.nombre', 'Tinte en crema')
            ->assertJsonPath('data.0.consumos.2.unidad_medida', 'ml')
            ->assertJsonPath('data.0.consumos.2.cantidad_utilizada', 60)
            ->assertJsonPath('data.1.cantidad', 2)
            ->assertJsonPath('data.1.consumos.0.cantidad_utilizada', 40);
    }

    public function test_ajuste_del_consumo_y_bloqueo_en_estado_final(): void
    {
        $reservaId = $this->reservar([$this->servicio('Coloración completa')->id]);
        $linea = $this->lineaDe($reservaId, 'Coloración completa');
        $tinte = $this->recurso('Tinte en crema');
        $oxidante = $this->recurso('Oxidante 20 vol.');
        $crema = $this->recurso('Crema de tratamiento');

        // Se usó más tinte, se agregó crema y no hubo lavado con champú
        $this->putJson("/api/detalles-reserva/{$linea->id}/consumos", ['consumos' => [
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => 75],
            ['recurso_id' => $oxidante->id, 'cantidad_utilizada' => 90],
            ['recurso_id' => $crema->id, 'cantidad_utilizada' => 10],
        ]])->assertStatus(200)
            ->assertJsonPath('data.detalle_reserva_id', $linea->id)
            ->assertJsonCount(3, 'data.consumos');

        $this->assertSame(3, ConsumoRecurso::where('detalle_reserva_id', $linea->id)->count());
        $this->assertDatabaseMissing('detalle_reserva_recurso', ['detalle_reserva_id' => $linea->id, 'recurso_id' => $this->recurso('Champú profesional')->id]);
        $this->assertDatabaseHas('detalle_reserva_recurso', ['detalle_reserva_id' => $linea->id, 'recurso_id' => $tinte->id, 'cantidad_utilizada' => 75]);

        $this->putJson("/api/detalles-reserva/{$linea->id}/consumos", ['consumos' => [
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => 10],
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => -1],
        ]])->assertStatus(422)->assertJsonValidationErrors(['consumos.1.recurso_id', 'consumos.1.cantidad_utilizada']);

        $this->completar($reservaId);

        $this->putJson("/api/detalles-reserva/{$linea->id}/consumos", ['consumos' => [
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => 1],
        ]])->assertStatus(422)->assertJsonValidationErrors(['estado']);
        $this->assertDatabaseHas('detalle_reserva_recurso', ['detalle_reserva_id' => $linea->id, 'recurso_id' => $tinte->id, 'cantidad_utilizada' => 75]);

        // Una reserva cancelada tampoco admite ajustes
        $this->travelBack();
        $cancelada = $this->reservar([$this->servicio('Corte de dama')->id], 1, '14:00', '15:00');
        $this->deleteJson("/api/reservas/{$cancelada}")->assertStatus(200);
        $this->putJson('/api/detalles-reserva/' . $this->lineaDe($cancelada, 'Corte de dama')->id . '/consumos', ['consumos' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);
    }

    public function test_completar_la_reserva_descuenta_el_consumo_real_del_stock(): void
    {
        $reservaId = $this->reservar([$this->servicio('Coloración completa')->id]);
        $linea = $this->lineaDe($reservaId, 'Coloración completa');
        $tinte = $this->recurso('Tinte en crema');
        $oxidante = $this->recurso('Oxidante 20 vol.');
        $champu = $this->recurso('Champú profesional');

        $this->putJson("/api/detalles-reserva/{$linea->id}/consumos", ['consumos' => [
            ['recurso_id' => $tinte->id, 'cantidad_utilizada' => 75],
            ['recurso_id' => $oxidante->id, 'cantidad_utilizada' => 90],
            ['recurso_id' => $champu->id, 'cantidad_utilizada' => 0], // no se usó: no genera movimiento
        ]])->assertStatus(200);

        $this->completar($reservaId);

        $this->assertSame(1125.0, $this->existencia('Tinte en crema'));
        $this->assertSame(2910.0, $this->existencia('Oxidante 20 vol.'));
        $this->assertSame(5000.0, $this->existencia('Champú profesional'));

        $movimientos = MovimientoStock::where('motivo', "Consumo en reserva #{$reservaId}")->get();
        $this->assertCount(2, $movimientos);
        $this->assertTrue($movimientos->every(fn ($m) => $m->tipo === 'salida'));
        $this->assertDatabaseHas('movimientos_stock', ['recurso_id' => $tinte->id, 'tipo' => 'salida', 'cantidad' => 75]);
        $this->assertDatabaseHas('movimientos_stock', ['recurso_id' => $oxidante->id, 'tipo' => 'salida', 'cantidad' => 90]);
    }

    public function test_completar_no_falla_si_la_existencia_registrada_no_alcanza(): void
    {
        $tinte = $this->recurso('Tinte en crema');
        $this->postJson('/api/movimientos-stock', ['recurso_id' => $tinte->id, 'tipo' => 'salida', 'cantidad' => 1170])
            ->assertStatus(201);

        $reservaId = $this->reservar([$this->servicio('Coloración completa')->id]); // usa 60 ml de tinte
        $this->completar($reservaId);

        $this->assertSame(0.0, $this->existencia('Tinte en crema')); // nunca negativa
        $this->assertSame(2910.0, $this->existencia('Oxidante 20 vol.'));

        $movimiento = MovimientoStock::where('recurso_id', $tinte->id)
            ->where('motivo', 'like', "Consumo en reserva #{$reservaId}%")
            ->firstOrFail();
        $this->assertSame(30.0, (float) $movimiento->cantidad);
        $this->assertStringContainsString('existencia registrada insuficiente', $movimiento->motivo);
        $this->assertTrue($this->recurso('Tinte en crema')->bajo_minimo);
    }

    // ---------------------------------------------------------------- Permisos

    public function test_profesional_solo_opera_consumos_de_su_agenda_y_no_escribe_inventario(): void
    {
        $deMonica = $this->reservar([$this->servicio('Coloración completa')->id], 1);
        $deCarla = $this->reservar([$this->servicio('Corte de dama')->id], 2, '10:00', '11:00');
        $lineaMonica = $this->lineaDe($deMonica, 'Coloración completa');
        $lineaCarla = $this->lineaDe($deCarla, 'Corte de dama');
        $champu = $this->recurso('Champú profesional');

        $this->actingAs($this->carla);

        // Agenda ajena
        $this->getJson("/api/reservas/{$deMonica}/consumos")->assertStatus(403);
        $this->putJson("/api/detalles-reserva/{$lineaMonica->id}/consumos", ['consumos' => []])->assertStatus(403);
        $this->assertSame(3, ConsumoRecurso::where('detalle_reserva_id', $lineaMonica->id)->count());

        // Su propia agenda
        $this->getJson("/api/reservas/{$deCarla}/consumos")
            ->assertStatus(200)
            ->assertJsonPath('data.0.consumos.0.cantidad_utilizada', 15);
        $this->putJson("/api/detalles-reserva/{$lineaCarla->id}/consumos", ['consumos' => [
            ['recurso_id' => $champu->id, 'cantidad_utilizada' => 25],
        ]])->assertStatus(200);

        // Consulta sí, escrituras de administración no
        $coloracion = $this->servicio('Coloración completa');
        $this->getJson("/api/servicios/{$coloracion->id}/recursos")->assertStatus(200);
        $this->putJson("/api/servicios/{$coloracion->id}/recursos", ['recursos' => []])->assertStatus(403);
        $this->postJson('/api/recursos', ['nombre' => 'Laca', 'stock_minimo' => 1])->assertStatus(403);
        $this->putJson("/api/recursos/{$champu->id}", ['nombre' => 'Otro'])->assertStatus(403);
        $this->deleteJson("/api/recursos/{$champu->id}")->assertStatus(403);
        $this->getJson("/api/recursos/{$champu->id}")->assertStatus(403);
        $this->getJson('/api/movimientos-stock')->assertStatus(403);
        $this->postJson('/api/movimientos-stock', ['recurso_id' => $champu->id, 'tipo' => 'entrada', 'cantidad' => 10])->assertStatus(403);
        $this->assertSame(5000.0, $this->existencia('Champú profesional'));
    }
}

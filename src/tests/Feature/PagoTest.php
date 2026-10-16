<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Descuento;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Personal;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SprintUnoDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU-19 a HU-22: Métodos de pago, descuentos y registro de cobros.
 */
class PagoTest extends TestCase
{
    use RefreshDatabase;

    /** Identificadores buscados por nombre: en PostgreSQL las secuencias no se reinician entre pruebas. */
    protected int $ana;
    protected int $monica;
    protected int $corte;
    protected int $nutricion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SprintUnoDosSeeder::class);
        $this->actingAs(User::where('email', 'admin@meninacoiffure.com')->first());

        $this->ana = Cliente::where('nombre', 'Ana')->where('apellido', 'Gómez')->value('id');
        $this->monica = Personal::where('email', 'monica@meninacoiffure.com')->value('id');
        $this->corte = Servicio::where('nombre', 'Corte de dama')->value('id');
        $this->nutricion = Servicio::where('nombre', 'Nutrición e hidratación capilar')->value('id');
    }

    protected function metodo(string $nombre): MetodoPago
    {
        return MetodoPago::where('nombre', $nombre)->firstOrFail();
    }

    /**
     * Reserva del próximo lunes creada por la API: Corte (Gs. 70.000) + Nutrición (Gs. 50.000).
     */
    protected function crearReserva(array $cambios = []): Reserva
    {
        $respuesta = $this->postJson('/api/reservas', [
            'cliente_id' => $this->ana,
            'personal_id' => $this->monica,
            'fecha' => Carbon::parse('next monday')->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:15',
            'servicios' => [$this->corte, $this->nutricion],
            ...$cambios,
        ])->assertStatus(201);

        return Reserva::findOrFail($respuesta->json('data.id'));
    }

    /**
     * Reserva insertada directamente (por ejemplo, en un estado final o en el pasado), con un corte de Gs. 70.000.
     */
    protected function reservaDirecta(string $estado): Reserva
    {
        $reserva = Reserva::create([
            'cliente_id' => $this->ana,
            'personal_id' => $this->monica,
            'fecha' => now()->subDays(3)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => $estado,
        ]);

        $reserva->detalles()->create(['servicio_id' => $this->corte, 'precio_aplicado' => 70000, 'cantidad' => 1]);

        return $reserva;
    }

    /**
     * HU-21: CRUD de métodos de pago; no se borra un método con cobros.
     */
    public function test_crud_de_metodos_de_pago(): void
    {
        $this->getJson('/api/metodos-pago')
            ->assertStatus(200)
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.nombre', 'Billetera electrónica')
            ->assertJsonPath('data.1.nombre', 'Efectivo');

        $this->postJson('/api/metodos-pago', [])->assertStatus(422)->assertJsonValidationErrors(['nombre']);
        $this->postJson('/api/metodos-pago', ['nombre' => 'Efectivo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        $id = $this->postJson('/api/metodos-pago', ['nombre' => 'Cheque'])
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Cheque')
            ->json('data.id');

        $this->putJson("/api/metodos-pago/{$id}", ['nombre' => 'Transferencia bancaria'])
            ->assertStatus(422)->assertJsonValidationErrors(['nombre']);
        $this->putJson("/api/metodos-pago/{$id}", ['nombre' => 'Cheque diferido'])
            ->assertStatus(200)->assertJsonPath('data.nombre', 'Cheque diferido');
        $this->getJson("/api/metodos-pago/{$id}")
            ->assertStatus(200)->assertJsonPath('data.pagos_count', 0);

        $this->deleteJson("/api/metodos-pago/{$id}")->assertStatus(200);
        $this->assertDatabaseMissing('metodos_pago', ['id' => $id]);

        // Con un cobro registrado, el método queda protegido
        $efectivo = $this->metodo('Efectivo');
        Pago::create(['metodo_pago_id' => $efectivo->id, 'monto' => 10000, 'fecha' => now(), 'descripcion' => 'Venta de champú']);
        $this->deleteJson("/api/metodos-pago/{$efectivo->id}")
            ->assertStatus(422)->assertJsonValidationErrors(['metodo_pago']);
        $this->assertDatabaseHas('metodos_pago', ['id' => $efectivo->id]);
    }

    /**
     * HU-22: Alta, edición y baja de descuentos con sus validaciones.
     */
    public function test_crud_de_descuentos_y_validaciones(): void
    {
        $base = ['nombre' => 'Día de la madre', 'tipo' => 'porcentaje', 'valor' => 15, 'fecha_inicio' => '2026-05-01', 'fecha_fin' => '2026-05-31'];

        $this->postJson('/api/descuentos', [])
            ->assertStatus(422)->assertJsonValidationErrors(['nombre', 'tipo', 'valor']);
        $this->postJson('/api/descuentos', [...$base, 'tipo' => 'regalo'])
            ->assertStatus(422)->assertJsonValidationErrors(['tipo']);
        $this->postJson('/api/descuentos', [...$base, 'valor' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['valor']);
        $this->postJson('/api/descuentos', [...$base, 'valor' => 150])
            ->assertStatus(422)->assertJsonValidationErrors(['valor']);
        $this->postJson('/api/descuentos', [...$base, 'fecha_fin' => '2026-04-30'])
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_fin']);

        // Un monto fijo sí puede superar 100
        $this->postJson('/api/descuentos', [...$base, 'nombre' => 'Gift card', 'tipo' => 'monto_fijo', 'valor' => 50000])
            ->assertStatus(201);

        $creado = $this->postJson('/api/descuentos', $base)
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Día de la madre')
            ->assertJsonPath('data.activo', true);
        $id = $creado->json('data.id');

        // Las reglas cruzadas se aplican sobre el resultado final de la edición
        $this->putJson("/api/descuentos/{$id}", ['valor' => 120])
            ->assertStatus(422)->assertJsonValidationErrors(['valor']);
        $this->putJson("/api/descuentos/{$id}", ['fecha_fin' => '2026-04-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_fin']);
        $this->putJson("/api/descuentos/{$id}", ['tipo' => 'monto_fijo', 'valor' => 20000, 'activo' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.tipo', 'monto_fijo')
            ->assertJsonPath('data.activo', false);

        $this->getJson("/api/descuentos/{$id}")->assertStatus(200)->assertJsonPath('data.reservas_count', 0);
        $this->deleteJson("/api/descuentos/{$id}")->assertStatus(200);
        $this->assertDatabaseMissing('descuentos', ['id' => $id]);

        // Una promoción aplicada en reservas no se borra: se desactiva
        $promo = Descuento::where('nombre', 'Promo Primavera')->first();
        $this->crearReserva(['descuento_id' => $promo->id]);
        $this->deleteJson("/api/descuentos/{$promo->id}")
            ->assertStatus(422)->assertJsonValidationErrors(['descuento']);
        $this->putJson("/api/descuentos/{$promo->id}", ['activo' => false])->assertStatus(200);
        $this->assertDatabaseHas('reservas', ['descuento_id' => $promo->id]);
    }

    /**
     * HU-22: solo_vigentes devuelve las promociones activas cuya vigencia incluye hoy.
     */
    public function test_filtro_de_descuentos_vigentes(): void
    {
        $hoy = now();
        Descuento::create(['nombre' => 'Inactiva', 'tipo' => 'porcentaje', 'valor' => 5, 'activo' => false]);
        Descuento::create(['nombre' => 'Vencida', 'tipo' => 'porcentaje', 'valor' => 5, 'activo' => true,
            'fecha_inicio' => $hoy->copy()->subMonth()->toDateString(), 'fecha_fin' => $hoy->copy()->subDay()->toDateString()]);
        Descuento::create(['nombre' => 'Futura', 'tipo' => 'porcentaje', 'valor' => 5, 'activo' => true,
            'fecha_inicio' => $hoy->copy()->addDay()->toDateString()]);
        Descuento::create(['nombre' => 'Vigente hoy', 'tipo' => 'monto_fijo', 'valor' => 10000, 'activo' => true,
            'fecha_inicio' => $hoy->toDateString(), 'fecha_fin' => $hoy->toDateString()]);

        $this->getJson('/api/descuentos')->assertStatus(200)->assertJsonCount(5, 'data');

        $nombres = collect($this->getJson('/api/descuentos?solo_vigentes=1')->assertStatus(200)->json('data'))
            ->pluck('nombre')->sort()->values()->all();
        $this->assertSame(['Promo Primavera', 'Vigente hoy'], $nombres);
    }

    /**
     * HU-20: Seña en una reserva pendiente y pago final hasta dejar el saldo en cero.
     */
    public function test_sena_y_pago_final_hasta_saldo_cero(): void
    {
        $promo = Descuento::where('nombre', 'Promo Primavera')->first();
        $reserva = $this->crearReserva(['descuento_id' => $promo->id]); // 120.000 - 10 % = 108.000

        $sena = $this->postJson('/api/pagos', [
            'reserva_id' => $reserva->id,
            'metodo_pago_id' => $this->metodo('Transferencia bancaria')->id,
            'monto' => 30000,
            'descripcion' => 'Seña',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Pago registrado exitosamente.')
            ->assertJsonPath('data.metodo_pago.nombre', 'Transferencia bancaria')
            ->assertJsonPath('data.reserva.cliente.nombre', 'Ana');
        $this->assertEquals(108000, $sena->json('data.reserva.total'));
        $this->assertEquals(30000, $sena->json('data.reserva.pagado'));
        $this->assertEquals(78000, $sena->json('data.reserva.saldo'));

        $final = $this->postJson('/api/pagos', [
            'reserva_id' => $reserva->id,
            'metodo_pago_id' => $this->metodo('Efectivo')->id,
            'monto' => 78000,
        ])->assertStatus(201);
        $this->assertEquals(108000, $final->json('data.reserva.pagado'));
        $this->assertEquals(0, $final->json('data.reserva.saldo'));

        // Saldada la reserva, no se admite otro cobro
        $this->postJson('/api/pagos', [
            'reserva_id' => $reserva->id,
            'metodo_pago_id' => $this->metodo('Efectivo')->id,
            'monto' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors(['monto']);

        $detalle = $this->getJson("/api/reservas/{$reserva->id}")->assertStatus(200);
        $this->assertEquals(0, $detalle->json('data.saldo'));
        $this->assertCount(2, $detalle->json('data.pagos'));

        // También se cobra una reserva ya completada
        $completada = $this->reservaDirecta('completada');
        $cobro = $this->postJson('/api/pagos', [
            'reserva_id' => $completada->id,
            'metodo_pago_id' => $this->metodo('Efectivo')->id,
            'monto' => 70000,
        ])->assertStatus(201);
        $this->assertEquals(0, $cobro->json('data.reserva.saldo'));
    }

    /**
     * HU-20: El monto no puede superar el saldo; el mensaje muestra el saldo en guaraníes.
     */
    public function test_pago_que_excede_el_saldo_es_rechazado(): void
    {
        $reserva = $this->crearReserva(); // 120.000

        $respuesta = $this->postJson('/api/pagos', [
            'reserva_id' => $reserva->id,
            'metodo_pago_id' => $this->metodo('Efectivo')->id,
            'monto' => 120001,
        ])->assertStatus(422)->assertJsonValidationErrors(['monto']);

        $this->assertStringContainsString('120.000', $respuesta->json('errors.monto.0'));
        $this->assertDatabaseCount('pagos_ingresos', 0);

        $this->postJson('/api/pagos', ['reserva_id' => $reserva->id, 'metodo_pago_id' => $this->metodo('Efectivo')->id, 'monto' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['monto']);
        $this->postJson('/api/pagos', ['reserva_id' => 999999999, 'metodo_pago_id' => 999999999, 'monto' => 100])
            ->assertStatus(422)->assertJsonValidationErrors(['reserva_id', 'metodo_pago_id']);
    }

    /**
     * Una reserva cancelada o en la que la clienta no vino no se cobra.
     */
    public function test_no_se_cobra_reserva_cancelada_ni_ausente(): void
    {
        $reserva = $this->crearReserva();
        $this->deleteJson("/api/reservas/{$reserva->id}")->assertStatus(200);

        $this->postJson('/api/pagos', [
            'reserva_id' => $reserva->id,
            'metodo_pago_id' => $this->metodo('Efectivo')->id,
            'monto' => 10000,
        ])->assertStatus(422)->assertJsonValidationErrors(['reserva_id']);

        $ausente = $this->reservaDirecta('no_asistio');
        $this->postJson('/api/pagos', [
            'reserva_id' => $ausente->id,
            'metodo_pago_id' => $this->metodo('Efectivo')->id,
            'monto' => 10000,
        ])->assertStatus(422)->assertJsonValidationErrors(['reserva_id']);

        $this->assertDatabaseCount('pagos_ingresos', 0);
    }

    /**
     * HU-19: Un ingreso sin reserva exige descripción y no puede tener fecha futura.
     */
    public function test_ingreso_sin_reserva_exige_descripcion(): void
    {
        $efectivo = $this->metodo('Efectivo')->id;

        $this->postJson('/api/pagos', ['metodo_pago_id' => $efectivo, 'monto' => 25000])
            ->assertStatus(422)->assertJsonValidationErrors(['descripcion']);
        $this->postJson('/api/pagos', ['metodo_pago_id' => $efectivo, 'monto' => 25000, 'descripcion' => '   '])
            ->assertStatus(422)->assertJsonValidationErrors(['descripcion']);
        $this->postJson('/api/pagos', [
            'metodo_pago_id' => $efectivo, 'monto' => 25000, 'descripcion' => 'Venta de champú',
            'fecha' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['fecha']);

        $this->postJson('/api/pagos', [
            'metodo_pago_id' => $efectivo, 'monto' => 25000, 'descripcion' => 'Venta de champú',
            'fecha' => '2026-03-07 15:30:00',
        ])->assertStatus(201)
            ->assertJsonPath('data.reserva_id', null)
            ->assertJsonPath('data.descripcion', 'Venta de champú')
            ->assertJsonPath('data.metodo_pago.nombre', 'Efectivo');

        $this->assertDatabaseHas('pagos_ingresos', ['reserva_id' => null, 'monto' => 25000, 'fecha' => '2026-03-07 15:30:00']);
    }

    /**
     * Listado con filtros, detalle y anulación de un pago cargado por error.
     */
    public function test_listado_filtros_detalle_y_anulacion(): void
    {
        $reserva = $this->crearReserva();
        $efectivo = $this->metodo('Efectivo')->id;
        $tarjeta = $this->metodo('Tarjeta de débito o crédito')->id;

        $p1 = Pago::create(['reserva_id' => $reserva->id, 'metodo_pago_id' => $efectivo, 'monto' => 20000, 'fecha' => '2026-03-02 10:00:00']);
        $p2 = Pago::create(['reserva_id' => $reserva->id, 'metodo_pago_id' => $tarjeta, 'monto' => 30000, 'fecha' => '2026-03-04 18:30:00']);
        Pago::create(['metodo_pago_id' => $efectivo, 'monto' => 15000, 'fecha' => '2026-03-05 09:00:00', 'descripcion' => 'Venta de producto']);

        $todos = $this->getJson('/api/pagos')->assertStatus(200)->assertJsonPath('total', 3);
        $this->assertSame('Venta de producto', $todos->json('data.0.descripcion')); // más reciente primero
        $this->assertSame('Ana', $todos->json('data.1.reserva.cliente.nombre'));
        $this->assertEquals(70000, $todos->json('data.1.reserva.saldo'));
        $this->assertArrayNotHasKey('detalles', $todos->json('data.1.reserva'));

        // fecha_hasta incluye el día completo
        $this->getJson('/api/pagos?fecha_desde=2026-03-02&fecha_hasta=2026-03-04')
            ->assertStatus(200)->assertJsonPath('total', 2);
        $this->getJson("/api/pagos?metodo_pago_id={$tarjeta}")
            ->assertStatus(200)->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $p2->id);
        $this->getJson("/api/pagos?reserva_id={$reserva->id}&per_page=1")
            ->assertStatus(200)->assertJsonPath('total', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/pagos?per_page=500')->assertStatus(422)->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/pagos?fecha_desde=2026-03-05&fecha_hasta=2026-03-01')
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_hasta']);

        $this->getJson("/api/pagos/{$p1->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.metodo_pago.nombre', 'Efectivo')
            ->assertJsonPath('data.reserva.id', $reserva->id);

        // Al anular un cobro, la reserva recupera ese saldo
        $this->deleteJson("/api/pagos/{$p1->id}")->assertStatus(200)->assertJsonPath('message', 'Pago anulado exitosamente.');
        $this->assertDatabaseMissing('pagos_ingresos', ['id' => $p1->id]);
        $this->assertEquals(90000, $this->getJson("/api/reservas/{$reserva->id}")->json('data.saldo'));
        $this->getJson('/api/pagos/999999999')->assertStatus(404);
    }

    /**
     * Permisos: el profesional consulta métodos y descuentos, pero no registra cobros ni los configura.
     */
    public function test_profesional_solo_consulta_metodos_y_descuentos(): void
    {
        $efectivo = $this->metodo('Efectivo')->id;
        $promo = Descuento::where('nombre', 'Promo Primavera')->value('id');
        $pago = Pago::create(['metodo_pago_id' => $efectivo, 'monto' => 10000, 'fecha' => now(), 'descripcion' => 'Venta de champú']);
        $this->actingAs(User::where('email', 'carla@meninacoiffure.com')->first());

        $this->getJson('/api/metodos-pago')->assertStatus(200);
        $this->getJson('/api/descuentos?solo_vigentes=1')->assertStatus(200);

        $this->getJson('/api/pagos')->assertStatus(403);
        $this->postJson('/api/pagos', ['metodo_pago_id' => $efectivo, 'monto' => 1000, 'descripcion' => 'x'])->assertStatus(403);
        $this->getJson("/api/pagos/{$pago->id}")->assertStatus(403);
        $this->deleteJson("/api/pagos/{$pago->id}")->assertStatus(403);
        $this->assertDatabaseHas('pagos_ingresos', ['id' => $pago->id]);
        $this->postJson('/api/metodos-pago', ['nombre' => 'Cheque'])->assertStatus(403);
        $this->deleteJson("/api/metodos-pago/{$efectivo}")->assertStatus(403);
        $this->postJson('/api/descuentos', ['nombre' => 'x', 'tipo' => 'porcentaje', 'valor' => 5])->assertStatus(403);
        $this->putJson("/api/descuentos/{$promo}", ['activo' => false])->assertStatus(403);
    }
}

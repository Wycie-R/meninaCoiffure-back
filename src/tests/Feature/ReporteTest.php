<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Descuento;
use App\Models\MetodoPago;
use App\Models\MovimientoStock;
use App\Models\Pago;
use App\Models\Personal;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\StockRecurso;
use App\Models\User;
use Database\Seeders\SprintUnoDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HU-23 y HU-24: Reportes de gestión con un conjunto de datos conocido.
 *
 * Período: lunes 2026-03-02 a domingo 2026-03-08.
 *  R1 03-02 Mónica  Ana      completada  Corte + Nutrición (120.000) - 10 %  = 108.000  75 min  pagos 50.000 ef. + 58.000 transf.
 *  R2 03-03 Carla   Beatriz  completada  Coloración (180.000), a domicilio   = 180.000  90 min  pago 100.000 ef.
 *  R3 03-03 Mónica  Beatriz  no_asistio  Corte
 *  R7 03-04 Carla   Gabriela completada  Depilación (30.000) - Gs. 40.000    = 0 (tope)  20 min
 *  R4 03-05 Carla   Ana      cancelada   Nutrición
 *  R5 03-06 Mónica  Ana      confirmada  Corte
 *  R8 03-06 Mónica  Ana      completada  Corte                              =  70.000  45 min  sin pagos
 *  Ingreso suelto 03-07: 15.000 ef. Fuera del período: R6 03-10 completada con su pago.
 */
class ReporteTest extends TestCase
{
    use RefreshDatabase;

    protected const PERIODO = '?fecha_desde=2026-03-02&fecha_hasta=2026-03-08';

    /** Identificadores buscados por nombre: en PostgreSQL las secuencias no se reinician entre pruebas. */
    protected int $ana;
    protected int $beatriz;
    protected int $monica;
    protected int $carla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SprintUnoDosSeeder::class);
        $this->actingAs(User::where('email', 'admin@meninacoiffure.com')->first());

        $this->ana = Cliente::where('nombre', 'Ana')->where('apellido', 'Gómez')->value('id');
        $this->beatriz = Cliente::where('nombre', 'Beatriz')->where('apellido', 'López')->value('id');
        $this->monica = Personal::where('email', 'monica@meninacoiffure.com')->value('id');
        $this->carla = Personal::where('email', 'carla@meninacoiffure.com')->value('id');
    }

    protected function servicio(string $nombre): Servicio
    {
        return Servicio::where('nombre', $nombre)->firstOrFail();
    }

    protected function metodo(string $nombre): int
    {
        return MetodoPago::where('nombre', $nombre)->value('id');
    }

    /**
     * @param array<int, array{0: string, 1: float}> $lineas [nombre del servicio, precio]
     */
    protected function reserva(string $fecha, int $personal, int $cliente, string $estado, string $inicio, string $fin, array $lineas, array $extra = []): Reserva
    {
        $reserva = Reserva::create([
            'cliente_id' => $cliente,
            'personal_id' => $personal,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'estado' => $estado,
            ...$extra,
        ]);

        foreach ($lineas as [$nombre, $precio]) {
            $reserva->detalles()->create(['servicio_id' => $this->servicio($nombre)->id, 'precio_aplicado' => $precio, 'cantidad' => 1]);
        }

        return $reserva;
    }

    protected function pago(?Reserva $reserva, string $metodo, float $monto, string $fecha, ?string $descripcion = null): void
    {
        Pago::create([
            'reserva_id' => $reserva?->id,
            'metodo_pago_id' => $this->metodo($metodo),
            'monto' => $monto,
            'fecha' => $fecha,
            'descripcion' => $descripcion,
        ]);
    }

    protected function cargarDatos(): void
    {
        $promo = Descuento::where('nombre', 'Promo Primavera')->first(); // 10 %
        $cortesia = Descuento::create(['nombre' => 'Cortesía', 'tipo' => 'monto_fijo', 'valor' => 40000, 'activo' => true]);
        $gabriela = Cliente::create(['nombre' => 'Gabriela', 'apellido' => 'Sosa', 'fecha_registro' => '2026-03-04']);

        $r1 = $this->reserva('2026-03-02', $this->monica, $this->ana, 'completada', '10:00:00', '11:15:00',
            [['Corte de dama', 70000], ['Nutrición e hidratación capilar', 50000]], ['descuento_id' => $promo->id]);
        $r2 = $this->reserva('2026-03-03', $this->carla, $this->beatriz, 'completada', '09:00:00', '10:30:00',
            [['Coloración completa', 180000]], ['a_domicilio' => true, 'direccion_domicilio' => 'Calle Ejemplo 123, Villa Elisa']);
        $this->reserva('2026-03-03', $this->monica, $this->beatriz, 'no_asistio', '14:00:00', '14:45:00', [['Corte de dama', 70000]]);
        $this->reserva('2026-03-04', $this->carla, $gabriela->id, 'completada', '11:00:00', '11:20:00',
            [['Depilación facial', 30000]], ['descuento_id' => $cortesia->id]);
        $this->reserva('2026-03-05', $this->carla, $this->ana, 'cancelada', '09:00:00', '09:30:00', [['Nutrición e hidratación capilar', 50000]]);
        $this->reserva('2026-03-06', $this->monica, $this->ana, 'confirmada', '14:00:00', '14:45:00', [['Corte de dama', 70000]]);
        $this->reserva('2026-03-06', $this->monica, $this->ana, 'completada', '09:00:00', '09:45:00', [['Corte de dama', 70000]]);
        $r6 = $this->reserva('2026-03-10', $this->monica, $this->beatriz, 'completada', '09:00:00', '10:30:00', [['Coloración completa', 180000]]);

        $this->pago($r1, 'Efectivo', 50000, '2026-03-02 11:20:00');
        $this->pago($r1, 'Transferencia bancaria', 58000, '2026-03-04 08:00:00');
        $this->pago($r2, 'Efectivo', 100000, '2026-03-03 10:40:00');
        $this->pago(null, 'Efectivo', 15000, '2026-03-07 23:59:00', 'Venta de champú');
        $this->pago($r6, 'Efectivo', 180000, '2026-03-10 10:40:00');
    }

    public function test_resumen_del_periodo(): void
    {
        $this->cargarDatos();

        DB::enableQueryLog();
        $datos = $this->getJson('/api/reportes/resumen' . self::PERIODO)->assertStatus(200)->json('data');
        // Consultas agregadas: la cantidad no crece con las reservas (sin N+1)
        $this->assertLessThan(12, count(DB::getQueryLog()));

        $this->assertSame(['desde' => '2026-03-02', 'hasta' => '2026-03-08'], $datos['periodo']);
        $this->assertSame(7, $datos['reservas']['total']);
        $this->assertSame(
            ['pendiente' => 0, 'confirmada' => 1, 'completada' => 4, 'cancelada' => 1, 'no_asistio' => 1],
            $datos['reservas']['por_estado']
        );
        $this->assertSame(1, $datos['reservas']['a_domicilio']);
        $this->assertEquals(0.2, $datos['reservas']['tasa_ausencia']);       // 1 / (4 + 1)
        $this->assertEquals(0.1429, $datos['reservas']['tasa_cancelacion']); // 1 / 7

        $this->assertEquals(358000, $datos['ingresos']['facturado']);   // 108.000 + 180.000 + 0 + 70.000
        $this->assertEquals(223000, $datos['ingresos']['cobrado']);     // 50.000 + 58.000 + 100.000 + 15.000
        $this->assertEquals(150000, $datos['ingresos']['por_cobrar']);  // 80.000 de R2 + 70.000 de R8
        $this->assertEquals(89500, $datos['ingresos']['ticket_promedio']);

        $porMetodo = $datos['ingresos']['por_metodo'];
        $this->assertCount(2, $porMetodo);
        $this->assertSame('Efectivo', $porMetodo[0]['metodo']);
        $this->assertEquals(165000, $porMetodo[0]['monto']);
        $this->assertSame(3, $porMetodo[0]['cantidad']);
        $this->assertSame('Transferencia bancaria', $porMetodo[1]['metodo']);
        $this->assertEquals(58000, $porMetodo[1]['monto']);

        $this->assertSame(['atendidos' => 3, 'nuevos' => 1], $datos['clientes']);
    }

    public function test_servicios_mas_pedidos(): void
    {
        $this->cargarDatos();

        $filas = $this->getJson('/api/reportes/servicios' . self::PERIODO)->assertStatus(200)->json('data');

        $this->assertSame(['Corte de dama', 'Coloración completa', 'Nutrición e hidratación capilar', 'Depilación facial'], array_column($filas, 'nombre'));
        $this->assertSame([2, 1, 1, 1], array_column($filas, 'cantidad'));
        $this->assertSame('Peluquería', $filas[0]['categoria']);
        // Cada línea lleva su parte del descuento de la reserva: 63.000 (R1) + 70.000 (R8)
        $this->assertEquals([133000, 180000, 45000, 0], array_column($filas, 'ingresos'));
        $this->assertEquals(358000, array_sum(array_column($filas, 'ingresos')));
    }

    public function test_desempeno_del_personal(): void
    {
        $this->cargarDatos();

        $filas = $this->getJson('/api/reportes/personal' . self::PERIODO)->assertStatus(200)->json('data');

        $this->assertCount(2, $filas);
        $this->assertEquals([
            'personal_id' => $this->carla, 'nombre' => 'Carla', 'apellido' => 'Estilista',
            'completadas' => 2, 'no_asistio' => 0, 'canceladas' => 1, 'minutos_atendidos' => 110, 'facturado' => 180000,
        ], $filas[0]);
        $this->assertEquals([
            'personal_id' => $this->monica, 'nombre' => 'Mónica', 'apellido' => 'Propietaria',
            'completadas' => 2, 'no_asistio' => 1, 'canceladas' => 0, 'minutos_atendidos' => 120, 'facturado' => 178000,
        ], $filas[1]);
    }

    public function test_ingresos_diarios_rellena_los_dias_sin_movimiento(): void
    {
        $this->cargarDatos();

        $dias = $this->getJson('/api/reportes/ingresos-diarios' . self::PERIODO)->assertStatus(200)->json('data');

        $this->assertCount(7, $dias);
        $this->assertSame(['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08'], array_column($dias, 'fecha'));
        $this->assertSame([1, 1, 1, 0, 1, 0, 0], array_column($dias, 'citas_completadas'));
        $this->assertEquals([108000, 180000, 0, 0, 70000, 0, 0], array_column($dias, 'facturado'));
        $this->assertEquals([50000, 100000, 58000, 0, 0, 15000, 0], array_column($dias, 'cobrado'));
    }

    public function test_stock_con_recursos_bajo_minimo_primero(): void
    {
        $tinte = StockRecurso::where('nombre', 'Tinte en crema')->first(); // mínimo 300 ml
        $tinte->update(['cantidad_actual' => 200]);

        MovimientoStock::create(['recurso_id' => $tinte->id, 'tipo' => 'entrada', 'cantidad' => 500, 'fecha' => '2026-03-03 09:00:00', 'motivo' => 'Compra']);
        MovimientoStock::create(['recurso_id' => $tinte->id, 'tipo' => 'salida', 'cantidad' => 60, 'fecha' => '2026-03-02 10:00:00', 'motivo' => 'Consumo']);
        MovimientoStock::create(['recurso_id' => $tinte->id, 'tipo' => 'salida', 'cantidad' => 999, 'fecha' => '2026-03-09 10:00:00', 'motivo' => 'Fuera del período']);

        $recursos = $this->getJson('/api/reportes/stock' . self::PERIODO)->assertStatus(200)->json('data.recursos');

        $this->assertCount(StockRecurso::count(), $recursos);
        $this->assertSame('Tinte en crema', $recursos[0]['nombre']);
        $this->assertTrue($recursos[0]['bajo_minimo']);
        $this->assertSame('ml', $recursos[0]['unidad_medida']);
        $this->assertEquals(200, $recursos[0]['cantidad_actual']);
        $this->assertEquals(300, $recursos[0]['stock_minimo']);
        $this->assertEquals(500, $recursos[0]['entradas']);
        $this->assertEquals(60, $recursos[0]['consumido']);

        $this->assertFalse($recursos[1]['bajo_minimo']);
        $this->assertEquals(0, $recursos[1]['entradas']);
    }

    public function test_periodo_por_defecto_y_validaciones(): void
    {
        $this->getJson('/api/reportes/resumen')
            ->assertStatus(200)
            ->assertJsonPath('data.periodo.desde', now()->startOfMonth()->toDateString())
            ->assertJsonPath('data.periodo.hasta', now()->toDateString());

        // Sin datos, las tasas y el ticket quedan en cero (sin división por cero)
        $vacio = $this->getJson('/api/reportes/resumen?fecha_desde=2020-01-01&fecha_hasta=2020-01-31')->assertStatus(200)->json('data');
        $this->assertEquals(0, $vacio['reservas']['tasa_ausencia']);
        $this->assertEquals(0, $vacio['ingresos']['ticket_promedio']);
        $this->assertSame([], $vacio['ingresos']['por_metodo']);

        $this->getJson('/api/reportes/resumen?fecha_desde=2026-03-08&fecha_hasta=2026-03-01')
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_hasta']);
        $this->getJson('/api/reportes/ingresos-diarios?fecha_desde=2025-01-01&fecha_hasta=2026-03-01')
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_hasta']);
        $this->getJson('/api/reportes/servicios?fecha_desde=no-es-fecha')
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_desde']);

        // 366 días (año bisiesto completo) es el máximo permitido
        $this->getJson('/api/reportes/ingresos-diarios?fecha_desde=2024-01-01&fecha_hasta=2024-12-31')
            ->assertStatus(200)->assertJsonCount(366, 'data');
    }

    public function test_profesional_no_accede_a_reportes(): void
    {
        $this->actingAs(User::where('email', 'carla@meninacoiffure.com')->first());

        foreach (['resumen', 'servicios', 'personal', 'ingresos-diarios', 'stock'] as $reporte) {
            $this->getJson("/api/reportes/{$reporte}")->assertStatus(403);
        }
    }
}

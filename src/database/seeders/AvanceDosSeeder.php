<?php

namespace Database\Seeders;

use App\Models\CategoriaServicio;
use App\Models\Cliente;
use App\Models\Descuento;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\MetodoPago;
use App\Models\Personal;
use App\Models\PrecioServicio;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

/**
 * Datos base del salón, alineados con el relevamiento del proyecto de Liderazgo
 * (AvanceLiderazgo_Menina_Coiffure): peluquería y estética, lunes a viernes de 8 a 17 h,
 * atención en el local y a domicilio. Los precios son referenciales hasta confirmarlos
 * con la propietaria.
 *
 * Cada registro se busca antes de crearlo, así que el seeder se puede repetir sin duplicar
 * nada (antes una guarda global sobre las categorías dejaba al personal triplicado).
 */
class AvanceDosSeeder extends Seeder
{
    public function run(): void
    {
        $this->clientes();
        $personal = $this->personal();
        $this->horarioDelLocal();
        $servicios = $this->catalogo();

        Descuento::firstOrCreate(['nombre' => 'Promo Primavera'], ['tipo' => 'porcentaje', 'valor' => 10, 'activo' => true]);

        foreach (['Efectivo', 'Transferencia bancaria', 'Tarjeta de débito o crédito', 'Billetera electrónica'] as $metodo) {
            MetodoPago::firstOrCreate(['nombre' => $metodo]);
        }

        $this->especialidades($personal);
    }

    protected function clientes(): void
    {
        Cliente::firstOrCreate(['nombre' => 'Ana', 'apellido' => 'Gómez'], [
            'telefono' => '0981111222', 'email' => 'ana.gomez@example.com', 'fecha_registro' => now()->toDateString(),
        ]);
        Cliente::firstOrCreate(['nombre' => 'Beatriz', 'apellido' => 'López'], [
            'telefono' => '0982333444', 'email' => 'beatriz.lopez@example.com', 'fecha_registro' => now()->toDateString(),
        ]);
    }

    /**
     * El equipo que atiende por cita, bajo demanda y dentro del horario del salón: la propietaria
     * (que también presta servicios) y la estilista y maquilladora. La asistente de salón apoya en el lavacabezas y no recibe reservas.
     *
     * @return array<string, Personal>
     */
    protected function personal(): array
    {
        $equipo = [
            'monica' => ['Mónica', 'Propietaria', 'monica@meninacoiffure.com', '0984000001', '2018-03-01'],
            'carla' => ['Carla', 'Estilista', 'carla@meninacoiffure.com', '0971555666', '2025-01-15'],
        ];

        $personal = [];
        foreach ($equipo as $clave => [$nombre, $apellido, $email, $telefono, $ingreso]) {
            $persona = Personal::firstOrCreate(['email' => $email], [
                'nombre' => $nombre, 'apellido' => $apellido, 'telefono' => $telefono,
                'fecha_ingreso' => $ingreso, 'activo' => true,
            ]);

            $personal[$clave] = $persona;
        }

        return $personal;
    }

    /**
     * Horario confirmado por la propietaria: lunes a viernes de 08:00 a 17:00.
     */
    protected function horarioDelLocal(): void
    {
        if (HorarioAtencion::exists()) {
            return;
        }

        foreach (range(1, 5) as $dia) {
            HorarioAtencion::create(['dia_semana' => $dia, 'hora_apertura' => '08:00:00', 'hora_cierre' => '17:00:00']);
        }
    }

    /**
     * Las dos líneas de servicio del salón: capilar y estética. Los cuatro primeros conservan
     * el orden de los datos originales porque las pruebas automáticas usan sus identificadores.
     *
     * @return array<string, Servicio>
     */
    protected function catalogo(): array
    {
        $categorias = [];
        foreach ([
            'Peluquería' => 'Cortes, lavado, modelado y peinados para damas, caballeros y niños',
            'Colorimetría' => 'Tintes, mechas, reflejos y balayage',
            'Tratamientos capilares' => 'Nutrición e hidratación del cabello',
            'Estética y maquillaje' => 'Depilación, tratamientos faciales básicos y maquillaje social',
        ] as $nombre => $descripcion) {
            $categorias[$nombre] = CategoriaServicio::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }

        // [clave, categoría, nombre, minutos, precio en guaraníes, activo]
        $catalogo = [
            ['corte_dama', 'Peluquería', 'Corte de dama', 45, 70000, true],
            ['nutricion', 'Tratamientos capilares', 'Nutrición e hidratación capilar', 30, 50000, true],
            ['coloracion', 'Colorimetría', 'Coloración completa', 90, 180000, true],
            ['decoloracion', 'Colorimetría', 'Decoloración Extrema (Descontinuado)', 120, 220000, false],
            ['corte_caballero', 'Peluquería', 'Corte de caballero', 30, 40000, true],
            ['corte_ninos', 'Peluquería', 'Corte de niños', 30, 35000, true],
            ['brushing', 'Peluquería', 'Lavado, secado y modelado', 45, 50000, true],
            ['peinado_evento', 'Peluquería', 'Peinado para eventos', 60, 120000, true],
            ['mechas', 'Colorimetría', 'Mechas y reflejos', 120, 250000, true],
            ['balayage', 'Colorimetría', 'Balayage', 180, 350000, true],
            ['depilacion_facial', 'Estética y maquillaje', 'Depilación facial', 20, 30000, true],
            ['depilacion_corporal', 'Estética y maquillaje', 'Depilación corporal', 45, 70000, true],
            ['limpieza_facial', 'Estética y maquillaje', 'Limpieza facial e hidratación', 60, 120000, true],
            ['maquillaje', 'Estética y maquillaje', 'Maquillaje social', 60, 150000, true],
        ];

        $servicios = [];
        foreach ($catalogo as [$clave, $categoria, $nombre, $minutos, $precio, $activo]) {
            $servicio = Servicio::firstOrCreate(['nombre' => $nombre], [
                'categoria_id' => $categorias[$categoria]->id,
                'duracion_minutos' => $minutos,
                'activo' => $activo,
            ]);

            if (!$servicio->precios()->exists()) {
                PrecioServicio::create([
                    'servicio_id' => $servicio->id, 'precio' => $precio,
                    'vigente_desde' => '2026-01-01', 'vigente_hasta' => null,
                ]);
            }

            $servicios[$clave] = $servicio;
        }

        return $servicios;
    }

    /**
     * @param array<string, Personal> $personal
     */
    protected function especialidades(array $personal): void
    {
        $especialidades = [];
        foreach ([
            'Peluquería' => 'Cortes, lavado, modelado y peinados',
            'Colorimetría' => 'Tintes, mechas, reflejos y balayage',
            'Peinados para eventos' => 'Peinados sociales y para eventos',
            'Maquillaje' => 'Maquillaje social y profesional',
            'Estética' => 'Depilación y tratamientos faciales básicos',
        ] as $nombre => $descripcion) {
            $especialidades[$nombre] = Especialidad::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }

        $asignaciones = [
            'monica' => ['Peluquería', 'Colorimetría', 'Maquillaje', 'Estética'],
            'carla' => ['Peluquería', 'Peinados para eventos', 'Maquillaje'],
        ];

        foreach ($asignaciones as $clave => $nombres) {
            $personal[$clave]->especialidades()->syncWithoutDetaching(
                collect($nombres)->map(fn ($nombre) => $especialidades[$nombre]->id)->all()
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\CategoriaServicio;
use App\Models\Descuento;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\MetodoPago;
use App\Models\PrecioServicio;
use App\Models\Role;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

/**
 * Datos reales con los que arranca el sistema en producción: roles, horario del salón, métodos de
 * pago, el catálogo de la lista de precios 2025 y la promo de los miércoles.
 *
 * No crea cuentas ni personal ni clientas: la administración se crea por terminal
 * (php artisan salon:crear-cuenta) y el resto se carga desde el panel.
 * Se puede correr más de una vez: cada registro se busca antes de crearlo.
 */
class ProduccionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'profesional', 'cliente'] as $rol) {
            Role::firstOrCreate(['nombre' => $rol]);
        }

        $this->horarioDelSalon();

        foreach (['Efectivo', 'Transferencia bancaria', 'Tarjeta de débito o crédito', 'Billetera electrónica'] as $metodo) {
            MetodoPago::firstOrCreate(['nombre' => $metodo]);
        }

        $this->catalogo();
        $this->promoDeLosMiercoles();
    }

    /**
     * Horario confirmado por la propietaria: lunes a viernes de 08:00 a 17:00.
     */
    protected function horarioDelSalon(): void
    {
        // Si ya hay horario cargado (o la dueña lo cambió desde el panel) no se toca
        if (HorarioAtencion::exists()) {
            return;
        }

        foreach (range(1, 5) as $dia) {
            HorarioAtencion::create(['dia_semana' => $dia, 'hora_apertura' => '08:00:00', 'hora_cierre' => '17:00:00']);
        }
    }

    /**
     * Lista de precios 2025 del salón. Las duraciones no figuran en la lista: son estimadas y la
     * dueña las ajusta desde el panel. Cuando la lista dice "a partir de", el precio es el mínimo
     * y la descripción lo aclara.
     */
    protected function catalogo(): void
    {
        // [nombre de la categoría, descripción, especialidad que pide]
        $lineas = [
            ['Uñas', 'Manicure, pedicure, uñas postizas, semipermanente y diseños', 'Uñas'],
            ['Planchita y ondas', 'Alisado con planchita y ondas según el largo del pelo, y trenzas', 'Peinados'],
            ['Maquillaje', 'Maquillaje natural y glam, diseño de cejas y pestañas', 'Maquillaje'],
            ['Capilar', 'Tintura, corte, lavado y tratamientos capilares', 'Capilar'],
        ];

        $categorias = [];
        $especialidades = [];
        foreach ($lineas as [$categoria, $descripcion, $especialidad]) {
            $categorias[$categoria] = CategoriaServicio::firstOrCreate(['nombre' => $categoria], ['descripcion' => $descripcion]);
            $especialidades[$categoria] = Especialidad::firstOrCreate(['nombre' => $especialidad], ['descripcion' => $descripcion]);
        }

        // [categoría, nombre, minutos, precio en guaraníes, descripción]
        $servicios = [
            ['Uñas', 'Manicure', 45, 40000, null],
            ['Uñas', 'Pedicure', 60, 60000, null],
            ['Uñas', 'Uñas postizas', 60, 20000, null],
            ['Uñas', 'Semipermanente', 60, 60000, null],
            ['Uñas', 'Extracción', 30, 20000, 'Retiro de uñas postizas o semipermanente'],
            ['Uñas', 'Diseño de uñas', 15, 5000, 'Precio por cada diseño'],

            ['Planchita y ondas', 'Planchita pelo corto', 30, 30000, null],
            ['Planchita y ondas', 'Planchita pelo medio', 45, 50000, null],
            ['Planchita y ondas', 'Planchita pelo largo', 60, 60000, null],
            ['Planchita y ondas', 'Ondas pelo corto', 45, 40000, null],
            ['Planchita y ondas', 'Ondas pelo medio', 60, 55000, null],
            ['Planchita y ondas', 'Ondas pelo largo', 75, 70000, null],
            ['Planchita y ondas', 'Trenzas', 45, 25000, 'Desde 25.000 hasta 45.000 según el peinado'],

            ['Maquillaje', 'Maquillaje natural', 60, 100000, null],
            ['Maquillaje', 'Maquillaje glam', 90, 120000, null],
            ['Maquillaje', 'Diseño de cejas con henna', 45, 50000, null],
            ['Maquillaje', 'Lifting de pestañas', 60, 90000, null],
            ['Maquillaje', 'Extensiones de pestañas', 120, 250000, null],

            ['Capilar', 'Tintura', 120, 90000, 'A partir de 90.000 según el largo y el color'],
            ['Capilar', 'Corte', 45, 30000, null],
            ['Capilar', 'Lavado', 30, 20000, null],
            ['Capilar', "Baño L'Oréal", 60, 80000, null],
            ['Capilar', 'Shock de keratina', 60, 80000, null],
            ['Capilar', 'Botox capilar', 90, 60000, null],
        ];

        foreach ($servicios as [$categoria, $nombre, $minutos, $precio, $descripcion]) {
            $servicio = Servicio::firstOrCreate(['nombre' => $nombre], [
                'categoria_id' => $categorias[$categoria]->id,
                'especialidad_id' => $especialidades[$categoria]->id,
                'descripcion' => $descripcion,
                'duracion_minutos' => $minutos,
                'activo' => true,
            ]);

            // El precio tiene historial: solo se carga el primero; los cambios se hacen desde el panel
            if (!$servicio->precios()->exists()) {
                PrecioServicio::create([
                    'servicio_id' => $servicio->id,
                    'precio' => $precio,
                    'vigente_desde' => '2025-01-01',
                    'vigente_hasta' => null,
                ]);
            }
        }
    }

    /**
     * Promo de los miércoles, tradición de las peluquerías. El porcentaje es un valor inicial:
     * la dueña lo cambia (o la desactiva) desde Promociones.
     */
    protected function promoDeLosMiercoles(): void
    {
        $promo = Descuento::firstOrCreate(
            ['nombre' => 'Promo miércoles'],
            ['tipo' => 'porcentaje', 'valor' => 10, 'activo' => true]
        );

        // ISO: 3 = miércoles. Con este día cargado la promo rige solo ese día de la semana
        $promo->dias()->firstOrCreate(['dia_semana' => 3]);
    }
}

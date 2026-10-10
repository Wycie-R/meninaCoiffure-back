
<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import Dropdown from '../../Components/Dropdown.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Pagination from '../../Components/Pagination.vue'

// ============================================================
// DATOS TEMPORALES DE DEMOSTRACIÓN
// Misma forma que cada elemento de `data` en GET /api/reservas
// (sin created_at ni updated_at). Clientes, personal, servicios y
// precios salen del seeder del backend.
// ============================================================

const ana = { id: 1, nombre: 'Ana', apellido: 'Gómez', telefono: '0981111222', email: 'ana.gomez@example.com' }
const beatriz = { id: 2, nombre: 'Beatriz', apellido: 'López', telefono: '0982333444', email: 'beatriz.lopez@example.com' }

const carla = { id: 1, nombre: 'Carla', apellido: 'Estilista', telefono: '0971555666', email: 'carla@meninacoiffure.com', fecha_ingreso: '2025-01-15', activo: true }
const diana = { id: 2, nombre: 'Diana', apellido: 'Colorista', telefono: '0972777888', email: 'diana@meninacoiffure.com', fecha_ingreso: '2025-03-01', activo: true }

const corte = { id: 1, categoria_id: 1, nombre: 'Corte Femenino', descripcion: null, duracion_minutos: 45, activo: true }
const nutricion = { id: 2, categoria_id: 1, nombre: 'Baño de Crema / Nutrición', descripcion: null, duracion_minutos: 30, activo: true }
const coloracion = { id: 3, categoria_id: 2, nombre: 'Coloración Completa', descripcion: null, duracion_minutos: 90, activo: true }

const precios = { 1: '70000.00', 2: '50000.00', 3: '180000.00' }

function reservaDemo(id, cliente, personal, fecha, inicio, fin, estado, servicios) {
    return {
        id,
        cliente_id: cliente.id,
        personal_id: personal.id,
        fecha,
        hora_inicio: `${inicio}:00`,
        hora_fin: `${fin}:00`,
        estado,
        descuento_id: null,
        cliente,
        personal,
        detalles: servicios.map((servicio, i) => ({
            id: id * 10 + i + 1,
            reserva_id: id,
            servicio_id: servicio.id,
            precio_aplicado: precios[servicio.id],
            cantidad: 1,
            agregado_en_el_momento: false,
            servicio,
        })),
    }
}

const reservas = [
    reservaDemo(1, ana, carla, '2026-09-28', '09:00', '09:45', 'completada', [corte]),
    reservaDemo(2, beatriz, diana, '2026-09-28', '10:00', '11:30', 'completada', [coloracion]),
    reservaDemo(3, ana, diana, '2026-09-30', '14:00', '14:30', 'cancelada', [nutricion]),
    reservaDemo(4, beatriz, carla, '2026-10-01', '11:00', '12:15', 'completada', [corte, nutricion]),
    reservaDemo(5, ana, carla, '2026-10-02', '15:00', '15:45', 'completada', [corte]),
    reservaDemo(6, ana, carla, '2026-10-05', '09:00', '09:45', 'completada', [corte]),
    reservaDemo(7, beatriz, diana, '2026-10-05', '09:30', '11:00', 'completada', [coloracion]),
    reservaDemo(8, ana, carla, '2026-10-05', '16:30', '17:00', 'cancelada', [nutricion]),
    reservaDemo(9, beatriz, carla, '2026-10-07', '10:00', '11:15', 'confirmada', [corte, nutricion]),
    reservaDemo(10, ana, diana, '2026-10-08', '13:00', '14:30', 'confirmada', [coloracion]),
    reservaDemo(11, beatriz, diana, '2026-10-09', '09:00', '09:30', 'pendiente', [nutricion]),
    reservaDemo(12, ana, carla, '2026-10-12', '10:00', '10:45', 'pendiente', [corte]),
    reservaDemo(13, beatriz, diana, '2026-10-13', '14:00', '15:30', 'pendiente', [coloracion]),
    reservaDemo(14, ana, carla, '2026-10-14', '16:00', '17:15', 'pendiente', [corte, nutricion]),
]

// ============================================================
// BÚSQUEDA, FILTRO Y PAGINACIÓN
// ============================================================

const porPagina = 6

const busqueda = ref('')
const estado = ref('todas')
const paginaActual = ref(1)

const opcionesEstado = [
    { label: 'Todas', value: 'todas' },
    { label: 'Pendientes', value: 'pendiente' },
    { label: 'Confirmadas', value: 'confirmada' },
    { label: 'Completadas', value: 'completada' },
    { label: 'Canceladas', value: 'cancelada' },
]

// Minúsculas y sin tildes: "gomez" encuentra "Gómez".
function normalizar(texto) {
    return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
}

// Más recientes primero; dentro del mismo día, por hora.
const reservasOrdenadas = [...reservas].sort(
    (a, b) => b.fecha.localeCompare(a.fecha) || a.hora_inicio.localeCompare(b.hora_inicio)
)

const reservasFiltradas = computed(() => {
    const texto = normalizar(busqueda.value)

    return reservasOrdenadas.filter((reserva) => {
        const coincideEstado = estado.value === 'todas' || reserva.estado === estado.value
        const nombreCliente = normalizar(`${reserva.cliente.nombre} ${reserva.cliente.apellido}`)

        return coincideEstado && nombreCliente.includes(texto)
    })
})

const totalPaginas = computed(() =>
    Math.max(1, Math.ceil(reservasFiltradas.value.length / porPagina))
)

const reservasPagina = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina
    return reservasFiltradas.value.slice(inicio, inicio + porPagina)
})

// Mismo formato que el arreglo `links` del paginador de Laravel.
const links = computed(() => {
    const actual = paginaActual.value
    const total = totalPaginas.value
    const url = (pagina) => `?page=${pagina}`

    const paginas = Array.from({ length: total }, (_, i) => ({
        url: url(i + 1),
        label: String(i + 1),
        page: i + 1,
        active: i + 1 === actual,
    }))

    return [
        {
            url: actual > 1 ? url(actual - 1) : null,
            label: '&laquo; Previous',
            page: actual > 1 ? actual - 1 : null,
            active: false,
        },
        ...paginas,
        {
            url: actual < total ? url(actual + 1) : null,
            label: 'Next &raquo;',
            page: actual < total ? actual + 1 : null,
            active: false,
        },
    ]
})

const resumen = computed(() => {
    const total = reservasFiltradas.value.length
    if (!total) return ''

    const desde = (paginaActual.value - 1) * porPagina + 1
    const hasta = desde + reservasPagina.value.length - 1
    return `Mostrando ${desde}–${hasta} de ${total} ${total === 1 ? 'reserva' : 'reservas'}`
})

// Al cambiar la búsqueda o el filtro, se vuelve a la primera página.
watch([busqueda, estado], () => {
    paginaActual.value = 1
})

// Pagination emite la URL; de ahí se toma el número de página.
function irAPagina(url) {
    const pagina = Number(new URL(url, window.location.origin).searchParams.get('page'))
    if (pagina >= 1 && pagina <= totalPaginas.value) paginaActual.value = pagina
}

// ============================================================
// FORMATO
// ============================================================

// Se arma con números para leer la fecha como hora local y no como UTC.
function fechaCorta(fecha) {
    const [anio, mes, dia] = fecha.split('-').map(Number)

    return new Date(anio, mes - 1, dia).toLocaleDateString('es-PY', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

function horario(reserva) {
    return `${reserva.hora_inicio.slice(0, 5)} – ${reserva.hora_fin.slice(0, 5)}`
}

function nombreCliente(reserva) {
    return `${reserva.cliente.nombre} ${reserva.cliente.apellido}`
}

function nombresServicios(reserva) {
    return reserva.detalles.map((detalle) => detalle.servicio.nombre).join(', ')
}
</script>

<template>
    <AppLayout>
        <div
            class="min-h-[calc(100vh-73px)] bg-[#1C1B1A] lg:min-h-[calc(100vh-69px)]"
        >
            <div class="mx-auto max-w-7xl px-6 py-8">

                <!-- Cabecera -->
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h1 class="text-2xl font-semibold text-[#F3ECE0]">
                        Reservas
                    </h1>

                        <Button label="+ Nueva reserva" />
                </div>

                <!-- Búsqueda y filtro -->
                <div
                    class="mt-6 flex flex-col gap-3 rounded-xl border border-[#47413B] bg-[#292725] p-4 sm:flex-row"
                >
                    <div class="sm:max-w-sm sm:flex-1">
                        <SearchInput
                            v-model="busqueda"
                            placeholder="Buscar por cliente..."
                        />
                    </div>

                    <div class="sm:w-52">
                        <Dropdown
                            v-model="estado"
                            :options="opcionesEstado"
                        />
                    </div>
                </div>

                <!-- Resultados -->
                <div class="mt-6">

                    <!-- Sin resultados -->
                    <div
                        v-if="!reservasFiltradas.length"
                        class="rounded-xl border border-[#47413B] bg-[#292725] px-5 py-12 text-center"
                    >
                        <p class="text-sm text-[#BEB3A6]">
                            No hay reservas que coincidan con la búsqueda.
                        </p>
                    </div>

                    <template v-else>

                        <!-- Tabla de escritorio -->
                        <div
                            class="hidden overflow-x-auto rounded-xl border border-[#47413B] bg-[#292725] lg:block"
                        >
                            <table class="w-full text-left text-sm">

                                <thead
                                    class="border-b border-[#51483F] bg-[#34302B] text-[#D4C3A3]"
                                >
                                    <tr>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Fecha
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Horario
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Cliente
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Servicios
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Profesional
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Estado
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-[#47413B]">
                                    <tr
                                        v-for="reserva in reservasPagina"
                                        :key="reserva.id"
                                        class="transition-colors hover:bg-[#302D29]"
                                    >
                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-[#F3ECE0] first-letter:uppercase"
                                        >
                                            {{ fechaCorta(reserva.fecha) }}
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-[#F3ECE0]"
                                        >
                                            {{ horario(reserva) }}
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-5 py-4 font-medium text-[#F3ECE0]"
                                        >
                                            {{ nombreCliente(reserva) }}
                                        </td>

                                        <td class="px-5 py-4 text-[#BEB3A6]">
                                            {{ nombresServicios(reserva) }}
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-5 py-4 text-[#BEB3A6]"
                                        >
                                            {{ reserva.personal.nombre }}
                                        </td>

                                        <td class="px-5 py-4">
                                            <StatusBadge :status="reserva.estado" />
                                        </td>
                                    </tr>
                                </tbody>

                            </table>
                        </div>

                        <!-- Tarjetas de tablet y móvil -->
                        <ul class="grid gap-3 sm:grid-cols-2 lg:hidden">
                            <li
                                v-for="reserva in reservasPagina"
                                :key="reserva.id"
                                class="rounded-xl border border-[#47413B] bg-[#292725] p-4 transition-colors hover:bg-[#302D29]"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p
                                            class="text-sm font-medium text-[#F3ECE0] first-letter:uppercase"
                                        >
                                            {{ fechaCorta(reserva.fecha) }}
                                        </p>

                                        <p class="text-sm text-[#BEB3A6]">
                                            {{ horario(reserva) }}
                                        </p>
                                    </div>

                                    <StatusBadge :status="reserva.estado" />
                                </div>

                                <div class="mt-3 border-t border-[#47413B] pt-3">
                                    <p class="text-sm font-medium text-[#F3ECE0]">
                                        {{ nombreCliente(reserva) }}
                                    </p>

                                    <p class="mt-1 text-sm text-[#BEB3A6]">
                                        {{ nombresServicios(reserva) }} ·
                                        {{ reserva.personal.nombre }}
                                    </p>
                                </div>
                            </li>
                        </ul>

                    </template>
                </div>

                <!-- Resumen y paginación -->
                <div
                    v-if="reservasFiltradas.length"
                    class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row"
                >
                    <p class="text-sm text-[#BEB3A6]">
                        {{ resumen }}
                    </p>

                    <Pagination
                        v-if="totalPaginas > 1"
                        :links="links"
                        @navigate="irAPagina"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>



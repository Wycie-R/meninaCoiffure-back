<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Pagination from '../../Components/Pagination.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Icono from '../../Components/Icono.vue'

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

const porPagina = 6

const busqueda = ref('')
const estado = ref('todas')
const paginaActual = ref(1)

const opcionesEstado = [
    { label: 'Todas', value: 'todas', punto: 'bg-[#D4C3A3]' },
    { label: 'Pendientes', value: 'pendiente', punto: 'bg-[#C9A96E]' },
    { label: 'Confirmadas', value: 'confirmada', punto: 'bg-[#9AD9AE]' },
    { label: 'Completadas', value: 'completada', punto: 'bg-[#7A6E61]' },
    { label: 'Canceladas', value: 'cancelada', punto: 'bg-[#F0A8A8]' },
]

function conteo(valor) {
    if (valor === 'todas') return reservas.length
    return reservas.filter((reserva) => reserva.estado === valor).length
}

function normalizar(texto) {
    return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
}

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

watch([busqueda, estado], () => {
    paginaActual.value = 1
})

function irAPagina(url) {
    const pagina = Number(new URL(url, window.location.origin).searchParams.get('page'))
    if (pagina >= 1 && pagina <= totalPaginas.value) paginaActual.value = pagina
}

function partesFecha(fecha) {
    const [anio, mes, dia] = fecha.split('-').map(Number)
    const objeto = new Date(anio, mes - 1, dia)

    return {
        dia,
        mes: objeto.toLocaleDateString('es-PY', { month: 'short' }).replace('.', ''),
        semana: objeto.toLocaleDateString('es-PY', { weekday: 'long' }),
    }
}

function iniciales(persona) {
    return `${persona.nombre[0] ?? ''}${persona.apellido[0] ?? ''}`.toUpperCase()
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
        <div class="mx-auto max-w-7xl px-6 py-8">
            <PageHeader
                seccion="Agenda"
                titulo="Reservas"
                descripcion="Todas las citas del salón, de la más reciente a la más antigua."
                icono="reservas"
            >
                <Button href="/reservas/crear">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nueva reserva
                </Button>
            </PageHeader>

            <div class="mt-6 rounded-xl border border-[#47413B] bg-[#292725] p-4">
                <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-center">
                    <div class="sm:max-w-sm 2xl:w-80">
                        <SearchInput v-model="busqueda" placeholder="Buscar por cliente..." />
                    </div>

                    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
                        <button
                            v-for="opcion in opcionesEstado"
                            :key="opcion.value"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3]"
                            :class="
                                estado === opcion.value
                                    ? 'border-[#C9A96E] bg-[#403629] text-[#F3ECE0]'
                                    : 'border-[#5B5147] bg-[#34302B] text-[#BEB3A6] hover:border-[#8C7961] hover:text-[#F3ECE0]'
                            "
                            :aria-pressed="estado === opcion.value"
                            @click="estado = opcion.value"
                        >
                            <span class="h-2 w-2 rounded-full" :class="opcion.punto" aria-hidden="true"></span>
                            {{ opcion.label }}
                            <span
                                class="rounded-full px-1.5 text-xs font-semibold lining-nums"
                                :class="estado === opcion.value ? 'bg-[#C9A96E] text-[#1C1B1A]' : 'bg-[#292725] text-[#BEB3A6]'"
                            >
                                {{ conteo(opcion.value) }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <EstadoVacio
                    v-if="!reservasFiltradas.length"
                    icono="buscar"
                    titulo="Sin coincidencias"
                    descripcion="No hay reservas que coincidan con la búsqueda o el estado seleccionado."
                >
                    <Button label="Limpiar filtros" variant="secondary" @click="busqueda = ''; estado = 'todas'" />
                </EstadoVacio>

                <template v-else>
                    <div class="hidden overflow-x-auto rounded-xl border border-[#47413B] bg-[#292725] lg:block">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-[#5B5147] bg-gradient-to-r from-[#3A3229] to-[#34302B] text-[#E0CDAF]">
                                <tr>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Fecha</th>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Horario</th>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Cliente</th>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Servicios</th>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Profesional</th>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Estado</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-[#3D3934]">
                                <tr
                                    v-for="reserva in reservasPagina"
                                    :key="reserva.id"
                                    class="group transition-colors hover:bg-[#2E2B28]"
                                    :class="{ 'opacity-60': reserva.estado === 'cancelada' }"
                                >
                                    <td class="relative whitespace-nowrap px-5 py-4">
                                        <span class="absolute inset-y-3 left-0 w-0.5 rounded-full bg-[#C9A96E] opacity-0 transition-opacity group-hover:opacity-100"></span>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-lg border border-[#5B5147] bg-[#1F1D1B]">
                                                <span class="font-serif text-xl font-semibold leading-none lining-nums text-[#F3ECE0]">
                                                    {{ partesFecha(reserva.fecha).dia }}
                                                </span>
                                                <span class="mt-0.5 text-[0.625rem] font-medium uppercase tracking-wider text-[#C9A96E]">
                                                    {{ partesFecha(reserva.fecha).mes }}
                                                </span>
                                            </span>
                                            <span class="text-sm capitalize text-[#BEB3A6]">
                                                {{ partesFecha(reserva.fecha).semana }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4 font-serif text-lg font-semibold lining-nums text-[#E0CDAF]">
                                        {{ horario(reserva) }}
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#403629] text-xs font-semibold text-[#E0CDAF]">
                                                {{ iniciales(reserva.cliente) }}
                                            </span>
                                            <span class="font-medium text-[#F3ECE0]">{{ nombreCliente(reserva) }}</span>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="detalle in reserva.detalles"
                                                :key="detalle.id"
                                                class="rounded-md border border-[#47413B] bg-[#34302B] px-2 py-0.5 text-xs text-[#E0CDAF]"
                                            >
                                                {{ detalle.servicio.nombre }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4">
                                        <div class="flex items-center gap-2 text-[#BEB3A6]">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full border border-[#C9A96E]/60 font-serif text-xs font-semibold text-[#E9D2A0]">
                                                {{ iniciales(reserva.personal) }}
                                            </span>
                                            {{ reserva.personal.nombre }}
                                        </div>
                                    </td>

                                    <td class="px-5 py-4">
                                        <StatusBadge :status="reserva.estado" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <ul class="grid gap-3 sm:grid-cols-2 lg:hidden">
                        <li
                            v-for="reserva in reservasPagina"
                            :key="reserva.id"
                            class="flex gap-4 rounded-xl border border-[#47413B] bg-[#292725] p-4 transition-colors hover:border-[#C9A96E]/50"
                            :class="{ 'opacity-60': reserva.estado === 'cancelada' }"
                        >
                            <span class="flex h-16 w-14 shrink-0 flex-col items-center justify-center rounded-lg border border-[#5B5147] bg-[#1F1D1B]">
                                <span class="font-serif text-2xl font-semibold leading-none lining-nums text-[#F3ECE0]">
                                    {{ partesFecha(reserva.fecha).dia }}
                                </span>
                                <span class="mt-1 text-[0.625rem] font-medium uppercase tracking-wider text-[#C9A96E]">
                                    {{ partesFecha(reserva.fecha).mes }}
                                </span>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-serif text-lg font-semibold leading-tight lining-nums text-[#E0CDAF]">
                                            {{ horario(reserva) }}
                                        </p>
                                        <p class="text-xs capitalize text-[#8C7F70]">{{ partesFecha(reserva.fecha).semana }}</p>
                                    </div>
                                    <StatusBadge :status="reserva.estado" />
                                </div>

                                <p class="mt-2 text-sm font-medium text-[#F3ECE0]">{{ nombreCliente(reserva) }}</p>
                                <p class="mt-0.5 text-sm text-[#BEB3A6]">
                                    {{ nombresServicios(reserva) }} · {{ reserva.personal.nombre }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </template>
            </div>

            <div
                v-if="reservasFiltradas.length"
                class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row"
            >
                <p class="font-serif text-base italic lining-nums text-[#BEB3A6]">
                    {{ resumen }}
                </p>

                <Pagination v-if="totalPaginas > 1" :links="links" @navigate="irAPagina" />
            </div>
        </div>
    </AppLayout>
</template>

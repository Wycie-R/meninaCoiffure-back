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
import { guaranies } from '../../formato'

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

const porPagina = 8

const busqueda = ref('')
const estado = ref('todas')
const paginaActual = ref(1)

const opcionesEstado = [
    { label: 'Todas', value: 'todas', tinte: 'bg-bruma' },
    { label: 'Pendientes', value: 'pendiente', tinte: 'bg-miel' },
    { label: 'Confirmadas', value: 'confirmada', tinte: 'bg-menta' },
    { label: 'Completadas', value: 'completada', tinte: 'bg-ceniza' },
    { label: 'Canceladas', value: 'cancelada', tinte: 'bg-borgona' },
]

const tinteEstado = {
    pendiente: 'bg-miel',
    confirmada: 'bg-menta',
    completada: 'bg-ceniza',
    cancelada: 'bg-borgona',
}

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
        return coincideEstado && normalizar(nombreCliente(reserva)).includes(texto)
    })
})

const totalPaginas = computed(() => Math.max(1, Math.ceil(reservasFiltradas.value.length / porPagina)))

const reservasPagina = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina
    return reservasFiltradas.value.slice(inicio, inicio + porPagina)
})

const dias = computed(() => {
    const grupos = new Map()

    for (const reserva of reservasPagina.value) {
        if (!grupos.has(reserva.fecha)) grupos.set(reserva.fecha, [])
        grupos.get(reserva.fecha).push(reserva)
    }

    return [...grupos.entries()].map(([fecha, lista]) => ({ fecha, ...partesFecha(fecha), reservas: lista }))
})

const links = computed(() => {
    const actual = paginaActual.value
    const total = totalPaginas.value
    const url = (pagina) => `?page=${pagina}`

    return [
        { url: actual > 1 ? url(actual - 1) : null, label: '&laquo; Previous', active: false },
        ...Array.from({ length: total }, (_, i) => ({ url: url(i + 1), label: String(i + 1), active: i + 1 === actual })),
        { url: actual < total ? url(actual + 1) : null, label: 'Next &raquo;', active: false },
    ]
})

const resumen = computed(() => {
    const pendientes = conteo('pendiente')
    const proximas = reservas.filter((r) => ['pendiente', 'confirmada'].includes(r.estado)).length
    return `${proximas} citas por atender, ${pendientes} de ellas sin confirmar.`
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
        semana: objeto.toLocaleDateString('es-PY', { weekday: 'long' }),
        mes: objeto.toLocaleDateString('es-PY', { month: 'long' }),
    }
}

function hora(valor) {
    return valor.slice(0, 5)
}

function nombreCliente(reserva) {
    return `${reserva.cliente.nombre} ${reserva.cliente.apellido}`
}

function totalReserva(reserva) {
    return reserva.detalles.reduce((total, d) => total + Number(d.precio_aplicado) * d.cantidad, 0)
}

function limpiarFiltros() {
    busqueda.value = ''
    estado.value = 'todas'
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader titulo="Agenda de reservas" :descripcion="resumen">
                <Button href="/reservas/crear">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nueva reserva
                </Button>
            </PageHeader>

            <div class="mt-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
                    <button
                        v-for="opcion in opcionesEstado"
                        :key="opcion.value"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                        :class="
                            estado === opcion.value
                                ? 'border-orquidea bg-orquidea/12 text-orquidea-claro'
                                : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla'
                        "
                        :aria-pressed="estado === opcion.value"
                        @click="estado = opcion.value"
                    >
                        <span class="h-3 w-1.5 rounded-full" :class="opcion.tinte" aria-hidden="true"></span>
                        {{ opcion.label }}
                        <span class="tabular-nums text-niebla">{{ conteo(opcion.value) }}</span>
                    </button>
                </div>

                <div class="lg:w-72">
                    <SearchInput v-model="busqueda" placeholder="Buscar por cliente..." />
                </div>
            </div>

            <EstadoVacio
                v-if="!reservasFiltradas.length"
                class="mt-10"
                titulo="Ninguna reserva coincide"
                descripcion="Prueba con otro nombre o vuelve a mostrar todos los estados."
            >
                <Button label="Ver todas las reservas" variant="secondary" @click="limpiarFiltros" />
            </EstadoVacio>

            <div v-else class="mt-10 space-y-10">
                <section
                    v-for="dia in dias"
                    :key="dia.fecha"
                    class="grid gap-4 md:grid-cols-[8rem_minmax(0,1fr)] md:gap-8"
                    :aria-label="`${dia.semana} ${dia.dia} de ${dia.mes}`"
                >
                    <div class="flex items-baseline gap-3 md:block">
                        <p class="font-display text-5xl leading-none tabular-nums">{{ dia.dia }}</p>
                        <p class="text-sm capitalize text-bruma md:mt-2">{{ dia.semana }}</p>
                        <p class="text-sm text-niebla">{{ dia.mes }}</p>
                    </div>

                    <ol class="space-y-2">
                        <li
                            v-for="reserva in dia.reservas"
                            :key="reserva.id"
                            class="group relative flex flex-wrap items-center gap-x-5 gap-y-2 overflow-hidden rounded-2xl bg-velo py-4 pl-6 pr-4 transition-colors hover:bg-terciopelo"
                            :class="{ 'opacity-60': reserva.estado === 'cancelada' }"
                        >
                            <span class="absolute inset-y-3 left-2 w-1.5 rounded-full" :class="tinteEstado[reserva.estado]" aria-hidden="true"></span>

                            <p class="w-36 shrink-0 whitespace-nowrap tabular-nums">
                                <span class="font-display text-2xl">{{ hora(reserva.hora_inicio) }}</span>
                                <span class="ml-1 text-sm text-niebla">a {{ hora(reserva.hora_fin) }}</span>
                            </p>

                            <div class="order-3 w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                                <p class="truncate font-medium">{{ nombreCliente(reserva) }}</p>
                                <p class="truncate text-sm text-bruma">
                                    {{ reserva.detalles.map((d) => d.servicio.nombre).join(', ') }}
                                </p>
                            </div>

                            <div class="hidden w-28 shrink-0 items-center gap-2 text-sm text-bruma lg:flex">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-raso font-display text-xs text-orquidea-claro">
                                    {{ reserva.personal.nombre[0] }}{{ reserva.personal.apellido[0] }}
                                </span>
                                {{ reserva.personal.nombre }}
                            </div>

                            <p class="hidden w-28 shrink-0 text-right text-sm tabular-nums text-bruma md:block">
                                {{ guaranies(totalReserva(reserva)) }}
                            </p>

                            <StatusBadge :status="reserva.estado" class="ml-auto sm:ml-0" />
                        </li>
                    </ol>
                </section>
            </div>

            <div
                v-if="reservasFiltradas.length && totalPaginas > 1"
                class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-hilo pt-6 sm:flex-row"
            >
                <p class="text-sm tabular-nums text-niebla">
                    Página {{ paginaActual }} de {{ totalPaginas }}, {{ reservasFiltradas.length }} reservas en total
                </p>
                <Pagination :links="links" @navigate="irAPagina" />
            </div>
        </div>
    </AppLayout>
</template>

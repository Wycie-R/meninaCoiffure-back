<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Pagination from '../../Components/Pagination.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Icono from '../../Components/Icono.vue'
import Dropdown from '../../Components/Dropdown.vue'
import Campo from '../../Components/Campo.vue'
import Aviso from '../../Components/Aviso.vue'
import PanelReserva from '../../Components/PanelReserva.vue'
import { aFecha, aISO, fechaLarga, guaranies, hora, plural } from '../../formato'
import { claseEntrada, claseEnfoque } from '../../ui'
import { useReservas } from '../../reservas'
import { usePermisos } from '../../auth'
import { ATAJOS_PERIODO, rangoDe, atajoDeRango } from '../../rangos'
import {
    aDomicilio,
    agruparPorDia,
    descartada,
    dia as diaDe,
    filtrarBase,
    fueraDeHorario,
    minutosTraslado,
    montosReserva,
    nombreCliente,
    nombrePersonal,
    nombresServicios,
    ordenar,
    porAtender,
    sinCerrar,
    textoTraslado,
} from '../../agenda'

const { reservas, cargando, cargado, error, cargarReservas } = useReservas()
const { esProfesional, puedeCrearReservas } = usePermisos()

const page = usePage()
const reservaNueva = Number(new URL(page.url, window.location.origin).searchParams.get('nueva')) || null

const diasPorPagina = 7

const ahora = ref(new Date())
let reloj = null

function avisarNueva() {
    const nueva = reservaNueva && reservas.value.find((r) => r.id === reservaNueva)
    if (!nueva) return false
    aviso.value = `Reserva de ${nombreCliente(nueva)} agendada para el ${fechaLarga(nueva.fecha)} a las ${hora(nueva.hora_inicio)}.`
    return true
}

onMounted(async () => {
    reloj = setInterval(() => {
        ahora.value = new Date()
    }, 30000)

    // Lo que ya está en memoria se muestra al instante; la recarga corre en segundo plano.
    const avisada = avisarNueva()
    await cargarReservas({ forzar: true })
    if (!avisada) avisarNueva()
})

onBeforeUnmount(() => clearInterval(reloj))

const hoy = computed(() => aISO(ahora.value))
const manana = computed(() => aISO(new Date(ahora.value.getFullYear(), ahora.value.getMonth(), ahora.value.getDate() + 1)))

const vista = ref('proximas')
const estado = ref('todas')
const personalId = ref('todos')
const desde = ref('')
const hasta = ref('')
const busqueda = ref('')
const paginaActual = ref(1)
const reservaAbierta = ref(null)
const aviso = ref('')
const filtrosAbiertos = ref(false)

const opcionesEstado = [
    { label: 'Todas', value: 'todas', tinte: 'bg-bruma' },
    { label: 'Pendientes', value: 'pendiente', tinte: 'bg-miel' },
    { label: 'Confirmadas', value: 'confirmada', tinte: 'bg-menta' },
    { label: 'Completadas', value: 'completada', tinte: 'bg-ceniza' },
    { label: 'Canceladas', value: 'cancelada', tinte: 'bg-borgona' },
    { label: 'No asistió', value: 'no_asistio', tinte: 'bg-niebla' },
]

const tinteEstado = {
    pendiente: 'bg-miel',
    confirmada: 'bg-menta',
    completada: 'bg-ceniza',
    cancelada: 'bg-borgona',
    no_asistio: 'bg-niebla',
}

const opcionesVista = computed(() => [
    { label: 'Próximas', value: 'proximas', total: reservas.value.filter((r) => diaDe(r) >= hoy.value).length },
    { label: 'Pasadas', value: 'pasadas', total: reservas.value.filter((r) => diaDe(r) < hoy.value).length },
    { label: 'Todas', value: 'todas', total: reservas.value.length },
])

const opcionesPersonal = computed(() => {
    const equipo = new Map()

    for (const reserva of reservas.value) {
        if (reserva.personal && !equipo.has(reserva.personal_id)) equipo.set(reserva.personal_id, nombrePersonal(reserva))
    }

    const ordenado = [...equipo.entries()]
        .sort((a, b) => a[1].localeCompare(b[1], 'es'))
        .map(([value, label]) => ({ value, label }))

    return [{ value: 'todos', label: 'Todo el equipo' }, ...ordenado]
})

const rangoInvalido = computed(() => Boolean(desde.value && hasta.value && desde.value > hasta.value))

// ---------- Atajos de período ----------

// En la agenda "Este mes" llega al último día del mes: también interesan las citas que vienen
const OPCIONES_RANGO = { hastaFinDeMes: true }

const atajoActivo = computed(() => atajoDeRango(desde.value, hasta.value, OPCIONES_RANGO))
const sinFechas = computed(() => !desde.value && !hasta.value)

function elegirAtajo(clave) {
    const rango = rangoDe(clave, OPCIONES_RANGO)
    desde.value = rango.desde
    hasta.value = rango.hasta
}

function quitarFechas() {
    desde.value = ''
    hasta.value = ''
}

function clasePeriodo(activo) {
    if (activo) return 'border-orquidea bg-orquidea/12 text-orquidea-claro'
    return 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla'
}

const base = computed(() =>
    filtrarBase(
        reservas.value,
        {
            vista: vista.value,
            personalId: personalId.value,
            desde: desde.value,
            hasta: hasta.value,
            texto: busqueda.value,
        },
        hoy.value
    )
)

const filtradas = computed(() => {
    const lista = estado.value === 'todas' ? base.value : base.value.filter((r) => r.estado === estado.value)
    return ordenar(lista, vista.value)
})

function conteo(valor) {
    if (valor === 'todas') return base.value.length
    return base.value.filter((reserva) => reserva.estado === valor).length
}

const diasAgrupados = computed(() => agruparPorDia(filtradas.value))
const totalPaginas = computed(() => Math.max(1, Math.ceil(diasAgrupados.value.length / diasPorPagina)))

const dias = computed(() => {
    const inicio = (paginaActual.value - 1) * diasPorPagina

    return diasAgrupados.value.slice(inicio, inicio + diasPorPagina).map((grupo) => ({
        ...grupo,
        ...partesFecha(grupo.fecha),
    }))
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
    if (!reservas.value.length) return 'Todavía no hay reservas registradas.'

    const pendientes = reservas.value.filter((r) => porAtender(r, ahora.value))
    const sinConfirmar = pendientes.filter((r) => r.estado === 'pendiente').length
    const abiertas = reservas.value.filter((r) => sinCerrar(r, ahora.value)).length

    const partes = []

    if (pendientes.length) {
        partes.push(
            `${plural(pendientes.length, 'cita por atender', 'citas por atender')}, ${
                sinConfirmar ? `${sinConfirmar} de ellas sin confirmar` : 'todas confirmadas'
            }.`
        )
    } else {
        partes.push('No hay citas por atender.')
    }

    if (abiertas) partes.push(`${plural(abiertas, 'cita pasada sigue', 'citas pasadas siguen')} sin cerrar.`)

    return partes.join(' ')
})

const filtrosSecundarios = computed(() => {
    let cantidad = 0
    if (personalId.value !== 'todos') cantidad++
    // Las fechas de un atajo ya se ven en la fila de período; solo cuentan las escritas a mano
    if ((desde.value || hasta.value) && !atajoActivo.value) cantidad++
    return cantidad
})

const descripcion = computed(() => {
    if (!cargado.value && !error.value) return 'Cargando las reservas…'
    if (error.value && !reservas.value.length) return ''
    return resumen.value
})

const hayFiltros = computed(
    () =>
        estado.value !== 'todas' ||
        personalId.value !== 'todos' ||
        Boolean(desde.value) ||
        Boolean(hasta.value) ||
        Boolean(busqueda.value.trim())
)

watch([vista, estado, personalId, desde, hasta, busqueda], () => {
    paginaActual.value = 1
})

watch([desde, hasta], ([nuevoDesde, nuevoHasta]) => {
    if ((nuevoDesde || nuevoHasta) && vista.value !== 'todas') vista.value = 'todas'
})

watch(totalPaginas, (total) => {
    if (paginaActual.value > total) paginaActual.value = total
})

function irAPagina(url) {
    const pagina = Number(new URL(url, window.location.origin).searchParams.get('page'))
    if (pagina >= 1 && pagina <= totalPaginas.value) paginaActual.value = pagina
}

function partesFecha(fecha) {
    const objeto = aFecha(fecha)
    const mismoAnio = objeto.getFullYear() === ahora.value.getFullYear()
    const mes = objeto.toLocaleDateString('es-PY', { month: 'long' })

    return {
        numero: objeto.getDate(),
        semana: objeto.toLocaleDateString('es-PY', { weekday: 'long' }),
        mes: mismoAnio ? mes : `${mes} ${objeto.getFullYear()}`,
        relativo: fecha === hoy.value ? 'Hoy' : fecha === manana.value ? 'Mañana' : '',
    }
}

function limpiarFiltros() {
    estado.value = 'todas'
    personalId.value = 'todos'
    desde.value = ''
    hasta.value = ''
    busqueda.value = ''
}

function verTodas() {
    limpiarFiltros()
    vista.value = 'todas'
}

function abrir(reserva) {
    reservaAbierta.value = reserva.id
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            <PageHeader :titulo="esProfesional ? 'Tu agenda' : 'Agenda de reservas'" :descripcion="descripcion">
                <Button variant="secondary" :disabled="cargando" @click="cargarReservas({ forzar: true })">
                    {{ cargando && reservas.length ? 'Actualizando…' : 'Actualizar' }}
                </Button>
                <Button v-if="puedeCrearReservas" href="/reservas/crear">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nueva reserva
                </Button>
            </PageHeader>

            <template v-if="reservas.length">
                <div class="mt-6 flex flex-col gap-3 sm:mt-8 lg:flex-row lg:items-center lg:justify-between lg:gap-4">
                    <!-- En celular las tres pestañas ocupan todo el ancho, con toques cómodos -->
                    <div class="flex w-full gap-1 rounded-full border border-hilo bg-velo p-1 sm:w-fit" role="group" aria-label="Elegir qué reservas ver">
                        <button
                            v-for="opcion in opcionesVista"
                            :key="opcion.value"
                            type="button"
                            class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-full px-3 py-1.5 text-sm transition-colors sm:flex-none sm:px-4"
                            :class="[
                                claseEnfoque,
                                vista === opcion.value ? 'bg-raso text-perla' : 'text-bruma hover:text-perla',
                            ]"
                            :aria-pressed="vista === opcion.value"
                            @click="vista = opcion.value"
                        >
                            {{ opcion.label }}
                            <span class="tabular-nums text-niebla">{{ opcion.total }}</span>
                        </button>
                    </div>

                    <div class="lg:w-80">
                        <SearchInput v-model="busqueda" placeholder="Buscar cliente o servicio..." />
                    </div>
                </div>

                <!-- Período: atajos rápidos; las fechas exactas siguen en "Más filtros".
                     En celular es una fila que se desliza de costado, para no ocupar varios renglones -->
                <div class="relative mt-4 sm:mt-5">
                    <div
                        class="-mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0 [&::-webkit-scrollbar]:hidden"
                        role="group"
                        aria-label="Elegir período"
                    >
                    <span class="mr-1 shrink-0 text-sm text-niebla">Período</span>
                    <button
                        type="button"
                        class="min-h-10 shrink-0 whitespace-nowrap rounded-full border px-4 py-2 text-sm transition-colors"
                        :class="[claseEnfoque, clasePeriodo(sinFechas)]"
                        :aria-pressed="sinFechas"
                        @click="quitarFechas"
                    >
                        Todas las fechas
                    </button>
                    <button
                        v-for="atajo in ATAJOS_PERIODO"
                        :key="atajo.clave"
                        type="button"
                        class="min-h-10 shrink-0 whitespace-nowrap rounded-full border px-4 py-2 text-sm transition-colors"
                        :class="[claseEnfoque, clasePeriodo(atajoActivo === atajo.clave)]"
                        :aria-pressed="atajoActivo === atajo.clave"
                        @click="elegirAtajo(atajo.clave)"
                    >
                        {{ atajo.etiqueta }}
                    </button>
                    <span v-if="!sinFechas && !atajoActivo" class="shrink-0 whitespace-nowrap text-sm text-niebla">Fechas elegidas en "Más filtros"</span>
                    </div>
                    <!-- Degradado en el borde: indica que hay más opciones deslizando -->
                    <div class="pointer-events-none absolute inset-y-0 -right-4 w-10 bg-linear-to-l from-noche to-transparent sm:hidden" aria-hidden="true"></div>
                </div>

                <div class="mt-3 flex flex-col gap-3 sm:mt-4 sm:gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="relative min-w-0">
                        <div
                            class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0 [&::-webkit-scrollbar]:hidden"
                            role="group"
                            aria-label="Filtrar por estado"
                        >
                        <button
                            v-for="opcion in opcionesEstado"
                            :key="opcion.value"
                            type="button"
                            class="inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-4 py-2 text-sm transition-colors"
                            :class="[
                                claseEnfoque,
                                estado === opcion.value
                                    ? 'border-orquidea bg-orquidea/12 text-orquidea-claro'
                                    : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                            ]"
                            :aria-pressed="estado === opcion.value"
                            @click="estado = opcion.value"
                        >
                            <span class="h-3 w-1.5 rounded-full" :class="opcion.tinte" aria-hidden="true"></span>
                            {{ opcion.label }}
                            <span class="tabular-nums text-niebla">{{ conteo(opcion.value) }}</span>
                        </button>
                        </div>
                        <div class="pointer-events-none absolute inset-y-0 -right-4 w-10 bg-linear-to-l from-noche to-transparent sm:hidden" aria-hidden="true"></div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Button v-if="hayFiltros" label="Quitar filtros" variant="secondary" @click="limpiarFiltros" />
                        <Button
                            variant="secondary"
                            aria-controls="filtros-adicionales"
                            :aria-expanded="filtrosAbiertos"
                            @click="filtrosAbiertos = !filtrosAbiertos"
                        >
                            <Icono name="filtros" class="h-4 w-4" />
                            Más filtros
                            <span
                                v-if="filtrosSecundarios"
                                class="flex h-5 min-w-5 items-center justify-center rounded-full bg-orquidea px-1.5 text-xs font-semibold text-noche"
                            >
                                {{ filtrosSecundarios }}
                            </span>
                        </Button>
                    </div>
                </div>

                <div v-show="filtrosAbiertos" id="filtros-adicionales" class="mt-4 grid gap-4 sm:grid-cols-3 sm:items-start">
                    <Campo v-if="!esProfesional" etiqueta="Profesional">
                        <Dropdown v-model="personalId" :options="opcionesPersonal" placeholder="Todo el equipo" />
                    </Campo>

                    <Campo etiqueta="Desde" para="filtro-desde">
                        <input id="filtro-desde" v-model="desde" type="date" :class="claseEntrada(false, true)" />
                    </Campo>

                    <Campo
                        etiqueta="Hasta"
                        para="filtro-hasta"
                        :error="rangoInvalido ? 'La fecha final no puede ser anterior a la inicial.' : ''"
                    >
                        <input id="filtro-hasta" v-model="hasta" type="date" :class="claseEntrada(rangoInvalido, true)" />
                    </Campo>
                </div>
            </template>

            <div v-if="!cargado && !error" class="mt-10 space-y-3" aria-busy="true" aria-label="Cargando las reservas">
                <div v-for="n in 4" :key="n" class="h-[4.5rem] animate-pulse rounded-2xl bg-velo"></div>
            </div>

            <EstadoVacio
                v-else-if="error && !reservas.length"
                class="mt-10"
                tono="error"
                titulo="No se pudieron cargar las reservas"
                :descripcion="error"
                role="alert"
            >
                <Button label="Reintentar" variant="secondary" @click="cargarReservas({ forzar: true })" />
            </EstadoVacio>

            <EstadoVacio
                v-else-if="!reservas.length"
                class="mt-10"
                titulo="Todavía no hay reservas"
                descripcion="Cuando se registre la primera cita, va a aparecer aquí agrupada por día."
            >
                <Button v-if="puedeCrearReservas" href="/reservas/crear" label="Nueva reserva" />
            </EstadoVacio>

            <EstadoVacio
                v-else-if="!filtradas.length"
                class="mt-10"
                titulo="Ninguna reserva coincide"
                :descripcion="
                    hayFiltros || vista !== 'todas'
                        ? 'Prueba con otros filtros o muestra todas las reservas.'
                        : 'No hay reservas para mostrar.'
                "
            >
                <Button label="Ver todas las reservas" variant="secondary" @click="verTodas" />
            </EstadoVacio>

            <div v-else class="mt-8 sm:mt-10">
                <p v-if="error" role="alert" class="mb-6 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                    {{ error }} Se muestran los últimos datos cargados.
                </p>

                <div class="space-y-8 sm:space-y-10">
                    <section
                        v-for="grupo in dias"
                        :key="grupo.fecha"
                        class="grid gap-3 md:grid-cols-[8rem_minmax(0,1fr)] md:gap-8"
                        :aria-label="`${grupo.semana} ${grupo.numero} de ${grupo.mes}`"
                    >
                        <div class="flex items-baseline gap-3 md:block">
                            <p class="font-display text-5xl leading-none tabular-nums">{{ grupo.numero }}</p>
                            <p class="text-sm capitalize text-bruma md:mt-2">{{ grupo.semana }}</p>
                            <p class="text-sm text-niebla">{{ grupo.mes }}</p>
                            <p
                                v-if="grupo.relativo"
                                class="rounded-full bg-orquidea/12 px-2.5 py-0.5 text-xs font-medium text-orquidea-claro md:mt-3 md:w-fit"
                            >
                                {{ grupo.relativo }}
                            </p>
                        </div>

                        <ol class="space-y-2">
                            <li v-for="reserva in grupo.reservas" :key="reserva.id">
                                <button
                                    type="button"
                                    class="group relative flex w-full flex-wrap items-center gap-x-4 gap-y-2 overflow-hidden rounded-2xl bg-velo py-4 pl-6 pr-4 text-left transition-colors hover:bg-terciopelo sm:gap-x-5"
                                    :class="[
                                        claseEnfoque,
                                        reserva.id === reservaAbierta ? 'ring-1 ring-orquidea/50' : '',
                                    ]"
                                    @click="abrir(reserva)"
                                >
                                    <span class="absolute inset-y-3 left-2 w-1.5 rounded-full" :class="tinteEstado[reserva.estado]" aria-hidden="true"></span>

                                    <span class="shrink-0 whitespace-nowrap tabular-nums sm:w-36">
                                        <span class="block">
                                            <span class="font-display text-2xl" :class="{ 'text-bruma': descartada(reserva) }">{{ hora(reserva.hora_inicio) }}</span>
                                            <span class="ml-1 text-sm text-niebla">a {{ hora(reserva.hora_fin) }}</span>
                                        </span>
                                        <span v-if="sinCerrar(reserva, ahora)" class="mt-0.5 flex items-center gap-1 text-xs text-miel">
                                            <Icono name="alerta" class="h-3.5 w-3.5" />
                                            Sin cerrar
                                        </span>
                                    </span>

                                    <span class="order-3 block w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                                        <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="min-w-0 max-w-full truncate font-medium" :class="{ 'text-bruma': descartada(reserva) }" :title="nombreCliente(reserva)">{{ nombreCliente(reserva) }}</span>
                                            <span
                                                v-if="aDomicilio(reserva)"
                                                class="inline-flex min-w-0 items-center gap-1 overflow-hidden rounded-full bg-orquidea/12 px-2 py-0.5 text-xs text-orquidea-claro"
                                                :title="textoTraslado(reserva) || undefined"
                                            >
                                                <Icono name="casa" class="h-3.5 w-3.5 shrink-0" />
                                                <span class="shrink-0">A domicilio</span>
                                                <span v-if="minutosTraslado(reserva)" class="hidden truncate sm:inline">
                                                    · +{{ minutosTraslado(reserva) }} min de traslado
                                                </span>
                                            </span>
                                            <span
                                                v-if="fueraDeHorario(reserva)"
                                                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-miel/12 px-2 py-0.5 text-xs text-miel"
                                                title="Autorizada fuera del horario del salón"
                                            >
                                                <Icono name="pendiente" class="h-3.5 w-3.5" />
                                                Fuera de horario
                                            </span>
                                        </span>
                                        <span class="block truncate text-sm" :class="descartada(reserva) ? 'text-niebla' : 'text-bruma'">
                                            {{ nombresServicios(reserva).join(', ') || 'Sin servicios' }}
                                        </span>
                                    </span>

                                    <span class="hidden w-28 shrink-0 items-center gap-2 text-sm lg:flex" :class="descartada(reserva) ? 'text-niebla' : 'text-bruma'">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-raso font-display text-xs text-orquidea-claro" aria-hidden="true">
                                            {{ (reserva.personal?.nombre ?? '?')[0] }}{{ (reserva.personal?.apellido ?? '')[0] ?? '' }}
                                        </span>
                                        {{ reserva.personal?.nombre ?? 'Sin asignar' }}
                                    </span>

                                    <span class="hidden w-28 shrink-0 text-right text-sm tabular-nums md:block" :class="descartada(reserva) ? 'text-niebla' : 'text-bruma'">
                                        {{ guaranies(montosReserva(reserva).total) }}
                                    </span>

                                    <span class="ml-auto flex shrink-0 justify-end sm:ml-0 sm:w-28">
                                        <StatusBadge :status="reserva.estado" />
                                    </span>
                                </button>
                            </li>
                        </ol>
                    </section>
                </div>

                <div
                    v-if="totalPaginas > 1"
                    class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-hilo pt-6 sm:flex-row"
                >
                    <p class="text-sm tabular-nums text-niebla">
                        Página {{ paginaActual }} de {{ totalPaginas }}, {{ plural(filtradas.length, 'reserva', 'reservas') }} en
                        {{ plural(diasAgrupados.length, 'día', 'días') }}
                    </p>
                    <Pagination :links="links" @navigate="irAPagina" />
                </div>
            </div>
        </div>

        <PanelReserva
            :reserva-id="reservaAbierta"
            :ahora="ahora"
            @cerrar="reservaAbierta = null"
            @aviso="aviso = $event"
        />

        <Aviso :mensaje="aviso" @cerrar="aviso = ''" />
    </AppLayout>
</template>

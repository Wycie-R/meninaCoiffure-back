<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Icono from '../../Components/Icono.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import { mensajeError } from '../../api'
import { aHora, aISO, aMinutos, fechaLarga as textoFecha, guaranies, hora, iniciales } from '../../formato'
import { buscarReservas, useReservas } from '../../reservas'
import { usePersonal } from '../../personal'
import { usePermisos } from '../../auth'
import { aDomicilio, descartada, dia as diaDe, montosReserva, nombreCliente, nombresServicios, porAtender } from '../../agenda'
import { franjasUnidas, useHorarioSalon } from '../../horarios'

const { personal, cargarPersonal } = usePersonal()
const { horarioSalon, cargarHorarioSalon } = useHorarioSalon()
const { reservas: agendaCompleta, cargado: agendaCargada } = useReservas()
// Un profesional solo ve su propia agenda (el servidor ya filtra sus reservas)
// y no ve la caja del salón; la caja espera a saber quién es el usuario.
const { cargado: usuarioCargado, esProfesional: propia, personalPropio, puedeCrearReservas } = usePermisos()

const ahora = ref(new Date())
const hoy = computed(() => aISO(ahora.value))
const diaIso = computed(() => ahora.value.getDay() || 7)

const reservas = ref(agendaCargada.value ? agendaCompleta.value.filter((r) => diaDe(r) === hoy.value) : [])
const cargado = ref(agendaCargada.value)
const error = ref('')

let reloj = null
let consulta = 0

async function cargar() {
    const numeroConsulta = ++consulta
    const fecha = hoy.value
    const { lista, fallo } = await buscarReservas({ fecha_desde: fecha, fecha_hasta: fecha })

    if (numeroConsulta !== consulta) return

    if (fallo) {
        error.value = mensajeError(fallo, propia.value ? 'cargar tu agenda de hoy' : 'cargar la agenda de hoy')
        return
    }

    error.value = ''
    reservas.value = lista.filter((r) => diaDe(r) === fecha)
    cargado.value = true
}

onMounted(() => {
    cargar()
    cargarPersonal()
    cargarHorarioSalon()
    reloj = setInterval(() => {
        ahora.value = new Date()
    }, 30000)
})

onBeforeUnmount(() => clearInterval(reloj))

watch(hoy, cargar)

const fechaLarga = computed(() => textoFecha(hoy.value))

function nombreCorto(reserva) {
    const apellido = reserva.cliente?.apellido ?? ''
    return `${reserva.cliente?.nombre ?? 'Cliente'}${apellido ? ` ${apellido[0]}.` : ''}`
}

function serviciosTexto(reserva) {
    return nombresServicios(reserva).join(', ') || 'Sin servicios'
}

const reservasHoy = computed(() =>
    [...reservas.value].sort((a, b) => aMinutos(a.hora_inicio) - aMinutos(b.hora_inicio) || a.id - b.id)
)

const reservasVigentes = computed(() => reservasHoy.value.filter((reserva) => !descartada(reserva)))

function contar(estado) {
    return reservasHoy.value.filter((reserva) => reserva.estado === estado).length
}

const resumenEstados = computed(() => [
    { estado: 'pendiente', titulo: 'Pendientes', valor: contar('pendiente') },
    { estado: 'confirmada', titulo: 'Confirmadas', valor: contar('confirmada') },
    { estado: 'completada', titulo: 'Completadas', valor: contar('completada') },
    { estado: 'cancelada', titulo: 'Canceladas', valor: contar('cancelada') },
    { estado: 'no_asistio', titulo: 'No asistieron', valor: contar('no_asistio') },
])

const resumen = computed(() => {
    if (!cargado.value) return error.value ? '' : propia.value ? 'Cargando tu agenda de hoy…' : 'Cargando la agenda de hoy…'

    const total = reservasVigentes.value.length
    if (!total) return propia.value ? 'Hoy no tienes citas en tu agenda.' : 'Hoy no hay citas en agenda.'

    const pendientes = contar('pendiente')
    const citas = total === 1 ? 'Una cita' : `${total} citas`
    const enAgenda = propia.value ? 'en tu agenda de hoy' : 'en agenda'
    if (!pendientes) return `${citas} ${enAgenda}, todas confirmadas o atendidas.`

    return `${citas} ${enAgenda}, ${pendientes === 1 ? 'una espera' : `${pendientes} esperan`} confirmación.`
})

const ingresoEstimado = computed(() =>
    reservasVigentes.value.reduce((total, reserva) => total + montosReserva(reserva).total, 0)
)

const ingresoCobrado = computed(() =>
    reservasHoy.value
        .filter((reserva) => reserva.estado === 'completada')
        .reduce((total, reserva) => total + montosReserva(reserva).total, 0)
)

const porcentajeCobrado = computed(() =>
    ingresoEstimado.value ? Math.min(100, Math.round((ingresoCobrado.value / ingresoEstimado.value) * 100)) : 0
)

const serviciosDelDia = computed(() => {
    const conteo = new Map()

    for (const detalle of reservasVigentes.value.flatMap((reserva) => reserva.detalles ?? [])) {
        const actual = conteo.get(detalle.servicio_id) ?? { nombre: detalle.servicio?.nombre ?? 'Servicio', cantidad: 0 }
        actual.cantidad += Number(detalle.cantidad ?? 1)
        conteo.set(detalle.servicio_id, actual)
    }

    const lista = [...conteo.values()].sort((a, b) => b.cantidad - a.cantidad)
    const maximo = lista[0]?.cantidad ?? 1

    return lista.map((item) => ({ ...item, ancho: Math.round((item.cantidad / maximo) * 100) }))
})

const tintes = {
    pendiente: 'bg-miel text-noche',
    confirmada: 'bg-menta text-noche',
    completada: 'bg-ceniza/80 text-noche',
    cancelada: 'bg-[repeating-linear-gradient(135deg,var(--color-borgona-hondo)_0_6px,transparent_6px_12px)] text-borgona ring-1 ring-inset ring-borgona/40',
    no_asistio: 'bg-raso text-bruma ring-1 ring-inset ring-hilo-fuerte',
}

function porNombre(a, b) {
    return String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es') || String(a.apellido ?? '').localeCompare(String(b.apellido ?? ''), 'es')
}

// Todas las profesionales atienden por cita dentro del horario del salón
const franjasSalonHoy = computed(() => franjasUnidas(horarioSalon.value, diaIso.value))

const personalHoy = computed(() => {
    const conCitas = new Set(reservasVigentes.value.map((reserva) => reserva.personal_id))
    const equipo = new Map(personal.value.map((persona) => [persona.id, persona]))
    const franjas = franjasSalonHoy.value

    for (const reserva of reservasVigentes.value) {
        if (reserva.personal && !equipo.has(reserva.personal_id)) {
            equipo.set(reserva.personal_id, { ...reserva.personal, id: reserva.personal_id, activo: true })
        }
    }

    if (propia.value) {
        const propioId = personalPropio.value?.id
        for (const id of [...equipo.keys()]) if (id !== propioId) equipo.delete(id)
    }

    // Con el salón abierto se muestra al equipo activo; cerrado, solo quien tiene citas fuera de horario
    return [...equipo.values()]
        .filter((persona) => (persona.activo && franjas.length) || conCitas.has(persona.id))
        .sort(porNombre)
        .map((persona) => {
            const propias = reservasVigentes.value.filter((reserva) => reserva.personal_id === persona.id)
            const ocupados = propias.reduce(
                (total, reserva) => total + aMinutos(reserva.hora_fin) - aMinutos(reserva.hora_inicio),
                0
            )
            const disponibles = franjas.reduce((total, franja) => total + franja.fin - franja.inicio, 0)

            return {
                id: persona.id,
                nombre: `${persona.nombre} ${persona.apellido}`,
                pila: persona.nombre,
                iniciales: iniciales(persona.nombre, persona.apellido),
                horario: franjas.length
                    ? franjas.map((franja) => `${aHora(franja.inicio)} – ${aHora(franja.fin)}`).join(', ')
                    : 'El salón no atiende hoy',
                franjas,
                reservas: propias.length,
                ocupacion: disponibles ? Math.min(100, Math.round((ocupados / disponibles) * 100)) : 0,
            }
        })
})

const rangoAgenda = computed(() => {
    const visibles = new Set(personalHoy.value.map((persona) => persona.id))
    const inicios = []
    const fines = []

    for (const persona of personalHoy.value) {
        for (const franja of persona.franjas) {
            inicios.push(franja.inicio)
            fines.push(franja.fin)
        }
    }

    for (const reserva of reservasHoy.value) {
        if (!visibles.has(reserva.personal_id)) continue
        inicios.push(aMinutos(reserva.hora_inicio))
        fines.push(aMinutos(reserva.hora_fin))
    }

    if (!inicios.length) return { inicio: 8 * 60, fin: 18 * 60 }

    const inicio = Math.floor(Math.min(...inicios) / 60) * 60
    const fin = Math.ceil(Math.max(...fines) / 60) * 60

    return { inicio, fin: Math.max(fin, inicio + 60) }
})

const horasAgenda = computed(() => {
    const lista = []
    for (let m = rangoAgenda.value.inicio; m <= rangoAgenda.value.fin; m += 60) lista.push(m)
    return lista
})

function posicion(inicio, fin) {
    const { inicio: desde, fin: hasta } = rangoAgenda.value
    const total = hasta - desde

    return {
        left: `${((inicio - desde) / total) * 100}%`,
        width: `${((fin - inicio) / total) * 100}%`,
    }
}

// Zonas sombreadas de la línea de tiempo en las que el salón está cerrado
function zonasFueraDeHorario(franjas) {
    const { inicio, fin } = rangoAgenda.value
    const zonas = []
    let cursor = inicio

    for (const franja of franjas) {
        if (franja.inicio > cursor) zonas.push([cursor, franja.inicio])
        cursor = Math.max(cursor, franja.fin)
    }

    if (cursor < fin) zonas.push([cursor, fin])

    return zonas.map(([desde, hasta]) => ({
        estilo: posicion(desde, hasta),
        clase: [desde === inicio ? 'rounded-l-2xl' : '', hasta === fin ? 'rounded-r-2xl' : ''],
    }))
}

const filasAgenda = computed(() =>
    personalHoy.value.map((persona, indice) => ({
        ...persona,
        fueraDeHorario: zonasFueraDeHorario(persona.franjas),
        bloques: reservasHoy.value
            .filter((reserva) => reserva.personal_id === persona.id)
            .sort((a, b) => Number(!descartada(a)) - Number(!descartada(b)))
            .map((reserva, orden) => ({
                reserva,
                estilo: {
                    ...posicion(aMinutos(reserva.hora_inicio), aMinutos(reserva.hora_fin)),
                    animationDelay: `${indice * 120 + orden * 70}ms`,
                },
            })),
    }))
)

const proximas = computed(() => reservasHoy.value.filter((reserva) => porAtender(reserva, ahora.value)))

function etiquetaHora(minuto) {
    return `${String(Math.floor(minuto / 60)).padStart(2, '0')}:00`
}

function tituloBloque(reserva) {
    const lugar = aDomicilio(reserva) ? ' (a domicilio)' : ''
    const horario = reserva.fuera_de_horario ? ' (fuera de horario)' : ''
    return `${hora(reserva.hora_inicio)} a ${hora(reserva.hora_fin)}: ${nombreCliente(reserva)}, ${serviciosTexto(reserva)}${lugar}${horario}`
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-6 py-10">
            <PageHeader :titulo="fechaLarga" :descripcion="resumen" class="[&_h1]:first-letter:uppercase">
                <Button href="/reservas" variant="secondary">{{ propia ? 'Ver tu agenda' : 'Ver todas las reservas' }}</Button>
                <Button v-if="puedeCrearReservas" href="/reservas/crear" variant="primary">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nueva reserva
                </Button>
            </PageHeader>

            <p
                v-if="error && cargado"
                role="alert"
                class="mt-6 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
            >
                {{ error }} Se muestran los últimos datos cargados.
            </p>

            <EstadoVacio
                v-if="error && !cargado"
                class="mt-10"
                tono="error"
                :titulo="propia ? 'No se pudo cargar tu agenda de hoy' : 'No se pudo cargar la agenda de hoy'"
                :descripcion="error"
                role="alert"
            >
                <Button label="Reintentar" variant="secondary" @click="cargar" />
            </EstadoVacio>

            <template v-else>
                <section class="mt-10 rounded-[28px] border border-hilo bg-velo p-5 sm:p-7" aria-labelledby="titulo-sillones">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-3">
                        <h2 id="titulo-sillones" class="text-2xl">{{ propia ? 'Tu agenda de hoy' : 'Sillones de hoy' }}</h2>

                        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-bruma">
                            <li v-for="item in resumenEstados" :key="item.estado" class="flex items-center gap-2">
                                <span class="h-3 w-1.5 rounded-full" :class="tintes[item.estado]" aria-hidden="true"></span>
                                {{ item.titulo }}
                                <span class="tabular-nums text-perla">{{ item.valor }}</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="!cargado" class="mt-6 space-y-3" aria-busy="true" :aria-label="propia ? 'Cargando tu agenda de hoy' : 'Cargando la agenda de hoy'">
                        <div v-for="n in 3" :key="n" class="h-16 animate-pulse rounded-2xl bg-terciopelo"></div>
                    </div>

                    <div v-else-if="filasAgenda.length" class="scroll-fino -mx-5 mt-6 overflow-x-auto px-5 pb-2 sm:-mx-7 sm:px-7">
                        <div class="min-w-[760px]">
                            <div class="relative ml-40 h-6">
                                <span
                                    v-for="minuto in horasAgenda"
                                    :key="minuto"
                                    class="absolute -translate-x-1/2 text-xs tabular-nums text-niebla"
                                    :style="{ left: posicion(rangoAgenda.inicio, minuto).width }"
                                >
                                    {{ etiquetaHora(minuto) }}
                                </span>
                            </div>

                            <div
                                v-for="fila in filasAgenda"
                                :key="fila.id"
                                class="flex items-center border-t border-hilo/70 py-3 first-of-type:border-t-0"
                            >
                                <div class="flex w-40 shrink-0 items-center gap-3 pr-4">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-raso font-display text-base text-orquidea-claro">
                                        {{ fila.iniciales }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ fila.pila }}</p>
                                        <p class="text-xs tabular-nums text-niebla" :title="fila.horario">{{ fila.horario }}</p>
                                    </div>
                                </div>

                                <div class="relative h-16 flex-1 rounded-2xl bg-terciopelo">
                                    <span
                                        v-for="minuto in horasAgenda.slice(1, -1)"
                                        :key="minuto"
                                        class="absolute inset-y-2 w-px bg-hilo/60"
                                        :style="{ left: posicion(rangoAgenda.inicio, minuto).width }"
                                        aria-hidden="true"
                                    ></span>

                                    <span
                                        v-for="(zona, i) in fila.fueraDeHorario"
                                        :key="i"
                                        class="absolute inset-y-0 bg-[repeating-linear-gradient(135deg,var(--color-velo)_0_4px,transparent_4px_9px)]"
                                        :class="zona.clase"
                                        :style="zona.estilo"
                                        aria-hidden="true"
                                    ></span>

                                    <div
                                        v-for="bloque in fila.bloques"
                                        :key="bloque.reserva.id"
                                        class="absolute inset-y-1.5 origin-left animate-crecer overflow-hidden rounded-xl px-2.5 py-1.5"
                                        :class="tintes[bloque.reserva.estado]"
                                        :style="bloque.estilo"
                                        :title="tituloBloque(bloque.reserva)"
                                    >
                                        <p class="flex items-center gap-1 truncate text-xs font-semibold leading-tight">
                                            <Icono v-if="aDomicilio(bloque.reserva)" name="casa" class="h-3 w-3 shrink-0" />
                                            <span class="truncate">{{ nombreCorto(bloque.reserva) }}</span>
                                        </p>
                                        <p class="truncate text-[0.6875rem] leading-tight opacity-80">
                                            {{ hora(bloque.reserva.hora_inicio) }} {{ serviciosTexto(bloque.reserva) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <EstadoVacio
                        v-else
                        class="mt-6"
                        titulo="El salón no atiende hoy"
                        descripcion="Las citas del día aparecen aquí en una línea de tiempo."
                    />
                </section>

                <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                    <section aria-labelledby="titulo-proximas">
                        <div class="flex items-baseline justify-between gap-4">
                            <h2 id="titulo-proximas" class="text-2xl">Por atender</h2>
                            <Link
                                href="/reservas"
                                class="rounded text-sm text-orquidea underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                            >
                                Ver agenda
                            </Link>
                        </div>

                        <div v-if="!cargado" class="mt-4 space-y-3" aria-hidden="true">
                            <div v-for="n in 3" :key="n" class="h-14 animate-pulse rounded-2xl bg-velo"></div>
                        </div>

                        <ol v-else-if="proximas.length" class="mt-4 divide-y divide-hilo/70 border-y border-hilo/70">
                            <li v-for="reserva in proximas" :key="reserva.id" class="flex items-center gap-4 py-4">
                                <p class="w-14 shrink-0 font-display text-2xl tabular-nums">{{ hora(reserva.hora_inicio) }}</p>
                                <div class="min-w-0 flex-1">
                                    <p class="flex min-w-0 items-center gap-2">
                                        <span class="truncate font-medium">{{ nombreCliente(reserva) }}</span>
                                        <span
                                            v-if="aDomicilio(reserva)"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-orquidea/12 px-2 py-0.5 text-xs text-orquidea-claro"
                                        >
                                            <Icono name="casa" class="h-3.5 w-3.5" />
                                            A domicilio
                                        </span>
                                    </p>
                                    <p class="truncate text-sm text-bruma">
                                        {{ serviciosTexto(reserva) }}<template v-if="!propia">, con {{ reserva.personal?.nombre ?? 'profesional sin asignar' }}</template>
                                    </p>
                                </div>
                                <StatusBadge :status="reserva.estado" />
                            </li>
                        </ol>

                        <p v-else class="mt-4 rounded-2xl bg-velo px-5 py-8 text-center text-sm text-bruma">
                            No quedan citas por atender hoy.
                        </p>
                    </section>

                    <div class="space-y-8">
                        <section
                            v-if="usuarioCargado && !propia"
                            class="rounded-[28px] bg-orquidea-hondo/35 p-6 ring-1 ring-inset ring-orquidea/25"
                            aria-labelledby="titulo-caja"
                        >
                            <h2 id="titulo-caja" class="text-lg text-orquidea-claro">Caja estimada de hoy</h2>
                            <p class="mt-2 font-display text-[2.75rem] leading-none tabular-nums">{{ guaranies(ingresoEstimado) }}</p>

                            <div
                                class="mt-5 flex h-2.5 overflow-hidden rounded-full bg-noche/50"
                                role="img"
                                :aria-label="`${porcentajeCobrado}% ya atendido`"
                            >
                                <div class="h-full rounded-full bg-orquidea" :style="{ width: `${porcentajeCobrado}%` }"></div>
                            </div>

                            <p class="mt-3 text-sm text-bruma">
                                {{ guaranies(ingresoCobrado) }} ya atendido. Con descuentos aplicados; no incluye citas canceladas ni ausencias.
                            </p>
                        </section>

                        <section aria-labelledby="titulo-pedidos">
                            <h2 id="titulo-pedidos" class="text-2xl">Lo más pedido</h2>

                            <ul v-if="serviciosDelDia.length" class="mt-4 space-y-3">
                                <li v-for="servicio in serviciosDelDia" :key="servicio.nombre">
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="truncate">{{ servicio.nombre }}</span>
                                        <span class="shrink-0 tabular-nums text-bruma">{{ servicio.cantidad }}</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 rounded-full bg-terciopelo">
                                        <div class="h-full rounded-full bg-miel/80" :style="{ width: `${servicio.ancho}%` }"></div>
                                    </div>
                                </li>
                            </ul>

                            <p v-else class="mt-4 text-sm text-bruma">Todavía no hay servicios agendados para hoy.</p>
                        </section>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

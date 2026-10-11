<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Icono from '../../Components/Icono.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import { guaranies } from '../../formato'

const personal = [
    {
        id: 1,
        nombre: 'Carla',
        apellido: 'Estilista',
        telefono: '0971555666',
        email: 'carla@meninacoiffure.com',
        fecha_ingreso: '2025-01-15',
        activo: true,
        turnos: [
            { id: 1, personal_id: 1, dia_semana: 1, hora_inicio: '08:00:00', hora_fin: '18:00:00' },
            { id: 3, personal_id: 1, dia_semana: 2, hora_inicio: '08:00:00', hora_fin: '18:00:00' },
            { id: 5, personal_id: 1, dia_semana: 3, hora_inicio: '08:00:00', hora_fin: '18:00:00' },
            { id: 7, personal_id: 1, dia_semana: 4, hora_inicio: '08:00:00', hora_fin: '18:00:00' },
            { id: 9, personal_id: 1, dia_semana: 5, hora_inicio: '08:00:00', hora_fin: '18:00:00' },
        ],
    },
    {
        id: 2,
        nombre: 'Diana',
        apellido: 'Colorista',
        telefono: '0972777888',
        email: 'diana@meninacoiffure.com',
        fecha_ingreso: '2025-03-01',
        activo: true,
        turnos: [
            { id: 2, personal_id: 2, dia_semana: 1, hora_inicio: '09:00:00', hora_fin: '17:00:00' },
            { id: 4, personal_id: 2, dia_semana: 2, hora_inicio: '09:00:00', hora_fin: '17:00:00' },
            { id: 6, personal_id: 2, dia_semana: 3, hora_inicio: '09:00:00', hora_fin: '17:00:00' },
            { id: 8, personal_id: 2, dia_semana: 4, hora_inicio: '09:00:00', hora_fin: '17:00:00' },
            { id: 10, personal_id: 2, dia_semana: 5, hora_inicio: '09:00:00', hora_fin: '17:00:00' },
        ],
    },
]

const [carla, diana] = personal

const ana = {
    id: 1,
    nombre: 'Ana',
    apellido: 'Gómez',
    telefono: '0981111222',
    email: 'ana.gomez@example.com',
}

const beatriz = {
    id: 2,
    nombre: 'Beatriz',
    apellido: 'López',
    telefono: '0982333444',
    email: 'beatriz.lopez@example.com',
}

const corte = {
    id: 1,
    categoria_id: 1,
    nombre: 'Corte Femenino',
    descripcion: null,
    duracion_minutos: 45,
    activo: true,
}

const nutricion = {
    id: 2,
    categoria_id: 1,
    nombre: 'Baño de Crema / Nutrición',
    descripcion: null,
    duracion_minutos: 30,
    activo: true,
}

const coloracion = {
    id: 3,
    categoria_id: 2,
    nombre: 'Coloración Completa',
    descripcion: null,
    duracion_minutos: 90,
    activo: true,
}

function reservaDemo({ id, cliente, profesional, fecha, inicio, fin, estado, servicios }) {
    const { turnos, ...personalSinTurnos } = profesional

    return {
        id,
        cliente_id: cliente.id,
        personal_id: profesional.id,
        fecha,
        hora_inicio: `${inicio}:00`,
        hora_fin: `${fin}:00`,
        estado,
        descuento_id: null,
        cliente,
        personal: personalSinTurnos,
        detalles: servicios.map(([servicio, precio], i) => ({
            id: id * 10 + i + 1,
            reserva_id: id,
            servicio_id: servicio.id,
            precio_aplicado: precio,
            cantidad: 1,
            agregado_en_el_momento: false,
            servicio,
        })),
    }
}

const reservas = [
    reservaDemo({
        id: 4, cliente: ana, profesional: diana, fecha: '2026-10-05',
        inicio: '14:00', fin: '14:30', estado: 'pendiente',
        servicios: [[nutricion, '50000.00']],
    }),
    reservaDemo({
        id: 1, cliente: ana, profesional: carla, fecha: '2026-10-05',
        inicio: '09:00', fin: '09:45', estado: 'completada',
        servicios: [[corte, '70000.00']],
    }),
    reservaDemo({
        id: 7, cliente: ana, profesional: carla, fecha: '2026-10-04',
        inicio: '10:00', fin: '10:45', estado: 'completada',
        servicios: [[corte, '70000.00']],
    }),
    reservaDemo({
        id: 3, cliente: beatriz, profesional: carla, fecha: '2026-10-05',
        inicio: '11:00', fin: '12:15', estado: 'confirmada',
        servicios: [[corte, '70000.00'], [nutricion, '50000.00']],
    }),
    reservaDemo({
        id: 8, cliente: beatriz, profesional: diana, fecha: '2026-10-06',
        inicio: '10:00', fin: '11:30', estado: 'pendiente',
        servicios: [[coloracion, '180000.00']],
    }),
    reservaDemo({
        id: 2, cliente: beatriz, profesional: diana, fecha: '2026-10-05',
        inicio: '09:30', fin: '11:00', estado: 'completada',
        servicios: [[coloracion, '180000.00']],
    }),
    reservaDemo({
        id: 6, cliente: ana, profesional: carla, fecha: '2026-10-05',
        inicio: '16:30', fin: '17:00', estado: 'cancelada',
        servicios: [[nutricion, '50000.00']],
    }),
    reservaDemo({
        id: 5, cliente: beatriz, profesional: carla, fecha: '2026-10-05',
        inicio: '15:30', fin: '16:15', estado: 'pendiente',
        servicios: [[corte, '70000.00']],
    }),
]

const hoy = new Date('2026-10-05T00:00:00')

function aFechaISO(fecha) {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0')
    const dia = String(fecha.getDate()).padStart(2, '0')
    return `${fecha.getFullYear()}-${mes}-${dia}`
}

const fechaHoy = aFechaISO(hoy)
const diaIso = hoy.getDay() || 7

const fechaLarga = hoy.toLocaleDateString('es-PY', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
})

function hora(valor) {
    return valor.slice(0, 5)
}

function minutos(valor) {
    const [h, m] = valor.split(':').map(Number)
    return h * 60 + m
}

function nombresServicios(reserva) {
    return reserva.detalles.map((detalle) => detalle.servicio.nombre).join(', ')
}

function iniciales(nombre, apellido) {
    return `${nombre[0] ?? ''}${apellido[0] ?? ''}`.toUpperCase()
}


const reservasHoy = computed(() =>
    reservas
        .filter((reserva) => reserva.fecha === fechaHoy)
        .sort((a, b) => a.hora_inicio.localeCompare(b.hora_inicio))
)

const reservasVigentes = computed(() =>
    reservasHoy.value.filter((reserva) => reserva.estado !== 'cancelada')
)

function contar(estado) {
    return reservasHoy.value.filter((reserva) => reserva.estado === estado).length
}

const resumenEstados = computed(() => [
    { estado: 'pendiente', titulo: 'Pendientes', valor: contar('pendiente') },
    { estado: 'confirmada', titulo: 'Confirmadas', valor: contar('confirmada') },
    { estado: 'completada', titulo: 'Completadas', valor: contar('completada') },
    { estado: 'cancelada', titulo: 'Canceladas', valor: contar('cancelada') },
])

const resumen = computed(() => {
    const total = reservasHoy.value.length
    if (!total) return 'Hoy no hay citas en agenda.'

    const pendientes = contar('pendiente')
    const citas = total === 1 ? 'Una cita' : `${total} citas`
    if (!pendientes) return `${citas} en agenda, todas confirmadas o atendidas.`

    return `${citas} en agenda, ${pendientes === 1 ? 'una espera' : `${pendientes} esperan`} confirmación.`
})

const ingresoEstimado = computed(() =>
    reservasVigentes.value
        .flatMap((reserva) => reserva.detalles)
        .reduce((total, detalle) => total + Number(detalle.precio_aplicado) * detalle.cantidad, 0)
)

const ingresoCobrado = computed(() =>
    reservasHoy.value
        .filter((reserva) => reserva.estado === 'completada')
        .flatMap((reserva) => reserva.detalles)
        .reduce((total, detalle) => total + Number(detalle.precio_aplicado) * detalle.cantidad, 0)
)

const porcentajeCobrado = computed(() =>
    ingresoEstimado.value ? Math.round((ingresoCobrado.value / ingresoEstimado.value) * 100) : 0
)

const serviciosDelDia = computed(() => {
    const conteo = new Map()

    for (const detalle of reservasVigentes.value.flatMap((reserva) => reserva.detalles)) {
        const actual = conteo.get(detalle.servicio_id) ?? { nombre: detalle.servicio.nombre, cantidad: 0 }
        actual.cantidad += detalle.cantidad
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
}

function turnoDeHoy(persona) {
    return persona.turnos.find((turno) => turno.dia_semana === diaIso)
}

const personalHoy = computed(() =>
    personal
        .filter((persona) => persona.activo && turnoDeHoy(persona))
        .map((persona) => {
            const turno = turnoDeHoy(persona)
            const propias = reservasVigentes.value.filter((reserva) => reserva.personal_id === persona.id)
            const ocupados = propias.reduce(
                (total, reserva) => total + minutos(reserva.hora_fin) - minutos(reserva.hora_inicio),
                0
            )
            const disponibles = minutos(turno.hora_fin) - minutos(turno.hora_inicio)

            return {
                id: persona.id,
                nombre: `${persona.nombre} ${persona.apellido}`,
                pila: persona.nombre,
                iniciales: iniciales(persona.nombre, persona.apellido),
                horario: `${hora(turno.hora_inicio)} – ${hora(turno.hora_fin)}`,
                turno,
                reservas: propias.length,
                ocupacion: disponibles ? Math.min(100, Math.round((ocupados / disponibles) * 100)) : 0,
            }
        })
)

const rangoAgenda = computed(() => {
    const turnos = personalHoy.value.map((persona) => persona.turno)
    if (!turnos.length) return { inicio: 8 * 60, fin: 18 * 60 }

    return {
        inicio: Math.floor(Math.min(...turnos.map((t) => minutos(t.hora_inicio))) / 60) * 60,
        fin: Math.ceil(Math.max(...turnos.map((t) => minutos(t.hora_fin))) / 60) * 60,
    }
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

const filasAgenda = computed(() =>
    personalHoy.value.map((persona, indice) => ({
        ...persona,
        fueraDeTurno: [
            posicion(rangoAgenda.value.inicio, minutos(persona.turno.hora_inicio)),
            posicion(minutos(persona.turno.hora_fin), rangoAgenda.value.fin),
        ],
        bloques: reservasHoy.value
            .filter((reserva) => reserva.personal_id === persona.id)
            .map((reserva, orden) => ({
                reserva,
                estilo: {
                    ...posicion(minutos(reserva.hora_inicio), minutos(reserva.hora_fin)),
                    animationDelay: `${indice * 120 + orden * 70}ms`,
                },
            })),
    }))
)

const proximas = computed(() =>
    reservasHoy.value.filter((reserva) => ['pendiente', 'confirmada'].includes(reserva.estado))
)

function etiquetaHora(minuto) {
    return `${String(Math.floor(minuto / 60)).padStart(2, '0')}:00`
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-6 py-10">
            <PageHeader :titulo="fechaLarga" :descripcion="resumen" class="[&_h1]:first-letter:uppercase">
                <Button href="/reservas" variant="secondary">Ver todas las reservas</Button>
                <Button href="/reservas/crear" variant="primary">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nueva reserva
                </Button>
            </PageHeader>

            <section class="mt-10 rounded-[28px] border border-hilo bg-velo p-5 sm:p-7" aria-labelledby="titulo-sillones">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-3">
                    <h2 id="titulo-sillones" class="text-2xl">Sillones de hoy</h2>

                    <ul class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-bruma">
                        <li v-for="item in resumenEstados" :key="item.estado" class="flex items-center gap-2">
                            <span class="h-3 w-1.5 rounded-full" :class="tintes[item.estado]" aria-hidden="true"></span>
                            {{ item.titulo }}
                            <span class="tabular-nums text-perla">{{ item.valor }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="filasAgenda.length" class="scroll-fino -mx-5 mt-6 overflow-x-auto px-5 pb-2 sm:-mx-7 sm:px-7">
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
                                    <p class="text-xs tabular-nums text-niebla">{{ fila.horario }}</p>
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
                                    v-for="(zona, i) in fila.fueraDeTurno"
                                    :key="i"
                                    class="absolute inset-y-0 bg-[repeating-linear-gradient(135deg,var(--color-velo)_0_4px,transparent_4px_9px)]"
                                    :class="i === 0 ? 'rounded-l-2xl' : 'rounded-r-2xl'"
                                    :style="zona"
                                    aria-hidden="true"
                                ></span>

                                <div
                                    v-for="bloque in fila.bloques"
                                    :key="bloque.reserva.id"
                                    class="absolute inset-y-1.5 origin-left animate-crecer overflow-hidden rounded-xl px-2.5 py-1.5"
                                    :class="tintes[bloque.reserva.estado]"
                                    :style="bloque.estilo"
                                    :title="`${hora(bloque.reserva.hora_inicio)} a ${hora(bloque.reserva.hora_fin)}: ${bloque.reserva.cliente.nombre} ${bloque.reserva.cliente.apellido}, ${nombresServicios(bloque.reserva)}`"
                                >
                                    <p class="truncate text-xs font-semibold leading-tight">
                                        {{ bloque.reserva.cliente.nombre }} {{ bloque.reserva.cliente.apellido[0] }}.
                                    </p>
                                    <p class="truncate text-[0.6875rem] leading-tight opacity-80">
                                        {{ hora(bloque.reserva.hora_inicio) }} {{ nombresServicios(bloque.reserva) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <EstadoVacio
                    v-else
                    class="mt-6"
                    titulo="Nadie tiene turno hoy"
                    descripcion="Cuando haya profesionales con turno, sus citas aparecen aquí en una línea de tiempo."
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

                    <ol v-if="proximas.length" class="mt-4 divide-y divide-hilo/70 border-y border-hilo/70">
                        <li v-for="reserva in proximas" :key="reserva.id" class="flex items-center gap-4 py-4">
                            <p class="w-14 shrink-0 font-display text-2xl tabular-nums">{{ hora(reserva.hora_inicio) }}</p>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ reserva.cliente.nombre }} {{ reserva.cliente.apellido }}</p>
                                <p class="truncate text-sm text-bruma">
                                    {{ nombresServicios(reserva) }}, con {{ reserva.personal.nombre }}
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
                    <section class="rounded-[28px] bg-orquidea-hondo/35 p-6 ring-1 ring-inset ring-orquidea/25" aria-labelledby="titulo-caja">
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
                            {{ guaranies(ingresoCobrado) }} ya atendido. No incluye citas canceladas.
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

                        <p v-else class="mt-4 text-sm text-bruma">Todavía no hay servicios agendados.</p>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Icono from '../../Components/Icono.vue'
import { guaranies } from '../../api'

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
    year: 'numeric',
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

const horaActual = new Date().getHours()

const saludo = horaActual < 12 ? 'Buen día' : horaActual < 19 ? 'Buenas tardes' : 'Buenas noches'

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

const tarjetas = computed(() => [
    { clave: 'total', titulo: 'Reservas de hoy', detalle: 'En la agenda del día', icono: 'total', valor: reservasHoy.value.length, destacada: false },
    { clave: 'pendientes', titulo: 'Pendientes', detalle: 'Esperan confirmación', icono: 'pendiente', valor: contar('pendiente'), destacada: true },
    { clave: 'completadas', titulo: 'Completadas', detalle: 'Atenciones terminadas', icono: 'completada', valor: contar('completada'), destacada: false },
    { clave: 'canceladas', titulo: 'Canceladas', detalle: 'No cuentan en el ingreso', icono: 'cancelada', valor: contar('cancelada'), destacada: false },
])

const resumen = computed(() => {
    const total = reservasHoy.value.length
    const pendientes = contar('pendiente')

    if (!total) return 'Hoy no hay citas en agenda.'

    const citas = total === 1 ? '1 cita' : `${total} citas`
    if (!pendientes) return `Hoy hay ${citas} en agenda y todas están confirmadas o atendidas.`

    const esperan = pendientes === 1 ? '1 espera confirmación' : `${pendientes} esperan confirmación`
    return `Hoy hay ${citas} en agenda y ${esperan}.`
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

const colorPunto = {
    pendiente: 'bg-[#C9A96E] ring-[#C9A96E]/25',
    confirmada: 'bg-[#9AD9AE] ring-[#9AD9AE]/20',
    completada: 'bg-[#7A6E61] ring-[#7A6E61]/25',
    cancelada: 'bg-[#F0A8A8] ring-[#F0A8A8]/20',
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
                iniciales: iniciales(persona.nombre, persona.apellido),
                horario: `${hora(turno.hora_inicio)} – ${hora(turno.hora_fin)}`,
                reservas: propias.length,
                ocupacion: disponibles ? Math.min(100, Math.round((ocupados / disponibles) * 100)) : 0,
            }
        })
)
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <section class="relative overflow-hidden rounded-2xl border border-[#5B5147] bg-gradient-to-br from-[#3A3128] via-[#2A2622] to-[#1F1D1B] px-6 py-7 sm:px-8 sm:py-9">
                <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-transparent via-[#C9A96E] to-transparent"></div>

                <svg
                    class="pointer-events-none absolute -right-8 top-1/2 h-80 w-80 -translate-y-1/2 text-[#C9A96E]/15"
                    viewBox="0 0 200 200"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.2"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M25 160 C 55 110, 45 60, 95 32 S 166 18, 160 78" />
                    <path d="M40 170 C 70 120, 60 70, 110 40 S 180 30, 170 90" />
                    <path d="M55 178 C 85 128, 75 78, 125 48 S 190 45, 178 100" />
                    <path d="M70 186 C 100 136, 90 86, 140 56 S 196 62, 184 110" />
                    <circle cx="150" cy="150" r="22" />
                    <circle cx="150" cy="150" r="14" />
                </svg>

                <div class="relative max-w-xl">
                    <p class="flex items-center gap-2 text-[0.6875rem] font-medium uppercase tracking-[0.3em] text-[#C9A96E]">
                        <Icono name="destello" class="h-3.5 w-3.5" />
                        <span class="lining-nums">{{ fechaLarga }}</span>
                    </p>

                    <h1 class="mt-3 text-4xl font-semibold text-[#F3ECE0] sm:text-5xl">
                        {{ saludo }}, equipo
                    </h1>

                    <p class="mt-3 font-serif text-xl italic leading-snug text-[#E0CDAF] lining-nums">
                        {{ resumen }}
                    </p>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <Button href="/reservas/crear">
                            <Icono name="agregar" class="h-4 w-4" />
                            Nueva reserva
                        </Button>
                        <Button href="/reservas" variant="secondary">
                            <Icono name="reservas" class="h-4 w-4" />
                            Ver agenda completa
                        </Button>
                    </div>
                </div>
            </section>

            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    v-for="tarjeta in tarjetas"
                    :key="tarjeta.clave"
                    class="relative flex min-w-0 flex-col items-start gap-3 overflow-hidden rounded-xl border p-4 sm:flex-row sm:items-center sm:gap-4 sm:p-5"
                    :class="tarjeta.destacada ? 'border-[#867158] bg-[#403629]' : 'border-[#47413B] bg-[#292725]'"
                >
                    <div
                        v-if="tarjeta.destacada"
                        class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-[#C9A96E] via-[#E9D2A0] to-[#C9A96E]"
                    ></div>

                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border"
                        :class="tarjeta.destacada ? 'border-[#C9A96E] bg-[#C9A96E]/20 text-[#E9D2A0]' : 'border-[#C9A96E]/50 bg-[#C9A96E]/10 text-[#E0CDAF]'"
                    >
                        <Icono :name="tarjeta.icono" class="h-6 w-6" />
                    </span>

                    <div class="min-w-0">
                        <p class="font-serif text-4xl font-semibold leading-none lining-nums text-[#F3ECE0]">
                            {{ tarjeta.valor }}
                        </p>
                        <p class="mt-1.5 text-xs font-medium uppercase tracking-wide" :class="tarjeta.destacada ? 'text-[#E0CDAF]' : 'text-[#C7BEB3]'">
                            {{ tarjeta.titulo }}
                        </p>
                        <p class="mt-0.5 hidden text-xs text-[#8C7F70] sm:block">
                            {{ tarjeta.detalle }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <section class="min-w-0 rounded-xl border border-[#47413B] bg-[#292725] lg:col-span-2">
                    <div class="flex items-center justify-between gap-4 border-b border-[#47413B] px-4 py-4 sm:px-6">
                        <h2 class="flex items-center gap-2.5 text-2xl font-semibold text-[#F3ECE0]">
                            <Icono name="reservas" class="h-5 w-5 text-[#C9A96E]" />
                            Agenda de hoy
                        </h2>

                        <Link
                            href="/reservas"
                            class="shrink-0 rounded text-sm text-[#D4C3A3] transition-colors hover:text-[#F3ECE0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3]"
                        >
                            Ver todas →
                        </Link>
                    </div>

                    <ol v-if="reservasHoy.length" class="relative mx-4 my-5 border-l border-[#5B5147] sm:mx-6">
                        <li
                            v-for="reserva in reservasHoy"
                            :key="reserva.id"
                            class="relative mb-3 ml-5 min-w-0 last:mb-0 sm:ml-6"
                        >
                            <span
                                class="absolute -left-[1.61rem] top-[1.1rem] h-3 w-3 rounded-full ring-4 sm:-left-[1.86rem]"
                                :class="colorPunto[reserva.estado]"
                                aria-hidden="true"
                            ></span>

                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-[#3D3934] bg-[#2E2B28] px-4 py-3 transition-colors hover:border-[#C9A96E]/50 hover:bg-[#332F2B]"
                                :class="{ 'opacity-60': reserva.estado === 'cancelada' }"
                            >
                                <p class="w-16 font-serif text-xl font-semibold lining-nums tabular-nums text-[#E0CDAF]">
                                    {{ hora(reserva.hora_inicio) }}
                                </p>

                                <span
                                    class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#403629] text-xs font-semibold text-[#E0CDAF] sm:flex"
                                    aria-hidden="true"
                                >
                                    {{ iniciales(reserva.cliente.nombre, reserva.cliente.apellido) }}
                                </span>

                                <div class="order-3 w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                                    <p class="truncate text-sm font-medium text-[#F3ECE0]">
                                        {{ reserva.cliente.nombre }} {{ reserva.cliente.apellido }}
                                    </p>
                                    <p class="truncate text-xs text-[#BEB3A6]">
                                        {{ nombresServicios(reserva) }} · con {{ reserva.personal.nombre }} · hasta {{ hora(reserva.hora_fin) }}
                                    </p>
                                </div>

                                <StatusBadge :status="reserva.estado" class="ml-auto sm:ml-0" />
                            </div>
                        </li>
                    </ol>

                    <div v-else class="px-5 py-14 text-center">
                        <Icono name="reservas" class="mx-auto h-12 w-12 text-[#5B5147]" />
                        <p class="mt-3 font-serif text-xl italic text-[#BEB3A6]">
                            No hay reservas para hoy.
                        </p>
                        <Button label="+ Nueva reserva" href="/reservas/crear" variant="secondary" class="mt-4" />
                    </div>
                </section>

                <div class="min-w-0 space-y-6">
                    <section class="relative overflow-hidden rounded-xl border border-[#867158] bg-gradient-to-br from-[#4A3E2F] to-[#352E26] p-6">
                        <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-[#C9A96E] via-[#E9D2A0] to-[#C9A96E]"></div>

                        <p class="text-[0.6875rem] font-medium uppercase tracking-[0.25em] text-[#C9A96E]">
                            Ingreso estimado del día
                        </p>
                        <p class="mt-2 font-serif text-4xl font-semibold lining-nums text-[#F3ECE0]">
                            {{ guaranies(ingresoEstimado) }}
                        </p>

                        <div class="mt-4 h-1.5 rounded-full bg-[#1C1B1A]/50">
                            <div
                                class="h-1.5 rounded-full bg-gradient-to-r from-[#C9A96E] to-[#E9D2A0]"
                                :style="{ width: `${porcentajeCobrado}%` }"
                            ></div>
                        </div>

                        <p class="mt-2 text-xs text-[#E0CDAF] lining-nums">
                            {{ guaranies(ingresoCobrado) }} ya atendido ({{ porcentajeCobrado }}%) · sin contar canceladas
                        </p>
                    </section>

                    <section class="rounded-xl border border-[#51483F] bg-[#34302B]">
                        <div class="border-b border-[#51483F] px-5 py-4">
                            <h2 class="flex items-center gap-2.5 text-2xl font-semibold text-[#F3ECE0]">
                                <Icono name="personal" class="h-5 w-5 text-[#C9A96E]" />
                                Equipo de hoy
                            </h2>
                        </div>

                        <ul v-if="personalHoy.length" class="space-y-5 px-5 py-5">
                            <li v-for="persona in personalHoy" :key="persona.id" class="flex items-center gap-3">
                                <span
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#E9D2A0] to-[#9C7E4C] p-[1.5px]"
                                    aria-hidden="true"
                                >
                                    <span class="flex h-full w-full items-center justify-center rounded-full bg-[#292725] font-serif text-lg font-semibold text-[#E9D2A0]">
                                        {{ persona.iniciales }}
                                    </span>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="truncate text-sm font-medium text-[#F3ECE0]">
                                            {{ persona.nombre }}
                                        </p>
                                        <p class="shrink-0 text-xs text-[#E0CDAF] lining-nums">
                                            {{ persona.reservas }} {{ persona.reservas === 1 ? 'cita' : 'citas' }}
                                        </p>
                                    </div>

                                    <p class="text-xs text-[#BEB3A6] lining-nums">
                                        {{ persona.horario }} · {{ persona.ocupacion }}% ocupado
                                    </p>

                                    <div class="mt-1.5 h-1 rounded-full bg-[#292725]">
                                        <div class="h-1 rounded-full bg-[#C9A96E]" :style="{ width: `${persona.ocupacion}%` }"></div>
                                    </div>
                                </div>
                            </li>
                        </ul>

                        <p v-else class="px-5 py-8 text-center font-serif text-lg italic text-[#BEB3A6]">
                            Ningún profesional tiene turno hoy.
                        </p>
                    </section>

                    <section class="rounded-xl border border-[#47413B] bg-[#292725]">
                        <div class="border-b border-[#47413B] px-5 py-4">
                            <h2 class="flex items-center gap-2.5 text-2xl font-semibold text-[#F3ECE0]">
                                <Icono name="servicios" class="h-5 w-5 text-[#C9A96E]" />
                                Servicios del día
                            </h2>
                        </div>

                        <ul v-if="serviciosDelDia.length" class="space-y-3.5 px-5 py-5">
                            <li v-for="servicio in serviciosDelDia" :key="servicio.nombre">
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <p class="truncate text-[#F3ECE0]">{{ servicio.nombre }}</p>
                                    <p class="shrink-0 font-serif text-lg font-semibold lining-nums text-[#E0CDAF]">
                                        {{ servicio.cantidad }}
                                    </p>
                                </div>
                                <div class="mt-1 h-1 rounded-full bg-[#34302B]">
                                    <div class="h-1 rounded-full bg-[#867158]" :style="{ width: `${servicio.ancho}%` }"></div>
                                </div>
                            </li>
                        </ul>

                        <p v-else class="px-5 py-8 text-center font-serif text-lg italic text-[#BEB3A6]">
                            Todavía no hay servicios agendados.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

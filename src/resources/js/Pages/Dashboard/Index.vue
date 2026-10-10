
<script setup>
import { computed } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import StatusBadge from '../../Components/StatusBadge.vue'

// ============================================================
// DATOS TEMPORALES DE DEMOSTRACIÓN
// Tienen la misma forma que devuelven /api/personal y /api/reservas
// (sin created_at ni updated_at). Al conectar la API, este bloque se
// reemplaza por esas respuestas.
// ============================================================

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

// Arma una reserva con la forma real de GET /api/reservas.
// El personal anidado va sin turnos, porque el index real no los carga.

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

// Desordenadas a propósito para comprobar el orden por hora.
// 6 de hoy (2026-10-05), 1 de ayer (2026-10-04) y 1 de mañana (2026-10-06).

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

// ============================================================
// LÓGICA DE LA PÁGINA (esta parte se queda al conectar la API)
// ============================================================

// Fecha de demostración fija: lunes 5 de octubre de 2026.
// El "T00:00:00" hace que se lea como hora local y no como UTC.
// Con la API real se reemplaza por: const hoy = new Date()

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

function nombresServicios(reserva) {
    return reserva.detalles.map((detalle) => detalle.servicio.nombre).join(', ')
}

// El backend no filtra por fecha ni ordena por hora, así que se hace aquí.

const reservasHoy = computed(() =>
    reservas
        .filter((reserva) => reserva.fecha === fechaHoy)
        .sort((a, b) => a.hora_inicio.localeCompare(b.hora_inicio))
)

const tarjetas = computed(() => {
    const contar = (estado) =>
        reservasHoy.value.filter((reserva) => reserva.estado === estado).length

    return [
        { clave: 'total', titulo: 'Reservas de hoy', valor: reservasHoy.value.length, destacada: false },
        { clave: 'pendientes', titulo: 'Pendientes', valor: contar('pendiente'), destacada: true },
        { clave: 'completadas', titulo: 'Completadas', valor: contar('completada'), destacada: false },
        { clave: 'canceladas', titulo: 'Canceladas', valor: contar('cancelada'), destacada: false },
    ]
})

function turnoDeHoy(persona) {
    return persona.turnos.find((turno) => turno.dia_semana === diaIso)
}

const personalHoy = computed(() =>
    personal
        .filter((persona) => persona.activo && turnoDeHoy(persona))
        .map((persona) => {
            const turno = turnoDeHoy(persona)

            return {
                id: persona.id,
                nombre: `${persona.nombre} ${persona.apellido}`,
                horario: `${hora(turno.hora_inicio)} – ${hora(turno.hora_fin)}`,
                // Calculado por el Dashboard cruzando reservas y personal.
                // No viene de /api/personal.
                reservasActivas: reservasHoy.value.filter(
                    (reserva) =>
                        reserva.personal_id === persona.id &&
                        reserva.estado !== 'cancelada'
                ).length,
            }
        })
)
</script>

<template>
    <AppLayout>
        <div
            class="min-h-[calc(100vh-73px)] bg-[#1C1B1A] lg:min-h-[calc(100vh-69px)]"
        >
            <div class="mx-auto max-w-7xl px-6 py-8">

                <!-- Cabecera del Dashboard -->
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-semibold text-[#F3ECE0]">
                            Dashboard
                        </h1>
                        <p class="mt-1 text-sm text-[#BEB3A6] first-letter:uppercase">
                            {{ fechaLarga }}
                        </p>
                    </div>

                        <Button label="+ Nueva reserva" />
                </div>

                <!-- Tarjetas de resumen -->
                <div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div
                        v-for="tarjeta in tarjetas"
                        :key="tarjeta.clave"
                        class="rounded-xl border p-5"
                        :class="
                            tarjeta.destacada
                                ? 'border-[#867158] bg-[#403629]'
                                : 'border-[#47413B] bg-[#292725]'
                        "
                    >
                        <p
                            class="text-sm"
                            :class="
                                tarjeta.destacada
                                    ? 'text-[#E0CDAF]'
                                    : 'text-[#C7BEB3]'
                            "
                        >
                            {{ tarjeta.titulo }}
                        </p>

                        <p class="mt-2 text-3xl font-semibold text-[#F3ECE0]">
                            {{ tarjeta.valor }}
                        </p>
                    </div>
                </div>

                <!-- Agenda y personal -->
                <div class="mt-6 grid gap-6 lg:grid-cols-3">

                    <!-- Agenda de hoy -->
                    <section
                        class="rounded-xl border border-[#47413B] bg-[#292725] lg:col-span-2"
                    >
                        <div
                            class="flex items-center justify-between border-b border-[#47413B] px-5 py-4"
                        >
                            <h2 class="text-base font-semibold text-[#F3ECE0]">
                                Agenda de hoy
                            </h2>

                            <a
                                href="#"
                                class="rounded text-sm text-[#D4C3A3] transition-colors hover:text-[#F3ECE0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3]"
                            >
                                Ver todas →
                            </a>
                        </div>

                        <ul
                            v-if="reservasHoy.length"
                            class="divide-y divide-[#47413B]"
                        >
                            <li
                                v-for="reserva in reservasHoy"
                                :key="reserva.id"
                                class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-4 transition-colors hover:bg-[#302D29]"
                            >
                                <p
                                    class="order-1 text-sm font-medium text-[#F3ECE0] sm:w-32"
                                >
                                    {{ hora(reserva.hora_inicio) }} –
                                    {{ hora(reserva.hora_fin) }}
                                </p>

                                <StatusBadge
                                    :status="reserva.estado"
                                    class="order-2 ml-auto sm:order-3 sm:ml-0"
                                />

                                <div
                                    class="order-3 w-full min-w-0 sm:order-2 sm:w-auto sm:flex-1"
                                >
                                    <p
                                        class="truncate text-sm font-medium text-[#F3ECE0]"
                                    >
                                        {{ reserva.cliente.nombre }}
                                        {{ reserva.cliente.apellido }}
                                    </p>

                                    <p class="truncate text-sm text-[#BEB3A6]">
                                        {{ nombresServicios(reserva) }} ·
                                        {{ reserva.personal.nombre }}
                                    </p>
                                </div>
                            </li>
                        </ul>

                        <div
                            v-else
                            class="px-5 py-12 text-center"
                        >
                            <p class="text-sm text-[#BEB3A6]">
                                No hay reservas para hoy.
                            </p>

                            <Button
                                label="+ Nueva reserva"
                                variant="secondary"
                                class="mt-4"
                            />
                        </div>
                    </section>

                    <!-- Personal de hoy -->
                    <section
                        class="rounded-xl border border-[#51483F] bg-[#34302B]"
                    >
                        <div class="border-b border-[#51483F] px-5 py-4">
                            <h2 class="text-base font-semibold text-[#F3ECE0]">
                                Personal de hoy
                            </h2>
                        </div>

                        <ul
                            v-if="personalHoy.length"
                            class="divide-y divide-[#51483F]"
                        >
                            <li
                                v-for="persona in personalHoy"
                                :key="persona.id"
                                class="flex items-center justify-between gap-4 px-5 py-4"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-medium text-[#F3ECE0]"
                                    >
                                        {{ persona.nombre }}
                                    </p>

                                    <p class="text-sm text-[#BEB3A6]">
                                        {{ persona.horario }}
                                    </p>
                                </div>

                                <p
                                    class="shrink-0 rounded-md bg-[#453D33] px-2.5 py-1 text-sm text-[#E0CDAF]"
                                >
                                    {{ persona.reservasActivas }}
                                    {{
                                        persona.reservasActivas === 1
                                            ? 'reserva'
                                            : 'reservas'
                                    }}
                                </p>
                            </li>
                        </ul>

                        <p
                            v-else
                            class="px-5 py-8 text-center text-sm text-[#BEB3A6]"
                        >
                            Ningún profesional tiene turno hoy.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>



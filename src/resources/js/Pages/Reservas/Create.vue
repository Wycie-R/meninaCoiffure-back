<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import Dropdown from '../../Components/Dropdown.vue'
import PageHeader from '../../Components/PageHeader.vue'
import Icono from '../../Components/Icono.vue'

const clientes = [
    { id: 1, nombre: 'Ana', apellido: 'Gómez' },
    { id: 2, nombre: 'Beatriz', apellido: 'López' },
]

const personal = [
    {
        id: 1,
        nombre: 'Carla',
        apellido: 'Estilista',
        activo: true,
        turnos: [1, 2, 3, 4, 5].map((dia) => ({
            dia_semana: dia,
            hora_inicio: '08:00:00',
            hora_fin: '18:00:00',
        })),
    },
    {
        id: 2,
        nombre: 'Diana',
        apellido: 'Colorista',
        activo: true,
        turnos: [1, 2, 3, 4, 5].map((dia) => ({
            dia_semana: dia,
            hora_inicio: '09:00:00',
            hora_fin: '17:00:00',
        })),
    },
]

const servicios = [
    {
        id: 1,
        categoria_id: 1,
        nombre: 'Corte Femenino',
        descripcion: null,
        duracion_minutos: 45,
        activo: true,
    },
    {
        id: 2,
        categoria_id: 1,
        nombre: 'Baño de Crema / Nutrición',
        descripcion: null,
        duracion_minutos: 30,
        activo: true,
    },
    {
        id: 3,
        categoria_id: 2,
        nombre: 'Coloración Completa',
        descripcion: null,
        duracion_minutos: 90,
        activo: true,
    },
    {
        id: 4,
        categoria_id: 2,
        nombre: 'Decoloración Extrema (Descontinuado)',
        descripcion: null,
        duracion_minutos: 120,
        activo: false,
    },
]

const form = ref({
    cliente_id: null,
    personal_id: null,
    fecha: '',
    hora_inicio: '',
    hora_fin: '',
    servicios: [],
})

const intentoGuardar = ref(false)
const avisoSinConexion = ref(false)

const opcionesClientes = clientes.map((c) => ({
    label: `${c.nombre} ${c.apellido}`,
    value: c.id,
}))

const opcionesPersonal = personal
    .filter((p) => p.activo)
    .map((p) => ({
        label: `${p.nombre} ${p.apellido}`,
        value: p.id,
    }))

const serviciosActivos = servicios.filter((s) => s.activo)

function fechaLocalHoy() {
    const hoy = new Date()
    const mes = String(hoy.getMonth() + 1).padStart(2, '0')
    const dia = String(hoy.getDate()).padStart(2, '0')

    return `${hoy.getFullYear()}-${mes}-${dia}`
}

const fechaMinima = fechaLocalHoy()

const nombresDias = {
    1: 'lunes',
    2: 'martes',
    3: 'miércoles',
    4: 'jueves',
    5: 'viernes',
    6: 'sábados',
    7: 'domingos',
}

const horarioProfesional = computed(() => {
    const profesional = personal.find(
        (p) => p.id === form.value.personal_id
    )

    if (!profesional || !profesional.turnos.length) return ''

    const dias = profesional.turnos
        .map((t) => t.dia_semana)
        .sort((a, b) => a - b)

    const consecutivos = dias.every(
        (dia, i) => i === 0 || dia === dias[i - 1] + 1
    )

    const textoDias = consecutivos && dias.length > 1
        ? `de ${nombresDias[dias[0]]} a ${nombresDias[dias.at(-1)]}`
        : `los ${dias.map((d) => nombresDias[d]).join(', ')}`

    const { hora_inicio, hora_fin } = profesional.turnos[0]

    return `Atiende ${textoDias}, de ${hora_inicio.slice(0, 5)} a ${hora_fin.slice(0, 5)}`
})

const duracionTotal = computed(() =>
    serviciosActivos
        .filter((s) => form.value.servicios.includes(s.id))
        .reduce((total, s) => total + s.duracion_minutos, 0)
)

const serviciosElegidos = computed(() =>
    serviciosActivos.filter((s) => form.value.servicios.includes(s.id))
)

const clienteElegido = computed(() => {
    const cliente = clientes.find((c) => c.id === form.value.cliente_id)
    return cliente ? `${cliente.nombre} ${cliente.apellido}` : ''
})

const profesionalElegido = computed(() => {
    const profesional = personal.find((p) => p.id === form.value.personal_id)
    return profesional ? `${profesional.nombre} ${profesional.apellido}` : ''
})

const fechaElegida = computed(() => {
    if (!form.value.fecha) return ''
    const [anio, mes, dia] = form.value.fecha.split('-').map(Number)

    return new Date(anio, mes - 1, dia).toLocaleDateString('es-PY', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    })
})

function aMinutos(hora) {
    const [h, m] = hora.slice(0, 5).split(':').map(Number)
    return h * 60 + m
}

function aHora(minutos) {
    return `${String(Math.floor(minutos / 60)).padStart(2, '0')}:${String(minutos % 60).padStart(2, '0')}`
}

function aISO(fecha) {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0')
    const dia = String(fecha.getDate()).padStart(2, '0')
    return `${fecha.getFullYear()}-${mes}-${dia}`
}

const profesionalActual = computed(() => personal.find((p) => p.id === form.value.personal_id) ?? null)

const inicioBloque = ref(0)
const diasPorBloque = 14

const diasVisibles = computed(() => {
    const hoy = new Date()

    return Array.from({ length: diasPorBloque }, (_, i) => {
        const fecha = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() + inicioBloque.value + i)
        const diaIso = fecha.getDay() || 7
        const atiende = !profesionalActual.value || profesionalActual.value.turnos.some((t) => t.dia_semana === diaIso)

        return {
            iso: aISO(fecha),
            semana: fecha.toLocaleDateString('es-PY', { weekday: 'short' }).replace('.', ''),
            dia: fecha.getDate(),
            mes: fecha.toLocaleDateString('es-PY', { month: 'short' }).replace('.', ''),
            esHoy: inicioBloque.value + i === 0,
            disponible: atiende,
        }
    })
})

const rangoVisible = computed(() => {
    const primero = diasVisibles.value[0]
    const ultimo = diasVisibles.value.at(-1)
    return `${primero.dia} ${primero.mes} – ${ultimo.dia} ${ultimo.mes}`
})

function moverBloque(direccion) {
    inicioBloque.value = Math.max(0, inicioBloque.value + direccion * diasPorBloque)
}

function elegirDia(dia) {
    if (!dia.disponible) return
    form.value.fecha = dia.iso
}

const turnoDelDia = computed(() => {
    if (!form.value.fecha) return null

    const [anio, mes, dia] = form.value.fecha.split('-').map(Number)
    const diaIso = new Date(anio, mes - 1, dia).getDay() || 7

    if (!profesionalActual.value) return { hora_inicio: '08:00', hora_fin: '18:00' }
    return profesionalActual.value.turnos.find((t) => t.dia_semana === diaIso) ?? null
})

const horarios = computed(() => {
    const turno = turnoDelDia.value
    if (!turno) return []

    const inicio = aMinutos(turno.hora_inicio)
    const fin = aMinutos(turno.hora_fin)
    const necesario = duracionTotal.value || 30
    const ahora = new Date()
    const esHoy = form.value.fecha === fechaMinima
    const minutosAhora = ahora.getHours() * 60 + ahora.getMinutes()
    const lista = []

    for (let minuto = inicio; minuto + necesario <= fin; minuto += 30) {
        lista.push({
            hora: aHora(minuto),
            disponible: !esHoy || minuto > minutosAhora,
            tarde: minuto >= 13 * 60,
        })
    }

    return lista
})

const horariosManana = computed(() => horarios.value.filter((h) => !h.tarde))
const horariosTarde = computed(() => horarios.value.filter((h) => h.tarde))

function elegirHora(horario) {
    if (!horario.disponible) return
    form.value.hora_inicio = horario.hora
}

watch([() => form.value.hora_inicio, duracionTotal], ([inicio, minutos]) => {
    form.value.hora_fin = inicio && minutos ? aHora(aMinutos(inicio) + minutos) : ''
})

watch([() => form.value.personal_id, () => form.value.fecha], () => {
    if (form.value.fecha && !turnoDelDia.value) {
        form.value.fecha = ''
    }
    if (form.value.hora_inicio && !horarios.value.some((h) => h.hora === form.value.hora_inicio && h.disponible)) {
        form.value.hora_inicio = ''
    }
})

function alternarServicio(id) {
    const lista = form.value.servicios

    form.value.servicios = lista.includes(id)
        ? lista.filter((s) => s !== id)
        : [...lista, id]
}

const errores = computed(() => {
    const f = form.value
    const e = {}

    if (!f.cliente_id) e.cliente_id = 'Selecciona un cliente.'
    if (!f.personal_id) e.personal_id = 'Selecciona un profesional.'

    if (!f.fecha) {
        e.fecha = 'Elige el día de la cita.'
    } else if (f.fecha < fechaMinima) {
        e.fecha = 'La fecha no puede ser anterior a hoy.'
    }

    if (!f.hora_inicio) e.hora_inicio = 'Elige un horario.'

    if (!f.servicios.length) {
        e.servicios = 'Selecciona al menos un servicio.'
    }

    return e
})

function error(campo) {
    return intentoGuardar.value ? errores.value[campo] : ''
}

const payload = computed(() => ({
    cliente_id: form.value.cliente_id,
    personal_id: form.value.personal_id,
    fecha: form.value.fecha,
    hora_inicio: form.value.hora_inicio,
    hora_fin: form.value.hora_fin,
    servicios: [...form.value.servicios],
}))

watch(payload, () => {
    avisoSinConexion.value = false
})

function guardar() {
    intentoGuardar.value = true

    if (Object.keys(errores.value).length) return

    avisoSinConexion.value = true
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader
                titulo="Nueva reserva"
                descripcion="Agenda una cita eligiendo cliente, profesional, horario y servicios."
            />

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <form
                    class="min-w-0 rounded-[28px] border border-hilo bg-velo"
                    novalidate
                    @submit.prevent="guardar"
                >
                    <section class="p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">1</span>
                            Cliente y profesional
                        </h2>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div class="min-w-0">
                                <p class="mb-1.5 text-sm font-medium text-perla">Cliente</p>
                                <Dropdown
                                    v-model="form.cliente_id"
                                    :options="opcionesClientes"
                                    placeholder="Seleccionar cliente"
                                />
                                <p v-if="error('cliente_id')" class="mt-1.5 text-sm text-borgona">
                                    {{ error('cliente_id') }}
                                </p>
                            </div>

                            <div class="min-w-0">
                                <p class="mb-1.5 text-sm font-medium text-perla">Profesional</p>
                                <Dropdown
                                    v-model="form.personal_id"
                                    :options="opcionesPersonal"
                                    placeholder="Seleccionar profesional"
                                />
                                <p v-if="error('personal_id')" class="mt-1.5 text-sm text-borgona">
                                    {{ error('personal_id') }}
                                </p>
                                <p v-else-if="horarioProfesional" class="mt-1.5 flex items-center gap-1.5 text-sm text-bruma">
                                    <Icono name="pendiente" class="h-4 w-4 text-orquidea" />
                                    {{ horarioProfesional }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">2</span>
                            Servicios
                        </h2>

                        <fieldset class="mt-5">
                            <legend class="sr-only">Servicios de la reserva</legend>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label
                                    v-for="servicio in serviciosActivos"
                                    :key="servicio.id"
                                    class="relative flex cursor-pointer items-center gap-3 rounded-lg border p-3.5 transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-orquidea"
                                    :class="
                                        form.servicios.includes(servicio.id)
                                            ? 'border-orquidea bg-orquidea/12'
                                            : 'border-hilo bg-terciopelo hover:border-hilo-fuerte'
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        class="sr-only"
                                        :checked="form.servicios.includes(servicio.id)"
                                        @change="alternarServicio(servicio.id)"
                                    />

                                    <span
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border transition-colors"
                                        :class="
                                            form.servicios.includes(servicio.id)
                                                ? 'border-orquidea bg-orquidea text-noche'
                                                : 'border-hilo-fuerte text-orquidea'
                                        "
                                    >
                                        <Icono
                                            :name="form.servicios.includes(servicio.id) ? 'completada' : 'servicios'"
                                            class="h-5 w-5"
                                        />
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-perla">{{ servicio.nombre }}</span>
                                        <span class="mt-0.5 inline-flex items-center gap-1 text-xs text-bruma">
                                            <Icono name="pendiente" class="h-3.5 w-3.5" />
                                            {{ servicio.duracion_minutos }} min
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <p v-if="error('servicios')" class="mt-2 text-sm text-borgona">
                                {{ error('servicios') }}
                            </p>
                        </fieldset>
                    </section>

                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">3</span>
                            Fecha y horario
                        </h2>

                        <div class="mt-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-perla">Día</p>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 text-bruma transition-colors enabled:hover:bg-terciopelo enabled:hover:text-perla disabled:opacity-30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                        :disabled="inicioBloque === 0"
                                        aria-label="Días anteriores"
                                        @click="moverBloque(-1)"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" /></svg>
                                    </button>
                                    <span class="min-w-32 text-center text-sm tabular-nums text-bruma">{{ rangoVisible }}</span>
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 text-bruma transition-colors hover:bg-terciopelo hover:text-perla focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                        aria-label="Días siguientes"
                                        @click="moverBloque(1)"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" /></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-7 gap-1.5 sm:gap-2" role="group" aria-label="Elegir día">
                                <button
                                    v-for="dia in diasVisibles"
                                    :key="dia.iso"
                                    type="button"
                                    class="relative flex flex-col items-center rounded-lg border py-2 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                    :class="
                                        form.fecha === dia.iso
                                            ? 'border-orquidea bg-orquidea text-noche'
                                            : dia.disponible
                                                ? 'border-hilo bg-terciopelo text-perla hover:border-orquidea/70'
                                                : 'cursor-not-allowed border-hilo bg-transparent text-hilo-fuerte'
                                    "
                                    :disabled="!dia.disponible"
                                    :aria-pressed="form.fecha === dia.iso"
                                    :aria-label="`${dia.semana} ${dia.dia} de ${dia.mes}${dia.disponible ? '' : ', no atiende'}`"
                                    @click="elegirDia(dia)"
                                >
                                    <span
                                        class="text-[0.6875rem] font-medium capitalize"
                                        :class="form.fecha === dia.iso ? 'text-noche/70' : dia.disponible ? 'text-bruma' : ''"
                                    >
                                        {{ dia.semana }}
                                    </span>
                                    <span class="font-display text-xl leading-tight tabular-nums sm:text-2xl">{{ dia.dia }}</span>
                                    <span
                                        v-if="dia.esHoy"
                                        class="absolute bottom-1 h-1 w-1 rounded-full"
                                        :class="form.fecha === dia.iso ? 'bg-noche' : 'bg-orquidea'"
                                        aria-hidden="true"
                                    ></span>
                                </button>
                            </div>

                            <p v-if="error('fecha')" class="mt-2 text-sm text-borgona">{{ error('fecha') }}</p>
                            <p v-else-if="profesionalActual" class="mt-2 text-xs text-niebla">
                                Los días apagados son los que {{ profesionalActual.nombre }} no atiende.
                            </p>
                        </div>

                        <div class="mt-6">
                            <p class="text-sm font-medium text-perla">Horario</p>

                            <div
                                v-if="!form.fecha"
                                class="mt-2 flex items-center gap-3 rounded-lg border border-dashed border-hilo px-4 py-5 text-sm text-niebla"
                            >
                                <Icono name="pendiente" class="h-5 w-5 shrink-0 text-hilo-fuerte" />
                                Elige un día para ver los horarios disponibles.
                            </div>

                            <div
                                v-else-if="!horarios.length"
                                class="mt-2 rounded-lg border border-dashed border-hilo px-4 py-5 text-sm text-niebla"
                            >
                                No hay horarios en los que entren los servicios elegidos ese día.
                            </div>

                            <div v-else class="mt-2 space-y-4">
                                <div v-for="bloque in [{ titulo: 'Mañana', lista: horariosManana }, { titulo: 'Tarde', lista: horariosTarde }]" :key="bloque.titulo">
                                    <template v-if="bloque.lista.length">
                                        <p class="mb-2 text-xs font-medium text-niebla">{{ bloque.titulo }}</p>
                                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-6" role="group" :aria-label="`Horarios de la ${bloque.titulo.toLowerCase()}`">
                                            <button
                                                v-for="horario in bloque.lista"
                                                :key="horario.hora"
                                                type="button"
                                                class="rounded-lg border py-2 font-display text-lg tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                                :class="
                                                    form.hora_inicio === horario.hora
                                                        ? 'border-orquidea bg-orquidea text-noche'
                                                        : horario.disponible
                                                            ? 'border-hilo bg-terciopelo text-perla hover:border-orquidea/70 hover:text-perla'
                                                            : 'cursor-not-allowed border-hilo text-hilo-fuerte line-through'
                                                "
                                                :disabled="!horario.disponible"
                                                :aria-pressed="form.hora_inicio === horario.hora"
                                                @click="elegirHora(horario)"
                                            >
                                                {{ horario.hora }}
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <p v-if="error('hora_inicio')" class="mt-2 text-sm text-borgona">{{ error('hora_inicio') }}</p>

                            <div
                                v-if="form.hora_inicio && form.hora_fin"
                                class="mt-4 flex items-center gap-3 rounded-lg border border-orquidea/40 bg-orquidea/12 px-4 py-3 text-sm text-perla"
                            >
                                <Icono name="completada" class="h-5 w-5 shrink-0 text-orquidea" />
                                <span class="tabular-nums">
                                    De <strong class="font-semibold text-perla">{{ form.hora_inicio }}</strong>
                                    a <strong class="font-semibold text-perla">{{ form.hora_fin }}</strong>
                                    ({{ duracionTotal }} min según los servicios)
                                </span>
                            </div>
                            <p v-else-if="form.hora_inicio && !duracionTotal" class="mt-3 text-sm text-niebla">
                                Elige los servicios para calcular la hora de fin.
                            </p>
                        </div>
                    </section>

                    <div
                        v-if="avisoSinConexion"
                        role="status"
                        class="mx-5 mb-5 flex items-start gap-2.5 rounded-lg border border-orquidea/40 bg-orquidea/12 px-4 py-3 text-sm text-perla sm:mx-6"
                    >
                        <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                        El formulario está completo, pero todavía no está conectado al servidor. La reserva no se guardó.
                    </div>

                    <div class="flex flex-col-reverse gap-3 rounded-b-[28px] border-t border-hilo bg-velo p-5 sm:flex-row sm:justify-end sm:px-6">
                        <Button label="Cancelar" href="/reservas" variant="secondary" class="w-full sm:w-auto" />
                        <Button type="submit" class="w-full sm:w-auto">
                            <Icono name="completada" class="h-4 w-4" />
                            Guardar reserva
                        </Button>
                    </div>
                </form>

                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <p class="mb-3 text-sm font-medium text-bruma">Resumen de la cita</p>

                    <div class="relative overflow-hidden rounded-[28px] bg-orquidea-hondo/35 ring-1 ring-inset ring-orquidea/25">
                        
                        <div class="p-5">
                            <p class="text-sm text-orquidea-claro">Cliente</p>
                            <p class="mt-1 font-display text-2xl" :class="clienteElegido ? 'text-perla' : 'text-niebla'">
                                {{ clienteElegido || 'Sin elegir' }}
                            </p>

                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex items-start gap-2.5">
                                    <Icono name="personal" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    <div>
                                        <dt class="sr-only">Profesional</dt>
                                        <dd :class="profesionalElegido ? 'text-perla' : 'text-niebla'">
                                            {{ profesionalElegido || 'Profesional sin elegir' }}
                                        </dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <Icono name="reservas" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    <div>
                                        <dt class="sr-only">Fecha</dt>
                                        <dd class="first-letter:uppercase" :class="fechaElegida ? 'text-perla' : 'text-niebla'">
                                            {{ fechaElegida || 'Fecha sin elegir' }}
                                        </dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <Icono name="pendiente" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    <div>
                                        <dt class="sr-only">Horario</dt>
                                        <dd class="tabular-nums" :class="form.hora_inicio ? 'text-perla' : 'text-niebla'">
                                            <template v-if="form.hora_inicio">
                                                {{ form.hora_inicio }}{{ form.hora_fin ? ` – ${form.hora_fin}` : '' }}
                                            </template>
                                            <template v-else>Horario sin elegir</template>
                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        <div class="border-t border-hilo-fuerte bg-noche/40 p-5">
                            <p class="text-sm text-orquidea-claro">Servicios</p>

                            <ul v-if="serviciosElegidos.length" class="mt-3 space-y-2">
                                <li
                                    v-for="servicio in serviciosElegidos"
                                    :key="servicio.id"
                                    class="flex items-baseline justify-between gap-3 text-sm"
                                >
                                    <span class="text-perla">{{ servicio.nombre }}</span>
                                    <span class="shrink-0 text-xs text-bruma">{{ servicio.duracion_minutos }} min</span>
                                </li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-niebla">Todavía no elegiste servicios.</p>

                            <div class="mt-4 flex items-baseline justify-between border-t border-hilo-fuerte pt-3">
                                <span class="text-sm text-bruma">Duración total</span>
                                <span class="font-display text-2xl tabular-nums text-perla">
                                    {{ duracionTotal }} <span class="text-base">min</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

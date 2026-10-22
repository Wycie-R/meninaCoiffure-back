<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import Dropdown from './Dropdown.vue'
import Icono from './Icono.vue'
import { api, mensajeError, primerosErrores } from '../api'
import { aHora, aISO, aMinutos, capitalizar, duracion, fechaLarga, hora } from '../formato'
import { claseEnfoque } from '../ui'
import { usePersonal } from '../personal'
import { usePermisos } from '../auth'
import { minutosReserva, nombrePersonal } from '../agenda'
import { diaSemanaDe, franjasUnidas, nombreDia, textoFranjas } from '../turnos'

const props = defineProps({
    reserva: {
        type: Object,
        required: true,
    },
    ahora: {
        type: Date,
        required: true,
    },
})

const eleccion = defineModel({ type: Object, required: true })

const { personal, cargarPersonal } = usePersonal()
const { esAdmin } = usePermisos()

onMounted(() => cargarPersonal())

const original = computed(() => ({
    personal_id: props.reserva.personal_id,
    fecha: String(props.reserva.fecha).slice(0, 10),
    hora_inicio: hora(props.reserva.hora_inicio),
}))

const minutos = computed(() => minutosReserva(props.reserva))
const traslado = computed(() => (props.reserva.a_domicilio ? Number(props.reserva.minutos_traslado) || 0 : 0))
const hoy = computed(() => aISO(props.ahora))

function porNombre(a, b) {
    return a.nombre.localeCompare(b.nombre, 'es') || a.apellido.localeCompare(b.apellido, 'es')
}

const equipo = computed(() => {
    const activos = personal.value.filter((p) => p.activo || p.id === original.value.personal_id).sort(porNombre)
    if (activos.some((p) => p.id === original.value.personal_id) || !props.reserva.personal) return activos
    return [props.reserva.personal, ...activos]
})

const opcionesPersonal = computed(() =>
    equipo.value.map((p) => ({
        value: p.id,
        label: `${p.nombre} ${p.apellido}`,
        detalle: p.id === original.value.personal_id ? 'Tiene la cita ahora' : p.turnos?.length === 0 ? 'Sin horario asignado' : '',
    }))
)

const profesional = computed(() => equipo.value.find((p) => p.id === eleccion.value.personal_id) ?? props.reserva.personal ?? null)
const nombreProfesional = computed(() => profesional.value?.nombre ?? 'la profesional')
const turnos = computed(() => profesional.value?.turnos)

function elegirProfesional(id) {
    if (id === eleccion.value.personal_id) return
    eleccion.value = { ...eleccion.value, personal_id: id, hora_inicio: '' }
}

const inicioBloque = ref(0)
const diasPorBloque = 14

function atiende(dia) {
    if (turnos.value === undefined) return true
    return franjasUnidas(turnos.value, dia).length > 0
}

const diasVisibles = computed(() => {
    const base = props.ahora

    return Array.from({ length: diasPorBloque }, (_, i) => {
        const fecha = new Date(base.getFullYear(), base.getMonth(), base.getDate() + inicioBloque.value + i)
        const iso = aISO(fecha)

        return {
            iso,
            semana: fecha.toLocaleDateString('es-PY', { weekday: 'short' }).replace('.', ''),
            dia: fecha.getDate(),
            mes: fecha.toLocaleDateString('es-PY', { month: 'short' }).replace('.', ''),
            esHoy: inicioBloque.value + i === 0,
            esOriginal: iso === original.value.fecha,
            disponible: atiende(fecha.getDay() || 7),
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

function centrarEnFecha(iso) {
    const desde = new Date(props.ahora.getFullYear(), props.ahora.getMonth(), props.ahora.getDate())
    const [anio, mes, dia] = iso.split('-').map(Number)
    const diferencia = Math.round((new Date(anio, mes - 1, dia) - desde) / 86400000)
    inicioBloque.value = diferencia > 0 ? Math.floor(diferencia / diasPorBloque) * diasPorBloque : 0
}

centrarEnFecha(eleccion.value.fecha || original.value.fecha)

function elegirDia(dia) {
    if (!dia.disponible || dia.iso === eleccion.value.fecha) return
    eleccion.value = { ...eleccion.value, fecha: dia.iso, hora_inicio: '' }
}

const disponibilidad = ref(null)
const revisando = ref(false)
const errorAgenda = ref('')
let consulta = 0
let controlador = null

const MOTIVOS = {
    ocupado: 'ocupado',
    pasado: 'ya pasó',
    local_cerrado: 'el local está cerrado',
}

async function revisarAgenda() {
    controlador?.abort()
    const turno = ++consulta
    const { personal_id, fecha } = eleccion.value

    if (!personal_id || !fecha) {
        disponibilidad.value = null
        return
    }

    const parametros = new URLSearchParams({
        personal_id,
        fecha,
        duracion_minutos: minutos.value,
        intervalo: 30,
        excluir_reserva_id: props.reserva.id,
    })

    if (traslado.value) {
        parametros.set('a_domicilio', 1)
        parametros.set('minutos_traslado', traslado.value)
    }

    disponibilidad.value = null
    errorAgenda.value = ''
    revisando.value = true
    controlador = new AbortController()

    const respuesta = await api(`/api/disponibilidad?${parametros}`, { senal: controlador.signal })
    if (turno !== consulta) return

    revisando.value = false
    controlador = null

    if (!respuesta.ok) {
        errorAgenda.value =
            respuesta.status === 422
                ? Object.values(primerosErrores(respuesta.cuerpo))[0] ?? mensajeError(respuesta, 'revisar los horarios libres')
                : mensajeError(respuesta, 'revisar los horarios libres')
        return
    }

    disponibilidad.value = respuesta.cuerpo?.data ?? null
}

watch(() => [eleccion.value.personal_id, eleccion.value.fecha], revisarAgenda, { immediate: true })

onBeforeUnmount(() => controlador?.abort())

const mismoDiaYProfesional = computed(
    () => eleccion.value.personal_id === original.value.personal_id && eleccion.value.fecha === original.value.fecha
)

function chocaConLaPropia(inicio) {
    if (!mismoDiaYProfesional.value) return false
    const desde = aMinutos(original.value.hora_inicio) - traslado.value
    const hasta = aMinutos(props.reserva.hora_fin) + traslado.value
    return inicio - traslado.value < hasta && inicio + minutos.value + traslado.value > desde
}

const horarios = computed(() =>
    (disponibilidad.value?.horarios ?? []).map((h) => {
        const inicio = aMinutos(h.hora)
        const actual = mismoDiaYProfesional.value && hora(h.hora) === original.value.hora_inicio
        const liberado = !h.disponible && h.motivo === 'ocupado' && chocaConLaPropia(inicio)
        const disponible = !actual && (Boolean(h.disponible) || liberado)

        return {
            hora: hora(h.hora),
            actual,
            disponible,
            motivo: actual || disponible ? '' : MOTIVOS[h.motivo] ?? 'no disponible',
            tarde: inicio >= 13 * 60,
        }
    })
)

const franjasDelDia = computed(() => {
    const delServidor = disponibilidad.value?.franjas
    if (Array.isArray(delServidor) && delServidor.length) {
        return delServidor.map(([desde, hasta]) => ({ inicio: aMinutos(desde), fin: aMinutos(hasta) }))
    }
    return turnos.value ? franjasUnidas(turnos.value, diaSemanaDe(eleccion.value.fecha)) : []
})

const textoDia = computed(() => {
    if (!franjasDelDia.value.length || !eleccion.value.fecha) return ''
    return `El ${nombreDia(diaSemanaDe(eleccion.value.fecha))} ${nombreProfesional.value} atiende ${textoFranjas(franjasDelDia.value)}.`
})

const horariosManana = computed(() => horarios.value.filter((h) => !h.tarde))
const horariosTarde = computed(() => horarios.value.filter((h) => h.tarde))
const sinHorariosLibres = computed(() => horarios.value.length > 0 && !horarios.value.some((h) => h.disponible))

function etiquetaHorario(horario) {
    if (horario.actual) return `${horario.hora}, horario actual de la cita`
    if (horario.motivo) return `${horario.hora}, ${horario.motivo}`
    return horario.hora
}

function elegirHora(horario) {
    if (!horario.disponible) return
    eleccion.value = { ...eleccion.value, hora_inicio: horario.hora }
}

const horaFin = computed(() =>
    eleccion.value.hora_inicio ? aHora(aMinutos(eleccion.value.hora_inicio) + minutos.value) : ''
)

const textoAntes = computed(
    () => `${capitalizar(fechaLarga(original.value.fecha))}, ${original.value.hora_inicio} con ${nombrePersonal(props.reserva)}`
)

const textoDespues = computed(() => {
    if (!eleccion.value.hora_inicio) return ''
    const quien = profesional.value ? `${profesional.value.nombre} ${profesional.value.apellido}` : nombrePersonal(props.reserva)
    return `${capitalizar(fechaLarga(eleccion.value.fecha))}, ${eleccion.value.hora_inicio} con ${quien}`
})

defineExpose({ horaFin })
</script>

<template>
    <div class="space-y-8">
        <section aria-labelledby="reagendar-titulo">
            <h3 id="reagendar-titulo" class="text-xl">Reagendar la cita</h3>
            <p class="mt-1 text-sm text-bruma">
                La cita dura {{ duracion(minutos) }}<template v-if="traslado">, más {{ duracion(traslado) }} de traslado antes y después</template>.
                Los servicios y el precio no cambian.
            </p>
        </section>

        <section v-if="esAdmin" aria-labelledby="reagendar-profesional">
            <p id="reagendar-profesional" class="text-sm font-medium text-perla">Profesional</p>
            <div class="mt-2">
                <Dropdown
                    :model-value="eleccion.personal_id"
                    :options="opcionesPersonal"
                    placeholder="Elige un profesional"
                    @update:model-value="elegirProfesional"
                />
            </div>
        </section>

        <section aria-labelledby="reagendar-dia">
            <div class="flex items-center justify-between gap-3">
                <p id="reagendar-dia" class="text-sm font-medium text-perla">Día</p>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-bruma transition-colors enabled:hover:bg-raso enabled:hover:text-perla disabled:opacity-30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                        :disabled="inicioBloque === 0"
                        aria-label="Días anteriores"
                        @click="moverBloque(-1)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" /></svg>
                    </button>
                    <span class="min-w-32 text-center text-sm tabular-nums text-bruma">{{ rangoVisible }}</span>
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-bruma transition-colors hover:bg-raso hover:text-perla focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                        aria-label="Días siguientes"
                        @click="moverBloque(1)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" /></svg>
                    </button>
                </div>
            </div>

            <div class="mt-2 grid grid-cols-7 gap-1.5" role="group" aria-label="Elegir día">
                <button
                    v-for="dia in diasVisibles"
                    :key="dia.iso"
                    type="button"
                    class="relative flex flex-col items-center rounded-lg border py-2 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                    :class="
                        eleccion.fecha === dia.iso
                            ? 'border-orquidea bg-orquidea text-noche'
                            : dia.disponible
                                ? 'border-hilo bg-raso text-perla hover:border-orquidea/70'
                                : 'cursor-not-allowed border-hilo bg-transparent text-hilo-fuerte'
                    "
                    :disabled="!dia.disponible"
                    :aria-pressed="eleccion.fecha === dia.iso"
                    :aria-label="`${dia.semana} ${dia.dia} de ${dia.mes}${dia.esOriginal ? ', día actual de la cita' : ''}${dia.disponible ? '' : ', no atiende'}`"
                    @click="elegirDia(dia)"
                >
                    <span
                        class="text-[0.6875rem] font-medium capitalize"
                        :class="eleccion.fecha === dia.iso ? 'text-noche/70' : dia.disponible ? 'text-bruma' : ''"
                    >
                        {{ dia.semana }}
                    </span>
                    <span class="font-display text-xl leading-tight tabular-nums">{{ dia.dia }}</span>
                    <span
                        v-if="dia.esHoy || dia.esOriginal"
                        class="absolute bottom-1 h-1 w-1 rounded-full"
                        :class="eleccion.fecha === dia.iso ? 'bg-noche' : dia.esOriginal ? 'bg-miel' : 'bg-orquidea'"
                        aria-hidden="true"
                    ></span>
                </button>
            </div>

            <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-bruma">
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-miel" aria-hidden="true"></span>Día actual de la cita</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-orquidea" aria-hidden="true"></span>Hoy</span>
            </p>
        </section>

        <section aria-labelledby="reagendar-hora">
            <p id="reagendar-hora" class="text-sm font-medium text-perla">Horario</p>
            <p v-if="textoDia" class="mt-1 text-xs text-bruma">{{ textoDia }}</p>

            <p
                v-if="errorAgenda"
                role="alert"
                class="mt-2 flex items-start gap-2.5 rounded-lg border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
            >
                <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    {{ errorAgenda }}
                    <button type="button" class="rounded underline underline-offset-4" :class="claseEnfoque" @click="revisarAgenda()">
                        Reintentar
                    </button>
                </span>
            </p>

            <div
                v-else-if="revisando || !disponibilidad"
                class="mt-2 grid grid-cols-4 gap-2"
                aria-busy="true"
                :aria-label="`Revisando la agenda de ${nombreProfesional}`"
            >
                <div v-for="n in 8" :key="n" class="h-11 animate-pulse rounded-lg bg-raso"></div>
            </div>

            <div v-else-if="!horarios.length" class="mt-2 rounded-lg border border-dashed border-hilo px-4 py-5 text-sm text-bruma">
                {{
                    franjasDelDia.length
                        ? 'Ese día no hay un espacio en el que entre la cita completa. Prueba con otro día.'
                        : `Ese día ${nombreProfesional} no tiene turno. Prueba con otro día.`
                }}
            </div>

            <div v-else class="mt-2 space-y-4">
                <div v-for="bloque in [{ titulo: 'Mañana', lista: horariosManana }, { titulo: 'Tarde', lista: horariosTarde }]" :key="bloque.titulo">
                    <template v-if="bloque.lista.length">
                        <p class="mb-2 text-xs font-medium text-bruma">{{ bloque.titulo }}</p>
                        <div class="grid grid-cols-4 gap-2" role="group" :aria-label="`Horarios de la ${bloque.titulo.toLowerCase()}`">
                            <button
                                v-for="horario in bloque.lista"
                                :key="horario.hora"
                                type="button"
                                class="rounded-lg border py-2 font-display text-lg tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                :class="
                                    eleccion.hora_inicio === horario.hora
                                        ? 'border-orquidea bg-orquidea text-noche'
                                        : horario.actual
                                            ? 'cursor-default border-miel/50 bg-miel/10 text-miel'
                                            : horario.disponible
                                                ? 'border-hilo bg-raso text-perla hover:border-orquidea/70'
                                                : 'cursor-not-allowed border-hilo text-hilo-fuerte line-through'
                                "
                                :disabled="!horario.disponible"
                                :title="horario.actual || horario.motivo ? etiquetaHorario(horario) : undefined"
                                :aria-label="etiquetaHorario(horario)"
                                :aria-pressed="eleccion.hora_inicio === horario.hora"
                                @click="elegirHora(horario)"
                            >
                                {{ horario.hora }}
                                <span v-if="horario.actual" class="block font-sans text-[0.6875rem] font-medium leading-tight" aria-hidden="true">
                                    Actual
                                </span>
                            </button>
                        </div>
                    </template>
                </div>

                <p v-if="sinHorariosLibres" class="text-sm text-bruma">
                    Ese día ya no quedan horarios libres para esta cita. Prueba con otro día.
                </p>
            </div>
        </section>

        <section
            aria-live="polite"
            class="rounded-2xl border px-4 py-4 text-sm"
            :class="textoDespues ? 'border-orquidea/40 bg-orquidea/12' : 'border-dashed border-hilo'"
        >
            <dl class="grid grid-cols-[4.5rem_minmax(0,1fr)] gap-x-3 gap-y-2">
                <dt class="text-bruma">Antes</dt>
                <dd class="tabular-nums text-bruma" :class="{ 'line-through decoration-hilo-fuerte': textoDespues }">
                    {{ textoAntes }}
                </dd>
                <dt class="text-bruma">Ahora</dt>
                <dd class="tabular-nums" :class="textoDespues ? 'text-perla' : 'text-bruma'">
                    <template v-if="textoDespues">
                        <strong class="font-semibold">{{ textoDespues }}</strong>
                        <span class="block text-bruma">Termina a las {{ horaFin }}.</span>
                    </template>
                    <template v-else>Elige un día y un horario.</template>
                </dd>
            </dl>
        </section>
    </div>
</template>

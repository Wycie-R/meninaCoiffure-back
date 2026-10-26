<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Button from './Button.vue'
import EditorSemana from './EditorSemana.vue'
import Icono from './Icono.vue'
import Campo from './Campo.vue'
import Modal from './Modal.vue'
import { api, mensajeError, primerosErrores } from '../api'
import { fechaCorta, fechaLarga, hora, hoyISO } from '../formato'
import { claseEntrada } from '../ui'
import {
    comoFranjas,
    semanaDesdeFranjas,
    franjasDesdeSemana,
    firmaSemana,
    validarSemana,
    semanaTieneErrores,
    erroresVacios,
    erroresSemanaDesdeServidor,
    actualizarHorarioSalon,
    useCierres,
    actualizarCierres,
    finDelCierre,
} from '../horarios'

const emit = defineEmits(['aviso'])

const cargando = ref(true)
const error = ref('')
const semana = ref([])
const base = ref('')
const errores = ref(erroresVacios())
const guardando = ref(false)
const fueraDeHorario = ref([])

function aplicar(horarios) {
    semana.value = semanaDesdeFranjas(comoFranjas(horarios))
    base.value = firmaSemana(semana.value)
    errores.value = erroresVacios()
}

async function cargar() {
    cargando.value = true
    error.value = ''
    const respuesta = await api('/api/horarios-atencion')
    cargando.value = false

    if (!respuesta.ok) {
        error.value = mensajeError(respuesta, 'cargar el horario del local')
        return
    }

    aplicar(respuesta.cuerpo?.data ?? [])
}

onMounted(() => {
    cargar()
    // Se pide de nuevo: la lista tiene que estar al día para agregar o quitar cierres
    cargarCierres({ forzar: true })
})

watch(semana, () => {
    if (semanaTieneErrores(errores.value)) errores.value = erroresVacios()
})

const cambios = computed(() => !cargando.value && firmaSemana(semana.value) !== base.value)

// Reservas futuras que quedaron fuera del nuevo horario (aviso informativo de la API)
const reservasAfectadas = computed(() =>
    fueraDeHorario.value.map((r) => {
        const persona = r.personal ? `${r.personal.nombre} ${r.personal.apellido ?? ''}`.trim() : 'Sin profesional'
        return {
            id: r.id,
            texto: `${fechaCorta(String(r.fecha).slice(0, 10))}, de ${hora(r.hora_inicio)} a ${hora(r.hora_fin)} · ${persona}`,
        }
    })
)

function descartar() {
    semana.value = semanaDesdeFranjas(JSON.parse(base.value))
    errores.value = erroresVacios()
}

async function guardar() {
    errores.value = validarSemana(semana.value)
    if (semanaTieneErrores(errores.value)) return

    const franjas = franjasDesdeSemana(semana.value)

    // El salón siempre debe tener horario: la API rechaza una semana sin días
    if (!franjas.length) {
        errores.value = { general: 'El salón debe atender al menos un día.', dias: {} }
        return
    }

    const horarios = franjas.map((f) => ({ dia_semana: f.dia_semana, hora_apertura: f.hora_inicio, hora_cierre: f.hora_fin }))

    guardando.value = true
    const respuesta = await api('/api/horarios-atencion', { metodo: 'PUT', datos: { horarios } })
    guardando.value = false

    if (!respuesta.ok) {
        if (respuesta.status === 422) {
            const crudos = primerosErrores(respuesta.cuerpo)
            const resultado = erroresSemanaDesdeServidor(crudos, franjas)
            errores.value = semanaTieneErrores(resultado) ? resultado : { general: mensajeError(respuesta, 'guardar el horario'), dias: {} }
        } else {
            errores.value = { general: mensajeError(respuesta, 'guardar el horario'), dias: {} }
        }
        return
    }

    aplicar(respuesta.cuerpo?.data ?? [])
    actualizarHorarioSalon(respuesta.cuerpo?.data ?? [])
    fueraDeHorario.value = respuesta.cuerpo?.reservas_fuera_de_horario ?? []
    emit('aviso', 'Horario del local actualizado.')
}

// ---------- Días sin atención: uno puntual o un período (vacaciones, un accidente) ----------

const { cierres, cierresCargados, cargarCierres } = useCierres()
const nuevoCierre = ref({ fecha: '', fecha_hasta: '', motivo: '' })
const erroresCierre = ref({})
const avisoCierre = ref('')
const guardandoCierre = ref(false)
const cierrePorQuitar = ref(null)
const quitandoCierre = ref(false)

// "lunes 2 de noviembre de 2026" o "Del lunes 2 al viernes 13 de noviembre de 2026"
function textoDelCierre(cierre) {
    const desde = String(cierre.fecha).slice(0, 10)
    const hasta = finDelCierre(cierre)
    if (desde === hasta) return fechaLarga(desde, true)
    return `Del ${fechaLarga(desde)} al ${fechaLarga(hasta, true)}`
}

const listaCierres = computed(() => {
    const lista = []
    for (const cierre of cierres.value) {
        lista.push({ ...cierre, esPeriodo: Boolean(cierre.fecha_hasta), texto: textoDelCierre(cierre) })
    }
    return lista
})

const textoBotonCierre = computed(() => {
    if (guardandoCierre.value) return 'Guardando…'
    if (nuevoCierre.value.fecha_hasta) return 'Cerrar esos días'
    return 'Cerrar ese día'
})

// Si cambia el primer día y el último quedó antes, el último se borra para no mandar un rango al revés
watch(
    () => nuevoCierre.value.fecha,
    (desde) => {
        if (nuevoCierre.value.fecha_hasta && desde && nuevoCierre.value.fecha_hasta < desde) {
            nuevoCierre.value.fecha_hasta = ''
        }
    }
)

async function cerrarDia() {
    erroresCierre.value = {}
    avisoCierre.value = ''

    if (!nuevoCierre.value.fecha) {
        erroresCierre.value = { fecha: 'Elige el día que el salón no trabaja.' }
        return
    }

    if (nuevoCierre.value.fecha_hasta && nuevoCierre.value.fecha_hasta < nuevoCierre.value.fecha) {
        erroresCierre.value = { fecha_hasta: 'El último día no puede ser anterior al primero.' }
        return
    }

    guardandoCierre.value = true
    const respuesta = await api('/api/cierres-salon', {
        metodo: 'POST',
        datos: {
            fecha: nuevoCierre.value.fecha,
            // Vacío = un solo día
            fecha_hasta: nuevoCierre.value.fecha_hasta || null,
            motivo: nuevoCierre.value.motivo.trim() || null,
        },
    })
    guardandoCierre.value = false

    if (!respuesta.ok) {
        if (respuesta.status === 422) {
            erroresCierre.value = primerosErrores(respuesta.cuerpo)
        } else {
            erroresCierre.value = { general: mensajeError(respuesta, 'cerrar ese día') }
        }
        return
    }

    actualizarCierres([...cierres.value, respuesta.cuerpo.data])
    // El mensaje avisa si esas fechas ya tenían citas agendadas
    avisoCierre.value = respuesta.cuerpo?.message ?? 'Listo.'
    nuevoCierre.value = { fecha: '', fecha_hasta: '', motivo: '' }
}

async function quitarCierre() {
    const cierre = cierrePorQuitar.value
    quitandoCierre.value = true
    const respuesta = await api(`/api/cierres-salon/${cierre.id}`, { metodo: 'DELETE' })
    quitandoCierre.value = false
    cierrePorQuitar.value = null

    if (!respuesta.ok && respuesta.status !== 404) {
        erroresCierre.value = { general: mensajeError(respuesta, 'quitar el cierre') }
        return
    }

    actualizarCierres(cierres.value.filter((c) => c.id !== cierre.id))
    avisoCierre.value = respuesta.cuerpo?.message ?? 'El salón vuelve a tener el horario de siempre.'
}
</script>

<template>
    <section aria-labelledby="config-horario">
        <div class="max-w-2xl">
            <h2 id="config-horario" class="text-3xl">Horario del local</h2>
            <p class="mt-2 text-sm leading-relaxed text-bruma">
                Los días y horas en que el salón atiende. Las citas, en el local o a domicilio, se agendan dentro de estas franjas;
                solo las marcadas como fuera de horario pueden quedar afuera.
            </p>
        </div>

        <div v-if="cargando" class="mt-6 max-w-2xl space-y-3" aria-busy="true" aria-label="Cargando el horario">
            <div v-for="n in 7" :key="n" class="h-[4.25rem] animate-pulse rounded-2xl bg-velo"></div>
        </div>

        <div v-else-if="error" role="alert" class="mt-6 max-w-2xl rounded-2xl border border-borgona/40 bg-borgona-hondo px-4 py-4 text-sm text-borgona">
            <p>{{ error }}</p>
            <Button class="mt-3" label="Reintentar" variant="secondary" @click="cargar" />
        </div>

        <div v-else class="mt-6 max-w-2xl">
            <div
                v-if="reservasAfectadas.length"
                role="note"
                class="mb-6 flex gap-3 rounded-2xl border border-miel/40 bg-miel/10 px-4 py-3.5 text-sm"
            >
                <Icono name="alerta" class="mt-0.5 h-5 w-5 shrink-0 text-miel" />
                <div class="min-w-0">
                    <p class="font-medium text-miel">Hay citas agendadas fuera del nuevo horario</p>
                    <p class="mt-1 text-perla">Siguen en pie. Si no se atenderán, reprográmalas o márcalas como fuera de horario desde Reservas.</p>
                    <ul class="mt-2 space-y-1 text-bruma">
                        <li v-for="r in reservasAfectadas" :key="r.id">{{ r.texto }}</li>
                    </ul>
                    <button type="button" class="mt-2 rounded text-sm text-miel underline underline-offset-4" @click="fueraDeHorario = []">Entendido</button>
                </div>
            </div>

            <EditorSemana
                v-model="semana"
                :errores="errores"
                texto-vacio="Sin días de atención: el salón debe atender al menos un día para recibir citas."
                texto-descanso="Cerrado"
            />

            <!-- Solo con cambios sin guardar la barra queda pegada abajo; si no, es un botón más al final -->
            <div
                class="-mx-1 mt-6 flex flex-col-reverse gap-3 px-1 sm:flex-row sm:justify-end"
                :class="cambios ? 'sticky bottom-0 z-10 bg-gradient-to-t from-noche from-60% to-transparent pb-[max(1rem,env(safe-area-inset-bottom))] pt-6' : 'pb-2'"
            >
                <Button v-if="cambios" label="Descartar cambios" variant="secondary" :disabled="guardando" @click="descartar" />
                <Button :disabled="guardando || !cambios" @click="guardar">
                    {{ guardando ? 'Guardando…' : 'Guardar horario' }}
                </Button>
            </div>
        </div>

        <!-- Días sin atención (uno o varios seguidos): no tocan el horario de la semana -->
        <section class="mt-12 max-w-2xl" aria-labelledby="config-cierres">
            <h2 id="config-cierres" class="text-3xl">Días sin atención</h2>
            <p class="mt-2 text-sm leading-relaxed text-bruma">
                Para un día en que el salón no trabaja ("este miércoles no abrimos") o varios seguidos, como vacaciones o un
                imprevisto: completa también el último día. El horario de la semana no cambia; solo esas fechas quedan sin citas,
                ni siquiera fuera de horario.
            </p>

            <p v-if="avisoCierre" role="status" class="mt-4 rounded-xl border border-miel/40 bg-miel/10 px-4 py-3 text-sm text-miel">
                {{ avisoCierre }}
            </p>
            <p v-if="erroresCierre.general" role="alert" class="mt-4 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                {{ erroresCierre.general }}
            </p>

            <form class="mt-5 grid gap-4 rounded-[24px] border border-hilo bg-velo p-5 sm:grid-cols-2" novalidate @submit.prevent="cerrarDia">
                <Campo etiqueta="Desde" para="cierre-fecha" :error="erroresCierre.fecha">
                    <input id="cierre-fecha" v-model="nuevoCierre.fecha" type="date" :min="hoyISO()" :class="claseEntrada(Boolean(erroresCierre.fecha))" />
                </Campo>
                <Campo etiqueta="Hasta (opcional)" para="cierre-fecha-hasta" :error="erroresCierre.fecha_hasta">
                    <input
                        id="cierre-fecha-hasta"
                        v-model="nuevoCierre.fecha_hasta"
                        type="date"
                        :min="nuevoCierre.fecha || hoyISO()"
                        :class="claseEntrada(Boolean(erroresCierre.fecha_hasta))"
                    />
                </Campo>
                <Campo class="sm:col-span-2" etiqueta="Motivo (opcional)" para="cierre-motivo" :error="erroresCierre.motivo">
                    <input
                        id="cierre-motivo"
                        v-model="nuevoCierre.motivo"
                        type="text"
                        maxlength="120"
                        placeholder="Ej.: feriado, vacaciones, capacitación"
                        :class="claseEntrada(Boolean(erroresCierre.motivo))"
                    />
                </Campo>
                <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-niebla">Deja "Hasta" vacío para cerrar un solo día.</p>
                    <Button type="submit" :disabled="guardandoCierre">{{ textoBotonCierre }}</Button>
                </div>
            </form>

            <p v-if="cierresCargados && !listaCierres.length" class="mt-4 text-sm text-niebla">No hay días sin atención programados.</p>
            <ul v-else-if="listaCierres.length" class="mt-4 divide-y divide-hilo rounded-[24px] border border-hilo">
                <li v-for="cierre in listaCierres" :key="cierre.id" class="flex items-center justify-between gap-4 px-5 py-3.5">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-perla first-letter:uppercase">{{ cierre.texto }}</p>
                        <p v-if="cierre.motivo" class="mt-0.5 text-sm text-bruma">{{ cierre.motivo }}</p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded text-sm text-bruma underline-offset-4 hover:text-perla hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                        @click="cierrePorQuitar = cierre"
                    >
                        Quitar
                    </button>
                </li>
            </ul>
        </section>

        <Modal :show="cierrePorQuitar !== null" label="Confirmar quitar el cierre" max-width="sm" @close="cierrePorQuitar = null">
            <template v-if="cierrePorQuitar">
                <h2 class="text-2xl">{{ cierrePorQuitar.esPeriodo ? '¿Volver a atender esos días?' : '¿Volver a atender ese día?' }}</h2>
                <p class="mt-2 text-sm text-bruma first-letter:uppercase">
                    {{ cierrePorQuitar.texto }}
                    {{ cierrePorQuitar.esPeriodo ? 'vuelven a tener el horario de siempre.' : 'vuelve a tener el horario de siempre.' }}
                </p>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button label="Volver" variant="secondary" :disabled="quitandoCierre" @click="cierrePorQuitar = null" />
                    <Button :disabled="quitandoCierre" @click="quitarCierre">{{ quitandoCierre ? 'Quitando…' : 'Sí, atender' }}</Button>
                </div>
            </template>
        </Modal>
    </section>
</template>

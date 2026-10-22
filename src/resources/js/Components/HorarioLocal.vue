<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Button from './Button.vue'
import EditorSemana from './EditorSemana.vue'
import Icono from './Icono.vue'
import { api, mensajeError, primerosErrores } from '../api'
import { fechaCorta, hora } from '../formato'
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

onMounted(cargar)

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

            <div class="sticky bottom-0 -mx-1 mt-6 flex flex-col-reverse gap-3 bg-gradient-to-t from-noche from-60% to-transparent px-1 pb-4 pt-6 sm:flex-row sm:justify-end">
                <Button v-if="cambios" label="Descartar cambios" variant="secondary" :disabled="guardando" @click="descartar" />
                <Button :disabled="guardando || !cambios" @click="guardar">
                    {{ guardando ? 'Guardando…' : 'Guardar horario' }}
                </Button>
            </div>
        </div>
    </section>
</template>

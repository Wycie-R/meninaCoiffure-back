<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Button from './Button.vue'
import EditorTurnos from './EditorTurnos.vue'
import Icono from './Icono.vue'
import { api, mensajeError, primerosErrores } from '../api'
import { hora } from '../formato'
import {
    DIAS,
    semanaDesdeTurnos,
    turnosDesdeSemana,
    firmaSemana,
    validarSemana,
    semanaTieneErrores,
    erroresVacios,
    erroresTurnosDesdeServidor,
} from '../turnos'

const emit = defineEmits(['aviso'])

const cargando = ref(true)
const error = ref('')
const semana = ref([])
const base = ref('')
const errores = ref(erroresVacios())
const guardando = ref(false)
const fueraDeHorario = ref([])

function comoTurnos(horarios) {
    return horarios.map((h) => ({ dia_semana: Number(h.dia_semana), hora_inicio: hora(h.hora_apertura), hora_fin: hora(h.hora_cierre) }))
}

function aplicar(horarios) {
    semana.value = semanaDesdeTurnos(comoTurnos(horarios))
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

const porPersona = computed(() => {
    const grupos = new Map()
    for (const t of fueraDeHorario.value) {
        const clave = t.personal_id
        if (!grupos.has(clave)) grupos.set(clave, { nombre: `${t.nombre} ${t.apellido}`, turnos: [] })
        grupos.get(clave).turnos.push(`${DIAS.find((d) => d.dia === Number(t.dia_semana))?.nombre.toLowerCase()} de ${hora(t.hora_inicio)} a ${hora(t.hora_fin)}`)
    }
    return [...grupos.values()]
})

function descartar() {
    semana.value = semanaDesdeTurnos(JSON.parse(base.value))
    errores.value = erroresVacios()
}

async function guardar() {
    errores.value = validarSemana(semana.value)
    if (semanaTieneErrores(errores.value)) return

    const turnos = turnosDesdeSemana(semana.value)
    const horarios = turnos.map((t) => ({ dia_semana: t.dia_semana, hora_apertura: t.hora_inicio, hora_cierre: t.hora_fin }))

    guardando.value = true
    const respuesta = await api('/api/horarios-atencion', { metodo: 'PUT', datos: { horarios } })
    guardando.value = false

    if (!respuesta.ok) {
        if (respuesta.status === 422) {
            const crudos = primerosErrores(respuesta.cuerpo)
            const adaptados = Object.fromEntries(Object.entries(crudos).map(([clave, mensaje]) => [clave.replace(/^horarios/, 'turnos'), mensaje]))
            const resultado = erroresTurnosDesdeServidor(adaptados, turnos)
            errores.value = semanaTieneErrores(resultado) ? resultado : { general: mensajeError(respuesta, 'guardar el horario'), dias: {} }
        } else {
            errores.value = { general: mensajeError(respuesta, 'guardar el horario'), dias: {} }
        }
        return
    }

    aplicar(respuesta.cuerpo?.data ?? [])
    fueraDeHorario.value = respuesta.cuerpo?.turnos_fuera_de_horario ?? []
    emit('aviso', 'Horario del local actualizado.')
}
</script>

<template>
    <section aria-labelledby="config-horario">
        <div class="max-w-2xl">
            <h2 id="config-horario" class="text-3xl">Horario del local</h2>
            <p class="mt-2 text-sm leading-relaxed text-bruma">
                Los días y horas en que el salón abre. Las citas en el local solo se pueden agendar dentro de estas franjas;
                las citas a domicilio pueden quedar fuera.
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
                v-if="porPersona.length"
                role="note"
                class="mb-6 flex gap-3 rounded-2xl border border-miel/40 bg-miel/10 px-4 py-3.5 text-sm"
            >
                <Icono name="alerta" class="mt-0.5 h-5 w-5 shrink-0 text-miel" />
                <div class="min-w-0">
                    <p class="font-medium text-miel">Hay turnos del equipo fuera del nuevo horario</p>
                    <p class="mt-1 text-perla">Esas horas solo servirán para citas a domicilio. Si no es lo que quieres, ajusta su horario en Personal.</p>
                    <ul class="mt-2 space-y-1 text-bruma">
                        <li v-for="p in porPersona" :key="p.nombre">
                            <span class="text-perla">{{ p.nombre }}:</span> {{ p.turnos.join('; ') }}
                        </li>
                    </ul>
                    <button type="button" class="mt-2 rounded text-sm text-miel underline underline-offset-4" @click="fueraDeHorario = []">Entendido</button>
                </div>
            </div>

            <EditorTurnos
                v-model="semana"
                :errores="errores"
                texto-vacio="Sin horario cargado: el sistema no limita las citas por horario del local."
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

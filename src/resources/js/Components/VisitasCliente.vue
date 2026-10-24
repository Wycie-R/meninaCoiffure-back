<script setup>
import { ref, computed, nextTick } from 'vue'
import Button from './Button.vue'
import Icono from './Icono.vue'
import { mensajeError, primerosErrores } from '../api'
import { guaranies, fechaCorta } from '../formato'
import { claseEntrada, claseEnfoque } from '../ui'
import { usePermisos } from '../auth'
import { guardarNotasVisita } from '../clientes'

const props = defineProps({
    visitas: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['actualizada', 'aviso'])

const { esAdmin, personalPropio } = usePermisos()

const INICIALES = 5
const todas = ref(false)
const visibles = computed(() => (todas.value ? props.visitas : props.visitas.slice(0, INICIALES)))

const editando = ref(null)
const notas = ref('')
const error = ref('')
const guardando = ref(false)

function puedeEditar(visita) {
    return esAdmin.value || (visita.profesional?.id ?? null) === (personalPropio.value?.id ?? undefined)
}

function profesional(visita) {
    const p = visita.profesional
    return p ? `${p.nombre} ${p.apellido}` : 'Profesional sin registrar'
}

function servicios(visita) {
    return visita.servicios.map((s) => (s.cantidad > 1 ? `${s.nombre} ×${s.cantidad}` : s.nombre)).join(', ')
}

async function editar(visita) {
    editando.value = visita.id
    notas.value = visita.notas ?? ''
    error.value = ''
    await nextTick()
    document.getElementById(`visita-notas-${visita.id}`)?.focus()
}

async function guardar(visita) {
    const texto = notas.value.trim()
    if (texto.length > 2000) {
        error.value = 'Las notas no pueden superar los 2000 caracteres.'
        return
    }

    guardando.value = true
    const respuesta = await guardarNotasVisita(visita.id, texto || null)
    guardando.value = false

    if (!respuesta.ok) {
        error.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo).notas ?? mensajeError(respuesta, 'guardar las notas')
            : mensajeError(respuesta, 'guardar las notas')
        return
    }

    emit('actualizada', respuesta.cuerpo.data)
    emit('aviso', 'Notas de la visita guardadas.')
    editando.value = null
}
</script>

<template>
    <section aria-labelledby="cliente-visitas">
        <h3 id="cliente-visitas" class="text-xl">Historial de visitas</h3>
        <p class="mt-1 text-sm text-bruma">Cada cita completada queda registrada aquí con sus notas.</p>

        <ol v-if="visitas.length" class="mt-4 space-y-3">
            <li v-for="v in visibles" :key="v.id" class="rounded-2xl border border-hilo px-4 py-3.5">
                <div class="flex items-start justify-between gap-4">
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-perla tabular-nums">{{ fechaCorta(v.fecha_realizada) }}</span>
                        <span class="mt-0.5 block text-sm text-bruma">
                            {{ profesional(v) }}
                            <span v-if="v.a_domicilio" class="ml-1 inline-flex items-center gap-1 rounded-full bg-raso px-2 py-0.5 text-xs text-bruma">
                                <Icono name="casa" class="h-3.5 w-3.5" />
                                A domicilio
                            </span>
                        </span>
                    </span>
                    <span class="shrink-0 text-sm tabular-nums text-perla">{{ guaranies(v.total) }}</span>
                </div>

                <p class="mt-2 text-sm text-bruma">{{ servicios(v) || 'Sin servicios registrados' }}</p>

                <div v-if="editando === v.id" class="mt-3">
                    <label :for="`visita-notas-${v.id}`" class="mb-1.5 block text-sm font-medium text-perla">Notas de la visita</label>
                    <textarea
                        :id="`visita-notas-${v.id}`"
                        v-model="notas"
                        rows="3"
                        maxlength="2000"
                        placeholder="Ej.: se usó 9.1 con 20 vol., 35 min de exposición"
                        :class="[claseEntrada(Boolean(error), true), 'resize-y']"
                    ></textarea>
                    <p v-if="error" role="alert" class="mt-1.5 text-sm text-borgona">{{ error }}</p>
                    <div class="mt-3 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button label="Cancelar" variant="secondary" :disabled="guardando" @click="editando = null" />
                        <Button :disabled="guardando" @click="guardar(v)">{{ guardando ? 'Guardando…' : 'Guardar notas' }}</Button>
                    </div>
                </div>

                <!-- En celular la nota ocupa todo el ancho y "Editar notas" va debajo, con lugar para tocarlo -->
                <div v-else class="mt-2 flex flex-col items-start gap-1 sm:flex-row sm:justify-between sm:gap-4">
                    <p v-if="v.notas" class="w-full min-w-0 whitespace-pre-line break-words rounded-xl bg-terciopelo px-3 py-2 text-sm text-perla sm:w-auto">{{ v.notas }}</p>
                    <p v-else class="text-sm text-niebla">Sin notas.</p>
                    <button
                        v-if="puedeEditar(v) && editando === null"
                        type="button"
                        :class="[claseEnfoque, 'min-h-11 shrink-0 rounded text-sm text-bruma underline-offset-4 hover:text-perla hover:underline sm:min-h-0']"
                        :aria-label="`${v.notas ? 'Editar' : 'Agregar'} notas de la visita del ${fechaCorta(v.fecha_realizada)}`"
                        @click="editar(v)"
                    >
                        {{ v.notas ? 'Editar notas' : 'Agregar notas' }}
                    </button>
                </div>
            </li>
        </ol>
        <p v-else class="mt-4 rounded-2xl border border-dashed border-hilo px-4 py-3 text-sm text-bruma">
            Todavía no tiene visitas completadas.
        </p>

        <button
            v-if="visitas.length > INICIALES"
            type="button"
            :class="[claseEnfoque, 'mt-3 rounded-full text-sm text-orquidea hover:text-orquidea-claro']"
            @click="todas = !todas"
        >
            {{ todas ? 'Ver solo las más recientes' : `Ver las ${visitas.length} visitas` }}
        </button>
    </section>
</template>

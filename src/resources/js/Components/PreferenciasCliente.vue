<script setup>
import { ref, computed, nextTick } from 'vue'
import Button from './Button.vue'
import Campo from './Campo.vue'
import Dropdown from './Dropdown.vue'
import Modal from './Modal.vue'
import Icono from './Icono.vue'
import { mensajeError, primerosErrores } from '../api'
import { claseEntrada, claseEnfoque } from '../ui'
import {
    CATEGORIAS_PREFERENCIA,
    nombreCategoria,
    crearPreferencia,
    actualizarPreferencia,
    eliminarPreferencia,
} from '../clientes'

const props = defineProps({
    clienteId: {
        type: Number,
        required: true,
    },
    preferencias: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['cambio', 'aviso'])

const ORDEN = Object.fromEntries(CATEGORIAS_PREFERENCIA.map((c, i) => [c.value, i]))

const lista = computed(() =>
    [...props.preferencias].sort((a, b) => (ORDEN[a.categoria] ?? 99) - (ORDEN[b.categoria] ?? 99) || a.id - b.id)
)

const editando = ref(null)
const form = ref({ categoria: 'otro', descripcion: '' })
const errores = ref({})
const guardando = ref(false)
const porBorrar = ref(null)
const borrando = ref(false)
const errorBorrado = ref('')

const restantes = computed(() => 1000 - form.value.descripcion.length)

async function enfocar() {
    await nextTick()
    document.getElementById('preferencia-descripcion')?.focus()
}

function nueva() {
    form.value = { categoria: 'otro', descripcion: '' }
    errores.value = {}
    editando.value = 'nueva'
    enfocar()
}

function editar(preferencia) {
    form.value = { categoria: preferencia.categoria ?? 'otro', descripcion: preferencia.descripcion ?? '' }
    errores.value = {}
    editando.value = preferencia.id
    enfocar()
}

function cancelar() {
    editando.value = null
    errores.value = {}
}

async function guardar() {
    const descripcion = form.value.descripcion.trim()
    errores.value = {}
    if (!descripcion) errores.value.descripcion = 'Escribe la preferencia.'
    else if (descripcion.length > 1000) errores.value.descripcion = 'No puede superar los 1000 caracteres.'
    if (Object.keys(errores.value).length) return

    const datos = { categoria: form.value.categoria, descripcion }
    const esNueva = editando.value === 'nueva'

    guardando.value = true
    const respuesta = esNueva
        ? await crearPreferencia(props.clienteId, datos)
        : await actualizarPreferencia(editando.value, datos)
    guardando.value = false

    if (!respuesta.ok) {
        errores.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo)
            : { general: mensajeError(respuesta, 'guardar la preferencia') }
        return
    }

    const guardada = respuesta.cuerpo.data
    emit(
        'cambio',
        esNueva ? [...props.preferencias, guardada] : props.preferencias.map((p) => (p.id === guardada.id ? guardada : p))
    )
    emit('aviso', esNueva ? 'Preferencia agregada.' : 'Preferencia actualizada.')
    editando.value = null
}

async function borrar() {
    const preferencia = porBorrar.value
    borrando.value = true
    errorBorrado.value = ''
    const respuesta = await eliminarPreferencia(preferencia.id)
    borrando.value = false
    porBorrar.value = null

    if (!respuesta.ok) {
        errorBorrado.value = mensajeError(respuesta, 'borrar la preferencia')
        return
    }

    emit('cambio', props.preferencias.filter((p) => p.id !== preferencia.id))
    emit('aviso', 'Preferencia borrada.')
}
</script>

<template>
    <section aria-labelledby="cliente-preferencias">
        <div class="flex items-baseline justify-between gap-4">
            <h3 id="cliente-preferencias" class="text-xl">Preferencias</h3>
            <button
                v-if="editando === null"
                type="button"
                :class="[claseEnfoque, 'inline-flex items-center gap-1.5 rounded-full text-sm text-orquidea hover:text-orquidea-claro']"
                @click="nueva"
            >
                <Icono name="agregar" class="h-4 w-4" />
                Agregar
            </button>
        </div>
        <p class="mt-1 text-sm text-bruma">Lo que conviene saber antes de la cita: alergias, fórmulas de color, gustos.</p>

        <ul v-if="lista.length" class="mt-4 space-y-2">
            <li v-for="p in lista" :key="p.id">
                <div
                    v-if="editando !== p.id"
                    class="flex items-start gap-3 rounded-2xl border px-4 py-3"
                    :class="p.categoria === 'alergia' ? 'border-borgona/40 bg-borgona-hondo/50' : 'border-hilo'"
                >
                    <span class="min-w-0 flex-1">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="p.categoria === 'alergia' ? 'bg-borgona/15 text-borgona' : 'bg-raso text-bruma'"
                        >
                            <Icono v-if="p.categoria === 'alergia'" name="alerta" class="h-3.5 w-3.5" />
                            {{ nombreCategoria(p.categoria) }}
                        </span>
                        <span class="mt-1.5 block whitespace-pre-line break-words text-sm text-perla">{{ p.descripcion }}</span>
                    </span>
                    <span v-if="editando === null" class="flex shrink-0 gap-3 pt-0.5 text-sm">
                        <button
                            type="button"
                            :class="[claseEnfoque, 'rounded text-bruma underline-offset-4 hover:text-perla hover:underline']"
                            :aria-label="`Editar la preferencia: ${nombreCategoria(p.categoria)}`"
                            @click="editar(p)"
                        >
                            Editar
                        </button>
                        <button
                            type="button"
                            :class="[claseEnfoque, 'rounded text-bruma underline-offset-4 hover:text-borgona hover:underline']"
                            :aria-label="`Borrar la preferencia: ${nombreCategoria(p.categoria)}`"
                            @click="porBorrar = p"
                        >
                            Borrar
                        </button>
                    </span>
                </div>

                <form v-else class="rounded-[24px] border border-orquidea/40 p-4" novalidate @submit.prevent="guardar">
                    <div class="space-y-4">
                        <Campo etiqueta="Tipo">
                            <Dropdown v-model="form.categoria" :options="CATEGORIAS_PREFERENCIA" />
                        </Campo>
                        <Campo etiqueta="Detalle" para="preferencia-descripcion" :error="errores.descripcion" :ayuda="restantes < 200 ? `Quedan ${restantes} caracteres.` : ''">
                            <textarea id="preferencia-descripcion" v-model="form.descripcion"
                        @input="errores.descripcion = ''" rows="3" maxlength="1000" :class="[claseEntrada(Boolean(errores.descripcion), true), 'resize-y']"></textarea>
                        </Campo>
                    </div>
                    <p v-if="errores.general" role="alert" class="mt-3 text-sm text-borgona">{{ errores.general }}</p>
                    <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button label="Cancelar" variant="secondary" :disabled="guardando" @click="cancelar" />
                        <Button type="submit" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar cambios' }}</Button>
                    </div>
                </form>
            </li>
        </ul>
        <p v-else-if="editando !== 'nueva'" class="mt-4 rounded-2xl border border-dashed border-hilo px-4 py-3 text-sm text-bruma">
            Sin preferencias registradas.
        </p>

        <form v-if="editando === 'nueva'" class="mt-4 rounded-[24px] border border-orquidea/40 p-4" novalidate @submit.prevent="guardar">
            <h4 class="text-lg">Nueva preferencia</h4>
            <div class="mt-4 space-y-4">
                <Campo etiqueta="Tipo">
                    <Dropdown v-model="form.categoria" :options="CATEGORIAS_PREFERENCIA" />
                </Campo>
                <Campo etiqueta="Detalle" para="preferencia-descripcion" :error="errores.descripcion" :ayuda="restantes < 200 ? `Quedan ${restantes} caracteres.` : ''">
                    <textarea
                        id="preferencia-descripcion"
                        v-model="form.descripcion"
                        @input="errores.descripcion = ''"
                        rows="3"
                        maxlength="1000"
                        placeholder="Ej.: rubio ceniza 9.1 con oxidante de 20 vol."
                        :class="[claseEntrada(Boolean(errores.descripcion), true), 'resize-y']"
                    ></textarea>
                </Campo>
            </div>
            <p v-if="errores.general" role="alert" class="mt-3 text-sm text-borgona">{{ errores.general }}</p>
            <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" :disabled="guardando" @click="cancelar" />
                <Button type="submit" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Agregar preferencia' }}</Button>
            </div>
        </form>

        <p v-if="errorBorrado" role="alert" class="mt-3 text-sm text-borgona">{{ errorBorrado }}</p>

        <Modal :show="Boolean(porBorrar)" label="Confirmar borrado" max-width="sm" @close="porBorrar = null">
            <h2 class="text-2xl">¿Borrar esta preferencia?</h2>
            <p class="mt-2 text-sm text-bruma">{{ nombreCategoria(porBorrar?.categoria) }}: {{ porBorrar?.descripcion }}</p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" @click="porBorrar = null" />
                <Button variant="danger" :disabled="borrando" @click="borrar">{{ borrando ? 'Borrando…' : 'Borrar' }}</Button>
            </div>
        </Modal>
    </section>
</template>

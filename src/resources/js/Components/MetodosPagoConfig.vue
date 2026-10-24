<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import Button from './Button.vue'
import Modal from './Modal.vue'
import Icono from './Icono.vue'
import { api, mensajeError, primerosErrores } from '../api'
import { normalizar } from '../formato'
import { claseEntrada, claseEnfoque } from '../ui'
import { useMetodosPago } from '../metodosPago'

const emit = defineEmits(['aviso'])

const { metodos, error, cargado, cargarMetodosPago } = useMetodosPago()

onMounted(() => cargarMetodosPago({ forzar: true }))

const lista = computed(() => [...metodos.value].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')))

const nuevo = ref('')
const errorNuevo = ref('')
const agregando = ref(false)

const editando = ref(null)
const nombre = ref('')
const errorEdicion = ref('')
const guardando = ref(false)

const porEliminar = ref(null)
const eliminando = ref(false)
const errorEliminar = ref('')

function repetido(texto, ignorar = null) {
    return metodos.value.some((m) => m.id !== ignorar && normalizar(m.nombre) === normalizar(texto))
}

async function agregar() {
    const texto = nuevo.value.trim()
    errorNuevo.value = ''
    if (!texto) {
        errorNuevo.value = 'Escribe el nombre del método.'
        return
    }
    if (repetido(texto)) {
        errorNuevo.value = 'Ya existe un método de pago con ese nombre.'
        return
    }

    agregando.value = true
    const respuesta = await api('/api/metodos-pago', { metodo: 'POST', datos: { nombre: texto } })
    agregando.value = false

    if (!respuesta.ok) {
        errorNuevo.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo).nombre ?? mensajeError(respuesta, 'agregar el método')
            : mensajeError(respuesta, 'agregar el método')
        return
    }

    nuevo.value = ''
    await cargarMetodosPago({ forzar: true })
    emit('aviso', `${texto} se agregó a los métodos de pago.`)
}

async function editar(metodo) {
    editando.value = metodo.id
    nombre.value = metodo.nombre
    errorEdicion.value = ''
    await nextTick()
    document.getElementById(`metodo-${metodo.id}`)?.select()
}

async function guardar(metodo) {
    const texto = nombre.value.trim()
    errorEdicion.value = ''
    if (!texto) {
        errorEdicion.value = 'Escribe el nombre del método.'
        return
    }
    if (texto === metodo.nombre) {
        editando.value = null
        return
    }
    if (repetido(texto, metodo.id)) {
        errorEdicion.value = 'Ya existe un método de pago con ese nombre.'
        return
    }

    guardando.value = true
    const respuesta = await api(`/api/metodos-pago/${metodo.id}`, { metodo: 'PUT', datos: { nombre: texto } })
    guardando.value = false

    if (!respuesta.ok) {
        errorEdicion.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo).nombre ?? mensajeError(respuesta, 'renombrar el método')
            : mensajeError(respuesta, 'renombrar el método')
        return
    }

    editando.value = null
    await cargarMetodosPago({ forzar: true })
    emit('aviso', 'Método de pago renombrado.')
}

async function eliminar() {
    const metodo = porEliminar.value
    eliminando.value = true
    errorEliminar.value = ''
    const respuesta = await api(`/api/metodos-pago/${metodo.id}`, { metodo: 'DELETE' })
    eliminando.value = false
    porEliminar.value = null

    if (!respuesta.ok) {
        errorEliminar.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo).metodo_pago ?? mensajeError(respuesta, 'eliminar el método')
            : mensajeError(respuesta, 'eliminar el método')
        return
    }

    await cargarMetodosPago({ forzar: true })
    emit('aviso', `${metodo.nombre} ya no aparece como método de pago.`)
}
</script>

<template>
    <section aria-labelledby="config-metodos" class="max-w-2xl">
        <h2 id="config-metodos" class="text-3xl">Métodos de pago</h2>
        <p class="mt-2 text-sm leading-relaxed text-bruma">
            Las opciones que aparecen al registrar un pago. Un método que ya tiene cobros registrados no se puede eliminar,
            pero sí renombrar.
        </p>

        <div v-if="!cargado && !error" class="mt-6 space-y-2" aria-busy="true" aria-label="Cargando los métodos de pago">
            <div v-for="n in 4" :key="n" class="h-14 animate-pulse rounded-2xl bg-velo"></div>
        </div>

        <div v-else-if="error && !cargado" role="alert" class="mt-6 rounded-2xl border border-borgona/40 bg-borgona-hondo px-4 py-4 text-sm text-borgona">
            <p>{{ error }}</p>
            <Button class="mt-3" label="Reintentar" variant="secondary" @click="cargarMetodosPago({ forzar: true })" />
        </div>

        <template v-else>
            <ul v-if="lista.length" class="mt-6 divide-y divide-hilo overflow-hidden rounded-[24px] border border-hilo bg-velo">
                <li v-for="m in lista" :key="m.id" class="px-5 py-3">
                    <form v-if="editando === m.id" class="flex flex-col gap-3 sm:flex-row sm:items-start" novalidate @submit.prevent="guardar(m)">
                        <div class="min-w-0 flex-1">
                            <label :for="`metodo-${m.id}`" class="sr-only">Nuevo nombre de {{ m.nombre }}</label>
                            <input
                                :id="`metodo-${m.id}`"
                                v-model="nombre"
                                type="text"
                                maxlength="255"
                                autocomplete="off"
                                :class="claseEntrada(Boolean(errorEdicion), true)"
                                :aria-invalid="Boolean(errorEdicion)"
                                @input="errorEdicion = ''"
                                @keydown.esc.stop="editando = null"
                            />
                            <p v-if="errorEdicion" role="alert" class="mt-1.5 text-sm text-borgona">{{ errorEdicion }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button label="Cancelar" variant="secondary" :disabled="guardando" @click="editando = null" />
                            <Button type="submit" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar' }}</Button>
                        </div>
                    </form>

                    <!-- En celular las acciones van debajo del nombre: así el nombre se lee completo -->
                    <div v-else class="flex min-h-11 flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-raso text-orquidea" aria-hidden="true">
                                <Icono name="billete" class="h-[1.125rem] w-[1.125rem]" />
                            </span>
                            <span class="min-w-0 break-words text-perla sm:truncate">{{ m.nombre }}</span>
                        </span>
                        <span v-if="editando === null" class="flex shrink-0 gap-5 pl-12 text-sm sm:gap-4 sm:pl-0">
                            <button type="button" :class="[claseEnfoque, 'min-h-11 rounded text-bruma underline-offset-4 hover:text-perla hover:underline sm:min-h-0']" :aria-label="`Renombrar ${m.nombre}`" @click="editar(m)">
                                Renombrar
                            </button>
                            <button type="button" :class="[claseEnfoque, 'min-h-11 rounded text-bruma underline-offset-4 hover:text-borgona hover:underline sm:min-h-0']" :aria-label="`Eliminar ${m.nombre}`" @click="porEliminar = m">
                                Eliminar
                            </button>
                        </span>
                    </div>
                </li>
            </ul>
            <p v-else class="mt-6 rounded-2xl border border-dashed border-hilo-fuerte px-4 py-4 text-sm text-bruma">
                No hay métodos de pago. Agrega al menos uno para poder registrar cobros.
            </p>

            <p v-if="errorEliminar" role="alert" class="mt-3 text-sm text-borgona">{{ errorEliminar }}</p>

            <form class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-start" novalidate @submit.prevent="agregar">
                <div class="min-w-0 flex-1">
                    <label for="metodo-nuevo" class="sr-only">Nombre del nuevo método de pago</label>
                    <input
                        id="metodo-nuevo"
                        v-model="nuevo"
                        type="text"
                        maxlength="255"
                        autocomplete="off"
                        placeholder="Ej.: Billetera Tigo Money"
                        :class="claseEntrada(Boolean(errorNuevo), true)"
                        :aria-invalid="Boolean(errorNuevo)"
                        @input="errorNuevo = ''"
                    />
                    <p v-if="errorNuevo" role="alert" class="mt-1.5 text-sm text-borgona">{{ errorNuevo }}</p>
                </div>
                <Button type="submit" class="shrink-0" :disabled="agregando">
                    <Icono name="agregar" class="h-4 w-4" />
                    {{ agregando ? 'Agregando…' : 'Agregar método' }}
                </Button>
            </form>
        </template>

        <Modal :show="Boolean(porEliminar)" label="Confirmar eliminación" max-width="sm" @close="porEliminar = null">
            <h2 class="text-2xl">¿Eliminar {{ porEliminar?.nombre }}?</h2>
            <p class="mt-2 text-sm text-bruma">
                Dejará de aparecer al registrar pagos. Si ya tiene cobros registrados, el sistema no lo permitirá; en ese caso puedes renombrarlo.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" @click="porEliminar = null" />
                <Button variant="danger" :disabled="eliminando" @click="eliminar">{{ eliminando ? 'Eliminando…' : 'Eliminar' }}</Button>
            </div>
        </Modal>
    </section>
</template>

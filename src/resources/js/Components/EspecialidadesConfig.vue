<script setup>
import { ref, computed, onMounted } from 'vue'
import Button from './Button.vue'
import Campo from './Campo.vue'
import Modal from './Modal.vue'
import Icono from './Icono.vue'
import PanelLateral from './PanelLateral.vue'
import { api, mensajeError, primerosErrores } from '../api'
import { iniciales, normalizar, plural } from '../formato'
import { claseEntrada, claseEnfoque } from '../ui'

const emit = defineEmits(['aviso'])

const especialidades = ref([])
const cargando = ref(true)
const error = ref('')

async function cargar() {
    cargando.value = true
    error.value = ''
    const respuesta = await api('/api/especialidades')
    cargando.value = false
    if (!respuesta.ok) {
        error.value = mensajeError(respuesta, 'cargar las especialidades')
        return
    }
    especialidades.value = respuesta.cuerpo?.data ?? []
}

onMounted(cargar)

const lista = computed(() => [...especialidades.value].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')))

const abierto = ref(false)
const editando = ref(null)
const form = ref({ nombre: '', descripcion: '' })
const errores = ref({})
const guardando = ref(false)

function abrirNueva() {
    editando.value = null
    form.value = { nombre: '', descripcion: '' }
    errores.value = {}
    abierto.value = true
}

function abrirEdicion(e) {
    editando.value = e
    form.value = { nombre: e.nombre, descripcion: e.descripcion ?? '' }
    errores.value = {}
    abierto.value = true
}

function limpiar(campo) {
    if (!errores.value[campo]) return
    errores.value = Object.fromEntries(Object.entries(errores.value).filter(([clave]) => clave !== campo))
}

async function guardar() {
    const nombre = form.value.nombre.trim()
    const descripcion = form.value.descripcion.trim()
    const e = {}
    if (!nombre) e.nombre = 'Indica el nombre de la especialidad.'
    else if (especialidades.value.some((x) => x.id !== editando.value?.id && normalizar(x.nombre) === normalizar(nombre))) e.nombre = 'Ya existe una especialidad con ese nombre.'
    if (descripcion.length > 1000) e.descripcion = 'La descripción no puede superar los 1000 caracteres.'
    errores.value = e
    if (Object.keys(e).length) return

    const datos = { nombre, descripcion: descripcion || null }
    guardando.value = true
    const respuesta = editando.value
        ? await api(`/api/especialidades/${editando.value.id}`, { metodo: 'PUT', datos })
        : await api('/api/especialidades', { metodo: 'POST', datos })
    guardando.value = false

    if (!respuesta.ok) {
        errores.value = respuesta.status === 422 ? primerosErrores(respuesta.cuerpo) : {}
        if (!Object.keys(errores.value).length) errores.value = { general: mensajeError(respuesta, 'guardar la especialidad') }
        return
    }

    const guardada = respuesta.cuerpo.data
    especialidades.value = editando.value
        ? especialidades.value.map((x) => (x.id === guardada.id ? { ...x, ...guardada } : x))
        : [...especialidades.value, { personal: [], personal_count: 0, ...guardada }]
    abierto.value = false
    emit('aviso', editando.value ? 'Especialidad actualizada.' : `${guardada.nombre} se agregó a las especialidades.`)
}

const confirmarEliminar = ref(false)
const eliminando = ref(false)

async function eliminar() {
    const e = editando.value
    eliminando.value = true
    const respuesta = await api(`/api/especialidades/${e.id}`, { metodo: 'DELETE' })
    eliminando.value = false
    confirmarEliminar.value = false

    if (!respuesta.ok) {
        errores.value = { general: mensajeError(respuesta, 'eliminar la especialidad') }
        return
    }

    especialidades.value = especialidades.value.filter((x) => x.id !== e.id)
    abierto.value = false
    emit('aviso', `${e.nombre} fue eliminada.`)
}
</script>

<template>
    <section aria-labelledby="config-especialidades">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <h2 id="config-especialidades" class="text-3xl">Especialidades</h2>
                <p class="mt-2 text-sm leading-relaxed text-bruma">
                    Lo que sabe hacer cada profesional del equipo: colorimetría, uñas, maquillaje, barbería.
                </p>
            </div>
            <Button @click="abrirNueva">
                <Icono name="agregar" class="h-4 w-4" />
                Nueva especialidad
            </Button>
        </div>

        <div v-if="cargando" class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2" aria-busy="true" aria-label="Cargando las especialidades">
            <div v-for="n in 4" :key="n" class="h-32 animate-pulse rounded-[24px] bg-velo"></div>
        </div>

        <div v-else-if="error" role="alert" class="mt-6 max-w-2xl rounded-2xl border border-borgona/40 bg-borgona-hondo px-4 py-4 text-sm text-borgona">
            <p>{{ error }}</p>
            <Button class="mt-3" label="Reintentar" variant="secondary" @click="cargar" />
        </div>

        <p v-else-if="!lista.length" class="mt-6 max-w-2xl rounded-2xl border border-dashed border-hilo-fuerte px-4 py-6 text-sm text-bruma">
            Todavía no hay especialidades. Crea la primera para describir lo que hace el equipo.
        </p>

        <ul v-else class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <li v-for="e in lista" :key="e.id">
                <button
                    type="button"
                    class="flex h-full w-full flex-col rounded-[24px] border border-hilo bg-velo p-5 text-left transition-colors hover:border-hilo-fuerte"
                    :class="claseEnfoque"
                    @click="abrirEdicion(e)"
                >
                    <span class="block text-xl font-display text-perla">{{ e.nombre }}</span>
                    <span class="mt-1 block text-sm" :class="e.descripcion ? 'text-bruma' : 'text-niebla'">{{ e.descripcion || 'Sin descripción' }}</span>
                    <span class="mt-auto flex items-center gap-3 pt-4">
                        <span v-if="e.personal?.length" class="flex -space-x-2" aria-hidden="true">
                            <span
                                v-for="p in e.personal.slice(0, 4)"
                                :key="p.id"
                                class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-velo text-xs font-semibold"
                                :class="p.activo ? 'bg-orquidea-hondo text-perla' : 'bg-raso text-niebla'"
                            >
                                {{ iniciales(p.nombre, p.apellido) }}
                            </span>
                        </span>
                        <span class="text-sm text-bruma">
                            {{
                                e.personal_count
                                    ? `${plural(e.personal_count, 'profesional', 'profesionales')}: ${e.personal.map((p) => p.nombre).join(', ')}`
                                    : 'Nadie del equipo la tiene asignada'
                            }}
                        </span>
                    </span>
                </button>
            </li>
        </ul>

        <PanelLateral
            :abierto="abierto"
            :titulo="editando ? 'Editar especialidad' : 'Nueva especialidad'"
            :subtitulo="editando ? editando.nombre : ''"
            @cerrar="abierto = false"
        >
            <form id="form-especialidad" class="space-y-6" novalidate @submit.prevent="guardar">
                <p v-if="errores.general" role="alert" class="rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                    {{ errores.general }}
                </p>

                <Campo etiqueta="Nombre" para="especialidad-nombre" :error="errores.nombre">
                    <input
                        id="especialidad-nombre"
                        v-model="form.nombre"
                        type="text"
                        maxlength="255"
                        autocomplete="off"
                        placeholder="Ej.: Colorimetría"
                        :class="claseEntrada(Boolean(errores.nombre))"
                        @input="limpiar('nombre')"
                    />
                </Campo>

                <Campo etiqueta="Descripción" para="especialidad-descripcion" :error="errores.descripcion" opcional>
                    <textarea
                        id="especialidad-descripcion"
                        v-model="form.descripcion"
                        rows="4"
                        maxlength="1000"
                        :class="[claseEntrada(Boolean(errores.descripcion)), 'resize-y']"
                        @input="limpiar('descripcion')"
                    ></textarea>
                </Campo>

                <p class="rounded-xl bg-terciopelo px-4 py-3 text-sm text-bruma">
                    Para asignarla a cada profesional, abre su ficha en Personal.
                </p>

                <section v-if="editando" class="rounded-[24px] border border-hilo p-5" aria-labelledby="especialidad-eliminar">
                    <h3 id="especialidad-eliminar" class="text-lg">Eliminar especialidad</h3>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                        <p class="max-w-xs text-sm text-bruma">
                            {{
                                editando.personal_count
                                    ? `Se quitará a ${plural(editando.personal_count, 'profesional que la tiene', 'profesionales que la tienen')} asignada.`
                                    : 'Nadie la tiene asignada.'
                            }}
                        </p>
                        <Button variant="danger" @click="confirmarEliminar = true">Eliminar</Button>
                    </div>
                </section>
            </form>

            <template #pie>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button label="Cancelar" variant="secondary" @click="abierto = false" />
                    <Button type="submit" form="form-especialidad" :disabled="guardando">
                        {{ guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear especialidad' }}
                    </Button>
                </div>
            </template>
        </PanelLateral>

        <Modal :show="confirmarEliminar" label="Confirmar eliminación" max-width="sm" @close="confirmarEliminar = false">
            <h2 class="text-2xl">¿Eliminar {{ editando?.nombre }}?</h2>
            <p class="mt-2 text-sm text-bruma">
                {{ editando?.personal_count ? `Se quitará de ${editando.personal.map((p) => p.nombre).join(', ')}. ` : '' }}Esta acción no se puede deshacer.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" @click="confirmarEliminar = false" />
                <Button variant="danger" :disabled="eliminando" @click="eliminar">{{ eliminando ? 'Eliminando…' : 'Eliminar' }}</Button>
            </div>
        </Modal>
    </section>
</template>

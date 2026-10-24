<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Ilustracion from '../../Components/Ilustracion.vue'
import Aviso from '../../Components/Aviso.vue'
import Icono from '../../Components/Icono.vue'
import PanelLateral from '../../Components/PanelLateral.vue'
import Campo from '../../Components/Campo.vue'
import Modal from '../../Components/Modal.vue'
import { api, primerosErrores, mensajeError } from '../../api'
import { normalizar, plural } from '../../formato'
import { useCatalogo } from '../../catalogo'
import { usePermisos } from '../../auth'
import { claseEntrada, ilustracionDe } from '../../ui'

const { categorias, servicios, cargando, error, cargarCatalogo } = useCatalogo()
const { esAdmin } = usePermisos()

onMounted(() => cargarCatalogo())

const aviso = ref('')

const lista = computed(() =>
    [...categorias.value]
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
        .map((categoria) => {
            const propios = servicios.value.filter((s) => s.categoria?.id === categoria.id)
            const total = categoria.servicios_count ?? propios.length

            return {
                ...categoria,
                total,
                activos: propios.filter((s) => s.activo).length,
                ilustracion: ilustracionDe(categoria.nombre),
                ejemplos: propios.filter((s) => s.activo).slice(0, 3).map((s) => s.nombre),
            }
        })
)

const resumen = computed(() => {
    if (!categorias.value.length) return 'Todavía no hay categorías.'
    return `${plural(categorias.value.length, 'categoría', 'categorías')} agrupan los ${plural(servicios.value.length, 'servicio', 'servicios')} de la carta.`
})

const panelAbierto = ref(false)
const editando = ref(null)
const form = ref({ nombre: '', descripcion: '' })
const errores = ref({})
const guardando = ref(false)

function abrirNueva() {
    editando.value = null
    form.value = { nombre: '', descripcion: '' }
    errores.value = {}
    panelAbierto.value = true
}

function abrirEdicion(categoria) {
    editando.value = categoria
    form.value = { nombre: categoria.nombre, descripcion: categoria.descripcion ?? '' }
    errores.value = {}
    panelAbierto.value = true
}

function validar() {
    const e = {}
    const nombre = form.value.nombre.trim()

    if (!nombre) {
        e.nombre = 'Indica el nombre de la categoría.'
    } else if (nombre.length > 255) {
        e.nombre = 'El nombre no puede superar los 255 caracteres.'
    } else if (
        categorias.value.some((c) => c.id !== editando.value?.id && normalizar(c.nombre) === normalizar(nombre))
    ) {
        e.nombre = 'Ya existe una categoría con este nombre.'
    }

    return e
}

async function guardar() {
    errores.value = validar()
    if (Object.keys(errores.value).length) return

    guardando.value = true
    const datos = { nombre: form.value.nombre.trim(), descripcion: form.value.descripcion.trim() || null }
    const respuesta = editando.value
        ? await api(`/api/categorias-servicio/${editando.value.id}`, { metodo: 'PUT', datos })
        : await api('/api/categorias-servicio', { metodo: 'POST', datos })
    guardando.value = false

    if (!respuesta.ok) {
        errores.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo)
            : { general: mensajeError(respuesta, 'guardar la categoría') }
        return
    }

    await cargarCatalogo({ forzar: true })
    aviso.value = editando.value ? `Categoría «${datos.nombre}» actualizada.` : `Categoría «${datos.nombre}» creada.`
    panelAbierto.value = false
}

const porEliminar = ref(null)
const eliminando = ref(false)
const errorEliminar = ref('')

function pedirEliminar(categoria) {
    errorEliminar.value = ''
    porEliminar.value = categoria
}

async function eliminar() {
    eliminando.value = true
    const nombre = porEliminar.value.nombre
    const respuesta = await api(`/api/categorias-servicio/${porEliminar.value.id}`, { metodo: 'DELETE' })
    eliminando.value = false

    if (!respuesta.ok) {
        if (respuesta.status === 422) {
            errorEliminar.value =
                primerosErrores(respuesta.cuerpo).categoria ??
                respuesta.cuerpo?.message ??
                'No se puede eliminar porque la categoría todavía tiene servicios. Muévelos a otra categoría primero.'
            cargarCatalogo({ forzar: true })
            return
        }
        errorEliminar.value = mensajeError(respuesta, 'eliminar la categoría')
        return
    }

    porEliminar.value = null
    await cargarCatalogo({ forzar: true })
    aviso.value = `Categoría «${nombre}» eliminada.`
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10">
            <PageHeader titulo="Categorías" :descripcion="cargando && !categorias.length ? 'Cargando categorías…' : resumen">
                <Button href="/servicios" variant="secondary">Ver la carta</Button>
                <Button v-if="esAdmin" @click="abrirNueva">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nueva categoría
                </Button>
            </PageHeader>

            <div class="mt-10">
                <div v-if="cargando && !categorias.length" class="grid gap-4 sm:grid-cols-2" aria-busy="true">
                    <div v-for="n in 4" :key="n" class="h-40 animate-pulse rounded-[28px] bg-velo"></div>
                </div>

                <EstadoVacio v-else-if="error" tono="error" titulo="No se pudieron cargar las categorías" :descripcion="error" role="alert">
                    <Button label="Reintentar" variant="secondary" @click="cargarCatalogo({ forzar: true })" />
                </EstadoVacio>

                <EstadoVacio
                    v-else-if="!lista.length"
                    titulo="Sin categorías todavía"
                    descripcion="Crea la primera, por ejemplo Peluquería o Coloración, para empezar a armar la carta."
                >
                    <Button v-if="esAdmin" label="Nueva categoría" @click="abrirNueva" />
                </EstadoVacio>

                <ul v-else class="grid gap-4 sm:grid-cols-2">
                    <li
                        v-for="categoria in lista"
                        :key="categoria.id"
                        class="flex flex-col rounded-[28px] border border-hilo bg-velo p-6"
                    >
                        <div class="flex items-start gap-4">
                            <Ilustracion :nombre="categoria.ilustracion" class="h-16 w-16 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <h2 class="text-3xl leading-tight">{{ categoria.nombre }}</h2>
                                <p class="mt-1 text-sm text-niebla">
                                    {{ plural(categoria.total, 'servicio', 'servicios') }}<template v-if="categoria.total">, {{ categoria.activos === 1 ? "1 disponible" : `${categoria.activos} disponibles` }}</template>
                                </p>
                            </div>
                        </div>

                        <p v-if="categoria.descripcion" class="mt-4 text-sm leading-relaxed text-bruma">{{ categoria.descripcion }}</p>
                        <p v-else-if="categoria.ejemplos.length" class="mt-4 text-sm leading-relaxed text-bruma">
                            {{ categoria.ejemplos.join(', ') }}<template v-if="categoria.activos > 3"> y más</template>.
                        </p>

                        <div v-if="esAdmin" class="mt-auto flex flex-wrap items-center gap-2 pt-6">
                            <Button variant="secondary" @click="abrirEdicion(categoria)">Editar</Button>
                            <Button
                                v-if="!categoria.total"
                                variant="danger"
                                @click="pedirEliminar(categoria)"
                            >
                                Eliminar
                            </Button>
                            <p v-else class="text-xs text-niebla">
                                Para eliminarla, primero mueve sus servicios a otra categoría.
                            </p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <PanelLateral
            :abierto="panelAbierto"
            :titulo="editando ? 'Editar categoría' : 'Nueva categoría'"
            :subtitulo="editando ? editando.nombre : 'Agrupa servicios parecidos en la carta.'"
            @cerrar="panelAbierto = false"
        >
            <form id="form-categoria" class="space-y-5" novalidate @submit.prevent="guardar">
                <p v-if="errores.general" role="alert" class="rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                    {{ errores.general }}
                </p>

                <div class="flex justify-center py-2">
                    <Ilustracion :nombre="ilustracionDe(form.nombre)" class="h-24 w-24" />
                </div>

                <Campo etiqueta="Nombre" para="categoria-nombre" :error="errores.nombre" ayuda="La ilustración se elige sola según el nombre.">
                    <input
                        id="categoria-nombre"
                        v-model="form.nombre"
                        type="text"
                        maxlength="255"
                        placeholder="Ej.: Coloración"
                        :class="claseEntrada(Boolean(errores.nombre))"
                        :aria-invalid="Boolean(errores.nombre)"
                    />
                </Campo>

                <Campo etiqueta="Descripción" para="categoria-descripcion" opcional>
                    <textarea
                        id="categoria-descripcion"
                        v-model="form.descripcion"
                        rows="3"
                        placeholder="Qué tipo de servicios incluye."
                        :class="[claseEntrada(), 'resize-y']"
                    />
                </Campo>
            </form>

            <template #pie>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button label="Cancelar" variant="secondary" @click="panelAbierto = false" />
                    <Button type="submit" form="form-categoria" :disabled="guardando">
                        {{ guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear categoría' }}
                    </Button>
                </div>
            </template>
        </PanelLateral>

        <Modal :show="Boolean(porEliminar)" label="Confirmar eliminación" max-width="sm" @close="porEliminar = null">
            <h2 class="text-2xl">¿Eliminar «{{ porEliminar?.nombre }}»?</h2>
            <p class="mt-2 text-sm text-bruma">La categoría no tiene servicios. Esta acción no se puede deshacer.</p>
            <p v-if="errorEliminar" role="alert" class="mt-3 text-sm text-borgona">{{ errorEliminar }}</p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" @click="porEliminar = null" />
                <Button variant="danger" :disabled="eliminando" @click="eliminar">
                    {{ eliminando ? 'Eliminando…' : 'Eliminar categoría' }}
                </Button>
            </div>
        </Modal>

        <Aviso :mensaje="aviso" @cerrar="aviso = ''" />
    </AppLayout>
</template>

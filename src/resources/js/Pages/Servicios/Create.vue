<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import Dropdown from '../../Components/Dropdown.vue'
import PageHeader from '../../Components/PageHeader.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Icono from '../../Components/Icono.vue'
import Ilustracion from '../../Components/Ilustracion.vue'
import Campo from '../../Components/Campo.vue'
import Interruptor from '../../Components/Interruptor.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import { api, primerosErrores, mensajeError } from '../../api'
import { duracion, guaranies } from '../../formato'
import { useCatalogo } from '../../catalogo'
import { usePermisos } from '../../auth'
import { claseEntrada, ilustracionDe } from '../../ui'

const { categorias, cargando: cargandoCategorias, error: errorCatalogo, cargarCatalogo } = useCatalogo()
const { esProfesional } = usePermisos()

onMounted(() => cargarCatalogo())

const form = ref({
    nombre: '',
    categoria_id: null,
    duracion_minutos: '',
    precio: '',
    descripcion: '',
    activo: true,
})

const intentoGuardar = ref(false)
const enviando = ref(false)
const erroresServidor = ref({})
const errorGeneral = ref('')

const opcionesCategoria = computed(() => categorias.value.map((c) => ({ label: c.nombre, value: c.id })))

const precioTexto = computed({
    get: () => (form.value.precio === '' ? '' : Number(form.value.precio).toLocaleString('es-PY')),
    set: (valor) => {
        const digitos = valor.replace(/\D/g, '').slice(0, 10)
        form.value.precio = digitos === '' ? '' : Number(digitos)
        limpiarServidor('precio')
    },
})

const categoriaElegida = computed(() => categorias.value.find((c) => c.id === form.value.categoria_id)?.nombre ?? '')

const duracionVista = computed(() => {
    const minutos = Number(form.value.duracion_minutos)
    return form.value.duracion_minutos !== '' && minutos > 0 ? duracion(minutos) : ''
})

const ayudaDuracion = computed(() => {
    const minutos = Number(form.value.duracion_minutos)
    return minutos >= 60 && minutos <= 480 ? `Equivale a ${duracion(minutos)}.` : 'Entre 5 y 480 minutos.'
})

const errores = computed(() => {
    const f = form.value
    const e = {}
    const minutos = Number(f.duracion_minutos)

    if (!f.nombre.trim()) e.nombre = 'Indica el nombre del servicio.'
    else if (f.nombre.trim().length > 255) e.nombre = 'El nombre no puede superar los 255 caracteres.'

    if (!f.categoria_id) e.categoria_id = 'Selecciona una categoría.'

    if (f.duracion_minutos === '') e.duracion_minutos = 'Indica la duración en minutos.'
    else if (!Number.isInteger(minutos) || minutos < 5 || minutos > 480) e.duracion_minutos = 'Debe estar entre 5 y 480 minutos (8 horas).'

    if (f.precio === '') e.precio = 'Indica el precio.'

    return e
})

function error(campo) {
    return (intentoGuardar.value && errores.value[campo]) || erroresServidor.value[campo] || ''
}

function limpiarServidor(campo) {
    if (erroresServidor.value[campo]) {
        const { [campo]: _, ...resto } = erroresServidor.value
        erroresServidor.value = resto
    }
}

async function guardar() {
    intentoGuardar.value = true
    errorGeneral.value = ''
    if (Object.keys(errores.value).length) return

    enviando.value = true
    const respuesta = await api('/api/servicios', {
        metodo: 'POST',
        datos: {
            nombre: form.value.nombre.trim(),
            categoria_id: form.value.categoria_id,
            duracion_minutos: Number(form.value.duracion_minutos),
            precio: form.value.precio,
            descripcion: form.value.descripcion.trim() || null,
            activo: form.value.activo,
        },
    })
    enviando.value = false

    if (respuesta.ok) {
        router.visit(`/servicios?nuevo=${respuesta.cuerpo.data.id}`)
        return
    }

    if (respuesta.status === 422) erroresServidor.value = primerosErrores(respuesta.cuerpo)
    else errorGeneral.value = mensajeError(respuesta, 'guardar el servicio')
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader titulo="Nuevo servicio" descripcion="Agrega un servicio a la carta con su duración y su precio inicial." />

            <EstadoVacio
                v-if="esProfesional"
                class="mt-8"
                titulo="Solo la administración agrega servicios"
                descripcion="Puedes consultar la carta completa, con precios y duraciones, desde Servicios."
            >
                <Button href="/servicios" label="Ver la carta" variant="secondary" />
            </EstadoVacio>

            <div v-else class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <form class="min-w-0 rounded-[28px] border border-hilo bg-velo" novalidate @submit.prevent="guardar">
                    <div
                        v-if="errorGeneral || errorCatalogo"
                        role="alert"
                        class="m-5 mb-0 flex items-start gap-2.5 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona sm:m-6 sm:mb-0"
                    >
                        <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                        {{ errorGeneral || errorCatalogo }}
                    </div>

                    <section class="p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">1</span>
                            Datos del servicio
                        </h2>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <Campo etiqueta="Nombre" para="nombre" :error="error('nombre')">
                                <input
                                    id="nombre"
                                    v-model="form.nombre"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Ej.: Corte femenino"
                                    :aria-invalid="Boolean(error('nombre'))"
                                    :class="claseEntrada(Boolean(error('nombre')))"
                                    @input="limpiarServidor('nombre')"
                                />
                            </Campo>

                            <Campo etiqueta="Categoría" :error="error('categoria_id')">
                                <Dropdown
                                    v-model="form.categoria_id"
                                    :options="opcionesCategoria"
                                    :placeholder="cargandoCategorias ? 'Cargando…' : 'Seleccionar categoría'"
                                    :disabled="cargandoCategorias"
                                    @update:model-value="limpiarServidor('categoria_id')"
                                />
                            </Campo>
                        </div>

                        <Campo class="mt-5" etiqueta="Descripción" para="descripcion" opcional>
                            <textarea
                                id="descripcion"
                                v-model="form.descripcion"
                                rows="3"
                                placeholder="Detalle breve que ayude a la recepción a explicar el servicio."
                                :class="[claseEntrada(), 'resize-y']"
                            />
                        </Campo>
                    </section>

                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">2</span>
                            Duración y precio
                        </h2>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <Campo etiqueta="Duración (minutos)" para="duracion" :error="error('duracion_minutos')" :ayuda="ayudaDuracion">
                                <input
                                    id="duracion"
                                    v-model="form.duracion_minutos"
                                    type="number"
                                    inputmode="numeric"
                                    min="5"
                                    max="480"
                                    step="5"
                                    placeholder="Ej.: 45"
                                    :aria-invalid="Boolean(error('duracion_minutos'))"
                                    :class="claseEntrada(Boolean(error('duracion_minutos')))"
                                    @input="limpiarServidor('duracion_minutos')"
                                />
                            </Campo>

                            <Campo etiqueta="Precio" para="precio" :error="error('precio')" ayuda="Rige desde hoy. Después puedes programar cambios.">
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-niebla">Gs.</span>
                                    <input
                                        id="precio"
                                        v-model="precioTexto"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="70.000"
                                        :aria-invalid="Boolean(error('precio'))"
                                        :class="[claseEntrada(Boolean(error('precio'))), 'pl-12']"
                                    />
                                </div>
                            </Campo>
                        </div>
                    </section>

                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">3</span>
                            Disponibilidad
                        </h2>

                        <div class="mt-5">
                            <Interruptor
                                v-model="form.activo"
                                etiqueta="Disponible para reservas"
                                descripcion="Si lo apagas, el servicio queda en la carta pero no aparece al crear reservas."
                            />
                        </div>
                    </section>

                    <div class="flex flex-col-reverse gap-3 rounded-b-[28px] border-t border-hilo bg-noche/30 p-5 sm:flex-row sm:justify-end sm:px-6">
                        <Button label="Cancelar" href="/servicios" variant="secondary" class="w-full sm:w-auto" />
                        <Button type="submit" :disabled="enviando" class="w-full sm:w-auto">
                            <Icono name="completada" class="h-4 w-4" />
                            {{ enviando ? 'Guardando…' : 'Guardar servicio' }}
                        </Button>
                    </div>
                </form>

                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <p class="mb-3 text-sm font-medium text-bruma">Así se verá en la carta</p>

                    <div class="rounded-[28px] bg-orquidea-hondo/35 p-5 ring-1 ring-inset ring-orquidea/25">
                        <div class="flex items-start justify-between gap-3">
                            <Ilustracion :nombre="ilustracionDe(categoriaElegida)" class="h-14 w-14" />
                            <StatusBadge :status="form.activo ? 'activo' : 'inactivo'" />
                        </div>

                        <p class="mt-4 font-display text-2xl leading-tight">
                            {{ form.nombre.trim() || 'Nombre del servicio' }}
                        </p>
                        <p class="mt-1 text-sm" :class="categoriaElegida ? 'text-orquidea-claro' : 'text-niebla'">
                            {{ categoriaElegida || 'Sin categoría' }}
                        </p>
                        <p v-if="form.descripcion.trim()" class="mt-3 text-sm text-bruma">
                            {{ form.descripcion.trim() }}
                        </p>

                        <div class="mt-5 flex items-end justify-between gap-3 border-t border-orquidea/20 pt-4">
                            <span class="inline-flex items-center gap-1.5 text-sm text-bruma">
                                <Icono name="pendiente" class="h-4 w-4" />
                                {{ duracionVista || '— min' }}
                            </span>
                            <span class="font-display text-2xl tabular-nums">
                                {{ form.precio === '' ? 'Gs. —' : guaranies(form.precio) }}
                            </span>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import Dropdown from '../../Components/Dropdown.vue'
import PageHeader from '../../Components/PageHeader.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Icono from '../../Components/Icono.vue'
import { api, primerosErrores, duracion, guaranies } from '../../api'

const form = ref({
    nombre: '',
    categoria_id: null,
    duracion_minutos: '',
    precio: '',
    descripcion: '',
    activo: true,
})

const categorias = ref([])
const cargandoCategorias = ref(true)
const intentoGuardar = ref(false)
const enviando = ref(false)
const erroresServidor = ref({})
const errorGeneral = ref('')

onMounted(async () => {
    try {
        const respuesta = await api('/api/categorias-servicio')
        if (respuesta.ok) categorias.value = respuesta.cuerpo.data
        else errorGeneral.value = 'No se pudieron cargar las categorías. Recarga la página.'
    } catch {
        errorGeneral.value = 'No se pudieron cargar las categorías. Recarga la página.'
    } finally {
        cargandoCategorias.value = false
    }
})

const opcionesCategoria = computed(() =>
    categorias.value.map((c) => ({ label: c.nombre, value: c.id }))
)

const precioTexto = computed({
    get: () => (form.value.precio === '' ? '' : Number(form.value.precio).toLocaleString('es-PY')),
    set: (valor) => {
        const digitos = valor.replace(/\D/g, '').slice(0, 10)
        form.value.precio = digitos === '' ? '' : Number(digitos)
    },
})

const categoriaElegida = computed(
    () => categorias.value.find((c) => c.id === form.value.categoria_id)?.nombre ?? ''
)

const duracionVista = computed(() => {
    const minutos = Number(form.value.duracion_minutos)
    return form.value.duracion_minutos !== '' && minutos > 0 ? duracion(minutos) : ''
})

const duracionTexto = computed(() => {
    const minutos = Number(form.value.duracion_minutos)
    return minutos >= 60 && minutos <= 480 ? `Equivale a ${duracion(minutos)}.` : ''
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

    try {
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

        if (respuesta.ok) {
            router.visit(`/servicios?nuevo=${respuesta.cuerpo.data.id}`)
            return
        }

        if (respuesta.status === 422) {
            erroresServidor.value = primerosErrores(respuesta.cuerpo)
        } else {
            errorGeneral.value = 'No se pudo guardar el servicio. Intenta de nuevo en unos minutos.'
        }
    } catch {
        errorGeneral.value = 'No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'
    } finally {
        enviando.value = false
    }
}

const inputClass =
    'w-full min-w-0 min-h-12 rounded-xl border bg-terciopelo px-4 py-3 text-sm text-perla placeholder:text-niebla [color-scheme:dark] transition-colors hover:border-hilo-fuerte focus-visible:border-orquidea focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orquidea/15'

function inputBorder(campo) {
    return error(campo) ? 'border-borgona' : 'border-hilo'
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader
                titulo="Nuevo servicio"
                descripcion="Agrega un servicio al catálogo con su precio y duración."
            />

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <form
                    class="min-w-0 rounded-[28px] border border-hilo bg-velo"
                    novalidate
                    @submit.prevent="guardar"
                >
                    <div
                        v-if="errorGeneral"
                        role="alert"
                        class="m-5 mb-0 flex items-start gap-2.5 rounded-lg border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona sm:m-6 sm:mb-0"
                    >
                        <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                        {{ errorGeneral }}
                    </div>

                    <section class="p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">1</span>
                            Datos del servicio
                        </h2>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="nombre" class="mb-1.5 block text-sm font-medium text-perla">
                                    Nombre
                                </label>
                                <input
                                    id="nombre"
                                    v-model="form.nombre"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Ej.: Corte femenino"
                                    :aria-invalid="Boolean(error('nombre'))"
                                    :aria-describedby="error('nombre') ? 'nombre-error' : undefined"
                                    :class="[inputClass, inputBorder('nombre')]"
                                    @input="limpiarServidor('nombre')"
                                />
                                <p v-if="error('nombre')" id="nombre-error" class="mt-1.5 text-sm text-borgona">
                                    {{ error('nombre') }}
                                </p>
                            </div>

                            <div class="min-w-0">
                                <p class="mb-1.5 text-sm font-medium text-perla">
                                    Categoría
                                </p>
                                <Dropdown
                                    v-model="form.categoria_id"
                                    :options="opcionesCategoria"
                                    :placeholder="cargandoCategorias ? 'Cargando...' : 'Seleccionar categoría'"
                                    :disabled="cargandoCategorias"
                                    @update:model-value="limpiarServidor('categoria_id')"
                                />
                                <p v-if="error('categoria_id')" class="mt-1.5 text-sm text-borgona">
                                    {{ error('categoria_id') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <label for="descripcion" class="mb-1.5 block text-sm font-medium text-perla">
                                Descripción <span class="font-normal text-bruma">(opcional)</span>
                            </label>
                            <textarea
                                id="descripcion"
                                v-model="form.descripcion"
                                rows="3"
                                placeholder="Detalle breve que ayude a la recepción a explicar el servicio."
                                :class="[inputClass, 'border-hilo resize-y']"
                            />
                        </div>
                    </section>

                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">2</span>
                            Duración y precio
                        </h2>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="duracion" class="mb-1.5 block text-sm font-medium text-perla">
                                    Duración (minutos)
                                </label>
                                <div class="relative">
                                    <Icono name="pendiente" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-orquidea" />
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
                                        :aria-describedby="error('duracion_minutos') ? 'duracion-error' : undefined"
                                        :class="[inputClass, inputBorder('duracion_minutos'), 'pl-11']"
                                        @input="limpiarServidor('duracion_minutos')"
                                    />
                                </div>
                                <p v-if="error('duracion_minutos')" id="duracion-error" class="mt-1.5 text-sm text-borgona">
                                    {{ error('duracion_minutos') }}
                                </p>
                                <p v-else-if="duracionTexto" class="mt-1.5 text-sm text-bruma">
                                    {{ duracionTexto }}
                                </p>
                            </div>

                            <div class="min-w-0">
                                <label for="precio" class="mb-1.5 block text-sm font-medium text-perla">
                                    Precio
                                </label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-medium text-orquidea">
                                        Gs.
                                    </span>
                                    <input
                                        id="precio"
                                        v-model="precioTexto"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="70.000"
                                        :aria-invalid="Boolean(error('precio'))"
                                        :aria-describedby="error('precio') ? 'precio-error' : 'precio-ayuda'"
                                        :class="[inputClass, inputBorder('precio'), 'pl-12']"
                                        @input="limpiarServidor('precio')"
                                    />
                                </div>
                                <p v-if="error('precio')" id="precio-error" class="mt-1.5 text-sm text-borgona">
                                    {{ error('precio') }}
                                </p>
                                <p v-else id="precio-ayuda" class="mt-1.5 text-sm text-bruma">
                                    Rige desde hoy.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">3</span>
                            Disponibilidad
                        </h2>

                        <label
                            class="mt-5 flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors"
                            :class="form.activo ? 'border-orquidea/40 bg-orquidea/12' : 'border-hilo-fuerte hover:border-hilo-fuerte'"
                        >
                            <input
                                v-model="form.activo"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 shrink-0 accent-orquidea [color-scheme:dark]"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-perla">
                                    Disponible para reservas
                                </span>
                                <span class="block text-sm text-bruma">
                                    Si lo desmarcas, el servicio se guarda pero no aparece al crear reservas.
                                </span>
                            </span>
                        </label>
                    </section>

                    <div class="flex flex-col-reverse gap-3 rounded-b-[28px] border-t border-hilo bg-velo p-5 sm:flex-row sm:justify-end sm:px-6">
                        <Button label="Cancelar" href="/servicios" variant="secondary" class="w-full sm:w-auto" />
                        <Button type="submit" :disabled="enviando" class="w-full sm:w-auto">
                            <Icono name="completada" class="h-4 w-4" />
                            {{ enviando ? 'Guardando...' : 'Guardar servicio' }}
                        </Button>
                    </div>
                </form>

                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <p class="mb-3 text-sm font-medium text-bruma">Así se verá en el catálogo</p>

                    <div class="relative overflow-hidden rounded-[28px] bg-orquidea-hondo/35 ring-1 ring-inset ring-orquidea/25 p-5">
                        
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full border border-orquidea bg-orquidea/15 text-orquidea-claro">
                                <Icono name="servicios" class="h-6 w-6" />
                            </span>
                            <StatusBadge :status="form.activo ? 'activo' : 'inactivo'" />
                        </div>

                        <p class="mt-4 font-display text-2xl leading-tight text-perla">
                            {{ form.nombre.trim() || 'Nombre del servicio' }}
                        </p>
                        <p class="mt-1 text-sm" :class="categoriaElegida ? 'text-perla' : 'text-niebla'">
                            {{ categoriaElegida || 'Sin categoría' }}
                        </p>
                        <p v-if="form.descripcion.trim()" class="mt-3 text-sm text-bruma">
                            {{ form.descripcion.trim() }}
                        </p>

                        <div class="mt-5 flex items-end justify-between gap-3 border-t border-hilo-fuerte pt-4">
                            <span class="inline-flex items-center gap-1.5 text-sm text-bruma">
                                <Icono name="pendiente" class="h-4 w-4 text-orquidea" />
                                {{ duracionVista || '— min' }}
                            </span>
                            <span class="font-display text-2xl tabular-nums text-perla">
                                {{ form.precio === '' ? 'Gs. —' : guaranies(form.precio) }}
                            </span>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

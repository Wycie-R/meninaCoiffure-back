<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import Dropdown from '../../Components/Dropdown.vue'
import { api, primerosErrores, duracion } from '../../api'

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
    'w-full min-w-0 min-h-12 rounded-lg border bg-[#34302B] px-4 py-3 text-sm font-medium text-[#F3ECE0] placeholder:font-normal placeholder:text-[#BEB3A6] shadow-[0_3px_12px_rgba(0,0,0,0.12)] [color-scheme:dark] transition-all duration-150 hover:border-[#9C8A74] focus-visible:border-[#D4C3A3] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D4C3A3]/30'

function inputBorder(campo) {
    return error(campo) ? 'border-[#F19F9F]' : 'border-[#7A6E61]'
}
</script>

<template>
    <AppLayout>
        <div class="min-h-[calc(100vh-73px)] bg-[#1C1B1A] lg:min-h-[calc(100vh-69px)]">
            <div class="mx-auto max-w-3xl px-6 py-8">
                <h1 class="text-2xl font-semibold text-[#F3ECE0]">
                    Nuevo servicio
                </h1>

                <form
                    class="mt-6 rounded-xl border border-[#47413B] bg-[#292725] p-5 sm:p-6"
                    novalidate
                    @submit.prevent="guardar"
                >
                    <div
                        v-if="errorGeneral"
                        role="alert"
                        class="mb-5 rounded-lg border border-[#6A3A3A] bg-[#3A2626] px-4 py-3 text-sm text-[#F0A8A8]"
                    >
                        {{ errorGeneral }}
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="nombre" class="mb-1.5 block text-sm font-medium text-[#F3ECE0]">
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
                            <p v-if="error('nombre')" id="nombre-error" class="mt-1.5 text-sm text-[#F0A8A8]">
                                {{ error('nombre') }}
                            </p>
                        </div>

                        <div class="min-w-0">
                            <p class="mb-1.5 text-sm font-medium text-[#F3ECE0]">
                                Categoría
                            </p>
                            <Dropdown
                                v-model="form.categoria_id"
                                :options="opcionesCategoria"
                                :placeholder="cargandoCategorias ? 'Cargando...' : 'Seleccionar categoría'"
                                :disabled="cargandoCategorias"
                                @update:model-value="limpiarServidor('categoria_id')"
                            />
                            <p v-if="error('categoria_id')" class="mt-1.5 text-sm text-[#F0A8A8]">
                                {{ error('categoria_id') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="duracion" class="mb-1.5 block text-sm font-medium text-[#F3ECE0]">
                                Duración (minutos)
                            </label>
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
                                :class="[inputClass, inputBorder('duracion_minutos')]"
                                @input="limpiarServidor('duracion_minutos')"
                            />
                            <p v-if="error('duracion_minutos')" id="duracion-error" class="mt-1.5 text-sm text-[#F0A8A8]">
                                {{ error('duracion_minutos') }}
                            </p>
                            <p v-else-if="duracionTexto" class="mt-1.5 text-sm text-[#BEB3A6]">
                                {{ duracionTexto }}
                            </p>
                        </div>

                        <div class="min-w-0">
                            <label for="precio" class="mb-1.5 block text-sm font-medium text-[#F3ECE0]">
                                Precio
                            </label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-[#BEB3A6]">
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
                            <p v-if="error('precio')" id="precio-error" class="mt-1.5 text-sm text-[#F0A8A8]">
                                {{ error('precio') }}
                            </p>
                            <p v-else id="precio-ayuda" class="mt-1.5 text-sm text-[#BEB3A6]">
                                Rige desde hoy.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label for="descripcion" class="mb-1.5 block text-sm font-medium text-[#F3ECE0]">
                            Descripción <span class="font-normal text-[#BEB3A6]">(opcional)</span>
                        </label>
                        <textarea
                            id="descripcion"
                            v-model="form.descripcion"
                            rows="3"
                            placeholder="Detalle breve que ayude a la recepción a explicar el servicio."
                            :class="[inputClass, 'border-[#7A6E61] resize-y']"
                        />
                    </div>

                    <label
                        class="mt-5 flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
                        :class="form.activo ? 'border-[#8C7961] bg-[#403629]' : 'border-[#5B5147] hover:border-[#8C7961]'"
                    >
                        <input
                            v-model="form.activo"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 shrink-0 accent-[#D4C3A3] [color-scheme:dark]"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-[#F3ECE0]">
                                Disponible para reservas
                            </span>
                            <span class="block text-sm text-[#BEB3A6]">
                                Si lo desmarcas, el servicio se guarda pero no aparece al crear reservas.
                            </span>
                        </span>
                    </label>

                    <div
                        class="mt-6 flex flex-col-reverse gap-3 border-t border-[#47413B] pt-5 sm:flex-row sm:justify-end"
                    >
                        <Button
                            label="Cancelar"
                            href="/servicios"
                            variant="secondary"
                            class="w-full sm:w-auto"
                        />
                        <Button
                            :label="enviando ? 'Guardando...' : 'Guardar servicio'"
                            type="submit"
                            :disabled="enviando"
                            class="w-full sm:w-auto"
                        />
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
